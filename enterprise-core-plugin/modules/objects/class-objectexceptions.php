<?php
/**
 * Object Engine Exceptions
 *
 * @package Enterprise\Modules\Objects
 */

declare(strict_types=1);

namespace Enterprise\Modules\Objects;

use Enterprise\Core\Exception;

/**
 * زمانی که یک شیء با کلید تکراری ثبت شود.
 */
final class DuplicateObjectException extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct(
            sprintf('Object with key "%s" already exists.', $key),
            'parsyar.objects.duplicate_key',
            ['key' => $key]
        );
    }
}

/**
 * زمانی که شیء‌ای یافت نشود.
 */
final class ObjectNotFoundException extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct(
            sprintf('Object "%s" not found.', $key),
            'parsyar.objects.not_found',
            ['key' => $key]
        );
    }
}

/**
 * زمانی که نوع فیلد نامعتبر باشد.
 */
final class InvalidFieldTypeException extends Exception
{
    public function __construct(string $type)
    {
        parent::__construct(
            sprintf('Field type "%s" is not supported.', $type),
            'parsyar.objects.invalid_field_type',
            ['type' => $type]
        );
    }
}

/**
 * زمانی که طرحواره (schema) نامعتبر باشد.
 */
final class InvalidSchemaException extends Exception
{
    public function __construct(string $reason)
    {
        parent::__construct(
            sprintf('Invalid schema: %s', $reason),
            'parsyar.objects.invalid_schema',
            ['reason' => $reason]
        );
    }
}
