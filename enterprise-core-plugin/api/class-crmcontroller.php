<?php
/**
 * CRM REST Controller — کنترلر REST ماژول CRM
 *
 * نقاط پایانی:
 *   - /crm/contacts (index, show, store, update, destroy)
 *   - /crm/contacts/{id}/timeline
 *   - /crm/contacts/{id}/merge
 *   - /crm/contacts/{id}/lifecycle
 *   - /crm/contacts/lifecycle-breakdown
 *   - /crm/contacts/quick-search
 *   - /crm/organizations (index, show, store, update, destroy)
 *   - /crm/organizations/industry-breakdown
 *   - /crm/leads (index, show, store, update, destroy)
 *   - /crm/leads/{id}/convert
 *   - /crm/leads/{id}/assign
 *   - /crm/leads/funnel
 *   - /crm/leads/hot
 *   - /crm/deals (index, show, store, update, destroy)
 *   - /crm/deals/{id}/move
 *   - /crm/deals/{id}/assign
 *   - /crm/deals/rotting
 *   - /crm/deals/forecast
 *   - /crm/pipelines (index, show, store, update, destroy)
 *   - /crm/pipelines/{id}/stages (index, store, update, destroy)
 *   - /crm/pipelines/{id}/summary
 *   - /crm/activities (index, show, store, update, destroy)
 *   - /crm/activities/{id}/complete
 *   - /crm/activities/today
 *
 * @package Enterprise\Api
 */

declare(strict_types=1);

namespace Enterprise\Api;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use Enterprise\Modules\Crm\ContactService;
use Enterprise\Modules\Crm\OrganizationService;
use Enterprise\Modules\Crm\LeadService;
use Enterprise\Modules\Crm\PipelineService;
use Enterprise\Modules\Crm\ActivityService;

final class CrmController
{
    /* ------------------------------------------------------------------ *
     *  CONTACTS
     * ------------------------------------------------------------------ */

    public static function contactsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'lifecycle_stage', 'owner_id', 'organization_id', 'min_score']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $order   = (string) ($req->get_param('order') ?? 'score DESC, id DESC');

        $rows  = ContactService::search($filters, max(1, min(200, $limit)), max(0, $offset), $order);
        $total = ContactService::count($filters);
        return rest_ensure_response([
            'data'  => $rows,
            'meta'  => [
                'total'       => $total,
                'per_page'    => max(1, min(200, $limit)),
                'page'        => (int) ($req->get_param('page') ?? 0),
                'lifecycle'   => ContactService::LIFECYCLE,
            ],
        ]);
    }

    public static function contactsShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = $id > 0 ? ContactService::find($id) : ContactService::findByUuid((string) $req->get_param('id'));
        if (!$row) {
            return new WP_Error('not_found', 'Contact not found', ['status' => 404]);
        }
        $row['timeline']       = ContactService::timeline($id, 50);
        $row['completion_pct'] = ContactService::profileCompletion($row);
        return rest_ensure_response($row);
    }

    public static function contactsStore(WP_REST_Request $req)
    {
        try {
            $id = ContactService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('contact.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'contact' => ContactService::find($id)], 201);
    }

    public static function contactsUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!ContactService::find($id)) {
            return new WP_Error('not_found', 'Contact not found', ['status' => 404]);
        }
        try {
            $ok = ContactService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('contact.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'contact' => ContactService::find($id)]);
    }

    public static function contactsDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $force = (bool) $req->get_param('force');
        $ok = $force ? ContactService::hardDelete($id) : ContactService::softDelete($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function contactsTimeline(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $limit = (int) ($req->get_param('per_page') ?? 100);
        return rest_ensure_response(ContactService::timeline($id, max(1, min(500, $limit))));
    }

    public static function contactsMerge(WP_REST_Request $req)
    {
        $primary = (int) $req->get_param('id');
        $payload = (array) $req->get_json_params();
        $ok = ContactService::mergeInto($primary, $payload);
        return rest_ensure_response(['ok' => $ok, 'contact' => ContactService::find($primary)]);
    }

    public static function contactsLifecycle(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $stage = (string) ($req->get_json_params()['lifecycle_stage'] ?? '');
        if (!in_array($stage, ContactService::LIFECYCLE, true)) {
            return new WP_Error('invalid_stage', 'Invalid lifecycle stage', ['status' => 400]);
        }
        $ok = ContactService::update($id, ['lifecycle_stage' => $stage]);
        return rest_ensure_response(['ok' => $ok, 'lifecycle_stage' => $stage]);
    }

    public static function contactsLifecycleBreakdown()
    {
        return rest_ensure_response(ContactService::lifecycleBreakdown());
    }

    public static function contactsQuickSearch(WP_REST_Request $req)
    {
        $q = (string) ($req->get_param('q') ?? '');
        return rest_ensure_response(ContactService::quickSearch($q, (int) ($req->get_param('limit') ?? 10)));
    }

    /* ------------------------------------------------------------------ *
     *  ORGANIZATIONS
     * ------------------------------------------------------------------ */

    public static function organizationsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'industry', 'size', 'lifecycle_stage', 'owner_id', 'country', 'city', 'min_score']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = OrganizationService::search($filters, max(1, min(200, $limit)), max(0, $offset));
        $total   = OrganizationService::count($filters);
        return rest_ensure_response([
            'data' => $rows,
            'meta' => ['total' => $total, 'per_page' => max(1, min(200, $limit))],
        ]);
    }

    public static function organizationsShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = $id > 0 ? OrganizationService::find($id) : OrganizationService::findByUuid((string) $req->get_param('id'));
        if (!$row) {
            return new WP_Error('not_found', 'Organization not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function organizationsStore(WP_REST_Request $req)
    {
        try {
            $id = OrganizationService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('org.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'organization' => OrganizationService::find($id)], 201);
    }

    public static function organizationsUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!OrganizationService::find($id)) {
            return new WP_Error('not_found', 'Organization not found', ['status' => 404]);
        }
        try {
            $ok = OrganizationService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('org.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'organization' => OrganizationService::find($id)]);
    }

    public static function organizationsDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $force = (bool) $req->get_param('force');
        $ok = $force ? OrganizationService::hardDelete($id) : OrganizationService::softDelete($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function organizationsIndustryBreakdown()
    {
        return rest_ensure_response(OrganizationService::industryBreakdown());
    }

    public static function organizationsGeoBreakdown()
    {
        return rest_ensure_response(OrganizationService::geoBreakdown());
    }

    /* ------------------------------------------------------------------ *
     *  LEADS
     * ------------------------------------------------------------------ */

    public static function leadsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'source', 'stage', 'owner_id', 'min_score']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = LeadService::search($filters, max(1, min(200, $limit)), max(0, $offset));
        $total   = LeadService::count($filters);
        return rest_ensure_response([
            'data' => $rows,
            'meta' => ['total' => $total, 'per_page' => max(1, min(200, $limit))],
        ]);
    }

    public static function leadsShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = $id > 0 ? LeadService::find($id) : LeadService::findByUuid((string) $req->get_param('id'));
        if (!$row) {
            return new WP_Error('not_found', 'Lead not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function leadsStore(WP_REST_Request $req)
    {
        try {
            $id = LeadService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('lead.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'lead' => LeadService::find($id)], 201);
    }

    public static function leadsUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!LeadService::find($id)) {
            return new WP_Error('not_found', 'Lead not found', ['status' => 404]);
        }
        try {
            $ok = LeadService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('lead.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'lead' => LeadService::find($id)]);
    }

    public static function leadsDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = LeadService::softDelete($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function leadsConvert(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        try {
            $contactId = LeadService::convertToContact($id);
        } catch (\Throwable $e) {
            return new WP_Error('lead.convert_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['contact_id' => $contactId, 'contact' => ContactService::find($contactId)]);
    }

    public static function leadsAssign(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $userId = (int) ($req->get_json_params()['owner_id'] ?? 0);
        if ($userId <= 0) {
            return new WP_Error('invalid_user', 'owner_id required', ['status' => 400]);
        }
        $ok = LeadService::update($id, ['owner_id' => $userId]);
        return rest_ensure_response(['ok' => $ok, 'owner_id' => $userId]);
    }

    public static function leadsFunnel(WP_REST_Request $req)
    {
        $from = (string) ($req->get_param('from') ?? '-30 days');
        $to   = (string) ($req->get_param('to')   ?? 'now');
        return rest_ensure_response(LeadService::funnelStats($from, $to));
    }

    public static function leadsHot(WP_REST_Request $req)
    {
        return rest_ensure_response(LeadService::hotLeads((int) ($req->get_param('limit') ?? 20)));
    }

    /* ------------------------------------------------------------------ *
     *  DEALS / PIPELINES
     * ------------------------------------------------------------------ */

    public static function dealsIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['q', 'pipeline_id', 'stage_id', 'owner_id', 'currency', 'status']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = PipelineService::searchDeals($filters, max(1, min(200, $limit)), max(0, $offset));
        $total   = PipelineService::countDeals($filters);
        return rest_ensure_response([
            'data' => $rows,
            'meta' => ['total' => $total, 'per_page' => max(1, min(200, $limit))],
        ]);
    }

    public static function dealsShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = $id > 0 ? PipelineService::findDeal($id) : PipelineService::findDealByUuid((string) $req->get_param('id'));
        if (!$row) {
            return new WP_Error('not_found', 'Deal not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function dealsStore(WP_REST_Request $req)
    {
        try {
            $id = PipelineService::createDeal((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('deal.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'deal' => PipelineService::findDeal($id)], 201);
    }

    public static function dealsUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!PipelineService::findDeal($id)) {
            return new WP_Error('not_found', 'Deal not found', ['status' => 404]);
        }
        try {
            $ok = PipelineService::updateDeal($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('deal.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'deal' => PipelineService::findDeal($id)]);
    }

    public static function dealsDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = PipelineService::softDeleteDeal($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function dealsMove(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $stageId = (int) ($req->get_json_params()['stage_id'] ?? 0);
        if ($stageId <= 0) {
            return new WP_Error('invalid_stage', 'stage_id required', ['status' => 400]);
        }
        $ok = PipelineService::moveDealToStage($id, $stageId);
        return rest_ensure_response(['ok' => $ok, 'deal' => PipelineService::findDeal($id)]);
    }

    public static function dealsAssign(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $userId = (int) ($req->get_json_params()['owner_id'] ?? 0);
        if ($userId <= 0) {
            return new WP_Error('invalid_user', 'owner_id required', ['status' => 400]);
        }
        $ok = PipelineService::updateDeal($id, ['owner_id' => $userId]);
        return rest_ensure_response(['ok' => $ok, 'owner_id' => $userId]);
    }

    public static function dealsRotting(WP_REST_Request $req)
    {
        $days = (int) ($req->get_param('days') ?? 14);
        return rest_ensure_response(PipelineService::findRotting(max(1, min(180, $days))));
    }

    public static function dealsForecast(WP_REST_Request $req)
    {
        $pipelineId = (int) ($req->get_param('pipeline_id') ?? 0);
        $currency   = $req->get_param('currency') ?: null;
        if ($pipelineId <= 0) {
            return new WP_Error('invalid_pipeline', 'pipeline_id required', ['status' => 400]);
        }
        return rest_ensure_response(PipelineService::forecast($pipelineId, $currency ? (string) $currency : null));
    }

    /* ------------------------------------------------------------------ *
     *  PIPELINES
     * ------------------------------------------------------------------ */

    public static function pipelinesIndex(WP_REST_Request $req)
    {
        $active = (bool) $req->get_param('active_only');
        return rest_ensure_response(PipelineService::listPipelines($active));
    }

    public static function pipelinesShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = PipelineService::findPipeline($id);
        if (!$row) {
            return new WP_Error('not_found', 'Pipeline not found', ['status' => 404]);
        }
        $row['stages'] = PipelineService::listStages($id);
        $row['summary'] = PipelineService::summary($id);
        return rest_ensure_response($row);
    }

    public static function pipelinesStore(WP_REST_Request $req)
    {
        try {
            $id = PipelineService::createPipeline((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('pipeline.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'pipeline' => PipelineService::findPipeline($id)], 201);
    }

    public static function pipelinesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!PipelineService::findPipeline($id)) {
            return new WP_Error('not_found', 'Pipeline not found', ['status' => 404]);
        }
        try {
            $ok = PipelineService::updatePipeline($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('pipeline.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'pipeline' => PipelineService::findPipeline($id)]);
    }

    public static function pipelinesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = PipelineService::deletePipeline($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function pipelineStagesIndex(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        return rest_ensure_response(PipelineService::listStages($id));
    }

    public static function pipelineStagesStore(WP_REST_Request $req)
    {
        $data = (array) $req->get_json_params();
        $data['pipeline_id'] = (int) $req->get_param('id');
        try {
            $id = PipelineService::createStage($data);
        } catch (\Throwable $e) {
            return new WP_Error('stage.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'stage' => PipelineService::findStage($id)], 201);
    }

    public static function pipelineStagesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('stage_id');
        if (!PipelineService::findStage($id)) {
            return new WP_Error('not_found', 'Stage not found', ['status' => 404]);
        }
        try {
            $ok = PipelineService::updateStage($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('stage.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'stage' => PipelineService::findStage($id)]);
    }

    public static function pipelineStagesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('stage_id');
        $ok = PipelineService::deleteStage($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function pipelineSummary(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        return rest_ensure_response(PipelineService::summary($id));
    }

    /* ------------------------------------------------------------------ *
     *  ACTIVITIES
     * ------------------------------------------------------------------ */

    public static function activitiesIndex(WP_REST_Request $req)
    {
        $filters = self::pickFilters($req, ['type', 'status', 'owner_id', 'contact_id', 'organization_id', 'deal_id', 'due_from', 'due_to']);
        $limit   = (int) ($req->get_param('per_page') ?? 50);
        $offset  = (int) ($req->get_param('page') ?? 0) * $limit;
        $rows    = ActivityService::search($filters, max(1, min(200, $limit)), max(0, $offset));
        $total   = ActivityService::count($filters);
        return rest_ensure_response([
            'data' => $rows,
            'meta' => ['total' => $total, 'per_page' => max(1, min(200, $limit))],
        ]);
    }

    public static function activitiesShow(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $row = $id > 0 ? ActivityService::find($id) : ActivityService::findByUuid((string) $req->get_param('id'));
        if (!$row) {
            return new WP_Error('not_found', 'Activity not found', ['status' => 404]);
        }
        return rest_ensure_response($row);
    }

    public static function activitiesStore(WP_REST_Request $req)
    {
        try {
            $id = ActivityService::create((array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('activity.create_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['id' => $id, 'activity' => ActivityService::find($id)], 201);
    }

    public static function activitiesUpdate(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if (!ActivityService::find($id)) {
            return new WP_Error('not_found', 'Activity not found', ['status' => 404]);
        }
        try {
            $ok = ActivityService::update($id, (array) $req->get_json_params());
        } catch (\Throwable $e) {
            return new WP_Error('activity.update_failed', $e->getMessage(), ['status' => 400]);
        }
        return rest_ensure_response(['ok' => $ok, 'activity' => ActivityService::find($id)]);
    }

    public static function activitiesDestroy(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = ActivityService::softDelete($id);
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function activitiesComplete(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = ActivityService::complete($id);
        return rest_ensure_response(['ok' => $ok, 'activity' => ActivityService::find($id)]);
    }

    public static function activitiesCancel(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        $ok = ActivityService::cancel($id);
        return rest_ensure_response(['ok' => $ok, 'activity' => ActivityService::find($id)]);
    }

    public static function activitiesToday()
    {
        $uid = get_current_user_id();
        return rest_ensure_response(ActivityService::todaysTasks($uid));
    }

    /* ------------------------------------------------------------------ *
     *  Helpers
     * ------------------------------------------------------------------ */

    /**
     * استخراج فیلتر از request.
     *
     * @return array<string,mixed>
     */
    private static function pickFilters(WP_REST_Request $req, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            $val = $req->get_param($key);
            if ($val === null || $val === '') {
                continue;
            }
            $out[$key] = is_string($val) ? sanitize_text_field($val) : $val;
        }
        return $out;
    }
}
