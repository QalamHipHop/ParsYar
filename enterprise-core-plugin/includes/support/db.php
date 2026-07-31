<?php
declare(strict_types=1);

namespace Enterprise\Support;

defined('ABSPATH') || exit;

/**
 * Db — لایهٔ دسترسی ایمن به دیتابیس.
 *
 * تمام جداول ماژول‌های داخلی با پیش‌وند `wp_ent_` نام‌گذاری می‌شوند؛
 * جداول مربوط به ماژول‌های دامنه (CRM/ERP/HRM/...) با پیش‌وند `wp_parsyar_`.
 *
 * تمام متدها از Prepared Statements (wpdb::prepare) استفاده می‌کنند و
 * در برابر حملات SQL Injection مقاوم هستند.
 */
final class Db
{
    /**
     * دریافت نام کامل یک جدول.
     *
     * @param string $name نام جدول (بدون پیش‌وند ent_ یا parsyar_)
     * @param string $prefix پیش‌وند (ent | parsyar)
     */
    public static function table(string $name, string $prefix = 'ent'): string
    {
        global $wpdb;
        return $wpdb->prefix . $prefix . '_' . $name;
    }

    /**
     * درج رکورد.
     *
     * @throws \RuntimeException
     */
    public static function insert(string $table, array $data, string $prefix = 'ent'): int
    {
        global $wpdb;
        $ok = $wpdb->insert(self::table($table, $prefix), $data);
        if ($ok === false) {
            throw new \RuntimeException('DB insert failed: ' . $wpdb->last_error);
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * به‌روزرسانی.
     *
     * @throws \RuntimeException
     */
    public static function update(string $table, array $data, array $where, string $prefix = 'ent'): int
    {
        global $wpdb;
        $r = $wpdb->update(self::table($table, $prefix), $data, $where);
        if ($r === false) {
            throw new \RuntimeException('DB update failed: ' . $wpdb->last_error);
        }
        return (int) $r;
    }

    /**
     * حذف.
     *
     * @throws \RuntimeException
     */
    public static function delete(string $table, array $where, string $prefix = 'ent'): int
    {
        global $wpdb;
        $r = $wpdb->delete(self::table($table, $prefix), $where);
        if ($r === false) {
            throw new \RuntimeException('DB delete failed: ' . $wpdb->last_error);
        }
        return (int) $r;
    }

    /**
     * انتخاب یک ردیف.
     */
    public static function getRow(string $table, array $where, string $prefix = 'ent'): ?array
    {
        global $wpdb;
        [$sql, $params] = self::buildSelect(self::table($table, $prefix), $where, 1, 0);
        $row = $params ? $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    /**
     * انتخاب چند ردیف.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getResults(string $table, array $where, string $order = 'id DESC', int $limit = 50, int $offset = 0, string $prefix = 'ent'): array
    {
        global $wpdb;
        [$sql, $params] = self::buildSelect(self::table($table, $prefix), $where, $limit, $offset, $order);
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return $rows ?: [];
    }

    /**
     * شمارش.
     */
    public static function count(string $table, array $where = [], string $prefix = 'ent'): int
    {
        global $wpdb;
        $t = self::table($table, $prefix);
        if (empty($where)) {
            return (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$t}") ?: 0);
        }
        [$clause, $params] = self::buildWhere($where);
        $sql = "SELECT COUNT(*) FROM {$t} WHERE {$clause}";
        return (int) ($wpdb->get_var($wpdb->prepare($sql, $params)) ?: 0);
    }

    /**
     * دریافت یک ستون aggregate (SUM/AVG/MAX/MIN) یا هر expression ساده.
     *
     * @param string $column   نام ستون یا expression (مثلاً "SUM(days)" یا "id")
     * @param mixed  $fallback مقدار پیش‌فرض اگر NULL برگشت
     */
    public static function getVar(
        string $table,
        array $where,
        string $column = 'id',
        mixed $fallback = null,
        string $prefix = 'ent'
    ): mixed {
        global $wpdb;
        $t = self::table($table, $prefix);
        $expr = sanitize_key(preg_replace('/[^A-Za-z0-9_(),\\.\\* ]/', '', $column) ?? $column);
        if ($expr === '') {
            $expr = 'id';
        }
        if (empty($where)) {
            $val = $wpdb->get_var("SELECT {$expr} FROM {$t} LIMIT 1");
        } else {
            [$clause, $params] = self::buildWhere($where);
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT {$expr} FROM {$t} WHERE {$clause} LIMIT 1",
                $params
            ));
        }
        return $val !== null && $val !== '' ? $val : $fallback;
    }

    /**
     * اجرای کوئری دلخواه (با پارامترهای امن).
     *
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function query(string $sql, array $params = []): array
    {
        global $wpdb;
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return $rows ?: [];
    }

    /**
     * اجرای کوئری دلخواه (یک ردیف).
     *
     * @param array<int,mixed> $params
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        global $wpdb;
        $row = $params ? $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    /**
     * اجرای کوئری دلخواه (یک مقدار).
     *
     * @param array<int,mixed> $params
     */
    public static function queryVar(string $sql, array $params = []): ?string
    {
        global $wpdb;
        $val = $params ? $wpdb->get_var($wpdb->prepare($sql, $params)) : $wpdb->get_var($sql);
        return $val !== null ? (string) $val : null;
    }

    /**
     * ساخت SQL برای select.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function buildSelect(string $table, array $where, int $limit, int $offset, string $order = 'id DESC'): array
    {
        $sql = "SELECT * FROM {$table}";
        $params = [];
        if (!empty($where)) {
            [$clause, $wparams] = self::buildWhere($where);
            $sql .= " WHERE {$clause}";
            $params = $wparams;
        }
        $sql .= ' ORDER BY ' . sanitize_sql_orderby($order);
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
        return [$sql, $params];
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function buildWhere(array $where): array
    {
        $clauses = [];
        $params  = [];
        foreach ($where as $key => $value) {
            $field = sanitize_key((string) $key);
            if ($field === '') {
                continue;
            }
            if (is_array($value)) {
                // IN (...)
                $placeholders = implode(',', array_fill(0, count($value), '%s'));
                $clauses[]    = "{$field} IN ({$placeholders})";
                foreach ($value as $v) {
                    $params[] = (string) $v;
                }
            } else {
                $clauses[] = "{$field} = %s";
                $params[]  = (string) $value;
            }
        }
        return [implode(' AND ', $clauses), $params];
    }
}
