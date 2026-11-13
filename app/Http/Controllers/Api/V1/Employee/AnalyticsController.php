<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee-scoped Sales Analytics API.
 *
 * Strictly scoped to the authenticated employee.
 * Protected by auth:sanctum + ensure-role-active:employee.
 */
class AnalyticsController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/v1/employee/analytics
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $request->user('sanctum');

        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'product_id', 'payment_status',
        ]);

        return response()->json($this->analytics->employeeAnalytics($employee, $filters));
    }
}
