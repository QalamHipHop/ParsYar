<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

/**
 * Legacy entry point — نگه‌داشته شده برای سازگاری.
 * هدایت به Wizard اصلی.
 */
final class Setup
{
    public static function maybeRedirect(): void
    {
        if (!get_option('enterprise_setup_done') && current_user_can('manage_enterprise')) {
            $url = admin_url('admin.php?page=enterprise-setup&step=1');
            if (empty($_GET['page']) || $_GET['page'] !== 'enterprise-setup') {
                wp_safe_redirect($url);
                exit;
            }
        }
    }

    /** @deprecated از Wizard::render استفاده کنید. */
    public static function render(): void
    {
        Wizard::render();
    }
}
