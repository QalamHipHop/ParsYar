<?php
declare(strict_types=1);

namespace Enterprise\Modules\Sales;

defined('ABSPATH') || exit;

use Enterprise\Db;
use Enterprise\Modules\Erp\InvoiceService;
use Enterprise\Modules\Erp\InventoryService;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Str;

/**
 * سفارش فروش (Sales Order).
 *
 * Order مرحلهٔ قبل از فاکتور است. می‌تواند از معامله (Deal) ایجاد شود،
 * پس از تأیید به فاکتور تبدیل شود، و با پرداخت/ارسال/بازگشت همراه گردد.
 */
final class OrderService
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_PENDING  = 'pending';   // در انتظار تأیید/پرداخت
    public const STATUS_CONFIRMED= 'confirmed';
    public const STATUS_FULFILLED= 'fulfilled';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_CANCELLED= 'cancelled';
    public const STATUS_CLOSED   = 'closed';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT     => 'پیش‌نویس',
            self::STATUS_PENDING   => 'در انتظار',
            self::STATUS_CONFIRMED => 'تأییدشده',
            self::STATUS_FULFILLED => 'ارسال‌شده',
            self::STATUS_INVOICED  => 'فاکتورشده',
            self::STATUS_CANCELLED => 'لغوشده',
            self::STATUS_CLOSED    => 'بسته‌شده',
        ];
    }

    public static function tableName(): string
    {
        return Db::table('orders');
    }

    /**
     * جستجوی سفارش با فیلتر + صفحه‌بندی.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function search(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'id DESC'): array
    {
        global $wpdb;
        $table = self::tableName();

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like((string) $filters['q']) . '%';
            $where[]  = '(number LIKE %s OR customer_name LIKE %s)';
            $params[] = $q; $params[] = $q;
        }
        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $where[]  = 'customer_id = %d';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['currency'])) {
            $where[]  = 'currency = %s';
            $params[] = (string) $filters['currency'];
        }
        if (!empty($filters['from_date'])) {
            $where[]  = 'created_at >= %s';
            $params[] = (string) $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[]  = 'created_at <= %s';
            $params[] = (string) $filters['to_date'];
        }

        $order = sanitize_sql_orderby($order);
        $sql   = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
               . " ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = max(1, min(500, $limit));
        $params[] = max(0, $offset);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(static function ($r) {
            if (!empty($r['items']) && is_string($r['items'])) {
                $r['items'] = json_decode($r['items'], true) ?? [];
            }
            if (!empty($r['meta']) && is_string($r['meta'])) {
                $r['meta']  = json_decode($r['meta'],  true) ?? [];
            }
            return $r;
        }, $rows ?: []);
    }

    public static function count(array $filters = []): int
    {
        return count(self::search($filters, 100000, 0));
    }

    /**
     * ساخت سفارش جدید.
     *
     * @param array $data  فیلدها: customer_id, customer_name, deal_id, items, currency, totals, ...
     * @return int         شناسهٔ سفارش
     */
    public static function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $payload = [
            'uuid'         => Str::uuid(),
            'number'       => self::nextNumber(),
            'status'       => $data['status'] ?? self::STATUS_DRAFT,
            'customer_id'  => (int) ($data['customer_id']  ?? 0),
            'customer_name'=> (string) ($data['customer_name'] ?? ''),
            'deal_id'      => (int) ($data['deal_id'] ?? 0),
            'quote_id'     => (int) ($data['quote_id'] ?? 0),
            'currency'     => (string) ($data['currency'] ?? 'IRT'),
            'subtotal'     => (float) ($data['subtotal'] ?? 0),
            'discount'     => (float) ($data['discount'] ?? 0),
            'tax'          => (float) ($data['tax']      ?? 0),
            'shipping'     => (float) ($data['shipping'] ?? 0),
            'total'        => (float) ($data['total']    ?? 0),
            'paid'         => (float) ($data['paid']     ?? 0),
            'shipping_address' => (string) ($data['shipping_address'] ?? ''),
            'billing_address'  => (string) ($data['billing_address']  ?? ''),
            'notes'        => (string) ($data['notes'] ?? ''),
            'items'        => wp_json_encode($data['items'] ?? []),
            'meta'         => wp_json_encode($data['meta']  ?? []),
            'owner_id'     => (int) ($data['owner_id'] ?? get_current_user_id()),
            'branch_id'    => (int) ($data['branch_id'] ?? 0),
            'company_id'   => (int) ($data['company_id'] ?? 0),
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        $wpdb->insert(self::tableName(), $payload, self::formats());
        $id = (int) $wpdb->insert_id;
        Logger::info('order', 'created', ['id' => $id, 'number' => $payload['number'], 'total' => $payload['total']]);
        do_action('enterprise_order_created', $id, $payload);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = wp_json_encode($data['items']);
        }
        if (isset($data['meta']) && is_array($data['meta'])) {
            $data['meta'] = wp_json_encode($data['meta']);
        }
        $ok = $wpdb->update(self::tableName(), $data, ['id' => $id]);
        if ($ok) {
            Logger::info('order', 'updated', ['id' => $id]);
            do_action('enterprise_order_updated', $id, $data);
        }
        return (bool) $ok;
    }

    public static function find(int $id): ?array
    {
        $r = Db::find(self::tableName(), $id);
        return $r ?: null;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $r = Db::findBy(self::tableName(), 'uuid', $uuid);
        return $r ?: null;
    }

    public static function findByNumber(string $number): ?array
    {
        $r = Db::findBy(self::tableName(), 'number', $number);
        return $r ?: null;
    }

    /**
     * ثبت پرداخت جزئی روی سفارش.
     */
    public static function recordPayment(int $id, float $amount, string $method = '', string $reference = ''): bool
    {
        $o = self::find($id);
        if (!$o) {
            return false;
        }
        $newPaid = (float) $o['paid'] + $amount;
        if ($newPaid > (float) $o['total'] + 0.01) {
            $newPaid = (float) $o['total'];
        }
        $ok = self::update($id, ['paid' => $newPaid]);
        if ($ok) {
            Logger::info('order', 'payment', ['id' => $id, 'amount' => $amount, 'method' => $method, 'reference' => $reference]);
            do_action('enterprise_order_paid', $id, $amount, $method, $reference);
        }
        return $ok;
    }

    /**
     * تبدیل سفارش به فاکتور.
     */
    public static function toInvoice(int $id): int
    {
        $o = self::find($id);
        if (!$o) {
            return 0;
        }
        if (!empty($o['invoiced_at']) || $o['status'] === self::STATUS_INVOICED) {
            return 0;
        }
        $items = json_decode((string) $o['items'], true) ?: [];
        $invoiceId = InvoiceService::create([
            'order_id'      => $o['id'],
            'customer_id'   => (int) $o['customer_id'],
            'customer_name' => (string) $o['customer_name'],
            'currency'      => (string) $o['currency'],
            'subtotal'      => (float) $o['subtotal'],
            'discount'      => (float) $o['discount'],
            'tax'           => (float) $o['tax'],
            'shipping'      => (float) $o['shipping'],
            'total'         => (float) $o['total'],
            'items'         => $items,
            'owner_id'      => (int) $o['owner_id'],
            'branch_id'     => (int) $o['branch_id'],
            'company_id'    => (int) $o['company_id'],
        ]);
        self::update($id, [
            'status'      => self::STATUS_INVOICED,
            'invoiced_at' => current_time('mysql'),
            'invoice_id'  => $invoiceId,
        ]);
        do_action('enterprise_order_invoiced', $id, $invoiceId);
        return $invoiceId;
    }

    /**
     * لغو سفارش + بازگشت موجودی.
     */
    public static function cancel(int $id, string $reason = ''): bool
    {
        $o = self::find($id);
        if (!$o) {
            return false;
        }
        if (in_array($o['status'], [self::STATUS_CANCELLED, self::STATUS_CLOSED, self::STATUS_INVOICED], true)) {
            return false;
        }
        // بازگشت موجودی اگر تأیید شده
        if (in_array($o['status'], [self::STATUS_CONFIRMED, self::STATUS_FULFILLED], true)) {
            $items = json_decode((string) $o['items'], true) ?: [];
            foreach ($items as $it) {
                if (!empty($it['product_id']) && !empty($it['qty'])) {
                    try {
                        InventoryService::adjustStock((int) $it['product_id'], (float) $it['qty'], 'order_cancel', 'order', $id);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
        }
        self::update($id, [
            'status'         => self::STATUS_CANCELLED,
            'cancelled_at'   => current_time('mysql'),
            'cancel_reason'  => $reason,
        ]);
        do_action('enterprise_order_cancelled', $id, $reason);
        return true;
    }

    /**
     * شمارهٔ بعدی سفارش به فرمت SO-YYYYMM-####.
     */
    public static function nextNumber(): string
    {
        global $wpdb;
        $prefix = 'SO-' . gmdate('Ym') . '-';
        $like   = $wpdb->esc_like($prefix) . '%';
        $sql    = $wpdb->prepare("SELECT number FROM " . self::tableName() . " WHERE number LIKE %s ORDER BY id DESC LIMIT 1", $like);
        $last   = $wpdb->get_var($sql);
        $next   = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string,string> */
    public static function formats(): array
    {
        return [
            '%s', '%s', '%s', '%d', '%s', '%d', '%d',
            '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f',
            '%s', '%s', '%s', '%s', '%s',
            '%d', '%d', '%d', '%s', '%s',
        ];
    }
}
