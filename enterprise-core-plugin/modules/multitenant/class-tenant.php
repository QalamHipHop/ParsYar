<?php
/**
 * Tenant — مدل دادهٔ شرکت (company) در حالت multi-tenant.
 *
 * یک tenant = یک شرکت مستقل در حالت holding/enterprise.
 * هر tenant می‌تواند چندین branch (شعبه/دپارتمان) و چندین member (کاربر) داشته باشد.
 *
 * جدول: wp_parsyar_tenants
 *
 * @package Enterprise\Modules\Multitenant
 */

declare(strict_types=1);

namespace Enterprise\Modules\Multitenant;

defined('ABSPATH') || exit;

final class Tenant
{
    public const TABLE       = 'tenants';
    public const STATUSES    = ['active', 'suspended', 'archived'];
    public const PLAN_TIERS  = ['micro', 'starter', 'business', 'enterprise', 'holding'];

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'parsyar_' . self::TABLE;
    }

    /**
     * ساخت tenant جدید.
     *
     * @param array<string,mixed> $data
     * @return int ID
     */
    public static function create(array $data): int
    {
        global $wpdb;
        $defaults = [
            'uuid'         => self::uuid(),
            'name'         => '',
            'slug'         => '',
            'legal_name'   => '',
            'national_id'  => '',
            'economic_code'=> '',
            'logo_url'     => '',
            'status'       => 'active',
            'plan'         => 'starter',
            'settings'     => '{}',
            'branding'     => '{}',
            'created_by'   => get_current_user_id() ?: null,
            'created_at'   => current_time('mysql', true),
            'updated_at'   => current_time('mysql', true),
        ];
        $row = array_merge($defaults, array_intersect_key($data, $defaults));
        if ($row['name'] === '') {
            throw new \InvalidArgumentException('نام شرکت الزامی است.');
        }
        if ($row['slug'] === '') {
            $row['slug'] = self::uniqueSlug(self::slugify((string) $row['name']));
        } else {
            $row['slug'] = self::uniqueSlug(self::slugify((string) $row['slug']));
        }
        $row['settings'] = self::encodeJson($data['settings'] ?? []);
        $row['branding'] = self::encodeJson($data['branding'] ?? []);

        $wpdb->insert(self::table(), $row);
        if ($wpdb->last_error) {
            throw new \RuntimeException('DB insert failed: ' . $wpdb->last_error);
        }
        return (int) $wpdb->insert_id;
    }

    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        $allowed = ['name', 'legal_name', 'national_id', 'economic_code', 'logo_url', 'status', 'plan', 'settings', 'branding'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) {
            return false;
        }
        if (isset($update['settings']) && is_array($update['settings'])) {
            $update['settings'] = self::encodeJson($update['settings']);
        }
        if (isset($update['branding']) && is_array($update['branding'])) {
            $update['branding'] = self::encodeJson($update['branding']);
        }
        $update['updated_at'] = current_time('mysql', true);
        $r = $wpdb->update(self::table(), $update, ['id' => $id]);
        return $r !== false;
    }

    public static function delete(int $id, bool $soft = true): bool
    {
        global $wpdb;
        if ($soft) {
            return self::update($id, ['status' => 'archived']);
        }
        $r = $wpdb->delete(self::table(), ['id' => $id]);
        return $r !== false && $r > 0;
    }

    public static function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    public static function findByUuid(string $uuid): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE uuid = %s LIMIT 1",
            $uuid
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    public static function findBySlug(string $slug): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE slug = %s LIMIT 1",
            $slug
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public static function list(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'id DESC'): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['plan'])) {
            $where[] = 'plan = %s';
            $params[] = (string) $filters['plan'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE %s OR legal_name LIKE %s OR national_id LIKE %s)';
            $s = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }
        $whereSql = implode(' AND ', $where);
        $params[] = max(1, min(200, $limit));
        $params[] = max(0, $offset);
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE {$whereSql} ORDER BY {$order} LIMIT %d OFFSET %d",
            $params
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function count(array $filters = []): int
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = (string) $filters['status'];
        }
        $whereSql = implode(' AND ', $where);
        $sql = empty($params) ? "SELECT COUNT(*) FROM " . self::table() . " WHERE {$whereSql}" : $wpdb->prepare("SELECT COUNT(*) FROM " . self::table() . " WHERE {$whereSql}", $params);
        return (int) ($wpdb->get_var($sql) ?: 0);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function hydrate(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'uuid'          => (string) $row['uuid'],
            'name'          => (string) $row['name'],
            'slug'          => (string) $row['slug'],
            'legal_name'    => (string) ($row['legal_name'] ?? ''),
            'national_id'   => (string) ($row['national_id'] ?? ''),
            'economic_code' => (string) ($row['economic_code'] ?? ''),
            'logo_url'      => (string) ($row['logo_url'] ?? ''),
            'status'        => (string) $row['status'],
            'plan'          => (string) $row['plan'],
            'settings'      => self::decodeJson($row['settings'] ?? null),
            'branding'      => self::decodeJson($row['branding'] ?? null),
            'created_by'    => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'created_at'    => (string) ($row['created_at'] ?? ''),
            'updated_at'    => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public static function uuid(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9_\-\s]+/u', '', $s) ?? '';
        $s = preg_replace('/[\s_]+/', '-', $s) ?? '';
        return trim($s, '-');
    }

    private static function uniqueSlug(string $base): string
    {
        global $wpdb;
        $slug = $base !== '' ? $base : 'tenant';
        $i = 0;
        while (true) {
            $candidate = $i === 0 ? $slug : $slug . '-' . $i;
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::table() . " WHERE slug = %s",
                $candidate
            ));
            if ($exists === 0) {
                return $candidate;
            }
            $i++;
            if ($i > 50) {
                return $slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            }
        }
    }

    /**
     * @param mixed $v
     */
    private static function encodeJson($v): string
    {
        if (is_string($v)) {
            return $v;
        }
        return wp_json_encode($v !== null ? $v : new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param mixed $v
     * @return array<string,mixed>
     */
    private static function decodeJson($v): array
    {
        if (is_array($v)) {
            return $v;
        }
        if (!is_string($v) || $v === '') {
            return [];
        }
        $arr = json_decode($v, true);
        return is_array($arr) ? $arr : [];
    }
}
