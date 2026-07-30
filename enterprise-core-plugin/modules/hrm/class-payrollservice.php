<?php
declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Accounting\Ledger;

/**
 * محاسبه حقوق مطابق قوانین ایران (مالیات + بیمه).
 * در این نسخه ساده‌سازی شده؛ برای استفاده واقعی باید جداول نرخ سالانه اضافه شود.
 */
final class PayrollService
{
    private const TAX_RATE   = 0.10; // نرخ نمونه
    private const INS_RATE   = 0.07; // بیمه سهم کارمند
    private const INS_EMPLOYER_RATE = 0.23;

    /**
     * اجرای حقوق برای یک دوره (مثلاً 1403-05).
     */
    public static function run(string $period): array
    {
        $employees = Db::getResults('employees', []);
        $issued    = 0;
        $totalNet  = 0.0;
        foreach ($employees as $e) {
            $base   = (float) $e['base_salary'];
            $tax    = round($base * self::TAX_RATE, 2);
            $ins    = round($base * self::INS_RATE, 2);
            $net    = $base - $tax - $ins;
            $totalNet += $net;

            $no = sprintf('PAY-%s-%s-%d', $period, substr((string) $e['id'], 0, 6), $e['id']);
            Ledger::post([
                'entry_date'  => gmdate('Y-m-t', strtotime($period . '-01')),
                'description' => 'Payroll ' . $e['full_name'] . ' ' . $period,
                'source'      => 'payroll',
                'source_ref'  => $no,
                'lines'       => [
                    ['account_code' => '5200', 'debit'  => $base, 'description' => 'حقوق'],
                    ['account_code' => '2130', 'credit' => $net,  'description' => 'حقوق پرداختنی'],
                    ['account_code' => '2110', 'credit' => $tax,  'description' => 'مالیات'],
                    ['account_code' => '2120', 'credit' => $ins,  'description' => 'بیمه'],
                ],
            ]);
            $issued++;
        }
        return [
            'period'    => $period,
            'issued'    => $issued,
            'total_net' => $totalNet,
        ];
    }
}
