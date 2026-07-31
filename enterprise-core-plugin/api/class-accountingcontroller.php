<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Accounting\ChartOfAccounts;
use Enterprise\Modules\Accounting\Ledger;
use Enterprise\Modules\Accounting\Reports;
use Enterprise\Support\Db;

final class AccountingController
{
    public static function accounts()
    {
        return rest_ensure_response(ChartOfAccounts::all());
    }

    public static function postEntry(WP_REST_Request $req)
    {
        try {
            $id = Ledger::post((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('parsyar.ledger.post_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function listEntries()
    {
        $rows = Db::getResults('journal_entries', [], 'id DESC', 100, 0);
        return rest_ensure_response($rows);
    }

    public static function trialBalance(WP_REST_Request $req)
    {
        $args = self::filterArgs($req);
        return rest_ensure_response(Reports::trialBalance($args));
    }

    public static function incomeStatement(WP_REST_Request $req)
    {
        $args = self::filterArgs($req);
        return rest_ensure_response(Reports::incomeStatement($args));
    }

    public static function balanceSheet(WP_REST_Request $req)
    {
        $asOf = sanitize_text_field((string) $req->get_param('as_of'));
        if ($asOf === '') {
            $asOf = gmdate('Y-m-d');
        }
        $args = self::filterArgs($req);
        return rest_ensure_response(Reports::balanceSheet($asOf, $args));
    }

    public static function generalJournal(WP_REST_Request $req)
    {
        $args = self::filterArgs($req);
        $args['limit']  = (int) $req->get_param('limit')  ?: 200;
        $args['offset'] = (int) $req->get_param('offset') ?: 0;
        return rest_ensure_response(Reports::generalJournal($args));
    }

    public static function accountLedger(WP_REST_Request $req)
    {
        $code = (string) $req->get_param('code');
        if ($code === '') {
            return new WP_Error('parsyar.reports.code_required', 'account code is required', ['status' => 400]);
        }
        try {
            $data = Reports::accountLedger($code, self::filterArgs($req));
        } catch (\Throwable $e) {
            return new WP_Error('parsyar.reports.ledger_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response($data);
    }

    /**
     * استخراج فیلترهای رایج از request.
     *
     * @return array<string,mixed>
     */
    private static function filterArgs(WP_REST_Request $req): array
    {
        return [
            'company_id'       => (int) ($req->get_param('company_id') ?: 1),
            'fiscal_period_id' => $req->get_param('fiscal_period_id') ? (int) $req->get_param('fiscal_period_id') : null,
            'date_from'        => (string) ($req->get_param('date_from') ?? ''),
            'date_to'          => (string) ($req->get_param('date_to')   ?? ''),
            'currency'         => (string) ($req->get_param('currency')  ?? 'IRT'),
        ];
    }
}
