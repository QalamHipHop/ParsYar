<?php
declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Hrm;

use Enterprise\Modules\Hrm\LeaveService;
use PHPUnit\Framework\TestCase;

final class LeaveServiceTest extends TestCase
{
    public function testCountDaysFullRange(): void
    {
        self::assertSame(5, LeaveService::countDays('2025-01-01', '2025-01-05'));
        self::assertSame(1, LeaveService::countDays('2025-01-01', '2025-01-01'));
    }

    public function testCountDaysHalf(): void
    {
        $d = LeaveService::countDays('2025-01-01', '2025-01-10', true);
        self::assertSame(5, $d);
    }

    public function testTypesIncludesCommonOnes(): void
    {
        self::assertContains('annual',    LeaveService::TYPES);
        self::assertContains('sick',      LeaveService::TYPES);
        self::assertContains('maternity', LeaveService::TYPES);
        self::assertContains('unpaid',    LeaveService::TYPES);
    }

    public function testStatusesLifecycle(): void
    {
        $s = LeaveService::STATUSES;
        self::assertSame('pending',   $s[0]);
        self::assertSame('approved',  $s[1]);
        self::assertSame('rejected',  $s[2]);
        self::assertSame('cancelled', $s[3]);
    }
}
