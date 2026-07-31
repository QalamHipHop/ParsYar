<?php
/**
 * Backup — تهیهٔ خروجی/ورودی پیکربندی و داده‌های حیاتی ParsYar.
 *
 * خروجی شامل:
 *   - تنظیمات (options با prefix parsyar_)
 *   - Chart of Accounts
 *   - تعریف اشیاء (Schema)
 *   - Workflow definitions
 *   - فهرست شرکت‌ها/شعب/ارزها
 *
 * این نسخه ساده است؛ برای نسخهٔ production باید به‌صورت تدریجی (chunked)
 * جداول بزرگ را dump کرد.
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise\Includes;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

final class Backup
{
    public const VERSION = '1.0';
    public const TABLES  = [
        'accounts',
        'fiscal_periods',
        'chart_of_accounts',
        'companies',
        'branches',
        'currencies',
        'tax_rates',
        'employees',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function export(): array
    {
        return [
            'meta' => [
                'parsyar_version' => \Enterprise\Bootstrap::VERSION,
                'backup_version'  => self::VERSION,
                'created_at'      => gmdate('c'),
                'site_url'        => home_url(),
            ],
            'options' => self::exportOptions(),
            'tables'  => self::exportTables(),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function import(array $data): array
    {
        $report = ['options_imported' => 0, 'tables_imported' => []];

        if (!empty($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $k => $v) {
                if (!is_string($k) || strpos($k, 'parsyar_') !== 0) {
                    continue;
                }
                update_option($k, $v, false);
                $report['options_imported']++;
            }
        }

        if (!empty($data['tables']) && is_array($data['tables'])) {
            foreach (self::TABLES as $t) {
                $rows = $data['tables'][$t] ?? null;
                if (!is_array($rows)) {
                    continue;
                }
                $imported = 0;
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    try {
                        Db::insert($t, $row, self::prefixFor($t));
                        $imported++;
                    } catch (\Throwable $e) {
                        // skip duplicates / constraint errors
                    }
                }
                $report['tables_imported'][$t] = $imported;
            }
        }

        return $report;
    }

    /**
     * @return array<string,mixed>
     */
    private static function exportOptions(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'parsyar\\_%'",
            ARRAY_A
        );
        $out = [];
        foreach ((array) $rows as $r) {
            $out[(string) $r['option_name']] = maybe_unserialize((string) $r['option_value']);
        }
        return $out;
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function exportTables(): array
    {
        $out = [];
        foreach (self::TABLES as $t) {
            $rows = Db::getResults($t, [], 'id ASC', 10000, 0, self::prefixFor($t));
            // strip id for clean re-import
            $out[$t] = array_map(static function (array $r): array {
                unset($r['id']);
                return $r;
            }, $rows);
        }
        return $out;
    }

    private static function prefixFor(string $table): string
    {
        // objects/engine → ent, domain → parsyar
        $ent = ['accounts', 'fiscal_periods', 'chart_of_accounts'];
        return in_array($table, $ent, true) ? 'ent' : 'parsyar';
    }
}
