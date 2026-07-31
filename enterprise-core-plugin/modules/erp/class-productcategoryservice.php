<?php
/**
 * ProductCategoryService — دسته‌بندی سلسله‌مراتبی محصولات
 *
 * جدول ent_product_categories با parent_id self-reference
 * درخت دسته‌بندی را می‌سازد. از slug برای URL و یکتایی استفاده می‌شود.
 *
 * @package Enterprise\Modules\Erp
 */

declare(strict_types=1);

namespace Enterprise\Modules\Erp;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

final class ProductCategoryService
{
    /**
     * لیست تخت همهٔ دسته‌ها.
     */
    public static function listAll(bool $activeOnly = true): array
    {
        global $wpdb;
        $table = Db::table('product_categories');
        $sql = "SELECT * FROM {$table}";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * درخت سلسله‌مراتبی.
     * خروجی: [['id'=>1,'name'=>'...','children'=>[...]], ...]
     *
     * @param int|null $rootParent فقط زیرمجموعهٔ این parent (null = همه ریشه‌ها)
     */
    public static function tree(?int $rootParent = null): array
    {
        $flat = self::listAll(false);
        return self::buildTree($flat, $rootParent);
    }

    private static function buildTree(array $flat, ?int $parentId): array
    {
        $out = [];
        foreach ($flat as $node) {
            $nodeParent = $node['parent_id'] !== null ? (int) $node['parent_id'] : null;
            if ($nodeParent === $parentId) {
                $children = self::buildTree($flat, (int) $node['id']);
                $node['children'] = $children;
                $out[] = $node;
            }
        }
        return $out;
    }

    /**
     * دریافت با id.
     */
    public static function get(int $id): ?array
    {
        return Db::getRow('product_categories', ['id' => $id]);
    }

    /**
     * دریافت با slug.
     */
    public static function getBySlug(string $slug): ?array
    {
        return Db::getRow('product_categories', ['slug' => sanitize_title($slug)]);
    }

    /**
     * ساخت دسته.
     */
    public static function create(array $data): int
    {
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('نام دسته الزامی است.');
        }

        $slugInput = (string) ($data['slug'] ?? '');
        $slug = $slugInput !== '' ? sanitize_title($slugInput) : sanitize_title($name);
        if ($slug === '') {
            throw new \InvalidArgumentException('slug قابل تولید نیست.');
        }
        if (self::getBySlug($slug) !== null) {
            throw new \InvalidArgumentException('slug تکراری است: ' . $slug);
        }

        $parent = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        if ($parent !== null) {
            $parentNode = self::get($parent);
            if (!$parentNode) {
                throw new \InvalidArgumentException('دستهٔ والد یافت نشد: ' . $parent);
            }
        }

        $row = [
            'parent_id'   => $parent,
            'name'        => $name,
            'name_en'     => isset($data['name_en']) ? sanitize_text_field((string) $data['name_en']) : null,
            'slug'        => $slug,
            'description' => isset($data['description']) ? sanitize_textarea_field((string) $data['description']) : null,
            'icon'        => isset($data['icon']) ? sanitize_text_field((string) $data['icon']) : null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
        ];

        $id = Db::insert('product_categories', $row);
        Logger::log('product_category', $id, 'create', $row);
        do_action('enterprise_event', 'product_category.created', ['category_id' => $id, 'slug' => $slug]);
        return $id;
    }

    /**
     * به‌روزرسانی.
     */
    public static function update(int $id, array $data): bool
    {
        $current = self::get($id);
        if (!$current) {
            throw new \RuntimeException('دسته یافت نشد: ' . $id);
        }

        $patch = [];

        if (isset($data['name']) && $data['name'] !== $current['name']) {
            $patch['name'] = sanitize_text_field((string) $data['name']);
        }
        if (isset($data['name_en'])) {
            $patch['name_en'] = sanitize_text_field((string) $data['name_en']);
        }
        if (isset($data['parent_id'])) {
            $newParent = $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
            if ($newParent === $id) {
                throw new \InvalidArgumentException('دسته نمی‌تواند والد خودش باشد.');
            }
            if ($newParent !== null) {
                // جلوگیری از cycle: newParent نباید زیرمجموعهٔ id باشد
                if (self::isDescendant($newParent, $id)) {
                    throw new \InvalidArgumentException('انتقال باعث چرخش درخت می‌شود.');
                }
                if (!self::get($newParent)) {
                    throw new \InvalidArgumentException('والد جدید یافت نشد.');
                }
            }
            $patch['parent_id'] = $newParent;
        }
        if (isset($data['slug']) && $data['slug'] !== $current['slug']) {
            $slug = sanitize_title((string) $data['slug']);
            $exists = self::getBySlug($slug);
            if ($exists && (int) $exists['id'] !== $id) {
                throw new \InvalidArgumentException('slug تکراری است: ' . $slug);
            }
            $patch['slug'] = $slug;
        }
        if (isset($data['description'])) {
            $patch['description'] = sanitize_textarea_field((string) $data['description']);
        }
        if (isset($data['icon'])) {
            $patch['icon'] = sanitize_text_field((string) $data['icon']);
        }
        if (isset($data['sort_order'])) {
            $patch['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $patch['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }

        if (empty($patch)) {
            return true;
        }

        Db::update('product_categories', $patch, ['id' => $id]);
        Logger::log('product_category', $id, 'update', $patch);
        do_action('enterprise_event', 'product_category.updated', ['category_id' => $id, 'changes' => array_keys($patch)]);
        return true;
    }

    /**
     * حذف فقط در صورتی که دستهٔ فرزند یا محصولی نداشته باشد.
     */
    public static function delete(int $id): bool
    {
        $current = self::get($id);
        if (!$current) {
            return false;
        }

        global $wpdb;
        $children = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . Db::table('product_categories') . " WHERE parent_id = %d",
            $id
        ));
        if ($children > 0) {
            throw new \RuntimeException('این دسته زیرمجموعه دارد؛ ابتدا فرزندان را جابجا یا حذف کنید.');
        }
        $products = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . Db::table('products') . " WHERE category_id = %d",
            $id
        ));
        if ($products > 0) {
            throw new \RuntimeException('این دسته به محصولاتی متصل است؛ ابتدا محصولات را جابجا کنید.');
        }

        Db::delete('product_categories', ['id' => $id]);
        Logger::log('product_category', $id, 'delete', []);
        do_action('enterprise_event', 'product_category.deleted', ['category_id' => $id]);
        return true;
    }

    /**
     * آیا candidateId زیرمجموعهٔ ancestorId است؟
     */
    public static function isDescendant(int $candidateId, int $ancestorId): bool
    {
        $cur = self::get($candidateId);
        while ($cur && $cur['parent_id'] !== null) {
            if ((int) $cur['parent_id'] === $ancestorId) {
                return true;
            }
            $cur = self::get((int) $cur['parent_id']);
        }
        return false;
    }

    /**
     * مسیر کامل از ریشه تا این دسته (breadcrumb).
     * خروجی: [['id'=>1,'name'=>'...'], ['id'=>4,'name'=>'...']]
     */
    public static function breadcrumb(int $id): array
    {
        $path = [];
        $cur = self::get($id);
        $guard = 0;
        while ($cur && $guard++ < 32) {
            array_unshift($path, ['id' => (int) $cur['id'], 'name' => (string) $cur['name'], 'slug' => (string) $cur['slug']]);
            if ($cur['parent_id'] === null) {
                break;
            }
            $cur = self::get((int) $cur['parent_id']);
        }
        return $path;
    }
}
