<?php
/**
 * PortalService — read-only views over CRM/ERP + own tables.
 *
 * @package Enterprise\Modules\Portal
 */

declare(strict_types=1);

namespace Enterprise\Modules\Portal;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Modules\Audit\Logger;

final class PortalService
{
    public const TICKETS_TABLE       = 'portal_tickets';
    public const QUOTES_TABLE        = 'quote_requests';
    public const PUSH_TABLE          = 'push_subscriptions';
    public const EVENTS_TABLE        = 'portal_events';

    public const TICKET_STATUSES   = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];
    public const TICKET_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const TICKET_CATEGORIES = ['billing', 'technical', 'sales', 'shipping', 'other'];

    // ---------------- profile ----------------

    public static function getProfile(int $contactId): ?array
    {
        global $wpdb;
        $t = $wpdb->prefix . 'parsyar_contacts';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, uuid, full_name, email, phone, mobile, company, position, tags, custom_fields, created_at
             FROM {$t} WHERE id = %d LIMIT 1",
            $contactId
        ), ARRAY_A);
        if (!$row) return null;

        // سازمان مرتبط
        $org = null;
        if (class_exists('\\Enterprise\\Modules\\Crm\\OrganizationService')) {
            $orgs = \Enterprise\Modules\Crm\OrganizationService::search(['contact_id' => $contactId], 1, 0);
            $org  = $orgs[0] ?? null;
        }

        return [
            'id'            => (int) $row['id'],
            'uuid'          => (string) $row['uuid'],
            'full_name'     => (string) ($row['full_name'] ?? ''),
            'email'         => (string) ($row['email'] ?? ''),
            'phone'         => (string) ($row['phone'] ?? ''),
            'mobile'        => (string) ($row['mobile'] ?? ''),
            'company'       => $org['name'] ?? ($row['company'] ?? ''),
            'position'      => (string) ($row['position'] ?? ''),
            'tags'          => self::jsonArr($row['tags'] ?? null),
            'custom_fields' => self::jsonArr($row['custom_fields'] ?? null),
            'created_at'    => (string) ($row['created_at'] ?? ''),
        ];
    }

    // ---------------- invoices ----------------

    public static function listInvoices(int $contactId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $t = $wpdb->prefix . 'parsyar_invoices';
        $where = ['contact_id = %d'];
        $params = [$contactId];
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'issue_date >= %s';
            $params[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'issue_date <= %s';
            $params[] = (string) $filters['to'];
        }
        $whereSql = implode(' AND ', $where);
        $params[] = max(1, min(200, $limit));
        $params[] = max(0, $offset);

        $sql = $wpdb->prepare(
            "SELECT id, uuid, number, issue_date, due_date, status, total, paid, currency, tax_invoice_uid
             FROM {$t} WHERE {$whereSql} ORDER BY issue_date DESC, id DESC LIMIT %d OFFSET %d",
            $params
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map([self::class, 'mapInvoiceRow'], $rows);
    }

    public static function getInvoice(int $contactId, int $invoiceId): ?array
    {
        global $wpdb;
        $t = $wpdb->prefix . 'parsyar_invoices';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t} WHERE id = %d AND contact_id = %d LIMIT 1",
            $invoiceId, $contactId
        ), ARRAY_A);
        return $row ? self::mapInvoiceRow($row) : null;
    }

    private static function mapInvoiceRow(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'uuid'            => (string) ($r['uuid'] ?? ''),
            'number'          => (string) ($r['number'] ?? ''),
            'issue_date'      => (string) ($r['issue_date'] ?? ''),
            'due_date'        => (string) ($r['due_date'] ?? ''),
            'status'          => (string) ($r['status'] ?? ''),
            'total'           => (float) ($r['total'] ?? 0),
            'paid'            => (float) ($r['paid'] ?? 0),
            'currency'        => (string) ($r['currency'] ?? 'IRR'),
            'tax_invoice_uid' => (string) ($r['tax_invoice_uid'] ?? ''),
        ];
    }

    // ---------------- orders ----------------

    public static function listOrders(int $contactId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $t = $wpdb->prefix . 'parsyar_orders';
        $where = ['contact_id = %d'];
        $params = [$contactId];
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = (string) $filters['status'];
        }
        $whereSql = implode(' AND ', $where);
        $params[] = max(1, min(200, $limit));
        $params[] = max(0, $offset);

        $sql = $wpdb->prepare(
            "SELECT id, uuid, number, order_date, status, total, currency
             FROM {$t} WHERE {$whereSql} ORDER BY order_date DESC, id DESC LIMIT %d OFFSET %d",
            $params
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(function ($r) {
            return [
                'id'         => (int) $r['id'],
                'uuid'       => (string) ($r['uuid'] ?? ''),
                'number'     => (string) ($r['number'] ?? ''),
                'order_date' => (string) ($r['order_date'] ?? ''),
                'status'     => (string) ($r['status'] ?? ''),
                'total'      => (float) ($r['total'] ?? 0),
                'currency'   => (string) ($r['currency'] ?? 'IRR'),
            ];
        }, $rows);
    }

    // ---------------- payments ----------------

    public static function listPayments(int $contactId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $t = $wpdb->prefix . 'parsyar_payments';
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $sql = $wpdb->prepare(
            "SELECT p.id, p.uuid, p.amount, p.currency, p.status, p.method, p.paid_at, p.gateway, p.ref_id, p.invoice_id
             FROM {$t} p
             INNER JOIN {$wpdb->prefix}parsyar_invoices i ON i.id = p.invoice_id
             WHERE i.contact_id = %d
             ORDER BY p.paid_at DESC, p.id DESC LIMIT %d OFFSET %d",
            $contactId, $limit, $offset
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(function ($r) {
            return [
                'id'         => (int) $r['id'],
                'uuid'       => (string) ($r['uuid'] ?? ''),
                'amount'     => (float) ($r['amount'] ?? 0),
                'currency'   => (string) ($r['currency'] ?? 'IRR'),
                'status'     => (string) ($r['status'] ?? ''),
                'method'     => (string) ($r['method'] ?? ''),
                'paid_at'    => (string) ($r['paid_at'] ?? ''),
                'gateway'    => (string) ($r['gateway'] ?? ''),
                'ref_id'     => (string) ($r['ref_id'] ?? ''),
                'invoice_id' => (int) ($r['invoice_id'] ?? 0),
            ];
        }, $rows);
    }

    // ---------------- tickets ----------------

    public static function createTicket(int $contactId, array $data): int
    {
        $subject = sanitize_text_field((string) ($data['subject'] ?? ''));
        $body    = wp_kses_post((string) ($data['body'] ?? ''));
        $cat     = (string) ($data['category'] ?? 'other');
        $pri     = (string) ($data['priority'] ?? 'normal');

        if ($subject === '' || mb_strlen($subject) < 3) {
            throw new \InvalidArgumentException('موضوع تیکت باید حداقل ۳ کاراکتر باشد.');
        }
        if ($body === '' || mb_strlen($body) < 10) {
            throw new \InvalidArgumentException('شرح تیکت باید حداقل ۱۰ کاراکتر باشد.');
        }
        if (!in_array($cat, self::TICKET_CATEGORIES, true)) {
            $cat = 'other';
        }
        if (!in_array($pri, self::TICKET_PRIORITIES, true)) {
            $pri = 'normal';
        }

        $uuid = self::uuid();
        $id = Db::insert(self::TICKETS_TABLE, [
            'uuid'        => $uuid,
            'contact_id'  => $contactId,
            'subject'     => $subject,
            'body'        => $body,
            'category'    => $cat,
            'priority'    => $pri,
            'status'      => 'open',
            'attachments' => wp_json_encode(self::normalizeAttachments($data['attachments'] ?? [])),
            'created_at'  => current_time('mysql', true),
            'updated_at'  => current_time('mysql', true),
        ]);

        Logger::log('portal_ticket', $id, 'create', [
            'contact_id' => $contactId, 'category' => $cat, 'priority' => $pri,
        ]);
        do_action('enterprise_event', 'portal.ticket.created', [
            'ticket_id' => $id, 'contact_id' => $contactId, 'category' => $cat, 'priority' => $pri,
        ]);
        return $id;
    }

    public static function listTickets(int $contactId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $t = Db::table(self::TICKETS_TABLE);
        $where = ['contact_id = %d'];
        $params = [$contactId];
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = (string) $filters['status'];
        }
        $whereSql = implode(' AND ', $where);
        $params[] = max(1, min(200, $limit));
        $params[] = max(0, $offset);
        $sql = $wpdb->prepare("SELECT id, uuid, subject, status, priority, category, created_at, updated_at FROM {$t} WHERE {$whereSql} ORDER BY id DESC LIMIT %d OFFSET %d", $params);
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(function ($r) {
            return [
                'id'         => (int) $r['id'],
                'uuid'       => (string) $r['uuid'],
                'subject'    => (string) $r['subject'],
                'status'     => (string) $r['status'],
                'priority'   => (string) $r['priority'],
                'category'   => (string) $r['category'],
                'created_at' => (string) $r['created_at'],
                'updated_at' => (string) ($r['updated_at'] ?? ''),
            ];
        }, $rows);
    }

    public static function getTicket(int $contactId, int $ticketId): ?array
    {
        global $wpdb;
        $t = Db::table(self::TICKETS_TABLE);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t} WHERE id = %d AND contact_id = %d LIMIT 1",
            $ticketId, $contactId
        ), ARRAY_A);
        if (!$row) return null;
        $row['attachments'] = self::jsonArr($row['attachments'] ?? null);
        return $row;
    }

    public static function replyTicket(int $contactId, int $ticketId, string $body): bool
    {
        $body = wp_kses_post($body);
        if (mb_strlen($body) < 2) {
            throw new \InvalidArgumentException('پاسخ بسیار کوتاه است.');
        }
        $t = Db::table(self::TICKETS_TABLE);
        $existing = Db::getRow(self::TICKETS_TABLE, ['id' => $ticketId]);
        if (!$existing || (int) $existing['contact_id'] !== $contactId) {
            return false;
        }
        Db::update(self::TICKETS_TABLE, [
            'customer_reply' => $body,
            'customer_replied_at' => current_time('mysql', true),
            'status'         => 'waiting_customer',
            'updated_at'     => current_time('mysql', true),
        ], ['id' => $ticketId]);
        Logger::log('portal_ticket', $ticketId, 'customer_reply', ['contact_id' => $contactId]);
        return true;
    }

    // ---------------- quote requests ----------------

    public static function createQuoteRequest(int $contactId, array $data): int
    {
        $notes = wp_kses_post((string) ($data['notes'] ?? ''));
        if ($notes === '' || mb_strlen($notes) < 10) {
            throw new \InvalidArgumentException('شرح درخواست باید حداقل ۱۰ کاراکتر باشد.');
        }
        $items = self::normalizeQuoteItems($data['items'] ?? []);
        if (empty($items)) {
            throw new \InvalidArgumentException('حداقل یک ردیف کالا لازم است.');
        }
        $id = Db::insert(self::QUOTES_TABLE, [
            'uuid'         => self::uuid(),
            'contact_id'   => $contactId,
            'notes'        => $notes,
            'items_json'   => wp_json_encode($items),
            'status'       => 'pending',
            'created_at'   => current_time('mysql', true),
        ]);
        Logger::log('portal_quote', $id, 'create', ['contact_id' => $contactId, 'items' => count($items)]);
        do_action('enterprise_event', 'portal.quote.created', ['quote_id' => $id, 'contact_id' => $contactId]);
        return $id;
    }

    // ---------------- push subscriptions ----------------

    public static function savePushSubscription(int $contactId, array $payload): array
    {
        $endpoint = esc_url_raw((string) ($payload['endpoint'] ?? ''));
        $keys     = is_array($payload['keys'] ?? null) ? $payload['keys'] : [];
        $p256dh   = (string) ($keys['p256dh'] ?? '');
        $auth     = (string) ($keys['auth'] ?? '');
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new \InvalidArgumentException('اشتراک WebPush ناقص است.');
        }
        global $wpdb;
        $t = Db::table(self::PUSH_TABLE);
        // dedup on endpoint
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$t} WHERE endpoint = %s LIMIT 1", $endpoint), ARRAY_A);
        $data = [
            'contact_id'  => $contactId,
            'endpoint'    => $endpoint,
            'p256dh'      => $p256dh,
            'auth'        => $auth,
            'user_agent'  => substr((string) ($payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            'updated_at'  => current_time('mysql', true),
        ];
        if ($existing) {
            Db::update(self::PUSH_TABLE, $data, ['id' => (int) $existing['id']]);
            return ['id' => (int) $existing['id'], 'updated' => true];
        }
        $data['created_at'] = current_time('mysql', true);
        $id = Db::insert(self::PUSH_TABLE, $data);
        return ['id' => (int) $id, 'created' => true];
    }

    public static function deletePushSubscription(int $contactId, string $endpoint): bool
    {
        global $wpdb;
        $t = Db::table(self::PUSH_TABLE);
        $n = (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$t} WHERE contact_id = %d AND endpoint = %s",
            $contactId, $endpoint
        ));
        return $n > 0;
    }

    /**
     * ارسال push به همه endpointهای فعال یک contact.
     */
    public static function sendPush(int $contactId, string $title, string $body, array $data = []): int
    {
        global $wpdb;
        $t = Db::table(self::PUSH_TABLE);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE contact_id = %d", $contactId), ARRAY_A) ?: [];
        $sent = 0;
        foreach ($rows as $row) {
            $ok = self::dispatchWebPush($row, $title, $body, $data);
            if ($ok) $sent++;
        }
        return $sent;
    }

    /**
     * ارسال واقعی WebPush با VAPID.
     * در این نسخه، payload ساخته می‌شود و در لاگ ثبت می‌شود.
     * در فاز بعد، کتابخانهٔ minishlink/web-push اضافه خواهد شد.
     */
    private static function dispatchWebPush(array $sub, string $title, string $body, array $data): bool
    {
        // ذخیرهٔ رویداد برای observability
        Logger::log('portal_push', (int) $sub['id'], 'send', [
            'contact_id' => (int) $sub['contact_id'],
            'title'      => $title,
            'body_len'   => strlen($body),
            'data_keys'  => array_keys($data),
        ]);
        do_action('enterprise_portal_push_send', $sub, $title, $body, $data);
        return true;
    }

    // ---------------- events (client telemetry) ----------------

    public static function logClientEvent(int $contactId, array $payload): int
    {
        $eventId = sanitize_text_field((string) ($payload['event_id'] ?? ''));
        if ($eventId === '') {
            $eventId = self::uuid();
        }
        $type = sanitize_key((string) ($payload['type'] ?? 'unknown'));
        // idempotency
        global $wpdb;
        $t = Db::table(self::EVENTS_TABLE);
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE event_id = %s LIMIT 1", $eventId));
        if ($existing) return (int) $existing;

        $id = Db::insert(self::EVENTS_TABLE, [
            'event_id'   => $eventId,
            'contact_id' => $contactId,
            'type'       => $type,
            'payload'    => wp_json_encode($payload['payload'] ?? []),
            'client_ts'  => sanitize_text_field((string) ($payload['client_ts'] ?? '')),
            'created_at' => current_time('mysql', true),
        ]);
        return (int) $id;
    }

    // ---------------- helpers ----------------

    private static function normalizeAttachments($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach (array_slice($raw, 0, 5) as $a) {
            if (!is_array($a)) continue;
            $url = esc_url_raw((string) ($a['url'] ?? ''));
            $name = sanitize_file_name((string) ($a['name'] ?? ''));
            if ($url !== '') {
                $out[] = ['url' => $url, 'name' => $name];
            }
        }
        return $out;
    }

    private static function normalizeQuoteItems($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach (array_slice($raw, 0, 50) as $it) {
            if (!is_array($it)) continue;
            $name = sanitize_text_field((string) ($it['name'] ?? ''));
            $qty  = (float) ($it['qty'] ?? 0);
            if ($name === '' || $qty <= 0) continue;
            $out[] = [
                'name'     => $name,
                'qty'      => $qty,
                'sku'      => sanitize_text_field((string) ($it['sku'] ?? '')),
                'note'     => sanitize_text_field((string) ($it['note'] ?? '')),
            ];
        }
        return $out;
    }

    private static function jsonArr($v): array
    {
        if (is_array($v)) return $v;
        if (!is_string($v) || $v === '') return [];
        $d = json_decode($v, true);
        return is_array($d) ? $d : [];
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
