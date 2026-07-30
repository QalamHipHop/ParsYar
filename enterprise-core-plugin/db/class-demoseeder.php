<?php
declare(strict_types=1);

namespace Enterprise\Db;

defined('ABSPATH') || exit;

use Enterprise\Modules\Objects\ObjectEngine;
use Enterprise\Modules\Erp\InventoryService;
use Enterprise\Modules\Erp\InvoiceService;
use Enterprise\Modules\Crm\LeadService;
use Enterprise\Modules\Hrm\EmployeeService;

/**
 * داده‌های دمو برای تست فوری سیستم.
 */
final class DemoSeeder
{
    public static function run(): void
    {
        self::seedAccounts();
        self::seedLeads();
        self::seedProducts();
        self::seedInvoices();
        self::seedEmployees();
        self::seedSampleRecords();
    }

    private static function seedAccounts(): void
    {
        $account = ObjectEngine::findObjectByApiName('account');
        if (!$account) {
            return;
        }
        $names = [
            ['name' => 'شرکت پارس گستر', 'industry' => 'فناوری', 'website' => 'https://parsGostar.ir', 'phone' => '021-88776655', 'email' => 'info@parsgostar.ir', 'tax_id' => '12345678901'],
            ['name' => 'گروه صنعتی آریا',  'industry' => 'تولیدی',  'website' => 'https://aria.co.ir',  'phone' => '021-33445566', 'email' => 'sales@aria.co.ir',  'tax_id' => '98765432109'],
            ['name' => 'بازرگانی سپهر',     'industry' => 'بازرگانی','website' => 'https://sepehr.trade', 'phone' => '021-77889900', 'email' => 'hello@sepehr.trade', 'tax_id' => '11122233344'],
        ];
        foreach ($names as $n) {
            if (self::recordExists($account['id'], $n['name'])) {
                continue;
            }
            ObjectEngine::createRecord((int) $account['id'], $n);
        }
    }

    private static function seedLeads(): void
    {
        $seeds = [
            ['full_name' => 'علی محمدی',   'email' => 'ali@example.com', 'phone' => '09121234567', 'source' => 'web_form'],
            ['full_name' => 'مریم احمدی',  'email' => 'maryam@x.co',     'phone' => '09351234567', 'source' => 'referral'],
            ['full_name' => 'رضا کریمی',   'email' => 'reza@x.co',       'phone' => '',            'source' => 'campaign'],
        ];
        foreach ($seeds as $s) {
            LeadService::create($s);
        }
    }

    private static function seedProducts(): void
    {
        $products = [
            ['sku' => 'SKU-001', 'name' => 'لپ‌تاپ اداری',    'cost' => 15000000, 'price' => 18500000, 'stock' => 25],
            ['sku' => 'SKU-002', 'name' => 'مانیتور ۲۷ اینچ', 'cost' => 4500000,  'price' => 5800000,  'stock' => 40],
            ['sku' => 'SKU-003', 'name' => 'صندلی ارگونومیک', 'cost' => 3200000,  'price' => 4200000,  'stock' => 15],
        ];
        foreach ($products as $p) {
            try {
                InventoryService::addProduct($p);
            } catch (\Throwable $e) {
                // duplicate
            }
        }
    }

    private static function seedInvoices(): void
    {
        for ($i = 0; $i < 3; $i++) {
            InvoiceService::create([
                'issue_date' => gmdate('Y-m-d', strtotime("-{$i} days")),
                'subtotal'   => 5000000,
                'tax'        => 450000,
            ]);
        }
    }

    private static function seedEmployees(): void
    {
        $emps = [
            ['national_code' => '0012345678', 'full_name' => 'نگار رضایی', 'base_salary' => 18000000, 'hire_date' => '2022-03-15', 'position' => 'مدیر محصول'],
            ['national_code' => '0022345678', 'full_name' => 'حسین مرادی',  'base_salary' => 22000000, 'hire_date' => '2021-08-01', 'position' => 'سرپرست فنی'],
            ['national_code' => '0032345678', 'full_name' => 'زهرا کاظمی',  'base_salary' => 15000000, 'hire_date' => '2023-01-20', 'position' => 'حسابدار'],
        ];
        foreach ($emps as $e) {
            try {
                EmployeeService::create($e);
            } catch (\Throwable $e) {
                // duplicate
            }
        }
    }

    private static function seedSampleRecords(): void
    {
        $opportunity = ObjectEngine::findObjectByApiName('opportunity');
        if ($opportunity) {
            ObjectEngine::createRecord((int) $opportunity['id'], [
                'name'   => 'فروش سالانه پارس گستر',
                'amount' => 250000000,
                'stage'  => 'negotiation',
            ]);
        }
        $project = ObjectEngine::findObjectByApiName('project');
        if ($project) {
            ObjectEngine::createRecord((int) $project['id'], [
                'name'     => 'پیاده‌سازی ERP',
                'budget'   => 800000000,
                'status'   => 'active',
                'start_at' => gmdate('Y-m-d'),
            ]);
        }
    }

    private static function recordExists(int $objectId, string $name): bool
    {
        $rows = ObjectEngine::listRecords($objectId, ['limit' => 1000, 'offset' => 0]);
        foreach ($rows as $r) {
            if (($r['data']['name'] ?? '') === $name) {
                return true;
            }
        }
        return false;
    }
}
