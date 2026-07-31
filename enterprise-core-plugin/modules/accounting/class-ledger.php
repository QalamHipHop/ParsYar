<?php
/**
 * Ledger — موتور دفترداری دوطرفه با تضمین ریاضی
 *
 * اصل بنیادین: برای هر سند، مجموع بدهکار == مجموع بستانکار. بدون استثناء.
 * هر سند یکتا یک entry_no دارد (الگو: JE-YYYY-NNNNN).
 * پس از ثبت، سند قابل ویرایش نیست (فقط برگشت با سند معکوس).
 *
 * @package Enterprise\Modules\Accounting
 */

declare(strict_types=1);

namespace Enterprise\Modules\Accounting;

defined('ABSPATH') || exit;

use Enterprise\Modules\Accounting\ChartOfAccounts;
use Enterprise\Modules\Accounting\EmptyEntryException;
use Enterprise\Modules\Accounting\UnbalancedEntryException;
use Enterprise\Modules\Accounting\InactiveAccountException;
use Enterprise\Modules\Accounting\AccountNotFoundException;
use Enterprise\Modules\Accounting\ClosedPeriodException;
use Enterprise\Modules\Accounting\PeriodMismatchException;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class Ledger
{
    /** دقت مجاز برای تساوی بدهکار و بستانکار (۵ ریال) */
    public const TOLERANCE = 0.005;

    /** وضعیت‌های ممکن سند */
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_POSTED  = 'posted';
    public const STATUS_REVERSED = 'reversed';

    /**
     * ثبت یک سند حسابداری.
     *
     * @param array $entry {
     *   @var string $entry_date    تاریخ (Y-m-d یا Y/m/d یا timestamp)
     *   @var string $description  شرح کلی
     *   @var string $source       منبع (manual|invoice|payroll|inventory|tax)
     *   @var string $source_ref   شناسهٔ منبع (مثلاً شماره فاکتور)
     *   @var int    $fiscal_period_id   دورهٔ مالی (اختیاری؛ خودکار تشخیص داده می‌شود)
     *   @var string $currency     واحد پول (IRT پیش‌فرض)
     *   @var int    $company_id   شرکت (multi-company)
     *   @var array  $lines        لیست خطوط
     *   @var array  $meta         داده‌های اضافی
     * }
     * @return int entry_id
     *
     * @throws EmptyEntryException
     * @throws UnbalancedEntryException
     * @throws AccountNotFoundException
     * @throws InactiveAccountException
     * @throws ClosedPeriodException
     * @throws PeriodMismatchException
     */
    public static function post(array $entry): int
    {
        global $wpdb;

        $lines = $entry['lines'] ?? [];
        if (count($lines) < 2) {
            throw new EmptyEntryException();
        }

        $entryDate = self::normalizeDate($entry['entry_date'] ?? '');
        if ($entryDate === '') {
            throw new \InvalidArgumentException('entry_date is required');
        }

        // بررسی دورهٔ مالی
        $periodId = $entry['fiscal_period_id'] ?? null;
        $period = $periodId
            ? self::findPeriod($periodId)
            : self::findPeriodByDate($entryDate, (int) ($entry['company_id'] ?? 1));
        if (!$period) {
            throw new \RuntimeException(sprintf('No fiscal period found for date %s', $entryDate));
        }
        if ($period['status'] === 'closed') {
            throw new ClosedPeriodException((int) $period['id'], (string) $period['name']);
        }

        // اعتبارسنجی و حل خطوط
        $debit  = 0.0;
        $credit = 0.0;
        $resolved = [];
        foreach ($lines as $idx => $line) {
            if (!is_array($line)) {
                throw new \InvalidArgumentException("Line #{$idx} must be an array");
            }
            $accountCode = (string) ($line['account_code'] ?? '');
            if ($accountCode === '') {
                throw new \InvalidArgumentException("Line #{$idx}: account_code is required");
            }
            try {
                $account = ChartOfAccounts::find($accountCode);
            } catch (AccountNotFoundException $e) {
                throw $e;
            }
            if ((int) $account['is_active'] !== 1) {
                throw new InactiveAccountException($accountCode);
            }

            $d = round((float) ($line['debit'] ?? 0), 4);
            $c = round((float) ($line['credit'] ?? 0), 4);
            if ($d < 0 || $c < 0) {
                throw new \InvalidArgumentException("Line #{$idx}: negative amounts not allowed");
            }
            if (($d > 0) && ($c > 0)) {
                throw new \InvalidArgumentException("Line #{$idx}: line cannot be both debit and credit");
            }
            if ($d == 0.0 && $c == 0.0) {
                throw new \InvalidArgumentException("Line #{$idx}: line must have either debit or credit");
            }
            $debit  += $d;
            $credit += $c;

            $resolved[] = [
                'account_id'   => (int) $account['id'],
                'account_code' => $accountCode,
                'debit'        => $d,
                'credit'       => $c,
                'description'  => $line['description'] ?? null,
                'cost_center'  => $line['cost_center'] ?? null,
                'project_id'   => isset($line['project_id']) ? (int) $line['project_id'] : null,
                'currency'     => $line['currency'] ?? $entry['currency'] ?? 'IRT',
                'fx_rate'      => isset($line['fx_rate']) ? (float) $line['fx_rate'] : 1.0,
            ];
        }

        // تضمین ریاضی: تراز
        if (abs($debit - $credit) > self::TOLERANCE) {
            throw new UnbalancedEntryException($debit, $credit);
        }

        // تراکنش اتمیک
        $wpdb->query('START TRANSACTION');
        try {
            $entryNo = self::nextEntryNo($period['id']);
            $entryId = Db::insert('journal_entries', [
                'entry_no'        => $entryNo,
                'entry_date'      => $entryDate,
                'description'     => $entry['description'] ?? null,
                'source'          => $entry['source'] ?? 'manual',
                'source_ref'      => $entry['source_ref'] ?? null,
                'fiscal_period_id'=> (int) $period['id'],
                'company_id'      => (int) ($entry['company_id'] ?? 1),
                'branch_id'       => isset($entry['branch_id']) ? (int) $entry['branch_id'] : null,
                'currency'        => $entry['currency'] ?? 'IRT',
                'total_debit'     => $debit,
                'total_credit'    => $credit,
                'status'          => self::STATUS_POSTED,
                'meta'            => isset($entry['meta']) ? wp_json_encode($entry['meta'], JSON_UNESCAPED_UNICODE) : null,
                'posted_by'       => get_current_user_id() ?: null,
                'created_at'      => current_time('mysql'),
                'posted_at'       => current_time('mysql'),
            ]);

            foreach ($resolved as $r) {
                Db::insert('journal_lines', $r + [
                    'entry_id'  => $entryId,
                    'created_at'=> current_time('mysql'),
                ]);
            }

            // به‌روزرسانی ماندهٔ حساب‌ها (دِبت/کردیت تجمعی)
            foreach ($resolved as $r) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ent_accounts
                     SET balance_debit  = balance_debit  + %f,
                         balance_credit = balance_credit + %f,
                         updated_at     = %s
                     WHERE id = %d",
                    $r['debit'],
                    $r['credit'],
                    current_time('mysql'),
                    $r['account_id']
                ));
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        Logger::log('journal', $entryId, 'post', [
            'entry_no'  => $entryNo,
            'debit'     => $debit,
            'credit'    => $credit,
            'source'    => $entry['source'] ?? 'manual',
            'source_ref'=> $entry['source_ref'] ?? null,
            'lines'     => count($resolved),
        ]);

        do_action('enterprise_event', 'journal.posted', [
            'entry_id'  => $entryId,
            'entry_no'  => $entryNo,
            'amount'    => $debit,
            'source'    => $entry['source'] ?? 'manual',
            'source_ref'=> $entry['source_ref'] ?? null,
        ]);

        return $entryId;
    }

    /**
     * برگشت سند (سند معکوس صادر می‌کند، اصل را نمی‌تواند حذف کند).
     */
    public static function reverse(int $entryId, string $reason = ''): int
    {
        $original = self::getEntry($entryId);
        if (!$original) {
            throw new \RuntimeException("Entry {$entryId} not found");
        }
        if ($original['status'] === self::STATUS_REVERSED) {
            throw new \RuntimeException("Entry {$entryId} is already reversed");
        }
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            // صدور سند معکوس
            $reversedLines = [];
            foreach ($original['lines'] as $l) {
                $reversedLines[] = [
                    'account_code' => $l['account_code'],
                    'debit'        => (float) $l['credit'],
                    'credit'       => (float) $l['debit'],
                    'description'  => 'برگشت: ' . ($l['description'] ?? ''),
                ];
            }
            $newId = self::post([
                'entry_date'   => current_time('Y-m-d'),
                'description'  => 'برگشت سند ' . $original['entry_no'] . ' — ' . $reason,
                'source'       => 'reversal',
                'source_ref'   => (string) $entryId,
                'fiscal_period_id' => $original['fiscal_period_id'],
                'company_id'   => $original['company_id'],
                'branch_id'    => $original['branch_id'],
                'currency'     => $original['currency'],
                'lines'        => $reversedLines,
            ]);

            // علامت‌گذاری سند اصلی به‌عنوان برگشت‌شده
            Db::update('journal_entries', [
                'status'       => self::STATUS_REVERSED,
                'reversed_by'  => $newId,
                'reversed_at'  => current_time('mysql'),
            ], ['id' => $entryId]);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        Logger::log('journal', $entryId, 'reverse', [
            'reason'     => $reason,
            'reversal_id'=> $newId,
        ]);

        return $newId;
    }

    /**
     * دریافت سند با خطوط.
     */
    public static function getEntry(int $id): ?array
    {
        global $wpdb;
        $entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ent_journal_entries WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        if (!$entry) {
            return null;
        }
        $lines = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT jl.*, a.code AS account_code, a.label AS account_label, a.type AS account_type
                 FROM {$wpdb->prefix}ent_journal_lines jl
                 INNER JOIN {$wpdb->prefix}ent_accounts a ON a.id = jl.account_id
                 WHERE jl.entry_id = %d
                 ORDER BY jl.id ASC",
                $id
            ),
            ARRAY_A
        );
        $entry['lines'] = $lines ?: [];
        return $entry;
    }

    /**
     * تراز آزمایشی (Trial Balance) — کل سیستم.
     */
    public static function trialBalance(?int $companyId = null, ?int $periodId = null): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        if ($companyId !== null) {
            $where[] = 'je.company_id = %d';
            $params[] = $companyId;
        }
        if ($periodId !== null) {
            $where[] = 'je.fiscal_period_id = %d';
            $params[] = $periodId;
        }
        $where[] = "je.status = 'posted'";
        $sql = "SELECT
                  a.code,
                  a.label,
                  a.type,
                  a.nature,
                  COALESCE(SUM(jl.debit), 0)  AS total_debit,
                  COALESCE(SUM(jl.credit), 0) AS total_credit,
                  CASE WHEN a.nature = 'debit'
                       THEN COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0)
                       ELSE COALESCE(SUM(jl.credit), 0) - COALESCE(SUM(jl.debit), 0)
                  END AS balance
                FROM {$wpdb->prefix}ent_accounts a
                LEFT JOIN {$wpdb->prefix}ent_journal_lines jl ON jl.account_id = a.id
                LEFT JOIN {$wpdb->prefix}ent_journal_entries je
                       ON je.id = jl.entry_id AND " . implode(' AND ', array_slice($where, 1)) . '
                WHERE a.deleted_at IS NULL
                GROUP BY a.id
                ORDER BY a.code ASC';
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return $rows ?: [];
    }

    /**
     * دفتر روزنامه با فیلتر.
     */
    public static function journal(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['date_from'])) {
            $where[] = 'je.entry_date >= %s';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'je.entry_date <= %s';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['source'])) {
            $where[] = 'je.source = %s';
            $params[] = $filters['source'];
        }
        if (!empty($filters['company_id'])) {
            $where[] = 'je.company_id = %d';
            $params[] = (int) $filters['company_id'];
        }
        if (!empty($filters['account_code'])) {
            $where[] = 'EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'ent_journal_lines jl2
                            INNER JOIN ' . $wpdb->prefix . 'ent_accounts a2 ON a2.id = jl2.account_id
                            WHERE jl2.entry_id = je.id AND a2.code = %s)';
            $params[] = $filters['account_code'];
        }
        $sql = "SELECT je.*,
                       u.display_name AS posted_by_name,
                       fp.name AS period_name
                FROM {$wpdb->prefix}ent_journal_entries je
                LEFT JOIN {$wpdb->users} u ON u.ID = je.posted_by
                LEFT JOIN {$wpdb->prefix}ent_fiscal_periods fp ON fp.id = je.fiscal_period_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY je.entry_date DESC, je.id DESC
                LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    /**
     * صورت سود و زیان (Income Statement).
     */
    public static function incomeStatement(string $from, string $to, ?int $companyId = null): array
    {
        global $wpdb;
        $sql = "SELECT
                  a.type,
                  a.code,
                  a.label,
                  COALESCE(SUM(jl.credit), 0) - COALESCE(SUM(jl.debit), 0) AS amount
                FROM {$wpdb->prefix}ent_accounts a
                INNER JOIN {$wpdb->prefix}ent_journal_lines jl ON jl.account_id = a.id
                INNER JOIN {$wpdb->prefix}ent_journal_entries je ON je.id = jl.entry_id
                WHERE a.type IN ('revenue', 'expense')
                  AND je.status = 'posted'
                  AND je.entry_date BETWEEN %s AND %s" .
                ($companyId ? ' AND je.company_id = %d' : '') . "
                GROUP BY a.id
                ORDER BY a.type, a.code";
        $params = [$from, $to];
        if ($companyId) {
            $params[] = $companyId;
        }
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    /**
     * ترازنامه (Balance Sheet).
     */
    public static function balanceSheet(string $asOf, ?int $companyId = null): array
    {
        global $wpdb;
        $sql = "SELECT
                  a.type,
                  a.code,
                  a.label,
                  CASE WHEN a.nature = 'debit'
                       THEN COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0)
                       ELSE COALESCE(SUM(jl.credit), 0) - COALESCE(SUM(jl.debit), 0)
                  END AS balance
                FROM {$wpdb->prefix}ent_accounts a
                LEFT JOIN {$wpdb->prefix}ent_journal_lines jl ON jl.account_id = a.id
                LEFT JOIN {$wpdb->prefix}ent_journal_entries je ON je.id = jl.entry_id AND je.status = 'posted' AND je.entry_date <= %s
                WHERE a.type IN ('asset','liability','equity')" .
                ($companyId ? ' AND (je.company_id = %d OR je.company_id IS NULL)' : '') . "
                GROUP BY a.id
                ORDER BY a.type, a.code";
        $params = [$asOf];
        if ($companyId) {
            $params[] = $companyId;
        }
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    /**
     * شمارهٔ بعدی سند (در هر دورهٔ مالی شمارندهٔ مستقل).
     */
    private static function nextEntryNo(int $periodId): string
    {
        global $wpdb;
        $prefix = 'JE-' . $periodId . '-';
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT entry_no FROM {$wpdb->prefix}ent_journal_entries
             WHERE entry_no LIKE %s ORDER BY id DESC LIMIT 1",
            $wpdb->esc_like($prefix) . '%'
        ));
        $next = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return sprintf('JE-%d-%06d', $periodId, $next);
    }

    /**
     * پیدا کردن دورهٔ مالی بر اساس تاریخ.
     */
    private static function findPeriodByDate(string $date, int $companyId): ?array
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ent_fiscal_periods
                 WHERE company_id = %d AND start_date <= %s AND end_date >= %s
                 LIMIT 1",
                $companyId, $date, $date
            ),
            ARRAY_A
        ) ?: null;
    }

    private static function findPeriod(int $id): array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ent_fiscal_periods WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        if (!$row) {
            throw new \RuntimeException("Fiscal period {$id} not found");
        }
        return $row;
    }

    /**
     * نرمال‌سازی تاریخ (پشتیبانی از Y-m-d، Y/m/d، timestamp، Jalali).
     */
    private static function normalizeDate(string $date): string
    {
        if ($date === '') {
            return '';
        }
        if (is_numeric($date)) {
            return gmdate('Y-m-d', (int) $date);
        }
        // Jalali: 14XX/XX/XX
        if (preg_match('/^1[34]\d\d[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $date)) {
            $parts = preg_split('/[\/\-]/', $date);
            return \Enterprise\Jalali::toGregorian(
                (int) $parts[0], (int) $parts[1], (int) $parts[2]
            );
        }
        // Gregorian
        $ts = strtotime($date);
        if ($ts === false) {
            throw new \InvalidArgumentException('Invalid date: ' . $date);
        }
        return gmdate('Y-m-d', $ts);
    }
}
