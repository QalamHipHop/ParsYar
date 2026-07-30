<?php
declare(strict_types=1);

namespace Enterprise\Modules\Erp;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

final class InventoryService
{
    public static function products(): array
    {
        return Db::getResults('products', [], 'id DESC', 200, 0);
    }

    public static function addProduct(array $data): int
    {
        $sku = sanitize_text_field((string) ($data['sku'] ?? ''));
        if ($sku === '') {
            throw new \InvalidArgumentException('sku required');
        }
        $id = Db::insert('products', [
            'sku'     => $sku,
            'name'    => sanitize_text_field((string) ($data['name'] ?? '')),
            'unit'    => sanitize_text_field((string) ($data['unit'] ?? 'عدد')),
            'cost'    => (float) ($data['cost'] ?? 0),
            'price'   => (float) ($data['price'] ?? 0),
            'stock'   => (int) ($data['stock'] ?? 0),
            'taxable' => !empty($data['taxable']) ? 1 : 1,
        ]);
        \Enterprise\Modules\Audit\Logger::log('product', $id, 'create', $data);
        do_action('enterprise_event', 'product.created', ['product_id' => $id]);
        return $id;
    }

    /**
     * تغییر موجودی با ثبت سند حسابداری.
     */
    public static function adjustStock(int $productId, int $delta, string $reason = 'adjustment'): void
    {
        global $wpdb;
        $table = Db::table('products');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET stock = stock + %d WHERE id = %d",
            $delta,
            $productId
        ));
        \Enterprise\Modules\Audit\Logger::log('product', $productId, 'stock_adjust', [
            'delta'  => $delta,
            'reason' => $reason,
        ]);
    }
}
