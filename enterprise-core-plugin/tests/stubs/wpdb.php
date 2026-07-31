<?php
/**
 * wpdb stub for unit tests.
 * Captures queries into an in-memory table registry; supports the small
 * subset of operations the ParsYar codebase uses.
 */

declare(strict_types=1);

if (!class_exists('wpdb', false)) {
    class wpdb
    {
        public string $prefix = 'wp_';
        public string $last_query = '';
        public mixed $last_result = null;
        public int $insert_id = 0;
        /** @var array<string, array<int, array<string, mixed>>> */
        public array $_tables = [];
        /** @var array<string, int> */
        public array $_autoinc = [];

        public function get_charset_collate(): string
        {
            return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        public function prepare(string $sql, ...$args): string
        {
            // Substitute %s and %d placeholders in order.
            $i = 0;
            $result = preg_replace_callback('/%[sdf]/', function () use (&$i, $args) {
                if (!array_key_exists($i, $args)) {
                    return '';
                }
                $v = $args[$i++];
                if (is_int($v) || is_float($v)) {
                    return (string) $v;
                }
                if (is_null($v)) {
                    return 'NULL';
                }
                return "'" . addslashes((string) $v) . "'";
            }, $sql);
            return (string) $result;
        }

        public function query(string $sql): int|bool
        {
            $this->last_query = $sql;
            if (stripos($sql, 'CREATE TABLE') === 0) {
                return 1;
            }
            if (stripos($sql, 'ALTER TABLE') === 0) {
                return 1;
            }
            if (stripos($sql, 'SHOW TABLES') === 0) {
                return 0;
            }
            if (stripos($sql, 'INSERT') === 0) {
                $this->insert_id++;
                return 1;
            }
            if (stripos($sql, 'UPDATE') === 0 || stripos($sql, 'DELETE') === 0) {
                return 1;
            }
            return 1;
        }

        public function get_var(string $sql): mixed
        {
            $this->last_query = $sql;
            if (stripos($sql, 'SHOW TABLES') === 0) {
                // Return first matching table from our registry.
                if (!empty($this->_tables)) {
                    return array_key_first($this->_tables);
                }
                return null;
            }
            if (stripos($sql, 'SELECT COUNT(*)') === 0) {
                foreach ($this->_tables as $rows) {
                    return (string) count($rows);
                }
                return '0';
            }
            if (stripos($sql, 'SELECT id') !== false || stripos($sql, 'SELECT 1') === 0) {
                return 1;
            }
            return null;
        }

        public function get_row(string $sql, string $output = OBJECT): mixed
        {
            $this->last_query = $sql;
            foreach ($this->_tables as $rows) {
                if (!empty($rows)) {
                    return (object) $rows[0];
                }
            }
            return null;
        }

        /** @return array<int, object|array<string, mixed>> */
        public function get_results(string $sql, string $output = OBJECT): array
        {
            $this->last_query = $sql;
            foreach ($this->_tables as $rows) {
                return array_map(fn($r) => (object) $r, $rows);
            }
            return [];
        }

        /** @return array<string, mixed> */
        public function insert(string $table, array $data, array $format = null): int|false
        {
            $this->last_query = "INSERT INTO {$table}";
            $this->_autoinc[$table] = ($this->_autoinc[$table] ?? 0) + 1;
            $data['id'] = $this->_autoinc[$table];
            $this->_tables[$table][] = $data;
            $this->insert_id = $data['id'];
            return 1;
        }

        /** @return int|false */
        public function update(string $table, array $data, array $where, array $format = null, array $where_format = null)
        {
            $this->last_query = "UPDATE {$table}";
            return 1;
        }

        public function delete(string $table, array $where, array $format = null): int|false
        {
            $this->last_query = "DELETE FROM {$table}";
            return 1;
        }
    }
}

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new wpdb();
}
