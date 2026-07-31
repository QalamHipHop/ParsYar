<?php
/**
 * Leave Service — درخواست/تأیید مرخصی، محاسبه مانده.
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Str;
use Enterprise\Validator;

final class LeaveService
{
    public const TYPE_ANNUAL    = 'annual';
    public const TYPE_SICK      = 'sick';
    public const TYPE_UNPAID    = 'unpaid';
    public const TYPE_MATERNITY = 'maternity';
    public const TYPE_PATERNITY = 'paternity';
    public const TYPE_BEREAVEMENT = 'bereavement';
    public const TYPE_MARRIAGE    = 'marriage';
    public const TYPE_PILGRIMAGE  = 'pilgrimage';
    public const TYPE_MISSION     = 'mission';
    public const TYPE_SICK_FAMILY = 'sick_family';

    /** @var string[] */
    public const TYPES = [
        self::TYPE_ANNUAL, self::TYPE_SICK, self::TYPE_UNPAID, self::TYPE_MATERNITY,
        self::TYPE_PATERNITY, self::TYPE_BEREAVEMENT, self::TYPE_MARRIAGE,
        self::TYPE_PILGRIMAGE, self::TYPE_MISSION, self::TYPE_SICK_FAMILY,
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var string[] */
    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_APPROVED,
        self::STATUS_REJECTED, self::STATUS_CANCELLED,
    ];

    /**
     * ثبت درخواست مرخصی.
     *
     * @param array{
     *   employee_id:int, type:string, start_date:string, end_date:string,
     *   reason?:string, attachment_url?:string, half_day?:bool, hourly?:bool,
     *   hours?:float, company_id?:int
     * } $data
     */
    public static function request(array $data): int
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        if ($employeeId === 0) {
            throw new \InvalidArgumentException('employee_id required');
        }
        $type = (string) ($data['type'] ?? self::TYPE_ANNUAL);
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('invalid leave type');
        }
        $start = self::normalizeDate($data['start_date'] ?? null);
        $end   = self::normalizeDate($data['end_date']   ?? null);
        if ($start === null || $end === null) {
            throw new \InvalidArgumentException('start_date and end_date are required');
        }
        if ($end < $start) {
            throw new \InvalidArgumentException('end_date cannot be before start_date');
        }
        $days = (int) ($data['days'] ?? self::countDays($start, $end, (bool) ($data['half_day'] ?? false)));
        if ($type === self::TYPE_ANNUAL) {
            $balance = self::annualBalance($employeeId);
            if ($days > $balance) {
                throw new \InvalidArgumentException(sprintf('requested %d day(s), balance only %d', $days, $balance));
            }
        }

        $payload = [
            'uuid'          => Str::uuid(),
            'employee_id'   => $employeeId,
            'type'          => $type,
            'start_date'    => $start,
            'end_date'      => $end,
            'days'          => $days,
            'half_day'      => !empty($data['half_day']) ? 1 : 0,
            'hourly'        => !empty($data['hourly'])   ? 1 : 0,
            'hours'         => (float) ($data['hours'] ?? 0),
            'reason'        => sanitize_textarea_field((string) ($data['reason'] ?? '')),
            'attachment_url'=> esc_url_raw((string) ($data['attachment_url'] ?? '')),
            'status'        => self::STATUS_PENDING,
            'company_id'    => (int) ($data['company_id'] ?? 1),
            'requested_by'  => get_current_user_id() ?: null,
        ];
        return (int) Db::insert('leave_requests', $payload);
    }

    public static function approve(int $id, ?int $approverId = null, ?string $note = null): bool
    {
        $row = self::find($id);
        if (!$row || $row['status'] !== self::STATUS_PENDING) {
            return false;
        }
        return Db::update('leave_requests', [
            'status'        => self::STATUS_APPROVED,
            'approved_by'   => $approverId ?? get_current_user_id() ?: null,
            'approved_at'   => gmdate('Y-m-d H:i:s'),
            'decision_note' => $note !== null ? sanitize_textarea_field($note) : null,
        ], ['id' => $id]) !== false;
    }

    public static function reject(int $id, ?int $approverId = null, ?string $note = null): bool
    {
        $row = self::find($id);
        if (!$row || $row['status'] !== self::STATUS_PENDING) {
            return false;
        }
        return Db::update('leave_requests', [
            'status'        => self::STATUS_REJECTED,
            'approved_by'   => $approverId ?? get_current_user_id() ?: null,
            'approved_at'   => gmdate('Y-m-d H:i:s'),
            'decision_note' => $note !== null ? sanitize_textarea_field($note) : null,
        ], ['id' => $id]) !== false;
    }

    public static function cancel(int $id, ?int $cancelledBy = null): bool
    {
        return Db::update('leave_requests', [
            'status'       => self::STATUS_CANCELLED,
            'cancelled_by' => $cancelledBy ?? get_current_user_id() ?: null,
            'cancelled_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]) !== false;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow('leave_requests', ['id' => $id]);
        return $row ?: null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listFor(int $employeeId, ?string $status = null, int $limit = 100): array
    {
        $where = ['employee_id' => $employeeId];
        if ($status !== null) {
            $where['status'] = $status;
        }
        return Db::getResults('leave_requests', $where, 'start_date DESC', $limit, 0);
    }

    public static function pendingCount(?int $companyId = null): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_leave_requests';
        if ($companyId !== null) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND company_id = %d", $companyId
            ));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
    }

    public static function annualBalance(int $employeeId, ?int $year = null): float
    {
        $year   = $year ?? (int) gmdate('Y');
        $total  = (float) get_user_meta($employeeId, 'parsyar_annual_leave_total', true) ?: 26.0;
        $used   = (float) Db::getVar('leave_requests', [
            'employee_id' => $employeeId,
            'type'        => self::TYPE_ANNUAL,
            'status'      => self::STATUS_APPROVED,
        ], 'SUM(days)', 0.0);
        // crude: does not filter by year in this stub. Filter in real impl.
        $usedYear = (float) Db::getVar('leave_requests', [
            'employee_id' => $employeeId,
            'type'        => self::TYPE_ANNUAL,
            'status'      => self::STATUS_APPROVED,
        ], 'SUM(days)', 0.0);
        return max(0.0, $total - $usedYear);
    }

    /**
     * @return array{approved_days:float, pending_days:float, sick_days:float, total:float}
     */
    public static function yearReport(int $employeeId, int $year): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_leave_requests';
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-12-31', $year);
        $sql = $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status='approved' AND type='annual' THEN days END), 0) AS approved_days,
                COALESCE(SUM(CASE WHEN status='pending'  AND type='annual' THEN days END), 0) AS pending_days,
                COALESCE(SUM(CASE WHEN status='approved' AND type='sick'   THEN days END), 0) AS sick_days
             FROM {$table}
             WHERE employee_id = %d AND start_date BETWEEN %s AND %s",
            $employeeId, $start, $end
        );
        $row = $wpdb->get_row($sql, ARRAY_A) ?: [];
        $approved = (float) ($row['approved_days'] ?? 0);
        $total = (float) get_user_meta($employeeId, 'parsyar_annual_leave_total', true) ?: 26.0;
        return [
            'approved_days' => $approved,
            'pending_days'  => (float) ($row['pending_days'] ?? 0),
            'sick_days'     => (float) ($row['sick_days'] ?? 0),
            'total'         => $total,
        ];
    }

    public static function countDays(string $start, string $end, bool $halfDay = false): int
    {
        $a = strtotime($start);
        $b = strtotime($end);
        if ($a === false || $b === false) {
            return 0;
        }
        $diff = (int) floor(($b - $a) / 86400) + 1;
        return $halfDay ? (int) ceil($diff / 2) : $diff;
    }

    private static function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        return $ts === false ? null : gmdate('Y-m-d', $ts);
    }
}
