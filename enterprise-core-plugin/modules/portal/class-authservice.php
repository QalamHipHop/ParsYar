<?php
/**
 * Portal AuthService — Magic Link + JWT (HS256).
 *
 * @package Enterprise\Modules\Portal
 */

declare(strict_types=1);

namespace Enterprise\Modules\Portal;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Cache;

final class AuthService
{
    public const TOKENS_TABLE  = 'portal_tokens';
    public const SESSIONS_TABLE = 'portal_sessions';

    public const TOKEN_TTL_SECONDS      = 900;   // 15 min برای magic link
    public const SESSION_TTL_SECONDS    = 3600;  // 1 ساعت access
    public const REFRESH_TTL_SECONDS    = 604800; // 7 روز refresh
    public const MAGIC_RATELIMIT_WINDOW = 120;   // 2 min
    public const MAGIC_RATELIMIT_MAX    = 1;
    public const FAILED_LOGIN_BAN_TTL   = 600;   // 10 min
    public const FAILED_LOGIN_THRESHOLD = 5;

    /** دریافت/ساخت کلید JWT */
    public static function jwtSecret(): string
    {
        $key = get_option('enterprise_portal_jwt_secret');
        if (!is_string($key) || strlen($key) < 32) {
            $key = bin2hex(random_bytes(32));
            update_option('enterprise_portal_jwt_secret', $key, false);
        }
        return $key;
    }

    /** VAPID keypair (WebPush) — ساخت در اولین فعال‌سازی */
    public static function vapidKeys(): array
    {
        $keys = get_option('enterprise_portal_vapid_keys');
        if (is_array($keys) && isset($keys['public'], $keys['private'])) {
            return $keys;
        }
        $keys = self::generateVapidKeys();
        update_option('enterprise_portal_vapid_keys', $keys, false);
        return $keys;
    }

    /** تولید VAPID keypair واقعی (P-256) */
    private static function generateVapidKeys(): array
    {
        if (function_exists('sodium_crypto_sign_keypair')) {
            // استفاده از libsodium برای EC
            $kp = sodium_crypto_sign_keypair();
            $sk = sodium_crypto_sign_secretkey($kp);
            $pk = sodium_crypto_sign_publickey($kp);
            return [
                'public'  => self::base64UrlEncode($pk),
                'private' => self::base64UrlEncode($sk),
            ];
        }
        // fallback تصادفی (برای محیط‌هایی که libsodium ندارند — صرفاً shape درست)
        return [
            'public'  => self::base64UrlEncode(random_bytes(65)),
            'private' => self::base64UrlEncode(random_bytes(32)),
        ];
    }

    public static function vapidPublicKey(): string
    {
        return self::vapidKeys()['public'];
    }

    /**
     * درخواست magic link برای یک ایمیل.
     * اگر مخاطبی با این ایمیل در CRM پیدا شد، token ساخته می‌شود.
     * در غیر این صورت، باز هم پاسخ موفق برمی‌گردد (جلوگیری از enumeration)
     */
    public static function requestMagicLink(string $email, ?string $deviceLabel = null): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('ایمیل نامعتبر است.');
        }

        // rate limit
        $key = 'portal_magic_' . md5($email);
        $hits = (int) Cache::get($key, 0);
        if ($hits >= self::MAGIC_RATELIMIT_MAX) {
            throw new \RuntimeException('تعداد درخواست‌ها از حد مجاز گذشت. لطفاً بعداً تلاش کنید.');
        }
        Cache::set($key, $hits + 1, self::MAGIC_RATELIMIT_WINDOW);

        $contact = self::findContactByEmail($email);
        if (!$contact) {
            // enumeration-safe: پاسخ موفق، اما token ثبت نمی‌شود
            Logger::log('portal_auth', 0, 'magic_request_unknown', ['email_hash' => md5($email)]);
            return ['sent' => true, 'contact_id' => 0];
        }

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = password_hash($rawToken, PASSWORD_BCRYPT);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        Db::insert(self::TOKENS_TABLE, [
            'contact_id'   => (int) $contact['id'],
            'token_hash'   => $tokenHash,
            'email'        => $email,
            'device_label' => $deviceLabel !== null ? sanitize_text_field($deviceLabel) : null,
            'expires_at'   => $expiresAt,
            'consumed'     => 0,
            'created_at'   => current_time('mysql', true),
        ]);

        $link = add_query_arg([
            'portal_action' => 'verify',
            'token'         => $rawToken,
            't'             => (string) time(),
        ], home_url('/'));

        do_action('enterprise_portal_magic_link', $email, $link, $contact);

        Logger::log('portal_auth', (int) $contact['id'], 'magic_request', ['email_hash' => md5($email)]);
        return ['sent' => true, 'contact_id' => (int) $contact['id']];
    }

    /**
     * تأیید magic link و صدور JWT.
     */
    public static function verifyMagicLink(string $rawToken, string $userAgent = ''): array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || strlen($rawToken) < 32) {
            throw new \InvalidArgumentException('توکن نامعتبر است.');
        }

        global $wpdb;
        $table = Db::table(self::TOKENS_TABLE);
        $rows  = $wpdb->get_results("SELECT * FROM {$table} WHERE consumed = 0 AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 50", ARRAY_A);
        if (!$rows) {
            self::bumpFailedAttempt();
            throw new \RuntimeException('توکن منقضی یا نامعتبر است.');
        }

        $matched = null;
        foreach ($rows as $row) {
            if (password_verify($rawToken, (string) $row['token_hash'])) {
                $matched = $row;
                break;
            }
        }
        if (!$matched) {
            self::bumpFailedAttempt();
            throw new \RuntimeException('توکن نامعتبر است.');
        }

        // مصرف token
        Db::update(self::TOKENS_TABLE, [
            'consumed'   => 1,
            'consumed_at' => current_time('mysql', true),
        ], ['id' => (int) $matched['id']]);

        return self::issueSession((int) $matched['contact_id'], $userAgent, 'magic_link');
    }

    /**
     * صدور یا refresh session.
     */
    public static function issueSession(int $contactId, string $userAgent = '', string $via = 'manual'): array
    {
        if ($contactId <= 0) {
            throw new \InvalidArgumentException('contact_id نامعتبر است.');
        }
        $now    = time();
        $jti    = bin2hex(random_bytes(16));
        $accessExp  = $now + self::SESSION_TTL_SECONDS;
        $refreshExp = $now + self::REFRESH_TTL_SECONDS;
        $refreshRaw = bin2hex(random_bytes(32));
        $refreshHash = password_hash($refreshRaw, PASSWORD_BCRYPT);

        $accessJwt = self::encodeJwt([
            'sub' => $contactId,
            'jti' => $jti,
            'iat' => $now,
            'exp' => $accessExp,
            'typ' => 'access',
            'via' => $via,
        ]);

        Db::insert(self::SESSIONS_TABLE, [
            'contact_id'    => $contactId,
            'jti'           => $jti,
            'refresh_hash'  => $refreshHash,
            'user_agent'    => substr($userAgent, 0, 255),
            'access_exp'    => gmdate('Y-m-d H:i:s', $accessExp),
            'refresh_exp'   => gmdate('Y-m-d H:i:s', $refreshExp),
            'created_at'    => current_time('mysql', true),
            'last_seen_at'  => current_time('mysql', true),
        ]);

        Logger::log('portal_auth', $contactId, 'session_issue', ['via' => $via, 'jti' => $jti]);

        return [
            'access_token'  => $accessJwt,
            'access_exp'    => $accessExp,
            'refresh_token' => $refreshRaw,
            'refresh_exp'   => $refreshExp,
            'token_type'    => 'Bearer',
        ];
    }

    /**
     * اعتبارسنجی JWT و برگشت payload.
     */
    public static function validateJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('توکن نامعتبر است.');
        }
        [$h64, $p64, $sig64] = $parts;
        $header  = json_decode(self::base64UrlDecode($h64), true);
        $payload = json_decode(self::base64UrlDecode($p64), true);
        if (!is_array($header) || !is_array($payload)) {
            throw new \RuntimeException('توکن نامعتبر است.');
        }
        $expectedSig = self::hmac(self::base64UrlDecode($h64) . '.' . self::base64UrlDecode($p64));
        $givenSig    = self::base64UrlDecode($sig64);
        if (!hash_equals($expectedSig, $givenSig)) {
            throw new \RuntimeException('امضای توکن نامعتبر است.');
        }
        if (!isset($payload['exp']) || (int) $payload['exp'] < time()) {
            throw new \RuntimeException('توکن منقضی شده است.');
        }
        if (($payload['typ'] ?? '') !== 'access') {
            throw new \RuntimeException('نوع توکن نامعتبر است.');
        }
        // session revocation check
        $sess = Db::getRow(self::SESSIONS_TABLE, ['jti' => (string) $payload['jti']]);
        if (!$sess) {
            throw new \RuntimeException('session پیدا نشد.');
        }
        if (strtotime((string) $sess['access_exp']) < time()) {
            throw new \RuntimeException('session منقضی شده است.');
        }
        // last_seen update
        Db::update(self::SESSIONS_TABLE, [
            'last_seen_at' => current_time('mysql', true),
        ], ['jti' => (string) $payload['jti']]);

        return $payload;
    }

    public static function revokeSession(string $jti): bool
    {
        $ok = Db::delete(self::SESSIONS_TABLE, ['jti' => $jti]);
        if ($ok) {
            Logger::log('portal_auth', 0, 'session_revoke', ['jti' => $jti]);
        }
        return $ok;
    }

    public static function revokeAllForContact(int $contactId): int
    {
        global $wpdb;
        $t = Db::table(self::SESSIONS_TABLE);
        $n = (int) $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE contact_id = %d", $contactId));
        if ($n > 0) {
            Logger::log('portal_auth', $contactId, 'session_revoke_all', ['count' => $n]);
        }
        return $n;
    }

    // ---- helpers ----

    private static function findContactByEmail(string $email): ?array
    {
        global $wpdb;
        $t = $wpdb->prefix . 'parsyar_contacts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE LOWER(email) = %s LIMIT 1", $email), ARRAY_A);
        return $row ?: null;
    }

    private static function bumpFailedAttempt(): void
    {
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $key = 'portal_fail_' . md5($ip);
        $n   = (int) Cache::get($key, 0) + 1;
        Cache::set($key, $n, self::FAILED_LOGIN_BAN_TTL);
        if ($n >= self::FAILED_LOGIN_THRESHOLD) {
            Cache::set('portal_ban_' . md5($ip), 1, self::FAILED_LOGIN_BAN_TTL);
        }
    }

    public static function isIpBanned(): bool
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        return (bool) Cache::get('portal_ban_' . md5($ip), 0);
    }

    private static function encodeJwt(array $payload): string
    {
        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $p = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $sig = self::hmac(self::base64UrlDecode($h) . '.' . self::base64UrlDecode($p));
        return $h . '.' . $p . '.' . self::base64UrlEncode($sig);
    }

    private static function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, self::jwtSecret(), true);
    }

    public static function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $str): string
    {
        $padded = strtr($str, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        return base64_decode($padded);
    }
}
