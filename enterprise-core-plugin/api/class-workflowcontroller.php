<?php
/**
 * Workflow REST Controller — نسخهٔ ۱.۵.
 *
 * اندپوینت‌ها:
 *   GET    /workflows                 → لیست (با ?active=1)
 *   GET    /workflows/{id}            → جزئیات
 *   POST   /workflows                 → ساخت
 *   PUT    /workflows/{id}            → به‌روزرسانی
 *   DELETE /workflows/{id}            → حذف
 *   POST   /workflows/{id}/duplicate  → تکرار
 *   POST   /workflows/{id}/run        → اجرای دستی
 *   GET    /workflows/{id}/runs       → اجراهای اخیر
 *   GET    /workflows/{id}/logs       → لاگ‌ها
 *   GET    /workflows/templates       → قالب‌های آماده
 *   GET    /workflows/triggers        → لیست triggerهای مجاز
 *   GET    /workflows/node-types      → انواع گره + اپراتورها
 *   GET    /workflows/stats           → آمار
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use Enterprise\Modules\Workflow\Repository;
use Enterprise\Modules\Workflow\Dispatcher;
use Enterprise\Support\Db;

final class WorkflowController
{
    public static function index(WP_REST_Request $req)
    {
        $active = (bool) $req->get_param('active');
        $rows   = Repository::listAll($active, 200, 0);
        $out    = array_map(static function (array $r): array {
            $r['graph'] = $r['graph'] ?? json_decode((string) ($r['graph_json'] ?? ''), true) ?: [];
            return $r;
        }, $rows);
        return rest_ensure_response([
            'success' => true,
            'data'    => $out,
            'meta'    => ['total' => count($out)],
        ]);
    }

    public static function show(WP_REST_Request $req)
    {
        $id  = (int) $req['id'];
        $row = Repository::find($id);
        if (!$row) {
            return new \WP_Error('parsyar.workflow.not_found', 'Workflow یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true, 'data' => $row]);
    }

    public static function store(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        if (empty($data['name'])) {
            return new \WP_Error('parsyar.workflow.invalid', 'نام Workflow الزامی است.', ['status' => 400]);
        }
        if (empty($data['trigger_event']) || !array_key_exists($data['trigger_event'], Repository::TRIGGERS)) {
            return new \WP_Error('parsyar.workflow.invalid_trigger', 'Trigger نامعتبر است.', ['status' => 400]);
        }
        if (isset($data['graph']) && is_array($data['graph'])) {
            $errors = Dispatcher::validateGraph($data['graph']);
            if (!empty($errors)) {
                return new \WP_Error('parsyar.workflow.invalid_graph', 'گراف نامعتبر است.', ['status' => 422, 'errors' => $errors]);
            }
        }
        $id = Repository::create($data);
        return rest_ensure_response(['success' => true, 'data' => ['id' => $id]], 201);
    }

    public static function update(WP_REST_Request $req)
    {
        $id   = (int) $req['id'];
        $data = (array) $req->get_json_params();
        if (isset($data['trigger_event']) && !array_key_exists($data['trigger_event'], Repository::TRIGGERS)) {
            return new \WP_Error('parsyar.workflow.invalid_trigger', 'Trigger نامعتبر است.', ['status' => 400]);
        }
        if (isset($data['graph']) && is_array($data['graph'])) {
            $errors = Dispatcher::validateGraph($data['graph']);
            if (!empty($errors)) {
                return new \WP_Error('parsyar.workflow.invalid_graph', 'گراف نامعتبر است.', ['status' => 422, 'errors' => $errors]);
            }
        }
        $ok = Repository::update($id, $data);
        if (!$ok) {
            return new \WP_Error('parsyar.workflow.not_found', 'Workflow یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true, 'data' => ['id' => $id]]);
    }

    public static function destroy(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = Repository::delete($id);
        if (!$ok) {
            return new \WP_Error('parsyar.workflow.not_found', 'Workflow یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function duplicate(WP_REST_Request $req)
    {
        $id  = (int) $req['id'];
        $new = (string) ($req->get_param('name') ?? '');
        $dup = Repository::duplicate($id, $new !== '' ? $new : null);
        if (!$dup) {
            return new \WP_Error('parsyar.workflow.not_found', 'Workflow یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true, 'data' => ['id' => $dup]], 201);
    }

    public static function run(WP_REST_Request $req)
    {
        $id  = (int) $req['id'];
        $ctx = (array) $req->get_json_params();
        $runId = Repository::runManually($id, $ctx);
        if (!$runId) {
            return new \WP_Error('parsyar.workflow.not_found', 'Workflow یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true, 'data' => ['run_id' => $runId]]);
    }

    public static function runs(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        return rest_ensure_response([
            'success' => true,
            'data'    => Repository::recentRuns($id, 50),
        ]);
    }

    public static function logs(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        return rest_ensure_response([
            'success' => true,
            'data'    => Repository::logs($id, 200, 0),
        ]);
    }

    public static function templates()
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => Repository::templates(),
        ]);
    }

    public static function triggers()
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => Repository::TRIGGERS,
        ]);
    }

    public static function nodeTypes()
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => [
                'nodes' => Dispatcher::NODE_TYPES,
                'ops'   => Dispatcher::OPS,
            ],
        ]);
    }

    public static function stats()
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => Repository::stats(),
        ]);
    }
}
