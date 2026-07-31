<?php
/**
 * Reports — موتور گزارش‌های مالی مستقیم از Ledger.
 *
 * تمام گزارش‌ها از journal_lines خوانده می‌شوند (append-only، تغییرناپذیر).
 * محاسبات با دقت بالا و گردش در آخرین مرحله انجام می‌شود.
 *
 * @package Enterprise\Modules\Accounting
 */

declare(strict_types=1);

namespace Enterprise\Modules\Accounting;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Support\Cache;

final class Reports
{
    private const CACHE_TTL = 120; // 2 minutes — financial data cache window

    /**
     * تراز آزمایشی (Trial Balance) — برای یک دورهٔ مالی یا بازهٔ تاریخی.
     *
     * @param array{
     *   company_id?:int,
     *   fiscal_period_id?:int,
     *   date_from?:string,
     *   date_to?:string,
     *   include_zero?:bool,
     * } $args
     * @return array{
     *   meta:array<string,mixed>,
     *   rows:array<int,array<string,mixed>>,
     *   totals:array<string,float>
     * }
     */
    public static function trialBalance(array $args = []): array
    {
        $args = self::normalizeArgs($args);
        $cacheKey = 'tb:' . md5(json_encode($args, JSON_UNESCAPED_UNICODE));

        return Cache::remember($cacheKey, function () use ($args) {
            $sql = self::baseLinesSelect($args, true) . "
                SELECT
                    a.code         AS account_code,
                    a.label        AS account_label,
                    a.type         AS account_type,
                    a.normal_side  AS normal_side,
                    SUM(l.debit)   AS total_debit,
                    SUM(l.credit)  AS total_credit
                FROM {lines} l
                INNER JOIN {entries} e ON e.id = l.entry_id
                INNER JOIN {accounts} a ON a.id = l.account_id
                {where}
                GROUP BY a.id, a.code, a.label, a.type, a.normal_side
                ORDER BY a.code ASC
            ";

            [$sql, $params] = self::bind($sql, $args);
            $rowsRaw = Db::query($sql, $params);

            $rows = [];
            $sumDebit = 0.0;
            $sumCredit = 0.0;
            foreach ($rowsRaw as $r) {
                $debit  = (float) $r['total_debit'];
                $credit = (float) $r['total_credit'];
                $balance = round($debit - $credit, 4);

                if (!$args['include_zero'] && abs($debit) < 0.005 && abs($credit) < 0.005) {
                    continue;
                }

                $rows[] = [
                    'account_code'  => (string) $r['account_code'],
                    'account_label' => (string) $r['account_label'],
                    'account_type'  => (string) $r['account_type'],
                    'normal_side'   => (string) $r['normal_side'],
                    'debit'         => round($debit, 2),
                    'credit'        => round($credit, 2),
                    'balance'       => $balance,
                    'side'          => $balance >= 0 ? 'debit' : 'credit',
                ];
                $sumDebit  += $debit;
                $sumCredit += $credit;
            }

            $diff = round($sumDebit - $sumCredit, 4);

            return [
                'meta'   => [
                    'company_id'        => $args['company_id'],
                    'fiscal_period_id'  => $args['fiscal_period_id'],
                    'date_from'         => $args['date_from'],
                    'date_to'           => $args['date_to'],
                    'currency'          => $args['currency'],
                    'generated_at'      => gmdate('c'),
                    'row_count'         => count($rows),
                ],
                'rows'   => $rows,
                'totals' => [
                    'debit'           => round($sumDebit, 2),
                    'credit'          => round($sumCredit, 2),
                    'difference'      => $diff,
                    'is_balanced'     => abs($diff) < Ledger::TOLERANCE,
                ],
            ];
        }, self::CACHE_TTL, 'reports');
    }

    /**
     * صورت سود و زیان (Income Statement / P&L).
     *
     * @return array{
     *   meta:array<string,mixed>,
     *   revenue:array<string,float>,
     *   expenses:array<string,float>,
     *   totals:array<string,float>
     * }
     */
    public static function incomeStatement(array $args = []): array
    {
        $args = self::normalizeArgs($args);
        $cacheKey = 'pl:' . md5(json_encode($args, JSON_UNESCAPED_UNICODE));

        return Cache::remember($cacheKey, function () use ($args) {
            $sql = self::baseLinesSelect($args, true) . "
                SELECT
                    a.code        AS account_code,
                    a.label       AS account_label,
                    a.type        AS account_type,
                    a.normal_side AS normal_side,
                    SUM(l.debit)  AS total_debit,
                    SUM(l.credit) AS total_credit
                FROM {lines} l
                INNER JOIN {entries} e ON e.id = l.entry_id
                INNER JOIN {accounts} a ON a.id = l.account_id
                WHERE a.type IN ('revenue', 'expense')
                {extra_where}
                GROUP BY a.id, a.code, a.label, a.type, a.normal_side
                ORDER BY a.code ASC
            ";

            [$sql, $params] = self::bind($sql, $args);
            $rows = Db::query($sql, $params);

            $revenue  = [];
            $expenses = [];
            $totalRevenue  = 0.0;
            $totalExpenses = 0.0;

            foreach ($rows as $r) {
                $debit  = (float) $r['total_debit'];
                $credit = (float) $r['total_credit'];
                // revenue: normal credit → credit - debit
                // expense: normal debit  → debit - credit
                if ($r['account_type'] === 'revenue') {
                    $net = round($credit - $debit, 2);
                    $revenue[] = [
                        'account_code'  => (string) $r['account_code'],
                        'account_label' => (string) $r['account_label'],
                        'amount'        => $net,
                    ];
                    $totalRevenue += $net;
                } else {
                    $net = round($debit - $credit, 2);
                    $expenses[] = [
                        'account_code'  => (string) $r['account_code'],
                        'account_label' => (string) $r['account_label'],
                        'amount'        => $net,
                    ];
                    $totalExpenses += $net;
                }
            }

            $netIncome = round($totalRevenue - $totalExpenses, 2);
            $grossMargin = $totalRevenue > 0
                ? round(($netIncome / $totalRevenue) * 100, 2)
                : 0.0;

            return [
                'meta'    => [
                    'company_id'       => $args['company_id'],
                    'fiscal_period_id' => $args['fiscal_period_id'],
                    'date_from'        => $args['date_from'],
                    'date_to'          => $args['date_to'],
                    'currency'         => $args['currency'],
                    'generated_at'     => gmdate('c'),
                ],
                'revenue'  => $revenue,
                'expenses' => $expenses,
                'totals'   => [
                    'total_revenue'  => round($totalRevenue, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'net_income'     => $netIncome,
                    'gross_margin'   => $grossMargin,
                ],
            ];
        }, self::CACHE_TTL, 'reports');
    }

    /**
     * ترازنامه (Balance Sheet) — در تاریخ مشخص.
     *
     * @param string $asOfDate Y-m-d
     */
    public static function balanceSheet(string $asOfDate, array $args = []): array
    {
        $args = array_merge($args, [
            'date_to'     => $asOfDate,
            'date_from'   => '0001-01-01',
        ]);
        $args = self::normalizeArgs($args);
        $cacheKey = 'bs:' . md5(json_encode($args, JSON_UNESCAPED_UNICODE));

        return Cache::remember($cacheKey, function () use ($args) {
            $sql = self::baseLinesSelect($args, true) . "
                SELECT
                    a.code        AS account_code,
                    a.label       AS account_label,
                    a.type        AS account_type,
                    a.normal_side AS normal_side,
                    SUM(l.debit)  AS total_debit,
                    SUM(l.credit) AS total_credit
                FROM {lines} l
                INNER JOIN {entries} e ON e.id = l.entry_id
                INNER JOIN {accounts} a ON a.id = l.account_id
                WHERE a.type IN ('asset', 'liability', 'equity')
                GROUP BY a.id, a.code, a.label, a.type, a.normal_side
                ORDER BY a.code ASC
            ";

            [$sql, $params] = self::bind($sql, $args);
            $rows = Db::query($sql, $params);

            $assets      = [];
            $liabilities = [];
            $equity      = [];
            $totalAssets = 0.0;
            $totalLiab   = 0.0;
            $totalEquity = 0.0;

            foreach ($rows as $r) {
                $debit  = (float) $r['total_debit'];
                $credit = (float) $r['total_credit'];
                $net = match ($r['account_type']) {
                    'asset'      => round($debit - $credit, 2),
                    'liability'  => round($credit - $debit, 2),
                    'equity'     => round($credit - $debit, 2),
                    default      => 0.0,
                };

                $bucket = match ($r['account_type']) {
                    'asset'      => 'assets',
                    'liability'  => 'liabilities',
                    'equity'     => 'equity',
                    default      => null,
                };
                if ($bucket === null) {
                    continue;
                }

                ${'total' . ucfirst($bucket)} += $net;
                ${$bucket}[] = [
                    'account_code'  => (string) $r['account_code'],
                    'account_label' => (string) $r['account_label'],
                    'amount'        => $net,
                ];
            }

            $totalLiabEquity = round($totalLiab + $totalEquity, 2);
            $balanced = abs(round($totalAssets - $totalLiabEquity, 2)) < 0.01;

            return [
                'meta' => [
                    'as_of'         => $asOfDate,
                    'company_id'    => $args['company_id'],
                    'currency'      => $args['currency'],
                    'generated_at'  => gmdate('c'),
                ],
                'assets'      => $assets,
                'liabilities' => $liabilities,
                'equity'      => $equity,
                'totals'      => [
                    'total_assets'      => round($totalAssets, 2),
                    'total_liabilities' => round($totalLiab, 2),
                    'total_equity'      => round($totalEquity, 2),
                    'total_liab_equity' => $totalLiabEquity,
                    'is_balanced'       => $balanced,
                ],
            ];
        }, self::CACHE_TTL, 'reports');
    }

    /**
     * دفتر روزنامه (General Journal) — تمام اسناد ثبت‌شده با فیلتر.
     *
     * @return array{
     *   meta:array<string,mixed>,
     *   entries:array<int,array<string,mixed>>
     * }
     */
    public static function generalJournal(array $args = []): array
    {
        $args = self::normalizeArgs($args);
        $args['limit']  = max(1, min(1000, (int) ($args['limit']  ?? 200)));
        $args['offset'] = max(0, (int) ($args['offset'] ?? 0));
        $cacheKey = 'gj:' . md5(json_encode($args, JSON_UNESCAPED_UNICODE));

        return Cache::remember($cacheKey, function () use ($args) {
            $where = ['1=1'];
            $params = [];

            if ($args['fiscal_period_id'] !== null) {
                $where[] = 'e.fiscal_period_id = %d';
                $params[] = $args['fiscal_period_id'];
            }
            if ($args['company_id'] !== null) {
                $where[] = 'e.company_id = %d';
                $params[] = $args['company_id'];
            }
            if ($args['date_from'] !== '') {
                $where[] = 'e.entry_date >= %s';
                $params[] = $args['date_from'];
            }
            if ($args['date_to'] !== '') {
                $where[] = 'e.entry_date <= %s';
                $params[] = $args['date_to'];
            }
            if (!empty($args['source'])) {
                $where[] = 'e.source = %s';
                $params[] = (string) $args['source'];
            }

            $clause = implode(' AND ', $where);
            $sql = "
                SELECT
                    e.id, e.entry_no, e.entry_date, e.description,
                    e.source, e.source_ref, e.status, e.currency, e.created_at
                FROM {entries} e
                WHERE {$clause}
                ORDER BY e.entry_date DESC, e.id DESC
                LIMIT %d OFFSET %d
            ";
            $params[] = $args['limit'];
            $params[] = $args['offset'];

            [$sql, $params] = self::bind($sql, $args);
            $entries = Db::query($sql, $params);

            // load lines for each entry
            $ids = array_map(static fn($r) => (int) $r['id'], $entries);
            $lines = [];
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $lineSql = "
                    SELECT l.entry_id, l.account_id, a.code AS account_code, a.label AS account_label,
                           l.debit, l.credit, l.description AS line_description
                    FROM {lines} l
                    INNER JOIN {accounts} a ON a.id = l.account_id
                    WHERE l.entry_id IN ({$placeholders})
                    ORDER BY l.entry_id ASC, l.id ASC
                ";
                $lineRows = Db::query($lineSql, $ids);
                foreach ($lineRows as $lr) {
                    $lines[(int) $lr['entry_id']][] = [
                        'account_code'  => (string) $lr['account_code'],
                        'account_label' => (string) $lr['account_label'],
                        'debit'         => round((float) $lr['debit'], 2),
                        'credit'        => round((float) $lr['credit'], 2),
                        'description'   => (string) ($lr['line_description'] ?? ''),
                    ];
                }
            }

            $out = [];
            foreach ($entries as $e) {
                $eid = (int) $e['id'];
                $out[] = [
                    'id'           => $eid,
                    'entry_no'     => (string) $e['entry_no'],
                    'entry_date'   => (string) $e['entry_date'],
                    'description'  => (string) $e['description'],
                    'source'       => (string) $e['source'],
                    'source_ref'   => (string) ($e['source_ref'] ?? ''),
                    'status'       => (string) $e['status'],
                    'currency'     => (string) ($e['currency'] ?? 'IRT'),
                    'created_at'   => (string) $e['created_at'],
                    'lines'        => $lines[$eid] ?? [],
                ];
            }

            return [
                'meta'    => [
                    'count'       => count($out),
                    'limit'       => $args['limit'],
                    'offset'      => $args['offset'],
                    'date_from'   => $args['date_from'],
                    'date_to'     => $args['date_to'],
                    'generated_at'=> gmdate('c'),
                ],
                'entries' => $out,
            ];
        }, self::CACHE_TTL, 'reports');
    }

    /**
     * دفتر معین (General Ledger) — برای یک حساب خاص.
     */
    public static function accountLedger(string $accountCode, array $args = []): array
    {
        $account = ChartOfAccounts::find($accountCode);
        $args = self::normalizeArgs($args);
        $args['account_id'] = (int) $account['id'];

        $sql = self::baseLinesSelect($args, true) . "
            SELECT
                e.entry_date, e.entry_no, e.description AS entry_description,
                l.debit, l.credit, l.description AS line_description
            FROM {lines} l
            INNER JOIN {entries} e ON e.id = l.entry_id
            WHERE l.account_id = %d
            {extra_where}
            ORDER BY e.entry_date ASC, e.id ASC
            LIMIT 1000
        ";
        [$sql, $params] = self::bind($sql, $args);
        $params[] = $args['account_id'];
        $rows = Db::query($sql, $params);

        $opening = 0.0;
        $sign    = $account['normal_side'] === 'credit' ? -1 : 1;
        $lines = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $running = 0.0;

        foreach ($rows as $r) {
            $d = (float) $r['debit'];
            $c = (float) $r['credit'];
            $totalDebit  += $d;
            $totalCredit += $c;
            $running = round($running + $sign * ($d - $c), 4);
            $lines[] = [
                'date'         => (string) $r['entry_date'],
                'entry_no'     => (string) $r['entry_no'],
                'description'  => (string) ($r['line_description'] ?: $r['entry_description']),
                'debit'        => round($d, 2),
                'credit'       => round($c, 2),
                'balance'      => $running,
            ];
        }

        return [
            'meta' => [
                'account_code'  => (string) $account['code'],
                'account_label' => (string) $account['label'],
                'normal_side'   => (string) $account['normal_side'],
                'date_from'     => $args['date_from'],
                'date_to'       => $args['date_to'],
                'generated_at'  => gmdate('c'),
            ],
            'opening' => $opening,
            'lines'   => $lines,
            'totals'  => [
                'debit'  => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'ending' => $running,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalizeArgs(array $args): array
    {
        return [
            'company_id'       => isset($args['company_id']) ? (int) $args['company_id'] : 1,
            'fiscal_period_id' => isset($args['fiscal_period_id']) ? (int) $args['fiscal_period_id'] : null,
            'date_from'        => (string) ($args['date_from'] ?? '0001-01-01'),
            'date_to'          => (string) ($args['date_to']   ?? gmdate('Y-m-d')),
            'currency'         => (string) ($args['currency'] ?? 'IRT'),
            'include_zero'     => (bool) ($args['include_zero'] ?? false),
            'source'           => (string) ($args['source'] ?? ''),
        ];
    }

    /**
     * ساخت SELECT اولیه با placeholder برای جداول.
     *
     * @return string
     */
    private static function baseLinesSelect(array $args, bool $withPlaceholders): string
    {
        return ''; // placeholder، در bind جایگزین می‌شود
    }

    /**
     * جایگزینی placeholderهای {lines}/{entries}/{accounts} و اضافه کردن WHERE.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function bind(string $sql, array $args): array
    {
        global $wpdb;
        $linesT    = $wpdb->prefix . 'ent_journal_lines';
        $entriesT  = $wpdb->prefix . 'ent_journal_entries';
        $accountsT = $wpdb->prefix . 'ent_accounts';

        $sql = strtr($sql, [
            '{lines}'    => $linesT,
            '{entries}'  => $entriesT,
            '{accounts}' => $accountsT,
        ]);

        // اضافه کردن WHERE date range به انتهای هر query که extra_where دارد
        $where = [];
        $params = [];
        if ($args['date_from'] !== '' && $args['date_to'] !== '') {
            $where[] = 'e.entry_date >= %s';
            $params[] = $args['date_from'];
            $where[] = 'e.entry_date <= %s';
            $params[] = $args['date_to'];
        }
        if (!empty($args['fiscal_period_id'])) {
            $where[] = 'e.fiscal_period_id = %d';
            $params[] = (int) $args['fiscal_period_id'];
        }
        if (!empty($args['company_id'])) {
            $where[] = 'e.company_id = %d';
            $params[] = (int) $args['company_id'];
        }
        if (!empty($where)) {
            $clause = implode(' AND ', $where);
            if (strpos($sql, '{extra_where}') !== false) {
                $sql = str_replace('{extra_where}', ' AND ' . $clause, $sql);
            } else {
                $sql .= ' WHERE ' . $clause;
            }
        } else {
            $sql = str_replace('{extra_where}', '', $sql);
        }

        // پارامترهای اولیه (limit/offset) که در خود query اضافه شده‌اند
        // نیازی به append نیست — در caller اضافه می‌شود.
        return [$sql, $params];
    }
}
