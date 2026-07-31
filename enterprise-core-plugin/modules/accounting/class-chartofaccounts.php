<?php
/**
 * Chart of Accounts — جدول حساب‌ها
 *
 * ساختار ۵ رقمی استاندارد ایران:
 *   1xxxx — دارایی‌ها
 *   2xxxx — بدهی‌ها
 *   3xxxx — حقوق صاحبان سهام
 *   4xxxx — درآمدها
 *   5xxxx — هزینه‌ها
 *   6xxxx — حساب‌های انتظامی (off-balance)
 *
 * @package Enterprise\Modules\Accounting
 */

declare(strict_types=1);

namespace Enterprise\Modules\Accounting;

use Enterprise\Modules\Accounting\AccountNotFoundException;
use Enterprise\Modules\Accounting\InvalidAccountTypeException;

final class ChartOfAccounts
{
    /** نوع حساب‌ها */
    public const TYPE_ASSET     = 'asset';       // 1xxxx
    public const TYPE_LIABILITY = 'liability';   // 2xxxx
    public const TYPE_EQUITY    = 'equity';      // 3xxxx
    public const TYPE_REVENUE   = 'revenue';     // 4xxxx
    public const TYPE_EXPENSE   = 'expense';     // 5xxxx
    public const TYPE_MEMO      = 'memo';        // 6xxxx (off-balance)

    /** ریشهٔ طبیعی حساب */
    public const NATURE_DEBIT  = 'debit';
    public const NATURE_CREDIT = 'credit';

    /**
     * دریافت حساب با کد ۵ رقمی.
     *
     * @throws AccountNotFoundException
     */
    public static function find(string $code): array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ent_accounts WHERE code = %s AND deleted_at IS NULL",
                $code
            ),
            ARRAY_A
        );
        if (!$row) {
            throw new AccountNotFoundException($code);
        }
        return $row;
    }

    /**
     * دریافت شناسهٔ داخلی حساب (ایجاد lazy اگر نباشد).
     */
    public static function idFor(string $code, ?string $label = null): int
    {
        try {
            $row = self::find($code);
            return (int) $row['id'];
        } catch (AccountNotFoundException $e) {
            return self::create([
                'code'  => $code,
                'label' => $label ?? self::defaultLabelFor($code),
                'type'  => self::typeFor($code),
            ]);
        }
    }

    /**
     * ایجاد حساب جدید.
     *
     * @throws InvalidAccountTypeException
     */
    public static function create(array $data): int
    {
        global $wpdb;
        $code = (string) ($data['code'] ?? '');
        $type = (string) ($data['type'] ?? self::typeFor($code));
        if (!self::isValidType($type)) {
            throw new InvalidAccountTypeException($code, 'create', 'asset|liability|equity|revenue|expense|memo');
        }
        $wpdb->insert($wpdb->prefix . 'ent_accounts', [
            'code'         => $code,
            'label'        => (string) ($data['label'] ?? ''),
            'type'         => $type,
            'nature'       => self::natureFor($type),
            'parent_code'  => $data['parent_code'] ?? null,
            'is_system'    => !empty($data['is_system']) ? 1 : 0,
            'is_active'    => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'currency'     => $data['currency'] ?? 'IRT',
            'description'  => $data['description'] ?? null,
            'created_at'   => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * به‌روزرسانی حساب.
     */
    public static function update(string $code, array $data): bool
    {
        global $wpdb;
        $existing = self::find($code);
        if ((int) $existing['is_system'] === 1 && isset($data['code'])) {
            throw new \RuntimeException('Cannot rename system account.');
        }
        $data['updated_at'] = current_time('mysql');
        $r = $wpdb->update($wpdb->prefix . 'ent_accounts', $data, ['id' => $existing['id']]);
        return $r !== false;
    }

    /**
     * لیست تمام حساب‌ها (درختی).
     */
    public static function tree(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ent_accounts WHERE deleted_at IS NULL ORDER BY code ASC",
            ARRAY_A
        );
        return self::buildTree($rows ?: []);
    }

    /**
     * ساخت درخت از لیست خطی.
     */
    private static function buildTree(array $rows, ?string $parentCode = null): array
    {
        $branch = [];
        foreach ($rows as $r) {
            if (($r['parent_code'] ?? null) === $parentCode) {
                $r['children'] = self::buildTree($rows, $r['code']);
                $branch[] = $r;
            }
        }
        return $branch;
    }

    /**
     * محاسبهٔ موجودی حساب در بازهٔ زمانی.
     *
     * @return array{debit:float, credit:float, balance:float}
     */
    public static function balance(string $code, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $wpdb;
        $acc = self::find($code);
        $sql = "SELECT
                  COALESCE(SUM(jl.debit), 0)  AS total_debit,
                  COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM {$wpdb->prefix}ent_journal_lines jl
                INNER JOIN {$wpdb->prefix}ent_journal_entries je ON je.id = jl.entry_id
                WHERE jl.account_id = %d
                  AND je.status = 'posted'";
        $params = [(int) $acc['id']];
        if ($dateFrom !== null) {
            $sql .= ' AND je.entry_date >= %s';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND je.entry_date <= %s';
            $params[] = $dateTo;
        }
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        $debit = (float) ($row['total_debit'] ?? 0);
        $credit = (float) ($row['total_credit'] ?? 0);
        $balance = $acc['nature'] === self::NATURE_DEBIT
            ? $debit - $credit
            : $credit - $debit;
        return [
            'debit'   => $debit,
            'credit'  => $credit,
            'balance' => $balance,
            'nature'  => $acc['nature'],
        ];
    }

    /**
     * تشخیص نوع حساب از روی کد (۵ رقمی).
     */
    public static function typeFor(string $code): string
    {
        if (!preg_match('/^\d{5}$/', $code)) {
            throw new \InvalidArgumentException('Account code must be 5 digits: ' . $code);
        }
        $first = (int) $code[0];
        return match (true) {
            $first === 1 => self::TYPE_ASSET,
            $first === 2 => self::TYPE_LIABILITY,
            $first === 3 => self::TYPE_EQUITY,
            $first === 4 => self::TYPE_REVENUE,
            $first === 5 => self::TYPE_EXPENSE,
            $first === 6 => self::TYPE_MEMO,
            default      => throw new \InvalidArgumentException('Invalid account code prefix: ' . $code),
        };
    }

    /**
     * ماهیت طبیعی حساب بر اساس نوع.
     */
    public static function natureFor(string $type): string
    {
        return match ($type) {
            self::TYPE_ASSET, self::TYPE_EXPENSE => self::NATURE_DEBIT,
            self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_REVENUE => self::NATURE_CREDIT,
            self::TYPE_MEMO => self::NATURE_DEBIT, // arbitrary
            default => throw new \InvalidArgumentException('Invalid account type: ' . $type),
        };
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_ASSET, self::TYPE_LIABILITY, self::TYPE_EQUITY,
            self::TYPE_REVENUE, self::TYPE_EXPENSE, self::TYPE_MEMO,
        ], true);
    }

    /**
     * نام پیش‌فرض برای کدهای استاندارد ایران (زیرمجموعه‌ای).
     */
    private static function defaultLabelFor(string $code): string
    {
        $labels = [
            '10000' => 'دارایی‌ها',
            '11000' => 'دارایی‌های جاری',
            '11100' => 'صندوق',
            '11110' => 'صندوق ریالی',
            '11120' => 'صندوق ارزی',
            '11200' => 'بانک',
            '11210' => 'بانک ملی',
            '11220' => 'بانک ملت',
            '11230' => 'بانک تجارت',
            '11240' => 'بانک صادرات',
            '11300' => 'موجودی کالا',
            '11400' => 'مطالبات',
            '11410' => 'مطالبات از مشتریان',
            '11420' => 'اسناد دریافتنی',
            '11500' => 'پیش‌پرداخت‌ها',
            '12000' => 'دارایی‌های غیرجاری',
            '12100' => 'دارایی‌های ثابت',
            '12110' => 'زمین',
            '12120' => 'ساختمان',
            '12130' => 'ماشین‌آلات',
            '12140' => 'اثاثیه',
            '12200' => 'استهلاک انباشته',
            '20000' => 'بدهی‌ها',
            '21000' => 'بدهی‌های جاری',
            '21100' => 'پرداختنی‌ها',
            '21110' => 'حساب‌های پرداختنی',
            '21120' => 'اسناد پرداختنی',
            '21200' => 'مالیات پرداختنی',
            '21210' => 'مالیات بر ارزش افزوده',
            '21220' => 'مالیات حقوق',
            '21230' => 'مالیات تکلیفی',
            '21300' => 'بیمه پرداختنی',
            '21310' => 'بیمهٔ تأمین اجتماعی',
            '22000' => 'بدهی‌های بلندمدت',
            '22100' => 'وام‌های بانکی',
            '30000' => 'حقوق صاحبان سهام',
            '31000' => 'سرمایه',
            '32000' => 'سود انباشته',
            '33000' => 'سود (زیان) جاری',
            '40000' => 'درآمدها',
            '41000' => 'درآمد فروش',
            '41100' => 'فروش کالا',
            '41200' => 'فروش خدمات',
            '42000' => 'درآمد متفرقه',
            '43000' => 'تخفیفات نقدی فروش',
            '50000' => 'هزینه‌ها',
            '51000' => 'بهای تمام‌شدهٔ کالای فروش رفته',
            '52000' => 'هزینه‌های عملیاتی',
            '52100' => 'حقوق و دستمزد',
            '52110' => 'حقوق پایه',
            '52120' => 'اضافه‌کار',
            '52130' => 'مزایا',
            '52200' => 'هزینهٔ اجاره',
            '52300' => 'هزینهٔ آب و برق و گاز',
            '52400' => 'هزینهٔ تلفن و اینترنت',
            '52500' => 'هزینهٔ حمل و نقل',
            '52600' => 'هزینهٔ بیمه',
            '52700' => 'هزینهٔ تبلیغات',
            '52800' => 'هزینهٔ استهلاک',
            '52900' => 'سایر هزینه‌های عملیاتی',
            '53000' => 'هزینه‌های مالی',
            '53100' => 'کارمزد بانکی',
            '53200' => 'سود وام',
            '54000' => 'هزینه‌های متفرقه',
            '60000' => 'حساب‌های انتظامی',
            '61000' => 'تعهدات',
            '62000' => 'وثایق',
        ];
        return $labels[$code] ?? 'حساب ' . $code;
    }
}
