<?php
/**
 * Jalali (شمسی) Calendar Engine
 *
 * پیاده‌سازی الگوریتم ۳۳ ساله (ساده، سریع، دقیق برای محدودهٔ ۱۲۴۴–۱۴۷۳ شمسی).
 * برای محدودهٔ وسیع‌تر از الگوریتم ۲۸۲۰ ساله استفاده می‌شود.
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise;

final class Jalali
{
    public const ALGO_33   = '33';
    public const ALGO_2820 = '2820';

    /**
     * بررسی سال کبیسه.
     */
    public static function isLeap(int $year, string $algo = self::ALGO_33): bool
    {
        if ($algo === self::ALGO_2820) {
            return ((($year - 474) % 2820 + 474 + 38) * 682) % 2816 < 682;
        }
        // الگوریتم ۳۳ ساله
        return in_array($year % 33, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    /**
     * تعداد روزهای ماه.
     */
    public static function daysInMonth(int $year, int $month, string $algo = self::ALGO_33): int
    {
        if ($month < 1 || $month > 12) {
            return 0;
        }
        if ($month <= 6) {
            return 31;
        }
        if ($month <= 11) {
            return 30;
        }
        // اسفند
        return self::isLeap($year, $algo) ? 30 : 29;
    }

    /**
     * تبدیل میلادی → شمسی.
     * خروجی: ['y'=>1404,'m'=>8,'d'=>23]
     */
    public static function fromGregorian(string $gregorianDate, string $algo = self::ALGO_33): array
    {
        $ts = is_numeric($gregorianDate) ? (int) $gregorianDate : strtotime($gregorianDate);
        if ($ts === false) {
            throw new \InvalidArgumentException('Invalid Gregorian date: ' . $gregorianDate);
        }
        [$gy, $gm, $gd] = explode('-', date('Y-n-j', $ts));

        if ($algo === self::ALGO_2820) {
            return self::fromGregorian2820((int) $gy, (int) $gm, (int) $gd);
        }
        return self::fromGregorian33((int) $gy, (int) $gm, (int) $gd);
    }

    /**
     * تبدیل شمسی → میلادی.
     * خروجی: 'Y-m-d'
     */
    public static function toGregorian(int $jy, int $jm, int $jd, string $algo = self::ALGO_33): string
    {
        if ($algo === self::ALGO_2820) {
            return self::toGregorian2820($jy, $jm, $jd);
        }
        return self::toGregorian33($jy, $jm, $jd);
    }

    /**
     * قالب‌بندی تاریخ شمسی.
     */
    public static function format(int $jy, int $jm, int $jd, string $format = 'Y/m/d'): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        $map = [
            'Y' => str_pad((string) $jy, 4, '0', STR_PAD_LEFT),
            'y' => substr((string) $jy, -2),
            'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'n' => (string) $jm,
            'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
            'j' => (string) $jd,
            'F' => $months[$jm] ?? '',
            'M' => mb_substr($months[$jm] ?? '', 0, 3),
        ];

        $out = '';
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $c = $format[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $out .= $format[++$i];
                continue;
            }
            $out .= $map[$c] ?? $c;
        }
        return $out;
    }

    /**
     * تاریخ امروز به شمسی.
     */
    public static function today(string $format = 'Y/m/d', string $algo = self::ALGO_33): string
    {
        $parts = self::fromGregorian(date('Y-m-d'), $algo);
        return self::format($parts['y'], $parts['m'], $parts['d'], $format);
    }

    /**
     * تعیین اولین روز هفته (شنبه در ایران).
     */
    public static function weekStart(int $jy, int $jm, int $jd): int
    {
        $ts = strtotime(self::toGregorian($jy, $jm, $jd));
        // PHP: Sunday=0, Saturday=6
        $w = (int) date('w', $ts);
        // تبدیل به شنبه=0، یکشنبه=1، ...
        return ($w + 1) % 7;
    }

    // -----------------------------------------------------------------
    // الگوریتم ۳۳ ساله
    // -----------------------------------------------------------------
    private static function fromGregorian33(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4)
              - (int) (($gy2 + 99) / 100) + (int) (($gy2 + 399) / 400)
              + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int) ($days / 12053));
        $days %= 12053;
        $jy += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $jm = ($days < 186) ? 1 + (int) ($days / 31) : 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days < 186) ? $days % 31 : ($days - 186) % 30);
        return ['y' => $jy, 'm' => $jm, 'd' => $jd];
    }

    private static function toGregorian33(int $jy, int $jm, int $jd): string
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (int) (($jy / 33) * 8)
              + (int) ((($jy % 33) + 3) / 4) + $jd
              + (($jm < 7) ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186);
        $gy = 400 * (int) ($days / 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * (int) (--$days / 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $g_d_m = [0, 31, ($gy % 4 === 0 && $gy % 100 !== 0 || $gy % 400 === 0) ? 29 : 28,
                  31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 1;
        while ($gm < 12 && $days >= $g_d_m[$gm]) {
            $days -= $g_d_m[$gm++];
        }
        return sprintf('%04d-%02d-%02d', $gy, $gm, $days + 1);
    }

    // -----------------------------------------------------------------
    // الگوریتم ۲۸۲۰ ساله
    // -----------------------------------------------------------------
    private static function fromGregorian2820(int $gy, int $gm, int $gd): array
    {
        $days = (int) ((($gy + 399) * 365.25) - 397.6 * (int) (($gy + 399) / 100) + $gd
                - 115860.4 + 367 * $gm / 12 - 30.4 * (int) ($gm / 2) - 1);
        $days = (int) ($days - (int) ($days / 36525) * 0.3);
        $jy = $days / 365.25;
        $days = $days - 365.25 * (int) $jy;
        $jm = ($days < 186) ? 1 + $days / 31 : 7 + ($days - 186) / 30;
        $jd = ($days < 186) ? 1 + $days % 31 : 1 + ($days - 186) % 30;
        return ['y' => (int) $jy + 1, 'm' => (int) $jm, 'd' => (int) $jd];
    }

    private static function toGregorian2820(int $jy, int $jm, int $jd): string
    {
        $days = 365 * ($jy - 1) + (int) (($jy - 1) / 2820) * 686 + (int) ((($jy - 1) % 2820 + 474) / 2820) * 686
              - (int) ((($jy - 1) % 2820 + 474) / 2820)
              + (int) ((($jy - 1) % 2820 + 474) % 2820 / 2820)
              - (int) ((($jy - 1) % 2820 + 474) % 2820)
              - (int) ((($jy - 1) % 2820 + 474) % 2820 * 0.3)
              + (($jm < 7) ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186) + $jd - 1;
        $gy = 400 * (int) ($days / 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * (int) (--$days / 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $g_d_m = [0, 31, ($gy % 4 === 0 && $gy % 100 !== 0 || $gy % 400 === 0) ? 29 : 28,
                  31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 1;
        while ($gm < 12 && $days >= $g_d_m[$gm]) {
            $days -= $g_d_m[$gm++];
        }
        return sprintf('%04d-%02d-%02d', $gy, $gm, $days + 1);
    }
}
