<?php
/**
 * Calendar Engine — موتور تقویم با پل Jalali و رویدادهای تکرارشونده
 *
 * @package Enterprise\Modules\Calendar
 */

declare(strict_types=1);

namespace Enterprise\Modules\Calendar;

defined('ABSPATH') || exit;

use Enterprise\Jalali;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class CalendarEngine
{
    public const TABLE = 'calendar_events';

    public const RRULE_DAILY   = 'DAILY';
    public const RRULE_WEEKLY  = 'WEEKLY';
    public const RRULE_MONTHLY = 'MONTHLY';
    public const RRULE_YEARLY  = 'YEARLY';

    public static function create(array $data): int
    {
        $data = self::normalizeInput($data);
        $data['uuid']         = self::uuid();
        $data['created_by']   = $data['created_by'] ?? get_current_user_id() ?: null;
        $data['created_at']   = current_time('mysql', true);
        $data['updated_at']   = current_time('mysql', true);
        $data['start_at']     = self::toGmt($data['start_at']);
        $data['end_at']       = self::toGmt($data['end_at'] ?? null);
        $data['all_day']      = !empty($data['all_day']) ? 1 : 0;
        $data['is_recurring'] = !empty($data['rrule']) ? 1 : 0;
        $data['status']       = $data['status'] ?? 'confirmed';

        $id = Db::insert(self::TABLE, $data);
        Logger::log('calendar_event', $id, 'create', $data);
        do_action('enterprise_event', 'calendar.event_created', ['event_id' => $id, 'data' => $data]);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) return false;
        $data = self::normalizeInput($data);
        $data['updated_at'] = current_time('mysql', true);
        if (!empty($data['start_at'])) $data['start_at'] = self::toGmt($data['start_at']);
        if (!empty($data['end_at']))   $data['end_at']   = self::toGmt($data['end_at']);
        if (!empty($data['all_day']))  $data['all_day']  = 1;
        Db::update(self::TABLE, $data, ['id' => $id]);
        Logger::log('calendar_event', $id, 'update', ['before' => $existing, 'after' => $data]);
        return true;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow(self::TABLE, ['id' => $id]);
        if ($row) {
            $row['attendees'] = self::decodeJson($row['attendees'] ?? null);
            $row['meta']      = self::decodeJson($row['meta'] ?? null);
        }
        return $row;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $row = Db::getRow(self::TABLE, ['uuid' => $uuid]);
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function delete(int $id): bool
    {
        return Db::delete(self::TABLE, ['id' => $id]) > 0;
    }

    /**
     * رویدادهای یک بازه.
     */
    public static function eventsInRange(string $from, string $to, int $userId = 0, int $limit = 500): array
    {
        $where = ['1=1'];
        $params = [];

        $fromG = self::toGmt($from);
        $toG   = self::toGmt($to);
        $where[] = '(start_at BETWEEN %s AND %s OR (is_recurring = 1 AND start_at <= %s))';
        $params = array_merge($params, [$fromG, $toG, $toG]);

        if ($userId) {
            $where[] = '(owner_id = %d OR attendees LIKE %s)';
            $params[] = $userId;
            $params[] = '%"' . $userId . '"%';
        }
        $where[] = 'deleted_at IS NULL';

        $sql = 'SELECT * FROM ' . Db::table(self::TABLE)
             . ' WHERE ' . implode(' AND ', $where)
             . ' ORDER BY start_at ASC LIMIT %d';
        $params[] = $limit;

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        // Expand recurring events
        $expanded = [];
        foreach ($rows as $r) {
            if ((int) $r['is_recurring'] === 1) {
                $occurrences = self::expandRecurring($r, $fromG, $toG);
                $expanded = array_merge($expanded, $occurrences);
            } else {
                $expanded[] = $r;
            }
        }
        usort($expanded, static fn ($a, $b) => strcmp((string) $a['start_at'], (string) $b['start_at']));
        return $expanded;
    }

    /**
     * رویدادهای یک روز خاص (Jalali یا میلادی).
     */
    public static function eventsOnDay(string $date, bool $isJalali = false, int $userId = 0): array
    {
        if ($isJalali) {
            $gregorian = Jalali::toGregorian(
                (int) substr($date, 0, 4),
                (int) substr($date, 5, 2),
                (int) substr($date, 8, 2)
            );
        } else {
            $gregorian = $date;
        }
        return self::eventsInRange($gregorian . ' 00:00:00', $gregorian . ' 23:59:59', $userId);
    }

    /**
     * رویدادهای یک ماه میلادی (calendar grid).
     */
    public static function monthGrid(int $year, int $month, int $userId = 0): array
    {
        $firstDay = sprintf('%04d-%02d-01', $year, $month);
        $lastDay  = gmdate('Y-m-t', strtotime($firstDay));
        $events   = self::eventsInRange($firstDay . ' 00:00:00', $lastDay . ' 23:59:59', $userId);

        $byDay = [];
        foreach ($events as $e) {
            $day = substr((string) $e['start_at'], 0, 10);
            $byDay[$day][] = $e;
        }

        // ساخت grid با روزهای خالی
        $grid = [];
        $startTs = strtotime($firstDay);
        $endTs   = strtotime($lastDay);
        for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
            $day = gmdate('Y-m-d', $ts);
            $grid[] = [
                'date'    => $day,
                'jalali'  => self::formatJalali($day, 'Y/m/d'),
                'events'  => $byDay[$day] ?? [],
                'is_today'=> $day === gmdate('Y-m-d'),
            ];
        }
        return $grid;
    }

    /**
     * ساخت event سریع.
     */
    public static function quickEvent(string $title, string $startAt, int $durationMin = 60, array $extra = []): int
    {
        $endAt = gmdate('Y-m-d H:i:s', strtotime($startAt) + $durationMin * 60);
        return self::create(array_merge([
            'title'    => $title,
            'start_at' => $startAt,
            'end_at'   => $endAt,
        ], $extra));
    }

    /**
     * محاسبهٔ تکرار رویداد.
     */
    private static function expandRecurring(array $event, string $from, string $to): array
    {
        $rrule = $event['rrule'] ?? '';
        if (empty($rrule)) {
            return [$event];
        }
        $parts = [];
        foreach (explode(';', $rrule) as $p) {
            if (str_contains($p, '=')) {
                [$k, $v] = explode('=', $p, 2);
                $parts[strtoupper($k)] = $v;
            }
        }
        $freq  = strtoupper($parts['FREQ'] ?? 'DAILY');
        $count = (int) ($parts['COUNT'] ?? 30);
        $until = $parts['UNTIL'] ?? null;
        $interval = (int) ($parts['INTERVAL'] ?? 1);

        $startTs = strtotime((string) $event['start_at']);
        $endTs   = $event['end_at'] ? strtotime((string) $event['end_at']) : $startTs + 3600;
        $duration = $endTs - $startTs;

        $fromTs = strtotime($from);
        $toTs   = strtotime($to);

        $occurrences = [];
        $current = $startTs;
        $i = 0;
        while ($i < $count) {
            if ($current > $toTs) break;
            if ($current >= $fromTs && $current <= $toTs) {
                $occ = $event;
                $occ['start_at'] = gmdate('Y-m-d H:i:s', $current);
                $occ['end_at']   = gmdate('Y-m-d H:i:s', $current + $duration);
                $occ['is_occurrence'] = 1;
                $occ['recurring_event_id'] = (int) $event['id'];
                $occurrences[] = $occ;
            }
            $i++;
            $current = match ($freq) {
                self::RRULE_DAILY   => strtotime("+{$interval} day", $current),
                self::RRULE_WEEKLY  => strtotime("+{$interval} week", $current),
                self::RRULE_MONTHLY => strtotime("+{$interval} month", $current),
                self::RRULE_YEARLY  => strtotime("+{$interval} year", $current),
                default             => $current + 86400,
            };
            if ($until && $current > strtotime($until)) break;
        }
        return $occurrences;
    }

    public static function formatJalali(string $mysqlDate, string $format = 'Y/m/d'): string
    {
        if (!$mysqlDate) return '';
        try {
            $p = Jalali::fromGregorian(substr($mysqlDate, 0, 10));
            return Jalali::format($p['y'], $p['m'], $p['d'], $format);
        } catch (\Throwable $e) {
            return $mysqlDate;
        }
    }

    /**
     * اوقات شرعی تهران (نمونه ساده) — در پروداکشن با API جایگزین شود.
     */
    public static function prayerTimes(string $date, float $lat = 35.6892, float $lng = 51.3890): array
    {
        // الگوریتم ساده — sunset ≈ 18:30 تابستان، 17:00 زمستان
        $month = (int) gmdate('n', strtotime($date));
        $sunset = ($month >= 4 && $month <= 9) ? '19:30' : '17:30';
        return [
            'date'      => $date,
            'fajr'      => '05:00',
            'sunrise'   => '06:30',
            'dhuhr'     => '13:00',
            'asr'       => '16:30',
            'maghrib'   => $sunset,
            'isha'      => gmdate('H:i', strtotime($sunset) + 90 * 60),
        ];
    }

    // ----------------- Internal -----------------

    private static function normalizeInput(array $data): array
    {
        $out = [];
        $map = [
            'title'       => 'sanitize_text_field',
            'description' => 'sanitize_textarea_field',
            'location'    => 'sanitize_text_field',
            'type'        => 'sanitize_text_field',
            'color'       => 'sanitize_text_field',
            'status'      => 'sanitize_text_field',
            'rrule'       => 'sanitize_text_field',
            'timezone'    => 'sanitize_text_field',
        ];
        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $out[$k] = wp_json_encode($v);
            } elseif (isset($map[$k]) && is_string($v)) {
                $out[$k] = call_user_func($map[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function decodeJson(?string $json): mixed
    {
        if (!$json) return null;
        return json_decode($json, true) ?: null;
    }

    private static function toGmt(?string $date): ?string
    {
        if (!$date) return null;
        $ts = is_numeric($date) ? (int) $date : strtotime($date);
        if ($ts === false) return null;
        return gmdate('Y-m-d H:i:s', $ts);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
