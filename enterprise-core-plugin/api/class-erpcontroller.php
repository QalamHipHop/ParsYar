<?php
/**
 * ERP REST Controller — کنترلر REST ماژول ERP (محصول + انبار + فاکتور + سفارش + پرداخت + بازپرداخت).
 *
 * Endpoints:
 *   /erp/products, /erp/products/{id}, /erp/products/{id}/stock, /erp/products/{id}/stock-card
 *   /erp/products/{id}/wac, /erp/products/low-stock, /erp/products/summary
 *   /erp/categories (CRUD + tree)
 *   /erp/warehouses (CRUD + default + summary)
 *   /erp/stock-movements (list, record, transfer)
 *   /erp/invoices (CRUD + payment + void + cancel + moodian actions + summary)
 *   /erp/orders (CRUD + confirm + fulfill + cancel + to-invoice)
 *   /erp/payments (CRUD + refund)
 *   /erp/refunds (CRUD + approve + process)
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Erp\InventoryService;
use Enterprise\Modules\Erp\InvoiceService;
use Enterprise\Modules\Erp\WarehouseService;
use Enterprise\Modules\Erp\ProductCategoryService;
use Enterprise\Modules\Erp\StockMovementService;
use Enterprise\Modules\Sales\OrderService;
use Enterprise\Modules\Sales\PaymentService;
use Enterprise\Modules\Sales\RefundService;

final class ErpController
{
    /* ==================================================================
     *  PRODUCTS
     * ================================================================== */

    public static function productsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'category_id', 'brand', 'product_type', 'is_active', 'track_stock', 'low_stock']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = InventoryService::products($filters, max(1, min(500, $limit)), max(0, $offset));
        $total   = InventoryService::countProducts($filters);
        return rest_ensure_response([
            'data' => $rows,
            'meta' => [
                'total'    => $total,
                'per_page' => max(1, min(500, $limit)),
                'page'     => (int) ($req->get_param('page') ?? 0),
                'types'    => InventoryService::PRODUCT_TYPES,
            ],
        ]);
    }

    public static function productsShow(WP_REST_Request $req)
    {
        $id   = (int) $req->get_param('id');
        $sku  = (string) ($req->get_param('sku') ?? '');
        $bc   = (string) ($req->get_param('barcode') ?? '');
        $row  = $id > 0
            ? InventoryService::findProduct($id)
            : ($sku !== '' ? InventoryService::findProductBySku($sku)
            : ($bc   !== '' ? InventoryService::findProductByBarcode($bc) : null));
        if (!$row) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }
        $row['stock_balance'] = InventoryService::getStockBalance($id);
        $row['wac']           = InventoryService::productWac($id);
        return rest_ensure_response($row);
    }

    public static function productsStore(WP_REST_Request $req)
    {
        try {
            $id = InventoryService::createProduct((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('product.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'product' => InventoryService::findProduct($id)], 201);
    }

    public static function productsUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!InventoryService::findProduct($id)) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }
        try {
            $ok = InventoryService::updateProduct($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('product.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'product' => InventoryService::findProduct($id)]);
    }

    public static function productsDestroy(WP_REST_Request $req)
    {
        $id    = (int) $req->get_param('id');
        $force = (bool) $req->get_param('force');
        $ok    = InventoryService::deleteProduct($id, !$force);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function productsStockAdjust(WP_REST_Request $req)
    {
        $id   = (int) $req->get_param('id');
        $data = (array) $req->get_json_params();
        $delta = (int) ($data['delta'] ?? 0);
        $reason = sanitize_text_field((string) ($data['reason'] ?? 'adjustment'));
        $warehouseId = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null;
        try {
            $mid = InventoryService::adjustStock($id, $delta, $reason, $warehouseId, get_current_user_id() ?: null);
        } catch (\Throwable $e) {
            return new WP_Error('stock.failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => true, 'movement_id' => $mid, 'balance' => InventoryService::getStockBalance($id)]);
    }

    public static function productsStockCard(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        return rest_ensure_response([
            'product_id' => $id,
            'movements'  => InventoryService::productStockCard($id),
            'balance'    => InventoryService::getStockBalance($id),
        ]);
    }

    public static function productsWac(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        return rest_ensure_response([
            'product_id' => $id,
            'wac'        => InventoryService::productWac($id),
        ]);
    }

    public static function productsLowStock(WP_REST_Request $req)
    {
        return rest_ensure_response(InventoryService::lowStock((int) ($req->get_param('limit') ?? 50)));
    }

    public static function productsSummary()
    {
        return rest_ensure_response(InventoryService::inventorySummary());
    }

    /* ==================================================================
     *  PRODUCT CATEGORIES
     * ================================================================== */

    public static function categoriesIndex(WP_REST_Request $req)
    {
        $active = (bool) $req->get_param('active_only');
        $tree   = (bool) $req->get_param('tree');
        if ($tree) {
            return rest_ensure_response(ProductCategoryService::tree());
        }
        return rest_ensure_response(ProductCategoryService::listAll($active));
    }

    public static function categoriesShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = ProductCategoryService::get($id);
        if (!$row) {
            return new WP_Error('not_found', 'Category not found', ['status' => 404]);
        }
        $row['breadcrumb'] = ProductCategoryService::breadcrumb($id);
        return rest_ensure_response($row);
    }

    public static function categoriesStore(WP_REST_Request $req)
    {
        try {
            $id = ProductCategoryService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('category.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'category' => ProductCategoryService::get($id)], 201);
    }

    public static function categoriesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!ProductCategoryService::get($id)) {
            return new WP_Error('not_found', 'Category not found', ['status' => 404]);
        }
        try {
            $ok = ProductCategoryService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('category.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'category' => ProductCategoryService::get($id)]);
    }

    public static function categoriesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = ProductCategoryService::delete($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    /* ==================================================================
     *  WAREHOUSES
     * ================================================================== */

    public static function warehousesIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['company_id', 'branch_id', 'type', 'is_active', 'search']);
        $rows    = WarehouseService::list($filters);
        return rest_ensure_response(['data' => $rows, 'meta' => ['total' => count($rows)]]);
    }

    public static function warehousesShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = WarehouseService::get($id);
        if (!$row) {
            return new WP_Error('not_found', 'Warehouse not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function warehousesStore(WP_REST_Request $req)
    {
        try {
            $id = WarehouseService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('warehouse.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'warehouse' => WarehouseService::get($id)], 201);
    }

    public static function warehousesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!WarehouseService::get($id)) {
            return new WP_Error('not_found', 'Warehouse not found', ['status' => 404]);
        }
        try {
            $ok = WarehouseService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('warehouse.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'warehouse' => WarehouseService::get($id)]);
    }

    public static function warehousesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $active = (bool) $req->get_param('activate');
        $ok = $active ? WarehouseService::activate($id) : WarehouseService::deactivate($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function warehousesSetDefault(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = WarehouseService::setDefault($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function warehousesDefault(WP_REST_Request $req)
    {
        $companyId = (int) ($req->get_param('company_id') ?? 1);
        $row = WarehouseService::getDefault($companyId);
        if (!$row) {
            return new WP_Error('not_found', 'No default warehouse', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function warehousesSummary(WP_REST_Request $req)
    {
        $companyId = (int) ($req->get_param('company_id') ?? 1);
        return rest_ensure_response(WarehouseService::summary($companyId));
    }

    /* ==================================================================
     *  STOCK MOVEMENTS
     * ================================================================== */

    public static function stockMovementsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['product_id', 'warehouse_id', 'type', 'from_date', 'to_date']);
        $limit   = (int) ($req->get_param('per_page') ?? 100);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = StockMovementService::list($filters, max(1, min(500, $limit)), max(0, $offset));
        return rest_ensure_response(['data' => $rows, 'meta' => ['total' => count($rows)]]);
    }

    public static function stockMovementsStore(WP_REST_Request $req)
    {
        try {
            $id = StockMovementService::record((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('stock.failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id], 201);
    }

    public static function stockMovementsTransfer(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        foreach (['product_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity'] as $k) {
            if (empty($data[$k])) {
                return new WP_Error('invalid_input', $k . ' required', ['status' => 400]);
            }
        }
        try {
            $res = StockMovementService::transfer(
                (int) $data['product_id'],
                (int) $data['from_warehouse_id'],
                (int) $data['to_warehouse_id'],
                (float) $data['quantity'],
                array_diff_key($data, array_flip(['product_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity']))
            );
        } catch (\Throwable $e) {
            return new WP_Error('transfer.failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response($res);
    }

    /* ==================================================================
     *  INVOICES
     * ================================================================== */

    public static function invoicesIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'status', 'customer_id', 'currency', 'moodian_status', 'from_date', 'to_date', 'overdue']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = InvoiceService::all($filters, max(1, min(500, $limit)), max(0, $offset));
        $total   = InvoiceService::count($filters);
        return rest_ensure_response([
            'data' => $rows,
            'meta' => [
                'total'    => $total,
                'per_page' => max(1, min(500, $limit)),
                'statuses' => InvoiceService::STATUSES,
            ],
        ]);
    }

    public static function invoicesShow(WP_REST_Request $req)
    {
        $id  = (int) $req->get_param('id');
        $no  = (string) ($req->get_param('invoice_no') ?? '');
        $row = $id > 0
            ? InvoiceService::find($id)
            : ($no !== '' ? InvoiceService::findByNumber($no) : null);
        if (!$row) {
            return new WP_Error('not_found', 'Invoice not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function invoicesStore(WP_REST_Request $req)
    {
        try {
            $id = InvoiceService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('invoice.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'invoice' => InvoiceService::find($id)], 201);
    }

    public static function invoicesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!InvoiceService::find($id)) {
            return new WP_Error('not_found', 'Invoice not found', ['status' => 404]);
        }
        try {
            $ok = InvoiceService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('invoice.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'invoice' => InvoiceService::find($id)]);
    }

    public static function invoicesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $reason = sanitize_text_field((string) ($req->get_json_params()['reason'] ?? ''));
        try {
            $ok = InvoiceService::void($id, $reason);
        } catch (\Throwable $e) {
            return new WP_Error('invoice.void_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function invoicesPay(WP_REST_Request $req)
    {
        $id     = (int) $req->get_param('id');
        $data   = (array) $req->get_json_params();
        $amount = (float) ($data['amount'] ?? 0);
        $method = sanitize_text_field((string) ($data['method'] ?? 'cash'));
        $ref    = sanitize_text_field((string) ($data['reference'] ?? ''));
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'amount > 0 required', ['status' => 400]);
        }
        try {
            $ok = InvoiceService::recordPayment($id, $amount, $method, $ref);
        } catch (\Throwable $e) {
            return new WP_Error('payment.failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'invoice' => InvoiceService::find($id)]);
    }

    public static function invoicesCancel(WP_REST_Request $req)
    {
        $id     = (int) $req->get_param('id');
        $reason = sanitize_text_field((string) ($req->get_json_params()['reason'] ?? ''));
        $ok     = InvoiceService::cancel($id, $reason);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function invoicesOverdue(WP_REST_Request $req)
    {
        return rest_ensure_response(InvoiceService::overdue((int) ($req->get_param('limit') ?? 100)));
    }

    public static function invoicesSummary(WP_REST_Request $req)
    {
        return rest_ensure_response(InvoiceService::summary(
            $req->get_param('from_date') ?: null,
            $req->get_param('to_date') ?: null
        ));
    }

    public static function invoicesPendingMoodian(WP_REST_Request $req)
    {
        return rest_ensure_response(InvoiceService::pendingMoodian((int) ($req->get_param('limit') ?? 100)));
    }

    public static function invoicesSetMoodian(WP_REST_Request $req)
    {
        $id     = (int) $req->get_param('id');
        $data   = (array) $req->get_json_params();
        $status = sanitize_text_field((string) ($data['status'] ?? 'sent'));
        $ref    = isset($data['reference']) ? sanitize_text_field((string) $data['reference']) : null;
        $error  = isset($data['error'])     ? sanitize_text_field((string) $data['error'])     : null;
        $ok     = InvoiceService::setMoodianResult($id, $status, $ref, $error);
        return rest_ensure_response(['ok' => $ok, 'invoice' => InvoiceService::find($id)]);
    }

    /* ==================================================================
     *  ORDERS
     * ================================================================== */

    public static function ordersIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'status', 'customer_id', 'currency', 'from_date', 'to_date']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = OrderService::search($filters, max(1, min(500, $limit)), max(0, $offset));
        return rest_ensure_response(['data' => $rows, 'meta' => ['total' => count($rows), 'statuses' => OrderService::statuses()]]);
    }

    public static function ordersShow(WP_REST_Request $req)
    {
        $id  = (int) $req->get_param('id');
        $no  = (string) ($req->get_param('number') ?? '');
        $row = $id > 0
            ? OrderService::find($id)
            : ($no !== '' ? OrderService::findByNumber($no) : null);
        if (!$row) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function ordersStore(WP_REST_Request $req)
    {
        try {
            $id = OrderService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('order.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'order' => OrderService::find($id)], 201);
    }

    public static function ordersUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!OrderService::find($id)) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }
        try {
            $ok = OrderService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('order.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'order' => OrderService::find($id)]);
    }

    public static function ordersConfirm(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = OrderService::update($id, ['status' => 'confirmed']);
        return rest_ensure_response(['ok' => $ok, 'order' => OrderService::find($id)]);
    }

    public static function ordersFulfill(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = OrderService::update($id, ['status' => 'fulfilled']);
        return rest_ensure_response(['ok' => $ok, 'order' => OrderService::find($id)]);
    }

    public static function ordersCancel(WP_REST_Request $req)
    {
        $id     = (int) $req->get_param('id');
        $reason = sanitize_text_field((string) ($req->get_json_params()['reason'] ?? ''));
        try {
            $ok = OrderService::cancel($id, $reason);
        } catch (\Throwable $e) {
            return new WP_Error('order.cancel_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function ordersToInvoice(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        try {
            $invoiceId = OrderService::toInvoice($id);
        } catch (\Throwable $e) {
            return new WP_Error('order.invoice_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['invoice_id' => $invoiceId, 'invoice' => InvoiceService::find($invoiceId)]);
    }

    public static function ordersPay(WP_REST_Request $req)
    {
        $id     = (int) $req->get_param('id');
        $data   = (array) $req->get_json_params();
        $amount = (float) ($data['amount'] ?? 0);
        $method = sanitize_text_field((string) ($data['method'] ?? 'cash'));
        $ref    = sanitize_text_field((string) ($data['reference'] ?? ''));
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'amount > 0 required', ['status' => 400]);
        }
        $ok = OrderService::recordPayment($id, $amount, $method, $ref);
        return rest_ensure_response(['ok' => $ok, 'order' => OrderService::find($id)]);
    }

    /* ==================================================================
     *  PAYMENTS
     * ================================================================== */

    public static function paymentsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'method', 'status', 'invoice_id', 'order_id', 'customer_id', 'currency', 'from_date', 'to_date']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = PaymentService::search($filters, max(1, min(500, $limit)), max(0, $offset));
        return rest_ensure_response([
            'data'    => $rows,
            'meta'    => [
                'total'    => count($rows),
                'methods'  => PaymentService::methods(),
                'statuses' => PaymentService::statuses(),
            ],
        ]);
    }

    public static function paymentsShow(WP_REST_Request $req)
    {
        $id  = (int) $req->get_param('id');
        $row = PaymentService::find($id);
        if (!$row) {
            return new WP_Error('not_found', 'Payment not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function paymentsStore(WP_REST_Request $req)
    {
        try {
            $id = PaymentService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('payment.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'payment' => PaymentService::find($id)], 201);
    }

    public static function paymentsRefund(WP_REST_Request $req)
    {
        $id     = (int) $req->get_param('id');
        $data   = (array) $req->get_json_params();
        $amount = (float) ($data['amount'] ?? 0);
        $reason = sanitize_text_field((string) ($data['reason'] ?? ''));
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'amount > 0 required', ['status' => 400]);
        }
        try {
            $ok = PaymentService::refund($id, $amount, $reason);
        } catch (\Throwable $e) {
            return new WP_Error('refund.failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'payment' => PaymentService::find($id)]);
    }

    /* ==================================================================
     *  REFUNDS
     * ================================================================== */

    public static function refundsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'status', 'type', 'invoice_id', 'order_id', 'customer_id']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = RefundService::search($filters, max(1, min(500, $limit)), max(0, $offset));
        return rest_ensure_response([
            'data'    => $rows,
            'meta'    => ['total' => count($rows), 'types' => RefundService::types(), 'statuses' => RefundService::statuses()],
        ]);
    }

    public static function refundsShow(WP_REST_Request $req)
    {
        $id  = (int) $req->get_param('id');
        $row = RefundService::find($id);
        if (!$row) {
            return new WP_Error('not_found', 'Refund not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function refundsStore(WP_REST_Request $req)
    {
        try {
            $id = RefundService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('refund.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'refund' => RefundService::find($id)], 201);
    }

    public static function refundsApprove(WP_REST_Request $req)
    {
        $id  = (int) $req->get_param('id');
        $uid = get_current_user_id();
        try {
            $ok = RefundService::approve($id, $uid);
        } catch (\Throwable $e) {
            return new WP_Error('refund.approve_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'refund' => RefundService::find($id)]);
    }

    public static function refundsProcess(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        try {
            $ok = RefundService::process($id);
        } catch (\Throwable $e) {
            return new WP_Error('refund.process_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'refund' => RefundService::find($id)]);
    }

    /* ==================================================================
     *  Helpers
     * ================================================================== */

    private static function pickFilters(WP_REST_Request $req, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            $val = $req->get_param($key);
            if ($val === null || $val === '') {
                continue;
            }
            $out[$key] = is_string($val) ? sanitize_text_field($val) : $val;
        }
        return $out;
    }
}
