<?php
/**
 * Membership — ارتباط کاربر ↔ tenant ↔ branch با نقش.
 *
 * جدول: wp_parsyar_memberships
 *
 * @package Enterprise\Modules\Multitenant
 */

declare(strict_types=1);

namespace Enterprise\Modules\Multitenant;

defined('ABSPATH') || exit;

final class Membership
{
    public const TABLE = 'memberships';
    public const ROLES = ['owner', 'admin', 'manager', 'member', 'viewer'];

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'parsyar_' . self::TABLE;
    }

    public static function grant(int $userId, int $tenantId, string $role = 'member', ?int $branchId = null): int
    {
        global $wpdb;
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('نقش نامعتبر است.');
        }
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE user_id = %d AND tenant_id = %d AND branch_id <=> %d LIMIT 1",
            $userId, $tenantId, $branchId
        ));
        if ($existing) {
            $wpdb->update(self::table(), [
                'role'       => $role,
                'is_active'  => 1,
                'updated_at' => current_time('mysql', true),
            ], ['id' => $existing]);
            return $existing;
        }
        $wpdb->insert(self::table(), [
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'branch_id'  => $branchId,
            'role'       => $role,
            'is_active'  => 1,
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);
        if ($wpdb->last_error) {
            throw new \RuntimeException('DB insert failed: ' . $wpdb->last_error);
        }
        return (int) $wpdb->insert_id;
    }

    public static function revoke(int $userId, int $tenantId, ?int $branchId = null): bool
    {
        global $wpdb;
        $where = ['user_id' => $userId, 'tenant_id' => $tenantId];
        if ($branchId === null) {
            $r = $wpdb->delete(self::table(), $where);
        } else {
            $where['branch_id'] = $branchId;
            $r = $wpdb->delete(self::table(), $where);
        }
        return $r !== false && $r > 0;
    }

    public static function deactivate(int $userId, int $tenantId, ?int $branchId = null): bool
    {
        global $wpdb;
        $where = ['user_id' => $userId, 'tenant_id' => $tenantId, 'is_active' => 1];
        if ($branchId !== null) {
            $where['branch_id'] = $branchId;
        }
        $r = $wpdb->update(self::table(), ['is_active' => 0, 'updated_at' => current_time('mysql', true)], $where);
        return $r !== false;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function userTenants(int $userId, bool $activeOnly = true): array
    {
        global $wpdb;
        $sql = "SELECT m.id AS membership_id, m.user_id, m.tenant_id, m.branch_id, m.role, m.is_active, m.created_at,
                       t.uuid AS tenant_uuid, t.name AS tenant_name, t.slug AS tenant_slug, t.status AS tenant_status,
                       t.plan, t.branding
                FROM " . self::table() . " m
                INNER JOIN " . Tenant::table() . " t ON t.id = m.tenant_id
                WHERE m.user_id = %d" . ($activeOnly ? " AND m.is_active = 1 AND t.status = 'active'" : "") . "
                ORDER BY t.name ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $userId), ARRAY_A) ?: [];
        return array_map(static function (array $r): array {
            $branding = json_decode((string) ($r['branding'] ?? '{}'), true);
            return [
                'membership_id' => (int) $r['membership_id'],
                'user_id'       => (int) $r['user_id'],
                'tenant_id'     => (int) $r['tenant_id'],
                'tenant_uuid'   => (string) $r['tenant_uuid'],
                'tenant_name'   => (string) $r['tenant_name'],
                'tenant_slug'   => (string) $r['tenant_slug'],
                'tenant_status' => (string) $r['tenant_status'],
                'plan'          => (string) ($r['plan'] ?? 'starter'),
                'branch_id'     => isset($r['branch_id']) ? (int) $r['branch_id'] : null,
                'role'          => (string) $r['role'],
                'is_active'     => (int) $r['is_active'] === 1,
                'branding'      => is_array($branding) ? $branding : [],
            ];
        }, $rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function tenantMembers(int $tenantId, ?int $branchId = null): array
    {
        global $wpdb;
        $where = 'm.tenant_id = %d';
        $params = [$tenantId];
        if ($branchId !== null) {
            $where .= ' AND m.branch_id = %d';
            $params[] = $branchId;
        }
        $sql = "SELECT m.id, m.user_id, m.tenant_id, m.branch_id, m.role, m.is_active, m.created_at, m.updated_at,
                       u.user_login, u.user_email, u.display_name
                FROM " . self::table() . " m
                INNER JOIN {$wpdb->users} u ON u.ID = m.user_id
                WHERE {$where}
                ORDER BY m.role ASC, u.display_name ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        return array_map(static function (array $r): array {
            return [
                'id'         => (int) $r['id'],
                'user_id'    => (int) $r['user_id'],
                'user_login' => (string) $r['user_login'],
                'user_email' => (string) $r['user_email'],
                'display_name' => (string) $r['display_name'],
                'tenant_id'  => (int) $r['tenant_id'],
                'branch_id'  => isset($r['branch_id']) ? (int) $r['branch_id'] : null,
                'role'       => (string) $r['role'],
                'is_active'  => (int) $r['is_active'] === 1,
                'created_at' => (string) ($r['created_at'] ?? ''),
                'updated_at' => (string) ($r['updated_at'] ?? ''),
            ];
        }, $rows);
    }

    public static function userHasAccess(int $userId, int $tenantId, ?int $branchId = null): bool
    {
        global $wpdb;
        $sql = "SELECT 1 FROM " . self::table() . " WHERE user_id = %d AND tenant_id = %d AND is_active = 1";
        $params = [$userId, $tenantId];
        if ($branchId !== null) {
            $sql .= " AND (branch_id = %d OR branch_id IS NULL)";
            $params[] = $branchId;
        }
        $sql .= " LIMIT 1";
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params)) === 1;
    }

    public static function userRole(int $userId, int $tenantId): ?string
    {
        global $wpdb;
        $r = $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM " . self::table() . " WHERE user_id = %d AND tenant_id = %d AND is_active = 1 ORDER BY FIELD(role,'owner','admin','manager','member','viewer') LIMIT 1",
            $userId, $tenantId
        ));
        return is_string($r) ? $r : null;
    }
}
