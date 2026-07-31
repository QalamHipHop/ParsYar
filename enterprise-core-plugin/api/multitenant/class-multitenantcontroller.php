<?php
/**
 * Multitenant REST Controller — /wp-json/enterprise/v1/tenants/*
 *
 * @package Enterprise\Api\Multitenant
 */

declare(strict_types=1);

namespace Enterprise\Api\Multitenant;

defined('ABSPATH') || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Enterprise\Modules\Multitenant\Tenant;
use Enterprise\Modules\Multitenant\Branch;
use Enterprise\Modules\Multitenant\Membership;
use Enterprise\Modules\Multitenant\Repository;
use Enterprise\Modules\Multitenant\Context;

final class MultitenantController
{
    public const NS = 'enterprise/v1';

    public static function registerRoutes(): void
    {
        $ns = self::NS;

        /* ---------- Tenants ---------- */
        register_rest_route($ns, '/tenants', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'tenantsIndex'],
                'permission_callback' => [self::class, 'capAdmin'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'tenantsStore'],
                'permission_callback' => [self::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/tenants/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'tenantsShow'],
                'permission_callback' => [self::class, 'capRecords'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'tenantsUpdate'],
                'permission_callback' => [self::class, 'capAdmin'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [self::class, 'tenantsDestroy'],
                'permission_callback' => [self::class, 'capAdmin'],
            ],
        ]);

        /* ---------- Current tenant (for portal/mobile/REST clients) ---------- */
        register_rest_route($ns, '/tenants/current', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'tenantsCurrent'],
            'permission_callback' => [self::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/tenants/switch', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'tenantsSwitch'],
            'permission_callback' => [self::class, 'capRecords'],
        ]);

        /* ---------- Branches ---------- */
        register_rest_route($ns, '/tenants/(?P<tenant_id>\d+)/branches', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'branchesIndex'],
                'permission_callback' => [self::class, 'capRecords'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'branchesStore'],
                'permission_callback' => [self::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/tenants/(?P<tenant_id>\d+)/branches/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'branchesUpdate'],
                'permission_callback' => [self::class, 'capAdmin'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [self::class, 'branchesDestroy'],
                'permission_callback' => [self::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/tenants/(?P<tenant_id>\d+)/branches/(?P<id>\d+)/default', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'branchesSetDefault'],
            'permission_callback' => [self::class, 'capAdmin'],
        ]);

        /* ---------- Memberships ---------- */
        register_rest_route($ns, '/tenants/(?P<tenant_id>\d+)/members', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'membersIndex'],
            'permission_callback' => [self::class, 'capAdmin'],
        ]);
        register_rest_route($ns, '/tenants/(?P<tenant_id>\d+)/members/grant', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'membersGrant'],
            'permission_callback' => [self::class, 'capAdmin'],
        ]);
        register_rest_route($ns, '/tenants/(?P<tenant_id>\d+)/members/(?P<user_id>\d+)/revoke', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'membersRevoke'],
            'permission_callback' => [self::class, 'capAdmin'],
        ]);

        /* ---------- User's tenants (lightweight, no admin cap) ---------- */
        register_rest_route($ns, '/me/tenants', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'meTenants'],
            'permission_callback' => [self::class, 'capRecords'],
        ]);
    }

    /* ============ Permission callbacks ============ */

    public static function capRecords(): bool
    {
        return is_user_logged_in();
    }

    public static function capAdmin(): bool
    {
        return current_user_can('manage_options') || current_user_can('manage_enterprise');
    }

    /* ============ Tenants ============ */

    public static function tenantsIndex(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $filters = array_filter([
            'status' => $req->get_param('status'),
            'plan'   => $req->get_param('plan'),
            'search' => $req->get_param('search'),
        ], fn($v) => $v !== null && $v !== '');
        $limit  = (int) ($req->get_param('limit') ?? 50);
        $offset = (int) ($req->get_param('offset') ?? 0);
        $rows = Tenant::list($filters, $limit, $offset);
        $total = Tenant::count($filters);
        return self::ok($rows, 200, ['limit' => $limit, 'offset' => $offset, 'total' => $total]);
    }

    public static function tenantsStore(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        try {
            $id = Tenant::create($body);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.tenant.invalid', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return self::fail('parsyar.tenant.create_failed', $e->getMessage(), 500);
        }
        Repository::invalidateCache();
        $created = Tenant::find($id);
        // grant creator as owner
        Membership::grant(get_current_user_id(), $id, 'owner');
        return self::ok($created, 201);
    }

    public static function tenantsShow(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $id = (int) $req->get_param('id');
        $t = Tenant::find($id);
        if (!$t) {
            return self::fail('parsyar.tenant.not_found', 'شرکت پیدا نشد.', 404);
        }
        return self::ok($t);
    }

    public static function tenantsUpdate(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $id = (int) $req->get_param('id');
        $t = Tenant::find($id);
        if (!$t) {
            return self::fail('parsyar.tenant.not_found', 'شرکت پیدا نشد.', 404);
        }
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $ok = Tenant::update($id, $body);
        Repository::invalidateCache();
        return self::ok(['updated' => $ok, 'tenant' => Tenant::find($id)]);
    }

    public static function tenantsDestroy(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $id = (int) $req->get_param('id');
        $ok = Tenant::delete($id, true);
        Repository::invalidateCache();
        return self::ok(['archived' => $ok]);
    }

    /* ============ Current tenant context ============ */

    public static function tenantsCurrent(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $cid = Context::companyId();
        $t = $cid > 0 ? Tenant::find($cid) : null;
        $bid = Context::branchId();
        $b = $bid > 0 ? Branch::find($bid) : null;
        $u = wp_get_current_user();
        $userTenants = $u && $u->ID ? Membership::userTenants($u->ID) : [];
        return self::ok([
            'tenant'        => $t,
            'branch'        => $b,
            'user_tenants'  => $userTenants,
            'is_multitenant'=> Context::isMultitenant(),
        ]);
    }

    public static function tenantsSwitch(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $u = wp_get_current_user();
        if (!$u || !$u->ID) {
            return self::fail('parsyar.auth.required', 'نیاز به لاگین.', 401);
        }
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $tenantId = (int) ($body['tenant_id'] ?? 0);
        $branchId = isset($body['branch_id']) ? (int) $body['branch_id'] : 0;
        if ($tenantId <= 0) {
            return self::fail('parsyar.tenant.missing_id', 'tenant_id الزامی است.', 422);
        }
        if (!Membership::userHasAccess($u->ID, $tenantId, $branchId > 0 ? $branchId : null)) {
            return self::fail('parsyar.tenant.no_access', 'دسترسی به این شرکت ندارید.', 403);
        }
        Context::setCompany($tenantId);
        if ($branchId > 0) {
            Context::setBranch($branchId);
        }
        // پیش‌فرض‌ها را برای کاربر ثبت کن
        update_user_meta($u->ID, 'parsyar_default_company_id', $tenantId);
        if ($branchId > 0) {
            update_user_meta($u->ID, 'parsyar_default_branch_id', $branchId);
        }
        return self::ok([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'tenant'    => Tenant::find($tenantId),
            'branch'    => $branchId > 0 ? Branch::find($branchId) : null,
        ]);
    }

    /* ============ Branches ============ */

    public static function branchesIndex(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $tenantId = (int) $req->get_param('tenant_id');
        if ($tenantId <= 0) {
            return self::fail('parsyar.tenant.missing_id', 'tenant_id الزامی است.', 422);
        }
        $rows = Branch::list(['tenant_id' => $tenantId], 200, 0);
        return self::ok($rows, 200, ['total' => count($rows)]);
    }

    public static function branchesStore(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $tenantId = (int) $req->get_param('tenant_id');
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $body['tenant_id'] = $tenantId;
        try {
            $id = Branch::create($body);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.branch.invalid', $e->getMessage(), 422);
        }
        return self::ok(Branch::find($id), 201);
    }

    public static function branchesUpdate(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $id = (int) $req->get_param('id');
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $ok = Branch::update($id, $body);
        return self::ok(['updated' => $ok, 'branch' => Branch::find($id)]);
    }

    public static function branchesDestroy(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $id = (int) $req->get_param('id');
        $ok = Branch::delete($id);
        return self::ok(['deleted' => $ok]);
    }

    public static function branchesSetDefault(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $id = (int) $req->get_param('id');
        $tenantId = (int) $req->get_param('tenant_id');
        $b = Branch::find($id);
        if (!$b) {
            return self::fail('parsyar.branch.not_found', 'شعبه پیدا نشد.', 404);
        }
        Branch::update($id, ['is_default' => 1]);
        return self::ok(['branch' => Branch::find($id)]);
    }

    /* ============ Members ============ */

    public static function membersIndex(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $tenantId = (int) $req->get_param('tenant_id');
        $rows = Membership::tenantMembers($tenantId);
        return self::ok($rows, 200, ['total' => count($rows)]);
    }

    public static function membersGrant(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $tenantId = (int) $req->get_param('tenant_id');
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $userId = (int) ($body['user_id'] ?? 0);
        $role = (string) ($body['role'] ?? 'member');
        $branchId = isset($body['branch_id']) ? (int) $body['branch_id'] : null;
        if ($userId <= 0) {
            return self::fail('parsyar.member.missing_user', 'user_id الزامی است.', 422);
        }
        try {
            $id = Membership::grant($userId, $tenantId, $role, $branchId);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.member.invalid_role', $e->getMessage(), 422);
        }
        return self::ok(['id' => $id], 201);
    }

    public static function membersRevoke(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $tenantId = (int) $req->get_param('tenant_id');
        $userId = (int) $req->get_param('user_id');
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $branchId = isset($body['branch_id']) ? (int) $body['branch_id'] : null;
        $ok = Membership::revoke($userId, $tenantId, $branchId);
        return self::ok(['revoked' => $ok]);
    }

    /* ============ /me/tenants ============ */

    public static function meTenants(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $u = wp_get_current_user();
        if (!$u || !$u->ID) {
            return self::ok([]);
        }
        $rows = Membership::userTenants($u->ID);
        return self::ok($rows, 200, ['total' => count($rows)]);
    }

    /* ============ helpers ============ */

    private static function ok($data = null, int $status = 200, array $meta = []): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'data' => $data, 'meta' => $meta], $status);
    }

    private static function fail(string $code, string $msg, int $status = 400, array $details = []): WP_Error
    {
        return new WP_Error($code, $msg, array_merge(['status' => $status], $details));
    }
}
