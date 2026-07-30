<?php
declare(strict_types=1);

namespace Enterprise\Modules\Workflow;

defined('ABSPATH') || exit;

/**
 * اجرای گره‌های گراف Workflow.
 *
 * گره‌های پشتیبانی‌شده در این نسخه:
 * - if/condition
 * - set_field (به‌روزرسانی فیلد رکورد)
 * - send_sms (ارسال پیامک)
 * - send_email
 * - notify_admin
 */
final class Dispatcher
{
    public static function handle(string $event, array $payload): void
    {
        $workflows = Repository::activeFor($event);
        foreach ($workflows as $wf) {
            self::runGraph($wf['graph'], $payload);
        }
    }

    private static function runGraph(array $graph, array $ctx): void
    {
        $nodes = (array) ($graph['nodes'] ?? []);
        $start = null;
        foreach ($nodes as $n) {
            if (($n['type'] ?? '') === 'start') {
                $start = $n;
                break;
            }
        }
        if (!$start) {
            return;
        }
        $edges = (array) ($graph['edges'] ?? []);
        $current = self::nextFrom($edges, (string) ($start['id'] ?? ''));
        $visited = [];
        while ($current && !in_array($current['id'] ?? '', $visited, true)) {
            $visited[] = (string) $current['id'];
            self::executeNode($current, $ctx);
            $current = self::nextFrom($edges, (string) $current['id']);
        }
    }

    private static function nextFrom(array $edges, string $nodeId): ?array
    {
        $next = null;
        foreach ($edges as $e) {
            if (($e['from'] ?? '') === $nodeId) {
                $next = $e;
                break;
            }
        }
        if (!$next) {
            return null;
        }
        $nodes = (array) ($GLOBALS['_enterprise_wf_nodes'] ?? []);
        $toId  = (string) $next['to'];
        foreach ($nodes as $n) {
            if (($n['id'] ?? '') === $toId) {
                return $n;
            }
        }
        return null;
    }

    private static function executeNode(array $node, array &$ctx): void
    {
        switch ($node['type'] ?? '') {
            case 'condition':
                $field = (string) ($node['config']['field'] ?? '');
                $op    = (string) ($node['config']['op'] ?? '==');
                $val   = $node['config']['value'] ?? null;
                $actual = self::resolve($ctx, $field);
                $ok = match ($op) {
                    '=='  => $actual == $val,
                    '!='  => $actual != $val,
                    '>'   => is_numeric($actual) && $actual >  $val,
                    '<'   => is_numeric($actual) && $actual <  $val,
                    default => false,
                };
                if (!$ok) {
                    $GLOBALS['_enterprise_wf_block'] = true;
                }
                break;

            case 'send_sms':
                $to  = self::resolve($ctx, (string) ($node['config']['to'] ?? ''));
                $msg = self::resolve($ctx, (string) ($node['config']['message'] ?? ''));
                do_action('enterprise_send_sms', $to, $msg);
                break;

            case 'send_email':
                do_action('enterprise_send_email', $ctx, $node['config'] ?? []);
                break;

            case 'notify_admin':
                \Enterprise\Modules\Audit\Logger::log('workflow', null, 'notify', [
                    'message' => $node['config']['message'] ?? '',
                    'ctx'     => $ctx,
                ]);
                break;
        }
    }

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
}
