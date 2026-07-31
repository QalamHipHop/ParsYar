<?php
/**
 * Branch — شعبه / دپارتمان درون یک tenant.
 *
 * جدول: wp_parsyar_branches
 *
 * @package Enterprise\Modules\Multitenant
 */

declare(strict_types=1);

namespace Enterprise\Modules\Multitenant;

defined('ABSPATH') || exit;

final class Branch
{
    public const TABLE = 'branches';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'parsyar_' . self::TABLE;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        global $wpdb;
        if (empty($data['tenant_id'])) {
            throw new \InvalidArgumentException('tenant_id الزامی است.');
        }
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('نام شعبه الزامی است.');
        }
        $row = [
            'uuid'         => Tenant::uuid(),
            'tenant_id'    => (int) $data['tenant_id'],
            'name'         => (string) $data['name'],
            'code'         => (string) ($data['code'] ?? ''),
            'parent_id'    => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'manager_id'   => isset($data['manager_id']) ? (int) $data['manager_id'] : null,
            'address'      => (string) ($data['address'] ?? ''),
            'city'         => (string) ($data['city'] ?? ''),
            'province'     => (string) ($data['province'] ?? ''),
            'phone'        => (string) ($data['phone'] ?? ''),
            'is_default'   => !empty($data['is_default']) ? 1 : 0,
            'is_active'    => isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : 1,
            'created_at'   => current_time('mysql', true),
            'updated_at'   => current_time('mysql', true),
        ];
        $wpdb->insert(self::table(), $row);
        if ($wpdb->last_error) {
            throw new \RuntimeException('DB insert failed: ' . $wpdb->last_error);
        }
        $id = (int) $wpdb->insert_id;
        if ($row['is_default'] === 1) {
            self::setDefault($id, (int) $row['tenant_id']);
        }
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        $allowed = ['name', 'code', 'parent_id', 'manager_id', 'address', 'city', 'province', 'phone', 'is_default', 'is_active'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) {
            return false;
        }
        $update['updated_at'] = current_time('mysql', true);
        $r = $wpdb->update(self::table(), $update, ['id' => $id]);
        if ($r === false) {
            return false;
        }
        if (!empty($update['is_default'])) {
            $tenant = (int) $wpdb->get_var($wpdb->prepare("SELECT tenant_id FROM " . self::table() . " WHERE id = %d", $id));
            self::setDefault($id, $tenant);
        }
        return true;
    }

    public static function delete(int $id): bool
    {
        global $wpdb;
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

    public static function defaultForTenant(int $tenantId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE tenant_id = %d AND is_default = 1 LIMIT 1",
            $tenantId
        ), ARRAY_A);
        if ($row) {
            return self::hydrate($row);
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE tenant_id = %d AND is_active = 1 ORDER BY id ASC LIMIT 1",
            $tenantId
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public static function list(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['tenant_id'])) {
            $where[] = 'tenant_id = %d';
            $params[] = (int) $filters['tenant_id'];
        }
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = %d';
            $params[] = (int) $filters['is_active'];
        }
        $whereSql = implode(' AND ', $where);
        $params[] = max(1, min(500, $limit));
        $params[] = max(0, $offset);
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE {$whereSql} ORDER BY is_default DESC, id ASC LIMIT %d OFFSET %d",
            $params
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function count(int $tenantId): int
    {
        global $wpdb;
        return (int) ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE tenant_id = %d",
            $tenantId
        )) ?: 0);
    }

    private static function setDefault(int $branchId, int $tenantId): void
    {
        global $wpdb;
        $wpdb->update(self::table(), ['is_default' => 0], ['tenant_id' => $tenantId]);
        $wpdb->update(self::table(), ['is_default' => 1], ['id' => $branchId]);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function hydrate(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'uuid'       => (string) $row['uuid'],
            'tenant_id'  => (int) $row['tenant_id'],
            'name'       => (string) $row['name'],
            'code'       => (string) ($row['code'] ?? ''),
            'parent_id'  => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            'manager_id' => isset($row['manager_id']) ? (int) $row['manager_id'] : null,
            'address'    => (string) ($row['address'] ?? ''),
            'city'       => (string) ($row['city'] ?? ''),
            'province'   => (string) ($row['province'] ?? ''),
            'phone'      => (string) ($row['phone'] ?? ''),
            'is_default' => (int) ($row['is_default'] ?? 0) === 1,
            'is_active'  => (int) ($row['is_active'] ?? 1) === 1,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
