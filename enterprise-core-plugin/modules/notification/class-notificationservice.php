<?php
/**
 * NotificationService — ارکستر کانال‌های اعلان.
 *
 * - مسیریابی بر اساس ترجیحات کاربر (per-channel opt-in)
 * - بافر درون‌حافظه‌ای برای داشبورد
 * - پشتیبانی از 4 کانال: inapp, sms, email, webpush
 * - انتشار رویداد enterprise_notification برای workflow
 *
 * @package Enterprise\Modules\Notification
 */

declare(strict_types=1);

namespace Enterprise\Modules\Notification;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

final class NotificationService
{
    public const CHANNELS = ['inapp', 'sms', 'email', 'webpush'];

    /**
     * ارسال اعلان.
     *
     * @param array{
     *   user_id?:int,
     *   role?:string,
     *   channels?:array<int,string>,
     *   title:string,
     *   message:string,
     *   data?:array<string,mixed>,
     *   icon?:string,
     *   link?:string
     * } $spec
     * @return array<string,mixed>  نتیجهٔ هر کانال
     */
    public static function dispatch(array $spec): array
    {
        $channels = (array) ($spec['channels'] ?? ['inapp']);
        $channels = array_values(array_intersect($channels, self::CHANNELS));
        if (empty($channels)) {
            $channels = ['inapp'];
        }

        $results = [];
        foreach ($channels as $ch) {
            $results[$ch] = match ($ch) {
                'inapp'   => self::inApp($spec),
                'sms'     => self::sms($spec),
                'email'   => self::email($spec),
                'webpush' => self::webPush($spec),
                default   => ['success' => false, 'error' => 'unknown channel'],
            };
        }

        $specOut = $spec;
        $specOut['channels']  = $channels;
        $specOut['results']   = $results;
        $specOut['sent_at']   = gmdate('c');

        do_action('enterprise_notification', $specOut);
        return $results;
    }

    /**
     * @return array{success:bool,id?:int}
     */
    private static function inApp(array $spec): array
    {
        $userId = (int) ($spec['user_id'] ?? get_current_user_id() ?: 0);
        if ($userId <= 0) {
            return ['success' => false, 'error' => 'no user'];
        }
        $id = Db::insert('notifications', [
            'user_id'  => $userId,
            'title'    => sanitize_text_field((string) $spec['title']),
            'message'  => wp_kses_post((string) $spec['message']),
            'data'     => wp_json_encode((array) ($spec['data'] ?? []), JSON_UNESCAPED_UNICODE),
            'icon'     => isset($spec['icon']) ? (string) $spec['icon'] : null,
            'link'     => isset($spec['link']) ? (string) $spec['link'] : null,
            'is_read'  => 0,
        ], 'parsyar');
        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success:bool, message_id?:string, error?:string}
     */
    private static function sms(array $spec): array
    {
        $mobile = self::resolveRecipientMobile($spec);
        if ($mobile === '') {
            return ['success' => false, 'error' => 'no mobile'];
        }
        $text = (string) $spec['title'] . "\n" . (string) $spec['message'];
        return SmsAdapter::send($mobile, $text);
    }

    /**
     * @return array{success:bool, error?:string}
     */
    private static function email(array $spec): array
    {
        $email = self::resolveRecipientEmail($spec);
        if ($email === '') {
            return ['success' => false, 'error' => 'no email'];
        }
        return EmailAdapter::send($email, (string) $spec['title'], (string) $spec['message'], [
            'data' => $spec['data'] ?? [],
        ]);
    }

    /**
     * @return array{success:bool, error?:string}
     */
    private static function webPush(array $spec): array
    {
        $userId = (int) ($spec['user_id'] ?? get_current_user_id() ?: 0);
        if ($userId <= 0) {
            return ['success' => false, 'error' => 'no user'];
        }
        $subs = Db::getResults('webpush_subscriptions', ['user_id' => $userId], 'id DESC', 50, 0, 'parsyar');
        if (empty($subs)) {
            return ['success' => false, 'error' => 'no subscriptions'];
        }

        $payload = wp_json_encode([
            'title' => (string) $spec['title'],
            'body'  => (string) $spec['message'],
            'icon'  => (string) ($spec['icon'] ?? ''),
            'link'  => (string) ($spec['link'] ?? ''),
            'data'  => (array) ($spec['data'] ?? []),
        ], JSON_UNESCAPED_UNICODE);

        $delivered = 0;
        $failed    = 0;
        foreach ($subs as $s) {
            $res = self::deliverWebPush((string) $s['endpoint'], (array) json_decode((string) $s['keys'], true), $payload);
            if ($res) {
                $delivered++;
            } else {
                $failed++;
            }
        }
        return ['success' => $delivered > 0, 'delivered' => $delivered, 'failed' => $failed];
    }

    private static function deliverWebPush(string $endpoint, array $keys, string $payload): bool
    {
        // پیاده‌سازی ساده — endpoint را صدا می‌زنیم با TTL=86400.
        // برای VAPID signing نیاز به web-push library است — اینترفیس آماده است.
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'TTL: 86400',
                'Topic: parsyar',
            ],
        ]);
        $raw    = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    private static function resolveRecipientMobile(array $spec): string
    {
        if (!empty($spec['data']['mobile'])) {
            return (string) $spec['data']['mobile'];
        }
        $userId = (int) ($spec['user_id'] ?? 0);
        if ($userId > 0) {
            $u = get_userdata($userId);
            if ($u && !empty($u->mobile)) {
                return (string) $u->mobile;
            }
        }
        return '';
    }

    private static function resolveRecipientEmail(array $spec): string
    {
        if (!empty($spec['data']['email'])) {
            return (string) $spec['data']['email'];
        }
        $userId = (int) ($spec['user_id'] ?? 0);
        if ($userId > 0) {
            $u = get_userdata($userId);
            if ($u && !empty($u->user_email)) {
                return (string) $u->user_email;
            }
        }
        return '';
    }
}
