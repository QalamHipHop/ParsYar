<?php
/**
 * Attendance Service — ثبت ورود و خروج روزانه
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class AttendanceService
{
    public const TABLE = 'attendance';

    public static function find(int $id): ?array
    {
        return Db::getRow(self::TABLE, ['id' => $id]);
    }

    public static function findForDay(int $employeeId, string $date): ?array
    {
        return Db::getRow(self::TABLE, ['employee_id' => $employeeId, 'work_date' => $date]);
    }

    public static function checkIn(int $employeeId, ?string $time = null, ?string $date = null): int
    {
        $date = $date ?: gmdate('Y-m-d');
        $time = $time ?: gmdate('H:i:s');
        $existing = self::findForDay($employeeId, $date);
        if ($existing) {
            Db::update(self::TABLE, ['check_in' => $time], ['id' => $existing['id']]);
            Logger::log('attendance', (int) $existing['id'], 'check_in_update', ['time' => $time]);
            return (int) $existing['id'];
        }
        $id = Db::insert(self::TABLE, [
            'employee_id'      => $employeeId,
            'work_date'        => $date,
            'check_in'         => $time,
            'check_out'        => null,
            'overtime_minutes' => 0,
        ]);
        Logger::log('attendance', $id, 'check_in', ['time' => $time]);
        return $id;
    }

    public static function checkOut(int $employeeId, ?string $time = null, ?string $date = null, int $overtimeMinutes = 0): bool
    {
        $date = $date ?: gmdate('Y-m-d');
        $time = $time ?: gmdate('H:i:s');
        $existing = self::findForDay($employeeId, $date);
        if (!$existing) {
            // ثبت اتوماتیک
            $existing = ['id' => Db::insert(self::TABLE, [
                'employee_id' => $employeeId, 'work_date' => $date, 'check_out' => null,
            ])];
        }
        $worked = null;
        if (!empty($existing['check_in'])) {
            $start = strtotime($existing['check_in']);
            $end   = strtotime($time);
            if ($start && $end && $end > $start) {
                $worked = ($end - $start) / 60 - 60; // کسر ناهار ۶۰ دقیقه
                if ($worked < 0) {
                    $worked = 0;
                }
                $overtimeMinutes = (int) max($overtimeMinutes, max(0, $worked - 8 * 60));
            }
        }
        Db::update(self::TABLE, [
            'check_out'        => $time,
            'overtime_minutes' => max(0, $overtimeMinutes),
        ], ['id' => $existing['id']]);
        Logger::log('attendance', (int) $existing['id'], 'check_out', ['time' => $time, 'overtime' => $overtimeMinutes]);
        return true;
    }

    public static function listForEmployee(int $employeeId, int $limit = 30): array
    {
        return Db::getResults(self::TABLE, ['employee_id' => $employeeId], 'work_date DESC', max(1, min(365, $limit)), 0);
    }

    public static function listForRange(string $dateFrom, string $dateTo, ?int $employeeId = null): array
    {
        global $wpdb;
        $t = Db::table(self::TABLE);
        if ($employeeId) {
            $sql = $wpdb->prepare(
                "SELECT a.*, e.full_name, e.national_code
                 FROM {$t} a
                 LEFT JOIN " . Db::table('employees') . " e ON e.id = a.employee_id
                 WHERE a.work_date BETWEEN %s AND %s AND a.employee_id = %d
                 ORDER BY a.work_date DESC, a.employee_id ASC",
                $dateFrom, $dateTo, $employeeId
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT a.*, e.full_name, e.national_code
                 FROM {$t} a
                 LEFT JOIN " . Db::table('employees') . " e ON e.id = a.employee_id
                 WHERE a.work_date BETWEEN %s AND %s
                 ORDER BY a.work_date DESC, a.employee_id ASC",
                $dateFrom, $dateTo
            );
        }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function summary(int $employeeId, string $period): array
    {
        // period: 1404-05
        [$year, $month] = array_pad(explode('-', $period), 2, null);
        if (!$year || !$month) {
            return ['days_worked' => 0, 'total_overtime' => 0];
        }
        $start = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $end   = gmdate('Y-m-t', strtotime($start));
        $rows  = self::listForRange($start, $end, $employeeId);
        $overtime = 0;
        $days = 0;
        foreach ($rows as $r) {
            if (!empty($r['check_in']) && !empty($r['check_out'])) {
                $days++;
            }
            $overtime += (int) ($r['overtime_minutes'] ?? 0);
        }
        return [
            'period'         => $period,
            'employee_id'    => $employeeId,
            'days_worked'    => $days,
            'total_overtime' => $overtime,
        ];
    }
}
