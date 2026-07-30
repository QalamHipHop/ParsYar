<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Accounting\ChartOfAccounts;
use Enterprise\Modules\Accounting\Ledger;
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
            return new WP_Error('post_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function listEntries()
    {
        $rows = Db::getResults('journal_entries', [], 'id DESC', 100, 0);
        return rest_ensure_response($rows);
    }

    public static function trialBalance()
    {
        return rest_ensure_response(Ledger::trialBalance());
    }
}
