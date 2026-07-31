<?php
/**
 * Report Service — Custom Report Builder (نسخهٔ ۱.۶).
 *
 * این سرویس به کاربران اجازه می‌دهد بدون کدنویسی گزارش‌های سفارشی بسازند:
 *  - Data Source: contacts, organizations, leads, deals, products,
 *                 invoices, payments, employees, attendance, leaves, journal
 *  - Filters: ترکیب شرط‌ها (field, op, value) با AND
 *  - Group By: گروه‌بندی بر اساس یک یا چند فیلد
 *  - Aggregations: count, sum, avg, min, max
 *  - Chart Type: bar, line, pie, table
 *  - ذخیره و اشتراک‌گذاری گزارش بین کاربران (is_public)
 *  - خروجی: JSON (پیش‌فرض)، CSV
 *
 * @package Enterprise\Modules\Reports
 */

declare(strict_types=1);

namespace Enterprise\Modules\Reports;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

final class ReportService
{
    /** منابع دادهٔ مجاز به‌همراه ستون‌های مجاز برای filter/group/metric. */
    public const SOURCES = [
        'contacts' => [
            'table'  => 'ent_contacts',
            'label'  => 'مخاطبان',
            'columns'=> ['id','first_name','last_name','email','mobile','city','lifecycle_stage','source','owner_id','created_at'],
        ],
        'organizations' => [
            'table'  => 'ent_organizations',
            'label'  => 'سازمان‌ها',
            'columns'=> ['id','name','industry','city','size','owner_id','created_at'],
        ],
        'leads' => [
            'table'  => 'ent_leads',
            'label'  => 'سرنخ‌ها',
            'columns'=> ['id','first_name','last_name','source','stage','score','owner_id','created_at'],
        ],
        'deals' => [
            'table'  => 'ent_deals',
            'label'  => 'معاملات',
            'columns'=> ['id','title','stage_id','amount','currency','status','owner_id','pipeline_id','close_date','created_at'],
        ],
        'products' => [
            'table'  => 'ent_products',
            'label'  => 'محصولات',
            'columns'=> ['id','name','sku','category_id','price','cost','stock','status','created_at'],
        ],
        'invoices' => [
            'table'  => 'ent_invoices',
            'label'  => 'فاکتورها',
            'columns'=> ['id','number','contact_id','status','subtotal','tax','total','paid','due_date','issue_date','created_at'],
        ],
        'payments' => [
            'table'  => 'ent_payments',
            'label'  => 'پرداخت‌ها',
            'columns'=> ['id','invoice_id','method','amount','status','paid_at','created_at'],
        ],
        'employees' => [
            'table'  => 'ent_employees',
            'label'  => 'کارمندان',
            'columns'=> ['id','first_name','last_name','department_id','manager_id','status','hire_date'],
        ],
        'attendance' => [
            'table'  => 'ent_attendance',
            'label'  => 'حضور و غیاب',
            'columns'=> ['id','employee_id','work_date','work_minutes','late_minutes','overtime_minutes','status'],
        ],
        'leaves' => [
            'table'  => 'ent_leaves',
            'label'  => 'مرخصی‌ها',
            'columns'=> ['id','employee_id','type','status','from_date','to_date','days'],
        ],
        'journal' => [
            'table'  => 'ent_journal_entries',
            'label'  => 'اسناد حسابداری',
            'columns'=> ['id','number','date','status','total_debit','total_credit','source'],
        ],
    ];

    /** اپراتورهای مجاز برای فیلتر. */
    public const OPS = ['==','!=','>','>=','<','<=','contains','in','empty','not_empty'];

    /** نوع نمودار. */
    public const CHARTS = ['table','bar','line','pie','area'];

    public static function listSources(): array
    {
        return self::SOURCES;
    }

    /**
     * ساخت گزارش.
     */
    public static function create(array $data): int
    {
        $data['name']        = sanitize_text_field((string) ($data['name'] ?? ''));
        $data['description'] = sanitize_textarea_field((string) ($data['description'] ?? ''));
        $data['data_source'] = sanitize_key((string) ($data['data_source'] ?? ''));
        $data['chart_type']  = sanitize_key((string) ($data['chart_type'] ?? 'table'));
        $data['is_public']   = !empty($data['is_public']) ? 1 : 0;
        $data['config_json'] = wp_json_encode(self::normalizeConfig($data), JSON_UNESCAPED_UNICODE);
        $data['created_by']  = get_current_user_id() ?: null;
        $data['created_at']  = current_time('mysql');
        return Db::insert('reports', $data, 'parsyar');
    }

    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        $patch = [];
        if (isset($data['name']))        $patch['name']        = sanitize_text_field((string) $data['name']);
        if (isset($data['description'])) $patch['description'] = sanitize_textarea_field((string) $data['description']);
        if (isset($data['data_source'])) $patch['data_source'] = sanitize_key((string) $data['data_source']);
        if (isset($data['chart_type']))  $patch['chart_type']  = sanitize_key((string) $data['chart_type']);
        if (isset($data['is_public']))   $patch['is_public']   = !empty($data['is_public']) ? 1 : 0;
        if (isset($data['config'])) {
            $patch['config_json'] = wp_json_encode(self::normalizeConfig($data), JSON_UNESCAPED_UNICODE);
        }
        $patch['updated_at'] = current_time('mysql');
        Db::update('reports', $patch, ['id' => $id], 'parsyar');
        return true;
    }

    public static function delete(int $id): bool
    {
        $r = Db::delete('reports', ['id' => $id], 'parsyar');
        return $r > 0;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow('reports', ['id' => $id], 'parsyar');
        if (!$row) {
            return null;
        }
        $row['config'] = $row['config_json'] ? json_decode((string) $row['config_json'], true) : [];
        return $row;
    }

    public static function listAll(bool $publicOnly = false, int $limit = 200, int $offset = 0): array
    {
        $where = $publicOnly ? ['is_public' => 1] : [];
        return Db::getResults('reports', $where, 'id DESC', $limit, $offset, 'parsyar');
    }

    /**
     * نرمال‌سازی ساختار config.
     */
    public static function normalizeConfig(array $data): array
    {
        $filters = [];
        foreach ((array) ($data['filters'] ?? []) as $f) {
            $field = (string) ($f['field'] ?? '');
            $op    = (string) ($f['op']    ?? '==');
            $value = $f['value'] ?? null;
            if ($field === '' || !in_array($op, self::OPS, true)) {
                continue;
            }
            $filters[] = ['field' => $field, 'op' => $op, 'value' => $value];
        }
        $groupBy = array_values(array_filter((array) ($data['group_by'] ?? []), 'is_string'));
        $metrics = [];
        foreach ((array) ($data['metrics'] ?? []) as $m) {
            $agg  = (string) ($m['agg']  ?? 'count');
            $col  = (string) ($m['col']  ?? '*');
            $alias = (string) ($m['alias'] ?? ($agg . '_' . $col));
            if (!in_array($agg, ['count','sum','avg','min','max'], true)) {
                continue;
            }
            $metrics[] = ['agg' => $agg, 'col' => $col, 'alias' => $alias];
        }
        $limit  = max(1, min(5000, (int) ($data['limit'] ?? 1000)));
        $sortBy = (string) ($data['sort_by'] ?? '');
        $sortDir= strtolower((string) ($data['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return [
            'filters'  => $filters,
            'group_by' => $groupBy,
            'metrics'  => $metrics,
            'sort_by'  => $sortBy,
            'sort_dir' => $sortDir,
            'limit'    => $limit,
        ];
    }

    /**
     * اجرای گزارش و برگرداندن نتیجه.
     */
    public static function run(int $id, array $overrides = []): array
    {
        $report = self::find($id);
        if (!$report) {
            return ['error' => 'report_not_found'];
        }
        $cfg = array_replace_recursive((array) $report['config'], $overrides);
        return self::execute((string) $report['data_source'], $cfg);
    }

    /**
     * اجرای مستقیم (بدون ذخیره).
     */
    public static function execute(string $source, array $config): array
    {
        if (!isset(self::SOURCES[$source])) {
            return ['error' => 'unknown_data_source', 'source' => $source];
        }
        $src = self::SOURCES[$source];
        $table = \Enterprise\Support\Db::table($src['table'], 'ent');
        $validCols = $src['columns'];

        $filters = (array) ($config['filters']  ?? []);
        $groupBy = (array) ($config['group_by'] ?? []);
        $metrics = (array) ($config['metrics']  ?? []);
        $limit   = (int)   ($config['limit']    ?? 1000);
        $sortBy  = (string)($config['sort_by']  ?? '');
        $sortDir = (string)($config['sort_dir'] ?? 'asc');

        // WHERE
        $where = '1=1';
        $args  = [];
        foreach ($filters as $f) {
            $field = (string) ($f['field'] ?? '');
            $op    = (string) ($f['op']    ?? '==');
            $val   = $f['value'] ?? null;
            if (!in_array($field, $validCols, true)) {
                continue;
            }
            $col = "`{$field}`";
            switch ($op) {
                case '==': $where .= " AND {$col} = %s"; $args[] = (string) $val; break;
                case '!=': $where .= " AND {$col} <> %s"; $args[] = (string) $val; break;
                case '>':  $where .= " AND {$col} > %s";  $args[] = (string) $val; break;
                case '>=': $where .= " AND {$col} >= %s"; $args[] = (string) $val; break;
                case '<':  $where .= " AND {$col} < %s";  $args[] = (string) $val; break;
                case '<=': $where .= " AND {$col} <= %s"; $args[] = (string) $val; break;
                case 'contains': $where .= " AND {$col} LIKE %s"; $args[] = '%' . $GLOBALS['wpdb']->esc_like((string) $val) . '%'; break;
                case 'in':
                    if (is_array($val) && !empty($val)) {
                        $placeholders = implode(',', array_fill(0, count($val), '%s'));
                        $where .= " AND {$col} IN ({$placeholders})";
                        foreach ($val as $v) { $args[] = (string) $v; }
                    }
                    break;
                case 'empty':     $where .= " AND ({$col} IS NULL OR {$col} = '')"; break;
                case 'not_empty': $where .= " AND ({$col} IS NOT NULL AND {$col} <> '')"; break;
            }
        }

        // SELECT
        if (empty($metrics) && empty($groupBy)) {
            // لیست ساده
            $select = '*';
            $rows = self::fetchAll($table, $where, $args, $sortBy, $sortDir, $limit, $validCols);
            return [
                'mode'   => 'list',
                'source' => $source,
                'rows'   => $rows,
                'total'  => count($rows),
            ];
        }

        // aggregate
        $selectParts = [];
        foreach ($groupBy as $g) {
            if (in_array($g, $validCols, true)) {
                $selectParts[] = "`{$g}`";
            }
        }
        if (empty($metrics)) {
            $metrics = [['agg' => 'count', 'col' => '*', 'alias' => 'count']];
        }
        foreach ($metrics as $m) {
            $col  = (string) $m['col'];
            $agg  = strtoupper((string) $m['agg']);
            $alias= (string) $m['alias'];
            if ($agg === 'COUNT') {
                $selectParts[] = "COUNT(*) AS `{$alias}`";
            } else {
                if (!in_array($col, $validCols, true)) {
                    continue;
                }
                if (!in_array($agg, ['SUM','AVG','MIN','MAX'], true)) {
                    continue;
                }
                $selectParts[] = "{$agg}(`{$col}`) AS `{$alias}`";
            }
        }
        if (empty($selectParts)) {
            return ['error' => 'empty_select'];
        }
        $sql = 'SELECT ' . implode(', ', $selectParts) . " FROM {$table} WHERE {$where}";
        if (!empty($groupBy)) {
            $validGroup = array_values(array_filter($groupBy, static fn($g) => in_array($g, $validCols, true)));
            $sql .= ' GROUP BY ' . implode(', ', array_map(static fn($g) => "`{$g}`", $validGroup));
        }
        $sortCol = in_array($sortBy, $validCols, true) ? "`{$sortBy}`" : (count($metrics) > 0 ? "`{$metrics[0]['alias']}`" : '1');
        $sql .= " ORDER BY {$sortCol} {$sortDir} LIMIT " . max(1, min(5000, $limit));

        $rows = self::query($sql, $args);
        return [
            'mode'   => 'aggregate',
            'source' => $source,
            'rows'   => $rows,
            'total'  => count($rows),
            'group_by' => $groupBy,
            'metrics'  => $metrics,
        ];
    }

    /**
     * خروجی CSV از نتیجهٔ گزارش.
     */
    public static function toCsv(array $result): string
    {
        if (empty($result['rows']) || !is_array($result['rows'])) {
            return '';
        }
        $first = $result['rows'][0];
        if (!is_array($first) || empty($first)) {
            return '';
        }
        $headers = array_keys($first);
        $out = "\xEF\xBB\xBF"; // BOM برای اکسل فارسی
        $out .= implode(',', array_map(static fn($h) => '"' . str_replace('"', '""', (string) $h) . '"', $headers)) . "\n";
        foreach ($result['rows'] as $r) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = '"' . str_replace('"', '""', (string) ($r[$h] ?? '')) . '"';
            }
            $out .= implode(',', $line) . "\n";
        }
        return $out;
    }

    /* ----------------- internal helpers ----------------- */

    private static function fetchAll(string $table, string $where, array $args, string $sortBy, string $sortDir, int $limit, array $validCols): array
    {
        $order = '';
        if ($sortBy !== '' && in_array($sortBy, $validCols, true)) {
            $order = " ORDER BY `{$sortBy}` " . ($sortDir === 'desc' ? 'DESC' : 'ASC');
        }
        $sql = "SELECT * FROM {$table} WHERE {$where}{$order} LIMIT " . max(1, min(5000, $limit));
        return self::query($sql, $args);
    }

    /**
     * اجرای کوئری با $wpdb با جای‌گذاری ایمن.
     * اگه $args خالی باشه، مستقیم اجرا می‌شه (بدون prepare).
     */
    private static function query(string $sql, array $args): array
    {
        global $wpdb;
        if (empty($args)) {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        } else {
            $prepared = $wpdb->prepare($sql, $args);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
        }
        return is_array($rows) ? $rows : [];
    }

    /**
     * قالب‌های آماده (starter reports) برای سرعت کاربر.
     */
    public static function templates(): array
    {
        return [
            [
                'id'          => 'contacts-by-city',
                'name'        => 'مخاطبان به تفکیک شهر',
                'data_source' => 'contacts',
                'chart_type'  => 'bar',
                'config'      => [
                    'group_by' => ['city'],
                    'metrics'  => [['agg' => 'count', 'col' => '*', 'alias' => 'count']],
                    'sort_by'  => 'count', 'sort_dir' => 'desc', 'limit' => 50,
                    'filters'  => [['field' => 'city', 'op' => 'not_empty', 'value' => null]],
                ],
            ],
            [
                'id'          => 'deals-won-by-month',
                'name'        => 'معاملات بسته‌شده بر اساس ماه',
                'data_source' => 'deals',
                'chart_type'  => 'line',
                'config'      => [
                    'group_by' => ['pipeline_id'],
                    'metrics'  => [
                        ['agg' => 'sum', 'col' => 'amount', 'alias' => 'total_won'],
                        ['agg' => 'count', 'col' => '*', 'alias' => 'count_won'],
                    ],
                    'filters' => [['field' => 'status', 'op' => '==', 'value' => 'won']],
                    'sort_by' => 'total_won', 'sort_dir' => 'desc', 'limit' => 100,
                ],
            ],
            [
                'id'          => 'top-products-by-revenue',
                'name'        => 'محصولات پرفروش',
                'data_source' => 'invoices',
                'chart_type'  => 'bar',
                'config'      => [
                    'group_by' => [],
                    'metrics'  => [
                        ['agg' => 'sum', 'col' => 'total', 'alias' => 'revenue'],
                        ['agg' => 'count', 'col' => '*', 'alias' => 'invoices'],
                    ],
                    'sort_by' => 'revenue', 'sort_dir' => 'desc', 'limit' => 10,
                ],
            ],
            [
                'id'          => 'low-stock-alert',
                'name'        => 'محصولات با موجودی کم',
                'data_source' => 'products',
                'chart_type'  => 'table',
                'config'      => [
                    'group_by' => [],
                    'metrics'  => [],
                    'filters'  => [['field' => 'stock', 'op' => '<=', 'value' => 10]],
                    'sort_by'  => 'stock', 'sort_dir' => 'asc', 'limit' => 200,
                ],
            ],
        ];
    }
}
