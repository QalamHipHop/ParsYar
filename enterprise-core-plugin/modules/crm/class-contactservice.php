<?php
/**
 * Contact Service — مدیریت مخاطبین با dedup، scoring، timeline
 *
 * @package Enterprise\Modules\Crm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Crm;

defined('ABSPATH') || exit;

use Enterprise\Relations;
use Enterprise\Str;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class ContactService
{
    public const TABLE = 'contacts';
    public const LIFECYCLE = ['lead', 'mql', 'sql', 'opportunity', 'customer', 'evangelist', 'churned'];

    /**
     * ایجاد مخاطب با dedup خودکار.
     */
    public static function create(array $data): int
    {
        $data = self::normalizeInput($data);

        // تلاش برای یافتن مخاطب موجود
        $existing = self::findDuplicate($data);
        if ($existing) {
            // ادغام داده‌های جدید با قدیم
            self::mergeInto((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        $data['uuid']          = self::uuid();
        $data['score']         = self::score($data);
        $data['lifecycle_stage'] = $data['lifecycle_stage'] ?? 'lead';
        $data['created_at']    = current_time('mysql', true);
        $data['updated_at']    = current_time('mysql', true);
        $data['owner_id']      = $data['owner_id'] ?? get_current_user_id() ?: null;

        $id = Db::insert(self::TABLE, $data);
        Logger::log('contact', $id, 'create', $data);
        do_action('enterprise_event', 'contact.created', ['contact_id' => $id, 'data' => $data]);
        return $id;
    }

    /**
     * به‌روزرسانی.
     */
    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        $data = self::normalizeInput($data);
        $data['updated_at'] = current_time('mysql', true);
        $data['score']      = self::score(array_merge($existing, $data));
        Db::update(self::TABLE, $data, ['id' => $id]);
        Logger::log('contact', $id, 'update', ['before' => $existing, 'after' => $data]);
        do_action('enterprise_event', 'contact.updated', ['contact_id' => $id, 'data' => $data]);
        return true;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow(self::TABLE, ['id' => $id]);
        if ($row) {
            $row['tags']         = self::decodeJson($row['tags']         ?? null);
            $row['custom_fields'] = self::decodeJson($row['custom_fields'] ?? null);
            $row['consent']      = self::decodeJson($row['consent']      ?? null);
        }
        return $row;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $row = Db::getRow(self::TABLE, ['uuid' => $uuid]);
        return $row ? self::find((int) $row['id']) : null;
    }

    /**
     * جستجوی پیشرفته با فیلتر.
     */
    public static function search(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'score DESC, id DESC'): array
    {
        global $wpdb;
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like($filters['q']) . '%';
            $where[]  = '(full_name LIKE %s OR primary_email LIKE %s OR primary_mobile LIKE %s OR national_id LIKE %s)';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }
        if (!empty($filters['lifecycle_stage'])) {
            $where[]  = 'lifecycle_stage = %s';
            $params[] = $filters['lifecycle_stage'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]  = 'owner_id = %d';
            $params[] = (int) $filters['owner_id'];
        }
        if (!empty($filters['organization_id'])) {
            $where[]  = 'organization_id = %d';
            $params[] = (int) $filters['organization_id'];
        }
        if (!empty($filters['min_score'])) {
            $where[]  = 'score >= %d';
            $params[] = (int) $filters['min_score'];
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

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(static function ($r) {
            $r['tags']         = self::decodeJson($r['tags'] ?? null);
            $r['custom_fields'] = self::decodeJson($r['custom_fields'] ?? null);
            return $r;
        }, $rows ?: []);
    }

    /**
     * شمارش با فیلتر.
     */
    public static function count(array $filters = []): int
    {
        return count(self::search($filters, 100000, 0));
    }

    /**
     * Timeline مخاطب: تمام رویدادهای مرتبط.
     */
    public static function timeline(int $contactId, int $limit = 100): array
    {
        $rows = Db::getResults('activity_log', ['contact_id' => $contactId], 'occurred_at DESC', $limit, 0);
        return $rows;
    }

    /**
     * اتصال مخاطب به سازمان.
     */
    public static function attachToOrganization(int $contactId, int $orgId, string $role = 'primary', array $meta = []): int
    {
        return Relations::link('contact', $contactId, 'organization', $orgId, 'contact_to_org', array_merge(['role' => $role], $meta));
    }

    /**
     * حذف نرم (soft delete).
     */
    public static function softDelete(int $id): bool
    {
        Db::update(self::TABLE, ['deleted_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true)], ['id' => $id]);
        Logger::log('contact', $id, 'soft_delete', []);
        do_action('enterprise_event', 'contact.deleted', ['contact_id' => $id]);
        return true;
    }

    /**
     * بازیابی.
     */
    public static function restore(int $id): bool
    {
        Db::update(self::TABLE, ['deleted_at' => null, 'updated_at' => current_time('mysql', true)], ['id' => $id]);
        Logger::log('contact', $id, 'restore', []);
        return true;
    }

    /**
     * حذف سخت.
     */
    public static function hardDelete(int $id): bool
    {
        Relations::purge('contact', $id);
        $r = Db::delete(self::TABLE, ['id' => $id]);
        Logger::log('contact', $id, 'hard_delete', []);
        return $r > 0;
    }

    /**
     * الگوریتم امتیازدهی (0-100) — وزنی.
     */
    public static function score(array $data): int
    {
        $score = 0;
        // اطلاعات دموگرافیک
        if (!empty($data['primary_email']))   $score += 10;
        if (!empty($data['primary_mobile']))  $score += 10;
        if (!empty($data['primary_phone']))   $score += 5;
        if (!empty($data['national_id']))     $score += 15;
        if (!empty($data['job_title']))       $score += 5;
        if (!empty($data['organization_id'])) $score += 10;

        // تکمیل‌بودن
        $completion = self::profileCompletion($data);
        $score += (int) round($completion / 2);

        // lifecycle
        $score += match ($data['lifecycle_stage'] ?? 'lead') {
            'lead'        => 0,
            'mql'         => 10,
            'sql'         => 20,
            'opportunity' => 25,
            'customer'    => 30,
            'evangelist'  => 35,
            'churned'     => -10,
            default       => 0,
        };

        return max(0, min(100, $score));
    }

    /**
     * درصد تکمیل‌بودن پروفایل (0-100).
     */
    public static function profileCompletion(array $data): int
    {
        $fields = ['first_name', 'last_name', 'primary_email', 'primary_mobile', 'national_id', 'job_title', 'organization_id', 'address_line1', 'city', 'province', 'birthday_jalali'];
        $filled = 0;
        foreach ($fields as $f) {
            if (!empty($data[$f])) {
                $filled++;
            }
        }
        return (int) round(($filled / count($fields)) * 100);
    }

    /**
     * تشخیص مخاطب تکراری با الگوریتم چندلایه.
     */
    public static function findDuplicate(array $data): ?array
    {
        // ۱. کد ملی (قطعی)
        if (!empty($data['national_id'])) {
            $row = Db::getRow(self::TABLE, ['national_id' => $data['national_id']]);
            if ($row) {
                return $row;
            }
        }
        // ۲. ایمیل (قطعی)
        if (!empty($data['primary_email'])) {
            $row = Db::getRow(self::TABLE, ['primary_email' => $data['primary_email']]);
            if ($row) {
                return $row;
            }
        }
        // ۳. موبایل (قطعی)
        if (!empty($data['primary_mobile'])) {
            $mobile = $data['primary_mobile'];
            $row = Db::getRow(self::TABLE, ['primary_mobile' => $mobile]);
            if ($row) {
                return $row;
            }
        }
        // ۴. شباهت نام + سازمان (فازی)
        if (!empty($data['full_name']) && !empty($data['organization_id'])) {
            $candidates = Db::getResults(self::TABLE, ['organization_id' => $data['organization_id']], 'id DESC', 200, 0);
            foreach ($candidates as $c) {
                if (Str::isSameName((string) $c['full_name'], (string) $data['full_name'], 0.86)) {
                    return $c;
                }
            }
        }
        // ۵. شباهت نام + تلفن (آخرین رقم)
        if (!empty($data['full_name']) && !empty($data['primary_phone'])) {
            $phoneTail = substr((string) $data['primary_phone'], -7);
            $candidates = Db::getResults(self::TABLE, [], 'id DESC', 500, 0);
            foreach ($candidates as $c) {
                $cTail = substr((string) ($c['primary_phone'] ?? ''), -7);
                if ($cTail === $phoneTail && Str::isSameName((string) $c['full_name'], (string) $data['full_name'], 0.88)) {
                    return $c;
                }
            }
        }
        return null;
    }

    /**
     * ادغام دو رکورد — اولی نگه داشته می‌شود، دومی به عنوان duplicate لاگ می‌شود.
     */
    public static function mergeInto(int $primaryId, array $newData): bool
    {
        $primary = self::find($primaryId);
        if (!$primary) {
            return false;
        }
        $merged = array_merge($newData, $primary);
        // فیلدهای خالی primary با newData پر می‌شود، فیلدهای پر primary حفظ می‌شود
        foreach ($newData as $k => $v) {
            if (empty($primary[$k]) && !empty($v)) {
                $merged[$k] = $v;
            }
        }
        unset($merged['id'], $merged['uuid'], $merged['created_at']);
        $merged['updated_at'] = current_time('mysql', true);
        $merged['score']      = self::score($merged);
        Db::update(self::TABLE, $merged, ['id' => $primaryId]);
        Logger::log('contact', $primaryId, 'merge', ['new' => $newData]);
        do_action('enterprise_event', 'contact.merged', ['primary_id' => $primaryId, 'incoming' => $newData]);
        return true;
    }

    /**
     * جستجوی دایره‌ای (Circular) با phone-book-like behavior.
     */
    public static function quickSearch(string $q, int $limit = 10): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        return self::search(['q' => $q, 'include_deleted' => false], $limit, 0, 'score DESC, id DESC');
    }

    /**
     * آمار lifecycle — برای dashboard.
     */
    public static function lifecycleBreakdown(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT lifecycle_stage, COUNT(*) AS cnt, AVG(score) AS avg_score
             FROM " . Db::table(self::TABLE) . " WHERE deleted_at IS NULL
             GROUP BY lifecycle_stage",
            ARRAY_A
        );
        $out = [];
        foreach (self::LIFECYCLE as $l) {
            $out[$l] = ['count' => 0, 'avg_score' => 0];
        }
        foreach ($rows ?: [] as $r) {
            $out[$r['lifecycle_stage']] = [
                'count'     => (int) $r['cnt'],
                'avg_score' => (float) $r['avg_score'],
            ];
        }
        return $out;
    }

    // ----------------- Internal -----------------

    private static function normalizeInput(array $data): array
    {
        $out = [];
        $map = [
            'first_name'      => 'sanitize_text_field',
            'last_name'       => 'sanitize_text_field',
            'full_name'       => 'sanitize_text_field',
            'national_id'     => 'sanitize_text_field',
            'gender'          => 'sanitize_text_field',
            'job_title'       => 'sanitize_text_field',
            'primary_email'   => 'sanitize_email',
            'primary_phone'   => 'sanitize_text_field',
            'primary_mobile'  => 'sanitize_text_field',
            'language'        => 'sanitize_text_field',
            'city'            => 'sanitize_text_field',
            'province'        => 'sanitize_text_field',
            'address_line1'   => 'sanitize_text_field',
            'address_line2'   => 'sanitize_text_field',
            'postal_code'     => 'sanitize_text_field',
            'birthday_jalali' => 'sanitize_text_field',
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
        if (!empty($out['first_name']) && !empty($out['last_name']) && empty($out['full_name'])) {
            $out['full_name'] = trim($out['first_name'] . ' ' . $out['last_name']);
        } elseif (!empty($out['full_name']) && (empty($out['first_name']) || empty($out['last_name']))) {
            $parts = preg_split('/\s+/u', trim((string) $out['full_name']));
            $out['first_name'] = $out['first_name'] ?: ($parts[0] ?? '');
            $out['last_name']  = $out['last_name']  ?: (end($parts) ?: '');
        }
        if (!empty($out['primary_email'])) {
            $out['primary_email'] = strtolower((string) $out['primary_email']);
        }
        return $out;
    }

    private static function decodeJson(?string $json): mixed
    {
        if (!$json) {
            return null;
        }
        $d = json_decode($json, true);
        return $d ?? null;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
