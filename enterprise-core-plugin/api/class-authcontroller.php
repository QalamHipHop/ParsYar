<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;

/**
 * احراز هویت و کنترل دسترسی.
 */
final class AuthController
{
    public static function isAuthed(): bool
    {
        return is_user_logged_in();
    }

    public static function capRecords(): bool
    {
        return current_user_can('edit_enterprise_records');
    }

    public static function capAdmin(): bool
    {
        return current_user_can('manage_enterprise');
    }

    public static function capAccounting(): bool
    {
        return current_user_can('manage_enterprise_accounting') || current_user_can('manage_enterprise');
    }

    public static function capHR(): bool
    {
        return current_user_can('manage_enterprise_hr') || current_user_can('manage_enterprise');
    }

    public static function login(WP_REST_Request $req)
    {
        $params = $req->get_json_params();
        $user   = wp_authenticate(
            sanitize_text_field((string) ($params['username'] ?? '')),
            (string) ($params['password'] ?? '')
        );
        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Invalid login', ['status' => 401]);
        }
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, (bool) ($params['remember'] ?? false));
        $token = self::issueToken($user->ID);
        return rest_ensure_response([
            'token' => $token,
            'user'  => [
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'caps'         => array_keys((array) $user->allcaps),
            ],
        ]);
    }

    public static function me()
    {
        $u = wp_get_current_user();
        if (!$u->exists()) {
            return new WP_Error('not_authed', 'Not authenticated', ['status' => 401]);
        }
        return rest_ensure_response([
            'id'           => $u->ID,
            'display_name' => $u->display_name,
            'email'        => $u->user_email,
        ]);
    }

    /**
     * یک Token ساده (HMAC) برای دسترسی به API صادر می‌کند.
     * در production بهتر است از JWT با کلید قوی‌تر استفاده شود.
     */
    private static function issueToken(int $userId): string
    {
        $payload = [
            'u'   => $userId,
            'exp' => time() + DAY_IN_SECONDS,
        ];
        $body = base64_encode(wp_json_encode($payload));
        $sig  = hash_hmac('sha256', $body, wp_salt('auth'));
        return $body . '.' . $sig;
    }
}
