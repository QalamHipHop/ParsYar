<?php
/**
 * WarehouseService — مدیریت انبارها (multi-warehouse)
 *
 * جدول ent_warehouses نگهدارندهٔ انبارهای فیزیکی/مجازی/امانی/در-حال-ارسال است.
 * هر انبار به یک شرکت (multi-tenant) و اختیاری یک شعبه متصل است.
 *
 * @package Enterprise\Modules\Erp
 */

declare(strict_types=1);

namespace Enterprise\Modules\Erp;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

final class WarehouseService
{
    /** @var int شناسهٔ انبار پیش‌فرض در حافظهٔ درخواست جاری */
    private static ?int $defaultId = null;

    /**
     * لیست انبارها با فیلتر و صفحه‌بندی.
     *
     * @param array $filters {
     *   @var int|null    $company_id
     *   @var int|null    $branch_id
     *   @var string|null $type
     *   @var bool|null   $is_active
     *   @var string      $search
     *   @var string      $order
     *   @var int         $limit
     *   @var int         $offset
     * }
     */
    public static function list(array $filters = []): array
    {
        global $wpdb;
        $table = Db::table('warehouses');

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[]  = 'company_id = %d';
            $params[] = (int) $filters['company_id'];
        }
        if (!empty($filters['branch_id'])) {
            $where[]  = 'branch_id = %d';
            $params[] = (int) $filters['branch_id'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 'type = %s';
            $params[] = (string) $filters['type'];
        }
        if (isset($filters['is_active'])) {
            $where[]  = 'is_active = %d';
            $params[] = $filters['is_active'] ? 1 : 0;
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
            $where[]  = '(name LIKE %s OR code LIKE %s OR city LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $order = sanitize_sql_orderby((string) ($filters['order'] ?? 'id DESC'));
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
            . " ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * شمارش کل با همین فیلترها.
     */
    public static function count(array $filters = []): int
    {
        global $wpdb;
        $table = Db::table('warehouses');

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[]  = 'company_id = %d';
            $params[] = (int) $filters['company_id'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 'type = %s';
            $params[] = (string) $filters['type'];
        }
        if (isset($filters['is_active'])) {
            $where[]  = 'is_active = %d';
            $params[] = $filters['is_active'] ? 1 : 0;
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
            $where[]  = '(name LIKE %s OR code LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * دریافت یک انبار با id.
     */
    public static function get(int $id): ?array
    {
        return Db::getRow('warehouses', ['id' => $id]);
    }

    /**
     * دریافت با code.
     */
    public static function getByCode(string $code): ?array
    {
        global $wpdb;
        $table = Db::table('warehouses');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE code = %s LIMIT 1",
            $code
        ), ARRAY_A);
        return $row ?: null;
    }

    /**
     * ساخت انبار جدید.
     *
     * @throws \InvalidArgumentException
     */
    public static function create(array $data): int
    {
        $code = sanitize_text_field((string) ($data['code'] ?? ''));
        if ($code === '') {
            throw new \InvalidArgumentException('کد انبار الزامی است.');
        }
        if (self::getByCode($code) !== null) {
            throw new \InvalidArgumentException('کد انبار تکراری است: ' . $code);
        }
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('نام انبار الزامی است.');
        }

        $row = [
            'code'           => $code,
            'name'           => $name,
            'name_en'        => isset($data['name_en']) ? sanitize_text_field((string) $data['name_en']) : null,
            'type'           => self::normalizeType((string) ($data['type'] ?? 'main')),
            'manager_user_id'=> !empty($data['manager_user_id']) ? (int) $data['manager_user_id'] : null,
            'branch_id'      => !empty($data['branch_id']) ? (int) $data['branch_id'] : null,
            'company_id'     => (int) ($data['company_id'] ?? 1),
            'phone'          => isset($data['phone']) ? sanitize_text_field((string) $data['phone']) : null,
            'address_line1'  => isset($data['address_line1']) ? sanitize_text_field((string) $data['address_line1']) : null,
            'address_line2'  => isset($data['address_line2']) ? sanitize_text_field((string) $data['address_line2']) : null,
            'city'           => isset($data['city']) ? sanitize_text_field((string) $data['city']) : null,
            'province'       => isset($data['province']) ? sanitize_text_field((string) $data['province']) : null,
            'postal_code'    => isset($data['postal_code']) ? sanitize_text_field((string) $data['postal_code']) : null,
            'country'        => strtoupper(substr((string) ($data['country'] ?? 'IR'), 0, 2)),
            'lat'            => isset($data['lat']) && $data['lat'] !== '' ? (float) $data['lat'] : null,
            'lng'            => isset($data['lng']) && $data['lng'] !== '' ? (float) $data['lng'] : null,
            'is_active'      => !empty($data['is_active']) ? 1 : 1,
            'is_default'     => !empty($data['is_default']) ? 1 : 0,
            'notes'          => isset($data['notes']) ? sanitize_textarea_field((string) $data['notes']) : null,
        ];

        // اگه is_default=1 است، بقیه را غیرپیش‌فرض کن
        if ($row['is_default'] === 1) {
            self::clearDefaultFlag($row['company_id']);
        }

        $id = Db::insert('warehouses', $row);
        Logger::log('warehouse', $id, 'create', $row);
        do_action('enterprise_event', 'warehouse.created', [
            'warehouse_id' => $id,
            'code'         => $row['code'],
            'type'         => $row['type'],
        ]);
        return $id;
    }

    /**
     * به‌روزرسانی انبار.
     */
    public static function update(int $id, array $data): bool
    {
        $current = self::get($id);
        if (!$current) {
            throw new \RuntimeException('انبار یافت نشد: ' . $id);
        }

        $patch = [];

        if (isset($data['code']) && $data['code'] !== $current['code']) {
            $code = sanitize_text_field((string) $data['code']);
            $exists = self::getByCode($code);
            if ($exists && (int) $exists['id'] !== $id) {
                throw new \InvalidArgumentException('کد انبار تکراری است: ' . $code);
            }
            $patch['code'] = $code;
        }
        if (isset($data['name']))         { $patch['name']         = sanitize_text_field((string) $data['name']); }
        if (isset($data['name_en']))      { $patch['name_en']      = sanitize_text_field((string) $data['name_en']); }
        if (isset($data['type']))         { $patch['type']         = self::normalizeType((string) $data['type']); }
        if (isset($data['manager_user_id'])) { $patch['manager_user_id'] = (int) $data['manager_user_id'] ?: null; }
        if (isset($data['branch_id']))    { $patch['branch_id']    = (int) $data['branch_id'] ?: null; }
        if (isset($data['phone']))        { $patch['phone']        = sanitize_text_field((string) $data['phone']); }
        if (isset($data['address_line1'])){ $patch['address_line1']= sanitize_text_field((string) $data['address_line1']); }
        if (isset($data['address_line2'])){ $patch['address_line2']= sanitize_text_field((string) $data['address_line2']); }
        if (isset($data['city']))         { $patch['city']         = sanitize_text_field((string) $data['city']); }
        if (isset($data['province']))     { $patch['province']     = sanitize_text_field((string) $data['province']); }
        if (isset($data['postal_code']))  { $patch['postal_code']  = sanitize_text_field((string) $data['postal_code']); }
        if (isset($data['country']))      { $patch['country']      = strtoupper(substr((string) $data['country'], 0, 2)); }
        if (isset($data['lat']))          { $patch['lat']          = $data['lat'] !== '' ? (float) $data['lat'] : null; }
        if (isset($data['lng']))          { $patch['lng']          = $data['lng'] !== '' ? (float) $data['lng'] : null; }
        if (isset($data['is_active']))    { $patch['is_active']    = $data['is_active'] ? 1 : 0; }
        if (isset($data['notes']))        { $patch['notes']        = sanitize_textarea_field((string) $data['notes']); }

        if (!empty($data['is_default'])) {
            $patch['is_default'] = 1;
            self::clearDefaultFlag((int) $current['company_id'], $id);
        } elseif (array_key_exists('is_default', $data) && empty($data['is_default'])) {
            $patch['is_default'] = 0;
        }

        if (empty($patch)) {
            return true;
        }

        Db::update('warehouses', $patch, ['id' => $id]);
        Logger::log('warehouse', $id, 'update', $patch);
        do_action('enterprise_event', 'warehouse.updated', ['warehouse_id' => $id, 'changes' => array_keys($patch)]);
        return true;
    }

    /**
     * غیرفعال‌سازی (soft delete: is_active=0).
     */
    public static function deactivate(int $id): bool
    {
        $current = self::get($id);
        if (!$current) {
            return false;
        }
        if ((int) $current['is_default'] === 1) {
            throw new \RuntimeException('انبار پیش‌فرض قابل غیرفعال‌سازی نیست. ابتدا یک انبار دیگر را پیش‌فرض کنید.');
        }
        Db::update('warehouses', ['is_active' => 0, 'is_default' => 0], ['id' => $id]);
        Logger::log('warehouse', $id, 'deactivate', []);
        do_action('enterprise_event', 'warehouse.deactivated', ['warehouse_id' => $id]);
        return true;
    }

    /**
     * فعال‌سازی مجدد.
     */
    public static function activate(int $id): bool
    {
        Db::update('warehouses', ['is_active' => 1], ['id' => $id]);
        Logger::log('warehouse', $id, 'activate', []);
        do_action('enterprise_event', 'warehouse.activated', ['warehouse_id' => $id]);
        return true;
    }

    /**
     * گرفتن انبار پیش‌فرض یک شرکت.
     * اگر هیچ‌کدام default نیست، اولین انبار فعال را برمی‌گرداند.
     */
    public static function getDefault(int $companyId = 1): ?array
    {
        if (self::$defaultId !== null) {
            $row = self::get(self::$defaultId);
            if ($row) {
                return $row;
            }
        }

        global $wpdb;
        $table = Db::table('warehouses');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE company_id = %d AND is_default = 1 AND is_active = 1 LIMIT 1",
            $companyId
        ), ARRAY_A);
        if ($row) {
            self::$defaultId = (int) $row['id'];
            return $row;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE company_id = %d AND is_active = 1 ORDER BY id ASC LIMIT 1",
            $companyId
        ), ARRAY_A);
        if ($row) {
            self::$defaultId = (int) $row['id'];
        }
        return $row ?: null;
    }

    /**
     * تنظیم انبار پیش‌فرض.
     */
    public static function setDefault(int $id): bool
    {
        $current = self::get($id);
        if (!$current) {
            throw new \RuntimeException('انبار یافت نشد: ' . $id);
        }
        if ((int) $current['is_active'] !== 1) {
            throw new \RuntimeException('فقط انبار فعال قابل پیش‌فرض شدن است.');
        }
        self::clearDefaultFlag((int) $current['company_id']);
        Db::update('warehouses', ['is_default' => 1], ['id' => $id]);
        self::$defaultId = $id;
        Logger::log('warehouse', $id, 'set_default', []);
        do_action('enterprise_event', 'warehouse.set_default', ['warehouse_id' => $id]);
        return true;
    }

    /**
     * حذف پرچم پیش‌فرض از همهٔ انبارهای یک شرکت.
     * می‌توان یک id را استثنا کرد (هنگام آپدیت خودش).
     */
    private static function clearDefaultFlag(int $companyId, ?int $exceptId = null): void
    {
        global $wpdb;
        $table = Db::table('warehouses');
        $sql = "UPDATE {$table} SET is_default = 0 WHERE company_id = %d";
        $params = [$companyId];
        if ($exceptId !== null) {
            $sql .= ' AND id != %d';
            $params[] = $exceptId;
        }
        $wpdb->query($wpdb->prepare($sql, $params));
    }

    /**
     * اعتبارسنجی type.
     */
    private static function normalizeType(string $type): string
    {
        $allowed = ['main', 'branch', 'virtual', 'consignment', 'transit'];
        return in_array($type, $allowed, true) ? $type : 'main';
    }

    /**
     * خلاصهٔ تعداد انبارها برای داشبورد.
     */
    public static function summary(int $companyId = 1): array
    {
        global $wpdb;
        $table = Db::table('warehouses');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT type, is_active, COUNT(*) AS cnt FROM {$table} WHERE company_id = %d GROUP BY type, is_active",
            $companyId
        ), ARRAY_A);

        $summary = [
            'total'      => 0,
            'active'     => 0,
            'inactive'   => 0,
            'by_type'    => [],
        ];
        foreach ((array) $rows as $r) {
            $cnt = (int) $r['cnt'];
            $summary['total'] += $cnt;
            if ((int) $r['is_active'] === 1) {
                $summary['active'] += $cnt;
            } else {
                $summary['inactive'] += $cnt;
            }
            $t = (string) $r['type'];
            $summary['by_type'][$t] = ($summary['by_type'][$t] ?? 0) + $cnt;
        }
        return $summary;
    }
}
