<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

final class Menu
{
    public static function register(): void
    {
        add_menu_page(
            'Enterprise',
            'Enterprise',
            'manage_enterprise',
            'enterprise',
            [Dashboard::class, 'render'],
            'dashicons-building',
            2
        );
        add_submenu_page('enterprise', 'Objects',      'Objects',      'manage_enterprise', 'enterprise-objects',  [ObjectsPage::class, 'render']);
        add_submenu_page('enterprise', 'Accounting',   'Accounting',   'manage_enterprise', 'enterprise-account',  [AccountingPage::class, 'render']);
        add_submenu_page('enterprise', 'CRM Leads',    'CRM Leads',    'edit_enterprise_records', 'enterprise-leads',  [CrmPage::class, 'render']);
        add_submenu_page('enterprise', 'Inventory',    'Inventory',    'edit_enterprise_records', 'enterprise-erp',    [ErpPage::class, 'render']);
        add_submenu_page('enterprise', 'HRM',          'HRM',          'manage_enterprise_hr', 'enterprise-hr',      [HrmPage::class, 'render']);
        add_submenu_page('enterprise', 'Workflows',    'Workflows',    'manage_enterprise', 'enterprise-flows',    [WorkflowsPage::class, 'render']);
        add_submenu_page('enterprise', 'Audit Log',    'Audit Log',    'manage_enterprise', 'enterprise-audit',    [AuditPage::class, 'render']);
        add_submenu_page('enterprise', 'Setup',        'Setup Wizard', 'manage_enterprise', 'enterprise-setup',    [Wizard::class, 'render']);
    }
}
