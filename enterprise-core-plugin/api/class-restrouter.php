<?php
/**
 * ثبت Routeهای REST API.
 *
 * Prefix: /wp-json/enterprise/v1
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

final class RestRouter
{
    public static function register(): void
    {
        $ns = \Enterprise\Bootstrap::NS;

        // Rate limit + Security headers (CSP, X-Frame-Options, ...)
        SecurityHeaders::register();

        /* ------------------------------------------------------------------ *
         *  Auth
         * ------------------------------------------------------------------ */
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

        /* ------------------------------------------------------------------ *
         *  Objects (custom object engine)
         * ------------------------------------------------------------------ */
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

        /* ------------------------------------------------------------------ *
         *  CRM — Contacts
         * ------------------------------------------------------------------ */
        register_rest_route($ns, '/crm/contacts', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'contactsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [CrmController::class, 'contactsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/contacts/lifecycle-breakdown', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'contactsLifecycleBreakdown'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/contacts/quick-search', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'contactsQuickSearch'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/contacts/(?P<id>[\w-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'contactsShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [CrmController::class, 'contactsUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [CrmController::class, 'contactsDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/contacts/(?P<id>\d+)/timeline', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'contactsTimeline'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/contacts/(?P<id>\d+)/merge', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'contactsMerge'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/contacts/(?P<id>\d+)/lifecycle', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'contactsLifecycle'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ------------------------------------------------------------------ *
         *  CRM — Organizations
         * ------------------------------------------------------------------ */
        register_rest_route($ns, '/crm/organizations', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'organizationsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [CrmController::class, 'organizationsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/organizations/industry-breakdown', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'organizationsIndustryBreakdown'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/organizations/geo-breakdown', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'organizationsGeoBreakdown'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/organizations/(?P<id>[\w-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'organizationsShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [CrmController::class, 'organizationsUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [CrmController::class, 'organizationsDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);

        /* ------------------------------------------------------------------ *
         *  CRM — Leads
         * ------------------------------------------------------------------ */
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
        register_rest_route($ns, '/crm/leads/funnel', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'leadsFunnel'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/leads/hot', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'leadsHot'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/leads/(?P<id>[\w-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'leadsShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [CrmController::class, 'leadsUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [CrmController::class, 'leadsDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/leads/(?P<id>\d+)/convert', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'leadsConvert'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/leads/(?P<id>\d+)/assign', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'leadsAssign'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ------------------------------------------------------------------ *
         *  CRM — Deals / Pipelines
         * ------------------------------------------------------------------ */
        register_rest_route($ns, '/crm/deals', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'dealsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [CrmController::class, 'dealsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/deals/rotting', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'dealsRotting'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/deals/forecast', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'dealsForecast'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/deals/(?P<id>[\w-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'dealsShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [CrmController::class, 'dealsUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [CrmController::class, 'dealsDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/deals/(?P<id>\d+)/move', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'dealsMove'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/deals/(?P<id>\d+)/assign', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'dealsAssign'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        register_rest_route($ns, '/crm/pipelines', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'pipelinesIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [CrmController::class, 'pipelinesStore'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/crm/pipelines/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'pipelinesShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [CrmController::class, 'pipelinesUpdate'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [CrmController::class, 'pipelinesDestroy'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/crm/pipelines/(?P<id>\d+)/stages', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'pipelineStagesIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [CrmController::class, 'pipelineStagesStore'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/crm/pipelines/(?P<id>\d+)/stages/(?P<stage_id>\d+)', [
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [CrmController::class, 'pipelineStagesUpdate'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [CrmController::class, 'pipelineStagesDestroy'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/crm/pipelines/(?P<id>\d+)/summary', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'pipelineSummary'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ------------------------------------------------------------------ *
         *  CRM — Activities
         * ------------------------------------------------------------------ */
        register_rest_route($ns, '/crm/activities', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'activitiesIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [CrmController::class, 'activitiesStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/activities/today', [
            'methods'             => 'GET',
            'callback'            => [CrmController::class, 'activitiesToday'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/activities/(?P<id>[\w-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [CrmController::class, 'activitiesShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [CrmController::class, 'activitiesUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [CrmController::class, 'activitiesDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/crm/activities/(?P<id>\d+)/complete', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'activitiesComplete'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/crm/activities/(?P<id>\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [CrmController::class, 'activitiesCancel'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ------------------------------------------------------------------ *
         *  Accounting
         * ------------------------------------------------------------------ */
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
            'args' => [
                'company_id'       => ['required' => false, 'type' => 'integer', 'default' => 1],
                'fiscal_period_id' => ['required' => false, 'type' => 'integer'],
                'date_from'        => ['required' => false, 'type' => 'string', 'format' => 'date'],
                'date_to'          => ['required' => false, 'type' => 'string', 'format' => 'date'],
                'include_zero'     => ['required' => false, 'type' => 'boolean', 'default' => false],
            ],
        ]);
        register_rest_route($ns, '/accounting/income-statement', [
            'methods'             => 'GET',
            'callback'            => [AccountingController::class, 'incomeStatement'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
            'args' => [
                'company_id'       => ['required' => false, 'type' => 'integer', 'default' => 1],
                'fiscal_period_id' => ['required' => false, 'type' => 'integer'],
                'date_from'        => ['required' => false, 'type' => 'string', 'format' => 'date'],
                'date_to'          => ['required' => false, 'type' => 'string', 'format' => 'date'],
            ],
        ]);
        register_rest_route($ns, '/accounting/balance-sheet', [
            'methods'             => 'GET',
            'callback'            => [AccountingController::class, 'balanceSheet'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
            'args' => [
                'as_of'            => ['required' => false, 'type' => 'string', 'format' => 'date'],
                'company_id'       => ['required' => false, 'type' => 'integer', 'default' => 1],
            ],
        ]);
        register_rest_route($ns, '/accounting/general-journal', [
            'methods'             => 'GET',
            'callback'            => [AccountingController::class, 'generalJournal'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
            'args' => [
                'company_id'       => ['required' => false, 'type' => 'integer', 'default' => 1],
                'fiscal_period_id' => ['required' => false, 'type' => 'integer'],
                'date_from'        => ['required' => false, 'type' => 'string', 'format' => 'date'],
                'date_to'          => ['required' => false, 'type' => 'string', 'format' => 'date'],
                'source'           => ['required' => false, 'type' => 'string'],
                'limit'            => ['required' => false, 'type' => 'integer', 'default' => 200],
                'offset'           => ['required' => false, 'type' => 'integer', 'default' => 0],
            ],
        ]);
        register_rest_route($ns, '/accounting/account/(?P<code>[0-9A-Z]+)/ledger', [
            'methods'             => 'GET',
            'callback'            => [AccountingController::class, 'accountLedger'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
            'args' => [
                'date_from'        => ['required' => false, 'type' => 'string', 'format' => 'date'],
                'date_to'          => ['required' => false, 'type' => 'string', 'format' => 'date'],
            ],
        ]);

        /* ================================================================== *
         *  ERP — Products
         * ================================================================== */
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
        register_rest_route($ns, '/erp/products/low-stock', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'productsLowStock'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/products/summary', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'productsSummary'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/products/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'productsShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [ErpController::class, 'productsUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ErpController::class, 'productsDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/products/(?P<id>\d+)/stock', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'productsStockAdjust'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/products/(?P<id>\d+)/stock-card', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'productsStockCard'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/products/(?P<id>\d+)/wac', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'productsWac'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ================================================================== *
         *  ERP — Categories
         * ================================================================== */
        register_rest_route($ns, '/erp/categories', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'categoriesIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'categoriesStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/categories/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'categoriesShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [ErpController::class, 'categoriesUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ErpController::class, 'categoriesDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);

        /* ================================================================== *
         *  ERP — Warehouses
         * ================================================================== */
        register_rest_route($ns, '/erp/warehouses', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'warehousesIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'warehousesStore'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/erp/warehouses/default', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'warehousesDefault'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/warehouses/summary', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'warehousesSummary'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/warehouses/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'warehousesShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [ErpController::class, 'warehousesUpdate'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ErpController::class, 'warehousesDestroy'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);
        register_rest_route($ns, '/erp/warehouses/(?P<id>\d+)/set-default', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'warehousesSetDefault'],
            'permission_callback' => [AuthController::class, 'capAdmin'],
        ]);

        /* ================================================================== *
         *  ERP — Stock Movements
         * ================================================================== */
        register_rest_route($ns, '/erp/stock-movements', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'stockMovementsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'stockMovementsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/stock-movements/transfer', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'stockMovementsTransfer'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ================================================================== *
         *  ERP — Invoices
         * ================================================================== */
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
        register_rest_route($ns, '/erp/invoices/overdue', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'invoicesOverdue'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/invoices/summary', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'invoicesSummary'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/invoices/pending-moodian', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'invoicesPendingMoodian'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
        ]);
        register_rest_route($ns, '/erp/invoices/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'invoicesShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [ErpController::class, 'invoicesUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ErpController::class, 'invoicesDestroy'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/invoices/(?P<id>\d+)/pay', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'invoicesPay'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/invoices/(?P<id>\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'invoicesCancel'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/invoices/(?P<id>\d+)/moodian', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'invoicesSetMoodian'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
        ]);

        /* ================================================================== *
         *  ERP — Orders
         * ================================================================== */
        register_rest_route($ns, '/erp/orders', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'ordersIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'ordersStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/orders/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'ordersShow'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [ErpController::class, 'ordersUpdate'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/orders/(?P<id>\d+)/confirm', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'ordersConfirm'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/orders/(?P<id>\d+)/fulfill', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'ordersFulfill'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/orders/(?P<id>\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'ordersCancel'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/orders/(?P<id>\d+)/to-invoice', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'ordersToInvoice'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/orders/(?P<id>\d+)/pay', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'ordersPay'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ================================================================== *
         *  ERP — Payments
         * ================================================================== */
        register_rest_route($ns, '/erp/payments', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'paymentsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'paymentsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/payments/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'paymentsShow'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/payments/(?P<id>\d+)/refund', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'paymentsRefund'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);

        /* ================================================================== *
         *  ERP — Refunds
         * ================================================================== */
        register_rest_route($ns, '/erp/refunds', [
            [
                'methods'             => 'GET',
                'callback'            => [ErpController::class, 'refundsIndex'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ErpController::class, 'refundsStore'],
                'permission_callback' => [AuthController::class, 'capRecords'],
            ],
        ]);
        register_rest_route($ns, '/erp/refunds/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ErpController::class, 'refundsShow'],
            'permission_callback' => [AuthController::class, 'capRecords'],
        ]);
        register_rest_route($ns, '/erp/refunds/(?P<id>\d+)/approve', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'refundsApprove'],
            'permission_callback' => [AuthController::class, 'capAdmin'],
        ]);
        register_rest_route($ns, '/erp/refunds/(?P<id>\d+)/process', [
            'methods'             => 'POST',
            'callback'            => [ErpController::class, 'refundsProcess'],
            'permission_callback' => [AuthController::class, 'capAdmin'],
        ]);

        /* ------------------------------------------------------------------ *
         *  HRM
         * ------------------------------------------------------------------ */
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
        register_rest_route($ns, '/hrm/employees/summary', [
            'methods'             => 'GET',
            'callback'            => [HrmController::class, 'employeesSummary'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/employees/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [HrmController::class, 'employeesShow'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [HrmController::class, 'employeesUpdate'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [HrmController::class, 'employeesDestroy'],
                'permission_callback' => [AuthController::class, 'capAdmin'],
            ],
        ]);

        register_rest_route($ns, '/hrm/attendance', [
            'methods'             => 'GET',
            'callback'            => [HrmController::class, 'attendanceIndex'],
            'permission_callback' => [AuthController::class, 'capHR'],
            'args' => [
                'employee_id' => ['required' => true, 'type' => 'integer'],
                'year'        => ['required' => false, 'type' => 'integer', 'default' => 0],
                'month'       => ['required' => false, 'type' => 'integer', 'default' => 0],
            ],
        ]);
        register_rest_route($ns, '/hrm/attendance/summary', [
            'methods'             => 'GET',
            'callback'            => [HrmController::class, 'attendanceSummary'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/attendance/team', [
            'methods'             => 'GET',
            'callback'            => [HrmController::class, 'attendanceTeam'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/attendance/check-in', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'attendanceCheckIn'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/attendance/check-out', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'attendanceCheckOut'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);

        register_rest_route($ns, '/hrm/leaves', [
            [
                'methods'             => 'GET',
                'callback'            => [HrmController::class, 'leavesIndex'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [HrmController::class, 'leavesStore'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
        ]);
        register_rest_route($ns, '/hrm/leaves/balance', [
            'methods'             => 'GET',
            'callback'            => [HrmController::class, 'leavesBalance'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/leaves/(?P<id>\d+)/approve', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'leavesApprove'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/leaves/(?P<id>\d+)/reject', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'leavesReject'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/leaves/(?P<id>\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'leavesCancel'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);

        register_rest_route($ns, '/hrm/reviews', [
            [
                'methods'             => 'GET',
                'callback'            => [HrmController::class, 'reviewsIndex'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [HrmController::class, 'reviewsStore'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
        ]);
        register_rest_route($ns, '/hrm/reviews/(?P<id>\d+)/submit', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'reviewsSubmit'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/reviews/(?P<id>\d+)/finalize', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'reviewsFinalize'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);

        register_rest_route($ns, '/hrm/payroll', [
            [
                'methods'             => 'GET',
                'callback'            => [HrmController::class, 'payrollIndex'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [HrmController::class, 'payrollRun'],
                'permission_callback' => [AuthController::class, 'capHR'],
            ],
        ]);
        register_rest_route($ns, '/hrm/payroll/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [HrmController::class, 'payrollShow'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/payroll/(?P<id>\d+)/approve', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'payrollApprove'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);
        register_rest_route($ns, '/hrm/payroll/(?P<id>\d+)/pay', [
            'methods'             => 'POST',
            'callback'            => [HrmController::class, 'payrollPay'],
            'permission_callback' => [AuthController::class, 'capHR'],
        ]);

        /* ------------------------------------------------------------------ *
         *  Tax (سامانه مؤدیان)
         * ------------------------------------------------------------------ */
        register_rest_route($ns, '/tax/invoices/(?P<id>\d+)/submit', [
            'methods'             => 'POST',
            'callback'            => [TaxController::class, 'submitInvoice'],
            'permission_callback' => [AuthController::class, 'capAccounting'],
        ]);

        /* ------------------------------------------------------------------ *
         *  Workflow
         * ------------------------------------------------------------------ */
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

        /* ------------------------------------------------------------------ *
         *  Audit
         * ------------------------------------------------------------------ */
        register_rest_route($ns, '/audit', [
            'methods'             => 'GET',
            'callback'            => [AuditController::class, 'index'],
            'permission_callback' => [AuthController::class, 'capAdmin'],
        ]);
    }
}
