<?php
/**
 * InventoryService — لایهٔ سرویس محصولات + انبار + حرکات.
 *
 * این سرویس facade‌ای است روی ProductService + WarehouseService + StockMovementService
 * تا API عمودی و ساده بدهد.
 *
 * @package Enterprise\Modules\Erp
 */

declare(strict_types=1);

namespace Enterprise\Modules\Erp;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

final class InventoryService
{
    public const TABLE_PRODUCTS = 'products';
    public const PRODUCT_TYPES  = ['physical', 'service', 'digital', 'subscription', 'bundle'];
    public const COST_METHODS   = ['fifo', 'lifo', 'wac', 'specific'];

    /* ------------------------------------------------------------------ *
     *  Products
     * ------------------------------------------------------------------ */

    public static function products(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'id DESC'): array
    {
        global $wpdb;
        $table = Db::table(self::TABLE_PRODUCTS);

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like((string) $filters['q']) . '%';
            $where[]  = '(sku LIKE %s OR name LIKE %s OR name_en LIKE %s OR barcode LIKE %s OR brand LIKE %s)';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }
        if (!empty($filters['category_id'])) {
            $where[]  = 'category_id = %d';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['brand'])) {
            $where[]  = 'brand = %s';
            $params[] = (string) $filters['brand'];
        }
        if (!empty($filters['product_type'])) {
            $where[]  = 'product_type = %s';
            $params[] = (string) $filters['product_type'];
        }
        if (isset($filters['is_active'])) {
            $where[]  = 'is_active = %d';
            $params[] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['track_stock']) && (int) $filters['track_stock'] === 1) {
            $where[] = 'track_stock = 1';
        }
        if (!empty($filters['low_stock'])) {
            $where[] = 'reorder_point > 0 AND stock <= reorder_point';
        }

        $order = sanitize_sql_orderby($order);
        $sql   = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
               . " ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = max(1, min(500, $limit));
        $params[] = max(0, $offset);

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map([self::class, 'decodeRow'], $rows ?: []);
    }

    public static function countProducts(array $filters = []): int
    {
        $all = self::products($filters, 100000, 0);
        return count($all);
    }

    public static function findProduct(int $id): ?array
    {
        $row = Db::getRow(self::TABLE_PRODUCTS, ['id' => $id]);
        return $row ? self::decodeRow($row) : null;
    }

    public static function findProductBySku(string $sku): ?array
    {
        $row = Db::getRow(self::TABLE_PRODUCTS, ['sku' => $sku]);
        return $row ? self::decodeRow($row) : null;
    }

    public static function findProductByBarcode(string $barcode): ?array
    {
        $row = Db::getRow(self::TABLE_PRODUCTS, ['barcode' => $barcode]);
        return $row ? self::decodeRow($row) : null;
    }

    public static function createProduct(array $data): int
    {
        $data = self::normalizeProduct($data);
        if ($data['sku'] === '') {
            throw new \InvalidArgumentException('sku required');
        }
        if (!in_array($data['product_type'], self::PRODUCT_TYPES, true)) {
            $data['product_type'] = 'physical';
        }
        if (!in_array($data['cost_method'], self::COST_METHODS, true)) {
            $data['cost_method'] = 'wac';
        }

        $data['created_at'] = current_time('mysql', true);
        $data['updated_at'] = current_time('mysql', true);

        $id = Db::insert(self::TABLE_PRODUCTS, $data);
        Logger::log('product', $id, 'create', $data);
        do_action('enterprise_event', 'product.created', ['product_id' => $id, 'data' => $data]);
        return $id;
    }

    public static function updateProduct(int $id, array $data): bool
    {
        $existing = self::findProduct($id);
        if (!$existing) {
            return false;
        }
        $data = self::normalizeProduct($data);
        $data['updated_at'] = current_time('mysql', true);

        Db::update(self::TABLE_PRODUCTS, $data, ['id' => $id]);
        Logger::log('product', $id, 'update', ['before' => $existing, 'after' => $data]);
        do_action('enterprise_event', 'product.updated', ['product_id' => $id, 'data' => $data]);
        return true;
    }

    public static function deleteProduct(int $id, bool $soft = true): bool
    {
        $existing = self::findProduct($id);
        if (!$existing) {
            return false;
        }
        if ($soft) {
            Db::update(self::TABLE_PRODUCTS, [
                'is_active'   => 0,
                'updated_at'  => current_time('mysql', true),
            ], ['id' => $id]);
            Logger::log('product', $id, 'soft_delete', []);
            return true;
        }
        Db::delete(self::TABLE_PRODUCTS, ['id' => $id]);
        Logger::log('product', $id, 'hard_delete', []);
        return true;
    }

    /**
     * تنظیم مستقیم موجودی (با ثبت حرکت).
     */
    public static function adjustStock(int $productId, int $delta, string $reason = 'adjustment', ?int $warehouseId = null, ?int $userId = null): int
    {
        $product = self::findProduct($productId);
        if (!$product) {
            throw new \RuntimeException('Product not found');
        }
        if (!$product['track_stock']) {
            throw new \RuntimeException('Product does not track stock');
        }
        $warehouseId = $warehouseId ?: (int) (WarehouseService::getDefault()['id'] ?? 0);
        if ($warehouseId <= 0) {
            throw new \RuntimeException('No warehouse available');
        }
        return StockMovementService::record([
            'product_id'    => $productId,
            'warehouse_id'  => $warehouseId,
            'type'          => $delta >= 0 ? 'in' : 'out',
            'quantity'      => abs($delta),
            'unit_cost'     => (float) ($product['cost'] ?? 0),
            'reason'        => $reason,
            'source'        => 'manual',
            'created_by'    => $userId,
        ]);
    }

    /**
     * انتقال بین دو انبار.
     */
    public static function transferStock(int $productId, int $fromWarehouse, int $toWarehouse, float $qty, array $extras = []): array
    {
        return StockMovementService::transfer($productId, $fromWarehouse, $toWarehouse, $qty, $extras);
    }

    public static function getStockBalance(int $productId, ?int $warehouseId = null): array
    {
        if ($warehouseId !== null) {
            return StockMovementService::getBalance($productId, $warehouseId);
        }
        return [
            'product_id' => $productId,
            'on_hand'    => StockMovementService::getTotalOnHand($productId),
        ];
    }

    public static function productStockCard(int $productId): array
    {
        return StockMovementService::stockCard($productId);
    }

    public static function productWac(int $productId): float
    {
        return StockMovementService::wac($productId);
    }

    /**
     * KPI — محصولات کم‌موجود.
     */
    public static function lowStock(int $limit = 50): array
    {
        global $wpdb;
        $table = Db::table(self::TABLE_PRODUCTS);
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT id, sku, name, stock, reorder_point, min_stock
             FROM {$table}
             WHERE track_stock = 1 AND is_active = 1 AND reorder_point > 0 AND stock <= reorder_point
             ORDER BY (stock / GREATEST(reorder_point,1)) ASC
             LIMIT %d",
            max(1, min(500, $limit))
        ), ARRAY_A);
        return $rows ?: [];
    }

    public static function inventorySummary(int $companyId = 1): array
    {
        global $wpdb;
        $products = Db::table(self::TABLE_PRODUCTS);
        $stats    = $wpdb->get_row(
            "SELECT COUNT(*) AS total_products,
                    SUM(CASE WHEN track_stock = 1 THEN 1 ELSE 0 END) AS stock_tracked,
                    SUM(CASE WHEN reorder_point > 0 AND stock <= reorder_point THEN 1 ELSE 0 END) AS low_stock,
                    SUM(stock * cost) AS inventory_value
             FROM {$products} WHERE is_active = 1",
            ARRAY_A
        );
        return [
            'total_products'  => (int) ($stats['total_products'] ?? 0),
            'stock_tracked'   => (int) ($stats['stock_tracked']  ?? 0),
            'low_stock_count' => (int) ($stats['low_stock']      ?? 0),
            'inventory_value' => (float) ($stats['inventory_value'] ?? 0),
        ];
    }

    /* ------------------------------------------------------------------ *
     *  Helpers
     * ------------------------------------------------------------------ */

    private static function normalizeProduct(array $data): array
    {
        $map = [
            'sku'           => 'sanitize_text_field',
            'barcode'       => 'sanitize_text_field',
            'name'          => 'sanitize_text_field',
            'name_en'       => 'sanitize_text_field',
            'description'   => 'wp_kses_post',
            'brand'         => 'sanitize_text_field',
            'unit'          => 'sanitize_text_field',
            'unit_symbol'   => 'sanitize_text_field',
            'image_url'     => 'esc_url_raw',
            'product_type'  => 'sanitize_text_field',
            'cost_method'   => 'sanitize_text_field',
            'currency'      => 'sanitize_text_field',
        ];
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (isset($map[$k]) && is_string($v)) {
                $out[$k] = call_user_func($map[$k], $v);
            } elseif (in_array($k, ['tags', 'custom_fields'], true) && (is_array($v) || is_object($v))) {
                $out[$k] = wp_json_encode($v);
            } else {
                $out[$k] = $v;
            }
        }
        if (empty($out['sku'])) {
            throw new \InvalidArgumentException('sku required');
        }
        if (empty($out['name'])) {
            throw new \InvalidArgumentException('name required');
        }
        $out['is_active']  = isset($out['is_active'])  ? (int) (bool) $out['is_active']  : 1;
        $out['taxable']    = isset($out['taxable'])    ? (int) (bool) $out['taxable']    : 1;
        $out['track_stock']= isset($out['track_stock'])? (int) (bool) $out['track_stock']: 1;
        return $out;
    }

    private static function decodeRow(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        foreach (['tags', 'custom_fields'] as $j) {
            if (!empty($row[$j]) && is_string($row[$j])) {
                $d = json_decode($row[$j], true);
                $row[$j] = $d ?? null;
            } else {
                $row[$j] = null;
            }
        }
        return $row;
    }
}
