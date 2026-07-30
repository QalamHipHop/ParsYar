<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

use Enterprise\Installer;

/**
 * ویزارد نصب یک‌کلیک.
 */
final class Setup
{
    public static function maybeRedirect(): void
    {
        if (!get_option('enterprise_setup_done') && current_user_can('manage_enterprise')) {
            $url = admin_url('admin.php?page=enterprise-setup');
            if (empty($_GET['page']) || $_GET['page'] !== 'enterprise-setup') {
                wp_safe_redirect($url);
                exit;
            }
        }
    }

    public static function render(): void
    {
        $action = $_POST['enterprise_setup_action'] ?? '';
        if ($action === 'install') {
            check_admin_referer('enterprise_setup');
            Installer::ensureSchema();
            Installer::seedDefaults();
            Installer::syncAllObjectTables();
            \Enterprise\Db\DemoSeeder::run();
            update_option('enterprise_setup_done', 1);
            echo '<div class="notice notice-success"><p>نصب کامل شد. تمام ماژول‌ها، حساب‌ها، Flat Tableها و داده‌های دمو ایجاد شدند.</p></div>';
        }
        echo '<div class="wrap"><h1>Enterprise Setup Wizard</h1>';
        echo '<p>این ویزارد جداول، حساب‌های پیش‌فرض، اشیاء سیستمی و داده‌های دمو را در یک مرحله نصب می‌کند.</p>';
        echo '<form method="post">';
        wp_nonce_field('enterprise_setup');
        echo '<input type="hidden" name="enterprise_setup_action" value="install" />';
        submit_button('نصب و راه‌اندازی یک‌کلیک');
        echo '</form></div>';
    }
}
