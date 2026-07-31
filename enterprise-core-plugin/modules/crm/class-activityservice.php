<?php
/**
 * Activity Service — tasks, calls, meetings, emails, notes
 *
 * @package Enterprise\Modules\Crm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Crm;

defined('ABSPATH') || exit;

use Enterprise\Jalali;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class ActivityService
{
    public const TABLE = 'activities';

    public const TYPES = [
        'task'     => 'وظیفه',
        'call'     => 'تماس',
        'meeting'  => 'جلسه',
        'email'    => 'ایمیل',
        'note'     => 'یادداشت',
        'sms'      => 'پیامک',
        'whatsapp' => 'واتساپ',
        'telegram' => 'تلگرام',
        'visit'    => 'بازدید',
        'demo'     => 'دمو',
        'follow_up'=> 'پیگیری',
    ];

    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled', 'overdue'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public static function create(array $data): int
    {
        $data = self::normalizeInput($data);
        $data['uuid']         = self::uuid();
        $data['status']       = $data['status'] ?? 'pending';
        $data['priority']     = $data['priority'] ?? 'normal';
        $data['owner_id']     = $data['owner_id'] ?? get_current_user_id() ?: null;
        $data['created_by']   = $data['created_by'] ?? get_current_user_id() ?: null;
        $data['created_at']   = current_time('mysql', true);
        $data['updated_at']   = current_time('mysql', true);
        $data['due_at']       = self::toGmt($data['due_at'] ?? null);
        $data['completed_at'] = null;

        $id = Db::insert(self::TABLE, $data);
        Logger::log('activity', $id, 'create', $data);
        do_action('enterprise_event', 'activity.created', ['activity_id' => $id, 'type' => $data['type'], 'data' => $data]);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) return false;
        $data = self::normalizeInput($data);
        $data['updated_at'] = current_time('mysql', true);
        if (!empty($data['due_at'])) {
            $data['due_at'] = self::toGmt($data['due_at']);
        }
        if (isset($data['status']) && $data['status'] === 'completed' && ($existing['status'] !== 'completed')) {
            $data['completed_at'] = current_time('mysql', true);
        }
        Db::update(self::TABLE, $data, ['id' => $id]);
        Logger::log('activity', $id, 'update', ['before' => $existing, 'after' => $data]);
        do_action('enterprise_event', 'activity.updated', ['activity_id' => $id, 'data' => $data]);
        return true;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow(self::TABLE, ['id' => $id]);
        if ($row) {
            $row['meta'] = self::decodeJson($row['meta'] ?? null);
        }
        return $row;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $row = Db::getRow(self::TABLE, ['uuid' => $uuid]);
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function search(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'due_at ASC, id DESC'): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like($filters['q']) . '%';
            $where[]  = '(title LIKE %s OR description LIKE %s)';
            $params = array_merge($params, [$q, $q]);
        }
        if (!empty($filters['type'])) {
            $types = (array) $filters['type'];
            $placeholders = implode(',', array_fill(0, count($types), '%s'));
            $where[]  = "type IN ($placeholders)";
            $params = array_merge($params, $types);
        }
        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[]  = 'priority = %s';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]  = 'owner_id = %d';
            $params[] = (int) $filters['owner_id'];
        }
        if (!empty($filters['contact_id'])) {
            $where[]  = 'contact_id = %d';
            $params[] = (int) $filters['contact_id'];
        }
        if (!empty($filters['organization_id'])) {
            $where[]  = 'organization_id = %d';
            $params[] = (int) $filters['organization_id'];
        }
        if (!empty($filters['deal_id'])) {
            $where[]  = 'deal_id = %d';
            $params[] = (int) $filters['deal_id'];
        }
        if (!empty($filters['lead_id'])) {
            $where[]  = 'lead_id = %d';
            $params[] = (int) $filters['lead_id'];
        }
        if (!empty($filters['due_from'])) {
            $where[]  = 'due_at >= %s';
            $params[] = self::toGmt($filters['due_from']);
        }
        if (!empty($filters['due_to'])) {
            $where[]  = 'due_at <= %s';
            $params[] = self::toGmt($filters['due_to']);
        }
        if (!empty($filters['overdue'])) {
            $where[] = "(status NOT IN ('completed','cancelled') AND due_at < NOW())";
        }
        if (!empty($filters['today'])) {
            $where[] = "(status NOT IN ('completed','cancelled') AND DATE(due_at) = CURDATE())";
        }
        if (empty($filters['include_deleted'])) {
            $where[] = 'deleted_at IS NULL';
        }

        $sql = 'SELECT * FROM ' . Db::table(self::TABLE)
             . ' WHERE ' . implode(' AND ', $where)
             . ' ORDER BY ' . sanitize_sql_orderby($order)
             . ' LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    public static function count(array $filters = []): int
    {
        return count(self::search($filters, 100000, 0));
    }

    public static function complete(int $id): bool
    {
        return self::update($id, ['status' => 'completed']);
    }

    public static function cancel(int $id): bool
    {
        return self::update($id, ['status' => 'cancelled']);
    }

    public static function softDelete(int $id): bool
    {
        Db::update(self::TABLE, ['deleted_at' => current_time('mysql', true)], ['id' => $id]);
        Logger::log('activity', $id, 'soft_delete', []);
        return true;
    }

    /**
     * تسک‌های امروز یک کاربر.
     */
    public static function todaysTasks(int $userId): array
    {
        return self::search(['owner_id' => $userId, 'today' => true, 'type' => ['task', 'call', 'meeting']], 50, 0);
    }

    /**
     * تسک‌های عقب‌افتاده.
     */
    public static function overdueTasks(int $userId = 0, int $limit = 50): array
    {
        $filters = ['overdue' => true, 'type' => ['task', 'call', 'meeting']];
        if ($userId) {
            $filters['owner_id'] = $userId;
        }
        return self::search($filters, $limit, 0, 'due_at ASC');
    }

    /**
     * activity stream — ترکیب همهٔ رویدادها.
     */
    public static function stream(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return self::search($filters, $limit, $offset, 'created_at DESC');
    }

    /**
     * ثبت فعالیت سریع (مثلا بعد از تماس).
     */
    public static function logQuick(string $type, string $title, array $extra = []): int
    {
        $data = array_merge([
            'type'  => $type,
            'title' => $title,
            'status'=> 'completed',
        ], $extra);
        return self::create($data);
    }

    /**
     * گزارش بهره‌وری کاربر.
     */
    public static function productivity(int $userId, string $from, string $to): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT type, status, COUNT(*) AS cnt
             FROM " . Db::table(self::TABLE) . "
             WHERE owner_id = %d AND created_at BETWEEN %s AND %s
             GROUP BY type, status",
            [$userId, self::toGmt($from), self::toGmt($to)]
        ), ARRAY_A);
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[$r['type']][$r['status']] = (int) $r['cnt'];
        }
        return $out;
    }

    /**
     * Jalali formatting helper.
     */
    public static function formatDueAt(?string $mysqlDate, string $format = 'Y/m/d H:i'): string
    {
        if (!$mysqlDate) return '';
        try {
            $parts = Jalali::fromGregorian($mysqlDate);
            return Jalali::format($parts['y'], $parts['m'], $parts['d'], $format);
        } catch (\Throwable $e) {
            return $mysqlDate;
        }
    }

    // ----------------- Internal -----------------

    private static function normalizeInput(array $data): array
    {
        $out = [];
        $map = [
            'type'        => 'sanitize_text_field',
            'title'       => 'sanitize_text_field',
            'description' => 'sanitize_textarea_field',
            'status'      => 'sanitize_text_field',
            'priority'    => 'sanitize_text_field',
            'location'    => 'sanitize_text_field',
            'outcome'     => 'sanitize_textarea_field',
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
