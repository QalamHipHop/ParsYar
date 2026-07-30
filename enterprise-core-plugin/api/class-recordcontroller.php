<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Objects\ObjectEngine;

final class RecordController
{
    public static function index(WP_REST_Request $req)
    {
        $api = (string) $req['api'];
        $obj = ObjectEngine::findObjectByApiName($api);
        if (!$obj) {
            return new WP_Error('not_found', 'Object not found', ['status' => 404]);
        }
        $limit  = max(1, min(200, (int) ($req->get_param('limit') ?: 50)));
        $offset = max(0, (int) ($req->get_param('offset') ?: 0));
        $rows   = ObjectEngine::listRecords((int) $obj['id'], ['limit' => $limit, 'offset' => $offset]);
        return rest_ensure_response([
            'data'   => $rows,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    public static function store(WP_REST_Request $req)
    {
        $api    = (string) $req['api'];
        $obj    = ObjectEngine::findObjectByApiName($api);
        if (!$obj) {
            return new WP_Error('not_found', 'Object not found', ['status' => 404]);
        }
        $data   = (array) $req->get_json_params();
        try {
            $id = ObjectEngine::createRecord((int) $obj['id'], $data);
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function show(WP_REST_Request $req)
    {
        $r = ObjectEngine::getRecord((int) $req['id']);
        if (!$r) {
            return new WP_Error('not_found', 'Record not found', ['status' => 404]);
        }
        return rest_ensure_response($r);
    }

    public static function update(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        try {
            $ok = ObjectEngine::updateRecord((int) $req['id'], $data);
        } catch (\Throwable $e) {
            return new WP_Error('update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function destroy(WP_REST_Request $req)
    {
        $ok = ObjectEngine::deleteRecord((int) $req['id']);
        return rest_ensure_response(['ok' => $ok]);
    }
}
