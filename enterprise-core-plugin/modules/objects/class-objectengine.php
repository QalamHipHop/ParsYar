<?php
declare(strict_types=1);

namespace Enterprise\Modules\Objects;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

/**
 * Custom Object Engine — الهام گرفته از Salesforce.
 *
 * هر شیء در دیتابیس:
 *  - یک رکورد در `wp_ent_objects` (فراداده)
 *  - یک جدول اختصاصی `wp_ent_data_{api_name}` (Flat Table) — ساخته شده توسط SchemaBuilder
 *  - فیلدهایش در `wp_ent_object_fields`
 *  - روابطش در `wp_ent_object_relations`
 */
final class ObjectEngine
{
    public const SUPPORTED_TYPES = [
        'text', 'textarea', 'number', 'decimal', 'boolean',
        'date', 'datetime', 'email', 'phone', 'url',
        'select', 'multiselect', 'lookup', 'currency',
    ];

    /**
     * ایجاد یک شیء جدید به همراه فیلدها و جدول اختصاصی.
     *
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

        $fields = [];
        foreach (($spec['fields'] ?? []) as $i => $field) {
            $fields[] = self::addField($id, $field, $i);
        }
        SchemaBuilder::syncObjectTable($id, $spec['api_name'], $fields);
        return $id;
    }

    public static function addField(int $objectId, array $field, int $sort = 0): array
    {
        if (!in_array($field['type'], self::SUPPORTED_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported field type: ' . ($field['type'] ?? ''));
        }
        self::assertValidApiName($field['api_name']);

        $id = Db::insert('object_fields', [
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
        return [
            'id'           => $id,
            'object_id'    => $objectId,
            'api_name'     => $field['api_name'],
            'label'        => $field['label'],
            'type'         => $field['type'],
            'is_required'  => !empty($field['required']) ? 1 : 0,
            'is_unique'    => !empty($field['unique']) ? 1 : 0,
            'default_value'=> $field['default'] ?? null,
            'options'      => $field['options'] ?? null,
            'sort_order'   => $sort,
        ];
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

    public static function getRelations(int $objectId): array
    {
        return Db::getResults('object_relations', [
            'parent_object_id' => $objectId,
        ], 'id ASC');
    }

    // ---------- Record facade (back-compat) ----------

    public static function createRecord(int $objectId, array $data, ?int $ownerId = null): int
    {
        $store = RecordStore::forObject($objectId);
        return $store->create($data, $ownerId);
    }

    public static function getRecord(int $id): ?array
    {
        $row = Db::getRow('records', ['id' => $id]);
        if ($row) {
            $r = $row;
            $r['data'] = json_decode((string) $r['data'], true) ?: [];
            return $r;
        }
        // تلاش در Flat Tables: نگاشت معکوس نداریم، پس null.
        return null;
    }

    public static function listRecords(int $objectId, array $args = []): array
    {
        $store  = RecordStore::forObject($objectId);
        $limit  = max(1, min(500, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        return $store->list($limit, $offset, (array) ($args['filters'] ?? []));
    }

    public static function updateRecord(int $id, array $data): bool
    {
        $row = self::getRecord($id);
        if (!$row) {
            return false;
        }
        $obj = Db::getRow('objects', ['id' => $row['object_id']]);
        if (!$obj) {
            return false;
        }
        $store = RecordStore::forObject((int) $obj['id'], (string) $obj['api_name']);
        return $store->update($id, $data);
    }

    public static function deleteRecord(int $id): bool
    {
        $row = self::getRecord($id);
        if ($row) {
            $r = Db::delete('records', ['id' => $id]);
            Logger::log('record', $id, 'delete', $row['data']);
            do_action('enterprise_event', 'record.deleted', ['record_id' => $id]);
            return $r > 0;
        }
        return false;
    }

    /**
     * اعتبارسنجی و تبدیل نوع داده‌ها — قابل استفاده مجدد توسط RecordStore.
     */
    public static function validateDataForFields(array $fields, array $data, array $previous = []): array
    {
        $out = $previous;
        foreach ($fields as $f) {
            $key = $f['api_name'];
            if (!array_key_exists($key, $data)) {
                if (!empty($f['is_required']) && !array_key_exists($key, $out)) {
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
                'lookup'                        => is_numeric($val) ? (int) $val : null,
                default                         => is_scalar($val) ? sanitize_text_field((string) $val) : null,
            };
            if ($val === null && !empty($f['is_required'])) {
                throw new \InvalidArgumentException('Field required: ' . $key);
            }
            $out[$key] = $val;
        }
        return $out;
    }

    /**
     * حذف شیء + جدول اختصاصی.
     */
    public static function deleteObject(string $apiName): bool
    {
        $obj = self::findObjectByApiName($apiName);
        if (!$obj || !empty($obj['is_system'])) {
            return false;
        }
        SchemaBuilder::dropObjectTable($apiName);
        Db::delete('object_fields', ['object_id' => $obj['id']]);
        Db::delete('object_relations', [
            'parent_object_id' => $obj['id'],
        ]);
        Db::delete('objects', ['id' => $obj['id']]);
        return true;
    }

    private static function assertValidApiName(string $apiName): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,62}$/', $apiName)) {
            throw new \InvalidArgumentException('Invalid API name: ' . $apiName);
        }
    }
}
