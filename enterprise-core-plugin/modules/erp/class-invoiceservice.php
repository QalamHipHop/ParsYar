<?php
/**
 * InvoiceService — مدیریت کامل فاکتور + اقلام + پرداخت + سند حسابداری + اتصال به مؤدیان.
 *
 * @package Enterprise\Modules\Erp
 */

declare(strict_types=1);

namespace Enterprise\Modules\Erp;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Modules\Accounting\Ledger;

final class InvoiceService
{
    public const TABLE = 'invoices';
    public const STATUSES = ['draft', 'issued', 'partial', 'paid', 'overdue', 'void', 'cancelled'];

    /* ------------------------------------------------------------------ *
     *  CRUD
     * ------------------------------------------------------------------ */

    public static function all(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'id DESC'): array
    {
        global $wpdb;
        $table = Db::table(self::TABLE);

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like((string) $filters['q']) . '%';
            $where[]  = '(invoice_no LIKE %s OR customer_name LIKE %s OR customer_nid LIKE %s)';
            $params[] = $q; $params[] = $q; $params[] = $q;
        }
        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $where[]  = 'customer_record_id = %d';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['currency'])) {
            $where[]  = 'currency = %s';
            $params[] = (string) $filters['currency'];
        }
        if (!empty($filters['moodian_status'])) {
            $where[]  = 'moodian_status = %s';
            $params[] = (string) $filters['moodian_status'];
        }
        if (!empty($filters['from_date'])) {
            $where[]  = 'issue_date >= %s';
            $params[] = (string) $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[]  = 'issue_date <= %s';
            $params[] = (string) $filters['to_date'];
        }
        if (!empty($filters['overdue'])) {
            $where[] = "due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ('paid','cancelled','void')";
        }

        $order = sanitize_sql_orderby($order);
        $sql   = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
               . " ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = max(1, min(500, $limit));
        $params[] = max(0, $offset);

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map([self::class, 'decodeRow'], $rows ?: []);
    }

    public static function count(array $filters = []): int
    {
        $all = self::all($filters, 100000, 0);
        return count($all);
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow(self::TABLE, ['id' => $id]);
        return $row ? self::decodeRow($row) : null;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $row = Db::getRow(self::TABLE, ['uuid' => $uuid]);
        return $row ? self::decodeRow($row) : null;
    }

    public static function findByNumber(string $number): ?array
    {
        $row = Db::getRow(self::TABLE, ['invoice_no' => $number]);
        return $row ? self::decodeRow($row) : null;
    }

    /**
     * ایجاد فاکتور + اقلام + سند حسابداری.
     */
    public static function create(array $data): int
    {
        $data = self::normalize($data);

        $subtotal = (float) $data['subtotal'];
        $discount = (float) $data['discount'];
        $tax      = (float) $data['tax'];
        $shipping = (float) $data['shipping'];
        $total    = round($subtotal - $discount + $tax + $shipping, 2);

        $id = Db::insert(self::TABLE, [
            'uuid'                  => self::uuid(),
            'invoice_no'            => self::nextInvoiceNo(),
            'order_id'              => isset($data['order_id']) ? (int) $data['order_id'] : null,
            'customer_record_id'    => isset($data['customer_record_id']) ? (int) $data['customer_record_id'] : null,
            'customer_name'         => (string) ($data['customer_name'] ?? ''),
            'customer_nid'          => (string) ($data['customer_nid']  ?? ''),
            'customer_economic_code'=> (string) ($data['customer_economic_code'] ?? ''),
            'issue_date'            => $data['issue_date'],
            'due_date'              => $data['due_date'],
            'currency'              => $data['currency'],
            'subtotal'              => $subtotal,
            'discount'              => $discount,
            'tax'                   => $tax,
            'shipping'              => $shipping,
            'total'                 => $total,
            'paid'                  => 0,
            'status'                => $data['status'] ?: 'issued',
            'items'                 => wp_json_encode($data['items'] ?? []),
            'owner_id'              => isset($data['owner_id']) ? (int) $data['owner_id'] : null,
            'branch_id'             => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            'company_id'            => isset($data['company_id']) ? (int) $data['company_id'] : null,
        ]);

        // سند حسابداری فروش (در حالت issued)
        if ($data['status'] === 'issued' && $total > 0) {
            self::postSaleEntry($id, $subtotal, $discount, $tax, $shipping, $total, $data['issue_date']);
        }

        Logger::log('invoice', $id, 'create', $data);
        do_action('enterprise_event', 'invoice.created', ['invoice_id' => $id, 'total' => $total]);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        $data = self::normalize($data);
        $data['updated_at'] = current_time('mysql', true);
        Db::update(self::TABLE, $data, ['id' => $id]);
        Logger::log('invoice', $id, 'update', ['before' => $existing, 'after' => $data]);
        do_action('enterprise_event', 'invoice.updated', ['invoice_id' => $id]);
        return true;
    }

    public static function void(int $id, string $reason = ''): bool
    {
        $inv = self::find($id);
        if (!$inv) {
            return false;
        }
        if ($inv['status'] === 'paid') {
            throw new \RuntimeException('Cannot void paid invoice — issue refund instead');
        }
        Db::update(self::TABLE, [
            'status'     => 'void',
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id]);
        Logger::log('invoice', $id, 'void', ['reason' => $reason]);
        do_action('enterprise_event', 'invoice.voided', ['invoice_id' => $id, 'reason' => $reason]);
        return true;
    }

    public static function cancel(int $id, string $reason = ''): bool
    {
        $inv = self::find($id);
        if (!$inv) {
            return false;
        }
        Db::update(self::TABLE, [
            'status'     => 'cancelled',
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id]);
        Logger::log('invoice', $id, 'cancel', ['reason' => $reason]);
        do_action('enterprise_event', 'invoice.cancelled', ['invoice_id' => $id, 'reason' => $reason]);
        return true;
    }

    /* ------------------------------------------------------------------ *
     *  Payments + Ledger
     * ------------------------------------------------------------------ */

    public static function recordPayment(int $invoiceId, float $amount, string $method = 'cash', string $reference = ''): bool
    {
        $inv = self::find($invoiceId);
        if (!$inv) {
            return false;
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be > 0');
        }
        $newPaid = round((float) $inv['paid'] + $amount, 2);
        $status  = $newPaid >= (float) $inv['total'] ? 'paid' : 'partial';

        Db::update(self::TABLE, [
            'paid'       => $newPaid,
            'status'     => $status,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $invoiceId]);

        Ledger::post([
            'entry_date'  => gmdate('Y-m-d'),
            'description' => 'Invoice payment ' . $inv['invoice_no'] . ' (' . $method . ')',
            'source'      => 'invoice_payment',
            'source_ref'  => $inv['invoice_no'] . '-P' . $newPaid,
            'lines'       => [
                ['account_code' => self::paymentAccount($method), 'debit'  => $amount, 'description' => 'دریافت ' . $method],
                ['account_code' => '1130', 'credit' => $amount, 'description' => 'حساب مشتری'],
            ],
        ]);

        Logger::log('invoice', $invoiceId, 'payment', ['amount' => $amount, 'method' => $method, 'reference' => $reference]);
        do_action('enterprise_event', 'invoice.paid', ['invoice_id' => $invoiceId, 'amount' => $amount, 'status' => $status]);
        return true;
    }

    /**
     * سازگاری با نسخهٔ قبلی.
     */
    public static function markPaid(int $invoiceId, string $paidAt = ''): bool
    {
        $inv = self::find($invoiceId);
        if (!$inv) {
            return false;
        }
        $remaining = (float) $inv['total'] - (float) $inv['paid'];
        if ($remaining <= 0) {
            return true;
        }
        return self::recordPayment($invoiceId, $remaining, 'manual', '');
    }

    /* ------------------------------------------------------------------ *
     *  Stats
     * ------------------------------------------------------------------ */

    public static function overdue(int $limit = 100): array
    {
        return self::all(['overdue' => 1], max(1, min(500, $limit)));
    }

    public static function summary(?string $fromDate = null, ?string $toDate = null): array
    {
        global $wpdb;
        $table = Db::table(self::TABLE);
        $where  = ['1=1'];
        $params = [];
        if ($fromDate) { $where[] = 'issue_date >= %s'; $params[] = $fromDate; }
        if ($toDate)   { $where[] = 'issue_date <= %s'; $params[] = $toDate;   }

        $sql = "SELECT
                  COUNT(*) AS total,
                  SUM(CASE WHEN status='paid'    THEN total ELSE 0 END) AS paid_total,
                  SUM(CASE WHEN status='issued'  THEN total ELSE 0 END) AS issued_total,
                  SUM(CASE WHEN status='partial' THEN total ELSE 0 END) AS partial_total,
                  SUM(CASE WHEN status='overdue' THEN total ELSE 0 END) AS overdue_total,
                  SUM(tax) AS total_tax,
                  SUM(CASE WHEN moodian_status='sent' THEN 1 ELSE 0 END) AS moodian_sent
                FROM {$table} WHERE " . implode(' AND ', $where);
        $row = $params ? $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_row($sql, ARRAY_A);
        return [
            'total'         => (int)    ($row['total']         ?? 0),
            'paid_total'    => (float)  ($row['paid_total']    ?? 0),
            'issued_total'  => (float)  ($row['issued_total']  ?? 0),
            'partial_total' => (float)  ($row['partial_total'] ?? 0),
            'overdue_total' => (float)  ($row['overdue_total'] ?? 0),
            'total_tax'     => (float)  ($row['total_tax']     ?? 0),
            'moodian_sent'  => (int)    ($row['moodian_sent']  ?? 0),
        ];
    }

    /* ------------------------------------------------------------------ *
     *  Moodian
     * ------------------------------------------------------------------ */

    public static function setMoodianResult(int $invoiceId, string $status, ?string $reference, ?string $error): bool
    {
        Db::update(self::TABLE, [
            'moodian_status'    => $status,
            'moodian_reference' => $reference,
            'moodian_error'     => $error,
            'moodian_sent_at'   => $status === 'sent' ? current_time('mysql', true) : null,
            'updated_at'        => current_time('mysql', true),
        ], ['id' => $invoiceId]);
        return true;
    }

    public static function pendingMoodian(int $limit = 100): array
    {
        global $wpdb;
        $table = Db::table(self::TABLE);
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status IN ('issued','partial','paid')
               AND (moodian_status IS NULL OR moodian_status = 'failed')
             ORDER BY id ASC LIMIT %d",
            max(1, min(500, $limit))
        ), ARRAY_A);
        return array_map([self::class, 'decodeRow'], $rows ?: []);
    }

    /* ------------------------------------------------------------------ *
     *  Helpers
     * ------------------------------------------------------------------ */

    private static function postSaleEntry(int $invoiceId, float $subtotal, float $discount, float $tax, float $shipping, float $total, string $date): void
    {
        $inv = self::find($invoiceId);
        $lines = [
            ['account_code' => '1130', 'debit' => $total, 'description' => 'حساب مشتری'],
        ];
        if ($subtotal > 0) {
            $lines[] = ['account_code' => '4100', 'credit' => $subtotal, 'description' => 'فروش'];
        }
        if ($discount > 0) {
            $lines[] = ['account_code' => '4190', 'debit'  => $discount, 'description' => 'تخفیف فروش'];
        }
        if ($tax > 0) {
            $lines[] = ['account_code' => '2110', 'credit' => $tax, 'description' => 'مالیات بر ارزش افزوده'];
        }
        if ($shipping > 0) {
            $lines[] = ['account_code' => '4200', 'credit' => $shipping, 'description' => 'هزینه حمل'];
        }
        Ledger::post([
            'entry_date'  => $date,
            'description' => 'Invoice ' . ($inv['invoice_no'] ?? (string) $invoiceId),
            'source'      => 'invoice',
            'source_ref'  => (string) ($inv['invoice_no'] ?? $invoiceId),
            'lines'       => $lines,
        ]);
    }

    private static function paymentAccount(string $method): string
    {
        return match (strtolower($method)) {
            'cash'        => '1110',
            'bank', 'card'=> '1100',
            'check'       => '1140',
            'pos'         => '1100',
            'online'      => '1100',
            'wallet'      => '1150',
            default       => '1100',
        };
    }

    private static function normalize(array $data): array
    {
        $defaults = [
            'issue_date' => gmdate('Y-m-d'),
            'currency'   => 'IRT',
            'subtotal'   => 0,
            'discount'   => 0,
            'tax'        => 0,
            'shipping'   => 0,
            'status'     => 'issued',
        ];
        $data = array_merge($defaults, array_filter($data, static fn($v) => $v !== null));
        $data['subtotal'] = (float) $data['subtotal'];
        $data['discount'] = (float) $data['discount'];
        $data['tax']      = (float) $data['tax'];
        $data['shipping'] = (float) $data['shipping'];
        if (!in_array($data['status'], self::STATUSES, true)) {
            $data['status'] = 'issued';
        }
        $data['currency'] = strtoupper(substr((string) $data['currency'], 0, 3));
        $data['issue_date'] = (string) $data['issue_date'];
        $data['due_date']   = !empty($data['due_date']) ? (string) $data['due_date'] : null;
        return $data;
    }

    private static function decodeRow(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        if (!empty($row['items']) && is_string($row['items'])) {
            $d = json_decode($row['items'], true);
            $row['items'] = $d ?? [];
        } else {
            $row['items'] = [];
        }
        return $row;
    }

    public static function nextInvoiceNo(): string
    {
        global $wpdb;
        $table = Db::table(self::TABLE);
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

    private static function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
