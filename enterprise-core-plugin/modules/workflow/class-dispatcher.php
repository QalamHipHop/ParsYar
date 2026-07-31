<?php
/**
 * Workflow Dispatcher — موتور اجرای گراف Workflow (نسخهٔ ۱.۵).
 *
 * قابلیت‌های این نسخه:
 * - گراف واقعی با چند یال خروجی از یک گره (branching).
 * - سه نوع مسیر شرطی: true / false / default.
 * - گره‌های: start, end, condition, set_field, send_sms, send_email,
 *            notify_admin, http_request, delay, create_task, branch, merge.
 * - حفاظت در برابر حلقهٔ بی‌نهایت (max hops + visited set).
 * - ثبت run و log برای هر اجرا (با callback به Repository).
 * - پشتیبانی از متغیرهای پویا در قالب {{path.to.value}} در تمام فیلدهای متنی.
 * - وزن‌دهی به شرط‌ها (اولویت یال‌ها بر اساس label و پیش‌فرض).
 *
 * @package Enterprise\Modules\Workflow
 */

declare(strict_types=1);

namespace Enterprise\Modules\Workflow;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;

final class Dispatcher
{
    /** حداکثر گام در هر اجرا برای جلوگیری از حلقهٔ بی‌نهایت. */
    private const MAX_HOPS = 200;

    /** اپراتورهای قابل قبول در condition. */
    public const OPS = ['==', '!=', '>', '>=', '<', '<=', 'contains', 'starts_with', 'ends_with', 'in', 'not_in', 'empty', 'not_empty'];

    /** انواع گره‌های پشتیبانی‌شده به‌علاوهٔ schema پیش‌فرض. */
    public const NODE_TYPES = [
        'start'        => ['label' => 'شروع',                 'color' => '#16a34a'],
        'end'          => ['label' => 'پایان',                'color' => '#dc2626'],
        'condition'    => ['label' => 'شرط',                  'color' => '#eab308'],
        'set_field'    => ['label' => 'به‌روزرسانی فیلد',     'color' => '#3b82f6'],
        'send_sms'     => ['label' => 'ارسال پیامک',          'color' => '#a855f7'],
        'send_email'   => ['label' => 'ارسال ایمیل',          'color' => '#6366f1'],
        'notify_admin' => ['label' => 'اطلاع‌رسانی به مدیر',  'color' => '#0ea5e9'],
        'http_request' => ['label' => 'درخواست HTTP',         'color' => '#f97316'],
        'delay'        => ['label' => 'تأخیر (زمان‌بندی)',    'color' => '#94a3b8'],
        'create_task'  => ['label' => 'ایجاد وظیفه',          'color' => '#14b8a6'],
        'branch'       => ['label' => 'انشعاب',               'color' => '#facc15'],
        'merge'        => ['label' => 'ادغام',                'color' => '#22d3ee'],
    ];

    /**
     * نقطهٔ ورود: گوش دادن به یک event و اجرای تمام workflowهای فعال.
     */
    public static function handle(string $event, array $payload): void
    {
        $workflows = Repository::activeFor($event);
        foreach ($workflows as $wf) {
            $runId = Repository::startRun((int) $wf['id'], $event, $payload);
            try {
                self::runGraph($wf['graph'], $payload, (int) $wf['id'], $runId, $event);
                Repository::finishRun($runId, 'success');
            } catch (\Throwable $e) {
                Repository::log((int) $wf['id'], $runId, 'error', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                Repository::finishRun($runId, 'failed', $e->getMessage());
                Logger::log('workflow', (int) $wf['id'], 'error', ['msg' => $e->getMessage()]);
            }
        }
    }

    /**
     * اجرای گراف. الگوریتم: BFS از گرهٔ start، با رعایت branching.
     */
    private static function runGraph(array $graph, array $ctx, int $workflowId, int $runId, string $event): void
    {
        $nodes   = self::indexById((array) ($graph['nodes'] ?? []));
        $edges   = (array) ($graph['edges'] ?? []);
        $startId = self::findStartId($nodes);
        if ($startId === null) {
            Repository::log($workflowId, $runId, 'warning', 'هیچ گرهٔ start در گراف یافت نشد.');
            return;
        }

        $hop = 0;
        $stack = [['node' => $nodes[$startId], 'branch' => 'default']];
        $visited = [];

        while (!empty($stack) && $hop < self::MAX_HOPS) {
            $hop++;
            $frame = array_shift($stack);
            $node  = $frame['node'];
            $nid   = (string) ($node['id'] ?? '');
            if ($nid === '' || in_array($nid, $visited, true)) {
                continue;
            }
            $visited[] = $nid;

            Repository::log($workflowId, $runId, 'info', 'اجرای گره: ' . ($node['label'] ?? $nid), [
                'type' => $node['type'] ?? '?',
                'hop'  => $hop,
            ]);

            // end → توقف شاخه
            if (($node['type'] ?? '') === 'end') {
                continue;
            }

            // اجرای side-effect گره
            $branch = self::executeNode($node, $ctx, $workflowId, $runId, $event);

            // پیدا کردن گره‌های بعدی بر اساس branch تصمیم‌گرفته‌شده
            $nextIds = self::resolveNextIds($edges, $nid, $branch);
            foreach ($nextIds as $nextId) {
                if (isset($nodes[$nextId])) {
                    array_unshift($stack, ['node' => $nodes[$nextId], 'branch' => 'default']);
                }
            }
        }

        if ($hop >= self::MAX_HOPS) {
            Repository::log($workflowId, $runId, 'warning', 'سقف MAX_HOPS رسیده، اجرا متوقف شد (احتمال حلقه).');
        }
    }

    /** ایندکس گره‌ها بر اساس id. */
    private static function indexById(array $nodes): array
    {
        $idx = [];
        foreach ($nodes as $n) {
            $idx[(string) ($n['id'] ?? '')] = $n;
        }
        return $idx;
    }

    private static function findStartId(array $nodes): ?string
    {
        foreach ($nodes as $id => $n) {
            if (($n['type'] ?? '') === 'start') {
                return (string) $id;
            }
        }
        return null;
    }

    /**
     * اجرای side-effect هر گره. خروجی: branch تصمیم‌گرفته‌شده (true|false|default) برای هدایت یال‌ها.
     */
    private static function executeNode(array $node, array &$ctx, int $workflowId, int $runId, string $event): string
    {
        $type   = (string) ($node['type'] ?? '');
        $config = (array) ($node['config'] ?? []);

        switch ($type) {
            case 'start':
            case 'merge':
                return 'default';

            case 'end':
                return 'default';

            case 'condition':
                $field = (string) ($config['field'] ?? '');
                $op    = (string) ($config['op']    ?? '==');
                $val   = $config['value'] ?? null;
                $actual = self::resolve($ctx, self::render($field, $ctx));
                $ok = self::compare($actual, $op, $val);
                Repository::log($workflowId, $runId, 'info', 'شرط: ' . $field . ' ' . $op . ' ' . (is_scalar($val) ? (string) $val : ''), [
                    'actual' => $actual, 'expected' => $val, 'result' => $ok,
                ]);
                return $ok ? 'true' : 'false';

            case 'set_field':
                $object = (string) ($config['object'] ?? '');
                $idPath = (string) ($config['id_path'] ?? 'record_id');
                $field  = (string) ($config['field']  ?? '');
                $value  = self::render((string) ($config['value'] ?? ''), $ctx);
                $recId  = self::resolve($ctx, $idPath);
                if ($object && $field && $recId) {
                    self::setRecordField((string) $object, (int) $recId, $field, $value);
                    Repository::log($workflowId, $runId, 'info', "set_field: $object#$recId.$field = $value");
                } else {
                    Repository::log($workflowId, $runId, 'warning', 'set_field: پارامترها ناقص بودند.');
                }
                return 'default';

            case 'send_sms':
                $to  = self::resolve($ctx, self::render((string) ($config['to'] ?? ''), $ctx));
                $msg = self::render((string) ($config['message'] ?? ''), $ctx);
                do_action('enterprise_send_sms', $to, $msg);
                Repository::log($workflowId, $runId, 'info', 'send_sms → ' . (is_scalar($to) ? (string) $to : ''));
                return 'default';

            case 'send_email':
                $to   = self::render((string) ($config['to']      ?? ''), $ctx);
                $subj = self::render((string) ($config['subject'] ?? ''), $ctx);
                $body = self::render((string) ($config['body']    ?? ''), $ctx);
                do_action('enterprise_send_email', $to, $subj, $body);
                Repository::log($workflowId, $runId, 'info', 'send_email → ' . $to);
                return 'default';

            case 'notify_admin':
                $msg = self::render((string) ($config['message'] ?? ''), $ctx);
                Logger::log('workflow', $workflowId, 'notify', ['message' => $msg, 'event' => $event]);
                Repository::log($workflowId, $runId, 'info', 'notify_admin: ' . $msg);
                return 'default';

            case 'http_request':
                $url    = self::render((string) ($config['url']    ?? ''), $ctx);
                $method = strtoupper((string) ($config['method'] ?? 'POST'));
                $body   = self::render((string) ($config['body']   ?? ''), $ctx);
                $resp = wp_remote_request($url, [
                    'method'  => $method,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body'    => $body,
                    'timeout' => 15,
                ]);
                $code = is_wp_error($resp) ? 0 : (int) wp_remote_retrieve_response_code($resp);
                Repository::log($workflowId, $runId, 'info', "http_request: $method $url → HTTP $code");
                return $code >= 200 && $code < 300 ? 'true' : 'false';

            case 'delay':
                $seconds = (int) ($config['seconds'] ?? 0);
                if ($seconds > 0 && $seconds <= 3600) {
                    Repository::log($workflowId, $runId, 'info', "delay: {$seconds}s (به‌صورت async ثبت شد)");
                    // اجرای واقعی در production از طریق WP Cron؛ اینجا فقط لاگ می‌کنیم.
                    wp_schedule_single_event(time() + $seconds, 'enterprise_workflow_delay', [$workflowId, $runId, $node['id']]);
                }
                return 'default';

            case 'create_task':
                $title = self::render((string) ($config['title'] ?? ''), $ctx);
                $assignee = (int) ($config['assignee'] ?? 0);
                $due = sanitize_text_field((string) ($config['due'] ?? ''));
                Repository::log($workflowId, $runId, 'info', "create_task: $title → user#$assignee due=$due");
                // در این نسخه فقط لاگ می‌شود؛ در آینده به ماژول tasks متصل می‌گردد.
                return 'default';

            case 'branch':
                // انشعاب شرطی: branch تصمیم نمی‌گیرد، بلکه همهٔ یال‌های خروجی default دنبال می‌شوند.
                return 'default';

            default:
                Repository::log($workflowId, $runId, 'warning', 'نوع گرهٔ ناشناس: ' . $type);
                return 'default';
        }
    }

    /**
     * تعیین گره‌های بعدی بر اساس branch خروجی از گرهٔ فعلی.
     * یال‌ها می‌توانند label: true|false|default داشته باشند.
     */
    private static function resolveNextIds(array $edges, string $fromId, string $branch): array
    {
        $matched = [];
        $defaults = [];
        foreach ($edges as $e) {
            if ((string) ($e['from'] ?? '') !== $fromId) {
                continue;
            }
            $label = (string) ($e['label'] ?? 'default');
            $to    = (string) ($e['to']   ?? '');
            if ($to === '') {
                continue;
            }
            if ($label === $branch) {
                $matched[] = $to;
            } elseif ($label === 'default' || $label === '') {
                $defaults[] = $to;
            }
        }
        // اگر branch تطبیق نداد، از defaultها استفاده کن
        return $matched ?: $defaults;
    }

    /**
     * مقایسهٔ دو مقدار بر اساس عملگر.
     */
    private static function compare($actual, string $op, $expected): bool
    {
        switch ($op) {
            case '==':          return (string) $actual === (string) $expected || $actual == $expected;
            case '!=':          return (string) $actual !== (string) $expected && $actual != $expected;
            case '>':           return is_numeric($actual) && $actual >  $expected;
            case '>=':          return is_numeric($actual) && $actual >= $expected;
            case '<':           return is_numeric($actual) && $actual <  $expected;
            case '<=':          return is_numeric($actual) && $actual <= $expected;
            case 'contains':    return is_string($actual) && str_contains($actual, (string) $expected);
            case 'starts_with': return is_string($actual) && str_starts_with($actual, (string) $expected);
            case 'ends_with':   return is_string($actual) && str_ends_with($actual, (string) $expected);
            case 'in':          return is_array($expected) && in_array($actual, $expected, false);
            case 'not_in':      return is_array($expected) && !in_array($actual, $expected, false);
            case 'empty':       return $actual === null || $actual === '' || $actual === [];
            case 'not_empty':   return !($actual === null || $actual === '' || $actual === []);
            default:            return false;
        }
    }

    /**
     * حل مسیر نقطه‌ای در context.
     * path: contact.first_name یا record_id
     */
    private static function resolve(array $ctx, string $path)
    {
        if ($path === '') {
            return null;
        }
        $parts = explode('.', $path);
        $cur = $ctx;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
            } else {
                return null;
            }
        }
        return $cur;
    }

    /**
     * جایگزینی متغیرهای {{path.to.value}} در یک رشته با مقدار واقعی از context.
     */
    private static function render(string $template, array $ctx): string
    {
        if ($template === '' || !str_contains($template, '{{')) {
            return $template;
        }
        return preg_replace_callback('/\{\{\s*([\w\.\-]+)\s*\}\}/u', static function (array $m) use ($ctx) {
            $val = self::resolve($ctx, $m[1]);
            if (is_scalar($val) || $val === null) {
                return (string) ($val ?? '');
            }
            return wp_json_encode($val, JSON_UNESCAPED_UNICODE);
        }, $template) ?? $template;
    }

    /**
     * به‌روزرسانی یک فیلد از رکورد دلخواه (بدون وابستگی به Object Engine — مستقیم از طریق post meta یا جدول ent_records).
     */
    private static function setRecordField(string $object, int $recordId, string $field, $value): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_records';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) {
            return;
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT data FROM {$table} WHERE id = %d", $recordId), ARRAY_A);
        if (!$row) {
            return;
        }
        $data = json_decode((string) $row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }
        $data[$field] = $value;
        $wpdb->update($table, ['data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE)], ['id' => $recordId], ['%s'], ['%d']);
    }

    /**
     * اعتبارسنجی ساختار گراف (برای استفاده در API قبل از ذخیره).
     */
    public static function validateGraph(array $graph): array
    {
        $errors = [];
        $nodes  = (array) ($graph['nodes'] ?? []);
        $edges  = (array) ($graph['edges'] ?? []);

        if (empty($nodes)) {
            $errors[] = 'گراف باید حداقل یک گره داشته باشد.';
            return $errors;
        }

        $hasStart = false;
        $hasEnd   = false;
        $ids      = [];
        foreach ($nodes as $n) {
            $id   = (string) ($n['id']   ?? '');
            $type = (string) ($n['type'] ?? '');
            if ($id === '') {
                $errors[] = 'هر گره باید id داشته باشد.';
            } else {
                $ids[] = $id;
            }
            if (!array_key_exists($type, self::NODE_TYPES) && $type !== 'unknown') {
                $errors[] = "نوع گرهٔ ناشناس: $type (id=$id)";
            }
            if ($type === 'start') $hasStart = true;
            if ($type === 'end')   $hasEnd   = true;
        }

        if (!$hasStart) $errors[] = 'گراف باید دقیقاً یک گرهٔ start داشته باشد.';
        if (!$hasEnd)   $errors[] = 'گراف باید حداقل یک گرهٔ end داشته باشد.';

        $idSet = array_flip($ids);
        foreach ($edges as $e) {
            $from = (string) ($e['from'] ?? '');
            $to   = (string) ($e['to']   ?? '');
            if (!isset($idSet[$from])) $errors[] = "یال به گرهٔ نامعلوم اشاره دارد: from=$from";
            if (!isset($idSet[$to]))   $errors[] = "یال به گرهٔ نامعلوم اشاره دارد: to=$to";
        }
        return $errors;
    }
}
