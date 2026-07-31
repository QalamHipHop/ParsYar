<?php
declare(strict_types=1);

namespace Enterprise\Tests\Unit\Core;

use Enterprise\Jalali;
use PHPUnit\Framework\TestCase;

final class JalaliTest extends TestCase
{
    /**
     * @dataProvider leapYearProvider
     */
    public function testIsLeap(int $year, bool $expected): void
    {
        self::assertSame($expected, Jalali::isLeap($year));
    }

    public static function leapYearProvider(): array
    {
        // 33-year cycle leap years.
        return [
            [1403, true],
            [1404, false],
            [1407, true],
            [1408, true],
            [1375, false],
            [1399, true],
            [1400, false],
        ];
    }

    public function testToGregorianFarvardin1(): void
    {
        $greg = Jalali::toGregorian(1404, 1, 1);
        self::assertSame('2025-03-21', $greg);
    }

    public function testToGregorianEsfand29(): void
    {
        // 1403 is a leap year in the 33-year cycle → Esfand has 30 days.
        $greg = Jalali::toGregorian(1403, 12, 30);
        self::assertSame('2025-03-20', $greg);
    }

    public function testFromGregorian(): void
    {
        $parts = Jalali::fromGregorian('2025-03-21');
        self::assertSame(1404, (int) $parts['y']);
        self::assertSame(1, (int) $parts['m']);
        self::assertSame(1, (int) $parts['d']);
    }

    public function testRoundTrip(): void
    {
        $cases = [
            [1404, 1, 1],
            [1404, 6, 31],
            [1404, 12, 29],
            [1403, 12, 30], // leap-year last day
            [1370, 5, 17],
        ];

        foreach ($cases as [$y, $m, $d]) {
            $greg = Jalali::toGregorian($y, $m, $d);
            $back = Jalali::fromGregorian($greg);
            self::assertSame($y, (int) $back['y'], "year mismatch for {$y}-{$m}-{$d}");
            self::assertSame($m, (int) $back['m'], "month mismatch for {$y}-{$m}-{$d}");
            self::assertSame($d, (int) $back['d'], "day mismatch for {$y}-{$m}-{$d}");
        }
    }

    public function testFormat(): void
    {
        self::assertSame('1404/01/01', Jalali::format('Y/m/d', 1404, 1, 1));
        self::assertSame('۱۴۰۴-۰۱-۰۱', Jalali::format('Y-m-d', 1404, 1, 1, true));
    }

    public function testDaysInMonth(): void
    {
        self::assertSame(31, Jalali::daysInMonth(1404, 1));  // Farvardin
        self::assertSame(31, Jalali::daysInMonth(1404, 12)); // Esfand in non-leap
        self::assertSame(30, Jalali::daysInMonth(1403, 12)); // Esfand in leap
    }
}
