<?php
declare(strict_types=1);

namespace Enterprise\Modules\Accounting;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

/**
 * درخت حساب‌ها (COA) — Standard Chart of Accounts.
 */
final class ChartOfAccounts
{
    /**
     * ساختار پیش‌فرض حساب‌ها — مطابق استاندارد ایران.
     *
     * @var array<int, array{code:string,name:string,type:string}>
     */
    private const DEFAULTS = [
        // دارایی‌ها
        ['code' => '1100', 'name' => 'صندوق',                  'type' => 'asset'],
        ['code' => '1110', 'name' => 'بانک',                   'type' => 'asset'],
        ['code' => '1120', 'name' => 'موجودی کالا',             'type' => 'asset'],
        ['code' => '1130', 'name' => 'حساب‌های دریافتنی',       'type' => 'asset'],
        ['code' => '1140', 'name' => 'دارایی‌های ثابت',         'type' => 'asset'],
        // بدهی‌ها
        ['code' => '2100', 'name' => 'حساب‌های پرداختنی',       'type' => 'liability'],
        ['code' => '2110', 'name' => 'مالیات پرداختنی',         'type' => 'liability'],
        ['code' => '2120', 'name' => 'بیمه پرداختنی',           'type' => 'liability'],
        ['code' => '2130', 'name' => 'حقوق پرداختنی',           'type' => 'liability'],
        // سرمایه
        ['code' => '3100', 'name' => 'سرمایه',                  'type' => 'equity'],
        ['code' => '3200', 'name' => 'سود انباشته',             'type' => 'equity'],
        // درآمد
        ['code' => '4100', 'name' => 'فروش',                    'type' => 'revenue'],
        ['code' => '4200', 'name' => 'سایر درآمدها',            'type' => 'revenue'],
        // هزینه
        ['code' => '5100', 'name' => 'بهای تمام شده کالای فروش رفته', 'type' => 'expense'],
        ['code' => '5200', 'name' => 'حقوق و دستمزد',           'type' => 'expense'],
        ['code' => '5300', 'name' => 'اجاره',                   'type' => 'expense'],
        ['code' => '5400', 'name' => 'عمومی و اداری',           'type' => 'expense'],
    ];

    public static function installDefaults(): void
    {
        foreach (self::DEFAULTS as $a) {
            $exists = Db::getRow('accounts', ['code' => $a['code']]);
            if ($exists) {
                continue;
            }
            Db::insert('accounts', $a + ['is_active' => 1]);
        }
    }

    public static function findByCode(string $code): ?array
    {
        $row = Db::getRow('accounts', ['code' => $code]);
        return $row ?: null;
    }

    public static function all(): array
    {
        return Db::getResults('accounts', [], 'code ASC');
    }
}
