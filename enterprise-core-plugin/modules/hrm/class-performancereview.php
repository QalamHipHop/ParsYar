<?php
/**
 * Performance Review Service — ارزیابی دوره‌ای عملکرد.
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Str;

final class PerformanceReview
{
    public const STATUS_DRAFT        = 'draft';
    public const STATUS_SUBMITTED    = 'submitted';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_FINALIZED    = 'finalized';

    /** @var string[] */
    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SUBMITTED,
        self::STATUS_ACKNOWLEDGED, self::STATUS_FINALIZED,
    ];

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        if ($employeeId === 0) {
            throw new \InvalidArgumentException('employee_id required');
        }
        $start = self::normalizeDate($data['period_start'] ?? null);
        $end   = self::normalizeDate($data['period_end']   ?? null);
        if ($start === null || $end === null) {
            throw new \InvalidArgumentException('period_start and period_end are required');
        }
        $payload = [
            'uuid'             => Str::uuid(),
            'employee_id'      => $employeeId,
            'reviewer_id'      => isset($data['reviewer_id']) ? (int) $data['reviewer_id'] : null,
            'period_start'     => $start,
            'period_end'       => $end,
            'productivity_score' => self::boundScore($data['productivity_score'] ?? 0),
            'quality_score'      => self::boundScore($data['quality_score']      ?? 0),
            'teamwork_score'     => self::boundScore($data['teamwork_score']     ?? 0),
            'punctuality_score'  => self::boundScore($data['punctuality_score']  ?? 0),
            'overall_score'      => self::computeOverall($data),
            'strengths'        => sanitize_textarea_field((string) ($data['strengths'] ?? '')),
            'weaknesses'       => sanitize_textarea_field((string) ($data['weaknesses'] ?? '')),
            'goals'            => sanitize_textarea_field((string) ($data['goals']     ?? '')),
            'feedback'         => sanitize_textarea_field((string) ($data['feedback']  ?? '')),
            'status'           => self::STATUS_DRAFT,
            'company_id'       => (int) ($data['company_id'] ?? 1),
        ];
        return (int) Db::insert('performance_reviews', $payload);
    }

    public static function submit(int $id): bool
    {
        return Db::update('performance_reviews', [
            'status'     => self::STATUS_SUBMITTED,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]) !== false;
    }

    public static function acknowledge(int $id): bool
    {
        return Db::update('performance_reviews', [
            'status'     => self::STATUS_ACKNOWLEDGED,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]) !== false;
    }

    public static function finalize(int $id): bool
    {
        return Db::update('performance_reviews', [
            'status'     => self::STATUS_FINALIZED,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]) !== false;
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow('performance_reviews', ['id' => $id]);
        return $row ?: null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function forEmployee(int $employeeId, int $limit = 20): array
    {
        return Db::getResults('performance_reviews', ['employee_id' => $employeeId], 'period_end DESC', $limit, 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function pendingForReviewer(int $reviewerId, int $limit = 50): array
    {
        return Db::getResults('performance_reviews', [
            'reviewer_id' => $reviewerId,
            'status'      => self::STATUS_DRAFT,
        ], 'period_end ASC', $limit, 0);
    }

    private static function boundScore($v): float
    {
        $v = (float) $v;
        return max(0.0, min(5.0, $v));
    }

    private static function computeOverall(array $data): float
    {
        $p = self::boundScore($data['productivity_score'] ?? 0);
        $q = self::boundScore($data['quality_score']      ?? 0);
        $t = self::boundScore($data['teamwork_score']     ?? 0);
        $u = self::boundScore($data['punctuality_score']  ?? 0);
        return round(($p * 0.4) + ($q * 0.3) + ($t * 0.15) + ($u * 0.15), 2);
    }

    private static function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        return $ts === false ? null : gmdate('Y-m-d', $ts);
    }
}
