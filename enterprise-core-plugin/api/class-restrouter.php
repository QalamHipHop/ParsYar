<?php
declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

/**
 * ثبت Routeهای REST API.
 *
 * Prefix: /wp-json/enterprise/v1
 */
final class RestRouter
{
    public static function register(): void
    {
        $ns = \Enterprise\Bootstrap::NS;

        // Auth
        register_rest_route($ns, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [AuthController::class, 'login'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [AuthController::class, 'me'],
            'permission_callback' => [AuthController::class, 'isAuthed'],
        ]);

        // Objects
        register_rest_route($ns, '/objects', [
            'methods'             => 'GET',
            'callback'            => [ObjectController::class, 'index'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/objects', [
            'methods'             => 'POST',
            'callback'            => [ObjectController::class, 'store'],
            'permission_callback' => [AuthController::class, 'capAdmin'],
        ]);
        register_rest_route($ns, '/objects/(?P<api>[a-z0-9_]+)', [
            'methods'             => 'GET',
            'callback'            => [ObjectController::class, 'show'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/objects/(?P<api>[a-z0-9_]+)/records', [
            [
                'methods'             => 'GET',
                'callback'            => [RecordController::class, 'index'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [RecordController::class, 'store'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/records/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [RecordController::class, 'show'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [RecordController::class, 'update'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [RecordController::class, 'destroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);

        // Accounting
        register_rest_route($ns, '/accounting/accounts', [
            'methods'             => 'GET',
            'callback'            => [AccountingController::class, 'accounts'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
        ]);
        register_rest_route($ns, '/accounting/journal', [
            [
                'methods'             => 'GET',
                'callback'            => [AccountingController::class, 'listEntries'],
                'permission_callback' => [AuthController::class, 'capAccounting'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [AccountingController::class, 'postEntry'],
                'permission_callback' => [AuthController::class, 'capAccounting'],
            ],
        ]);
        register_rest_route($ns, '/accounting/trial-balance', [
            'methods'             => 'GET',
            'callback'            => [AccountingController::class, 'trialBalance'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
        ]);

        // CRM
        register_rest_route($ns, '/crm/leads', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'leadsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [CrmController::class, 'leadsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);

        // ERP
        register_rest_route($ns, '/erp/products', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'productsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'productsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/invoices', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'invoicesIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'invoicesStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);

        // HRM
        register_rest_route($ns, '/hrm/employees', [
            [
                'methods'             => 'GET',
                'callback'            => [HrmController::class, 'employeesIndex'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [HrmController::class, 'employeesStore'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
        ]);
        register_rest_route($ns, '/hrm/payroll/run', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'payrollRun'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);

        // Tax (سامانه مؤدیان)
        register_rest_route($ns, '/tax/invoices/(?P<id>\d+)/submit', [
            'methods'             => 'POST',
            'callback'            => [TaxController::class, 'submitInvoice'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
        ]);

        // Workflow
        register_rest_route($ns, '/workflows', [
            [
                'methods'             => 'GET',
                'callback'            => [WorkflowController::class, 'index'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [WorkflowController::class, 'store'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);

        // Audit
        register_rest_route($ns, '/audit', [
            'methods'             => 'GET',
            'callback'            => [AuditController::class, 'index'],
            'permission_callback' => [AuthController::class, 'capAdmin'],
        ]);
    }
}
