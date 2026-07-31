<?php
/**
 * Workflow Repository — ذخیره و بازیابی اتوماسیون‌ها.
 *
 * گراف Workflow در جدول `wp_parsyar_workflows` ذخیره می‌شود (graph_json).
 * هر اجرا یک ردیف در `wp_parsyar_workflow_runs` دارد.
 * تمام رویدادها در `wp_parsyar_workflow_logs` لاگ می‌شوند.
 *
 * @package Enterprise\Modules\Workflow
 */

declare(strict_types=1);

namespace Enterprise\Modules\Workflow;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;

final class Repository
{
    /** رخدادهای پشتیبانی‌شده (Trigger) */
    public const TRIGGERS = [
        'contact.created'      => 'ایجاد مخاطب',
        'contact.updated'      => 'بروزرسانی مخاطب',
        'contact.merged'       => 'ادغام مخاطب',
        'lead.created'         => 'ایجاد سرنخ',
        'lead.qualified'       => 'واجد شرایط شدن سرنخ',
        'deal.created'         => 'ایجاد معامله',
        'deal.won'             => 'بردن معامله',
        'deal.lost'            => 'باختن معامله',
        'deal.stage_changed'   => 'تغییر مرحلهٔ معامله',
        'invoice.created'      => 'ایجاد فاکتور',
        'invoice.paid'         => 'پرداخت فاکتور',
        'invoice.overdue'      => 'سررسید گذشته',
        'moodian.invoice_submitted' => 'ارسال به سامانهٔ مؤدیان',
        'journal.posted'       => 'ثبت سند حسابداری',
        'order.created'        => 'ایجاد سفارش',
        'order.fulfilled'      => 'تکمیل سفارش',
        'ticket.created'       => 'ایجاد تیکت',
        'ticket.resolved'      => 'حل تیکت',
        'employee.hired'       => 'استخدام',
        'payroll.run_completed'=> 'پایان اجرای حقوق',
        'low_stock'            => 'موجودی کم',
    ];

    public static function boot(): void
    {
        // نقطهٔ ورود برای extensionهای آینده
    }

    /**
     * ایجاد Workflow.
     */
    public static function create(array $data): int
    {
        $data['name']          = sanitize_text_field((string) ($data['name'] ?? ''));
        $data['trigger_event'] = sanitize_key((string) ($data['trigger_event'] ?? ''));
        $data['graph_json']    = wp_json_encode(self::normalizeGraph($data['graph'] ?? []), JSON_UNESCAPED_UNICODE);
        $data['is_active']     = !empty($data['is_active']) ? 1 : 0;
        $data['description']   = sanitize_textarea_field((string) ($data['description'] ?? ''));
        $data['created_at']    = current_time('mysql');
        $data['created_by']    = get_current_user_id() ?: null;

        $id = \Enterprise\Support\Db::insert('workflows', $data, 'parsyar');
        Logger::log('workflow', $id, 'create', ['name' => $data['name'], 'trigger' => $data['trigger_event']]);
        return $id;
    }

    /**
     * به‌روزرسانی.
     */
    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        $patch = [];
        if (isset($data['name']))        $patch['name'] = sanitize_text_field((string) $data['name']);
        if (isset($data['description'])) $patch['description'] = sanitize_textarea_field((string) $data['description']);
        if (isset($data['trigger_event'])) $patch['trigger_event'] = sanitize_key((string) $data['trigger_event']);
        if (isset($data['is_active']))   $patch['is_active'] = !empty($data['is_active']) ? 1 : 0;
        if (isset($data['graph'])) {
            $patch['graph_json'] = wp_json_encode(self::normalizeGraph($data['graph']), JSON_UNESCAPED_UNICODE);
        }
        $patch['updated_at'] = current_time('mysql');
        \Enterprise\Support\Db::update('workflows', $patch, ['id' => $id], 'parsyar');
        Logger::log('workflow', $id, 'update', array_keys($patch));
        return true;
    }

    public static function delete(int $id): bool
    {
        \Enterprise\Support\Db::delete('workflow_runs', ['workflow_id' => $id], 'parsyar');
        \Enterprise\Support\Db::delete('workflow_logs', ['workflow_id' => $id], 'parsyar');
        $r = \Enterprise\Support\Db::delete('workflows', ['id' => $id], 'parsyar');
        return $r > 0;
    }

    public static function find(int $id): ?array
    {
        $row = \Enterprise\Support\Db::getRow('workflows', ['id' => $id], 'parsyar');
        if ($row) {
            $row['graph'] = json_decode((string) $row['graph_json'], true) ?: [];
        }
        return $row;
    }

    public static function listAll(bool $activeOnly = false, int $limit = 200, int $offset = 0): array
    {
        $where = $activeOnly ? ['is_active' => 1] : [];
        return \Enterprise\Support\Db::getResults('workflows', $where, 'id DESC', $limit, $offset, 'parsyar');
    }

    /**
     * Workflowهای فعال برای یک event.
     */
    public static function activeFor(string $event): array
    {
        $rows = \Enterprise\Support\Db::getResults(
            'workflows',
            ['trigger_event' => $event, 'is_active' => 1],
            'id ASC',
            100,
            0,
            'parsyar'
        );
        return array_map(static function (array $r): array {
            $r['graph'] = json_decode((string) $r['graph_json'], true) ?: [];
            return $r;
        }, $rows);
    }

    /**
     * ثبت یک اجرا.
     */
    public static function startRun(int $workflowId, string $event, array $payload): int
    {
        return \Enterprise\Support\Db::insert('workflow_runs', [
            'workflow_id' => $workflowId,
            'event'       => $event,
            'payload'     => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status'      => 'running',
            'started_at'  => current_time('mysql'),
        ], 'parsyar');
    }

    public static function finishRun(int $runId, string $status, ?string $error = null): void
    {
        \Enterprise\Support\Db::update('workflow_runs', [
            'status'     => $status,
            'finished_at'=> current_time('mysql'),
            'error'      => $error,
        ], ['id' => $runId], 'parsyar');
    }

    public static function log(int $workflowId, int $runId, string $level, string $message, array $context = []): void
    {
        \Enterprise\Support\Db::insert('workflow_logs', [
            'workflow_id' => $workflowId,
            'run_id'      => $runId,
            'level'       => $level,
            'message'     => $message,
            'context'     => wp_json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at'  => current_time('mysql'),
        ], 'parsyar');
    }

    /**
     * لاگ‌های یک Workflow.
     */
    public static function logs(int $workflowId, int $limit = 100, int $offset = 0): array
    {
        $rows = \Enterprise\Support\Db::getResults(
            'workflow_logs',
            ['workflow_id' => $workflowId],
            'id DESC',
            $limit,
            $offset,
            'parsyar'
        );
        return array_map(static function (array $r): array {
            $r['context'] = $r['context'] ? json_decode((string) $r['context'], true) : null;
            return $r;
        }, $rows);
    }

    /**
     * نرمال‌سازی گراف — اطمینان از ساختار استاندارد.
     */
    public static function normalizeGraph(array $graph): array
    {
        $nodes = [];
        foreach (($graph['nodes'] ?? []) as $n) {
            $nodes[] = [
                'id'     => (string) ($n['id']     ?? ''),
                'type'   => (string) ($n['type']   ?? 'unknown'),
                'label'  => (string) ($n['label']  ?? ''),
                'config' => is_array($n['config'] ?? null) ? $n['config'] : [],
                'x'      => (float) ($n['x'] ?? 0),
                'y'      => (float) ($n['y'] ?? 0),
            ];
        }
        $edges = [];
        foreach (($graph['edges'] ?? []) as $e) {
            $edges[] = [
                'id'   => (string) ($e['id']   ?? ''),
                'from' => (string) ($e['from'] ?? ''),
                'to'   => (string) ($e['to']   ?? ''),
                'label'=> (string) ($e['label']?? ''),
            ];
        }
        return [
            'version' => 1,
            'nodes'   => $nodes,
            'edges'   => $edges,
        ];
    }
}
