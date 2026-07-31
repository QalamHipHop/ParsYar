<?php
declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Hrm;

use Enterprise\Modules\Hrm\PayrollService;
use Enterprise\Str;
use PHPUnit\Framework\TestCase;

final class PayrollServiceTest extends TestCase
{
    public function testCalcTaxBelowThreshold(): void
    {
        // 30M IRT → first bracket (0%).
        $tax = self::call('calcTax', 30_000_000);
        self::assertSame(0.0, $tax);
    }

    public function testCalcTaxSecondBracket(): void
    {
        // 75M IRT → 25M in second bracket at 10% = 2.5M
        $tax = self::call('calcTax', 75_000_000);
        self::assertEqualsWithDelta(2_500_000, $tax, 0.5);
    }

    public function testCalcTaxProgressive(): void
    {
        // 300M IRT:
        //   50M @ 0% = 0
        //   50M @ 10% = 5M
        //  100M @ 15% = 15M
        //  100M @ 20% = 20M
        //  total = 40M
        $tax = self::call('calcTax', 300_000_000);
        self::assertEqualsWithDelta(40_000_000, $tax, 0.5);
    }

    public function testCalcTaxZero(): void
    {
        self::assertSame(0.0, self::call('calcTax', 0));
        self::assertSame(0.0, self::call('calcTax', -100));
    }

    public function testInsuranceRate(): void
    {
        self::assertSame(0.07, PayrollService::INSURANCE_RATE);
    }

    public function testTaxBracketsStructure(): void
    {
        self::assertCount(5, PayrollService::TAX_BRACKETS);
        self::assertSame(0.0, PayrollService::TAX_BRACKETS[0][1]);
        self::assertSame(0.25, PayrollService::TAX_BRACKETS[4][1]);
    }

    public function testFormatPeriodContainsYear(): void
    {
        $label = self::call('formatPeriod', 1404, 1);
        self::assertStringContainsString('1404', $label);
    }

    public function testStatusConstants(): void
    {
        self::assertSame('draft',     PayrollService::STATUS_DRAFT);
        self::assertSame('processing',PayrollService::STATUS_PROCESSING);
        self::assertSame('approved',  PayrollService::STATUS_APPROVED);
        self::assertSame('paid',      PayrollService::STATUS_PAID);
        self::assertSame('cancelled', PayrollService::STATUS_CANCELLED);
    }

    private static function call(string $method, ...$args)
    {
        $r = new \ReflectionClass(PayrollService::class);
        $m = $r->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }
}
