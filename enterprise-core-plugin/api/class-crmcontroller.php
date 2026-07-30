<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Crm\LeadService;

final class CrmController
{
    public static function leadsIndex()
    {
        return rest_ensure_response(LeadService::all());
    }

    public static function leadsStore(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        try {
            $id = LeadService::create($data);
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }
}
