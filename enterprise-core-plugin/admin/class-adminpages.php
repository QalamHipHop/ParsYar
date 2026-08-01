<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

use Enterprise\Modules\Objects\ObjectEngine;
use Enterprise\Modules\Accounting\Ledger;
use Enterprise\Modules\Crm\LeadService;
use Enterprise\Modules\Erp\InventoryService;
use Enterprise\Modules\Erp\InvoiceService;
use Enterprise\Modules\Hrm\EmployeeService;
use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

final class Dashboard
{
    public static function render(): void
    {
        $counts = [
            'accounts'  => (int) Db::getResults('accounts', [], 'id ASC', 1, 0) ? count(Db::getResults('accounts', [])) : 0,
            'records'   => count(Db::getResults('records', [])),
            'leads'     => count(Db::getResults('leads', [])),
            'products'  => count(Db::getResults('products', [])),
            'invoices'  => count(Db::getResults('invoices', [])),
            'employees' => count(Db::getResults('employees', [])),
            'journals'  => count(Db::getResults('journal_entries', [])),
        ];
        echo '<div class="wrap"><h1>Enterprise Dashboard</h1>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-top:16px;">';
        foreach ($counts as $k => $v) {
            printf(
                '<div style="background:#fff;padding:16px;border:1px solid #e5e7eb;border-radius:8px;"><div style="font-size:12px;color:#6b7280;text-transform:uppercase;">%s</div><div style="font-size:28px;font-weight:700;">%d</div></div>',
                esc_html($k),
                (int) $v
            );
        }
        echo '</div>';
        echo '<p style="margin-top:24px;">داشبورد کامل در آدرس <a href="' . esc_url(home_url('/enterprise')) . '">/enterprise</a> در دسترس است.</p>';
        echo '</div>';
    }
}

final class ObjectsPage
{
    public static function render(): void
    {
        $rows = Db::getResults('objects', [], 'id ASC');
        echo '<div class="wrap"><h1>Objects</h1><table class="widefat"><thead><tr><th>API</th><th>Label</th><th>System</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td><code>' . esc_html($r['api_name']) . '</code></td><td>' . esc_html($r['label']) . '</td><td>' . ($r['is_system'] ? 'بله' : 'خیر') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

final class AccountingPage
{
    public static function render(): void
    {
        $tb = Ledger::trialBalance();
        echo '<div class="wrap"><h1>Trial Balance</h1><table class="widefat"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Debit</th><th>Credit</th></tr></thead><tbody>';
        foreach ($tb as $r) {
            echo '<tr><td><code>' . esc_html($r['code']) . '</code></td><td>' . esc_html($r['name']) . '</td><td>' . esc_html($r['type']) . '</td><td>' . number_format((float) $r['total_debit'], 2) . '</td><td>' . number_format((float) $r['total_credit'], 2) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

final class CrmPage
{
    public static function render(): void
    {
        $rows = LeadService::all();
        echo '<div class="wrap"><h1>Leads</h1><table class="widefat"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th>Score</th><th>Stage</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html($r['full_name']) . '</td><td>' . esc_html((string) $r['email']) . '</td><td>' . esc_html((string) $r['phone']) . '</td><td>' . esc_html((string) $r['source']) . '</td><td>' . (int) $r['score'] . '</td><td>' . esc_html($r['stage']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

final class ErpPage
{
    public static function render(): void
    {
        $products  = InventoryService::products();
        $invoices  = InvoiceService::all();
        echo '<div class="wrap"><h1>Inventory</h1><table class="widefat"><thead><tr><th>SKU</th><th>Name</th><th>Cost</th><th>Price</th><th>Stock</th></tr></thead><tbody>';
        foreach ($products as $p) {
            echo '<tr><td>' . esc_html($p['sku']) . '</td><td>' . esc_html($p['name']) . '</td><td>' . number_format((float) $p['cost'], 2) . '</td><td>' . number_format((float) $p['price'], 2) . '</td><td>' . (int) $p['stock'] . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h1 style="margin-top:24px;">Invoices</h1><table class="widefat"><thead><tr><th>No</th><th>Date</th><th>Total</th><th>Status</th><th>Tax UID</th></tr></thead><tbody>';
        foreach ($invoices as $i) {
            echo '<tr><td>' . esc_html($i['invoice_no']) . '</td><td>' . esc_html($i['issue_date']) . '</td><td>' . number_format((float) $i['total'], 2) . '</td><td>' . esc_html($i['status']) . '</td><td><code>' . esc_html((string) $i['tax_invoice_uid']) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

final class HrmPage
{
    public static function render(): void
    {
        $rows = EmployeeService::all();
        echo '<div class="wrap"><h1>Employees</h1><table class="widefat"><thead><tr><th>Code</th><th>Name</th><th>Position</th><th>Base Salary</th><th>Hire</th></tr></thead><tbody>';
        foreach ($rows as $e) {
            echo '<tr><td>' . esc_html($e['national_code']) . '</td><td>' . esc_html($e['full_name']) . '</td><td>' . esc_html((string) $e['position']) . '</td><td>' . number_format((float) $e['base_salary'], 2) . '</td><td>' . esc_html($e['hire_date']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

final class WorkflowsPage
{
    public static function render(): void
    {
        $rows = Db::getResults('workflows', [], 'id DESC');
        echo '<div class="wrap"><h1>Workflows</h1><table class="widefat"><thead><tr><th>Name</th><th>Trigger</th><th>Active</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html($r['name']) . '</td><td><code>' . esc_html($r['trigger_event']) . '</code></td><td>' . ((int) $r['is_active'] ? 'v' : '—') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

final class AuditPage
{
    public static function render(): void
    {
        $rows = Logger::tail(200);
        echo '<div class="wrap"><h1>Audit Log (immutable)</h1><table class="widefat"><thead><tr><th>ID</th><th>Object</th><th>Action</th><th>Actor</th><th>When</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . (int) $r['id'] . '</td><td><code>' . esc_html($r['object']) . '#' . (int) ($r['object_id'] ?? 0) . '</code></td><td>' . esc_html($r['action']) . '</td><td>' . (int) ($r['actor_id'] ?? 0) . '</td><td>' . esc_html($r['created_at']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
