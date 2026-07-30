<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Tax\MoodianClient;

final class TaxController
{
    public static function submitInvoice(WP_REST_Request $req)
    {
        try {
            $uid = MoodianClient::submitInvoice((int) $req['id']);
        } catch (\Throwable $e) {
            return new WP_Error('submit_failed', $e->getMessage(), ['status' => 502]);
        }
        return rest_ensure_response(['tax_invoice_uid' => $uid]);
    }
}
