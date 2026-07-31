<?php
declare(strict_types=1);

namespace Enterprise\Modules\Multitenant;

defined('ABSPATH') || exit;

/**
 * Context چند-مستاجری.
 *
 * در حالت multisite یا single-site با چند شرکت، این کلاس
 * تعیین می‌کند که درخواست فعلی مربوط به کدام tenant (company / branch) است.
 *
 * منابع تشخیص:
 *  1. HTTP header `X-ParsYar-Company` و `X-ParsYar-Branch`
 *  2. WP query var `parsyar_company`
 *  3. کاربر جاری (`parsyar_default_company_id` user_meta)
 *  4. اولین شرکت پیش‌فرض
 */
final class Context
{
    public const QUERY_COMPANY = 'parsyar_company';
    public const QUERY_BRANCH  = 'parsyar_branch';
    public const HEADER_COMPANY = 'X-ParsYar-Company';
    public const HEADER_BRANCH  = 'X-ParsYar-Branch';

    private static ?int $companyId = null;
    private static ?int $branchId  = null;

    public static function boot(): void
    {
        add_filter('query_vars', [self::class, 'registerQueryVars']);
        add_action('parse_request', [self::class, 'parseRequest']);
        add_action('rest_pre_dispatch', [self::class, 'restDispatch'], 10, 3);
    }

    public static function registerQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_COMPANY;
        $vars[] = self::QUERY_BRANCH;
        return $vars;
    }

    public static function parseRequest($wp): void
    {
        $headers = function_exists('getallheaders') ? (array) getallheaders() : [];
        $hCompany = $headers[self::HEADER_COMPANY] ?? null;
        $hBranch  = $headers[self::HEADER_BRANCH]  ?? null;
        $qCompany = get_query_var(self::QUERY_COMPANY);
        $qBranch  = get_query_var(self::QUERY_BRANCH);

        if ($hCompany) {
            self::$companyId = (int) $hCompany;
        } elseif ($qCompany) {
            self::$companyId = (int) $qCompany;
        } else {
            self::$companyId = self::resolveDefaultCompany();
        }
        if ($hBranch) {
            self::$branchId = (int) $hBranch;
        } elseif ($qBranch) {
            self::$branchId = (int) $qBranch;
        } else {
            self::$branchId = self::resolveDefaultBranch(self::$companyId);
        }
    }

    public static function restDispatch($result, $server, $request)
    {
        self::parseRequest(new \WP());
        return $result;
    }

    public static function companyId(): int
    {
        if (self::$companyId === null) {
            self::parseRequest(new \WP());
        }
        return (int) (self::$companyId ?? 0);
    }

    public static function branchId(): int
    {
        if (self::$branchId === null) {
            self::parseRequest(new \WP());
        }
        return (int) (self::$branchId ?? 0);
    }

    public static function setCompany(int $id): void { self::$companyId = $id; }
    public static function setBranch(int $id): void  { self::$branchId  = $id; }

    public static function isMultitenant(): bool
    {
        $mode = get_option('parsyar_deployment_mode', 'micro');
        return in_array($mode, ['enterprise', 'holding'], true);
    }

    private static function resolveDefaultCompany(): int
    {
        $u = wp_get_current_user();
        if ($u && $u->ID) {
            $cid = (int) get_user_meta($u->ID, 'parsyar_default_company_id', true);
            if ($cid) return $cid;
        }
        $companies = (array) get_option('parsyar_companies', []);
        if (!empty($companies[0]['id'])) {
            return (int) $companies[0]['id'];
        }
        return 0;
    }

    private static function resolveDefaultBranch(int $companyId): int
    {
        $u = wp_get_current_user();
        if ($u && $u->ID) {
            $bid = (int) get_user_meta($u->ID, 'parsyar_default_branch_id', true);
            if ($bid) return $bid;
        }
        return 0;
    }
}
