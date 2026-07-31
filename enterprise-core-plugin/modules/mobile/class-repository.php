<?php
/**
 * Mobile Repository — ساخت جدول device.
 *
 * @package Enterprise\Modules\Mobile
 */

declare(strict_types=1);

namespace Enterprise\Modules\Mobile;

defined('ABSPATH') || exit;

final class Repository
{
    public static function migrate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'parsyar_';

        $sql = [];

        $sql[] = "CREATE TABLE {$p}mobile_devices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contact_id BIGINT UNSIGNED NOT NULL,
            platform VARCHAR(16) NOT NULL,
            token VARCHAR(500) NOT NULL,
            app_version VARCHAR(32) NOT NULL DEFAULT '0.0.0',
            os_version VARCHAR(32) NULL,
            device_model VARCHAR(64) NULL,
            locale VARCHAR(8) NOT NULL DEFAULT 'fa-IR',
            push_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_seen_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uk_token (token(191)),
            KEY idx_contact (contact_id),
            KEY idx_active (contact_id, is_active),
            KEY idx_platform (platform)
        ) {$charset};";

        foreach ($sql as $stmt) {
            dbDelta($stmt);
        }
    }
}
