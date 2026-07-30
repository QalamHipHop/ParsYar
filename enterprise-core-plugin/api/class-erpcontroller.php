<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Erp\InventoryService;
use Enterprise\Modules\Erp\InvoiceService;

final class ErpController
{
    public static function productsIndex()
    {
        return rest_ensure_response(InventoryService::products());
    }

    public static function productsStore(WP_REST_Request $req)
    {
        try {
            $id = InventoryService::addProduct((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function invoicesIndex()
    {
        return rest_ensure_response(InvoiceService::all());
    }

    public static function invoicesStore(WP_REST_Request $req)
    {
        try {
            $id = InvoiceService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }
}
