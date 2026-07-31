<?php
/**
 * Tests for ReportService.
 *
 * @package Enterprise\Tests\Unit\Modules\Reports
 */

declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Reports;

use PHPUnit\Framework\TestCase;
use Enterprise\Modules\Reports\ReportService;

final class ReportServiceTest extends TestCase
{
    public function testSourcesContainExpectedEntities(): void
    {
        $this->assertArrayHasKey('contacts',     ReportService::SOURCES);
        $this->assertArrayHasKey('organizations',ReportService::SOURCES);
        $this->assertArrayHasKey('leads',        ReportService::SOURCES);
        $this->assertArrayHasKey('deals',        ReportService::SOURCES);
        $this->assertArrayHasKey('invoices',     ReportService::SOURCES);
        $this->assertArrayHasKey('employees',    ReportService::SOURCES);
        $this->assertArrayHasKey('attendance',   ReportService::SOURCES);
    }

    public function testOpsAreSupported(): void
    {
        $this->assertContains('==',         ReportService::OPS);
        $this->assertContains('contains',   ReportService::OPS);
        $this->assertContains('in',         ReportService::OPS);
    }

    public function testNormalizeConfigStripsUnknownFields(): void
    {
        $cfg = ReportService::normalizeConfig([
            'data_source' => 'contacts',
            'filters'     => [
                ['field' => 'city', 'op' => '==', 'value' => 'Tehran'],
                ['field' => 'evil', 'op' => 'DROP', 'value' => 'x'], // اپراتور نامعتبر
            ],
            'group_by'    => ['city', 123, null], // فقط string ها
            'metrics'     => [
                ['agg' => 'count', 'col' => '*', 'alias' => 'c'],
                ['agg' => 'evil',  'col' => 'x', 'alias' => 'e'], // aggregation نامعتبر
            ],
            'sort_by'     => 'city',
            'sort_dir'    => 'desc',
            'limit'       => 50,
        ]);
        $this->assertCount(1, $cfg['filters']);
        $this->assertCount(1, $cfg['metrics']);
        $this->assertSame(['city'], $cfg['group_by']);
        $this->assertSame(50, $cfg['limit']);
        $this->assertSame('desc', $cfg['sort_dir']);
    }

    public function testNormalizeConfigClampsLimit(): void
    {
        $cfg = ReportService::normalizeConfig(['limit' => 99999]);
        $this->assertSame(5000, $cfg['limit']);
        $cfg = ReportService::normalizeConfig(['limit' => 0]);
        $this->assertSame(1, $cfg['limit']);
    }

    public function testTemplatesExist(): void
    {
        $t = ReportService::templates();
        $this->assertNotEmpty($t);
        foreach ($t as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('data_source', $row);
            $this->assertArrayHasKey('config', $row);
        }
    }

    public function testToCsvReturnsEmptyForEmptyResult(): void
    {
        $this->assertSame('', ReportService::toCsv(['rows' => []]));
        $this->assertSame('', ReportService::toCsv([]));
    }

    public function testToCsvEncodesHeadersAndRows(): void
    {
        $csv = ReportService::toCsv([
            'rows' => [
                ['city' => 'تهران',   'count' => 12],
                ['city' => 'اصفهان',  'count' => 7],
            ],
        ]);
        $this->assertStringContainsString('city,count', $csv);
        $this->assertStringContainsString('"تهران"', $csv);
        $this->assertStringContainsString('"اصفهان"', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }
}
