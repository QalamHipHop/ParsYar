<?php
/**
 * String Utilities — Levenshtein, Jaro-Winkler, Soundex, Normalize
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

final class Str
{
    /**
     * فاصلهٔ Levenshtein بین دو رشته.
     * O(m*n) time, O(min(m,n)) space.
     */
    public static function levenshtein(string $a, string $b): int
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === $b) {
            return 0;
        }
        if ($a === '') {
            return mb_strlen($b, 'UTF-8');
        }
        if ($b === '') {
            return mb_strlen($a, 'UTF-8');
        }

        $aLen = mb_strlen($a, 'UTF-8');
        $bLen = mb_strlen($b, 'UTF-8');

        $prev = range(0, $bLen);
        $curr = [];

        for ($i = 1; $i <= $aLen; $i++) {
            $curr[0] = $i;
            $aChar = mb_substr($a, $i - 1, 1, 'UTF-8');
            for ($j = 1; $j <= $bLen; $j++) {
                $cost = ($aChar === mb_substr($b, $j - 1, 1, 'UTF-8')) ? 0 : 1;
                $curr[$j] = min(
                    $curr[$j - 1] + 1,        // insertion
                    $prev[$j] + 1,             // deletion
                    $prev[$j - 1] + $cost      // substitution
                );
            }
            $tmp = $prev;
            $prev = $curr;
            $curr = $tmp;
        }
        return $prev[$bLen];
    }

    /**
     * شباهت Levenshtein به صورت 0-1 (1 = یکسان).
     */
    public static function levenshteinSimilarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' && $b === '') {
            return 1.0;
        }
        $maxLen = max(mb_strlen($a, 'UTF-8'), mb_strlen($b, 'UTF-8'));
        if ($maxLen === 0) {
            return 1.0;
        }
        return 1.0 - (self::levenshtein($a, $b) / $maxLen);
    }

    /**
     * شباهت Jaro.
     */
    public static function jaro(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === $b) {
            return 1.0;
        }
        $aLen = mb_strlen($a, 'UTF-8');
        $bLen = mb_strlen($b, 'UTF-8');
        if ($aLen === 0 || $bLen === 0) {
            return 0.0;
        }

        $matchDistance = (int) floor(max($aLen, $bLen) / 2) - 1;
        if ($matchDistance < 0) {
            $matchDistance = 0;
        }

        $aMatches = array_fill(0, $aLen, false);
        $bMatches = array_fill(0, $bLen, false);
        $matches = 0;
        $transpositions = 0;

        for ($i = 0; $i < $aLen; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $bLen);
            $aChar = mb_substr($a, $i, 1, 'UTF-8');
            for ($j = $start; $j < $end; $j++) {
                if ($bMatches[$j]) {
                    continue;
                }
                if ($aChar !== mb_substr($b, $j, 1, 'UTF-8')) {
                    continue;
                }
                $aMatches[$i] = true;
                $bMatches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $k = 0;
        for ($i = 0; $i < $aLen; $i++) {
            if (!$aMatches[$i]) {
                continue;
            }
            while (!$bMatches[$k]) {
                $k++;
            }
            if (mb_substr($a, $i, 1, 'UTF-8') !== mb_substr($b, $k, 1, 'UTF-8')) {
                $transpositions++;
            }
            $k++;
        }

        return (($matches / $aLen) + ($matches / $bLen) + (($matches - ($transpositions / 2)) / $matches)) / 3.0;
    }

    /**
     * شباهت Jaro-Winkler (با bonus برای پیشوند مشترک).
     */
    public static function jaroWinkler(string $a, string $b, float $prefixScale = 0.1, int $maxPrefix = 4): float
    {
        $jaro = self::jaro($a, $b);
        if ($jaro < 0.7) {
            return $jaro;
        }
        $aN = self::normalize($a);
        $bN = self::normalize($b);
        $prefix = 0;
        $max = min($maxPrefix, mb_strlen($aN, 'UTF-8'), mb_strlen($bN, 'UTF-8'));
        for ($i = 0; $i < $max; $i++) {
            if (mb_substr($aN, $i, 1, 'UTF-8') === mb_substr($bN, $i, 1, 'UTF-8')) {
                $prefix++;
            } else {
                break;
            }
        }
        return $jaro + ($prefix * $prefixScale * (1.0 - $jaro));
    }

    /**
     * Soundex برای فارسی — نسخهٔ ساده (حروف اول).
     * جایگزین: استفاده از الگوریتم‌های اختصاصی فارسی مانند جامد.
     */
    public static function soundexFa(string $s): string
    {
        $s = self::normalizeFa($s);
        if ($s === '') {
            return '0000';
        }
        $first = mb_substr($s, 0, 1, 'UTF-8');
        $code = self::soundexTableFa($first);
        $result = [$first];
        $lastCode = $code;
        $len = mb_strlen($s, 'UTF-8');
        for ($i = 1; $i < $len; $i++) {
            $char = mb_substr($s, $i, 1, 'UTF-8');
            $c = self::soundexTableFa($char);
            if ($c !== '0' && $c !== $lastCode) {
                $result[] = $c;
            }
            $lastCode = $c;
            if (count($result) >= 4) {
                break;
            }
        }
        while (count($result) < 4) {
            $result[] = '0';
        }
        return implode('', $result);
    }

    /**
     * نرمال‌سازی برای مقایسه: lowercase + حذف فاصله + یکدست‌سازی حروف عربی/فارسی.
     */
    public static function normalize(string $s): string
    {
        $s = trim($s);
        $s = mb_strtolower($s, 'UTF-8');
        // یکدست‌سازی حروف
        $s = str_replace(
            ['ي', 'ك', 'ؤ', 'إ', 'أ', 'ة', 'ٱ', 'ٲ', 'ٳ', 'ٵ', 'ٶ', 'ٷ', 'ٸ', 'ٹ', 'ٺ', 'ٻ', 'ټ', 'ٽ', 'ٿ'],
            ['ی', 'ک', 'و', 'ا', 'ا', 'ه', 'ا', 'ا', 'ا', 'ا', 'و', 'و', 'و', 'ت', 'ت', 'ب', 'ت', 'ت', 'ت'],
            $s
        );
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim((string) $s);
    }

    /**
     * نرمال‌سازی فارسی: حذف ارقام، علائم، فاصله.
     */
    public static function normalizeFa(string $s): string
    {
        $s = self::normalize($s);
        $s = preg_replace('/[^\x{0600}-\x{06FF}\x{FB8A}\x{067E}\x{0686}\x{0698}\x{06AF}\x{200C}]/u', '', (string) $s);
        return trim((string) $s);
    }

    /**
     * بررسی شباهت دو نام فارسی با در نظر گرفتن ترتیب.
     */
    public static function nameSimilarity(string $a, string $b): float
    {
        $aN = self::normalizeFa($a);
        $bN = self::normalizeFa($b);
        if ($aN === '' || $bN === '') {
            return 0.0;
        }
        if ($aN === $bN) {
            return 1.0;
        }
        return self::jaroWinkler($aN, $bN);
    }

    /**
     * آیا دو نام احتمالاً یک فرد هستند؟ با آستانهٔ پیش‌فرض 0.85.
     */
    public static function isSameName(string $a, string $b, float $threshold = 0.85): bool
    {
        return self::nameSimilarity($a, $b) >= $threshold;
    }

    /**
     * استخراج اولین کلمهٔ معنادار از نام (برای مقایسهٔ سریع).
     */
    public static function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', self::normalizeFa($fullName));
        return $parts[0] ?? '';
    }

    /**
     * استخراج نام خانوادگی (آخرین کلمه).
     */
    public static function lastName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', self::normalizeFa($fullName));
        $parts = array_values(array_filter($parts));
        return end($parts) ?: '';
    }

    private static function soundexTableFa(string $char): string
    {
        $map = [
            'ا' => '0', 'ب' => '1', 'پ' => '1', 'ت' => '2', 'ث' => '2', 'ج' => '3', 'چ' => '3',
            'ح' => '4', 'خ' => '4', 'د' => '5', 'ذ' => '5', 'ر' => '6', 'ز' => '6', 'ژ' => '6',
            'س' => '7', 'ش' => '7', 'ص' => '7', 'ض' => '7', 'ط' => '8', 'ظ' => '8', 'ع' => '9',
            'غ' => '9', 'ف' => '1', 'ق' => '1', 'ک' => '2', 'گ' => '2', 'ل' => '3', 'م' => '4',
            'ن' => '5', 'و' => '6', 'ه' => '7', 'ی' => '8', 'ء' => '0',
        ];
        return $map[$char] ?? '0';
    }
}
