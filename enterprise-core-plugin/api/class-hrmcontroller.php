<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Hrm\EmployeeService;
use Enterprise\Modules\Hrm\PayrollService;

final class HrmController
{
    public static function employeesIndex()
    {
        return rest_ensure_response(EmployeeService::all());
    }

    public static function employeesStore(WP_REST_Request $req)
    {
        try {
            $id = EmployeeService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function payrollRun(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        try {
            $result = PayrollService::run(
                sanitize_text_field((string) ($data['period'] ?? gmdate('Y-m')))
            );
        } catch (\Throwable $e) {
            return new WP_Error('payroll_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response($result);
    }
}
