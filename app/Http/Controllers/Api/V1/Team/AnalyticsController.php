<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Team-scoped Sales Analytics API.
 *
 * Strictly scoped to the authenticated team.
 * Protected by auth:sanctum + ensure-role-active:team.
 */
class AnalyticsController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/v1/team/analytics
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Team $team */
        $team = $request->user('sanctum');

        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'product_id', 'employee_id', 'payment_status',
        ]);

        return response()->json($this->analytics->teamAnalytics($team, $filters));
    }
}
