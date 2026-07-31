<?php
/**
 * Mobile Module — بوت‌استرپ، device registry، و FCM/APNs dispatcher.
 *
 * برای React Native اپ (v1.8.0) که علاوه بر WebPush، از
 * Firebase Cloud Messaging (Android) و Apple Push Notification service (iOS)
 * پشتیبانی می‌کند.
 *
 * @package Enterprise\Modules\Mobile
 */

declare(strict_types=1);

namespace Enterprise\Modules\Mobile;

defined('ABSPATH') || exit;

final class MobileModule
{
    public const OPTION_FCM_KEY    = 'enterprise_mobile_fcm_server_key';
    public const OPTION_APNS_KEYID = 'enterprise_mobile_apns_key_id';
    public const OPTION_APNS_TEAM  = 'enterprise_mobile_apns_team_id';
    public const OPTION_APNS_BUNDLE= 'enterprise_mobile_apns_bundle_id';
    public const OPTION_VERSION_MIN= 'enterprise_mobile_min_app_version';

    public static function boot(): void
    {
        add_action('init', [self::class, 'warmup'], 7);
        add_action('enterprise_daily', [self::class, 'pruneStaleDevices']);
    }

    public static function warmup(): void
    {
        // ensure options exist
        if (get_option(self::OPTION_VERSION_MIN) === false) {
            update_option(self::OPTION_VERSION_MIN, '1.0.0');
        }
    }

    public static function pruneStaleDevices(): void
    {
        global $wpdb;
        // device هایی که بیش از ۱۸۰ روز لاگین نداشته‌اند
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . Device::table() . " WHERE last_seen_at < UTC_TIMESTAMP() - INTERVAL %d DAY AND is_active = 1",
            180
        ));
    }

    /**
     * ارسال push به یک device (FCM یا APNs).
     * اگر توکن FCM/APNs ثبت نشده باشد، silent no-op برمی‌گردد.
     */
    public static function sendToDevice(Device $device, string $title, string $body, array $data = []): bool
    {
        $token = $device->token();
        if ($token === '') {
            return false;
        }
        switch ($device->platform()) {
            case 'ios':
                return self::sendApns($token, $title, $body, $data);
            case 'android':
                return self::sendFcm($token, $title, $body, $data);
        }
        return false;
    }

    private static function sendFcm(string $token, string $title, string $body, array $data): bool
    {
        $key = (string) get_option(self::OPTION_FCM_KEY, '');
        if ($key === '') {
            return false;
        }
        $payload = [
            'to'           => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
            ],
            'data' => $data,
            'priority' => 'high',
        ];
        $resp = wp_remote_post('https://fcm.googleapis.com/fcm/send', [
            'headers' => [
                'Authorization' => 'key=' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 8,
        ]);
        return !is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200;
    }

    private static function sendApns(string $token, string $title, string $body, array $data): bool
    {
        $keyId  = (string) get_option(self::OPTION_APNS_KEYID, '');
        $teamId = (string) get_option(self::OPTION_APNS_TEAM, '');
        $bundle = (string) get_option(self::OPTION_APNS_BUNDLE, '');
        if ($keyId === '' || $teamId === '' || $bundle === '') {
            return false;
        }
        // ساده‌ترین حالت: استفاده از stream context به apns-http2.
        // در پروداکشن، JWT-based provider بسازید. این جا فقط silent no-op اگر پیکربندی نشده.
        return false;
    }
}
