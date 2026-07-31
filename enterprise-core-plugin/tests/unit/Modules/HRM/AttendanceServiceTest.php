<?php
declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Hrm;

use Enterprise\Modules\Hrm\AttendanceService;
use PHPUnit\Framework\TestCase;

final class AttendanceServiceTest extends TestCase
{
    public function testStatusesIncludeAllRequired(): void
    {
        $s = AttendanceService::STATUSES;
        self::assertContains('present',  $s);
        self::assertContains('late',     $s);
        self::assertContains('absent',   $s);
        self::assertContains('leave',    $s);
        self::assertContains('holiday',  $s);
        self::assertContains('remote',   $s);
    }

    public function testComputeWorkMinutesSubtractLunch(): void
    {
        $m = new \ReflectionMethod(AttendanceService::class, 'computeWorkMinutes');
        $m->setAccessible(true);

        // 8:00 → 17:00 = 9 hours = 540 min − 30 lunch = 510
        self::assertSame(510, $m->invoke(null, '08:00:00', '17:00:00'));
        // 8:00 → 13:00 = 5 hours = 300 min, no lunch deduction
        self::assertSame(300, $m->invoke(null, '08:00:00', '13:00:00'));
    }

    public function testComputeLate(): void
    {
        $m = new \ReflectionMethod(AttendanceService::class, 'computeLate');
        $m->setAccessible(true);
        // 8:30 with shift 08:00 → 30 min late
        self::assertSame(30, $m->invoke(null, '08:30:00'));
        // 7:50 with shift 08:00 → 0 (early)
        self::assertSame(0, $m->invoke(null, '07:50:00'));
        // 9:00 with shift 08:00 → 60
        self::assertSame(60, $m->invoke(null, '09:00:00'));
    }

    public function testComputeOvertime(): void
    {
        $m = new \ReflectionMethod(AttendanceService::class, 'computeOvertime');
        $m->setAccessible(true);
        // 17:00 with shift end 17:00 → 0
        self::assertSame(0, $m->invoke(null, '08:00:00', '17:00:00'));
        // 18:00 with shift end 17:00 → 60
        self::assertSame(60, $m->invoke(null, '08:00:00', '18:00:00'));
        // 19:30 with shift end 17:00 → 150
        self::assertSame(150, $m->invoke(null, '08:00:00', '19:30:00'));
    }

    public function testDetectAnomaly(): void
    {
        $m = new \ReflectionMethod(AttendanceService::class, 'detectAnomaly');
        $m->setAccessible(true);
        self::assertTrue($m->invoke(null, '17:00:00', '08:00:00', 'present'));
        self::assertTrue($m->invoke(null, null, '17:00:00', 'present'));
        self::assertTrue($m->invoke(null, '08:00:00', null, 'present'));
        self::assertFalse($m->invoke(null, '08:00:00', '17:00:00', 'present'));
        self::assertFalse($m->invoke(null, null, null, 'absent'));
    }

    public function testDeriveStatus(): void
    {
        $m = new \ReflectionMethod(AttendanceService::class, 'deriveStatus');
        $m->setAccessible(true);
        self::assertSame('present', $m->invoke(null, '07:55:00', '08:00'));
        self::assertSame('present', $m->invoke(null, '08:00:00', '08:00'));
        self::assertSame('late',    $m->invoke(null, '08:01:00', '08:00'));
    }
}
