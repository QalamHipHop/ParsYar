<?php
declare(strict_types=1);

namespace Enterprise\Modules\Objects;

defined('ABSPATH') || exit;

/**
 * اشیاء پیش‌فرض سیستم — مشابه CRM/Account/Contact در Salesforce.
 */
final class Bootstrap
{
    public static function installSystemObjects(): void
    {
        // اشیاء سیستمی از قبل نصب شده‌اند؟
        if (get_option('enterprise_objects_seeded') === 'yes') {
            // فقط Flat Tableها را sync می‌کنیم.
            self::syncFlatTables();
            return;
        }

        $defaults = [
            [
                'api_name'     => 'account',
                'label'        => 'حساب',
                'label_plural' => 'حساب‌ها',
                'description'  => 'مشتریان و شرکت‌ها',
                'fields'       => [
                    ['api_name' => 'name',     'label' => 'نام',          'type' => 'text',     'required' => true],
                    ['api_name' => 'industry', 'label' => 'صنعت',         'type' => 'select',   'options' => ['تولیدی','خدماتی','بازرگانی','فناوری']],
                    ['api_name' => 'website',  'label' => 'وب‌سایت',      'type' => 'url'],
                    ['api_name' => 'phone',    'label' => 'تلفن',         'type' => 'phone'],
                    ['api_name' => 'email',    'label' => 'ایمیل',        'type' => 'email'],
                    ['api_name' => 'tax_id',   'label' => 'شناسه مالیاتی', 'type' => 'text'],
                ],
            ],
            [
                'api_name'     => 'contact',
                'label'        => 'مخاطب',
                'label_plural' => 'مخاطبین',
                'description'  => 'افراد مرتبط با حساب‌ها',
                'fields'       => [
                    ['api_name' => 'first_name', 'label' => 'نام',    'type' => 'text',     'required' => true],
                    ['api_name' => 'last_name',  'label' => 'نام خانوادگی', 'type' => 'text', 'required' => true],
                    ['api_name' => 'email',      'label' => 'ایمیل',  'type' => 'email'],
                    ['api_name' => 'phone',      'label' => 'موبایل', 'type' => 'phone'],
                    ['api_name' => 'title',      'label' => 'سمت',    'type' => 'text'],
                ],
            ],
            [
                'api_name'     => 'opportunity',
                'label'        => 'فرصت',
                'label_plural' => 'فرصت‌ها',
                'description'  => 'فرصت‌های فروش',
                'fields'       => [
                    ['api_name' => 'name',     'label' => 'عنوان',  'type' => 'text',     'required' => true],
                    ['api_name' => 'amount',   'label' => 'مبلغ',   'type' => 'currency'],
                    ['api_name' => 'stage',    'label' => 'مرحله',  'type' => 'select',   'options' => ['prospecting','qualification','proposal','negotiation','won','lost']],
                    ['api_name' => 'close_at', 'label' => 'تاریخ بسته شدن', 'type' => 'date'],
                ],
            ],
            [
                'api_name'     => 'project',
                'label'        => 'پروژه',
                'label_plural' => 'پروژه‌ها',
                'description'  => 'پروژه‌های داخلی یا مشتری',
                'fields'       => [
                    ['api_name' => 'name',      'label' => 'نام',     'type' => 'text',     'required' => true],
                    ['api_name' => 'budget',    'label' => 'بودجه',   'type' => 'currency'],
                    ['api_name' => 'start_at',  'label' => 'تاریخ شروع', 'type' => 'date'],
                    ['api_name' => 'end_at',    'label' => 'تاریخ پایان', 'type' => 'date'],
                    ['api_name' => 'status',    'label' => 'وضعیت',   'type' => 'select',  'options' => ['planning','active','on_hold','done','canceled']],
                ],
            ],
            [
                'api_name'     => 'contract',
                'label'        => 'قرارداد',
                'label_plural' => 'قراردادها',
                'description'  => 'قراردادهای رسمی با مشتریان/تامین‌کنندگان',
                'fields'       => [
                    ['api_name' => 'subject',   'label' => 'موضوع',   'type' => 'text',     'required' => true],
                    ['api_name' => 'value',     'label' => 'ارزش',    'type' => 'currency'],
                    ['api_name' => 'signed_at', 'label' => 'تاریخ امضا', 'type' => 'date'],
                    ['api_name' => 'expires_at','label' => 'تاریخ انقضا', 'type' => 'date'],
                ],
            ],
            [
                'api_name'     => 'asset',
                'label'        => 'دارایی',
                'label_plural' => 'دارایی‌ها',
                'description'  => 'دارایی‌های فیزیکی یا نرم‌افزاری',
                'fields'       => [
                    ['api_name' => 'name',       'label' => 'نام',     'type' => 'text',     'required' => true],
                    ['api_name' => 'serial',     'label' => 'سریال',   'type' => 'text'],
                    ['api_name' => 'cost',       'label' => 'بهای تمام شده', 'type' => 'currency'],
                    ['api_name' => 'purchased_at','label' => 'تاریخ خرید',   'type' => 'date'],
                ],
            ],
        ];

        foreach ($defaults as $spec) {
            if (ObjectEngine::findObjectByApiName($spec['api_name'])) {
                continue;
            }
            ObjectEngine::createObject($spec);
        }

        update_option('enterprise_objects_seeded', 'yes');
    }

    private static function syncFlatTables(): void
    {
        $rows = \Enterprise\Support\Db::getResults('objects', [], 'id ASC', 1000, 0);
        foreach ($rows as $obj) {
            $fields = ObjectEngine::getFields((int) $obj['id']);
            SchemaBuilder::syncObjectTable(
                (int) $obj['id'],
                (string) $obj['api_name'],
                $fields
            );
        }
    }
}
