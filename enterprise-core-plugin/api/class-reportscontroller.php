<?php
/**
 * Reports REST Controller — نسخهٔ ۱.۶.
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use Enterprise\Modules\Reports\ReportService;

final class ReportsController
{
    public static function index(WP_REST_Request $req)
    {
        $public = (bool) $req->get_param('public');
        return rest_ensure_response([
            'success' => true,
            'data'    => ReportService::listAll($public, 200, 0),
        ]);
    }

    public static function show(WP_REST_Request $req)
    {
        $id  = (int) $req['id'];
        $row = ReportService::find($id);
        if (!$row) {
            return new \WP_Error('parsyar.report.not_found', 'گزارش یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true, 'data' => $row]);
    }

    public static function store(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        if (empty($data['name'])) {
            return new \WP_Error('parsyar.report.invalid', 'نام گزارش الزامی است.', ['status' => 400]);
        }
        if (empty($data['data_source']) || !array_key_exists($data['data_source'], ReportService::SOURCES)) {
            return new \WP_Error('parsyar.report.invalid_source', 'data_source نامعتبر است.', ['status' => 400]);
        }
        $id = ReportService::create($data);
        return rest_ensure_response(['success' => true, 'data' => ['id' => $id]], 201);
    }

    public static function update(WP_REST_Request $req)
    {
        $id   = (int) $req['id'];
        $data = (array) $req->get_json_params();
        if (isset($data['data_source']) && !array_key_exists($data['data_source'], ReportService::SOURCES)) {
            return new \WP_Error('parsyar.report.invalid_source', 'data_source نامعتبر است.', ['status' => 400]);
        }
        $ok = ReportService::update($id, $data);
        if (!$ok) {
            return new \WP_Error('parsyar.report.not_found', 'گزارش یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function destroy(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = ReportService::delete($id);
        if (!$ok) {
            return new \WP_Error('parsyar.report.not_found', 'گزارش یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function run(WP_REST_Request $req)
    {
        $id  = (int) $req['id'];
        $overrides = (array) $req->get_json_params();
        $result = ReportService::run($id, $overrides);
        if (isset($result['error']) && $result['error'] === 'report_not_found') {
            return new \WP_Error('parsyar.report.not_found', 'گزارش یافت نشد.', ['status' => 404]);
        }
        return rest_ensure_response(['success' => true, 'data' => $result]);
    }

    public static function preview(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        if (empty($data['data_source']) || !array_key_exists($data['data_source'], ReportService::SOURCES)) {
            return new \WP_Error('parsyar.report.invalid_source', 'data_source نامعتبر است.', ['status' => 400]);
        }
        $cfg = ReportService::normalizeConfig($data);
        $result = ReportService::execute((string) $data['data_source'], $cfg);
        return rest_ensure_response(['success' => true, 'data' => $result]);
    }

    public static function sources()
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => ReportService::SOURCES,
        ]);
    }

    public static function meta()
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => [
                'ops'    => ReportService::OPS,
                'charts' => ReportService::CHARTS,
            ],
        ]);
    }

    public static function templates()
    {
        return rest_ensure_response([
            'success' => true,
            'data'    => ReportService::templates(),
        ]);
    }

    public static function exportCsv(WP_REST_Request $req)
    {
        $id  = (int) $req['id'];
        $row = ReportService::find($id);
        if (!$row) {
            return new \WP_Error('parsyar.report.not_found', 'گزارش یافت نشد.', ['status' => 404]);
        }
        $result = ReportService::run($id, []);
        $csv    = ReportService::toCsv($result);
        $fname  = sanitize_title($row['name']) . '.csv';
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
