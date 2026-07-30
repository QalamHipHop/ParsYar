<?php
declare(strict_types=1);

namespace Enterprise\Support;

defined('ABSPATH') || exit;

/**
 * لایه دسترسی به دیتابیس با امنیت بالا (Prepared Statements).
 * تمام جداول با پیش‌وند wp_ent_ نام‌گذاری می‌شوند.
 */
final class Db
{
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ent_' . $name;
    }

    public static function insert(string $table, array $data): int
    {
        global $wpdb;
        $ok = $wpdb->insert(self::table($table), $data);
        if ($ok === false) {
            throw new \RuntimeException('DB insert failed: ' . $wpdb->last_error);
        }
        return (int) $wpdb->insert_id;
    }

    public static function update(string $table, array $data, array $where): int
    {
        global $wpdb;
        $r = $wpdb->update(self::table($table), $data, $where);
        if ($r === false) {
            throw new \RuntimeException('DB update failed: ' . $wpdb->last_error);
        }
        return (int) $r;
    }

    public static function delete(string $table, array $where): int
    {
        global $wpdb;
        $r = $wpdb->delete(self::table($table), $where);
        if ($r === false) {
            throw new \RuntimeException('DB delete failed: ' . $wpdb->last_error);
        }
        return (int) $r;
    }

    public static function getRow(string $table, array $where): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table($table) . ' WHERE ' . self::buildWhere($where) . ' LIMIT 1',
            self::flatten($where)
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function getResults(string $table, array $where, string $order = 'id DESC', int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::table($table);
        $params = [];
        if (!empty($where)) {
            $sql .= ' WHERE ' . self::buildWhere($where);
            $params = self::flatten($where);
        }
        $sql .= ' ORDER BY ' . sanitize_sql_orderby($order);
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    private static function buildWhere(array $where): string
    {
        $clauses = [];
        foreach (array_keys($where) as $key) {
            $clauses[] = sanitize_key($key) . ' = %s';
        }
        return implode(' AND ', $clauses);
    }

    private static function flatten(array $where): array
    {
        $out = [];
        foreach ($where as $v) {
            if (is_array($v) || is_object($v)) {
                $out[] = wp_json_encode($v);
            } else {
                $out[] = (string) $v;
            }
        }
        return $out;
    }
}
