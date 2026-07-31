<?php
declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

/**
 * نصب، فعال‌سازی، و مهاجرت‌های دیتابیس.
 */
final class Installer
{
    public const VERSION = '1.5.0';

    public static function activate(): void
    {
        self::ensureSchema();
        self::seedDefaults();
        self::ensureCapabilities();
        self::runMigrations();
        flush_rewrite_rules();
    }

    /**
     * اجرای مهاجرت‌های تدریجی برای سایت‌هایی که قبلاً نصب بوده‌اند.
     */
    public static function runMigrations(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_fiscal_periods';

        // اگه جدول fiscal_periods خالیه (نصب قدیمی یا تازه)، seed کن
        // dbDelta در فعال‌سازی مجدد، جدول را می‌سازد ولی seed شرطی است.
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists && $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === '0') {
            self::seedFiscalPeriod();
        }

        // مهاجرت v1.2.1: ساخت جدول‌های دامنه‌ای parsyar_* (workflows, runs, logs, webpush)
        // که در نسخه‌های قبلی installer ساخته نمی‌شد ولی Repository به آن‌ها ارجاع می‌دهد.
        self::migrateParsyarTables();

        // مهاجرت v1.2.1: افزودن ستون tax_invoice_uid به ent_invoices (مورد نیاز Moodian client)
        self::migrateInvoiceTaxUid();

        // اگه جدول accounts خالیه (نصب قدیمی‌ای که seed شکست خورده)، seed کن
        self::seedAccountsIfEmpty();

        // نسخه را ذخیره کن
        update_option('enterprise_db_version', self::VERSION);
    }

    /**
     * ساخت ایمن جدول‌های parsyar_* (workflows / workflow_runs / workflow_logs / webpush_subscriptions).
     * این جدول‌ها در نسخه‌های قبلی installer ساخته نمی‌شد و باعث fatal error
     * در Repository::startRun/log می‌شد. dbDelta در برابر CREATE TABLE IF NOT EXISTS
     * ایمن است و برای سایت‌های از قبل نصب‌شده، migration idempotent است.
     */
    private static function migrateParsyarTables(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $parsyar = $wpdb->prefix . 'parsyar_';

        $sql = [];
        $sql[] = "CREATE TABLE {$parsyar}workflows (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            trigger_event VARCHAR(64) NOT NULL,
            graph_json LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            description TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            KEY idx_trigger (trigger_event),
            KEY idx_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$parsyar}workflow_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workflow_id BIGINT UNSIGNED NOT NULL,
            event VARCHAR(64) NOT NULL,
            payload LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'running',
            error TEXT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            KEY idx_workflow (workflow_id),
            KEY idx_status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$parsyar}workflow_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workflow_id BIGINT UNSIGNED NOT NULL,
            run_id BIGINT UNSIGNED NULL,
            level VARCHAR(16) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_workflow (workflow_id),
            KEY idx_run (run_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$parsyar}webpush_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh TEXT NULL,
            auth TEXT NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            KEY idx_user (user_id)
        ) {$charset};";

        foreach ($sql as $stmt) {
            dbDelta($stmt);
        }
    }

    /**
     * افزودن ستون tax_invoice_uid به ent_invoices در سایت‌هایی که قبل از v1.2.1
     * این ستون را ندارند. Moodian client برای ذخیرهٔ شناسهٔ مالیاتی برگشتی از سامانهٔ مؤدیان
     * به این ستون نیاز دارد. عملیات idempotent است.
     */
    private static function migrateInvoiceTaxUid(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_invoices';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) {
            return;
        }
        $col = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table} LIKE %s",
            'tax_invoice_uid'
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN tax_invoice_uid VARCHAR(64) NULL AFTER uuid, ADD UNIQUE KEY uniq_tax_uid (tax_invoice_uid)");
        }
    }

    /**
     * در سایت‌هایی که قبل از v1.2.0 نصب شده‌اند و seeding آن‌ها شکست خورده
     * (یا قبلاً enterprise_seeded=yes گذاشته شده ولی accounts خالی مانده)،
     * اکنون Chart of Accounts را seed می‌کنیم.
     */
    private static function seedAccountsIfEmpty(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_accounts';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) {
            return;
        }
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count === 0) {
            \Enterprise\Modules\Accounting\ChartOfAccounts::installDefaults();
        }
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

        $sql[] = "CREATE TABLE {$prefix}relations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_type VARCHAR(64) NOT NULL,
            from_id BIGINT UNSIGNED NOT NULL,
            to_type VARCHAR(64) NOT NULL,
            to_id BIGINT UNSIGNED NOT NULL,
            relation_type VARCHAR(64) NOT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_from (from_type, from_id),
            KEY idx_to (to_type, to_id),
            KEY idx_relation (relation_type),
            UNIQUE KEY uniq_link (from_type, from_id, to_type, to_id, relation_type)
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
            nature ENUM('debit','credit') NOT NULL DEFAULT 'debit',
            parent_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            balance_debit DECIMAL(20,2) NOT NULL DEFAULT 0,
            balance_credit DECIMAL(20,2) NOT NULL DEFAULT 0,
            description TEXT NULL,
            deleted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_type (type),
            KEY idx_parent (parent_id),
            KEY idx_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}journal_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_no VARCHAR(32) NOT NULL UNIQUE,
            entry_date DATE NOT NULL,
            description VARCHAR(512) NULL,
            source VARCHAR(64) NULL,
            source_ref VARCHAR(128) NULL,
            fiscal_period_id BIGINT UNSIGNED NULL,
            company_id BIGINT UNSIGNED NULL DEFAULT 1,
            branch_id BIGINT UNSIGNED NULL,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            total_debit DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_credit DECIMAL(20,2) NOT NULL DEFAULT 0,
            status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'posted',
            meta LONGTEXT NULL,
            posted_by BIGINT UNSIGNED NULL,
            posted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reversed_by BIGINT UNSIGNED NULL,
            reversed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_date (entry_date),
            KEY idx_source (source, source_ref),
            KEY idx_period (fiscal_period_id),
            KEY idx_company (company_id),
            KEY idx_branch (branch_id),
            KEY idx_status (status),
            KEY idx_posted_by (posted_by)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}journal_lines (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            debit DECIMAL(20,4) NOT NULL DEFAULT 0,
            credit DECIMAL(20,4) NOT NULL DEFAULT 0,
            description VARCHAR(512) NULL,
            cost_center VARCHAR(64) NULL,
            project_id BIGINT UNSIGNED NULL,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            fx_rate DECIMAL(20,6) NOT NULL DEFAULT 1.0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_entry (entry_id),
            KEY idx_account (account_id),
            KEY idx_project (project_id),
            KEY idx_cost_center (cost_center)
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

        $sql[] = "CREATE TABLE {$prefix}warehouses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(32) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NULL,
            type ENUM('main','branch','virtual','consignment','transit') NOT NULL DEFAULT 'main',
            manager_user_id BIGINT UNSIGNED NULL,
            branch_id BIGINT UNSIGNED NULL,
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            phone VARCHAR(32) NULL,
            address_line1 VARCHAR(255) NULL,
            address_line2 VARCHAR(255) NULL,
            city VARCHAR(128) NULL,
            province VARCHAR(128) NULL,
            postal_code VARCHAR(20) NULL,
            country CHAR(2) NOT NULL DEFAULT 'IR',
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_company (company_id),
            KEY idx_type (type),
            KEY idx_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(64) NOT NULL UNIQUE,
            barcode VARCHAR(64) NULL,
            name VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NULL,
            description TEXT NULL,
            category_id BIGINT UNSIGNED NULL,
            brand VARCHAR(128) NULL,
            product_type ENUM('physical','service','digital','subscription','bundle') NOT NULL DEFAULT 'physical',
            unit VARCHAR(16) NOT NULL DEFAULT 'عدد',
            unit_symbol VARCHAR(8) NULL,
            weight_kg DECIMAL(10,3) NULL,
            volume_m3 DECIMAL(10,4) NULL,
            cost DECIMAL(20,2) NOT NULL DEFAULT 0,
            cost_method ENUM('fifo','lifo','wac','specific') NOT NULL DEFAULT 'wac',
            price DECIMAL(20,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            taxable TINYINT(1) NOT NULL DEFAULT 1,
            track_stock TINYINT(1) NOT NULL DEFAULT 1,
            lot_tracked TINYINT(1) NOT NULL DEFAULT 0,
            serial_tracked TINYINT(1) NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0,
            min_stock INT NOT NULL DEFAULT 0,
            max_stock INT NOT NULL DEFAULT 0,
            reorder_point INT NOT NULL DEFAULT 0,
            image_url VARCHAR(512) NULL,
            tags JSON NULL,
            custom_fields JSON NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_category (category_id),
            KEY idx_brand (brand),
            KEY idx_active (is_active),
            KEY idx_barcode (barcode)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}product_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            name_en VARCHAR(255) NULL,
            slug VARCHAR(128) NOT NULL UNIQUE,
            description TEXT NULL,
            icon VARCHAR(64) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_parent (parent_id),
            KEY idx_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}stock_movements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            warehouse_id BIGINT UNSIGNED NOT NULL,
            type ENUM('in','out','transfer','adjust','reserve','release') NOT NULL,
            quantity DECIMAL(20,4) NOT NULL DEFAULT 0,
            unit_cost DECIMAL(20,4) NOT NULL DEFAULT 0,
            reason VARCHAR(255) NULL,
            source VARCHAR(64) NULL,
            source_ref VARCHAR(128) NULL,
            lot_no VARCHAR(64) NULL,
            serial_no VARCHAR(128) NULL,
            expires_at DATE NULL,
            meta LONGTEXT NULL,
            movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            KEY idx_product (product_id),
            KEY idx_warehouse (warehouse_id),
            KEY idx_type (type),
            KEY idx_date (movement_date),
            KEY idx_source (source, source_ref),
            KEY idx_lot (product_id, warehouse_id, lot_no)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}invoices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            invoice_no VARCHAR(32) NOT NULL UNIQUE,
            order_id BIGINT UNSIGNED NULL,
            customer_record_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(255) NULL,
            customer_nid VARCHAR(32) NULL,
            customer_economic_code VARCHAR(32) NULL,
            issue_date DATE NOT NULL,
            due_date DATE NULL,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            subtotal DECIMAL(20,2) NOT NULL DEFAULT 0,
            discount DECIMAL(20,2) NOT NULL DEFAULT 0,
            tax DECIMAL(20,2) NOT NULL DEFAULT 0,
            shipping DECIMAL(20,2) NOT NULL DEFAULT 0,
            total DECIMAL(20,2) NOT NULL DEFAULT 0,
            paid DECIMAL(20,2) NOT NULL DEFAULT 0,
            status ENUM('draft','issued','partial','paid','overdue','void','cancelled') NOT NULL DEFAULT 'issued',
            items LONGTEXT NULL,
            moodian_status VARCHAR(32) NULL,
            moodian_reference VARCHAR(64) NULL,
            moodian_error TEXT NULL,
            moodian_sent_at DATETIME NULL,
            owner_id BIGINT UNSIGNED NULL,
            branch_id BIGINT UNSIGNED NULL,
            company_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_customer (customer_record_id),
            KEY idx_status (status),
            KEY idx_due (due_date),
            KEY idx_moodian (moodian_status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            number VARCHAR(32) NOT NULL UNIQUE,
            status ENUM('draft','pending','confirmed','fulfilled','invoiced','cancelled','closed') NOT NULL DEFAULT 'draft',
            customer_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(255) NULL,
            deal_id BIGINT UNSIGNED NULL,
            quote_id BIGINT UNSIGNED NULL,
            invoice_id BIGINT UNSIGNED NULL,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            subtotal DECIMAL(20,2) NOT NULL DEFAULT 0,
            discount DECIMAL(20,2) NOT NULL DEFAULT 0,
            tax DECIMAL(20,2) NOT NULL DEFAULT 0,
            shipping DECIMAL(20,2) NOT NULL DEFAULT 0,
            total DECIMAL(20,2) NOT NULL DEFAULT 0,
            paid DECIMAL(20,2) NOT NULL DEFAULT 0,
            shipping_address TEXT NULL,
            billing_address TEXT NULL,
            notes TEXT NULL,
            items LONGTEXT NULL,
            meta LONGTEXT NULL,
            invoiced_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            cancel_reason VARCHAR(255) NULL,
            owner_id BIGINT UNSIGNED NULL,
            branch_id BIGINT UNSIGNED NULL,
            company_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_customer (customer_id),
            KEY idx_status (status),
            KEY idx_deal (deal_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            number VARCHAR(32) NOT NULL UNIQUE,
            method VARCHAR(32) NOT NULL,
            status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'paid',
            amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            invoice_id BIGINT UNSIGNED NULL,
            order_id BIGINT UNSIGNED NULL,
            customer_id BIGINT UNSIGNED NULL,
            gateway_ref VARCHAR(128) NULL,
            tracking_no VARCHAR(128) NULL,
            card_no VARCHAR(32) NULL,
            paid_at DATETIME NULL,
            note TEXT NULL,
            refunded_at DATETIME NULL,
            refund_amount DECIMAL(20,2) NULL,
            refund_reason VARCHAR(255) NULL,
            meta LONGTEXT NULL,
            owner_id BIGINT UNSIGNED NULL,
            branch_id BIGINT UNSIGNED NULL,
            company_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_invoice (invoice_id),
            KEY idx_order (order_id),
            KEY idx_status (status),
            KEY idx_method (method)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}refunds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            number VARCHAR(32) NOT NULL UNIQUE,
            type ENUM('refund','credit_note') NOT NULL DEFAULT 'refund',
            status ENUM('draft','approved','processed','cancelled') NOT NULL DEFAULT 'draft',
            invoice_id BIGINT UNSIGNED NULL,
            order_id BIGINT UNSIGNED NULL,
            payment_id BIGINT UNSIGNED NULL,
            customer_id BIGINT UNSIGNED NULL,
            amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            reason VARCHAR(255) NULL,
            items LONGTEXT NULL,
            meta LONGTEXT NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            processed_at DATETIME NULL,
            owner_id BIGINT UNSIGNED NULL,
            branch_id BIGINT UNSIGNED NULL,
            company_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_invoice (invoice_id),
            KEY idx_status (status),
            KEY idx_type (type)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}fiscal_periods (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            name VARCHAR(64) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status ENUM('open','closed','locked') NOT NULL DEFAULT 'open',
            calendar_type ENUM('gregorian','jalali') NOT NULL DEFAULT 'jalali',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_company_name (company_id, name),
            KEY idx_company_date (company_id, start_date, end_date)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}employees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NULL,
            user_id BIGINT UNSIGNED NULL,
            national_code VARCHAR(16) NOT NULL UNIQUE,
            full_name VARCHAR(255) NOT NULL,
            first_name VARCHAR(128) NULL,
            last_name VARCHAR(128) NULL,
            father_name VARCHAR(128) NULL,
            gender ENUM('male','female','other') NULL,
            birth_date DATE NULL,
            national_id_serial VARCHAR(32) NULL,
            place_of_birth VARCHAR(128) NULL,
            marital_status ENUM('single','married','divorced','widowed') NULL,
            children_count INT NOT NULL DEFAULT 0,
            military_status VARCHAR(32) NULL,
            phone VARCHAR(32) NULL,
            mobile VARCHAR(32) NULL,
            email VARCHAR(190) NULL,
            address TEXT NULL,
            postal_code VARCHAR(20) NULL,
            emergency_contact VARCHAR(255) NULL,
            bank_sheba VARCHAR(32) NULL,
            bank_card VARCHAR(32) NULL,
            bank_account VARCHAR(32) NULL,
            insurance_number VARCHAR(64) NULL,
            employment_type ENUM('full_time','part_time','contract','intern','apprentice') NOT NULL DEFAULT 'full_time',
            department VARCHAR(128) NULL,
            position VARCHAR(128) NULL,
            job_title VARCHAR(128) NULL,
            manager_id BIGINT UNSIGNED NULL,
            hire_date DATE NOT NULL,
            termination_date DATE NULL,
            probation_end_date DATE NULL,
            base_salary DECIMAL(20,2) NOT NULL DEFAULT 0,
            daily_wage DECIMAL(20,2) NOT NULL DEFAULT 0,
            hourly_wage DECIMAL(20,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            work_schedule VARCHAR(64) NOT NULL DEFAULT 'sat-wed',
            weekly_hours INT NOT NULL DEFAULT 44,
            leave_balance DECIMAL(6,2) NOT NULL DEFAULT 26.00,
            sick_balance DECIMAL(6,2) NOT NULL DEFAULT 10.00,
            skill_tags VARCHAR(255) NULL,
            avatar_url VARCHAR(512) NULL,
            notes TEXT NULL,
            status ENUM('active','on_leave','suspended','terminated') NOT NULL DEFAULT 'active',
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            branch_id BIGINT UNSIGNED NULL,
            meta LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_manager (manager_id),
            KEY idx_department (department),
            KEY idx_company (company_id),
            KEY idx_branch (branch_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}attendance (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            work_date DATE NOT NULL,
            check_in TIME NULL,
            check_out TIME NULL,
            work_minutes INT NOT NULL DEFAULT 0,
            overtime_minutes INT NOT NULL DEFAULT 0,
            late_minutes INT NOT NULL DEFAULT 0,
            status ENUM('present','late','absent','leave','holiday','remote') NOT NULL DEFAULT 'present',
            source VARCHAR(32) NOT NULL DEFAULT 'manual',
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            device_ip VARCHAR(45) NULL,
            is_anomaly TINYINT(1) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            branch_id BIGINT UNSIGNED NULL,
            recorded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_emp_date (employee_id, work_date),
            KEY idx_employee (employee_id),
            KEY idx_status (status),
            KEY idx_date (work_date),
            KEY idx_company (company_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}leave_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            type ENUM('annual','sick','unpaid','maternity','paternity','bereavement','marriage','pilgrimage','mission','sick_family') NOT NULL DEFAULT 'annual',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days DECIMAL(5,1) NOT NULL DEFAULT 1,
            half_day TINYINT(1) NOT NULL DEFAULT 0,
            hourly TINYINT(1) NOT NULL DEFAULT 0,
            hours DECIMAL(6,2) NOT NULL DEFAULT 0,
            reason TEXT NULL,
            attachment_url VARCHAR(512) NULL,
            status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            decision_note TEXT NULL,
            cancelled_by BIGINT UNSIGNED NULL,
            cancelled_at DATETIME NULL,
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            requested_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_employee (employee_id),
            KEY idx_status (status),
            KEY idx_type (type),
            KEY idx_dates (start_date, end_date),
            KEY idx_company (company_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}performance_reviews (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            reviewer_id BIGINT UNSIGNED NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            overall_score DECIMAL(4,2) NOT NULL DEFAULT 0,
            productivity_score DECIMAL(4,2) NOT NULL DEFAULT 0,
            quality_score DECIMAL(4,2) NOT NULL DEFAULT 0,
            teamwork_score DECIMAL(4,2) NOT NULL DEFAULT 0,
            punctuality_score DECIMAL(4,2) NOT NULL DEFAULT 0,
            strengths TEXT NULL,
            weaknesses TEXT NULL,
            goals TEXT NULL,
            feedback TEXT NULL,
            status ENUM('draft','submitted','acknowledged','finalized') NOT NULL DEFAULT 'draft',
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_employee (employee_id),
            KEY idx_reviewer (reviewer_id),
            KEY idx_status (status),
            KEY idx_company (company_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}contracts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            contract_type ENUM('permanent','fixed_term','probation','apprenticeship') NOT NULL DEFAULT 'permanent',
            start_date DATE NOT NULL,
            end_date DATE NULL,
            salary DECIMAL(20,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            terms LONGTEXT NULL,
            signed_at DATETIME NULL,
            expires_at DATETIME NULL,
            file_url VARCHAR(512) NULL,
            status ENUM('draft','active','expired','terminated') NOT NULL DEFAULT 'draft',
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_employee (employee_id),
            KEY idx_status (status),
            KEY idx_dates (start_date, end_date),
            KEY idx_company (company_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}payroll_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NULL,
            period_year INT NOT NULL,
            period_month INT NOT NULL,
            period_label VARCHAR(32) NULL,
            status ENUM('draft','processing','approved','paid','cancelled') NOT NULL DEFAULT 'draft',
            total_employees INT NOT NULL DEFAULT 0,
            total_gross DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_net DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_tax DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_insurance DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_overtime DECIMAL(20,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'IRT',
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            paid_at DATETIME NULL,
            notes TEXT NULL,
            company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_period (company_id, period_year, period_month),
            KEY idx_status (status),
            KEY idx_company (company_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}payroll_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payroll_run_id BIGINT UNSIGNED NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            base_salary DECIMAL(20,2) NOT NULL DEFAULT 0,
            overtime_pay DECIMAL(20,2) NOT NULL DEFAULT 0,
            bonus DECIMAL(20,2) NOT NULL DEFAULT 0,
            deductions DECIMAL(20,2) NOT NULL DEFAULT 0,
            tax DECIMAL(20,2) NOT NULL DEFAULT 0,
            insurance DECIMAL(20,2) NOT NULL DEFAULT 0,
            net_pay DECIMAL(20,2) NOT NULL DEFAULT 0,
            worked_days INT NOT NULL DEFAULT 0,
            paid_leave_days DECIMAL(5,1) NOT NULL DEFAULT 0,
            unpaid_leave_days DECIMAL(5,1) NOT NULL DEFAULT 0,
            overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
            late_minutes INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            status ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_run_emp (payroll_run_id, employee_id),
            KEY idx_employee (employee_id),
            KEY idx_run (payroll_run_id)
        ) {$charset};";

        foreach ($sql as $q) {
            dbDelta($q);
        }
    }

    public static function seedDefaults(): void
    {
        if (get_option('enterprise_seeded') === 'yes') {
            return;
        }
        \Enterprise\Modules\Accounting\ChartOfAccounts::installDefaults();
        \Enterprise\Modules\Objects\Bootstrap::installSystemObjects();
        self::seedFiscalPeriod();
        update_option('enterprise_seeded', 'yes');
    }

    /**
     * ساخت دورهٔ مالی پیش‌فرض بر اساس تنظیمات.
     * - اگر تقویم شمسی انتخاب شده باشد: ۱ فروردین تا ۲۹ اسفند
     * - در غیر این صورت: ژانویه تا دسامبر همان سال
     */
    public static function seedFiscalPeriod(?int $companyId = 1): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_fiscal_periods';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE company_id = %d ORDER BY id ASC LIMIT 1",
            $companyId
        ));
        if ($existing) {
            return (int) $existing;
        }

        $calendar = (string) get_option('enterprise_calendar_type', 'jalali');

        if ($calendar === 'jalali') {
            $jy = self::currentJalaliYear();
            $start = \Enterprise\Jalali::toGregorian($jy, 1, 1);
            $end   = \Enterprise\Jalali::toGregorian($jy, 12, (\Enterprise\Jalali::isLeap($jy) ? 30 : 29));
        } else {
            $y = (int) gmdate('Y');
            $start = sprintf('%04d-01-01', $y);
            $end   = sprintf('%04d-12-31', $y);
        }

        $name = $calendar === 'jalali'
            ? 'سال مالی ' . self::currentJalaliYear()
            : 'Fiscal Year ' . gmdate('Y');

        $wpdb->insert($table, [
            'company_id'    => $companyId,
            'name'          => $name,
            'start_date'    => $start,
            'end_date'      => $end,
            'status'        => 'open',
            'calendar_type' => $calendar,
        ]);

        return (int) $wpdb->insert_id;
    }

    private static function currentJalaliYear(): int
    {
        $parts = \Enterprise\Jalali::fromGregorian(gmdate('Y-m-d'));
        return (int) $parts['y'];
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
