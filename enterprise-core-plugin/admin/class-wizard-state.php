<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

/**
 * مدیریت وضعیت (state) ویزارد نصب.
 *
 * - ذخیره‌سازی در `wp_options` تحت کلید `parsyar_wizard_state`
 * - Resumable: در هر زمان قابل ادامه
 * - Export/Import به JSON
 * - Idempotent: اعمال چندباره‌ی یک مرحله مشکلی ایجاد نمی‌کند
 */
final class WizardState
{
    public const OPTION_KEY = 'parsyar_wizard_state';
    public const STEPS      = 23;

    /** @var array<int,string> عنوان هر مرحله. */
    public const STEP_LABELS = [
        1  => 'خوش‌آمدگویی و بررسی سیستم',
        2  => 'زبان و منطقهٔ زمانی',
        3  => 'پروفایل سازمان',
        4  => 'شرکت‌های چندگانه (Holding)',
        5  => 'شعب و دپارتمان‌ها',
        6  => 'ارزها و نرخ تبدیل',
        7  => 'سال مالی',
        8  => 'تنظیمات تقویم شمسی',
        9  => 'خطوط فروش (Pipelines)',
        10 => 'مالیات و عوارض',
        11 => 'ماژول‌ها',
        12 => 'کاربران و نقش‌ها',
        13 => 'کانال‌های اعلان',
        14 => 'درگاه‌های پرداخت',
        15 => 'یکپارچگی‌های ایرانی',
        16 => 'فروشگاه اینترنتی (WooCommerce)',
        17 => 'ورود داده (Import)',
        18 => 'دادهٔ نمونه (Demo)',
        19 => 'قالب و برندینگ',
        20 => 'دستیار هوش مصنوعی',
        21 => 'امنیت',
        22 => 'پشتیبان‌گیری و Webhook',
        23 => 'پایان',
    ];

    /** وضعیت پیش‌فرض همهٔ مراحل. */
    public static function defaults(): array
    {
        return [
            'version'         => 1,
            'current_step'    => 1,
            'completed'       => [],
            'skipped'         => [],
            'mode'            => 'micro', // solo|micro|smb|enterprise|holding
            'deployment_mode' => 'micro',
            'created_at'      => current_time('mysql'),
            'updated_at'      => current_time('mysql'),

            // Step 2: language & locale
            'language'        => 'fa_IR',
            'timezone'        => 'Asia/Tehran',
            'first_day_week'  => 6, // شنبه در ایران
            'date_format'     => 'Y/m/d',
            'time_format'     => 'H:i',
            'number_format'   => 'fa', // fa|en

            // Step 3: organization
            'org' => [
                'name'            => '',
                'legal_name'      => '',
                'national_id'     => '',
                'economic_code'   => '',
                'registration_no' => '',
                'vat_number'      => '',
                'industry'        => '',
                'size'            => 'small',
                'website'         => '',
                'email'           => '',
                'phone'           => '',
                'mobile'          => '',
                'address_line1'   => '',
                'address_line2'   => '',
                'city'            => '',
                'province'        => '',
                'postal_code'     => '',
                'country'         => 'IR',
                'logo_url'        => '',
                'stamp_url'       => '',
                'signature_url'   => '',
            ],

            // Step 4: companies (holding)
            'companies' => [],

            // Step 5: branches
            'branches' => [],

            // Step 6: currencies
            'base_currency'      => 'IRT',
            'currencies'         => ['IRT', 'IRR', 'USD', 'EUR', 'AED', 'TRY'],
            'exchange_provider'  => 'manual', // manual|openexchangerates|cbr|tgju
            'exchange_api_key'   => '',

            // Step 7: fiscal year
            'fiscal_type'     => 'iranian', // iranian|gregorian|custom
            'fiscal_start_md' => '03-21', // 21 March
            'fiscal_label'    => 'سال مالی',

            // Step 8: jalali
            'jalali_mode'    => 'astronomical', // astronomical|2820|33
            'jalali_format'  => 'Y/m/d',
            'jalali_locale'  => 'fa',

            // Step 9: pipelines
            'pipelines' => [
                [
                    'id'    => 'default',
                    'name'  => 'خط فروش پیش‌فرض',
                    'stages' => [
                        ['id' => 'lead',     'name' => 'سرنخ',         'probability' => 5,   'wip_limit' => 0],
                        ['id' => 'qualified','name' => 'واجد شرایط',   'probability' => 15,  'wip_limit' => 0],
                        ['id' => 'proposal', 'name' => 'پیشنهاد',      'probability' => 40,  'wip_limit' => 0],
                        ['id' => 'negotiation', 'name' => 'مذاکره',     'probability' => 70,  'wip_limit' => 0],
                        ['id' => 'won',      'name' => 'برنده',        'probability' => 100, 'wip_limit' => 0],
                        ['id' => 'lost',     'name' => 'باخته',        'probability' => 0,   'wip_limit' => 0],
                    ],
                ],
            ],

            // Step 10: taxes
            'taxes' => [
                'vat_percent'      => 10,
                'withholding'      => [],
                'exemptions'       => [],
                'auto_calculate'   => true,
                'rounding'         => 'half_up',
            ],

            // Step 11: modules
            'modules' => [
                'crm'        => true,
                'erp'        => true,
                'hrm'        => true,
                'accounting' => true,
                'inbox'      => true,
                'marketing'  => true,
                'automation' => true,
                'reports'    => true,
                'support'    => true,
                'projects'   => true,
                'documents'  => true,
            ],

            // Step 12: users & roles
            'admin_user_id' => 0,
            'roles'         => [
                'super_admin'     => true,
                'admin'           => true,
                'sales_manager'   => true,
                'sales_rep'       => true,
                'support'         => true,
                'marketing'       => true,
                'hr'              => true,
                'accountant'      => true,
                'readonly'        => true,
            ],

            // Step 13: notifications
            'notifications' => [
                'smtp_host'        => '',
                'smtp_port'        => 587,
                'smtp_user'        => '',
                'smtp_pass'        => '',
                'smtp_secure'      => 'tls',
                'smtp_from_email'  => '',
                'smtp_from_name'   => '',
                'sms_provider'     => 'kavenegar', // kavenegar|melipayamak|ghasedak|smsir
                'sms_api_key'      => '',
                'sms_sender'       => '',
                'web_push'         => false,
                'in_app'           => true,
            ],

            // Step 14: payment gateways
            'payment_gateways' => [
                'zarinpal'    => ['enabled' => false, 'merchant_id' => ''],
                'idpay'       => ['enabled' => false, 'api_key'    => ''],
                'nextpay'     => ['enabled' => false, 'api_key'    => ''],
                'saman'       => ['enabled' => false, 'merchant_id' => ''],
                'pasargad'    => ['enabled' => false, 'terminal_id' => ''],
                'mellat'      => ['enabled' => false, 'terminal_id' => '', 'username' => '', 'password' => ''],
                'saderat'     => ['enabled' => false, 'terminal_id' => ''],
                'asanpardakht'=> ['enabled' => false, 'merchant_id' => ''],
            ],

            // Step 15: Iranian integrations
            'integrations' => [
                'moodian'    => ['enabled' => false, 'tax_username' => '', 'tax_password' => '', 'memory_id' => '', 'private_key_path' => ''],
                'shaparak'   => ['enabled' => false, 'terminal_id'  => '', 'merchant_id' => ''],
                'post'       => ['enabled' => false, 'username'     => ''],
                'jibit'      => ['enabled' => false, 'api_key'      => '', 'secret'     => ''],
                'finnotech'  => ['enabled' => false, 'client_id'    => '', 'client_secret' => ''],
                'neshan'     => ['enabled' => false, 'api_key'      => ''],
                'mapir'      => ['enabled' => false, 'api_key'      => ''],
            ],

            // Step 16: WooCommerce
            'woocommerce' => [
                'sync_enabled'   => false,
                'sync_products'  => true,
                'sync_orders'    => true,
                'sync_customers' => true,
                'sync_stock'     => true,
            ],

            // Step 17: import (handled per-run)
            'imports' => [],

            // Step 18: demo
            'demo' => [
                'enabled' => false,
                'leads'   => 50,
                'contacts'=> 100,
                'deals'   => 30,
                'products'=> 40,
                'invoices'=> 25,
                'employees' => 10,
            ],

            // Step 19: theme & branding
            'branding' => [
                'logo_url'      => '',
                'login_logo'    => '',
                'email_logo'    => '',
                'favicon'       => '',
                'primary_font'  => 'Vazirmatn',
                'primary_color' => '#0A0A0A',
                'accent_color'  => '#000000',
                'email_footer'  => '',
            ],

            // Step 20: AI assistant
            'ai' => [
                'enabled'      => false,
                'provider'     => 'openai', // openai|anthropic|local|rasa|huggingface
                'api_key'      => '',
                'model'        => 'gpt-4o-mini',
                'endpoint'     => '',
                'max_tokens'   => 2048,
                'temperature'  => 0.2,
                'system_prompt'=> 'شما یک دستیار فروش حرفه‌ای هستید که به زبان فارسی پاسخ می‌دهد.',
                'features'     => [
                    'lead_scoring'    => true,
                    'email_drafting'  => true,
                    'summarization'   => true,
                    'sentiment'       => true,
                    'translation'     => true,
                ],
            ],

            // Step 21: security
            'security' => [
                'two_factor_required'  => false,
                'ip_allowlist'         => [],
                'password_min_length'  => 12,
                'password_require_mix' => true,
                'session_lifetime'     => 8 * HOUR_IN_SECONDS,
                'audit_retention_days' => 365,
                'encrypt_at_rest'      => false,
            ],

            // Step 22: backup & webhooks
            'backups' => [
                'enabled'         => false,
                'schedule'        => 'daily',
                'destination'     => 'local', // local|email|s3|ftp
                'keep_last'       => 14,
                'include_uploads' => true,
            ],
            'webhooks' => [
                'enabled'        => false,
                'signing_secret' => '',
                'retry_max'      => 5,
                'endpoints'      => [],
            ],
        ];
    }

    public static function load(): array
    {
        $state = get_option(self::OPTION_KEY, []);
        if (!is_array($state) || empty($state)) {
            $state = self::defaults();
            update_option(self::OPTION_KEY, $state);
        } else {
            // merge با defaults برای فیلدهای جدید
            $state = self::deepMerge(self::defaults(), $state);
        }
        return $state;
    }

    public static function save(array $state): bool
    {
        $state['updated_at'] = current_time('mysql');
        return (bool) update_option(self::OPTION_KEY, $state);
    }

    public static function reset(): bool
    {
        return (bool) delete_option(self::OPTION_KEY);
    }

    public static function markCompleted(int $step, array &$state): void
    {
        if (!in_array($step, $state['completed'], true)) {
            $state['completed'][] = $step;
            sort($state['completed']);
        }
        $state['skipped'] = array_values(array_diff($state['skipped'], [$step]));
        $state['current_step'] = max($state['current_step'], $step + 1);
    }

    public static function markSkipped(int $step, array &$state): void
    {
        if (!in_array($step, $state['skipped'], true)) {
            $state['skipped'][] = $step;
        }
        $state['current_step'] = max($state['current_step'], $step + 1);
    }

    public static function isCompleted(int $step, array $state): bool
    {
        return in_array($step, $state['completed'] ?? [], true);
    }

    public static function isSkipped(int $step, array $state): bool
    {
        return in_array($step, $state['skipped'] ?? [], true);
    }

    public static function progress(array $state): array
    {
        $done = count($state['completed'] ?? []);
        $skip = count($state['skipped'] ?? []);
        $total = self::STEPS;
        $pct   = $total > 0 ? (int) round((($done + $skip) / $total) * 100) : 0;
        return [
            'total'      => $total,
            'done'       => $done,
            'skipped'    => $skip,
            'remaining'  => max(0, $total - $done - $skip),
            'percent'    => $pct,
        ];
    }

    public static function exportJson(): string
    {
        $state = self::load();
        unset($state['_token']);
        return wp_json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public static function importJson(string $json): bool
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return false;
        }
        $state = self::load();
        $merged = self::deepMerge($state, $data);
        return self::save($merged);
    }

    /** merge آرایه‌ها به صورت بازگشتی — کلیدهای عددی جایگزین می‌شوند */
    private static function deepMerge(array $base, array $incoming): array
    {
        foreach ($incoming as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && self::isAssoc($base[$k]) && self::isAssoc($v)) {
                $base[$k] = self::deepMerge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
