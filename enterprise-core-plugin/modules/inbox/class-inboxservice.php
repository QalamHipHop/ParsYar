<?php
/**
 * Inbox Service — صندوق ورودی یکپارچه چندکاناله
 *
 * کانال‌ها: email, sms, whatsapp, telegram, bale, rubika, instagram, webchat, voice
 *
 * @package Enterprise\Modules\Inbox
 */

declare(strict_types=1);

namespace Enterprise\Modules\Inbox;

defined('ABSPATH') || exit;

use Enterprise\Jalali;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class InboxService
{
    public const TABLE_THREAD   = 'inbox_threads';
    public const TABLE_MESSAGE  = 'inbox_messages';
    public const TABLE_CHANNEL  = 'inbox_channels';

    public const CHANNELS = [
        'email'     => 'ایمیل',
        'sms'       => 'پیامک',
        'whatsapp'  => 'واتساپ',
        'telegram'  => 'تلگرام',
        'bale'      => 'بله',
        'rubika'    => 'روبیکا',
        'instagram' => 'اینستاگرام',
        'webchat'   => 'گفتگوی وب',
        'voice'     => 'تماس صوتی',
        'soroush'   => 'سروش',
        'eitaa'     => 'ایتا',
        'gap'       => 'گپ',
    ];

    public const DIRECTIONS = ['inbound', 'outbound'];
    public const STATUSES   = ['unread', 'read', 'replied', 'archived', 'spam', 'trash'];

    // -------- Channel management --------

    public static function registerChannel(string $code, array $config): int
    {
        $existing = Db::getRow(self::TABLE_CHANNEL, ['code' => $code]);
        $data = [
            'code'        => $code,
            'name'        => self::CHANNELS[$code] ?? $code,
            'is_active'   => !empty($config['is_active']) ? 1 : 0,
            'config_json' => wp_json_encode($config),
            'updated_at'  => current_time('mysql', true),
        ];
        if ($existing) {
            Db::update(self::TABLE_CHANNEL, $data, ['id' => $existing['id']]);
            return (int) $existing['id'];
        }
        $data['created_at'] = current_time('mysql', true);
        return Db::insert(self::TABLE_CHANNEL, $data);
    }

    public static function getChannel(string $code): ?array
    {
        $row = Db::getRow(self::TABLE_CHANNEL, ['code' => $code]);
        if ($row) {
            $row['config'] = json_decode((string) ($row['config_json'] ?? '{}'), true) ?: [];
        }
        return $row;
    }

    public static function listChannels(bool $activeOnly = false): array
    {
        $where = $activeOnly ? ['is_active' => 1] : [];
        $rows = Db::getResults(self::TABLE_CHANNEL, $where, 'name ASC', 50, 0);
        return array_map(static function ($r) {
            $r['config'] = json_decode((string) ($r['config_json'] ?? '{}'), true) ?: [];
            return $r;
        }, $rows);
    }

    // -------- Threads --------

    public static function findOrCreateThread(string $channel, string $externalId, array $extra = []): int
    {
        $existing = Db::getRow(self::TABLE_THREAD, [
            'channel'     => $channel,
            'external_id' => $externalId,
        ]);
        if ($existing) {
            // بازگرداندن به unread در صورت پیام جدید
            return (int) $existing['id'];
        }
        $id = Db::insert(self::TABLE_THREAD, [
            'uuid'         => self::uuid(),
            'channel'      => $channel,
            'external_id'  => $externalId,
            'subject'      => $extra['subject'] ?? null,
            'contact_id'   => $extra['contact_id'] ?? null,
            'participant_name' => $extra['participant_name'] ?? null,
            'participant_handle' => $extra['participant_handle'] ?? null,
            'participant_avatar' => $extra['participant_avatar'] ?? null,
            'status'       => 'open',
            'unread_count' => 0,
            'created_at'   => current_time('mysql', true),
            'updated_at'   => current_time('mysql', true),
        ]);
        return $id;
    }

    public static function listThreads(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'last_message_at DESC'): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['channel'])) {
            $where[] = 'channel = %s';
            $params[] = $filters['channel'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['unread'])) {
            $where[] = 'unread_count > 0';
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 'assigned_to = %d';
            $params[] = (int) $filters['assigned_to'];
        }
        if (!empty($filters['contact_id'])) {
            $where[] = 'contact_id = %d';
            $params[] = (int) $filters['contact_id'];
        }
        if (empty($filters['include_archived'])) {
            $where[] = "status != 'archived'";
        }

        $sql = 'SELECT * FROM ' . Db::table(self::TABLE_THREAD)
             . ' WHERE ' . implode(' AND ', $where)
             . ' ORDER BY ' . sanitize_sql_orderby($order)
             . ' LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    public static function countUnread(string $channel = '', int $userId = 0): int
    {
        global $wpdb;
        $where = ['unread_count > 0', "status != 'archived'"];
        $params = [];
        if ($channel) {
            $where[] = 'channel = %s';
            $params[] = $channel;
        }
        if ($userId) {
            $where[] = 'assigned_to = %d';
            $params[] = $userId;
        }
        $sql = 'SELECT SUM(unread_count) FROM ' . Db::table(self::TABLE_THREAD)
             . ' WHERE ' . implode(' AND ', $where);
        return (int) ($wpdb->get_var($params ? $wpdb->prepare($sql, $params) : $sql) ?: 0);
    }

    // -------- Messages --------

    public static function addMessage(int $threadId, string $direction, string $body, array $extra = []): int
    {
        $data = [
            'uuid'         => self::uuid(),
            'thread_id'    => $threadId,
            'direction'    => $direction,
            'body'         => $body,
            'body_html'    => $extra['body_html'] ?? null,
            'media_urls'   => !empty($extra['media_urls']) ? wp_json_encode($extra['media_urls']) : null,
            'attachments'  => !empty($extra['attachments']) ? wp_json_encode($extra['attachments']) : null,
            'sender_id'    => $extra['sender_id'] ?? ($direction === 'outbound' ? get_current_user_id() : null),
            'sender_name'  => $extra['sender_name'] ?? null,
            'external_id'  => $extra['external_id'] ?? null,
            'status'       => $extra['status'] ?? 'sent',
            'is_read'      => $direction === 'outbound' ? 1 : 0,
            'sent_at'      => $extra['sent_at'] ?? current_time('mysql', true),
        ];
        $id = Db::insert(self::TABLE_MESSAGE, $data);

        // به‌روزرسانی thread
        $thread = Db::getRow(self::TABLE_THREAD, ['id' => $threadId]);
        $unreadInc = $direction === 'inbound' ? 1 : 0;
        $preview = mb_substr(wp_strip_all_tags($body), 0, 100);
        Db::update(self::TABLE_THREAD, [
            'last_message_at' => $data['sent_at'],
            'last_message_preview' => $preview,
            'unread_count'    => (int) ($thread['unread_count'] ?? 0) + $unreadInc,
            'updated_at'      => current_time('mysql', true),
        ], ['id' => $threadId]);

        do_action('enterprise_event', 'inbox.message_received', [
            'thread_id' => $threadId,
            'message_id' => $id,
            'channel'   => $thread['channel'] ?? null,
            'direction' => $direction,
        ]);
        return $id;
    }

    public static function listMessages(int $threadId, int $limit = 100, int $offset = 0, string $order = 'sent_at ASC'): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . Db::table(self::TABLE_MESSAGE) . ' WHERE thread_id = %d ORDER BY ' . sanitize_sql_orderby($order) . ' LIMIT %d OFFSET %d',
            $threadId, $limit, $offset
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(static function ($r) {
            $r['media_urls']  = $r['media_urls']  ? (json_decode($r['media_urls'], true) ?: []) : [];
            $r['attachments'] = $r['attachments'] ? (json_decode($r['attachments'], true) ?: []) : [];
            return $r;
        }, $rows);
    }

    public static function markRead(int $threadId, int $userId = 0): bool
    {
        Db::update(self::TABLE_MESSAGE, ['is_read' => 1, 'read_at' => current_time('mysql', true)], ['thread_id' => $threadId, 'is_read' => 0]);
        Db::update(self::TABLE_THREAD, ['unread_count' => 0], ['id' => $threadId]);
        do_action('enterprise_event', 'inbox.marked_read', ['thread_id' => $threadId, 'user_id' => $userId]);
        return true;
    }

    public static function archive(int $threadId): bool
    {
        Db::update(self::TABLE_THREAD, ['status' => 'archived'], ['id' => $threadId]);
        return true;
    }

    public static function assignThread(int $threadId, int $userId): bool
    {
        Db::update(self::TABLE_THREAD, ['assigned_to' => $userId], ['id' => $threadId]);
        return true;
    }

    // -------- Inbox: incoming webhook helpers --------

    /**
     * ورودی از webhook کانال عمومی (همهٔ مسنجرها).
     */
    public static function handleIncoming(string $channel, string $externalId, string $body, array $meta = []): int
    {
        $threadId = self::findOrCreateThread($channel, $externalId, $meta);
        $messageId = self::addMessage($threadId, 'inbound', $body, $meta);
        Logger::log('inbox', $threadId, 'incoming_' . $channel, ['message_id' => $messageId]);
        return $messageId;
    }

    /**
     * ارسال پیام خروجی.
     */
    public static function sendOutbound(int $threadId, string $body, array $meta = []): int
    {
        $messageId = self::addMessage($threadId, 'outbound', $body, $meta);
        Logger::log('inbox', $threadId, 'outbound', ['message_id' => $messageId]);
        do_action('enterprise_event', 'inbox.outbound_sent', [
            'thread_id' => $threadId,
            'message_id' => $messageId,
            'body'      => $body,
        ]);
        return $messageId;
    }

    /**
     * گزارش صندوق ورودی.
     */
    public static function dashboardStats(int $userId = 0): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        if ($userId) {
            $where[] = 'assigned_to = %d';
            $params[] = $userId;
        }
        $sql = 'SELECT channel, COUNT(*) AS cnt, SUM(unread_count) AS unread
                FROM ' . Db::table(self::TABLE_THREAD) . "
                WHERE status != 'archived' AND " . implode(' AND ', $where) . '
                GROUP BY channel';
        $rows = $wpdb->get_results($params ? $wpdb->prepare($sql, $params) : $sql, ARRAY_A);
        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[$r['channel']] = [
                'name'   => self::CHANNELS[$r['channel']] ?? $r['channel'],
                'count'  => (int) $r['cnt'],
                'unread' => (int) ($r['unread'] ?? 0),
            ];
        }
        return $out;
    }

    // ----------------- Internal -----------------

    private static function decodeJson(?string $json): mixed
    {
        if (!$json) return null;
        return json_decode($json, true) ?: null;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
