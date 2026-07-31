<?php
/**
 * HRM REST Controller — endpoints for Employees, Attendance, Leave,
 * Performance Reviews, and Payroll Runs.
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Hrm\EmployeeService;
use Enterprise\Modules\Hrm\PayrollService;
use Enterprise\Modules\Hrm\AttendanceService;
use Enterprise\Modules\Hrm\LeaveService;
use Enterprise\Modules\Hrm\PerformanceReview;

final class HrmController
{
    // ----- Employees -----

    public static function employeesIndex()
    {
        $q = sanitize_text_field((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            return rest_ensure_response(EmployeeService::search($q));
        }
        return rest_ensure_response(EmployeeService::all());
    }

    public static function employeesShow(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $row = EmployeeService::find($id);
        if (!$row) {
            return new WP_Error('not_found', 'کارمند پیدا نشد', ['status' => 404]);
        }
        return rest_ensure_response($row);
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

    public static function employeesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = EmployeeService::update($id, (array) $req->get_json_params());
        if (!$ok) {
            return new WP_Error('update_failed', 'به‌روزرسانی ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(EmployeeService::find($id));
    }

    public static function employeesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = EmployeeService::delete($id);
        if (!$ok) {
            return new WP_Error('delete_failed', 'حذف ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(['deleted' => true]);
    }

    public static function employeesSummary()
    {
        return rest_ensure_response([
            'total'        => EmployeeService::count(),
            'active'       => EmployeeService::count('active'),
            'on_leave'     => EmployeeService::count('on_leave'),
            'terminated'   => EmployeeService::count('terminated'),
            'suspended'    => EmployeeService::count('suspended'),
            'avg_tenure'   => EmployeeService::averageTenureMonths(),
            'pending_leaves' => LeaveService::pendingCount(),
        ]);
    }

    // ----- Attendance -----

    public static function attendanceIndex(WP_REST_Request $req)
    {
        $employeeId = (int) ($req['employee_id'] ?? $req->get_param('employee_id') ?? 0);
        $year  = (int) ($req->get_param('year')  ?? gmdate('Y'));
        $month = (int) ($req->get_param('month') ?? gmdate('n'));
        if ($employeeId === 0) {
            return new WP_Error('bad_request', 'employee_id required', ['status' => 400]);
        }
        return rest_ensure_response(AttendanceService::monthGrid($employeeId, $year, $month));
    }

    public static function attendanceSummary(WP_REST_Request $req)
    {
        $employeeId = (int) ($req['employee_id'] ?? 0);
        $year  = (int) ($req->get_param('year')  ?? gmdate('Y'));
        $month = (int) ($req->get_param('month') ?? gmdate('n'));
        if ($employeeId === 0) {
            return new WP_Error('bad_request', 'employee_id required', ['status' => 400]);
        }
        return rest_ensure_response(AttendanceService::monthSummary($employeeId, $year, $month));
    }

    public static function attendanceCheckIn(WP_REST_Request $req)
    {
        $employeeId = (int) ($req['employee_id'] ?? 0);
        if ($employeeId === 0) {
            $employeeId = (int) ($req->get_param('employee_id') ?? 0);
        }
        if ($employeeId === 0) {
            return new WP_Error('bad_request', 'employee_id required', ['status' => 400]);
        }
        $lat = $req->get_param('latitude');
        $lng = $req->get_param('longitude');
        return rest_ensure_response(AttendanceService::checkIn(
            $employeeId,
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        ));
    }

    public static function attendanceCheckOut(WP_REST_Request $req)
    {
        $employeeId = (int) ($req['employee_id'] ?? 0);
        if ($employeeId === 0) {
            $employeeId = (int) ($req->get_param('employee_id') ?? 0);
        }
        if ($employeeId === 0) {
            return new WP_Error('bad_request', 'employee_id required', ['status' => 400]);
        }
        $lat = $req->get_param('latitude');
        $lng = $req->get_param('longitude');
        return rest_ensure_response(AttendanceService::checkOut(
            $employeeId,
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        ));
    }

    public static function attendanceTeam(WP_REST_Request $req)
    {
        $idsRaw = (array) ($req->get_param('employee_ids') ?? []);
        $ids = array_map('intval', $idsRaw);
        $year  = (int) ($req->get_param('year')  ?? gmdate('Y'));
        $month = (int) ($req->get_param('month') ?? gmdate('n'));
        return rest_ensure_response(AttendanceService::teamStats($ids, $year, $month));
    }

    // ----- Leave -----

    public static function leavesIndex(WP_REST_Request $req)
    {
        $employeeId = (int) ($req->get_param('employee_id') ?? 0);
        $status     = sanitize_text_field((string) ($req->get_param('status') ?? ''));
        if ($employeeId === 0) {
            return new WP_Error('bad_request', 'employee_id required', ['status' => 400]);
        }
        return rest_ensure_response(LeaveService::listFor(
            $employeeId,
            $status !== '' ? $status : null,
        ));
    }

    public static function leavesStore(WP_REST_Request $req)
    {
        try {
            $id = LeaveService::request((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('leave_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function leavesApprove(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = LeaveService::approve($id, null, (string) ($req->get_param('note') ?? ''));
        if (!$ok) {
            return new WP_Error('approve_failed', 'تأیید ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(LeaveService::find($id));
    }

    public static function leavesReject(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = LeaveService::reject($id, null, (string) ($req->get_param('note') ?? ''));
        if (!$ok) {
            return new WP_Error('reject_failed', 'رد ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(LeaveService::find($id));
    }

    public static function leavesCancel(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = LeaveService::cancel($id);
        if (!$ok) {
            return new WP_Error('cancel_failed', 'لغو ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(['cancelled' => true]);
    }

    public static function leavesBalance(WP_REST_Request $req)
    {
        $employeeId = (int) ($req['employee_id'] ?? 0);
        $year  = (int) ($req->get_param('year') ?? gmdate('Y'));
        if ($employeeId === 0) {
            return new WP_Error('bad_request', 'employee_id required', ['status' => 400]);
        }
        return rest_ensure_response([
            'annual_balance' => LeaveService::annualBalance($employeeId, $year),
            'year_report'    => LeaveService::yearReport($employeeId, $year),
        ]);
    }

    // ----- Performance reviews -----

    public static function reviewsIndex(WP_REST_Request $req)
    {
        $employeeId = (int) ($req->get_param('employee_id') ?? 0);
        if ($employeeId === 0) {
            return new WP_Error('bad_request', 'employee_id required', ['status' => 400]);
        }
        return rest_ensure_response(PerformanceReview::forEmployee($employeeId));
    }

    public static function reviewsStore(WP_REST_Request $req)
    {
        try {
            $id = PerformanceReview::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function reviewsSubmit(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = PerformanceReview::submit($id);
        if (!$ok) {
            return new WP_Error('submit_failed', 'ارسال ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(PerformanceReview::find($id));
    }

    public static function reviewsFinalize(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = PerformanceReview::finalize($id);
        if (!$ok) {
            return new WP_Error('finalize_failed', 'نهایی‌سازی ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(PerformanceReview::find($id));
    }

    // ----- Payroll -----

    public static function payrollRun(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        $period = sanitize_text_field((string) ($data['period'] ?? gmdate('Y-m')));
        if (!preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return new WP_Error('bad_period', 'فرمت دوره باید YYYY-MM باشد', ['status' => 400]);
        }
        $year  = (int) $m[1];
        $month = (int) $m[2];
        try {
            $runId = PayrollService::createRun($year, $month);
            $totals = PayrollService::calculate($runId);
        } catch (\Throwable $e) {
            return new WP_Error('payroll_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response([
            'run_id' => $runId,
            'period' => $period,
            'totals' => $totals,
        ], 201);
    }

    public static function payrollApprove(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = PayrollService::approve($id);
        if (!$ok) {
            return new WP_Error('approve_failed', 'تأیید حقوق ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(PayrollService::findRun($id));
    }

    public static function payrollPay(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $ok = PayrollService::markPaid($id);
        if (!$ok) {
            return new WP_Error('pay_failed', 'پرداخت ناموفق', ['status' => 400]);
        }
        return rest_ensure_response(PayrollService::findRun($id));
    }

    public static function payrollShow(WP_REST_Request $req)
    {
        $id = (int) $req['id'];
        $run = PayrollService::findRun($id);
        if (!$run) {
            return new WP_Error('not_found', 'یافت نشد', ['status' => 404]);
        }
        $run['items'] = PayrollService::itemsFor($id);
        return rest_ensure_response($run);
    }

    public static function payrollIndex()
    {
        return rest_ensure_response(PayrollService::runs());
    }
}
