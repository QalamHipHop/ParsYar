<?php
/**
 * Attendance Service — ثبت ورود/خروج، محاسبه تأخیر و اضافه‌کاری.
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Str;

final class AttendanceService
{
    public const STATUS_PRESENT  = 'present';
    public const STATUS_LATE     = 'late';
    public const STATUS_ABSENT   = 'absent';
    public const STATUS_LEAVE    = 'leave';
    public const STATUS_HOLIDAY  = 'holiday';
    public const STATUS_REMOTE   = 'remote';

    /** @var string[] */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_ABSENT,
        self::STATUS_LEAVE,
        self::STATUS_HOLIDAY,
        self::STATUS_REMOTE,
    ];

    /**
     * ثبت یک رکورد تردد.
     *
     * @param array{
     *   employee_id:int, work_date:string, check_in?:string, check_out?:string,
     *   status?:string, source?:string, latitude?:float, longitude?:float,
     *   device_ip?:string, notes?:string, company_id?:int, branch_id?:int
     * } $data
     */
    public static function record(array $data): int
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $workDate   = self::normalizeDate($data['work_date'] ?? null);
        if ($employeeId === 0 || $workDate === null) {
            throw new \InvalidArgumentException('employee_id and work_date are required');
        }

        $checkIn  = self::normalizeTime($data['check_in']  ?? null);
        $checkOut = self::normalizeTime($data['check_out'] ?? null);

        $status = (string) ($data['status'] ?? self::STATUS_PRESENT);
        if (!in_array($status, self::STATUSES, true)) {
            $status = self::STATUS_PRESENT;
        }

        $overtimeMinutes = (int) ($data['overtime_minutes'] ?? self::computeOvertime($checkIn, $checkOut));
        $lateMinutes     = (int) ($data['late_minutes']     ?? self::computeLate($checkIn));
        $workMinutes     = self::computeWorkMinutes($checkIn, $checkOut);
        $isAnomaly       = self::detectAnomaly($checkIn, $checkOut, $status);

        $existing = self::findByDate($employeeId, $workDate);

        $payload = [
            'employee_id'      => $employeeId,
            'work_date'        => $workDate,
            'check_in'         => $checkIn,
            'check_out'        => $checkOut,
            'work_minutes'     => $workMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes'     => $lateMinutes,
            'status'           => $status,
            'source'           => sanitize_text_field((string) ($data['source'] ?? 'manual')),
            'latitude'         => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude'        => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'device_ip'        => sanitize_text_field((string) ($data['device_ip'] ?? '')),
            'is_anomaly'       => $isAnomaly ? 1 : 0,
            'notes'            => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'company_id'       => (int) ($data['company_id'] ?? 1),
            'branch_id'        => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            'recorded_by'      => get_current_user_id() ?: null,
        ];

        if ($existing) {
            Db::update('attendance', $payload, ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }
        return (int) Db::insert('attendance', $payload + ['uuid' => Str::uuid()]);
    }

    public static function findByDate(int $employeeId, string $workDate): ?array
    {
        $row = Db::getRow('attendance', ['employee_id' => $employeeId, 'work_date' => $workDate]);
        return $row ?: null;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public static function monthGrid(int $employeeId, int $year, int $month): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_attendance';
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = gmdate('Y-m-t', strtotime($start));
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE employee_id = %d AND work_date BETWEEN %s AND %s
             ORDER BY work_date ASC",
            $employeeId, $start, $end
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{present:int, late:int, absent:int, leave:int, holiday:int, remote:int, total_minutes:int, overtime_minutes:int, late_minutes:int}
     */
    public static function monthSummary(int $employeeId, int $year, int $month): array
    {
        $rows = self::monthGrid($employeeId, $year, $month);
        $out = [
            'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0,
            'holiday' => 0, 'remote' => 0, 'total_minutes' => 0,
            'overtime_minutes' => 0, 'late_minutes' => 0,
        ];
        foreach ($rows as $r) {
            $status = (string) ($r['status'] ?? '');
            if (isset($out[$status])) {
                $out[$status]++;
            }
            $out['total_minutes']    += (int) ($r['work_minutes'] ?? 0);
            $out['overtime_minutes'] += (int) ($r['overtime_minutes'] ?? 0);
            $out['late_minutes']     += (int) ($r['late_minutes'] ?? 0);
        }
        return $out;
    }

    public static function checkIn(int $employeeId, ?float $lat = null, ?float $lng = null): array
    {
        $now = self::normalizeTime(gmdate('H:i:s'));
        $date = gmdate('Y-m-d');
        $id = self::record([
            'employee_id' => $employeeId,
            'work_date'   => $date,
            'check_in'    => $now,
            'status'      => self::deriveStatus($now, self::shiftStart('default')),
            'latitude'    => $lat,
            'longitude'   => $lng,
            'source'      => 'self_service',
        ]);
        return ['id' => $id, 'check_in' => $now, 'status' => 'present'];
    }

    public static function checkOut(int $employeeId, ?float $lat = null, ?float $lng = null): array
    {
        $now = self::normalizeTime(gmdate('H:i:s'));
        $date = gmdate('Y-m-d');
        $existing = self::findByDate($employeeId, $date);
        if (!$existing) {
            return ['error' => 'no check-in found for today'];
        }
        self::record([
            'employee_id' => $employeeId,
            'work_date'   => $date,
            'check_in'    => (string) ($existing['check_in'] ?? $now),
            'check_out'   => $now,
            'status'      => (string) ($existing['status'] ?? 'present'),
            'latitude'    => $lat,
            'longitude'   => $lng,
            'source'      => 'self_service',
        ]);
        return ['check_out' => $now, 'status' => 'present'];
    }

    /**
     * @return array{late_minutes:int, total_present_days:int, total_absent_days:int, punctuality_pct:float}
     */
    public static function teamStats(array $employeeIds, int $year, int $month): array
    {
        global $wpdb;
        if (empty($employeeIds)) {
            return ['late_minutes' => 0, 'total_present_days' => 0, 'total_absent_days' => 0, 'punctuality_pct' => 0.0];
        }
        $ids   = array_map('intval', $employeeIds);
        $place = implode(',', array_fill(0, count($ids), '%d'));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = gmdate('Y-m-t', strtotime($start));

        $sql = $wpdb->prepare(
            "SELECT
                COALESCE(SUM(late_minutes), 0) AS late_minutes,
                SUM(status='present') + SUM(status='late') + SUM(status='remote') AS present_days,
                SUM(status='absent') AS absent_days,
                SUM(status IN ('present','remote')) AS on_time
             FROM {$wpdb->prefix}ent_attendance
             WHERE employee_id IN ({$place}) AND work_date BETWEEN %s AND %s",
            array_merge($ids, [$start, $end])
        );
        $row = $wpdb->get_row($sql, ARRAY_A) ?: [];
        $present = (int) ($row['present_days'] ?? 0);
        $onTime  = (int) ($row['on_time'] ?? 0);
        $pct     = $present > 0 ? round($onTime * 100.0 / $present, 2) : 0.0;
        return [
            'late_minutes'         => (int) ($row['late_minutes'] ?? 0),
            'total_present_days'   => $present,
            'total_absent_days'    => (int) ($row['absent_days'] ?? 0),
            'punctuality_pct'      => $pct,
        ];
    }

    private static function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        return $ts === false ? null : gmdate('Y-m-d', $ts);
    }

    private static function normalizeTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        $str = (string) $value;
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str)) {
            $parts = explode(':', $str);
            return sprintf('%02d:%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
        }
        $ts = strtotime($str);
        return $ts === false ? null : gmdate('H:i:s', $ts);
    }

    private static function computeLate(?string $checkIn): int
    {
        if ($checkIn === null) {
            return 0;
        }
        $start = strtotime(self::shiftStart('default') . ':00');
        $in    = strtotime($checkIn);
        $diff  = $in - $start;
        return $diff > 0 ? (int) floor($diff / 60) : 0;
    }

    private static function computeOvertime(?string $in, ?string $out): int
    {
        if ($in === null || $out === null) {
            return 0;
        }
        $end   = strtotime(self::shiftEnd('default') . ':00');
        $outT  = strtotime($out);
        $diff  = $outT - $end;
        return $diff > 0 ? (int) floor($diff / 60) : 0;
    }

    private static function computeWorkMinutes(?string $in, ?string $out): int
    {
        if ($in === null || $out === null) {
            return 0;
        }
        $inT  = strtotime($in);
        $outT = strtotime($out);
        $diff = $outT - $inT;
        // Subtract a 30 min lunch break when shift >= 6 hours.
        $subtract = $diff >= 6 * 3600 ? 30 : 0;
        return max(0, (int) floor($diff / 60) - $subtract);
    }

    private static function detectAnomaly(?string $in, ?string $out, string $status): bool
    {
        if ($in !== null && $out !== null) {
            return strtotime($out) <= strtotime($in);
        }
        if ($status === self::STATUS_PRESENT && ($in === null || $out === null)) {
            return true;
        }
        return false;
    }

    private static function shiftStart(string $name): string
    {
        $shifts = (array) get_option('parsyar_attendance_shifts', ['default' => '08:00']);
        return (string) ($shifts[$name] ?? '08:00');
    }

    private static function shiftEnd(string $name): string
    {
        $shifts = (array) get_option('parsyar_attendance_shifts_end', ['default' => '17:00']);
        return (string) ($shifts[$name] ?? '17:00');
    }

    private static function deriveStatus(string $checkIn, string $shiftStart): string
    {
        return $checkIn > $shiftStart . ':00' ? self::STATUS_LATE : self::STATUS_PRESENT;
    }
}
