<?php
/**
 * Field Type Registry — single source of truth for all supported field types.
 *
 * @package Enterprise\Modules\Objects
 */

declare(strict_types=1);

namespace Enterprise\Modules\Objects;

final class FieldTypes
{
    public const TEXT     = 'text';
    public const TEXTAREA = 'textarea';
    public const RICH     = 'rich';
    public const INT      = 'int';
    public const DECIMAL  = 'decimal';
    public const BOOL     = 'bool';
    public const DATE     = 'date';
    public const DATETIME = 'datetime';
    public const JALALI   = 'jalali';
    public const ENUM     = 'enum';
    public const MULTI    = 'multi';
    public const FK       = 'fk';
    public const FILE     = 'file';
    public const IMAGE    = 'image';
    public const JSON     = 'json';
    public const EMAIL    = 'email';
    public const URL      = 'url';
    public const PHONE    = 'phone';
    public const MOBILE   = 'mobile';
    public const SHEBA    = 'sheba';
    public const NID      = 'national_id';
    public const CARD     = 'card';

    public const ALL = [
        self::TEXT, self::TEXTAREA, self::RICH,
        self::INT, self::DECIMAL, self::BOOL,
        self::DATE, self::DATETIME, self::JALALI,
        self::ENUM, self::MULTI, self::FK,
        self::FILE, self::IMAGE, self::JSON,
        self::EMAIL, self::URL, self::PHONE, self::MOBILE,
        self::SHEBA, self::NID, self::CARD,
    ];

    /**
     * SQL column type per field type (for DDL generation).
     */
    public static function sqlType(string $type): string
    {
        return match ($type) {
            self::TEXT, self::EMAIL, self::URL, self::PHONE, self::MOBILE,
            self::SHEBA, self::NID, self::CARD => 'VARCHAR(255)',
            self::TEXTAREA, self::RICH         => 'LONGTEXT',
            self::INT                          => 'BIGINT',
            self::DECIMAL                      => 'DECIMAL(20,4)',
            self::BOOL                         => 'TINYINT(1)',
            self::DATE                         => 'DATE',
            self::DATETIME                     => 'DATETIME',
            self::JALALI                       => 'CHAR(10)',
            self::ENUM                         => 'VARCHAR(64)',
            self::MULTI, self::JSON            => 'LONGTEXT',
            self::FK                           => 'BIGINT UNSIGNED',
            self::FILE, self::IMAGE            => 'VARCHAR(512)',
            default                            => 'VARCHAR(255)',
        };
    }

    /**
     * نرمال‌سازی و اعتبارسنجی مقدار قبل از ذخیره.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function normalize(string $type, $value, array $options = [])
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            self::TEXT, self::TEXTAREA, self::RICH => (string) $value,
            self::INT  => (int) $value,
            self::DECIMAL => (float) $value,
            self::BOOL => $value ? 1 : 0,
            self::DATE, self::DATETIME => self::normalizeDate($value),
            self::JALALI => self::normalizeJalali($value),
            self::ENUM => self::normalizeEnum($value, $options),
            self::MULTI => wp_json_encode((array) $value),
            self::JSON => wp_json_encode($value),
            self::FK => (int) $value,
            self::EMAIL => sanitize_email($value),
            self::URL => esc_url_raw($value),
            self::PHONE, self::MOBILE => preg_replace('/[^0-9+]/', '', (string) $value),
            self::SHEBA => strtoupper(preg_replace('/\s+/', '', (string) $value)),
            self::NID => preg_replace('/[^0-9]/', '', (string) $value),
            self::CARD => preg_replace('/[^0-9]/', '', (string) $value),
            default => $value,
        };
    }

    private static function normalizeDate($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($ts === false) {
            throw new InvalidSchemaException('Invalid date value: ' . print_r($value, true));
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private static function normalizeJalali($value): string
    {
        // 14XX/XX/XX یا 14XX-XX-XX یا timestamp
        if (is_numeric($value)) {
            $d = \Enterprise\Jalali::fromGregorian(date('Y-m-d', (int) $value));
            return $d;
        }
        $clean = preg_replace('/[^0-9]/', '', (string) $value);
        if (strlen($clean) === 8) {
            return substr($clean, 0, 4) . '/' . substr($clean, 4, 2) . '/' . substr($clean, 6, 2);
        }
        return (string) $value;
    }

    private static function normalizeEnum($value, array $options): string
    {
        $allowed = $options['options'] ?? [];
        if (!empty($allowed) && !in_array($value, $allowed, true)) {
            throw new InvalidSchemaException('Value not in enum: ' . (string) $value);
        }
        return (string) $value;
    }
}
