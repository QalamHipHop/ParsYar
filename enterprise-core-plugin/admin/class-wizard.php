<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

use Enterprise\Installer;
use Enterprise\Db\DemoSeeder;
use Enterprise\Modules\Objects\ObjectEngine;

/**
 * کنترلر اصلی ویزارد نصب.
 *
 * - ثبت routeهای AJAX
 * - render صفحه
 * - ذخیره‌سازی state
 * - اعمال نهایی
 */
final class Wizard
{
    public const NONCE = 'parsyar_wizard';
    public const AJAX  = 'parsyar_wizard';

    public static function register(): void
    {
        add_action('admin_post_parsyar_wizard_save', [self::class, 'ajaxSave']);
        add_action('wp_ajax_parsyar_wizard_save',     [self::class, 'ajaxSave']);
        add_action('wp_ajax_parsyar_wizard_goto',     [self::class, 'ajaxGoto']);
        add_action('wp_ajax_parsyar_wizard_apply',    [self::class, 'ajaxApply']);
        add_action('wp_ajax_parsyar_wizard_reset',    [self::class, 'ajaxReset']);
        add_action('wp_ajax_parsyar_wizard_export',   [self::class, 'ajaxExport']);
        add_action('wp_ajax_parsyar_wizard_import',   [self::class, 'ajaxImport']);
        add_action('admin_enqueue_scripts',           [self::class, 'enqueue']);
    }

    public static function enqueue(string $hook): void
    {
        $page = $_GET['page'] ?? '';
        if ($page !== 'enterprise-setup') {
            return;
        }
        wp_enqueue_script(
            'parsyar-wizard',
            plugins_url('assets/js/wizard.js', dirname(__DIR__, 2) . '/enterprise-core.php'),
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('parsyar-wizard', 'ParsYarWizard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
            'action'  => self::AJAX,
            'i18n'    => [
                'saving'   => 'در حال ذخیره...',
                'saved'    => 'ذخیره شد',
                'error'    => 'خطا',
                'confirmReset' => 'پاک کردن همهٔ پیکربندی و شروع از نو؟',
            ],
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_enterprise')) {
            wp_die(esc_html__('دسترسی ندارید.', 'enterprise-core'));
        }
        $state    = WizardState::load();
        $current  = (int) ($_GET['step'] ?? $state['current_step'] ?? 1);
        $current  = max(1, min(WizardState::STEPS, $current));
        $progress = WizardState::progress($state);
        $system   = SystemCheck::run();
        $summary  = SystemCheck::summary($system);

        ob_start();
        $file = __DIR__ . '/views/wizard/step-' . sprintf('%02d', $current) . '-' . self::stepSlug($current) . '.php';
        if (file_exists($file)) {
            /** @phpstan-ignore-next-line — view includes are user-defined step files */
            include $file;
        } else {
            echo '<div class="pw-banner warning">مرحله پیدا نشد.</div>';
        }
        $body = ob_get_clean();

        $state['current_step'] = $current;
        WizardState::save($state);

        $state    = WizardState::load();
        $progress = WizardState::progress($state);
        $page     = $_GET['page'] ?? 'enterprise-setup';

        $view = __DIR__ . '/views/wizard/layout.php';
        /** @phpstan-ignore-next-line */
        include $view;
    }

    public static function stepSlug(int $step): string
    {
        $slugs = [
            1 => 'welcome', 2 => 'locale', 3 => 'organization', 4 => 'companies', 5 => 'branches',
            6 => 'currencies', 7 => 'fiscal', 8 => 'jalali', 9 => 'pipelines', 10 => 'taxes',
            11 => 'modules', 12 => 'users-roles', 13 => 'notifications', 14 => 'payment-gateways',
            15 => 'iranian-integrations', 16 => 'woocommerce', 17 => 'import', 18 => 'demo',
            19 => 'branding', 20 => 'ai', 21 => 'security', 22 => 'backup-webhooks', 23 => 'done',
        ];
        return $slugs[$step] ?? 'welcome';
    }

    /* ---------------- AJAX handlers ---------------- */

    public static function ajaxSave(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_enterprise')) {
            wp_send_json_error(['message' => 'دسترسی ندارید.'], 403);
        }
        $state  = WizardState::load();
        $step   = (int) ($_POST['step'] ?? 0);
        $action = sanitize_key($_POST['step_action'] ?? 'next'); // next|skip
        $patch  = $_POST['data'] ?? [];

        if (!is_array($patch)) {
            wp_send_json_error(['message' => 'دادهٔ نامعتبر.']);
        }

        $state = self::mergePatch($state, $patch);

        if ($action === 'skip') {
            WizardState::markSkipped($step, $state);
        } else {
            WizardState::markCompleted($step, $state);
        }

        WizardState::save($state);
        wp_send_json_success([
            'state'    => $state,
            'next'     => min(WizardState::STEPS, max(1, $step + 1)),
            'progress' => WizardState::progress($state),
        ]);
    }

    public static function ajaxGoto(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_enterprise')) {
            wp_send_json_error(['message' => 'دسترسی ندارید.'], 403);
        }
        $state  = WizardState::load();
        $step   = (int) ($_POST['step'] ?? 1);
        $state['current_step'] = max(1, min(WizardState::STEPS, $step));
        WizardState::save($state);
        wp_send_json_success(['current' => $state['current_step']]);
    }

    public static function ajaxApply(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_enterprise')) {
            wp_send_json_error(['message' => 'دسترسی ندارید.'], 403);
        }
        $state = WizardState::load();

        // 1. schema & defaults
        Installer::ensureSchema();
        Installer::seedDefaults();

        // 2. sync object tables
        try {
            Installer::syncAllObjectTables();
        } catch (\Throwable $e) {
            // ignore
        }

        // 3. apply pipelines, taxes, modules, settings
        self::applyPipelines($state);
        self::applyTaxes($state);
        self::applyBranding($state);
        self::applyNotifications($state);
        self::applyPaymentGateways($state);
        self::applyIntegrations($state);
        self::applySecurity($state);
        self::applyAi($state);
        self::applyBackupsAndWebhooks($state);
        self::applyRoles($state);
        self::applyOrgProfile($state);

        // 4. demo data
        if (!empty($state['demo']['enabled'])) {
            DemoSeeder::run();
        }

        // 5. mark complete
        WizardState::markCompleted(WizardState::STEPS, $state);
        update_option('enterprise_setup_done', 1);
        update_option('parsyar_setup_completed_at', current_time('mysql'));
        WizardState::save($state);

        wp_send_json_success(['message' => 'نصب کامل شد.', 'redirect' => admin_url('admin.php?page=enterprise')]);
    }

    public static function ajaxReset(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_enterprise')) {
            wp_send_json_error(['message' => 'دسترسی ندارید.'], 403);
        }
        WizardState::reset();
        wp_send_json_success(['message' => 'پیکربندی پاک شد.']);
    }

    public static function ajaxExport(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_enterprise')) {
            wp_die('forbidden', 403);
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="parsyar-config-' . gmdate('Ymd-His') . '.json"');
        echo WizardState::exportJson(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public static function ajaxImport(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_enterprise')) {
            wp_send_json_error(['message' => 'دسترسی ندارید.'], 403);
        }
        if (empty($_FILES['file']['tmp_name'])) {
            wp_send_json_error(['message' => 'فایلی ارسال نشد.']);
        }
        $json = (string) file_get_contents((string) $_FILES['file']['tmp_name']);
        $ok   = WizardState::importJson($json);
        if (!$ok) {
            wp_send_json_error(['message' => 'فایل نامعتبر.']);
        }
        wp_send_json_success(['message' => 'پیکربندی وارد شد.']);
    }

    /* ---------------- Apply helpers ---------------- */

    private static function applyPipelines(array $state): void
    {
        if (empty($state['pipelines']) || !is_array($state['pipelines'])) {
            return;
        }
        update_option('parsyar_pipelines', array_values($state['pipelines']));
    }

    private static function applyTaxes(array $state): void
    {
        if (!empty($state['taxes'])) {
            $t = $state['taxes'];
            // decode JSON-encoded withholding / exemptions
            if (isset($t['withholding_json'])) {
                $t['withholding'] = json_decode((string) $t['withholding_json'], true) ?: [];
                unset($t['withholding_json']);
            }
            if (isset($t['exemptions_json'])) {
                $t['exemptions'] = json_decode((string) $t['exemptions_json'], true) ?: [];
                unset($t['exemptions_json']);
            }
            update_option('parsyar_taxes', $t);
        }
    }

    private static function applyBranding(array $state): void
    {
        if (!empty($state['branding'])) {
            update_option('parsyar_branding', $state['branding']);
        }
    }

    private static function applyNotifications(array $state): void
    {
        if (!empty($state['notifications'])) {
            update_option('parsyar_notifications', $state['notifications']);
        }
    }

    private static function applyPaymentGateways(array $state): void
    {
        if (!empty($state['payment_gateways'])) {
            update_option('parsyar_payment_gateways', $state['payment_gateways']);
        }
    }

    private static function applyIntegrations(array $state): void
    {
        if (!empty($state['integrations'])) {
            update_option('parsyar_integrations', $state['integrations']);
        }
    }

    private static function applySecurity(array $state): void
    {
        if (!empty($state['security'])) {
            $s = $state['security'];
            if (isset($s['ip_allowlist_text'])) {
                $s['ip_allowlist'] = array_values(array_filter(array_map('trim', explode("\n", (string) $s['ip_allowlist_text']))));
                unset($s['ip_allowlist_text']);
            }
            update_option('parsyar_security', $s);
        }
    }

    private static function applyAi(array $state): void
    {
        if (!empty($state['ai'])) {
            update_option('parsyar_ai', $state['ai']);
        }
    }

    private static function applyBackupsAndWebhooks(array $state): void
    {
        if (!empty($state['backups'])) {
            update_option('parsyar_backups', $state['backups']);
        }
        if (!empty($state['webhooks'])) {
            update_option('parsyar_webhooks', $state['webhooks']);
        }
    }

    private static function applyRoles(array $state): void
    {
        Installer::ensureCapabilities();
        if (!empty($state['roles'])) {
            update_option('parsyar_active_roles', array_keys(array_filter($state['roles'])));
        }
    }

    private static function applyOrgProfile(array $state): void
    {
        if (!empty($state['org'])) {
            update_option('parsyar_organization', $state['org']);
        }
        if (!empty($state['language'])) {
            update_option('parsyar_language', $state['language']);
        }
        if (!empty($state['timezone'])) {
            update_option('timezone_string', $state['timezone']);
        }
        if (!empty($state['base_currency'])) {
            update_option('parsyar_base_currency', $state['base_currency']);
        }
        if (!empty($state['currencies'])) {
            update_option('parsyar_currencies', $state['currencies']);
        }
        if (!empty($state['mode'])) {
            update_option('parsyar_deployment_mode', $state['mode']);
        }
        if (!empty($state['fiscal_type'])) {
            update_option('parsyar_fiscal_type', $state['fiscal_type']);
            update_option('parsyar_fiscal_start_md', $state['fiscal_start_md'] ?? '03-21');
        }
        if (!empty($state['jalali_mode'])) {
            update_option('parsyar_jalali_mode', $state['jalali_mode']);
        }
        if (!empty($state['branches'])) {
            update_option('parsyar_branches', $state['branches']);
        }
        if (!empty($state['companies'])) {
            update_option('parsyar_companies', $state['companies']);
        }
    }

    /**
     * Patch را به‌صورت امن در state ادغام می‌کند.
     * - فقط کلیدهای مجاز شناخته‌شده در defaults پذیرفته می‌شوند.
     *
     * @param array $state
     * @param array $patch
     * @return array
     */
    private static function mergePatch(array $state, array $patch): array
    {
        $allowed = self::allowedKeys(WizardState::defaults());
        foreach ($patch as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (in_array($k, $allowed, true) || self::isNestedAllowed($k, $allowed)) {
                $state[$k] = self::sanitizeValue($v);
            }
        }
        return $state;
    }

    private static function allowedKeys(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach (array_keys($arr) as $k) {
            $out[] = $prefix . (string) $k;
        }
        return $out;
    }

    private static function isNestedAllowed(string $k, array $allowed): bool
    {
        foreach ($allowed as $a) {
            if (str_starts_with($a, $k . '[')) {
                return true;
            }
        }
        return false;
    }

    private static function sanitizeValue($v)
    {
        if (is_array($v)) {
            return array_map([self::class, 'sanitizeValue'], $v);
        }
        if (is_string($v)) {
            return wp_unslash($v);
        }
        return $v;
    }
}
