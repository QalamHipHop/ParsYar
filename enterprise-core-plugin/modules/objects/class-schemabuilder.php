<?php
/**
 * Schema Builder — برای هر Object یک Flat Table اختصاصی می‌سازد.
 *
 * @package Enterprise\Modules\Objects
 */

declare(strict_types=1);

namespace Enterprise\Modules\Objects;

defined('ABSPATH') || exit;

use Enterprise\Modules\Objects\FieldTypes;
use Enterprise\Modules\Objects\InvalidFieldTypeException;

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
            } elseif (in_array($f['type'], [FieldTypes::EMAIL, FieldTypes::TEXT, FieldTypes::ENUM, FieldTypes::MOBILE, FieldTypes::NID, FieldTypes::SHEBA, FieldTypes::CARD], true)) {
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
     * اکنون از FieldTypes::ALL پشتیبانی می‌کند (۲۲ نوع).
     */
    private static function columnSpec(array $field): ?string
    {
        $name = sanitize_key($field['api_name']);
        if ($name === '') {
            return null;
        }
        $type = $field['type'] ?? 'text';
        if (!in_array($type, FieldTypes::ALL, true)) {
            throw new InvalidFieldTypeException($type);
        }
        $sql = FieldTypes::sqlType($type);
        $null = empty($field['is_required']) ? 'NULL' : 'NOT NULL';

        // بولی همیشه NOT NULL DEFAULT 0
        if ($type === FieldTypes::BOOL) {
            return "{$name} {$sql} NOT NULL DEFAULT 0";
        }
        // FK نیازی به default ندارد
        if ($type === FieldTypes::FK) {
            return "{$name} {$sql} {$null}";
        }
        // JSON و MULTI نیازی به default ندارند اگر اختیاری باشند
        if (in_array($type, [FieldTypes::JSON, FieldTypes::MULTI, FieldTypes::TEXTAREA, FieldTypes::RICH], true)) {
            return "{$name} {$sql} {$null}";
        }
        // بقیه
        return "{$name} {$sql} {$null}";
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
            } elseif (in_array($f['type'], [FieldTypes::EMAIL, FieldTypes::TEXT, FieldTypes::ENUM, FieldTypes::MOBILE, FieldTypes::NID, FieldTypes::SHEBA, FieldTypes::CARD], true)) {
                $wpdb->query("ALTER TABLE {$table} ADD KEY idx_{$name} ({$name})");
            }
        }
    }
}
