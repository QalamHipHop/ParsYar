<?php
/**
 * Static Data Loader — دسترسی به داده‌های ایستا (شهرها، بانک‌ها، اپراتورها، ارزها)
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

final class Data
{
    private const CACHE_GROUP = 'parsyar_static_data';
    private const DATA_DIR = 'assets/data';

    /**
     * بارگذاری فایل داده با cache خودکار.
     */
    public static function load(string $key): array
    {
        $cache_key = self::CACHE_GROUP . '_' . $key;
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $path = ENTERPRISE_PLUGIN_DIR . self::DATA_DIR . '/' . $key . '.php';
        if (!file_exists($path)) {
            return [];
        }

        $data = require $path;
        if (!is_array($data)) {
            return [];
        }

        wp_cache_set($cache_key, $data, self::CACHE_GROUP, DAY_IN_SECONDS);
        return $data;
    }

    /**
     * جستجو در آرایهٔ داده بر اساس فیلد.
     */
    public static function find(array $rows, string $field, mixed $value): ?array
    {
        foreach ($rows as $row) {
            if (($row[$field] ?? null) === $value) {
                return $row;
            }
        }
        return null;
    }

    /**
     * فیلتر ساده بر اساس شرط callback.
     */
    public static function filter(array $rows, callable $predicate): array
    {
        return array_values(array_filter($rows, $predicate));
    }

    // ---- Helpers with semantic names ----

    public static function provinces(): array
    {
        return self::load('iran-provinces');
    }

    public static function banks(): array
    {
        return self::load('iran-banks');
    }

    public static function mobilePrefixes(): array
    {
        return self::load('mobile-prefixes');
    }

    public static function currencies(): array
    {
        return self::load('currencies');
    }

    public static function languages(): array
    {
        return self::load('languages');
    }

    public static function industries(): array
    {
        return self::load('industries');
    }

    /**
     * تشخیص اپراتور موبایل بر اساس prefix.
     */
    public static function detectMobileOperator(string $number): ?array
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($number, '0098')) {
            $number = '0' . substr($number, 4);
        } elseif (str_starts_with($number, '98') && strlen($number) === 12) {
            $number = '0' . substr($number, 2);
        }
        $prefix = substr($number, 0, 4);
        return self::find(self::mobilePrefixes(), 'prefix', $prefix);
    }

    /**
     * تشخیص بانک بر اساس شماره کارت (BIN).
     */
    public static function detectBankByCard(string $card): ?array
    {
        $card = preg_replace('/[^0-9]/', '', $card);
        if (strlen($card) !== 16) {
            return null;
        }
        $bin = substr($card, 0, 6);
        foreach (self::banks() as $bank) {
            foreach ($bank['bin_prefixes'] as $bp) {
                if (str_starts_with($bin, $bp) || $bp === $bin) {
                    return $bank;
                }
            }
        }
        return null;
    }

    /**
     * تشخیص بانک بر اساس شماره شبا.
     */
    public static function detectBankBySheba(string $sheba): ?array
    {
        $sheba = strtoupper(preg_replace('/\s+/', '', $sheba));
        if (strlen($sheba) !== 26 || substr($sheba, 0, 2) !== 'IR') {
            return null;
        }
        $prefix = substr($sheba, 4, 3); // سه رقم بعد از IR
        foreach (self::banks() as $bank) {
            if ($bank['sheba_prefix'] === '0' . $prefix || $bank['sheba_prefix'] === $prefix) {
                return $bank;
            }
        }
        return null;
    }

    /**
     * دریافت نام استان بر اساس کد.
     */
    public static function provinceName(string $code, string $lang = 'fa'): ?string
    {
        $p = self::find(self::provinces(), 'code', $code);
        if (!$p) {
            return null;
        }
        return $p[('fa' === $lang) ? 'name_fa' : 'name_en'] ?? null;
    }

    /**
     * تبدیل ارز (نرخ ایستا، در پروداکشن باید به API وصل بشه).
     */
    public static function convertCurrency(float $amount, string $from, string $to, ?array $rates = null): float
    {
        if ($from === $to) {
            return $amount;
        }
        $rates ??= self::currencies();
        $fromRate = self::find($rates, 'code', $from)['rate_to_irt'] ?? null;
        $toRate   = self::find($rates, 'code', $to)['rate_to_irt'] ?? null;
        if ($fromRate === null || $toRate === null || $fromRate <= 0) {
            return $amount;
        }
        $inIrt = $amount * (float) $fromRate;
        return $inIrt / (float) $toRate;
    }

    /**
     * اطلاعات یک زبان بر اساس کد.
     */
    public static function language(string $code): ?array
    {
        return self::find(self::languages(), 'code', $code);
    }

    /**
     * اطلاعات یک صنعت بر اساس کد.
     */
    public static function industry(string $code): ?array
    {
        return self::find(self::industries(), 'code', $code);
    }

    /**
     * پاک کردن کش (برای استفاده در CLI یا بعد از آپدیت).
     */
    public static function flushCache(): void
    {
        foreach (['iran-provinces', 'iran-banks', 'mobile-prefixes', 'currencies', 'languages', 'industries'] as $key) {
            wp_cache_delete(self::CACHE_GROUP . '_' . $key, self::CACHE_GROUP);
        }
    }
}
