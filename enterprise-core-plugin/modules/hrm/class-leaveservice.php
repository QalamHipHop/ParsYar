<?php
/**
 * Leave Service — مدیریت مرخصی
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class LeaveService
{
    public const TABLE = 'leave_requests';

    public const TYPES = [
        'annual'    => 'استحقاقی',
        'sick'      => 'استعلاجی',
        'unpaid'    => 'بدون حقوق',
        'maternity' => 'زایمان',
        'paternity' => 'پدر',
        'mission'   => 'مأموریت',
        'other'     => 'سایر',
    ];

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public static function find(int $id): ?array
    {
        return Db::getRow(self::TABLE, ['id' => $id]);
    }

    public static function listForEmployee(int $employeeId, int $limit = 50): array
    {
        return Db::getResults(self::TABLE, ['employee_id' => $employeeId], 'start_date DESC', max(1, min(200, $limit)), 0, 'parsyar');
    }

    public static function listPending(int $limit = 100): array
    {
        return Db::getResults(self::TABLE, ['status' => 'pending'], 'start_date ASC', max(1, min(500, $limit)), 0, 'parsyar');
    }

    public static function create(array $data): int
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            throw new \InvalidArgumentException('employee_id الزامی است');
        }
        if (!EmployeeService::find($employeeId)) {
            throw new \InvalidArgumentException('کارمند یافت نشد');
        }
        $type = (string) ($data['type'] ?? 'annual');
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('نوع مرخصی نامعتبر');
        }
        $start = (string) ($data['start_date'] ?? '');
        $end   = (string) ($data['end_date'] ?? $start);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            throw new \InvalidArgumentException('تاریخ نامعتبر (YYYY-MM-DD)');
        }
        if (strtotime($end) < strtotime($start)) {
            throw new \InvalidArgumentException('تاریخ پایان نمی‌تواند قبل از شروع باشد');
        }
        $days = (int) ((strtotime($end) - strtotime($start)) / 86400) + 1;
        $id = Db::insert(self::TABLE, [
            'uuid'         => self::uuid(),
            'employee_id'  => $employeeId,
            'type'         => $type,
            'start_date'   => $start,
            'end_date'     => $end,
            'days_count'   => $days,
            'reason'       => sanitize_textarea_field((string) ($data['reason'] ?? '')),
            'status'       => 'pending',
        ], 'parsyar');
        Logger::log('leave', $id, 'create', ['employee_id' => $employeeId, 'days' => $days]);
        do_action('enterprise_event', 'leave.created', ['leave_id' => $id, 'employee_id' => $employeeId, 'days' => $days]);
        return $id;
    }

    public static function approve(int $id, ?int $approverId = null, ?string $note = null): bool
    {
        $existing = self::find($id);
        if (!$existing || $existing['status'] !== 'pending') {
            return false;
        }
        Db::update(self::TABLE, [
            'status'       => 'approved',
            'approver_id'  => $approverId ?: get_current_user_id() ?: null,
            'approved_at'  => current_time('mysql', true),
            'decision_note'=> $note !== null ? sanitize_textarea_field($note) : null,
        ], ['id' => $id], 'parsyar');
        Logger::log('leave', $id, 'approve', ['approver' => $approverId]);
        do_action('enterprise_event', 'leave.approved', ['leave_id' => $id, 'employee_id' => (int) $existing['employee_id']]);
        return true;
    }

    public static function reject(int $id, ?int $approverId = null, ?string $note = null): bool
    {
        $existing = self::find($id);
        if (!$existing || $existing['status'] !== 'pending') {
            return false;
        }
        Db::update(self::TABLE, [
            'status'       => 'rejected',
            'approver_id'  => $approverId ?: get_current_user_id() ?: null,
            'approved_at'  => current_time('mysql', true),
            'decision_note'=> $note !== null ? sanitize_textarea_field($note) : null,
        ], ['id' => $id], 'parsyar');
        Logger::log('leave', $id, 'reject', ['approver' => $approverId]);
        return true;
    }

    public static function cancel(int $id, int $employeeId): bool
    {
        $existing = self::find($id);
        if (!$existing || (int) $existing['employee_id'] !== $employeeId) {
            return false;
        }
        if (!in_array($existing['status'], ['pending', 'approved'], true)) {
            return false;
        }
        Db::update(self::TABLE, ['status' => 'cancelled'], ['id' => $id], 'parsyar');
        Logger::log('leave', $id, 'cancel', []);
        return true;
    }

    /**
     * بررسی برخورد با مرخصی‌های ثبت‌شده.
     */
    public static function hasOverlap(int $employeeId, string $start, string $end, ?int $ignoreId = null): bool
    {
        global $wpdb;
        $t = Db::table(self::TABLE, 'parsyar');
        $sql = $wpdb->prepare(
            "SELECT id FROM {$t}
             WHERE employee_id = %d
               AND status IN ('pending','approved')
               AND NOT (end_date < %s OR start_date > %s)" .
               ($ignoreId ? " AND id != %d" : ''),
            ...array_filter([$employeeId, $start, $end, $ignoreId], fn($v) => $v !== null)
        );
        return (bool) $wpdb->get_var($sql);
    }

    public static function balance(int $employeeId, int $year): array
    {
        $rows = Db::query(
            "SELECT type, SUM(days_count) AS used
             FROM " . Db::table(self::TABLE, 'parsyar') . "
             WHERE employee_id = %d
               AND status = 'approved'
               AND YEAR(start_date) = %d
             GROUP BY type",
            [$employeeId, $year]
        );
        $byType = [];
        foreach ($rows as $r) {
            $byType[$r['type']] = (float) $r['used'];
        }
        $entitlement = 26.0; // روزهای استحقاقی سالانه طبق قانون کار
        $usedAnnual  = (float) ($byType['annual'] ?? 0);
        return [
            'year'          => $year,
            'employee_id'   => $employeeId,
            'entitlement'   => $entitlement,
            'used_annual'   => $usedAnnual,
            'remaining'     => max(0, $entitlement - $usedAnnual),
            'by_type'       => $byType,
        ];
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
