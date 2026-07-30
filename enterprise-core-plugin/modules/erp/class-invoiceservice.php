<?php
declare(strict_types=1);

namespace Enterprise\Modules\Erp;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Accounting\Ledger;

/**
 * مدیریت فاکتور فروش + ثبت خودکار سند حسابداری.
 */
final class InvoiceService
{
    public static function all(): array
    {
        return Db::getResults('invoices', [], 'id DESC', 200, 0);
    }

    public static function create(array $data): int
    {
        $subtotal = (float) ($data['subtotal'] ?? 0);
        $tax      = (float) ($data['tax'] ?? 0);
        $total    = $subtotal + $tax;
        $no       = self::nextInvoiceNo();

        $id = Db::insert('invoices', [
            'invoice_no'         => $no,
            'customer_record_id' => isset($data['customer_record_id']) ? (int) $data['customer_record_id'] : null,
            'issue_date'         => sanitize_text_field((string) ($data['issue_date'] ?? gmdate('Y-m-d'))),
            'due_date'           => sanitize_text_field((string) ($data['due_date'] ?? '')) ?: null,
            'subtotal'           => $subtotal,
            'tax'                => $tax,
            'total'              => $total,
            'status'             => 'issued',
        ]);

        // ثبت سند حسابداری
        Ledger::post([
            'entry_date'  => $data['issue_date'] ?? gmdate('Y-m-d'),
            'description' => 'Invoice ' . $no,
            'source'      => 'invoice',
            'source_ref'  => $no,
            'lines'       => [
                ['account_code' => '1130', 'debit' => $total, 'description' => 'حساب مشتری'],
                ['account_code' => '4100', 'credit' => $subtotal, 'description' => 'فروش'],
                ['account_code' => '2110', 'credit' => $tax, 'description' => 'مالیات بر ارزش افزوده'],
            ],
        ]);

        \Enterprise\Modules\Audit\Logger::log('invoice', $id, 'create', $data);
        do_action('enterprise_event', 'invoice.created', ['invoice_id' => $id, 'total' => $total]);
        return $id;
    }

    public static function markPaid(int $invoiceId, string $paidAt): void
    {
        global $wpdb;
        $wpdb->update(Db::table('invoices'), ['status' => 'paid'], ['id' => $invoiceId]);
        $inv = Db::getRow('invoices', ['id' => $invoiceId]);
        if (!$inv) {
            return;
        }
        Ledger::post([
            'entry_date'  => $paidAt,
            'description' => 'Invoice paid ' . $inv['invoice_no'],
            'source'      => 'invoice',
            'source_ref'  => $inv['invoice_no'],
            'lines'       => [
                ['account_code' => '1100', 'debit'  => (float) $inv['total']],
                ['account_code' => '1130', 'credit' => (float) $inv['total']],
            ],
        ]);
        do_action('enterprise_event', 'invoice.paid', ['invoice_id' => $invoiceId]);
    }

    private static function nextInvoiceNo(): string
    {
        global $wpdb;
        $table = Db::table('invoices');
        $year  = gmdate('Y');
        $like  = $wpdb->esc_like("INV-{$year}-") . '%';
        $last  = $wpdb->get_var($wpdb->prepare(
            "SELECT invoice_no FROM {$table} WHERE invoice_no LIKE %s ORDER BY id DESC LIMIT 1",
            $like
        ));
        $next = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return sprintf('INV-%s-%05d', $year, $next);
    }
}
