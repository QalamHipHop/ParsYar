<?php
/**
 * Device — ثبت و مدیریت device موبایل.
 *
 * جدول: wp_parsyar_mobile_devices
 *
 * @package Enterprise\Modules\Mobile
 */

declare(strict_types=1);

namespace Enterprise\Modules\Mobile;

defined('ABSPATH') || exit;

final class Device
{
    public const TABLE = 'mobile_devices';
    public const PLATFORMS = ['ios', 'android'];

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'parsyar_' . self::TABLE;
    }

    /**
     * ثبت یا به‌روزرسانی device.
     *
     * @param array<string,mixed> $data باید شامل: contact_id, platform, token, app_version, device_model
     * @return int device id
     */
    public static function register(array $data): int
    {
        global $wpdb;
        foreach (['contact_id', 'platform', 'token'] as $req) {
            if (empty($data[$req])) {
                throw new \InvalidArgumentException("فیلد {$req} الزامی است.");
            }
        }
        $platform = (string) $data['platform'];
        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new \InvalidArgumentException('platform باید ios یا android باشد.');
        }
        $row = [
            'contact_id'    => (int) $data['contact_id'],
            'platform'      => $platform,
            'token'         => (string) $data['token'],
            'app_version'   => (string) ($data['app_version'] ?? '0.0.0'),
            'os_version'    => (string) ($data['os_version'] ?? ''),
            'device_model'  => (string) ($data['device_model'] ?? ''),
            'locale'        => (string) ($data['locale'] ?? 'fa-IR'),
            'push_enabled'  => isset($data['push_enabled']) ? ((int) $data['push_enabled'] ? 1 : 0) : 1,
            'is_active'     => 1,
            'last_seen_at'  => current_time('mysql', true),
            'created_at'    => current_time('mysql', true),
            'updated_at'    => current_time('mysql', true),
        ];
        // اگه device با این token قبلاً ثبت شده، به‌روز کن
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE token = %s LIMIT 1",
            $row['token']
        ));
        if ($existing) {
            $wpdb->update(self::table(), $row, ['id' => $existing]);
            return $existing;
        }
        $wpdb->insert(self::table(), $row);
        if ($wpdb->last_error) {
            throw new \RuntimeException('DB insert failed: ' . $wpdb->last_error);
        }
        return (int) $wpdb->insert_id;
    }

    public static function touch(int $deviceId): bool
    {
        global $wpdb;
        $r = $wpdb->update(self::table(), [
            'last_seen_at' => current_time('mysql', true),
            'updated_at'   => current_time('mysql', true),
        ], ['id' => $deviceId]);
        return $r !== false;
    }

    public static function deactivate(int $deviceId): bool
    {
        global $wpdb;
        $r = $wpdb->update(self::table(), [
            'is_active'  => 0,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $deviceId]);
        return $r !== false;
    }

    public static function delete(int $deviceId): bool
    {
        global $wpdb;
        $r = $wpdb->delete(self::table(), ['id' => $deviceId]);
        return $r !== false && $r > 0;
    }

    public static function find(int $id): ?self
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A);
        return $row ? new self($row) : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,Device>
     */
    public static function list(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['contact_id'])) {
            $where[] = 'contact_id = %d';
            $params[] = (int) $filters['contact_id'];
        }
        if (!empty($filters['platform'])) {
            $where[] = 'platform = %s';
            $params[] = (string) $filters['platform'];
        }
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = %d';
            $params[] = (int) $filters['is_active'];
        }
        $whereSql = implode(' AND ', $where);
        $params[] = max(1, min(500, $limit));
        $params[] = max(0, $offset);
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE {$whereSql} ORDER BY last_seen_at DESC LIMIT %d OFFSET %d",
            $params
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(static fn(array $r) => new self($r), $rows);
    }

    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public function id(): int { return (int) $this->data['id']; }
    public function contactId(): int { return (int) $this->data['contact_id']; }
    public function platform(): string { return (string) $this->data['platform']; }
    public function token(): string { return (string) ($this->data['token'] ?? ''); }
    public function appVersion(): string { return (string) ($this->data['app_version'] ?? '0.0.0'); }
    public function osVersion(): string { return (string) ($this->data['os_version'] ?? ''); }
    public function deviceModel(): string { return (string) ($this->data['device_model'] ?? ''); }
    public function locale(): string { return (string) ($this->data['locale'] ?? 'fa-IR'); }
    public function pushEnabled(): bool { return (int) ($this->data['push_enabled'] ?? 0) === 1; }
    public function isActive(): bool { return (int) ($this->data['is_active'] ?? 1) === 1; }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id(),
            'contact_id'   => $this->contactId(),
            'platform'     => $this->platform(),
            'app_version'  => $this->appVersion(),
            'os_version'   => $this->osVersion(),
            'device_model' => $this->deviceModel(),
            'locale'       => $this->locale(),
            'push_enabled' => $this->pushEnabled(),
            'is_active'    => $this->isActive(),
            'last_seen_at' => (string) ($this->data['last_seen_at'] ?? ''),
        ];
    }
}
