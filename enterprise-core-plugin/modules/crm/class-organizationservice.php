<?php
/**
 * Organization Service — مدیریت سازمان‌ها/شرکت‌ها
 *
 * @package Enterprise\Modules\Crm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Crm;

defined('ABSPATH') || exit;

use Enterprise\Relations;
use Enterprise\Data;
use Enterprise\Str;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class OrganizationService
{
    public const TABLE = 'organizations';
    public const SIZES = ['micro', 'small', 'medium', 'large', 'enterprise'];

    public static function create(array $data): int
    {
        $data = self::normalizeInput($data);
        $existing = self::findDuplicate($data);
        if ($existing) {
            return (int) $existing['id'];
        }
        $data['uuid']         = self::uuid();
        $data['score']        = self::score($data);
        $data['created_at']   = current_time('mysql', true);
        $data['updated_at']   = current_time('mysql', true);
        $data['owner_id']     = $data['owner_id'] ?? get_current_user_id() ?: null;

        $id = Db::insert(self::TABLE, $data);
        Logger::log('organization', $id, 'create', $data);
        do_action('enterprise_event', 'organization.created', ['organization_id' => $id, 'data' => $data]);
        return $id;
    }

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
        Logger::log('organization', $id, 'update', ['before' => $existing, 'after' => $data]);
        do_action('enterprise_event', 'organization.updated', ['organization_id' => $id, 'data' => $data]);
        return true;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow(self::TABLE, ['id' => $id]);
        if ($row) {
            $row['tags']         = self::decodeJson($row['tags'] ?? null);
            $row['custom_fields'] = self::decodeJson($row['custom_fields'] ?? null);
        }
        return $row;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $row = Db::getRow(self::TABLE, ['uuid' => $uuid]);
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function search(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'score DESC, id DESC'): array
    {
        global $wpdb;
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like($filters['q']) . '%';
            $where[]  = '(name LIKE %s OR legal_name LIKE %s OR national_id LIKE %s OR email LIKE %s)';
            $params = array_merge($params, [$q, $q, $q, $q]);
        }
        if (!empty($filters['industry'])) {
            $where[]  = 'industry = %s';
            $params[] = $filters['industry'];
        }
        if (!empty($filters['size'])) {
            $where[]  = 'size = %s';
            $params[] = $filters['size'];
        }
        if (!empty($filters['city'])) {
            $where[]  = 'city = %s';
            $params[] = $filters['city'];
        }
        if (!empty($filters['province'])) {
            $where[]  = 'province = %s';
            $params[] = $filters['province'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]  = 'owner_id = %d';
            $params[] = (int) $filters['owner_id'];
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

    public static function softDelete(int $id): bool
    {
        Db::update(self::TABLE, ['deleted_at' => current_time('mysql', true)], ['id' => $id]);
        Logger::log('organization', $id, 'soft_delete', []);
        return true;
    }

    public static function hardDelete(int $id): bool
    {
        Relations::purge('organization', $id);
        return Db::delete(self::TABLE, ['id' => $id]) > 0;
    }

    /**
     * امتیازدهی سازمان (0-100).
     */
    public static function score(array $data): int
    {
        $score = 0;
        if (!empty($data['legal_name']))    $score += 10;
        if (!empty($data['national_id']))   $score += 20;
        if (!empty($data['economic_code'])) $score += 15;
        if (!empty($data['website']))       $score += 5;
        if (!empty($data['email']))         $score += 5;
        if (!empty($data['phone']))         $score += 5;
        if (!empty($data['industry']))      $score += 5;
        $size = $data['size'] ?? 'micro';
        $score += match ($size) {
            'enterprise' => 25,
            'large'      => 20,
            'medium'     => 15,
            'small'      => 10,
            'micro'      => 5,
            default      => 0,
        };
        return max(0, min(100, $score));
    }

    public static function findDuplicate(array $data): ?array
    {
        if (!empty($data['national_id'])) {
            $row = Db::getRow(self::TABLE, ['national_id' => $data['national_id']]);
            if ($row) return $row;
        }
        if (!empty($data['economic_code'])) {
            $row = Db::getRow(self::TABLE, ['economic_code' => $data['economic_code']]);
            if ($row) return $row;
        }
        if (!empty($data['name'])) {
            $candidates = Db::getResults(self::TABLE, [], 'id DESC', 200, 0);
            foreach ($candidates as $c) {
                if (Str::isSameName((string) $c['name'], (string) $data['name'], 0.92)) {
                    return $c;
                }
            }
        }
        return null;
    }

    public static function industryBreakdown(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT industry, COUNT(*) AS cnt
             FROM " . Db::table(self::TABLE) . " WHERE deleted_at IS NULL AND industry IS NOT NULL
             GROUP BY industry ORDER BY cnt DESC",
            ARRAY_A
        );
        $out = [];
        foreach ($rows ?: [] as $r) {
            $info = Data::industry($r['industry']);
            $out[$r['industry']] = [
                'count'     => (int) $r['cnt'],
                'name_fa'   => $info['name_fa'] ?? $r['industry'],
                'name_en'   => $info['name_en'] ?? $r['industry'],
            ];
        }
        return $out;
    }

    public static function geoBreakdown(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT province, COUNT(*) AS cnt
             FROM " . Db::table(self::TABLE) . " WHERE deleted_at IS NULL AND province IS NOT NULL
             GROUP BY province ORDER BY cnt DESC",
            ARRAY_A
        );
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[$r['province']] = [
                'count'   => (int) $r['cnt'],
                'name_fa' => Data::provinceName($r['province'], 'fa'),
            ];
        }
        return $out;
    }

    // ----------------- Internal -----------------

    private static function normalizeInput(array $data): array
    {
        $out = [];
        $map = [
            'name'           => 'sanitize_text_field',
            'legal_name'     => 'sanitize_text_field',
            'national_id'    => 'sanitize_text_field',
            'economic_code'  => 'sanitize_text_field',
            'registration_no'=> 'sanitize_text_field',
            'vat_number'     => 'sanitize_text_field',
            'industry'       => 'sanitize_text_field',
            'size'           => 'sanitize_text_field',
            'website'        => 'esc_url_raw',
            'email'          => 'sanitize_email',
            'phone'          => 'sanitize_text_field',
            'mobile'         => 'sanitize_text_field',
            'address_line1'  => 'sanitize_text_field',
            'address_line2'  => 'sanitize_text_field',
            'city'           => 'sanitize_text_field',
            'province'       => 'sanitize_text_field',
            'postal_code'    => 'sanitize_text_field',
            'timezone'       => 'sanitize_text_field',
            'currency'       => 'sanitize_text_field',
            'language'       => 'sanitize_text_field',
            'acquisition_source' => 'sanitize_text_field',
            'acquisition_campaign'=> 'sanitize_text_field',
            'lifecycle_stage' => 'sanitize_text_field',
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
        if (!empty($out['website']) && !preg_match('#^https?://#i', (string) $out['website'])) {
            $out['website'] = 'https://' . ltrim((string) $out['website'], '/');
        }
        if (!empty($out['email'])) {
            $out['email'] = strtolower((string) $out['email']);
        }
        return $out;
    }

    private static function decodeJson(?string $json): mixed
    {
        if (!$json) return null;
        return json_decode($json, true) ?: null;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
