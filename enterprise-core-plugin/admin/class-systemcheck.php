<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

/**
 * بررسی‌های سیستمی قبل از نصب (Step 1).
 *
 * چک PHP version, WP version, MySQL/MariaDB, extها, mod_rewrite,
 * permalinks، cron، fsockopen، cURL، mbstring، intl، json، openssl، GD، zip
 */
final class SystemCheck
{
    public const REQUIRED_PHP = '8.1';
    public const REQUIRED_WP  = '6.5';

    /**
     * اجرای همهٔ چک‌ها و برگرداندن نتیجه.
     *
     * @return array<int,array{key:string,label:string,status:string,message:string,required:bool}>
     */
    public static function run(): array
    {
        global $wp_version, $wpdb;
        $checks = [];

        // PHP version
        $checks[] = self::check(
            'php_version',
            'نسخهٔ PHP',
            version_compare(PHP_VERSION, self::REQUIRED_PHP, '>='),
            sprintf('نسخهٔ فعلی %s — نیاز %s+', PHP_VERSION, self::REQUIRED_PHP)
        );

        // WordPress version
        $checks[] = self::check(
            'wp_version',
            'نسخهٔ وردپرس',
            version_compare($wp_version ?? '0', self::REQUIRED_WP, '>='),
            sprintf('نسخهٔ فعلی %s — نیاز %s+', $wp_version ?? 'نامشخص', self::REQUIRED_WP)
        );

        // MySQL / MariaDB
        $dbVersion = $wpdb->db_version() ?: '0';
        $isMysql = stripos($dbVersion, 'Maria') !== false ? 'MariaDB' : 'MySQL';
        $dbMin    = $isMysql === 'MariaDB' ? '10.6' : '8.0';
        $checks[] = self::check(
            'db_version',
            'نسخهٔ ' . $isMysql,
            version_compare(preg_replace('/[^0-9.]/', '', $dbVersion), $dbMin, '>='),
            sprintf('نسخهٔ فعلی %s — نیاز %s+', $dbVersion, $dbMin)
        );

        // PHP extensions
        $exts = [
            'mbstring' => 'رشته‌های چندبایتی (فارسی)',
            'intl'     => 'بین‌المللی‌سازی (تقویم شمسی)',
            'mysqli'   => 'MySQL Improved',
            'json'     => 'JSON',
            'openssl'  => 'OpenSSL (برای APIهای بانکی)',
            'gd'       => 'GD (پردازش تصویر)',
            'zip'      => 'Zip (پشتیبان‌گیری)',
            'curl'     => 'cURL (یکپارچگی‌ها)',
            'fileinfo' => 'Fileinfo (تشخیص MIME)',
            'ctype'    => 'Ctype',
            'iconv'    => 'IconV (تبدیل کاراکتر)',
            'bcmath'   => 'BCMath (دقت مالی)',
        ];
        foreach ($exts as $ext => $label) {
            $checks[] = self::check(
                'ext_' . $ext,
                'افزونهٔ PHP: ' . $label,
                extension_loaded($ext),
                extension_loaded($ext) ? 'بارگذاری شد' : ('غیرفعال — نصب کنید: apt install php-' . $ext)
            );
        }

        // mod_rewrite (Apache)
        $checks[] = self::check(
            'mod_rewrite',
            'Apache mod_rewrite',
            function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : (getenv('SERVER_SOFTWARE') === 'nginx' || getenv('SERVER_SOFTWARE') === 'Caddy'),
            'مورد نیاز برای pretty permalinks'
        );

        // Pretty permalinks
        $permalinks = (string) get_option('permalink_structure', '');
        $checks[] = self::check(
            'permalinks',
            'Pretty Permalinks',
            $permalinks !== '',
            $permalinks === '' ? 'Plain permalinks فعلاً — بعداً فعال کنید' : 'ساختار: ' . $permalinks
        );

        // WP Cron
        $checks[] = self::check(
            'cron',
            'WP-Cron',
            !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'غیرفعال (روی سرور cron تنظیم کنید)' : 'فعال'
        );

        // fsockopen
        $checks[] = self::check(
            'fsockopen',
            'fsockopen',
            function_exists('fsockopen'),
            function_exists('fsockopen') ? 'موجود' : 'غیرفعال — ممکن است برخی SMS/Email gatewayها مشکل پیدا کنند'
        );

        // cURL
        $checks[] = self::check(
            'curl_available',
            'cURL extension',
            function_exists('curl_init'),
            function_exists('curl_init') ? 'موجود' : 'نصب نشده'
        );

        // SSL
        $checks[] = self::check(
            'ssl',
            'HTTPS / SSL',
            is_ssl(),
            is_ssl() ? 'فعال' : 'هشدار — برای APIهای بانکی SSL لازم است'
        );

        // Memory limit
        $memLimit = self::memoryLimitBytes();
        $memMb    = (int) round($memLimit / 1024 / 1024);
        $checks[] = self::check(
            'memory',
            'PHP memory_limit',
            $memMb >= 256,
            sprintf('فعلی %d MB — توصیه‌شده ۲۵۶ MB یا بیشتر', $memMb)
        );

        // Max execution time
        $maxExec = (int) ini_get('max_execution_time');
        $checks[] = self::check(
            'max_exec',
            'max_execution_time',
            $maxExec === 0 || $maxExec >= 60,
            sprintf('فعلی %d ثانیه — توصیه‌شده ۶۰ یا بیشتر', $maxExec)
        );

        // Upload max filesize
        $uploadMb = (int) round(wp_max_upload_size() / 1024 / 1024);
        $checks[] = self::check(
            'upload_max',
            'حداکثر حجم آپلود',
            $uploadMb >= 32,
            sprintf('فعلی %d MB — توصیه‌شده ≥ ۳۲ MB', $uploadMb)
        );

        // write permissions on uploads
        $uploadDir = wp_upload_dir();
        $checks[] = self::check(
            'uploads_writable',
            'پوشهٔ uploads قابل نوشتن',
            is_writable($uploadDir['path']),
            $uploadDir['path']
        );

        // timezone
        $tz = get_option('timezone_string', 'UTC');
        $checks[] = self::check(
            'wp_timezone',
            'منطقهٔ زمانی وردپرس',
            !empty($tz),
            $tz ?: 'خالی — تنظیم کنید'
        );

        return $checks;
    }

    public static function summary(array $checks): array
    {
        $total = count($checks);
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        foreach ($checks as $c) {
            if ($c['status'] === 'ok') {
                $passed++;
            } elseif ($c['required'] ?? false) {
                $failed++;
            } else {
                $warnings++;
            }
        }
        return [
            'total'    => $total,
            'passed'   => $passed,
            'failed'   => $failed,
            'warnings' => $warnings,
            'ready'    => $failed === 0,
        ];
    }

    /** @return array{key:string,label:string,status:string,message:string,required:bool} */
    private static function check(string $key, string $label, bool $ok, string $message, bool $required = true): array
    {
        return [
            'key'      => $key,
            'label'    => $label,
            'status'   => $ok ? 'ok' : 'fail',
            'message'  => $message,
            'required' => $required,
        ];
    }

    private static function memoryLimitBytes(): int
    {
        $v = (string) ini_get('memory_limit');
        $unit = strtolower(substr($v, -1));
        $num  = (int) $v;
        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
