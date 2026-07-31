<?php
/**
 * Payroll Service — محاسبه حقوق مطابق قوانین ایران (مالیات + بیمه)
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;
use Enterprise\Modules\Accounting\Ledger;
use Enterprise\Support\Db;

final class PayrollService
{
    /** نرخ‌ها — در نسخهٔ واقعی باید جدول سالانه داشته باشیم */
    public const TAX_RATE          = 0.10; // مالیات نمونه
    public const INS_EMPLOYEE_RATE = 0.07; // بیمه سهم کارمند
    public const INS_EMPLOYER_RATE = 0.23; // بیمه سهم کارفرما

    /**
     * اجرای حقوق برای یک دوره (مثلاً 1404-05 یا 2024-08).
     * ثبت سند حسابداری دوطرفه برای هر کارمند + ثبت PayrollRun.
     */
    public static function run(string $period, array $opts = []): array
    {
        $employees = EmployeeService::all(['status' => 'active'], 1000);
        $issued    = 0;
        $totalGross = $totalNet = $totalTax = $totalIns = 0.0;
        $lines = [];
        $companyId = (int) ($opts['company_id'] ?? 1);
        $branchId  = isset($opts['branch_id']) ? (int) $opts['branch_id'] : null;
        $currency  = (string) ($opts['currency'] ?? 'IRT');

        foreach ($employees as $e) {
            $base = (float) $e['base_salary'];
            $tax  = round($base * self::TAX_RATE, 2);
            $ins  = round($base * self::INS_EMPLOYEE_RATE, 2);
            $net  = $base - $tax - $ins;
            $totalGross += $base;
            $totalNet   += $net;
            $totalTax   += $tax;
            $totalIns   += $ins;
            $no = sprintf('PAY-%s-%d', $period, (int) $e['id']);
            $lines[] = [
                'employee_id' => (int) $e['id'],
                'name'        => $e['full_name'],
                'gross'       => $base,
                'tax'         => $tax,
                'insurance'   => $ins,
                'net'         => $net,
                'ref'         => $no,
            ];
            try {
                Ledger::post([
                    'entry_date'  => self::periodEnd($period),
                    'description' => 'Payroll ' . $e['full_name'] . ' ' . $period,
                    'source'      => 'payroll',
                    'source_ref'  => $no,
                    'currency'    => $currency,
                    'lines'       => [
                        ['account_code' => '5200', 'debit'  => $base, 'description' => 'حقوق'],
                        ['account_code' => '2130', 'credit' => $net,  'description' => 'خالص پرداختنی'],
                        ['account_code' => '2110', 'credit' => $tax,  'description' => 'مالیات'],
                        ['account_code' => '2120', 'credit' => $ins,  'description' => 'بیمه'],
                    ],
                ]);
                $issued++;
            } catch (\Throwable $ex) {
                Logger::log('payroll', (int) $e['id'], 'failed', ['period' => $period, 'error' => $ex->getMessage()]);
            }
        }
        $runId = self::recordRun($period, $companyId, $branchId, $currency, [
            'employee_count'    => $issued,
            'total_gross'       => $totalGross,
            'total_net'         => $totalNet,
            'total_tax'         => $totalTax,
            'total_insurance'   => $totalIns,
        ]);

        do_action('enterprise_event', 'payroll.run_completed', [
            'period' => $period,
            'run_id' => $runId,
            'count'  => $issued,
            'total'  => $totalNet,
        ]);
        return [
            'run_id'       => $runId,
            'period'       => $period,
            'issued'       => $issued,
            'total_gross'  => $totalGross,
            'total_net'    => $totalNet,
            'total_tax'    => $totalTax,
            'total_ins'    => $totalIns,
            'currency'     => $currency,
            'lines'        => $lines,
        ];
    }

    public static function history(int $limit = 50): array
    {
        return Db::getResults('payroll_runs', [], 'id DESC', max(1, min(200, $limit)), 0, 'parsyar');
    }

    public static function find(int $id): ?array
    {
        return Db::getRow('payroll_runs', ['id' => $id], 'parsyar');
    }

    private static function recordRun(string $period, int $companyId, ?int $branchId, string $currency, array $totals): int
    {
        return Db::insert('payroll_runs', [
            'uuid'             => self::uuid(),
            'period'           => $period,
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'employee_count'   => (int) ($totals['employee_count'] ?? 0),
            'total_gross'      => (float) ($totals['total_gross'] ?? 0),
            'total_net'        => (float) ($totals['total_net'] ?? 0),
            'total_tax'        => (float) ($totals['total_tax'] ?? 0),
            'total_insurance'  => (float) ($totals['total_insurance'] ?? 0),
            'currency'         => $currency,
            'status'           => 'completed',
            'run_by'           => get_current_user_id() ?: null,
        ], 'parsyar');
    }

    private static function periodEnd(string $period): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return gmdate('Y-m-t', strtotime(sprintf('%04d-%02d-01', (int) $m[1], (int) $m[2])));
        }
        return gmdate('Y-m-d');
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
