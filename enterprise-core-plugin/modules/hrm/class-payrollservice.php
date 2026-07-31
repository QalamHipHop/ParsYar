<?php
/**
 * Payroll Service — محاسبه حقوق و دستمزد ماهانه.
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Str;

final class PayrollService
{
    /** نرخ بیمه سهم کارمند (۷٪) */
    public const INSURANCE_RATE = 0.07;
    /** نرخ مالیات بر حقوق (ساده شده؛ جدول واقعی بر اساس پلکانی) */
    public const TAX_BRACKETS = [
        [50_000_000, 0.00],
        [100_000_000, 0.10],
        [200_000_000, 0.15],
        [400_000_000, 0.20],
        [PHP_INT_MAX, 0.25],
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * ایجاد یک Run حقوق.
     *
     * @return int payroll_run_id
     */
    public static function createRun(int $year, int $month, ?int $companyId = null): int
    {
        $companyId ??= 1;
        $existing = (int) Db::getVar('payroll_runs', [
            'company_id'    => $companyId,
            'period_year'   => $year,
            'period_month'  => $month,
        ], 'id', 0);
        if ($existing > 0) {
            return $existing;
        }
        return (int) Db::insert('payroll_runs', [
            'uuid'           => Str::uuid(),
            'period_year'    => $year,
            'period_month'   => $month,
            'period_label'   => self::formatPeriod($year, $month),
            'status'         => self::STATUS_DRAFT,
            'currency'       => 'IRT',
            'company_id'     => $companyId,
            'created_by'     => get_current_user_id() ?: null,
        ]);
    }

    /**
     * محاسبه حقوق برای همه کارمندان فعال یک run.
     */
    public static function calculate(int $runId): array
    {
        $run = self::findRun($runId);
        if (!$run) {
            throw new \InvalidArgumentException("Payroll run {$runId} not found");
        }
        $employees = Db::getResults('employees', [
            'company_id' => (int) $run['company_id'],
            'status'     => 'active',
        ], 'id ASC', 1000, 0);

        $totals = [
            'employees' => 0, 'gross' => 0.0, 'net' => 0.0,
            'tax' => 0.0, 'insurance' => 0.0, 'overtime' => 0.0,
        ];

        foreach ($employees as $emp) {
            $item = self::calculateEmployee(
                (int) $emp['id'],
                (int) $run['period_year'],
                (int) $run['period_month']
            );
            self::saveItem($runId, (int) $emp['id'], $item);
            $totals['employees']++;
            $totals['gross']     += $item['base_salary'] + $item['overtime_pay'] + $item['bonus'];
            $totals['net']       += $item['net_pay'];
            $totals['tax']       += $item['tax'];
            $totals['insurance'] += $item['insurance'];
            $totals['overtime']  += $item['overtime_pay'];
        }

        Db::update('payroll_runs', [
            'total_employees'  => $totals['employees'],
            'total_gross'      => $totals['gross'],
            'total_net'        => $totals['net'],
            'total_tax'        => $totals['tax'],
            'total_insurance'  => $totals['insurance'],
            'total_overtime'   => $totals['overtime'],
            'status'           => self::STATUS_PROCESSING,
        ], ['id' => $runId]);

        return $totals;
    }

    /**
     * محاسبه حقوق یک کارمند.
     *
     * @return array<string, float|int>
     */
    public static function calculateEmployee(int $employeeId, int $year, int $month): array
    {
        $emp = EmployeeService::find($employeeId);
        if (!$emp) {
            throw new \InvalidArgumentException("Employee {$employeeId} not found");
        }
        $baseSalary = (float) ($emp['base_salary'] ?? 0);
        $daily      = (float) ($emp['daily_wage']  ?? 0);
        $hourly     = (float) ($emp['hourly_wage'] ?? 0);

        // Worked days = present + late + remote
        $summary    = AttendanceService::monthSummary($employeeId, $year, $month);
        $workedDays = (int) ($summary['present'] ?? 0);

        // Overtime
        $overtimeMin  = (int) ($summary['overtime_minutes'] ?? 0);
        $overtimeHr   = $overtimeMin / 60.0;
        $overtimeRate = 1.4; // 140% per Iranian Labor Code
        $overtimePay  = round($hourly > 0 ? $hourly * $overtimeHr * $overtimeRate : 0, 2);

        // Leave days
        $approved = LeaveService::yearReport($employeeId, $year);
        $unpaidDays = self::unpaidLeaveDays($employeeId, $year, $month);

        // Pro-rated base
        $grossBase = $baseSalary > 0 ? round($baseSalary * ($workedDays / 30.0), 2) : round($daily * $workedDays, 2);

        $bonus      = 0.0; // pop bonus from attendance or contract
        $deductions = 0.0;

        $taxableBase = $grossBase + $overtimePay + $bonus;
        $tax        = self::calcTax($taxableBase);
        $insurance  = round($grossBase * self::INSURANCE_RATE, 2);

        $net = max(0.0, $grossBase + $overtimePay + $bonus - $tax - $insurance - $deductions);

        return [
            'base_salary'      => $grossBase,
            'overtime_pay'     => $overtimePay,
            'bonus'            => $bonus,
            'deductions'       => $deductions,
            'tax'              => $tax,
            'insurance'        => $insurance,
            'net_pay'          => round($net, 2),
            'worked_days'      => $workedDays,
            'paid_leave_days'  => (float) ($approved['approved_days'] ?? 0),
            'unpaid_leave_days'=> (float) $unpaidDays,
            'overtime_hours'   => round($overtimeHr, 2),
            'late_minutes'     => (int) ($summary['late_minutes'] ?? 0),
        ];
    }

    public static function approve(int $runId, ?int $approverId = null): bool
    {
        return Db::update('payroll_runs', [
            'status'      => self::STATUS_APPROVED,
            'approved_by' => $approverId ?? get_current_user_id() ?: null,
            'approved_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $runId]) !== false;
    }

    public static function markPaid(int $runId, ?int $paidBy = null): bool
    {
        return Db::update('payroll_runs', [
            'status'   => self::STATUS_PAID,
            'paid_at'  => gmdate('Y-m-d H:i:s'),
        ], ['id' => $runId]) !== false;
    }

    public static function findRun(int $runId): ?array
    {
        return Db::getRow('payroll_runs', ['id' => $runId]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function itemsFor(int $runId): array
    {
        return Db::getResults('payroll_items', ['payroll_run_id' => $runId], 'id ASC', 1000, 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function runs(?int $companyId = null, int $limit = 24): array
    {
        $where = [];
        if ($companyId !== null) {
            $where['company_id'] = $companyId;
        }
        return Db::getResults('payroll_runs', $where, 'period_year DESC, period_month DESC', $limit, 0);
    }

    private static function saveItem(int $runId, int $employeeId, array $item): void
    {
        $existing = Db::getRow('payroll_items', [
            'payroll_run_id' => $runId,
            'employee_id'    => $employeeId,
        ]);
        $payload = $item + ['payroll_run_id' => $runId, 'employee_id' => $employeeId];
        if ($existing) {
            Db::update('payroll_items', $payload, ['id' => (int) $existing['id']]);
        } else {
            Db::insert('payroll_items', $payload);
        }
    }

    private static function calcTax(float $taxable): float
    {
        if ($taxable <= 0) {
            return 0.0;
        }
        $tax = 0.0;
        $remaining = $taxable;
        $lower = 0.0;
        foreach (self::TAX_BRACKETS as [$upper, $rate]) {
            if ($remaining <= 0) {
                break;
            }
            $span = $upper - $lower;
            $portion = min($span, $remaining);
            $tax += $portion * $rate;
            $remaining -= $portion;
            $lower = $upper;
        }
        return round($tax, 2);
    }

    private static function unpaidLeaveDays(int $employeeId, int $year, int $month): float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_leave_requests';
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = gmdate('Y-m-t', strtotime($start));
        $sql = $wpdb->prepare(
            "SELECT COALESCE(SUM(days), 0) FROM {$table}
             WHERE employee_id = %d AND type = 'unpaid' AND status = 'approved'
             AND ((start_date BETWEEN %s AND %s) OR (end_date BETWEEN %s AND %s))",
            $employeeId, $start, $end, $start, $end
        );
        return (float) ($wpdb->get_var($sql) ?: 0);
    }

    private static function formatPeriod(int $year, int $month): string
    {
        $names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                  'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        return ($names[$month] ?? (string) $month) . ' ' . $year;
    }
}
