<?php
declare(strict_types=1);

namespace Enterprise\Modules\Accounting;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

/**
 * هسته حسابداری دوطرفه (Real-time Posting).
 *
 * هر تراکنش در سیستم (فروش، خرید، انبار، حقوق) باید از طریق این کلاس
 * یک سند حسابداری استاندارد در دفتر کل ثبت کند.
 *
 * اصل: مجموع بدهکار = مجموع بستانکار (بدون استثنا)
 */
final class Ledger
{
    /**
     * ثبت یک سند حسابداری.
     *
     * @param array{entry_date:string,description?:string,source?:string,source_ref?:string,lines:array<int,array{account_code:string,debit?:float,credit?:float,description?:string}>} $entry
     * @return int entry_id
     */
    public static function post(array $entry): int
    {
        $lines = $entry['lines'] ?? [];
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Journal entry needs at least 2 lines');
        }

        $debit  = 0.0;
        $credit = 0.0;
        $resolved = [];
        foreach ($lines as $line) {
            $account = ChartOfAccounts::findByCode($line['account_code']);
            if (!$account) {
                throw new \RuntimeException('Account not found: ' . $line['account_code']);
            }
            $d = (float) ($line['debit'] ?? 0);
            $c = (float) ($line['credit'] ?? 0);
            if ($d < 0 || $c < 0) {
                throw new \InvalidArgumentException('Negative amounts not allowed');
            }
            if (($d > 0) === ($c > 0)) {
                throw new \InvalidArgumentException('Each line must be either debit or credit');
            }
            $debit  += $d;
            $credit += $c;
            $resolved[] = [
                'account_id'   => (int) $account['id'],
                'debit'        => $d,
                'credit'       => $c,
                'description'  => $line['description'] ?? null,
            ];
        }
        if (abs($debit - $credit) > 0.005) {
            throw new \RuntimeException(sprintf(
                'Unbalanced entry: debit=%.2f credit=%.2f',
                $debit,
                $credit
            ));
        }

        $entryNo = self::nextEntryNo();
        $entryId = Db::insert('journal_entries', [
            'entry_no'    => $entryNo,
            'entry_date'  => $entry['entry_date'],
            'description' => $entry['description'] ?? null,
            'source'      => $entry['source'] ?? 'manual',
            'source_ref'  => $entry['source_ref'] ?? null,
            'status'      => 'posted',
        ]);

        foreach ($resolved as $line) {
            Db::insert('journal_lines', $line + ['entry_id' => $entryId]);
        }

        \Enterprise\Modules\Audit\Logger::log('journal', $entryId, 'post', [
            'entry_no' => $entryNo,
            'debit'    => $debit,
            'credit'   => $credit,
            'source'   => $entry['source'] ?? null,
        ]);
        do_action('enterprise_event', 'journal.posted', ['entry_id' => $entryId]);
        return $entryId;
    }

    public static function getEntry(int $id): ?array
    {
        $entry = Db::getRow('journal_entries', ['id' => $id]);
        if (!$entry) {
            return null;
        }
        $entry['lines'] = Db::getResults('journal_lines', ['entry_id' => $id]);
        return $entry;
    }

    public static function trialBalance(): array
    {
        global $wpdb;
        $table = Db::table('journal_lines');
        $sql   = "SELECT a.code, a.name, a.type,
                         COALESCE(SUM(jl.debit),0)  AS total_debit,
                         COALESCE(SUM(jl.credit),0) AS total_credit
                  FROM " . Db::table('accounts') . " a
                  LEFT JOIN {$table} jl ON jl.account_id = a.id
                  GROUP BY a.id
                  ORDER BY a.code ASC";
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function nextEntryNo(): string
    {
        global $wpdb;
        $table = Db::table('journal_entries');
        $year  = gmdate('Y');
        $like  = $wpdb->esc_like("JE-{$year}-") . '%';
        $last  = $wpdb->get_var($wpdb->prepare(
            "SELECT entry_no FROM {$table} WHERE entry_no LIKE %s ORDER BY id DESC LIMIT 1",
            $like
        ));
        $next = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return sprintf('JE-%s-%05d', $year, $next);
    }
}
