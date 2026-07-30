<?php
declare(strict_types=1);

namespace Enterprise\Modules\Objects;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

/**
 * RecordStore — لایه دسترسی به داده‌های رکورد.
 *
 * دو حالت:
 *  1) Flat Table: هر شیء جدول اختصاصی دارد (`wp_ent_data_{api_name}`).
 *     سرعت بالا، ایندکس‌گذاری مستقل، کوئری ساده.
 *  2) Fallback JSON: برای سازگاری با نسخه‌های قدیمی، رکوردها در
 *     `wp_ent_records.data` به صورت JSON ذخیره می‌شوند.
 */
final class RecordStore
{
    public function __construct(
        private readonly int    $objectId,
        private readonly string $apiName,
        private readonly array  $objectRow,
        private readonly array  $fields,
    ) {
    }

    public static function forObjectByApi(string $apiName): self
    {
        $obj = ObjectEngine::findObjectByApiName($apiName);
        if (!$obj) {
            throw new \RuntimeException('Object not found: ' . $apiName);
        }
        return new self(
            (int) $obj['id'],
            $apiName,
            $obj,
            ObjectEngine::getFields((int) $obj['id'])
        );
    }

    public static function forObject(int $objectId, ?string $apiName = null): self
    {
        if ($apiName === null) {
            $row = Db::getRow('objects', ['id' => $objectId]);
            if (!$row) {
                throw new \RuntimeException('Object not found');
            }
            $apiName = (string) $row['api_name'];
        }
        return self::forObjectByApi($apiName);
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function apiName(): string
    {
        return $this->apiName;
    }

    public function objectId(): int
    {
        return $this->objectId;
    }

    public function objectRow(): array
    {
        return $this->objectRow;
    }

    /**
     * تشخیص اینکه جدول اختصاصی این شیء ساخته شده یا نه.
     */
    public function hasFlatTable(): bool
    {
        global $wpdb;
        $table = SchemaBuilder::tableFor($this->apiName);
        $found = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );
        return !empty($found);
    }

    public function create(array $data, ?int $ownerId = null): int
    {
        $cleaned = ObjectEngine::validateDataForFields($this->fields, $data);
        $ownerId = $ownerId ?? (get_current_user_id() ?: null);

        if ($this->hasFlatTable()) {
            return $this->insertFlat($cleaned, $ownerId);
        }
        return $this->insertLegacy($cleaned, $ownerId);
    }

    public function get(int $id): ?array
    {
        if ($this->hasFlatTable()) {
            return $this->getFlat($id);
        }
        return $this->getLegacy($id);
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->get($id);
        if (!$existing) {
            return false;
        }
        $cleaned = ObjectEngine::validateDataForFields($this->fields, $data, $this->extractData($existing));
        if ($this->hasFlatTable()) {
            return $this->updateFlat($id, $cleaned);
        }
        return $this->updateLegacy($id, $cleaned);
    }

    public function delete(int $id): bool
    {
        if ($this->hasFlatTable()) {
            global $wpdb;
            $r = $wpdb->delete(SchemaBuilder::tableFor($this->apiName), ['id' => $id]);
            return $r !== false;
        }
        $r = Db::delete('records', ['id' => $id]);
        return $r > 0;
    }

    public function list(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        if ($this->hasFlatTable()) {
            return $this->listFlat($limit, $offset, $filters);
        }
        return $this->listLegacy($limit, $offset, $filters);
    }

    public function count(array $filters = []): int
    {
        if ($this->hasFlatTable()) {
            global $wpdb;
            [$where, $params] = $this->buildWhere($filters);
            $sql = "SELECT COUNT(*) FROM " . SchemaBuilder::tableFor($this->apiName) . $where;
            return (int) ($wpdb->get_var($params ? $wpdb->prepare($sql, $params) : $sql) ?: 0);
        }
        $where = ['object_id' => $this->objectId];
        return count(Db::getResults('records', $where, 'id DESC', 100000, 0));
    }

    // -------- Legacy JSON store --------

    private function insertLegacy(array $data, ?int $ownerId): int
    {
        $id = Db::insert('records', [
            'object_id' => $this->objectId,
            'data'      => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
            'owner_id'  => $ownerId,
        ]);
        Logger::log('record', $id, 'create', $data);
        do_action('enterprise_event', 'record.created', [
            'object'    => $this->apiName,
            'record_id' => $id,
            'data'      => $data,
        ]);
        return $id;
    }

    private function getLegacy(int $id): ?array
    {
        $row = Db::getRow('records', ['id' => $id, 'object_id' => $this->objectId]);
        if (!$row) {
            return null;
        }
        $row['data'] = json_decode((string) $row['data'], true) ?: [];
        return $row;
    }

    private function updateLegacy(int $id, array $data): bool
    {
        $before = $this->getLegacy($id);
        Db::update('records', ['data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE)], ['id' => $id]);
        Logger::log('record', $id, 'update', ['before' => $before['data'] ?? [], 'after' => $data]);
        do_action('enterprise_event', 'record.updated', [
            'object'    => $this->apiName,
            'record_id' => $id,
            'data'      => $data,
        ]);
        return true;
    }

    private function listLegacy(int $limit, int $offset, array $filters): array
    {
        $where = ['object_id' => $this->objectId];
        $rows  = Db::getResults('records', $where, 'id DESC', $limit, $offset);
        return array_map(static function (array $r): array {
            $r['data'] = json_decode((string) $r['data'], true) ?: [];
            return $r;
        }, $rows);
    }

    // -------- Flat Table store --------

    private function insertFlat(array $data, ?int $ownerId): int
    {
        global $wpdb;
        $row = array_merge($data, [
            'owner_id' => $ownerId,
            'status'   => 'active',
        ]);
        $ok = $wpdb->insert(SchemaBuilder::tableFor($this->apiName), $row);
        if ($ok === false) {
            throw new \RuntimeException('Flat insert failed: ' . $wpdb->last_error);
        }
        $id = (int) $wpdb->insert_id;
        Logger::log('record', $id, 'create', $data);
        do_action('enterprise_event', 'record.created', [
            'object'    => $this->apiName,
            'record_id' => $id,
            'data'      => $data,
        ]);
        return $id;
    }

    private function getFlat(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . SchemaBuilder::tableFor($this->apiName) . " WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $row['data'] = $this->extractData($row);
        return $row;
    }

    private function updateFlat(int $id, array $data): bool
    {
        global $wpdb;
        $before = $this->getFlat($id);
        $r = $wpdb->update(SchemaBuilder::tableFor($this->apiName), $data, ['id' => $id]);
        if ($r === false) {
            throw new \RuntimeException('Flat update failed: ' . $wpdb->last_error);
        }
        Logger::log('record', $id, 'update', ['before' => $before['data'] ?? [], 'after' => $data]);
        do_action('enterprise_event', 'record.updated', [
            'object'    => $this->apiName,
            'record_id' => $id,
            'data'      => $data,
        ]);
        return true;
    }

    private function listFlat(int $limit, int $offset, array $filters): array
    {
        global $wpdb;
        [$where, $params] = $this->buildWhere($filters);
        $sql    = "SELECT * FROM " . SchemaBuilder::tableFor($this->apiName) . "{$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params = array_merge($params, [$limit, $offset]);
        $rows   = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(function (array $r): array {
            $r['data'] = $this->extractData($r);
            return $r;
        }, $rows ?: []);
    }

    private function buildWhere(array $filters): array
    {
        if (empty($filters)) {
            return ['', []];
        }
        $clauses = [];
        $params  = [];
        foreach ($filters as $field => $value) {
            if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', (string) $field)) {
                continue;
            }
            $clauses[] = "{$field} = %s";
            $params[]  = is_scalar($value) ? (string) $value : wp_json_encode($value);
        }
        if (empty($clauses)) {
            return ['', []];
        }
        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function extractData(array $row): array
    {
        $systemKeys = ['id', 'owner_id', 'status', 'created_at', 'updated_at'];
        $out = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $systemKeys, true)) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }
}
