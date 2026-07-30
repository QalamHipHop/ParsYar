<?php
declare(strict_types=1);

namespace Enterprise\Modules\Objects;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Installer;

/**
 * Schema Builder — برای هر Object یک Flat Table اختصاصی می‌سازد.
 *
 * هر شیء در دیتابیس یک جدول مستقل دارد که ستون‌هایش معادل فیلدهای تعریف‌شده است.
 * این الگو (Flat Tables) سرعت کوئری‌ها را در مقیاس میلیون‌ها رکورد حفظ می‌کند.
 *
 * ساختار جدول:
 *   id BIGINT PK
 *   owner_id BIGINT
 *   status VARCHAR
 *   created_at DATETIME
 *   updated_at DATETIME
 *   + یک ستون اختصاصی برای هر فیلد
 *   + ایندکس روی ستون‌های پرکوئری
 */
final class SchemaBuilder
{
    /**
     * ساخت یا به‌روزرسانی جدول یک شیء بر اساس فیلدهایش.
     */
    public static function syncObjectTable(int $objectId, string $apiName, array $fields): void
    {
        global $wpdb;
        $tableName = self::tableFor($apiName);
        $existing  = self::getExistingColumns($tableName);

        $columns = [
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'owner_id BIGINT UNSIGNED NULL',
            'status VARCHAR(32) NOT NULL DEFAULT \'active\'',
            'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];
        $indexes = [
            'KEY idx_owner (owner_id)',
            'KEY idx_status (status)',
            'KEY idx_created (created_at)',
        ];

        foreach ($fields as $f) {
            $col = self::columnSpec($f);
            if ($col === null) {
                continue;
            }
            $columns[] = $col;
            if (!empty($f['is_unique'])) {
                $indexes[] = 'UNIQUE KEY uniq_' . $f['api_name'] . ' (' . $f['api_name'] . ')';
            } elseif (in_array($f['type'], ['email', 'text', 'select'], true)) {
                $indexes[] = 'KEY idx_' . $f['api_name'] . ' (' . $f['api_name'] . ')';
            }
        }

        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$tableName} (\n  "
                 . implode(",\n  ", array_merge($columns, $indexes))
                 . "\n) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // برای فیلدهایی که بعداً اضافه شده‌اند، ALTER TABLE بزن.
        self::addMissingColumns($tableName, $fields, $existing);
    }

    /**
     * حذف جدول یک شیء (فقط شیء غیرسیستمی).
     */
    public static function dropObjectTable(string $apiName): void
    {
        global $wpdb;
        $table = self::tableFor($apiName);
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    public static function tableFor(string $apiName): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ent_data_' . sanitize_key($apiName);
    }

    /**
     * نگاشت نوع فیلد به نوع ستون MySQL.
     */
    private static function columnSpec(array $field): ?string
    {
        $name = sanitize_key($field['api_name']);
        if ($name === '') {
            return null;
        }
        $type = $field['type'] ?? 'text';
        $null = empty($field['is_required']) ? 'NULL' : 'NOT NULL';
        $col  = match ($type) {
            'text', 'email', 'phone', 'url', 'select' => "VARCHAR(255) {$null}",
            'textarea'                                 => "TEXT {$null}",
            'number'                                   => "INT {$null}",
            'decimal', 'currency'                      => "DECIMAL(20,2) {$null}",
            'boolean'                                  => "TINYINT(1) NOT NULL DEFAULT 0",
            'date'                                     => "DATE {$null}",
            'datetime'                                 => "DATETIME {$null}",
            'multiselect'                              => "TEXT {$null}",
            'lookup'                                   => "BIGINT UNSIGNED {$null}",
            default                                    => "VARCHAR(255) {$null}",
        };
        return "{$name} {$col}";
    }

    private static function getExistingColumns(string $table): array
    {
        global $wpdb;
        $cols = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        if (!$cols) {
            return [];
        }
        $out = [];
        foreach ($cols as $c) {
            $out[] = (string) $c['Field'];
        }
        return $out;
    }

    /**
     * ALTER TABLE برای ستون‌های جدیدی که بعد از ساخت اولیه اضافه شده‌اند.
     */
    private static function addMissingColumns(string $table, array $fields, array $existing): void
    {
        global $wpdb;
        $existing = array_flip($existing);
        foreach ($fields as $f) {
            $col = self::columnSpec($f);
            if ($col === null) {
                continue;
            }
            $name = sanitize_key($f['api_name']);
            if (isset($existing[$name])) {
                continue;
            }
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$col}");
            if (!empty($f['is_unique'])) {
                $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_{$name} ({$name})");
            } elseif (in_array($f['type'], ['email', 'text', 'select'], true)) {
                $wpdb->query("ALTER TABLE {$table} ADD KEY idx_{$name} ({$name})");
            }
        }
    }
}
