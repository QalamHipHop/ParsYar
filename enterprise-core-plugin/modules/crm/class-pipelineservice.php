<?php
/**
 * Pipeline Service — خط فروش (Pipeline + Stages + Deals)
 *
 * @package Enterprise\Modules\Crm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Crm;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class PipelineService
{
    public const TABLE_PIPELINE  = 'pipelines';
    public const TABLE_STAGE     = 'pipeline_stages';
    public const TABLE_DEAL      = 'deals';
    public const DEFAULT_STAGES  = ['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    // -------- Pipeline CRUD --------

    public static function createPipeline(array $data): int
    {
        $data['uuid']       = self::uuid();
        $data['created_at'] = current_time('mysql', true);
        $data['is_active']  = $data['is_active'] ?? 1;
        $id = Db::insert(self::TABLE_PIPELINE, $data);
        Logger::log('pipeline', $id, 'create', $data);
        return $id;
    }

    public static function updatePipeline(int $id, array $data): bool
    {
        $r = Db::update(self::TABLE_PIPELINE, $data, ['id' => $id]);
        Logger::log('pipeline', $id, 'update', $data);
        return $r >= 0;
    }

    public static function deletePipeline(int $id): bool
    {
        Db::delete(self::TABLE_STAGE, ['pipeline_id' => $id]);
        Db::delete(self::TABLE_DEAL, ['pipeline_id' => $id]);
        $r = Db::delete(self::TABLE_PIPELINE, ['id' => $id]);
        return $r > 0;
    }

    public static function findPipeline(int $id): ?array
    {
        return Db::getRow(self::TABLE_PIPELINE, ['id' => $id]);
    }

    public static function listPipelines(bool $activeOnly = false): array
    {
        $where = $activeOnly ? ['is_active' => 1] : [];
        return Db::getResults(self::TABLE_PIPELINE, $where, 'sort_order ASC, id ASC', 100, 0);
    }

    // -------- Stage CRUD --------

    public static function createStage(array $data): int
    {
        $data['created_at'] = current_time('mysql', true);
        $id = Db::insert(self::TABLE_STAGE, $data);
        Logger::log('pipeline_stage', $id, 'create', $data);
        return $id;
    }

    public static function updateStage(int $id, array $data): bool
    {
        $r = Db::update(self::TABLE_STAGE, $data, ['id' => $id]);
        Logger::log('pipeline_stage', $id, 'update', $data);
        return $r >= 0;
    }

    public static function deleteStage(int $id): bool
    {
        return Db::delete(self::TABLE_STAGE, ['id' => $id]) > 0;
    }

    public static function listStages(int $pipelineId): array
    {
        return Db::getResults(self::TABLE_STAGE, ['pipeline_id' => $pipelineId], 'sort_order ASC, id ASC', 50, 0);
    }

    public static function findStage(int $id): ?array
    {
        return Db::getRow(self::TABLE_STAGE, ['id' => $id]);
    }

    // -------- Deal CRUD --------

    public static function createDeal(array $data): int
    {
        $data['uuid']         = self::uuid();
        $data['status']       = $data['status'] ?? 'open';
        $data['amount']       = (float) ($data['amount'] ?? 0);
        $data['probability']  = (int) ($data['probability'] ?? 10);
        $data['weighted_amount'] = $data['amount'] * $data['probability'] / 100;
        $data['currency']     = $data['currency'] ?? 'IRT';
        $data['expected_close_date'] = $data['expected_close_date'] ?? null;
        $data['owner_id']     = $data['owner_id'] ?? get_current_user_id() ?: null;
        $data['created_at']   = current_time('mysql', true);
        $data['updated_at']   = current_time('mysql', true);
        $data['stage_entered_at'] = current_time('mysql', true);

        $id = Db::insert(self::TABLE_DEAL, $data);
        Logger::log('deal', $id, 'create', $data);
        do_action('enterprise_event', 'deal.created', ['deal_id' => $id, 'data' => $data]);
        return $id;
    }

    public static function updateDeal(int $id, array $data): bool
    {
        $existing = self::findDeal($id);
        if (!$existing) return false;

        // اگر stage تغییر کرده، زمان ورود به stage جدید ثبت شود
        if (isset($data['stage_id']) && (int) $data['stage_id'] !== (int) $existing['stage_id']) {
            $data['stage_entered_at'] = current_time('mysql', true);
            $data['previous_stage_id'] = $existing['stage_id'];
            do_action('enterprise_event', 'deal.stage_changed', [
                'deal_id'   => $id,
                'from_stage'=> $existing['stage_id'],
                'to_stage'  => $data['stage_id'],
            ]);
        }
        if (isset($data['amount']) || isset($data['probability'])) {
            $amount = (float) ($data['amount'] ?? $existing['amount']);
            $prob   = (int) ($data['probability'] ?? $existing['probability']);
            $data['weighted_amount'] = $amount * $prob / 100;
        }
        $data['updated_at'] = current_time('mysql', true);
        Db::update(self::TABLE_DEAL, $data, ['id' => $id]);
        Logger::log('deal', $id, 'update', ['before' => $existing, 'after' => $data]);
        return true;
    }

    public static function findDeal(int $id): ?array
    {
        $row = Db::getRow(self::TABLE_DEAL, ['id' => $id]);
        if ($row) {
            $row['tags'] = self::decodeJson($row['tags'] ?? null);
            $row['custom_fields'] = self::decodeJson($row['custom_fields'] ?? null);
        }
        return $row;
    }

    public static function findDealByUuid(string $uuid): ?array
    {
        $row = Db::getRow(self::TABLE_DEAL, ['uuid' => $uuid]);
        return $row ? self::findDeal((int) $row['id']) : null;
    }

    public static function searchDeals(array $filters = [], int $limit = 50, int $offset = 0, string $order = 'amount DESC, id DESC'): array
    {
        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $wpdb->esc_like($filters['q']) . '%';
            $where[]  = '(title LIKE %s OR description LIKE %s)';
            $params = array_merge($params, [$q, $q]);
        }
        if (!empty($filters['pipeline_id'])) {
            $where[]  = 'pipeline_id = %d';
            $params[] = (int) $filters['pipeline_id'];
        }
        if (!empty($filters['stage_id'])) {
            $where[]  = 'stage_id = %d';
            $params[] = (int) $filters['stage_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['owner_id'])) {
            $where[]  = 'owner_id = %d';
            $params[] = (int) $filters['owner_id'];
        }
        if (!empty($filters['organization_id'])) {
            $where[]  = 'organization_id = %d';
            $params[] = (int) $filters['organization_id'];
        }
        if (!empty($filters['contact_id'])) {
            $where[]  = 'contact_id = %d';
            $params[] = (int) $filters['contact_id'];
        }
        if (!empty($filters['min_amount'])) {
            $where[]  = 'amount >= %f';
            $params[] = (float) $filters['min_amount'];
        }
        if (isset($filters['is_rotting']) && $filters['is_rotting']) {
            $where[] = 'stage_entered_at < DATE_SUB(NOW(), INTERVAL 14 DAY)';
            $where[] = "status = 'open'";
        }
        if (empty($filters['include_deleted'])) {
            $where[] = 'deleted_at IS NULL';
        }

        $sql = 'SELECT * FROM ' . Db::table(self::TABLE_DEAL)
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

    public static function countDeals(array $filters = []): int
    {
        return count(self::searchDeals($filters, 100000, 0));
    }

    public static function softDeleteDeal(int $id): bool
    {
        Db::update(self::TABLE_DEAL, ['deleted_at' => current_time('mysql', true)], ['id' => $id]);
        Logger::log('deal', $id, 'soft_delete', []);
        return true;
    }

    /**
     * انتقال Deal به Stage دیگر.
     */
    public static function moveDealToStage(int $dealId, int $stageId): bool
    {
        $stage = self::findStage($stageId);
        if (!$stage) {
            throw new \InvalidArgumentException('Stage not found');
        }
        $deal = self::findDeal($dealId);
        if (!$deal) {
            throw new \InvalidArgumentException('Deal not found');
        }
        $data = [
            'stage_id'        => $stageId,
            'probability'     => (int) ($stage['probability'] ?? $deal['probability']),
            'status'          => in_array($stage['code'] ?? '', ['won', 'lost'], true) ? ($stage['code']) : 'open',
        ];
        if (in_array($stage['code'] ?? '', ['won', 'lost'], true)) {
            $data['closed_at'] = current_time('mysql', true);
            if ($stage['code'] === 'won') {
                do_action('enterprise_event', 'deal.won', ['deal_id' => $dealId]);
            } else {
                do_action('enterprise_event', 'deal.lost', ['deal_id' => $dealId]);
            }
        }
        return self::updateDeal($dealId, $data);
    }

    /**
     * تشخیص معاملات راکد (rotting) — بیش از N روز در یک stage مانده.
     */
    public static function findRotting(int $daysIdle = 14, int $limit = 100): array
    {
        return self::searchDeals([
            'is_rotting'      => true,
            'status'          => 'open',
        ], $limit, 0, 'stage_entered_at ASC');
    }

    /**
     * forecasting: جمع مبلغ وزنی معاملات باز بر اساس stage.
     */
    public static function forecast(int $pipelineId, ?string $currency = null): array
    {
        $rows = self::searchDeals([
            'pipeline_id' => $pipelineId,
            'status'      => 'open',
        ], 10000, 0);

        $byStage = [];
        $totalWeighted = 0;
        $totalAmount = 0;
        foreach ($rows as $d) {
            $sid = (int) $d['stage_id'];
            if (!isset($byStage[$sid])) {
                $byStage[$sid] = [
                    'count'          => 0,
                    'total_amount'   => 0,
                    'weighted_amount'=> 0,
                ];
            }
            $byStage[$sid]['count']++;
            $byStage[$sid]['total_amount']    += (float) $d['amount'];
            $byStage[$sid]['weighted_amount'] += (float) $d['weighted_amount'];
            $totalWeighted += (float) $d['weighted_amount'];
            $totalAmount   += (float) $d['amount'];
        }

        $stages = self::listStages($pipelineId);
        $stagesMap = [];
        foreach ($stages as $s) {
            $stagesMap[(int) $s['id']] = $s;
        }
        $result = [];
        foreach ($byStage as $sid => $stats) {
            $result[] = [
                'stage_id'        => $sid,
                'stage_name'      => $stagesMap[$sid]['name'] ?? '',
                'stage_code'      => $stagesMap[$sid]['code'] ?? '',
                'count'           => $stats['count'],
                'total_amount'    => $stats['total_amount'],
                'weighted_amount' => $stats['weighted_amount'],
            ];
        }
        return [
            'pipeline_id'      => $pipelineId,
            'currency'         => $currency,
            'total_deals'      => count($rows),
            'total_amount'     => $totalAmount,
            'weighted_forecast'=> $totalWeighted,
            'by_stage'         => $result,
        ];
    }

    /**
     * ساخت Pipeline پیش‌فرض فارسی.
     */
    public static function createDefaultPipeline(int $ownerId = 0): int
    {
        $pipelineId = self::createPipeline([
            'name'        => 'خط فروش اصلی',
            'description' => 'Pipeline پیش‌فرض ParsYar',
            'sort_order'  => 1,
            'is_default'  => 1,
            'is_active'   => 1,
            'owner_id'    => $ownerId,
        ]);

        $stages = [
            ['name' => 'سرنخ',          'code' => 'lead',        'probability' => 10,  'color' => '#8A8A8A', 'sort_order' => 1],
            ['name' => 'واجد شرایط',    'code' => 'qualified',   'probability' => 25,  'color' => '#4A4A4A', 'sort_order' => 2],
            ['name' => 'پیشنهاد',       'code' => 'proposal',    'probability' => 50,  'color' => '#2A2A2A', 'sort_order' => 3],
            ['name' => 'مذاکره',        'code' => 'negotiation', 'probability' => 75,  'color' => '#1F1F1F', 'sort_order' => 4],
            ['name' => 'برنده',         'code' => 'won',         'probability' => 100, 'color' => '#0A0A0A', 'sort_order' => 5, 'is_won' => 1],
            ['name' => 'باخته',         'code' => 'lost',        'probability' => 0,   'color' => '#5A5A5A', 'sort_order' => 6, 'is_lost' => 1],
        ];
        foreach ($stages as $s) {
            $s['pipeline_id'] = $pipelineId;
            self::createStage($s);
        }
        return $pipelineId;
    }

    /**
     * آمار کلی pipeline برای dashboard.
     */
    public static function summary(int $pipelineId): array
    {
        $deals = self::searchDeals(['pipeline_id' => $pipelineId], 10000, 0);
        $open = array_filter($deals, static fn ($d) => $d['status'] === 'open');
        $won  = array_filter($deals, static fn ($d) => $d['status'] === 'won');
        $lost = array_filter($deals, static fn ($d) => $d['status'] === 'lost');

        $sumAmount = static function (array $arr): float {
            return array_sum(array_column($arr, 'amount'));
        };
        $sumWeighted = static function (array $arr): float {
            return array_sum(array_column($arr, 'weighted_amount'));
        };

        $winRate = count($deals) > 0 ? (count($won) / count($deals)) * 100 : 0;
        return [
            'pipeline_id'    => $pipelineId,
            'total_deals'    => count($deals),
            'open_deals'     => count($open),
            'won_deals'      => count($won),
            'lost_deals'     => count($lost),
            'win_rate'       => round($winRate, 2),
            'open_amount'    => $sumAmount($open),
            'won_amount'     => $sumAmount($won),
            'weighted_forecast' => $sumWeighted($open),
            'avg_deal_size'  => count($deals) > 0 ? $sumAmount($deals) / count($deals) : 0,
        ];
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
