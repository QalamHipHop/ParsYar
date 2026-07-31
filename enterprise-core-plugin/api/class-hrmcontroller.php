<?php
/**
 * HRM REST Controller — نقاط پایانی پرسنل، حضور و غیاب، مرخصی، حقوق
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Hrm\EmployeeService;
use Enterprise\Modules\Hrm\AttendanceService;
use Enterprise\Modules\Hrm\LeaveService;
use Enterprise\Modules\Hrm\PayrollService;

final class HrmController
{
    /* ================================================================== *
     *  Employees
     * ================================================================== */
    public static function employeesIndex(WP_REST_Request $req)
    {
        $filters = [];
        foreach (['status', 'department', 'position', 'employment_type'] as $f) {
            $v = $req->get_param($f);
            if ($v !== null && $v !== '') {
                $filters[$f] = sanitize_text_field((string) $v);
            }
        }
        $limit  = (int) ($req->get_param('per_page') ?? 50);
        $page   = (int) ($req->get_param('page') ?? 0);
        $offset = $page * $limit;
        $rows   = EmployeeService::all($filters, max(1, min(200, $limit)), max(0, $offset));
        $total  = EmployeeService::count($filters);
        return rest_ensure_response([
            'data'  => $rows,
            'meta'  => [
                'total'    => $total,
                'per_page' => max(1, min(200, $limit)),
                'page'     => $page,
            ],
        ]);
    }

    public static function employeesShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = EmployeeService::find($id);
        if (!$row) {
            return new WP_Error('not_found', 'کارمند یافت نشد', ['status' => 404]);
        }
        $row['attendance_recent'] = AttendanceService::listForEmployee($id, 10);
        $row['leave_recent']      = LeaveService::listForEmployee($id, 10);
        return rest_ensure_response($row);
    }

    public static function employeesStore(WP_REST_Request $req)
    {
        try {
            $id = EmployeeService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'employee' => EmployeeService::find($id)], 201);
    }

    public static function employeesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!EmployeeService::find($id)) {
            return new WP_Error('not_found', 'کارمند یافت نشد', ['status' => 404]);
        }
        try {
            $ok = EmployeeService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'employee' => EmployeeService::find($id)]);
    }

    public static function employeesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $hard = (bool) $req->get_param('force');
        $ok = EmployeeService::delete($id, $hard);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function employeesSearch(WP_REST_Request $req)
    {
        $q = (string) ($req->get_param('q') ?? '');
        $limit = (int) ($req->get_param('limit') ?? 20);
        return rest_ensure_response(EmployeeService::search($q, $limit));
    }

    public static function employeesStats()
    {
        return rest_ensure_response(EmployeeService::stats());
    }

    /* ================================================================== *
     *  Attendance
     * ================================================================== */
    public static function attendanceCheckIn(WP_REST_Request $req)
    {
        $id = (int) ($req->get_param('id') ?? 0);
        $employeeId = (int) ($req->get_param('employee_id') ?? $id);
        if ($employeeId <= 0) {
            return new WP_Error('bad_request', 'employee_id الزامی است', ['status' => 400]);
        }
        $time = $req->get_param('time') ? sanitize_text_field((string) $req->get_param('time')) : null;
        $date = $req->get_param('date') ? sanitize_text_field((string) $req->get_param('date')) : null;
        $aid  = AttendanceService::checkIn($employeeId, $time, $date);
        return rest_ensure_response(['id' => $aid, 'attendance' => AttendanceService::find($aid)], 201);
    }

    public static function attendanceCheckOut(WP_REST_Request $req)
    {
        $employeeId = (int) ($req->get_param('employee_id') ?? 0);
        if ($employeeId <= 0) {
            return new WP_Error('bad_request', 'employee_id الزامی است', ['status' => 400]);
        }
        $time = $req->get_param('time') ? sanitize_text_field((string) $req->get_param('time')) : null;
        $date = $req->get_param('date') ? sanitize_text_field((string) $req->get_param('date')) : null;
        $ot   = (int) ($req->get_param('overtime_minutes') ?? 0);
        AttendanceService::checkOut($employeeId, $time, $date, $ot);
        return rest_ensure_response(['ok' => true, 'attendance' => AttendanceService::findForDay($employeeId, $date ?: gmdate('Y-m-d'))]);
    }

    public static function attendanceIndex(WP_REST_Request $req)
    {
        $from   = (string) ($req->get_param('date_from') ?? gmdate('Y-m-01'));
        $to     = (string) ($req->get_param('date_to')   ?? gmdate('Y-m-d'));
        $empId  = $req->get_param('employee_id') ? (int) $req->get_param('employee_id') : null;
        $rows   = AttendanceService::listForRange($from, $to, $empId);
        return rest_ensure_response(['data' => $rows, 'meta' => ['from' => $from, 'to' => $to]]);
    }

    public static function attendanceSummary(WP_REST_Request $req)
    {
        $employeeId = (int) ($req->get_param('employee_id') ?? 0);
        $period     = (string) ($req->get_param('period') ?? gmdate('Y-m'));
        if ($employeeId <= 0) {
            return new WP_Error('bad_request', 'employee_id الزامی است', ['status' => 400]);
        }
        return rest_ensure_response(AttendanceService::summary($employeeId, $period));
    }

    /* ================================================================== *
     *  Leave
     * ================================================================== */
    public static function leaveIndex(WP_REST_Request $req)
    {
        $employeeId = $req->get_param('employee_id') ? (int) $req->get_param('employee_id') : null;
        $status     = $req->get_param('status') ? sanitize_text_field((string) $req->get_param('status')) : null;
        if ($employeeId) {
            $rows = LeaveService::listForEmployee($employeeId);
        } elseif ($status === 'pending') {
            $rows = LeaveService::listPending();
        } else {
            global $wpdb;
            $rows = $wpdb->get_results(
                "SELECT lr.*, e.full_name FROM " . \Enterprise\Support\Db::table('leave_requests', 'parsyar') . " lr
                 LEFT JOIN " . \Enterprise\Support\Db::table('employees') . " e ON e.id = lr.employee_id
                 ORDER BY lr.start_date DESC LIMIT 200",
                ARRAY_A
            ) ?: [];
        }
        return rest_ensure_response($rows);
    }

    public static function leaveStore(WP_REST_Request $req)
    {
        try {
            $id = LeaveService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'leave' => LeaveService::find($id)], 201);
    }

    public static function leaveApprove(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $note = $req->get_param('note');
        $ok = LeaveService::approve($id, null, $note ? (string) $note : null);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function leaveReject(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $note = $req->get_param('note');
        $ok = LeaveService::reject($id, null, $note ? (string) $note : null);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function leaveBalance(WP_REST_Request $req)
    {
        $employeeId = (int) ($req->get_param('employee_id') ?? 0);
        $year = (int) ($req->get_param('year') ?? (int) gmdate('Y'));
        if ($employeeId <= 0) {
            return new WP_Error('bad_request', 'employee_id الزامی است', ['status' => 400]);
        }
        return rest_ensure_response(LeaveService::balance($employeeId, $year));
    }

    /* ================================================================== *
     *  Payroll
     * ================================================================== */
    public static function payrollRun(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        try {
            $result = PayrollService::run(
                sanitize_text_field((string) ($data['period'] ?? gmdate('Y-m'))),
                [
                    'company_id' => (int) ($data['company_id'] ?? 1),
                    'branch_id'  => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
                    'currency'   => sanitize_text_field((string) ($data['currency'] ?? 'IRT')),
                ]
            );
        } catch (\Throwable $e) {
            return new WP_Error('payroll_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response($result, 201);
    }

    public static function payrollHistory(WP_REST_Request $req)
    {
        $limit = (int) ($req->get_param('per_page') ?? 50);
        return rest_ensure_response([
            'data' => PayrollService::history(max(1, min(200, $limit))),
        ]);
    }

    public static function payrollShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = PayrollService::find($id);
        if (!$row) {
            return new WP_Error('not_found', 'اجرای حقوق یافت نشد', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }
}
