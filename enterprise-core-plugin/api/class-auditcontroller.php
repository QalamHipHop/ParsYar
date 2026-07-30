<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;

final class AuditController
{
    public static function index(\WP_REST_Request $req)
    {
        $limit   = max(1, min(500, (int) ($req->get_param('limit') ?: 50)));
        $object  = $req->get_param('object');
        $rows    = Logger::tail($limit, $object ? sanitize_key((string) $object) : null);
        return rest_ensure_response($rows);
    }
}
