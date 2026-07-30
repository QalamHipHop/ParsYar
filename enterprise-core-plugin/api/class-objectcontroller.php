<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Objects\ObjectEngine;
use Enterprise\Support\Db;

final class ObjectController
{
    public static function index()
    {
        $rows = Db::getResults('objects', [], 'label ASC', 200, 0);
        return rest_ensure_response($rows);
    }

    public static function store(WP_REST_Request $req)
    {
        $params = $req->get_json_params();
        try {
            $id = ObjectEngine::createObject([
                'api_name'     => sanitize_key((string) ($params['api_name'] ?? '')),
                'label'        => sanitize_text_field((string) ($params['label'] ?? '')),
                'label_plural' => sanitize_text_field((string) ($params['label_plural'] ?? '')),
                'description'  => sanitize_textarea_field((string) ($params['description'] ?? '')),
                'fields'       => (array) ($params['fields'] ?? []),
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id]);
    }

    public static function show(WP_REST_Request $req)
    {
        $api = (string) $req['api'];
        $obj = ObjectEngine::findObjectByApiName($api);
        if (!$obj) {
            return new WP_Error('not_found', 'Object not found', ['status' => 404]);
        }
        $obj['fields'] = ObjectEngine::getFields((int) $obj['id']);
        return rest_ensure_response($obj);
    }
}
