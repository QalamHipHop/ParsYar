<?php
declare(strict_types=1);

namespace Enterprise\Modules\Audit;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

/**
 * ردپای حسابرسی تغییرناپذیر (Immutable Audit Trail).
 *
 * هر تغییر داده در جدول محافظت‌شده audit_log ثبت می‌شود.
 * رکوردها فقط append هستند و قابل ویرایش/حذف نیستند (در لایه اپلیکیشن).
 */
final class Logger
{
    public static function boot(): void
    {
        // محافظت: اجازه آپدیت/حذف مستقیم از طریق این کلاس داده نمی‌شود.
    }

    public static function log(string $object, ?int $objectId, string $action, array $diff = []): int
    {
        return Db::insert('audit_log', [
            'actor_id'  => get_current_user_id() ?: null,
            'object'    => $object,
            'object_id' => $objectId,
            'action'    => $action,
            'diff'      => wp_json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'ip'        => isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
        ]);
    }

    public static function tail(int $limit = 50, ?string $object = null): array
    {
        $where = $object ? ['object' => $object] : [];
        $rows  = Db::getResults('audit_log', $where, 'id DESC', $limit, 0);
        return array_map(static function (array $r): array {
            $r['diff'] = $r['diff'] ? json_decode((string) $r['diff'], true) : null;
            return $r;
        }, $rows);
    }
}
