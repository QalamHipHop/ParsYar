<?php
declare(strict_types=1);

namespace Enterprise\Modules\Crm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

/**
 * سرویس Lead — با قابلیت Lead Scoring خودکار.
 */
final class LeadService
{
    public static function create(array $data): int
    {
        $fullName = trim(sanitize_text_field((string) ($data['full_name'] ?? '')));
        if ($fullName === '') {
            throw new \InvalidArgumentException('full_name required');
        }
        $score = self::score($data);
        $id = Db::insert('leads', [
            'full_name' => $fullName,
            'email'     => sanitize_email((string) ($data['email'] ?? '')) ?: null,
            'phone'     => sanitize_text_field((string) ($data['phone'] ?? '')) ?: null,
            'source'    => sanitize_text_field((string) ($data['source'] ?? 'manual')),
            'score'     => $score,
            'stage'     => 'new',
            'owner_id'  => get_current_user_id() ?: null,
        ]);
        \Enterprise\Modules\Audit\Logger::log('lead', $id, 'create', $data);
        do_action('enterprise_event', 'lead.created', ['lead_id' => $id, 'score' => $score]);
        return $id;
    }

    public static function all(): array
    {
        return Db::getResults('leads', [], 'score DESC, id DESC', 200, 0);
    }

    /**
     * الگوریتم ساده امتیازدهی سرنخ — قابل گسترش.
     */
    private static function score(array $data): int
    {
        $score = 0;
        if (!empty($data['email'])) {
            $score += 20;
        }
        if (!empty($data['phone'])) {
            $score += 20;
        }
        $source = (string) ($data['source'] ?? '');
        $score += match ($source) {
            'referral'   => 30,
            'web_form'   => 20,
            'campaign'   => 10,
            'cold_call'  => 5,
            default      => 0,
        };
        return min(100, $score);
    }
}
