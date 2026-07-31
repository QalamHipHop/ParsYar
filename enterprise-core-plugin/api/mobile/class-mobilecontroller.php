<?php
/**
 * Mobile REST Controller — /wp-json/enterprise/v1/mobile/*
 *
 * endpoint های اختصاصی اپ React Native:
 *   - /mobile/info                : version + min_version + maintenance
 *   - /mobile/devices/register    : ثبت FCM/APNs token
 *   - /mobile/devices/heartbeat   : فعال نگه‌داشتن device
 *   - /mobile/devices/delete      : حذف device پس از logout
 *   - /mobile/notifications/test  : تست ارسال push (admin)
 *
 * @package Enterprise\Api\Mobile
 */

declare(strict_types=1);

namespace Enterprise\Api\Mobile;

defined('ABSPATH') || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Enterprise\Bootstrap;
use Enterprise\Modules\Mobile\Device;
use Enterprise\Modules\Mobile\MobileModule;

final class MobileController
{
    public const NS = 'enterprise/v1';

    public static function registerRoutes(): void
    {
        $ns = self::NS;

        register_rest_route($ns, '/mobile/info', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'info'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/mobile/devices/register', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'deviceRegister'],
            'permission_callback' => [\Enterprise\Api\Portal\PortalController::class, 'isAuthed'],
        ]);
        register_rest_route($ns, '/mobile/devices/heartbeat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'deviceHeartbeat'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/mobile/devices/delete', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [self::class, 'deviceDelete'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/mobile/notifications/test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'notificationTest'],
            'permission_callback' => [self::class, 'capAdmin'],
        ]);
    }

    /* ============ Permission callbacks ============ */

    public static function capAdmin(): bool
    {
        return current_user_can('manage_options') || current_user_can('manage_enterprise');
    }

    private static function portalContactId(WP_REST_Request $req): int
    {
        return (int) ($req->get_param('_portal_contact_id') ?? 0);
    }

    /* ============ Endpoints ============ */

    public static function info(WP_REST_Request $req): WP_REST_Response
    {
        $min = (string) get_option(MobileModule::OPTION_VERSION_MIN, '1.0.0');
        $maintenance = (bool) get_option('parsyar_maintenance_mode', false);
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'app_name'        => 'ParsYar Enterprise',
                'app_version'     => '1.8.0',
                'min_app_version' => $min,
                'api_version'     => Bootstrap::NS,
                'platforms'       => Device::PLATFORMS,
                'maintenance'     => $maintenance,
                'features'        => [
                    'magic_link'      => true,
                    'jwt_refresh'     => true,
                    'biometric'       => true,
                    'push_fcm'        => (string) get_option(MobileModule::OPTION_FCM_KEY) !== '',
                    'push_apns'       => (string) get_option(MobileModule::OPTION_APNS_KEYID) !== '',
                    'multitenant'     => true,
                ],
            ],
            'meta' => [],
        ], 200);
    }

    public static function deviceRegister(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $cid = self::portalContactId($req);
        if ($cid <= 0) {
            return new WP_Error('parsyar.mobile.unauthorized', 'نیاز به لاگین.', ['status' => 401]);
        }
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $body['contact_id'] = $cid;
        try {
            $id = Device::register($body);
        } catch (\InvalidArgumentException $e) {
            return new WP_Error('parsyar.mobile.invalid', $e->getMessage(), ['status' => 422]);
        } catch (\Throwable $e) {
            return new WP_Error('parsyar.mobile.register_failed', $e->getMessage(), ['status' => 500]);
        }
        $d = Device::find($id);
        return new WP_REST_Response(['success' => true, 'data' => $d ? $d->toArray() : ['id' => $id], 'meta' => []], 201);
    }

    public static function deviceHeartbeat(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $deviceId = (int) ($body['device_id'] ?? 0);
        $token = (string) ($body['token'] ?? '');
        if ($deviceId > 0) {
            Device::touch($deviceId);
            return new WP_REST_Response(['success' => true, 'data' => ['touched' => true], 'meta' => []], 200);
        }
        if ($token !== '') {
            global $wpdb;
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . Device::table() . " WHERE token = %s LIMIT 1",
                $token
            ));
            if ($id > 0) {
                Device::touch($id);
            }
            return new WP_REST_Response(['success' => true, 'data' => ['touched' => $id > 0], 'meta' => []], 200);
        }
        return new WP_Error('parsyar.mobile.missing', 'device_id یا token الزامی است.', ['status' => 422]);
    }

    public static function deviceDelete(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $token = (string) ($body['token'] ?? '');
        if ($token === '') {
            return new WP_Error('parsyar.mobile.missing_token', 'token الزامی است.', ['status' => 422]);
        }
        global $wpdb;
        $r = $wpdb->delete(Device::table(), ['token' => $token]);
        return new WP_REST_Response(['success' => true, 'data' => ['deleted' => (int) $r > 0], 'meta' => []], 200);
    }

    public static function notificationTest(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $body = (array) ($req->get_json_params() ?: $req->get_body_params());
        $deviceId = (int) ($body['device_id'] ?? 0);
        $title = (string) ($body['title'] ?? 'ParsYar تستی');
        $text  = (string) ($body['body']  ?? 'این یک پیام آزمایشی است.');
        if ($deviceId <= 0) {
            return new WP_Error('parsyar.mobile.missing_device', 'device_id الزامی است.', ['status' => 422]);
        }
        $d = Device::find($deviceId);
        if (!$d) {
            return new WP_Error('parsyar.mobile.device_not_found', 'device پیدا نشد.', ['status' => 404]);
        }
        $ok = MobileModule::sendToDevice($d, $title, $text, ['test' => '1']);
        return new WP_REST_Response(['success' => true, 'data' => ['sent' => $ok], 'meta' => []], 200);
    }
}
