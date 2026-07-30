<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use Enterprise\Modules\Workflow\Repository;
use Enterprise\Support\Db;

final class WorkflowController
{
    public static function index()
    {
        return rest_ensure_response(Db::getResults('workflows', [], 'id DESC', 100, 0));
    }

    public static function store(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        $id   = Repository::create(
            sanitize_text_field((string) ($data['name'] ?? '')),
            sanitize_text_field((string) ($data['trigger_event'] ?? '')),
            (array) ($data['graph'] ?? [])
        );
        return rest_ensure_response(['id' => $id], 201);
    }
}
