<?php
/**
 * Lead Service — کامل با capture (webform/webhook/import)، scoring، distribution، routing
 *
 * @package Enterprise\Modules\Crm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Crm;

defined('ABSPATH') || exit;

use Enterprise\Relations;
use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class LeadService
{
    public const TABLE = 'leads';
    public const SOURCES = ['web_form', 'landing_page', 'referral', 'campaign', 'cold_call', 'event', 'social_media', 'api', 'manual', 'import', 'webhook'];
    public const STAGES  = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    /**
     * ایجاد سرنخ جدید با validation و scoring.
     */
    public static function create(array $data): int
    {
        $data = self::normalizeInput($data);
        if (empty($data['full_name']) && empty($data['email']) && empty($data['phone'])) {
            throw new \InvalidArgumentException('حداقل یکی از نام، ایمیل یا تلفن الزامی است.');
        }

        // dedup با lead قبلی
        $existing = self::findDuplicate($data);
        if ($existing) {
            self::update($existing['id'], $data);
            return (int) $existing['id'];
        }

        $data['uuid']         = self::uuid();
        $data['score']        = self::score($data);
        $data['stage']        = $data['stage'] ?? 'new';
        $data['owner_id']     = $data['owner_id'] ?? self::autoAssign($data);
        $data['created_at']   = current_time('mysql', true);
        $data['updated_at']   = current_time('mysql', true);

        $id = Db::insert(self::TABLE, $data);
        Logger::log('lead', $id, 'create', $data);
        do_action('enterprise_event', 'lead.created', ['lead_id' => $id, 'score' => $data['score'], 'source' => $data['source'] ?? 'manual']);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) return false;
        $data = self::normalizeInput($data);
        $data['updated_at'] = current_time('mysql', true);
        $merged = array_merge($existing, $data);
        $data['score'] = self::score($merged);
        Db::update(self::TABLE, $data, ['id' => $id]);

        // اگر به qualified تغییر کرد، رویداد fired شود
        if (($existing['stage'] ?? '') !== ($data['stage'] ?? '') && ($data['stage'] ?? '') === 'qualified') {
            do_action('enterprise_event', 'lead.qualified', ['lead_id' => $id]);
        }
        Logger::log('lead', $id, 'update', ['before' => $existing, 'after' => $data]);
        return true;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow(self::TABLE, ['id' => $id]);
        if ($row) {
            $row['tags']         = self::decodeJson($row['tags'] ?? null);
            $row['custom_fields'] = self::decodeJson($row['custom_fields'] ?? null);
        }
        return $row;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $row = Db::getRow(self::TABLE, ['uuid' => $uuid]);
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function search(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'score DESC, id DESC'): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like($filters['q']) . '%';
            $where[]  = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $params = array_merge($params, [$q, $q, $q]);
        }
        if (!empty($filters['stage'])) {
            $stages = (array) $filters['stage'];
            $placeholders = implode(',', array_fill(0, count($stages), '%s'));
            $where[]  = "stage IN ($placeholders)";
            $params = array_merge($params, $stages);
        }
        if (!empty($filters['source'])) {
            $where[]  = 'source = %s';
            $params[] = $filters['source'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]  = 'owner_id = %d';
            $params[] = (int) $filters['owner_id'];
        }
        if (!empty($filters['min_score'])) {
            $where[]  = 'score >= %d';
            $params[] = (int) $filters['min_score'];
        }
        if (isset($filters['is_hot']) && $filters['is_hot']) {
            $where[] = 'score >= 70';
        }
        if (empty($filters['include_deleted'])) {
            $where[] = 'deleted_at IS NULL';
        }

        $sql = 'SELECT * FROM ' . Db::table(self::TABLE)
             . ' WHERE ' . implode(' AND ', $where)
             . ' ORDER BY ' . sanitize_sql_orderby($order)
             . ' LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        return array_map(static function ($r) {
            $r['tags'] = self::decodeJson($r['tags'] ?? null);
            return $r;
        }, $rows);
    }

    public static function count(array $filters = []): int
    {
        return count(self::search($filters, 100000, 0));
    }

    /**
     * حذف نرم.
     */
    public static function softDelete(int $id): bool
    {
        Db::update(self::TABLE, ['deleted_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true)], ['id' => $id]);
        Logger::log('lead', $id, 'soft_delete', []);
        do_action('enterprise_event', 'lead.deleted', ['lead_id' => $id]);
        return true;
    }

    /**
     * تبدیل سرنخ به مخاطب (conversion).
     */
    public static function convertToContact(int $leadId): int
    {
        $lead = self::find($leadId);
        if (!$lead) {
            throw new \InvalidArgumentException('Lead not found');
        }
        $parts = preg_split('/\s+/u', trim((string) $lead['full_name']));
        $contactId = ContactService::create([
            'first_name'      => $parts[0] ?? '',
            'last_name'       => end($parts) ?: '',
            'full_name'       => $lead['full_name'],
            'primary_email'   => $lead['email'] ?: null,
            'primary_phone'   => $lead['phone'] ?: null,
            'national_id'     => $lead['national_id'] ?? null,
            'lifecycle_stage' => 'customer',
            'acquisition_source'   => $lead['source'] ?? null,
            'acquisition_campaign' => $lead['campaign'] ?? null,
            'owner_id'        => $lead['owner_id'],
        ]);
        // ارتباط سرنخ ↔ مخاطب
        Relations::link('lead', $leadId, 'contact', $contactId, 'contact_to_lead', ['from_lead' => true]);

        // بستن سرنخ
        self::update($leadId, ['stage' => 'won', 'converted_contact_id' => $contactId]);
        do_action('enterprise_event', 'lead.converted', ['lead_id' => $leadId, 'contact_id' => $contactId]);
        return $contactId;
    }

    /**
     * scoring پیشرفته (0-100) — وزنی.
     */
    public static function score(array $data): int
    {
        $score = 0;

        // ۱. اطلاعات دموگرافیک
        if (!empty($data['email']))    $score += 10;
        if (!empty($data['phone']))    $score += 10;
        if (!empty($data['full_name']))$score += 5;
        if (!empty($data['company']))  $score += 10;
        if (!empty($data['job_title']))$score += 5;

        // ۲. منبع
        $source = (string) ($data['source'] ?? '');
        $score += match ($source) {
            'referral'      => 30,
            'event'         => 25,
            'campaign'      => 20,
            'web_form'      => 20,
            'landing_page'  => 18,
            'social_media'  => 15,
            'cold_call'     => 8,
            'import'        => 10,
            'api', 'webhook'=> 12,
            'manual'        => 5,
            default         => 0,
        };

        // ۳. UTM tracking
        if (!empty($data['utm_source']))   $score += 3;
        if (!empty($data['utm_campaign'])) $score += 5;
        if (!empty($data['utm_medium']) && in_array($data['utm_medium'], ['cpc', 'ppc'], true)) $score += 5;

        // ۴. رفتار (اگر قبلاً تعامل داشته)
        if (!empty($data['has_opened_email']))  $score += 5;
        if (!empty($data['has_clicked_email'])) $score += 8;
        if (!empty($data['has_visited_pricing'])) $score += 15;
        if (!empty($data['page_views']) && $data['page_views'] > 5) $score += 10;

        // ۵. موقعیت جغرافیایی
        if (!empty($data['country']) && strtoupper($data['country']) === 'IR') $score += 5;

        return max(0, min(100, $score));
    }

    /**
     * توزیع خودکار سرنخ‌ها بین کارشناسان (round-robin با وزن).
     */
    public static function autoAssign(array $data): ?int
    {
        $strategy = get_option('parsyar_lead_distribution', 'round_robin');
        $salesReps = get_users(['role' => 'sales_rep', 'fields' => 'ID']);
        if (empty($salesReps)) {
            $salesReps = get_users(['role__in' => ['sales_rep', 'author', 'administrator'], 'fields' => 'ID']);
        }
        if (empty($salesReps)) {
            return null;
        }

        if ($strategy === 'load_balanced') {
            // کارشناس با کمترین تعداد سرنخ فعال
            global $wpdb;
            $counts = $wpdb->get_results(
                "SELECT owner_id, COUNT(*) AS cnt
                 FROM " . Db::table(self::TABLE) . "
                 WHERE deleted_at IS NULL AND stage NOT IN ('won','lost')
                 GROUP BY owner_id",
                OBJECT_K
            );
            $min = PHP_INT_MAX;
            $picked = null;
            foreach ($salesReps as $uid) {
                $cnt = (int) ($counts[$uid]->cnt ?? 0);
                if ($cnt < $min) {
                    $min = $cnt;
                    $picked = (int) $uid;
                }
            }
            return $picked;
        }

        if ($strategy === 'weighted') {
            // وزن بر اساس ظرفیت (user_meta: parsyar_lead_capacity)
            $weights = [];
            foreach ($salesReps as $uid) {
                $cap = (int) get_user_meta($uid, 'parsyar_lead_capacity', true) ?: 10;
                $weights[(int) $uid] = max(1, $cap);
            }
            $sum = array_sum($weights);
            $r = mt_rand(1, $sum);
            foreach ($weights as $uid => $w) {
                $r -= $w;
                if ($r <= 0) return (int) $uid;
            }
            return (int) $salesReps[0];
        }

        // round_robin (پیش‌فرض)
        $lastIdx = (int) get_option('parsyar_lead_rr_index', -1);
        $next = ($lastIdx + 1) % count($salesReps);
        update_option('parsyar_lead_rr_index', (string) $next);
        return (int) $salesReps[$next];
    }

    /**
     * routing بر اساس قوانین (مثلاً بر اساس استان، محصول، حجم).
     */
    public static function routeByRules(array $data): ?int
    {
        $rules = get_option('parsyar_lead_routing_rules', []);
        if (empty($rules)) {
            return self::autoAssign($data);
        }
        foreach ($rules as $rule) {
            if (!self::matchRule($rule, $data)) {
                continue;
            }
            if (!empty($rule['assign_to'])) {
                return (int) $rule['assign_to'];
            }
        }
        return self::autoAssign($data);
    }

    private static function matchRule(array $rule, array $data): bool
    {
        $conds = $rule['conditions'] ?? [];
        $op = strtoupper($rule['operator'] ?? 'AND');
        $results = [];
        foreach ($conds as $c) {
            $field = $c['field'] ?? '';
            $value = $c['value'] ?? '';
            $cmp   = strtoupper($c['compare'] ?? 'equals');
            $actual = $data[$field] ?? null;
            $r = match ($cmp) {
                'equals'    => (string) $actual === (string) $value,
                'not_equals'=> (string) $actual !== (string) $value,
                'contains'  => is_string($actual) && str_contains((string) $actual, (string) $value),
                'starts_with' => is_string($actual) && str_starts_with((string) $actual, (string) $value),
                'in'        => is_array($value) && in_array($actual, $value, true),
                'not_in'    => is_array($value) && !in_array($actual, $value, true),
                'gt'        => is_numeric($actual) && $actual > $value,
                'gte'       => is_numeric($actual) && $actual >= $value,
                'lt'        => is_numeric($actual) && $actual < $value,
                'lte'       => is_numeric($actual) && $actual <= $value,
                'is_empty'  => empty($actual),
                'is_not_empty' => !empty($actual),
                default     => false,
            };
            $results[] = $r;
        }
        if (empty($results)) return false;
        return $op === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    /**
     * dedup سرنخ‌ها با multi-layer.
     */
    public static function findDuplicate(array $data): ?array
    {
        if (!empty($data['email'])) {
            $row = Db::getRow(self::TABLE, ['email' => $data['email']]);
            if ($row) return $row;
        }
        if (!empty($data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', (string) $data['phone']);
            if (strlen($phone) >= 10) {
                $candidates = Db::getResults(self::TABLE, [], 'id DESC', 500, 0);
                foreach ($candidates as $c) {
                    $cPhone = preg_replace('/[^0-9]/', '', (string) $c['phone']);
                    if (substr($cPhone, -10) === substr($phone, -10)) {
                        return $c;
                    }
                }
            }
        }
        return null;
    }

    /**
     * capture از web form (معمولاً از فرم تماس یا landing page).
     */
    public static function captureFromWebForm(array $formData): int
    {
        $data = [
            'full_name'  => trim(($formData['first_name'] ?? '') . ' ' . ($formData['last_name'] ?? '')),
            'email'      => $formData['email'] ?? '',
            'phone'      => $formData['phone'] ?? '',
            'company'    => $formData['company'] ?? '',
            'job_title'  => $formData['job_title'] ?? '',
            'message'    => $formData['message'] ?? '',
            'source'     => 'web_form',
            'campaign'   => $formData['utm_campaign'] ?? '',
            'utm_source' => $formData['utm_source'] ?? '',
            'utm_medium' => $formData['utm_medium'] ?? '',
            'ip_address' => self::clientIp(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'page_url'   => esc_url_raw((string) ($formData['page_url'] ?? '')),
        ];
        $leadId = self::create($data);
        do_action('parsyar_lead_captured', $leadId, $data);
        return $leadId;
    }

    /**
     * capture از webhook (مثلاً فرم‌های خارجی).
     */
    public static function captureFromWebhook(array $payload, string $source = 'webhook'): int
    {
        $data = [
            'full_name' => $payload['name'] ?? $payload['full_name'] ?? '',
            'email'     => $payload['email'] ?? '',
            'phone'     => $payload['phone'] ?? $payload['mobile'] ?? '',
            'company'   => $payload['company'] ?? '',
            'message'   => $payload['message'] ?? $payload['note'] ?? '',
            'source'    => $source,
            'ip_address'=> self::clientIp(),
        ];
        return self::create($data);
    }

    /**
     * آمار funnel.
     */
    public static function funnelStats(string $from = '-30 days', string $to = 'now'): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(*) AS cnt
             FROM " . Db::table(self::TABLE) . "
             WHERE created_at BETWEEN %s AND %s
             GROUP BY stage",
            [gmdate('Y-m-d H:i:s', strtotime($from)), gmdate('Y-m-d H:i:s', strtotime($to))]
        ), ARRAY_A);
        $out = array_fill_keys(self::STAGES, 0);
        foreach ($rows ?: [] as $r) {
            $out[$r['stage']] = (int) $r['cnt'];
        }
        return $out;
    }

    /**
     * لیست سرنخ‌های داغ (hot leads).
     */
    public static function hotLeads(int $limit = 20): array
    {
        return self::search(['is_hot' => true], $limit, 0, 'score DESC, updated_at DESC');
    }

    // ----------------- Internal -----------------

    private static function normalizeInput(array $data): array
    {
        $out = [];
        $map = [
            'full_name'  => 'sanitize_text_field',
            'email'      => 'sanitize_email',
            'phone'      => 'sanitize_text_field',
            'national_id'=> 'sanitize_text_field',
            'company'    => 'sanitize_text_field',
            'job_title'  => 'sanitize_text_field',
            'source'     => 'sanitize_text_field',
            'campaign'   => 'sanitize_text_field',
            'stage'      => 'sanitize_text_field',
            'message'    => 'sanitize_textarea_field',
            'page_url'   => 'esc_url_raw',
            'utm_source' => 'sanitize_text_field',
            'utm_medium' => 'sanitize_text_field',
            'utm_campaign' => 'sanitize_text_field',
        ];
        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $out[$k] = wp_json_encode($v);
            } elseif (isset($map[$k]) && is_string($v)) {
                $out[$k] = call_user_func($map[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }
        if (!empty($out['email'])) {
            $out['email'] = strtolower((string) $out['email']);
        }
        return $out;
    }

    private static function decodeJson(?string $json): mixed
    {
        if (!$json) return null;
        return json_decode($json, true) ?: null;
    }

    private static function clientIp(): ?string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', (string) $_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return null;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
