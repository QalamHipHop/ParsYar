<?php
declare(strict_types=1);

namespace Enterprise\Modules\Sales;

defined('ABSPATH') || exit;

use Enterprise\Db;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Str;

/**
 * پرداخت (Payment).
 *
 * می‌تواند روی فاکتور، سفارش، یا به‌صورت مستقل ثبت شود.
 * پشتیبانی از درگاه‌های ایرانی (زرین‌پال، آیدی‌پی، و ...) — متد پرداخت قابل تنظیم.
 */
final class PaymentService
{
    public const METHOD_CASH        = 'cash';
    public const METHOD_CARD        = 'card';
    public const METHOD_BANK        = 'bank_transfer';
    public const METHOD_CHEQUE      = 'cheque';
    public const METHOD_ZARINPAL    = 'zarinpal';
    public const METHOD_IDPAY       = 'idpay';
    public const METHOD_NEXTPAY     = 'nextpay';
    public const METHOD_SAMAN       = 'saman';
    public const METHOD_PASARGAD    = 'pasargad';
    public const METHOD_MELLAT      = 'mellat';
    public const METHOD_ASANPARDAKHT= 'asanpardakht';
    public const METHOD_SADERAT     = 'saderat';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED= 'cancelled';

    public static function methods(): array
    {
        return [
            self::METHOD_CASH         => 'نقدی',
            self::METHOD_CARD         => 'کارت به کارت',
            self::METHOD_BANK         => 'حوالهٔ بانکی',
            self::METHOD_CHEQUE       => 'چک',
            self::METHOD_ZARINPAL     => 'زرین‌پال',
            self::METHOD_IDPAY        => 'آیدی‌پی',
            self::METHOD_NEXTPAY      => 'نکست‌پی',
            self::METHOD_SAMAN        => 'سامان',
            self::METHOD_PASARGAD     => 'پاسارگاد',
            self::METHOD_MELLAT       => 'ملت',
            self::METHOD_ASANPARDAKHT => 'آسان‌پرداخت',
            self::METHOD_SADERAT      => 'صادرات',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING  => 'در انتظار',
            self::STATUS_PAID     => 'پرداخت‌شده',
            self::STATUS_FAILED   => 'ناموفق',
            self::STATUS_REFUNDED => 'بازگشت‌داده‌شده',
            self::STATUS_CANCELLED=> 'لغوشده',
        ];
    }

    public static function tableName(): string
    {
        return Db::table('payments');
    }

    /**
     * ثبت پرداخت.
     *
     * @return int
     */
    public static function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $payload = [
            'uuid'         => Str::uuid(),
            'number'       => self::nextNumber(),
            'method'       => (string) ($data['method'] ?? self::METHOD_CASH),
            'status'       => (string) ($data['status'] ?? self::STATUS_PAID),
            'amount'       => (float)  ($data['amount'] ?? 0),
            'currency'     => (string) ($data['currency'] ?? 'IRT'),
            'invoice_id'   => (int)    ($data['invoice_id']   ?? 0),
            'order_id'     => (int)    ($data['order_id']     ?? 0),
            'customer_id'  => (int)    ($data['customer_id']  ?? 0),
            'gateway_ref'  => (string) ($data['gateway_ref']  ?? ''),
            'tracking_no'  => (string) ($data['tracking_no']  ?? ''),
            'card_no'      => (string) ($data['card_no']      ?? ''),
            'paid_at'      => (string) ($data['paid_at']      ?? $now),
            'note'         => (string) ($data['note']         ?? ''),
            'meta'         => wp_json_encode($data['meta']    ?? []),
            'owner_id'     => (int)    ($data['owner_id']     ?? get_current_user_id()),
            'branch_id'    => (int)    ($data['branch_id']    ?? 0),
            'company_id'   => (int)    ($data['company_id']   ?? 0),
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        $wpdb->insert(self::tableName(), $payload, self::formats());
        $id = (int) $wpdb->insert_id;

        // به‌روزرسانی paid در سفارش/فاکتور
        if (!empty($payload['order_id'])) {
            OrderService::recordPayment((int) $payload['order_id'], (float) $payload['amount'], (string) $payload['method'], (string) $payload['gateway_ref']);
        }
        if (!empty($payload['invoice_id'])) {
            \Enterprise\Modules\Erp\InvoiceService::recordPayment((int) $payload['invoice_id'], (float) $payload['amount']);
        }

        Logger::info('payment', 'created', ['id' => $id, 'amount' => $payload['amount'], 'method' => $payload['method']]);
        do_action('enterprise_payment_created', $id, $payload);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        if (isset($data['meta']) && is_array($data['meta'])) {
            $data['meta'] = wp_json_encode($data['meta']);
        }
        $ok = $wpdb->update(self::tableName(), $data, ['id' => $id]);
        if ($ok) {
            Logger::info('payment', 'updated', ['id' => $id]);
            do_action('enterprise_payment_updated', $id, $data);
        }
        return (bool) $ok;
    }

    public static function find(int $id): ?array
    {
        $r = Db::find(self::tableName(), $id);
        return $r ?: null;
    }

    /**
     * بازگشت وجه.
     */
    public static function refund(int $id, float $amount, string $reason = ''): bool
    {
        $p = self::find($id);
        if (!$p) {
            return false;
        }
        $ok = self::update($id, [
            'status'         => self::STATUS_REFUNDED,
            'refunded_at'    => current_time('mysql'),
            'refund_amount'  => $amount,
            'refund_reason'  => $reason,
        ]);
        if ($ok) {
            do_action('enterprise_payment_refunded', $id, $amount, $reason);
        }
        return $ok;
    }

    public static function nextNumber(): string
    {
        global $wpdb;
        $prefix = 'PAY-' . gmdate('Ym') . '-';
        $like   = $wpdb->esc_like($prefix) . '%';
        $sql    = $wpdb->prepare("SELECT number FROM " . self::tableName() . " WHERE number LIKE %s ORDER BY id DESC LIMIT 1", $like);
        $last   = $wpdb->get_var($sql);
        $next   = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private static function formats(): array
    {
        return [
            '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%d',
            '%s', '%s', '%s', '%s', '%s', '%s',
            '%d', '%d', '%d', '%s', '%s',
        ];
    }
}
