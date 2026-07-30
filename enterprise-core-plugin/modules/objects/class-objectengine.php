<?php
declare(strict_types=1);

namespace Enterprise\Modules\Objects;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

/**
 * Custom Object Engine — الهام گرفته از Salesforce.
 *
 * مدیران می‌توانند بدون کدنویسی، اشیاء جدید با فیلدها و روابط تعریف کنند.
 * هر شیء در جداول SQL اختصاصی ذخیره می‌شود (Flat Tables) برای کوئری سریع.
 */
final class ObjectEngine
{
    public const SUPPORTED_TYPES = [
        'text', 'textarea', 'number', 'decimal', 'boolean',
        'date', 'datetime', 'email', 'phone', 'url',
        'select', 'multiselect', 'lookup', 'currency',
    ];

    /**
     * ایجاد یک شیء جدید به همراه فیلدهایش.
     *
     * @param array{api_name:string,label:string,label_plural:string,description?:string,fields?:array} $spec
     * @return int object_id
     */
    public static function createObject(array $spec): int
    {
        self::assertValidApiName($spec['api_name']);

        $id = Db::insert('objects', [
            'api_name'     => $spec['api_name'],
            'label'        => $spec['label'],
            'label_plural' => $spec['label_plural'] ?? $spec['label'],
            'description'  => $spec['description'] ?? null,
            'is_system'    => 0,
        ]);

        foreach (($spec['fields'] ?? []) as $i => $field) {
            self::addField($id, $field, $i);
        }
        self::ensureRecordTable($id, $spec['api_name']);
        return $id;
    }

    public static function addField(int $objectId, array $field, int $sort = 0): int
    {
        if (!in_array($field['type'], self::SUPPORTED_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported field type: ' . $field['type']);
        }
        self::assertValidApiName($field['api_name']);

        return Db::insert('object_fields', [
            'object_id'     => $objectId,
            'api_name'      => $field['api_name'],
            'label'         => $field['label'],
            'type'          => $field['type'],
            'is_required'   => !empty($field['required']) ? 1 : 0,
            'is_unique'     => !empty($field['unique']) ? 1 : 0,
            'default_value' => $field['default'] ?? null,
            'options'       => isset($field['options']) ? wp_json_encode($field['options']) : null,
            'sort_order'    => $sort,
        ]);
    }

    public static function addRelation(int $parentId, int $childId, string $type, string $apiName, string $label, string $onDelete = 'restrict'): int
    {
        if (!in_array($type, ['lookup', 'master_detail'], true)) {
            throw new \InvalidArgumentException('Invalid relation type');
        }
        return Db::insert('object_relations', [
            'parent_object_id' => $parentId,
            'child_object_id'  => $childId,
            'type'             => $type,
            'api_name'         => $apiName,
            'label'            => $label,
            'on_delete'        => $onDelete,
        ]);
    }

    public static function findObjectByApiName(string $apiName): ?array
    {
        $row = Db::getRow('objects', ['api_name' => $apiName]);
        return $row ?: null;
    }

    public static function getFields(int $objectId): array
    {
        $rows = Db::getResults('object_fields', ['object_id' => $objectId], 'sort_order ASC, id ASC');
        return array_map(static function (array $r): array {
            $r['options'] = $r['options'] ? json_decode((string) $r['options'], true) : null;
            return $r;
        }, $rows);
    }

    /**
     * ایجاد رکورد جدید برای یک شیء.
     */
    public static function createRecord(int $objectId, array $data, ?int $ownerId = null): int
    {
        $object = Db::getRow('objects', ['id' => $objectId]);
        if (!$object) {
            throw new \RuntimeException('Object not found');
        }
        $fields   = self::getFields($objectId);
        $cleaned  = self::validateAndCoerce($fields, $data);

        $id = Db::insert('records', [
            'object_id' => $objectId,
            'data'      => wp_json_encode($cleaned, JSON_UNESCAPED_UNICODE),
            'owner_id'  => $ownerId ?? get_current_user_id() ?: null,
        ]);
        \Enterprise\Modules\Audit\Logger::log('record', $id, 'create', $cleaned);
        do_action('enterprise_event', 'record.created', [
            'object'    => $object['api_name'],
            'record_id' => $id,
            'data'      => $cleaned,
        ]);
        return $id;
    }

    public static function getRecord(int $id): ?array
    {
        $row = Db::getRow('records', ['id' => $id]);
        if (!$row) {
            return null;
        }
        $row['data'] = json_decode((string) $row['data'], true) ?: [];
        return $row;
    }

    public static function listRecords(int $objectId, array $args = []): array
    {
        $where = ['object_id' => $objectId];
        $rows  = Db::getResults('records', $where, 'id DESC', $args['limit'] ?? 50, $args['offset'] ?? 0);
        return array_map(static function (array $r): array {
            $r['data'] = json_decode((string) $r['data'], true) ?: [];
            return $r;
        }, $rows);
    }

    public static function updateRecord(int $id, array $data): bool
    {
        $row = self::getRecord($id);
        if (!$row) {
            return false;
        }
        $object  = Db::getRow('objects', ['id' => $row['object_id']]);
        $fields  = self::getFields((int) $row['object_id']);
        $cleaned = self::validateAndCoerce($fields, $data, (array) $row['data']);

        Db::update('records', ['data' => wp_json_encode($cleaned, JSON_UNESCAPED_UNICODE)], ['id' => $id]);
        \Enterprise\Modules\Audit\Logger::log('record', $id, 'update', [
            'before' => $row['data'],
            'after'  => $cleaned,
        ]);
        do_action('enterprise_event', 'record.updated', [
            'object'    => $object['api_name'],
            'record_id' => $id,
            'data'      => $cleaned,
        ]);
        return true;
    }

    public static function deleteRecord(int $id): bool
    {
        $row = self::getRecord($id);
        if (!$row) {
            return false;
        }
        Db::delete('records', ['id' => $id]);
        \Enterprise\Modules\Audit\Logger::log('record', $id, 'delete', $row['data']);
        do_action('enterprise_event', 'record.deleted', ['record_id' => $id]);
        return true;
    }

    /**
     * اطمینان از ساخت جدول اختصاصی برای شیء (Flat Table Pattern).
     * در این نسخه، رکوردها در جدول مرکزی `records` با ستون JSON نگهداری می‌شوند
     * ولی برای اشیاء سیستمی جدول جدا ساخته می‌شود تا کوئری‌ها مقیاس‌پذیر شوند.
     */
    public static function ensureRecordTable(int $objectId, string $apiName): void
    {
        // Hook برای نسخه‌های آینده: ایجاد جدول اختصاصی برای اشیاء سنگین.
        do_action('enterprise_ensure_object_table', $objectId, $apiName);
    }

    private static function validateAndCoerce(array $fields, array $data, array $previous = []): array
    {
        $out = $previous;
        foreach ($fields as $f) {
            $key = $f['api_name'];
            if (!array_key_exists($key, $data)) {
                if ($f['is_required'] && !array_key_exists($key, $out)) {
                    throw new \InvalidArgumentException('Field required: ' . $key);
                }
                continue;
            }
            $val = $data[$key];
            $val = match ($f['type']) {
                'number', 'decimal', 'currency' => is_numeric($val) ? (float) $val : null,
                'boolean'                       => $val ? 1 : 0,
                'multiselect'                   => is_array($val) ? array_values(array_map('strval', $val)) : (array) $val,
                'date', 'datetime'              => $val ? sanitize_text_field((string) $val) : null,
                default                         => is_scalar($val) ? sanitize_text_field((string) $val) : null,
            };
            if ($val === null && $f['is_required']) {
                throw new \InvalidArgumentException('Field required: ' . $key);
            }
            $out[$key] = $val;
        }
        return $out;
    }

    private static function assertValidApiName(string $apiName): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,62}$/', $apiName)) {
            throw new \InvalidArgumentException('Invalid API name: ' . $apiName);
        }
    }
}
