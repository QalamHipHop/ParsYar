<?php
declare(strict_types=1);

namespace Enterprise\Modules\Sales;

defined('ABSPATH') || exit;

use Enterprise\Db;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Str;

/**
 * بازگشت کالا / یادداشت اعتبار.
 *
 * دو نوع:
 *  - refund: بازگشت وجه (مرتبط با پرداخت)
 *  - credit_note: یادداشت اعتبار (credit memo) — برای فاکتور
 */
final class RefundService
{
    public const TYPE_REFUND      = 'refund';
    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function tableName(): string
    {
        return Db::table('refunds');
    }

    public static function types(): array
    {
        return [
            self::TYPE_REFUND      => 'بازگشت وجه',
            self::TYPE_CREDIT_NOTE => 'یادداشت اعتبار (Credit Note)',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT     => 'پیش‌نویس',
            self::STATUS_APPROVED  => 'تأییدشده',
            self::STATUS_PROCESSED => 'پردازش‌شده',
            self::STATUS_CANCELLED => 'لغوشده',
        ];
    }

    /**
     * ثبت بازگشت/یادداشت اعتبار.
     *
     * @return int
     */
    public static function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $payload = [
            'uuid'        => Str::uuid(),
            'number'      => self::nextNumber($data['type'] ?? self::TYPE_REFUND),
            'type'        => (string) ($data['type']   ?? self::TYPE_REFUND),
            'status'      => (string) ($data['status'] ?? self::STATUS_DRAFT),
            'invoice_id'  => (int)    ($data['invoice_id']  ?? 0),
            'order_id'    => (int)    ($data['order_id']    ?? 0),
            'payment_id'  => (int)    ($data['payment_id']  ?? 0),
            'customer_id' => (int)    ($data['customer_id'] ?? 0),
            'amount'      => (float)  ($data['amount']      ?? 0),
            'currency'    => (string) ($data['currency']    ?? 'IRT'),
            'reason'      => (string) ($data['reason']      ?? ''),
            'items'       => wp_json_encode($data['items'] ?? []),
            'meta'        => wp_json_encode($data['meta']  ?? []),
            'approved_by' => (int)    ($data['approved_by'] ?? 0),
            'owner_id'    => (int)    ($data['owner_id']    ?? get_current_user_id()),
            'branch_id'   => (int)    ($data['branch_id']   ?? 0),
            'company_id'  => (int)    ($data['company_id']  ?? 0),
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
        $wpdb->insert(self::tableName(), $payload, self::formats());
        $id = (int) $wpdb->insert_id;
        Logger::info('refund', 'created', ['id' => $id, 'type' => $payload['type'], 'amount' => $payload['amount']]);
        do_action('enterprise_refund_created', $id, $payload);
        return $id;
    }

    public static function approve(int $id, int $approverId = 0): bool
    {
        $ok = Db::update(self::tableName(), $id, [
            'status'      => self::STATUS_APPROVED,
            'approved_by' => $approverId ?: get_current_user_id(),
            'approved_at' => current_time('mysql'),
        ]);
        if ($ok) {
            Logger::info('refund', 'approved', ['id' => $id]);
            do_action('enterprise_refund_approved', $id);
        }
        return $ok;
    }

    /**
     * پردازش نهایی: ایجاد payment منفی / به‌روزرسانی invoice paid
     */
    public static function process(int $id): bool
    {
        $r = self::find($id);
        if (!$r || $r['status'] !== self::STATUS_APPROVED) {
            return false;
        }
        if ($r['type'] === self::TYPE_REFUND && !empty($r['payment_id'])) {
            PaymentService::refund((int) $r['payment_id'], (float) $r['amount'], (string) $r['reason']);
        }
        if (!empty($r['invoice_id'])) {
            \Enterprise\Modules\Erp\InvoiceService::recordPayment(
                (int) $r['invoice_id'],
                -(float) $r['amount']
            );
        }
        Db::update(self::tableName(), $id, [
            'status'       => self::STATUS_PROCESSED,
            'processed_at' => current_time('mysql'),
        ]);
        do_action('enterprise_refund_processed', $id);
        return true;
    }

    public static function find(int $id): ?array
    {
        $r = Db::find(self::tableName(), $id);
        return $r ?: null;
    }

    public static function nextNumber(string $type): string
    {
        global $wpdb;
        $prefix = ($type === self::TYPE_CREDIT_NOTE ? 'CN-' : 'RF-') . gmdate('Ym') . '-';
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
            '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d',
            '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d',
            '%s', '%s',
        ];
    }
}
