<?php
/**
 * Relations Engine — مدیریت روابط چندگانه بین رکوردها
 *
 * Polymorphic: from_type + from_id ↔ to_type + to_id
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

final class Relations
{
    public const TABLE = 'relations';

    public const TYPES = [
        'contact_to_org'      => 'مخاطب ↔ سازمان',
        'contact_to_deal'     => 'مخاطب ↔ معامله',
        'contact_to_lead'     => 'مخاطب ↔ سرنخ',
        'contact_to_invoice'  => 'مخاطب ↔ فاکتور',
        'contact_to_ticket'   => 'مخاطب ↔ تیکت',
        'deal_to_org'         => 'معامله ↔ سازمان',
        'deal_to_invoice'     => 'معامله ↔ فاکتور',
        'deal_to_owner'       => 'معامله ↔ مالک',
        'lead_to_org'         => 'سرنخ ↔ سازمان',
        'employee_to_user'    => 'کارمند ↔ کاربر',
        'employee_to_manager' => 'کارمند ↔ مدیر',
        'ticket_to_contact'   => 'تیکت ↔ مخاطب',
        'ticket_to_deal'      => 'تیکت ↔ معامله',
        'project_to_client'   => 'پروژه ↔ مشتری',
        'task_to_project'     => 'تسک ↔ پروژه',
        'invoice_to_order'    => 'فاکتور ↔ سفارش',
        'product_to_supplier' => 'محصول ↔ تأمین‌کننده',
    ];

    /**
     * اتصال دو رکورد با رابطهٔ مشخص.
     */
    public static function link(string $fromType, int $fromId, string $toType, int $toId, string $relationType, array $meta = []): int
    {
        $existing = self::findOne($fromType, $fromId, $toType, $toId, $relationType);
        if ($existing) {
            if (!empty($meta)) {
                Db::update(self::TABLE, ['meta' => wp_json_encode($meta)], ['id' => $existing['id']]);
            }
            return (int) $existing['id'];
        }

        return Db::insert(self::TABLE, [
            'from_type'     => $fromType,
            'from_id'       => $fromId,
            'to_type'       => $toType,
            'to_id'         => $toId,
            'relation_type' => $relationType,
            'meta'          => !empty($meta) ? wp_json_encode($meta) : null,
            'created_at'    => current_time('mysql', true),
        ]);
    }

    /**
     * قطع رابطه.
     */
    public static function unlink(string $fromType, int $fromId, string $toType, int $toId, ?string $relationType = null): int
    {
        $where = [
            'from_type' => $fromType,
            'from_id'   => $fromId,
            'to_type'   => $toType,
            'to_id'     => $toId,
        ];
        if ($relationType) {
            $where['relation_type'] = $relationType;
        }
        return Db::delete(self::TABLE, $where);
    }

    /**
     * یافتن یک رابطه.
     */
    public static function findOne(string $fromType, int $fromId, string $toType, int $toId, string $relationType): ?array
    {
        return Db::getRow(self::TABLE, [
            'from_type'     => $fromType,
            'from_id'       => $fromId,
            'to_type'       => $toType,
            'to_id'         => $toId,
            'relation_type' => $relationType,
        ]);
    }

    /**
     * تمام روابط خروجی از یک رکورد.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function from(string $fromType, int $fromId, ?string $relationType = null): array
    {
        $where = ['from_type' => $fromType, 'from_id' => $fromId];
        if ($relationType) {
            $where['relation_type'] = $relationType;
        }
        return Db::getResults(self::TABLE, $where, 'created_at DESC', 1000, 0);
    }

    /**
     * تمام روابط ورودی به یک رکورد.
     */
    public static function to(string $toType, int $toId, ?string $relationType = null): array
    {
        $where = ['to_type' => $toType, 'to_id' => $toId];
        if ($relationType) {
            $where['relation_type'] = $relationType;
        }
        return Db::getResults(self::TABLE, $where, 'created_at DESC', 1000, 0);
    }

    /**
     * دریافت شناسه‌های رکوردهای مرتبط.
     */
    public static function relatedIds(string $fromType, int $fromId, string $toType, ?string $relationType = null): array
    {
        $rows = self::from($fromType, $fromId, $relationType);
        return array_values(array_filter(array_map(
            static fn (array $r) => (int) $r['to_id'],
            $rows
        )));
    }

    /**
     * دریافت رکوردهای مرتبط با hydrate کردن (نیاز به loader بیرونی).
     *
     * @template T
     * @param callable(string $toType, int $toId): ?T $loader
     * @return array<int, T>
     */
    public static function related(string $fromType, int $fromId, string $toType, callable $loader, ?string $relationType = null): array
    {
        $ids = self::relatedIds($fromType, $fromId, $toType, $relationType);
        $out = [];
        foreach ($ids as $id) {
            $rec = $loader($toType, $id);
            if ($rec !== null) {
                $out[] = $rec;
            }
        }
        return $out;
    }

    /**
     * شمارش روابط.
     */
    public static function count(string $side, string $type, int $id, ?string $relationType = null): int
    {
        $col = ('from' === $side) ? 'from' : 'to';
        $where = [
            $col . '_type' => $type,
            $col . '_id'   => $id,
        ];
        if ($relationType) {
            $where['relation_type'] = $relationType;
        }
        return count(Db::getResults(self::TABLE, $where, 'id DESC', 10000, 0));
    }

    /**
     * حذف تمام روابط یک رکورد (هنگام حذف رکورد اصلی).
     */
    public static function purge(string $type, int $id): int
    {
        $a = Db::delete(self::TABLE, ['from_type' => $type, 'from_id' => $id]);
        $b = Db::delete(self::TABLE, ['to_type' => $type, 'to_id' => $id]);
        return $a + $b;
    }
}
