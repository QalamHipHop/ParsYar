<?php
declare(strict_types=1);

namespace Enterprise\Modules\Portal;

defined('ABSPATH') || exit;

/**
 * Portal module bootstrap — wires up REST, JWT secret warmup, and
 * any required background tasks.
 *
 * @package Enterprise\Modules\Portal
 */
final class PortalModule
{
    public static function boot(): void
    {
        // Warm up JWT secret on first request so the portal can sign tokens
        // immediately (avoids "no secret yet" race on first magic link).
        add_action('init', [self::class, 'warmup'], 5);

        // Optional: prune expired magic-link tokens and sessions daily
        add_action('enterprise_daily', [self::class, 'pruneExpired']);

        // Schedule the daily event if not scheduled
        if (!wp_next_scheduled('enterprise_daily')) {
            wp_schedule_event(time() + 60, 'daily', 'enterprise_daily');
        }
    }

    public static function warmup(): void
    {
        // Triggers lazy creation of JWT secret and VAPID keys
        AuthService::jwtSecret();
        AuthService::vapidKeys();
    }

    /**
     * پاک‌سازی روزانه: توکن‌های مصرف‌شده/منقضی‌شده و سشن‌های منقضی.
     */
    public static function pruneExpired(): void
    {
        global $wpdb;
        $p = $wpdb->prefix . 'parsyar_';
        $wpdb->query("DELETE FROM {$p}portal_tokens WHERE (consumed = 1 AND consumed_at < UTC_TIMESTAMP() - INTERVAL 1 DAY) OR expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY");
        $wpdb->query("DELETE FROM {$p}portal_sessions WHERE refresh_exp < UTC_TIMESTAMP() - INTERVAL 1 DAY");
        $wpdb->query("DELETE FROM {$p}portal_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY");
    }
}
