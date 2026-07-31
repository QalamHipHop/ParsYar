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

    /**
     * تکرار یک Workflow (برای قالب‌ها و کپی سریع).
     */
    public static function duplicate(int $id, ?string $newName = null): int
    {
        $src = self::find($id);
        if (!$src) {
            return 0;
        }
        return self::create([
            'name'          => $newName ?? ($src['name'] . ' (کپی)'),
            'trigger_event' => $src['trigger_event'],
            'graph'         => is_array($src['graph']) ? $src['graph'] : [],
            'is_active'     => false,
            'description'   => $src['description'] ?? '',
        ]);
    }

    /**
     * اجرای دستی یک Workflow (صرف‌نظر از trigger).
     */
    public static function runManually(int $id, array $payload = []): int
    {
        $wf = self::find($id);
        if (!$wf) {
            return 0;
        }
        $runId = self::startRun($id, 'manual', $payload);
        try {
            \Enterprise\Modules\Workflow\Dispatcher::handle('manual', $payload);
            self::finishRun($runId, 'success');
        } catch (\Throwable $e) {
            self::log($id, $runId, 'error', $e->getMessage());
            self::finishRun($runId, 'failed', $e->getMessage());
        }
        return $runId;
    }

    /**
     * لیست runs اخیر یک workflow.
     */
    public static function recentRuns(int $workflowId, int $limit = 20): array
    {
        $rows = \Enterprise\Support\Db::getResults(
            'workflow_runs',
            ['workflow_id' => $workflowId],
            'id DESC',
            $limit,
            0,
            'parsyar'
        );
        return array_map(static function (array $r): array {
            $r['payload'] = $r['payload'] ? json_decode((string) $r['payload'], true) : null;
            return $r;
        }, $rows);
    }

    /**
     * آمار کلی سیستم Workflow.
     */
    public static function stats(): array
    {
        global $wpdb;
        $wfTable  = $wpdb->prefix . 'parsyar_workflows';
        $runTable = $wpdb->prefix . 'parsyar_workflow_runs';

        $total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wfTable}");
        $active  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wfTable} WHERE is_active = 1");
        $runsAll = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$runTable}");
        $runsOk  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$runTable} WHERE status = 'success'");
        $runsErr = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$runTable} WHERE status = 'failed'");

        return [
            'workflows_total'    => $total,
            'workflows_active'   => $active,
            'runs_total'         => $runsAll,
            'runs_success'       => $runsOk,
            'runs_failed'        => $runsErr,
            'success_rate'       => $runsAll > 0 ? round(($runsOk / $runsAll) * 100, 1) : 0.0,
        ];
    }

    /**
     * قالب‌های آماده (برای شروع سریع در editor بصری).
     */
    public static function templates(): array
    {
        return [
            [
                'id'      => 'welcome-lead',
                'name'    => 'خوش‌آمدگویی به سرنخ جدید',
                'trigger' => 'lead.created',
                'graph'   => [
                    'nodes' => [
                        ['id' => 'n1', 'type' => 'start',    'label' => 'شروع',     'x' => 100, 'y' => 100, 'config' => []],
                        ['id' => 'n2', 'type' => 'send_sms', 'label' => 'ارسال SMS', 'x' => 320, 'y' => 100, 'config' => [
                            'to'      => '{{ lead.mobile }}',
                            'message' => 'سلام {{ lead.first_name }}، از علاقهٔ شما متشکریم.',
                        ]],
                        ['id' => 'n3', 'type' => 'end',      'label' => 'پایان',    'x' => 540, 'y' => 100, 'config' => []],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'default'],
                        ['id' => 'e2', 'from' => 'n2', 'to' => 'n3', 'label' => 'default'],
                    ],
                ],
            ],
            [
                'id'      => 'overdue-reminder',
                'name'    => 'یادآوری فاکتور معوقه',
                'trigger' => 'invoice.overdue',
                'graph'   => [
                    'nodes' => [
                        ['id' => 'n1', 'type' => 'start',     'label' => 'شروع',         'x' => 100, 'y' => 100, 'config' => []],
                        ['id' => 'n2', 'type' => 'condition', 'label' => 'مبلغ > 1M؟',   'x' => 320, 'y' => 100, 'config' => [
                            'field' => 'invoice.amount', 'op' => '>', 'value' => 1000000,
                        ]],
                        ['id' => 'n3', 'type' => 'send_email', 'label' => 'ایمیل مدیر',  'x' => 540, 'y' => 30,  'config' => [
                            'to' => 'admin@company.ir', 'subject' => 'فاکتور معوقه بزرگ', 'body' => 'فاکتور شماره {{ invoice.id }} معوقه شد.',
                        ]],
                        ['id' => 'n4', 'type' => 'send_sms',  'label' => 'پیامک مشتری',  'x' => 540, 'y' => 170, 'config' => [
                            'to' => '{{ invoice.contact_mobile }}',
                            'message' => 'صورتحساب شما معوقه شده، لطفاً پرداخت کنید.',
                        ]],
                        ['id' => 'n5', 'type' => 'end',       'label' => 'پایان',        'x' => 760, 'y' => 100, 'config' => []],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'default'],
                        ['id' => 'e2', 'from' => 'n2', 'to' => 'n3', 'label' => 'true'],
                        ['id' => 'e3', 'from' => 'n2', 'to' => 'n4', 'label' => 'false'],
                        ['id' => 'e4', 'from' => 'n3', 'to' => 'n5', 'label' => 'default'],
                        ['id' => 'e5', 'from' => 'n4', 'to' => 'n5', 'label' => 'default'],
                    ],
                ],
            ],
            [
                'id'      => 'deal-won',
                'name' => 'وقتی معامله بسته شد',
                'trigger' => 'deal.won',
                'graph'   => [
                    'nodes' => [
                        ['id' => 'n1', 'type' => 'start',       'label' => 'شروع',                'x' => 100, 'y' => 100, 'config' => []],
                        ['id' => 'n2', 'type' => 'set_field',   'label' => 'تغییر مرحلهٔ مخاطب', 'x' => 320, 'y' => 100, 'config' => [
                            'object' => 'contact', 'id_path' => 'contact.id',
                            'field'  => 'lifecycle_stage', 'value' => 'customer',
                        ]],
                        ['id' => 'n3', 'type' => 'create_task', 'label' => 'وظیفهٔ تحویل',       'x' => 540, 'y' => 100, 'config' => [
                            'title'    => 'تحویل سفارش به {{ deal.contact_name }}',
                            'assignee' => 1, 'due' => '+7 days',
                        ]],
                        ['id' => 'n4', 'type' => 'notify_admin','label' => 'اطلاع به مدیر',     'x' => 760, 'y' => 100, 'config' => [
                            'message' => 'معاملهٔ {{ deal.title }} به ارزش {{ deal.amount }} بسته شد.',
                        ]],
                        ['id' => 'n5', 'type' => 'end',         'label' => 'پایان',               'x' => 980, 'y' => 100, 'config' => []],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'default'],
                        ['id' => 'e2', 'from' => 'n2', 'to' => 'n3', 'label' => 'default'],
                        ['id' => 'e3', 'from' => 'n3', 'to' => 'n4', 'label' => 'default'],
                        ['id' => 'e4', 'from' => 'n4', 'to' => 'n5', 'label' => 'default'],
                    ],
                ],
            ],
        ];
    }
}
