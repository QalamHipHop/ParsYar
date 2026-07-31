<?php
/**
 * Portal REST Controller — /wp-json/enterprise/v1/portal/*
 *
 * @package Enterprise\Api\Portal
 */

declare(strict_types=1);

namespace Enterprise\Api\Portal;

defined('ABSPATH') || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Enterprise\Modules\Portal\AuthService;
use Enterprise\Modules\Portal\PortalService;

final class PortalController
{
    public const NS = 'enterprise/v1';

    public static function registerRoutes(): void
    {
        $base = 'portal';

        // auth
        register_rest_route(self::NS, "/{$base}/auth/magic-link", [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'requestMagicLink'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, "/{$base}/auth/verify", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'verifyMagicLink'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, "/{$base}/auth/refresh", [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'refresh'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, "/{$base}/auth/logout", [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'logout'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/auth/vapid-public-key", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'vapidPublicKey'],
            'permission_callback' => '__return_true',
        ]);

        // protected
        register_rest_route(self::NS, "/{$base}/me", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'me'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/invoices", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'listInvoices'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/invoices/(?P<id>\d+)", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'getInvoice'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/orders", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'listOrders'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/payments", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'listPayments'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/tickets", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'listTickets'],
                'permission_callback' => [self::class, 'isAuthed'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'createTicket'],
                'permission_callback' => [self::class, 'isAuthed'],
            ],
        ]);
        register_rest_route(self::NS, "/{$base}/tickets/(?P<id>\d+)", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'getTicket'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/tickets/(?P<id>\d+)/reply", [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'replyTicket'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/quotes/request", [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'createQuoteRequest'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/push/subscribe", [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'pushSubscribe'],
                'permission_callback' => [self::class, 'isAuthed'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [self::class, 'pushUnsubscribe'],
                'permission_callback' => [self::class, 'isAuthed'],
            ],
        ]);
        register_rest_route(self::NS, "/{$base}/portal-event", [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'logEvent'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
        register_rest_route(self::NS, "/{$base}/notifications", [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'listNotifications'],
            'permission_callback' => [self::class, 'isAuthed'],
        ]);
    }

    // ---------- helpers ----------

    public static function isAuthed(WP_REST_Request $req): bool|WP_Error
    {
        if (AuthService::isIpBanned()) {
            return new WP_Error('parsyar.portal.banned', 'دسترسی موقتاً مسدود است.', ['status' => 429]);
        }
        $auth = $req->get_header('authorization');
        if (!$auth) {
            return new WP_Error('parsyar.portal.unauthorized', 'نیاز به لاگین.', ['status' => 401]);
        }
        if (stripos($auth, 'Bearer ') !== 0) {
            return new WP_Error('parsyar.portal.invalid_auth_scheme', 'Authorization scheme باید Bearer باشد.', ['status' => 401]);
        }
        $jwt = trim(substr($auth, 7));
        try {
            $payload = AuthService::validateJwt($jwt);
            $req->set_param('_portal_contact_id', (int) ($payload['sub'] ?? 0));
            $req->set_param('_portal_jti', (string) ($payload['jti'] ?? ''));
            return true;
        } catch (\Throwable $e) {
            return new WP_Error('parsyar.portal.invalid_token', $e->getMessage(), ['status' => 401]);
        }
    }

    private static function contactId(WP_REST_Request $req): int
    {
        return (int) $req->get_param('_portal_contact_id');
    }

    private static function ok($data = null, int $status = 200, array $meta = []): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'data' => $data, 'meta' => $meta], $status);
    }

    private static function fail(string $code, string $msg, int $status = 400, array $details = []): WP_Error
    {
        return new WP_Error($code, $msg, array_merge(['status' => $status], $details));
    }

    // ---------- auth ----------

    public static function requestMagicLink(WP_REST_Request $req)
    {
        if (AuthService::isIpBanned()) {
            return self::fail('parsyar.portal.banned', 'تلاش‌های زیاد. بعداً تلاش کنید.', 429);
        }
        $email = (string) $req->get_param('email');
        $device = (string) $req->get_param('device_label');
        try {
            $r = AuthService::requestMagicLink($email, $device !== '' ? $device : null);
            return self::ok($r, 200, ['email' => $email]);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.portal.invalid_email', $e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return self::fail('parsyar.portal.rate_limited', $e->getMessage(), 429);
        }
    }

    public static function verifyMagicLink(WP_REST_Request $req)
    {
        $token = (string) $req->get_param('token');
        $ua    = (string) ($req->get_header('user_agent') ?? '');
        try {
            $tokens = AuthService::verifyMagicLink($token, $ua);
            return self::ok($tokens);
        } catch (\Throwable $e) {
            return self::fail('parsyar.portal.verify_failed', $e->getMessage(), 401);
        }
    }

    public static function refresh(WP_REST_Request $req)
    {
        $refresh = (string) $req->get_param('refresh_token');
        $ua      = (string) ($req->get_header('user_agent') ?? '');
        if ($refresh === '') {
            return self::fail('parsyar.portal.missing_refresh', 'refresh_token الزامی است.', 400);
        }
        global $wpdb;
        $t = \Enterprise\Support\Db::table(AuthService::SESSIONS_TABLE);
        $rows = $wpdb->get_results("SELECT * FROM {$t} WHERE refresh_exp > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 200", ARRAY_A);
        $matched = null;
        foreach ($rows as $row) {
            if (password_verify($refresh, (string) $row['refresh_hash'])) {
                $matched = $row;
                break;
            }
        }
        if (!$matched) {
            return self::fail('parsyar.portal.refresh_invalid', 'refresh_token نامعتبر است.', 401);
        }
        // rotate: حذف session قبلی + صدور session جدید
        \Enterprise\Support\Db::delete(AuthService::SESSIONS_TABLE, ['id' => (int) $matched['id']]);
        $tokens = AuthService::issueSession((int) $matched['contact_id'], $ua, 'refresh');
        return self::ok($tokens);
    }

    public static function logout(WP_REST_Request $req)
    {
        $jti = (string) $req->get_param('_portal_jti');
        if ($jti !== '') {
            AuthService::revokeSession($jti);
        }
        return self::ok(['revoked' => true]);
    }

    public static function vapidPublicKey()
    {
        return self::ok(['key' => AuthService::vapidPublicKey()]);
    }

    // ---------- profile ----------

    public static function me(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $profile = PortalService::getProfile($cid);
        if (!$profile) {
            return self::fail('parsyar.portal.profile_not_found', 'پروفایل پیدا نشد.', 404);
        }
        return self::ok($profile);
    }

    // ---------- invoices ----------

    public static function listInvoices(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $filters = array_filter([
            'status' => $req->get_param('status'),
            'from'   => $req->get_param('from'),
            'to'     => $req->get_param('to'),
        ], fn($v) => $v !== null && $v !== '');
        $limit  = (int) ($req->get_param('limit') ?? 50);
        $offset = (int) ($req->get_param('offset') ?? 0);
        $rows = PortalService::listInvoices($cid, $filters, $limit, $offset);
        return self::ok($rows, 200, ['limit' => $limit, 'offset' => $offset, 'count' => count($rows)]);
    }

    public static function getInvoice(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $id  = (int) $req->get_param('id');
        $row = PortalService::getInvoice($cid, $id);
        if (!$row) {
            return self::fail('parsyar.portal.invoice_not_found', 'فاکتور پیدا نشد.', 404);
        }
        return self::ok($row);
    }

    public static function listOrders(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $filters = array_filter(['status' => $req->get_param('status')], fn($v) => $v !== null && $v !== '');
        $limit  = (int) ($req->get_param('limit') ?? 50);
        $offset = (int) ($req->get_param('offset') ?? 0);
        $rows = PortalService::listOrders($cid, $filters, $limit, $offset);
        return self::ok($rows, 200, ['count' => count($rows)]);
    }

    public static function listPayments(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $limit  = (int) ($req->get_param('limit') ?? 50);
        $offset = (int) ($req->get_param('offset') ?? 0);
        $rows = PortalService::listPayments($cid, $limit, $offset);
        return self::ok($rows, 200, ['count' => count($rows)]);
    }

    // ---------- tickets ----------

    public static function createTicket(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $data = $req->get_json_params() ?: [];
        try {
            $id = PortalService::createTicket($cid, $data);
            return self::ok(['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.portal.invalid_ticket', $e->getMessage(), 422);
        }
    }

    public static function listTickets(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $filters = array_filter(['status' => $req->get_param('status')], fn($v) => $v !== null && $v !== '');
        $limit  = (int) ($req->get_param('limit') ?? 50);
        $offset = (int) ($req->get_param('offset') ?? 0);
        $rows = PortalService::listTickets($cid, $filters, $limit, $offset);
        return self::ok($rows, 200, ['count' => count($rows)]);
    }

    public static function getTicket(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $id  = (int) $req->get_param('id');
        $row = PortalService::getTicket($cid, $id);
        if (!$row) {
            return self::fail('parsyar.portal.ticket_not_found', 'تیکت پیدا نشد.', 404);
        }
        return self::ok($row);
    }

    public static function replyTicket(WP_REST_Request $req)
    {
        $cid  = self::contactId($req);
        $id   = (int) $req->get_param('id');
        $body = (string) ($req->get_json_params()['body'] ?? '');
        try {
            $ok = PortalService::replyTicket($cid, $id, $body);
            if (!$ok) {
                return self::fail('parsyar.portal.ticket_reply_failed', 'تیکت پیدا نشد یا دسترسی ندارید.', 404);
            }
            return self::ok(['updated' => true]);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.portal.invalid_reply', $e->getMessage(), 422);
        }
    }

    public static function createQuoteRequest(WP_REST_Request $req)
    {
        $cid  = self::contactId($req);
        $data = $req->get_json_params() ?: [];
        try {
            $id = PortalService::createQuoteRequest($cid, $data);
            return self::ok(['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.portal.invalid_quote', $e->getMessage(), 422);
        }
    }

    // ---------- push ----------

    public static function pushSubscribe(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $body = $req->get_json_params() ?: [];
        try {
            $r = PortalService::savePushSubscription($cid, $body);
            return self::ok($r, 201);
        } catch (\InvalidArgumentException $e) {
            return self::fail('parsyar.portal.invalid_push', $e->getMessage(), 422);
        }
    }

    public static function pushUnsubscribe(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        $endpoint = (string) ($req->get_json_params()['endpoint'] ?? $req->get_param('endpoint') ?? '');
        if ($endpoint === '') {
            return self::fail('parsyar.portal.missing_endpoint', 'endpoint الزامی است.', 400);
        }
        $ok = PortalService::deletePushSubscription($cid, $endpoint);
        return self::ok(['deleted' => $ok]);
    }

    // ---------- events / notifications ----------

    public static function logEvent(WP_REST_Request $req)
    {
        $cid  = self::contactId($req);
        $body = $req->get_json_params() ?: [];
        $id = PortalService::logClientEvent($cid, $body);
        return self::ok(['id' => $id], 202);
    }

    public static function listNotifications(WP_REST_Request $req)
    {
        $cid = self::contactId($req);
        // feed از ticket replies + events اخیر
        $tickets = PortalService::listTickets($cid, [], 20, 0);
        $items = [];
        foreach ($tickets as $t) {
            if (!empty($t['updated_at']) && (string) $t['status'] === 'waiting_customer') {
                $items[] = [
                    'kind'    => 'ticket_reply',
                    'title'   => 'پاسخ جدید به تیکت شما',
                    'body'    => $t['subject'],
                    'ref_id'  => $t['id'],
                    'ts'      => $t['updated_at'],
                ];
            }
        }
        return self::ok($items, 200, ['count' => count($items)]);
    }
}
