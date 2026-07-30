<?php
declare(strict_types=1);

namespace Enterprise\Modules\Workflow;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

final class Repository
{
    public static function create(string $name, string $trigger, array $graph): int
    {
        return Db::insert('workflows', [
            'name'          => $name,
            'trigger_event' => $trigger,
            'graph_json'    => wp_json_encode($graph, JSON_UNESCAPED_UNICODE),
            'is_active'     => 1,
        ]);
    }

    public static function activeFor(string $event): array
    {
        $rows = Db::getResults('workflows', [
            'trigger_event' => $event,
            'is_active'     => 1,
        ]);
        return array_map(static function (array $r): array {
            $r['graph'] = json_decode((string) $r['graph_json'], true) ?: [];
            return $r;
        }, $rows);
    }
}
