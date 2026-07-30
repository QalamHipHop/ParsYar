<?php
declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

/**
 * نصب، فعال‌سازی، و مهاجرت‌های دیتابیس.
 */
final class Installer
{
    public static function activate(): void
    {
        self::ensureSchema();
        self::seedDefaults();
        self::ensureCapabilities();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function ensureSchema(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix . 'ent_';

        $sql = [];
        $sql[] = "CREATE TABLE {$prefix}objects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            api_name VARCHAR(64) NOT NULL UNIQUE,
            label VARCHAR(128) NOT NULL,
            label_plural VARCHAR(128) NOT NULL,
            description TEXT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}object_fields (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            object_id BIGINT UNSIGNED NOT NULL,
            api_name VARCHAR(64) NOT NULL,
            label VARCHAR(128) NOT NULL,
            type VARCHAR(32) NOT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            is_unique TINYINT(1) NOT NULL DEFAULT 0,
            default_value TEXT NULL,
            options LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_field (object_id, api_name),
            KEY idx_object (object_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}object_relations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_object_id BIGINT UNSIGNED NOT NULL,
            child_object_id BIGINT UNSIGNED NOT NULL,
            type ENUM('lookup','master_detail') NOT NULL DEFAULT 'lookup',
            api_name VARCHAR(64) NOT NULL,
            label VARCHAR(128) NOT NULL,
            on_delete ENUM('restrict','cascade','setnull') NOT NULL DEFAULT 'restrict',
            KEY idx_parent (parent_object_id),
            KEY idx_child (child_object_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            object_id BIGINT UNSIGNED NOT NULL,
            data LONGTEXT NOT NULL,
            owner_id BIGINT UNSIGNED NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_object (object_id),
            KEY idx_owner (owner_id),
            KEY idx_status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(32) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}journal_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_no VARCHAR(32) NOT NULL UNIQUE,
            entry_date DATE NOT NULL,
            description VARCHAR(512) NULL,
            source VARCHAR(64) NULL,
            source_ref VARCHAR(128) NULL,
            status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'posted',
            posted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_date (entry_date),
            KEY idx_source (source, source_ref)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}journal_lines (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            debit DECIMAL(20,2) NOT NULL DEFAULT 0,
            credit DECIMAL(20,2) NOT NULL DEFAULT 0,
            description VARCHAR(512) NULL,
            KEY idx_entry (entry_id),
            KEY idx_account (account_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_id BIGINT UNSIGNED NULL,
            object VARCHAR(64) NOT NULL,
            object_id BIGINT UNSIGNED NULL,
            action VARCHAR(32) NOT NULL,
            diff LONGTEXT NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_object (object, object_id),
            KEY idx_actor (actor_id),
            KEY idx_time (created_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}workflows (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            trigger_event VARCHAR(64) NOT NULL,
            graph_json LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}leads (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_id BIGINT UNSIGNED NULL,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(32) NULL,
            source VARCHAR(64) NULL,
            score INT NOT NULL DEFAULT 0,
            stage ENUM('new','contacted','qualified','lost','won') NOT NULL DEFAULT 'new',
            owner_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            unit VARCHAR(16) NOT NULL DEFAULT 'عدد',
            cost DECIMAL(20,2) NOT NULL DEFAULT 0,
            price DECIMAL(20,2) NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0,
            taxable TINYINT(1) NOT NULL DEFAULT 1
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}invoices (
            id BIGINT UNSIGNED AUTO_INSIGNED PRIMARY KEY,
            invoice_no VARCHAR(32) NOT NULL UNIQUE,
            customer_record_id BIGINT UNSIGNED NULL,
            issue_date DATE NOT NULL,
            due_date DATE NULL,
            subtotal DECIMAL(20,2) NOT NULL DEFAULT 0,
            tax DECIMAL(20,2) NOT NULL DEFAULT 0,
            total DECIMAL(20,2) NOT NULL DEFAULT 0,
            status ENUM('draft','issued','paid','void') NOT NULL DEFAULT 'issued',
            tax_invoice_uid VARCHAR(64) NULL
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}employees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            national_code VARCHAR(16) NOT NULL UNIQUE,
            full_name VARCHAR(255) NOT NULL,
            base_salary DECIMAL(20,2) NOT NULL DEFAULT 0,
            hire_date DATE NOT NULL,
            position VARCHAR(128) NULL
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}attendance (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_id BIGINT UNSIGNED NOT NULL,
            work_date DATE NOT NULL,
            check_in TIME NULL,
            check_out TIME NULL,
            overtime_minutes INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_emp_date (employee_id, work_date)
        ) {$charset};";

        foreach ($sql as $q) {
            dbDelta($q);
        }

        update_option('enterprise_db_version', self::VERSION);
    }

    public static function seedDefaults(): void
    {
        if (get_option('enterprise_seeded') === 'yes') {
            return;
        }
        \Enterprise\Modules\Accounting\ChartOfAccounts::installDefaults();
        \Enterprise\Modules\Objects\Bootstrap::installSystemObjects();
        update_option('enterprise_seeded', 'yes');
    }

    /**
     * ساخت Flat Table برای همه اشیاء موجود (migration).
     */
    public static function syncAllObjectTables(): int
    {
        $count = 0;
        $rows  = \Enterprise\Support\Db::getResults('objects', [], 'id ASC', 1000, 0);
        foreach ($rows as $obj) {
            $fields = \Enterprise\Modules\Objects\ObjectEngine::getFields((int) $obj['id']);
            \Enterprise\Modules\Objects\SchemaBuilder::syncObjectTable(
                (int) $obj['id'],
                (string) $obj['api_name'],
                $fields
            );
            $count++;
        }
        return $count;
    }

    private static function ensureCapabilities(): void
    {
        $role = get_role('administrator');
        if ($role) {
            foreach ([
                'manage_enterprise',
                'edit_enterprise_records',
                'view_enterprise_reports',
                'manage_enterprise_accounting',
                'manage_enterprise_hr',
            ] as $cap) {
                $role->add_cap($cap);
            }
        }
    }
}
