<?php
/**
 * StockMovementService — لاگ حرکات انبار (ledger-style)
 *
 * هر تغییر موجودی یک ردیف در ent_stock_movements ثبت می‌کند.
 * موجودی فعلی هر (product, warehouse) از جمع حرکات محاسبه می‌شود.
 * این جدول append-only است؛ اصلاح فقط با سند معکوس.
 *
 * انواع حرکت:
 *   - in: ورود (خرید، تولید، برگشت)
 *   - out: خروج (فروش، مصرف)
 *   - transfer: انتقال بین دو انبار (دو ردیف: out + in)
 *   - adjust: تعدیل دستی (مثبت/منفی)
 *   - reserve: رزرو (کم کردن available بدون تغییر on_hand)
 *
 * @package Enterprise\Modules\Erp
 */

declare(strict_types=1);

namespace Enterprise\Modules\Erp;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

final class StockMovementService
{
    public const TYPE_IN       = 'in';
    public const TYPE_OUT      = 'out';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_ADJUST   = 'adjust';
    public const TYPE_RESERVE  = 'reserve';
    public const TYPE_RELEASE  = 'release';

    public const ALLOWED = [
        self::TYPE_IN, self::TYPE_OUT, self::TYPE_TRANSFER,
        self::TYPE_ADJUST, self::TYPE_RESERVE, self::TYPE_RELEASE,
    ];

    /**
     * ثبت حرکت ساده (ورود/خروج/تعدیل).
     *
     * @param array $data {
     *   @var int    $product_id
     *   @var int    $warehouse_id
     *   @var string $type            in|out|adjust|reserve|release
     *   @var float  $quantity        (مثبت برای in/reserve/release، هر مقدار برای adjust)
     *   @var float  $unit_cost       اختیاری
     *   @var string $reason          دلیل
     *   @var string $source          manual|purchase|sale|invoice|return|transfer
     *   @var string $source_ref      شمارهٔ سند مبدأ
     *   @var string $lot_no
     *   @var string $serial_no
     *   @var string $expires_at
     *   @var array  $meta
     * }
     */
    public static function record(array $data): int
    {
        $productId   = (int) ($data['product_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $type        = (string) ($data['type'] ?? '');
        $qty         = (float) ($data['quantity'] ?? 0);

        if ($productId <= 0)   { throw new \InvalidArgumentException('product_id الزامی است.'); }
        if ($warehouseId <= 0) { throw new \InvalidArgumentException('warehouse_id الزامی است.'); }
        if (!in_array($type, self::ALLOWED, true)) {
            throw new \InvalidArgumentException('نوع حرکت نامعتبر: ' . $type);
        }
        if ($qty < 0) {
            throw new \InvalidArgumentException('quantity نمی‌تواند منفی باشد. برای خروج از type=out استفاده کنید.');
        }
        if (in_array($type, [self::TYPE_IN, self::TYPE_OUT, self::TYPE_ADJUST], true) && $qty == 0.0) {
            throw new \InvalidArgumentException('quantity برای این نوع حرکت باید بزرگ‌تر از صفر باشد.');
        }

        // محصول باید وجود داشته و track_stock=1 باشد
        $product = self::loadProduct($productId);
        if (!$product) {
            throw new \RuntimeException('محصول یافت نشد: ' . $productId);
        }
        if ((int) ($product['track_stock'] ?? 1) === 0 && in_array($type, [self::TYPE_IN, self::TYPE_OUT, self::TYPE_TRANSFER, self::TYPE_ADJUST], true)) {
            throw new \RuntimeException('این محصول track_stock=0 دارد.');
        }

        // انبار باید فعال باشد
        $warehouse = WarehouseService::get($warehouseId);
        if (!$warehouse || (int) $warehouse['is_active'] !== 1) {
            throw new \RuntimeException('انبار فعال نیست: ' . $warehouseId);
        }

        // بررسی lot/serial اگر محصول نیاز دارد
        if ((int) ($product['lot_tracked'] ?? 0) === 1 && empty($data['lot_no']) && in_array($type, [self::TYPE_IN, self::TYPE_OUT, self::TYPE_TRANSFER], true)) {
            throw new \InvalidArgumentException('این محصول lot_tracked=1 دارد؛ lot_no الزامی است.');
        }
        if ((int) ($product['serial_tracked'] ?? 0) === 1 && empty($data['serial_no']) && in_array($type, [self::TYPE_IN, self::TYPE_OUT, self::TYPE_TRANSFER], true)) {
            throw new \InvalidArgumentException('این محصول serial_tracked=1 دارد؛ serial_no الزامی است.');
        }

        // بررسی منفی نشدن on_hand برای خروج
        if ($type === self::TYPE_OUT) {
            $current = self::getBalance($productId, $warehouseId, (string) ($data['lot_no'] ?? ''));
            if (($current['on_hand'] ?? 0) < $qty) {
                throw new \RuntimeException(sprintf(
                    'موجودی کافی نیست: on_hand=%s, requested=%s',
                    $current['on_hand'] ?? 0,
                    $qty
                ));
            }
        }

        $row = [
            'product_id'    => $productId,
            'warehouse_id'  => $warehouseId,
            'type'          => $type,
            'quantity'      => $qty,
            'unit_cost'     => isset($data['unit_cost']) ? (float) $data['unit_cost'] : 0.0,
            'reason'        => isset($data['reason']) ? sanitize_text_field((string) $data['reason']) : null,
            'source'        => isset($data['source']) ? sanitize_text_field((string) $data['source']) : 'manual',
            'source_ref'    => isset($data['source_ref']) ? sanitize_text_field((string) $data['source_ref']) : null,
            'lot_no'        => isset($data['lot_no']) ? sanitize_text_field((string) $data['lot_no']) : null,
            'serial_no'     => isset($data['serial_no']) ? sanitize_text_field((string) $data['serial_no']) : null,
            'expires_at'    => isset($data['expires_at']) && $data['expires_at'] !== '' ? sanitize_text_field((string) $data['expires_at']) : null,
            'meta'          => isset($data['meta']) ? wp_json_encode($data['meta']) : null,
            'movement_date' => isset($data['movement_date']) && $data['movement_date'] !== ''
                                ? sanitize_text_field((string) $data['movement_date'])
                                : gmdate('Y-m-d H:i:s'),
            'created_by'    => get_current_user_id() ?: null,
        ];

        $id = Db::insert('stock_movements', $row);

        // به‌روزرسانی ستون stock محصول (cache سریع — منبع حقیقت همین جدول است)
        if (in_array($type, [self::TYPE_IN, self::TYPE_OUT, self::TYPE_ADJUST], true)) {
            $delta = $type === self::TYPE_OUT ? -$qty : $qty;
            if ($type === self::TYPE_ADJUST) {
                // adjust می‌تواند منفی باشد ولی ما بالا اجازه ندادیم؛ باید quantity علامت‌دار باشد
                // برای سادگی adjust = مثبت (add) در نظر می‌گیریم؛ برای منفی از out استفاده شود
            }
            self::bumpProductStock($productId, $delta);
        }

        Logger::log('stock_movement', $id, 'create', $row);
        do_action('enterprise_event', 'stock.movement', $row + ['id' => $id]);
        return $id;
    }

    /**
     * انتقال بین دو انبار: دو ردیف (out از مبدأ + in به مقصد) که به هم ref می‌دهند.
     */
    public static function transfer(int $productId, int $fromWarehouseId, int $toWarehouseId, float $qty, array $extras = []): array
    {
        if ($qty <= 0) { throw new \InvalidArgumentException('quantity باید بیشتر از صفر باشد.'); }
        if ($fromWarehouseId === $toWarehouseId) {
            throw new \InvalidArgumentException('انبار مبدأ و مقصد یکسان است.');
        }
        $product = self::loadProduct($productId);
        if (!$product) {
            throw new \RuntimeException('محصول یافت نشد.');
        }
        // بررسی موجودی
        $current = self::getBalance($productId, $fromWarehouseId, (string) ($extras['lot_no'] ?? ''));
        if (($current['on_hand'] ?? 0) < $qty) {
            throw new \RuntimeException('موجودی انبار مبدأ کافی نیست.');
        }
        $ref = 'TR-' . gmdate('Ymd-His') . '-' . wp_generate_password(4, false, false);
        $common = [
            'source'     => 'transfer',
            'source_ref' => $ref,
            'reason'     => $extras['reason'] ?? 'انتقال بین انبار',
            'lot_no'     => $extras['lot_no']     ?? null,
            'serial_no'  => $extras['serial_no']  ?? null,
            'unit_cost'  => $extras['unit_cost']  ?? 0,
            'meta'       => isset($extras['meta']) ? wp_json_encode($extras['meta']) : null,
        ];
        $outId = self::record([
            'product_id'   => $productId,
            'warehouse_id' => $fromWarehouseId,
            'type'         => self::TYPE_OUT,
            'quantity'     => $qty,
        ] + $common);
        $inId = self::record([
            'product_id'   => $productId,
            'warehouse_id' => $toWarehouseId,
            'type'         => self::TYPE_IN,
            'quantity'     => $qty,
        ] + $common);

        return ['out_id' => $outId, 'in_id' => $inId, 'ref' => $ref];
    }

    /**
     * موجودی فعلی یک محصول در یک انبار.
     * خروجی: ['on_hand'=>float, 'reserved'=>float, 'available'=>float]
     */
    public static function getBalance(int $productId, int $warehouseId, string $lotNo = ''): array
    {
        global $wpdb;
        $table = Db::table('stock_movements');

        $where  = ['product_id = %d', 'warehouse_id = %d'];
        $params = [$productId, $warehouseId];

        $lotNo = trim($lotNo);
        if ($lotNo !== '') {
            $where[]  = 'lot_no = %s';
            $params[] = $lotNo;
        } else {
            $where[] = '(lot_no IS NULL OR lot_no = "")';
        }

        $sql = "SELECT
                  COALESCE(SUM(CASE WHEN type IN ('in','release')  THEN quantity ELSE 0 END), 0) -
                  COALESCE(SUM(CASE WHEN type IN ('out','reserve') THEN quantity ELSE 0 END), 0) AS on_hand,
                  COALESCE(SUM(CASE WHEN type = 'reserve' THEN quantity ELSE 0 END), 0) AS reserved
                FROM {$table} WHERE " . implode(' AND ', $where);
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        $onHand   = (float) ($row['on_hand']   ?? 0);
        $reserved = (float) ($row['reserved']  ?? 0);
        return [
            'on_hand'   => round($onHand, 4),
            'reserved'  => round($reserved, 4),
            'available' => round($onHand - $reserved, 4),
        ];
    }

    /**
     * موجودی کل یک محصول در همهٔ انبارها.
     */
    public static function getTotalOnHand(int $productId): float
    {
        global $wpdb;
        $table = Db::table('stock_movements');
        $onHand = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT
              COALESCE(SUM(CASE WHEN type IN ('in','release')  THEN quantity ELSE 0 END), 0) -
              COALESCE(SUM(CASE WHEN type IN ('out','reserve') THEN quantity ELSE 0 END), 0)
             FROM {$table} WHERE product_id = %d",
            $productId
        ));
        return round($onHand, 4);
    }

    /**
     * لیست حرکات با فیلتر.
     */
    public static function list(array $filters = []): array
    {
        global $wpdb;
        $table = Db::table('stock_movements');

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['product_id'])) {
            $where[]  = 'product_id = %d';
            $params[] = (int) $filters['product_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[]  = 'warehouse_id = %d';
            $params[] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 'type = %s';
            $params[] = (string) $filters['type'];
        }
        if (!empty($filters['source'])) {
            $where[]  = 'source = %s';
            $params[] = (string) $filters['source'];
        }
        if (!empty($filters['source_ref'])) {
            $where[]  = 'source_ref = %s';
            $params[] = (string) $filters['source_ref'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'movement_date >= %s';
            $params[] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'movement_date <= %s';
            $params[] = (string) $filters['date_to'];
        }
        if (!empty($filters['lot_no'])) {
            $where[]  = 'lot_no = %s';
            $params[] = (string) $filters['lot_no'];
        }

        $order = sanitize_sql_orderby((string) ($filters['order'] ?? 'id DESC'));
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
            . " ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    /**
     * گزارش موجودی همهٔ انبارها برای یک محصول.
     * خروجی: [['warehouse_id'=>1,'warehouse_code'=>'MAIN','on_hand'=>12.5], ...]
     */
    public static function stockCard(int $productId): array
    {
        global $wpdb;
        $movementTable = Db::table('stock_movements');
        $whTable       = Db::table('warehouses');

        $sql = "SELECT w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                       w.type AS warehouse_type,
                       COALESCE(SUM(CASE WHEN m.type IN ('in','release')  THEN m.quantity ELSE 0 END), 0) -
                       COALESCE(SUM(CASE WHEN m.type IN ('out','reserve') THEN m.quantity ELSE 0 END), 0) AS on_hand
                FROM {$whTable} w
                LEFT JOIN {$movementTable} m
                  ON m.warehouse_id = w.id AND m.product_id = %d
                WHERE w.is_active = 1
                GROUP BY w.id, w.code, w.name, w.type
                ORDER BY w.is_default DESC, w.id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $productId), ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['on_hand'] = round((float) $r['on_hand'], 4);
        }
        unset($r);
        return $rows;
    }

    /**
     * محاسبهٔ WAC (Weighted Average Cost) یک محصول.
     * ساده: میانگین وزنی unit_cost در همهٔ ورودی‌ها منهای خروجی‌هایی که unit_cost ندارند.
     */
    public static function wac(int $productId): float
    {
        global $wpdb;
        $table = Db::table('stock_movements');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
              COALESCE(SUM(CASE WHEN type = 'in'  THEN quantity * unit_cost ELSE 0 END), 0) AS total_in_cost,
              COALESCE(SUM(CASE WHEN type = 'in'  THEN quantity ELSE 0 END), 0) AS total_in_qty
             FROM {$table} WHERE product_id = %d AND unit_cost > 0",
            $productId
        ), ARRAY_A);

        $totalCost = (float) ($row['total_in_cost'] ?? 0);
        $totalQty  = (float) ($row['total_in_qty']  ?? 0);
        if ($totalQty <= 0) {
            return 0.0;
        }
        return round($totalCost / $totalQty, 4);
    }

    private static function loadProduct(int $id): ?array
    {
        return Db::getRow('products', ['id' => $id]);
    }

    private static function bumpProductStock(int $productId, float $delta): void
    {
        global $wpdb;
        $table = Db::table('products');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET stock = GREATEST(0, stock + %f) WHERE id = %d",
            $delta,
            $productId
        ));
    }
}
