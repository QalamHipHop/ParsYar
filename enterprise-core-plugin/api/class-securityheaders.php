<?php
/**
 * Security Headers + Rate Limiting برای REST API.
 *
 * - Rate limit: شمارش درخواست بر اساس IP+endpoint، در یک پنجرهٔ زمانی کوتاه.
 *   داده‌ها در Transient (object-cache اگر موجود، مثلاً Redis) ذخیره می‌شوند
 *   تا بین چندین سرور PHP-FPM مشترک باشند.
 * - Security headers: Content-Security-Policy پایه + X-Content-Type-Options
 *   + X-Frame-Options + Referrer-Policy + Permissions-Policy.
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

final class SecurityHeaders
{
    /**
     * ثبت فیلتر rest_pre_serve_request برای rate-limit و headers.
     */
    public static function register(): void
    {
        add_filter('rest_pre_serve_request', [self::class, 'enforceRateLimit'], 5, 4);
        add_filter('rest_pre_echo_response',  [self::class, 'maybeInjectHeaders'], 10, 2);
    }

    /**
     * Rate limit middleware.
     *
     * @param bool $served
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error|bool
     */
    public static function enforceRateLimit($served, $server, $request)
    {
        if (!self::isEnterpriseRoute($request)) {
            return $served;
        }

        $limit  = (int) apply_filters('enterprise_rate_limit_per_minute', 60);
        $window = MINUTE_IN_SECONDS;
        $key    = self::bucketKey($request);

        $count = (int) get_transient($key);
        if ($count === 0) {
            // اولین درخواست در پنجره
            set_transient($key, 1, $window);
            $count = 1;
        } else {
            $count++;
            set_transient($key, $count, $window);
        }

        if ($count > $limit) {
            $retry = (int) $window;
            return new \WP_Error(
                'enterprise_rate_limited',
                sprintf(
                    /* translators: %d: max requests per minute */
                    __('تعداد درخواست‌ها بیش از حد مجاز است. سقف %d درخواست در دقیقه.', 'enterprise'),
                    $limit
                ),
                ['status' => 429, 'retry_after' => $retry]
            );
        }

        return $served;
    }

    /**
     * تزریق security headers به پاسخ REST.
     * این فیلتر پیش از ارسال پاسخ نهایی صدا زده می‌شود.
     *
     * @param array<string,mixed> $result
     * @param \WP_REST_Server $server
     * @return array<string,mixed>
     */
    public static function maybeInjectHeaders($result, $server)
    {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
            header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data: https:; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; frame-ancestors \'self\'; base-uri \'self\'; form-action \'self\'');
        }
        return $result;
    }

    /**
     * فقط routeهای خود enterprise را محدود می‌کنیم.
     */
    private static function isEnterpriseRoute(\WP_REST_Request $request): bool
    {
        $route = (string) $request->get_route();
        return strpos($route, '/' . \Enterprise\Bootstrap::NS . '/') === 0;
    }

    /**
     * ساخت کلید bucket. IP + method + مسیر.
     */
    private static function bucketKey(\WP_REST_Request $request): string
    {
        $ip   = self::clientIp();
        $hash = substr(md5($ip . '|' . $request->get_method() . '|' . $request->get_route()), 0, 16);
        return 'entrl_' . $hash;
    }

    /**
     * IP واقعی کلاینت با احترام به reverse proxy.
     */
    private static function clientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', (string) $_SERVER[$k])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
