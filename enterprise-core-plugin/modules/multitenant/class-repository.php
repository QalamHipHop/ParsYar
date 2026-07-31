<?php
/**
 * Multitenant Repository — بوت‌استرپ، migration helper و cache invalidation.
 *
 * @package Enterprise\Modules\Multitenant
 */

declare(strict_types=1);

namespace Enterprise\Modules\Multitenant;

defined('ABSPATH') || exit;

use Enterprise\Cache;

final class Repository
{
    public const CACHE_GROUP = 'parsyar_multitenant';
    public const CACHE_TTL   = 300; // 5 min

    public static function boot(): void
    {
        add_action('init', [self::class, 'warmup'], 6);
        add_action('enterprise_daily', [self::class, 'pruneSoftDeleted']);
    }

    public static function warmup(): void
    {
        // هیچ کاری لازم نیست در boot؛ فقط گرم کردن cache پیش‌فرض
        self::getCachedDefaultTenant();
    }

    public static function getCachedDefaultTenant(): ?array
    {
        $cacheKey = 'default_tenant';
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if ($cached !== false) {
            return is_array($cached) ? $cached : null;
        }
        $tenants = Tenant::list(['status' => 'active'], 1, 0, 'id ASC');
        $default = $tenants[0] ?? null;
        wp_cache_set($cacheKey, $default ?: false, self::CACHE_GROUP, self::CACHE_TTL);
        return $default;
    }

    public static function invalidateCache(): void
    {
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public static function pruneSoftDeleted(): void
    {
        global $wpdb;
        // آرشیوهای بالای ۹۰ روز واقعاً حذف شوند
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . Tenant::table() . " WHERE status = 'archived' AND updated_at < UTC_TIMESTAMP() - INTERVAL %d DAY",
            90
        ));
    }

    /**
     * مهاجرت جداول multitenant. در activation اجرا می‌شود.
     */
    public static function migrate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'parsyar_';

        $sql = [];

        $sql[] = "CREATE TABLE {$p}tenants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL UNIQUE,
            legal_name VARCHAR(190) NULL,
            national_id VARCHAR(32) NULL,
            economic_code VARCHAR(64) NULL,
            logo_url VARCHAR(500) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            plan VARCHAR(32) NOT NULL DEFAULT 'starter',
            settings LONGTEXT NULL,
            branding LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            KEY idx_status (status),
            KEY idx_plan (plan),
            KEY idx_name (name)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}branches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(64) NOT NULL UNIQUE,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            code VARCHAR(64) NULL,
            parent_id BIGINT UNSIGNED NULL,
            manager_id BIGINT UNSIGNED NULL,
            address TEXT NULL,
            city VARCHAR(64) NULL,
            province VARCHAR(64) NULL,
            phone VARCHAR(32) NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            KEY idx_tenant (tenant_id),
            KEY idx_default (tenant_id, is_default),
            KEY idx_active (tenant_id, is_active),
            KEY idx_code (tenant_id, code)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}memberships (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'member',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uk_user_tenant_branch (user_id, tenant_id, branch_id),
            KEY idx_user (user_id),
            KEY idx_tenant (tenant_id),
            KEY idx_role (tenant_id, role)
        ) {$charset};";

        foreach ($sql as $stmt) {
            dbDelta($stmt);
        }
    }
}
