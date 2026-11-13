<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin Analytics API endpoints.
 *
 * Provides global sales, revenue, product, employee, and team intelligence.
 * Protected by auth:sanctum + ensure-role-active:admin.
 */
class AnalyticsController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/v1/admin/analytics/overview
     *
     * High-level KPI overview across all orders, products, and revenue.
     */
    public function overview(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'product_id', 'employee_id', 'team_id', 'payment_status', 'order_status',
        ]);

        return response()->json($this->analytics->overview($filters));
    }

    /**
     * GET /api/v1/admin/analytics/revenue
     *
     * Financial report distinguishing product value, closing value,
     * actual verified collections, pending amount, discounts, and refunds.
     */
    public function revenue(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'product_id', 'employee_id', 'team_id', 'payment_status', 'order_status',
        ]);

        return response()->json($this->analytics->revenueBreakdown($filters));
    }

    /**
     * GET /api/v1/admin/analytics/products
     *
     * Catalog-driven per-product figures, all computed only from verified money.
     */
    public function productReport(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'employee_id', 'team_id', 'payment_status',
        ]);

        return response()->json($this->analytics->productReport($filters));
    }

    /**
     * GET /api/v1/admin/analytics/employees
     *
     * Employee-wise sales performance comparison.
     */
    public function employees(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'team_id', 'employee_id', 'payment_status',
        ]);

        return response()->json($this->analytics->employeeReport($filters));
    }

    /**
     * GET /api/v1/admin/analytics/teams
     *
     * Team-wise revenue, collections, member performance, and subscription totals.
     */
    public function teams(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'team_id', 'payment_status',
        ]);

        return response()->json($this->analytics->teamReport($filters));
    }

    /**
     * GET /api/v1/admin/analytics/monthly
     *
     * Monthly trend series for revenue, order volume, and subscriptions.
     */
    public function monthly(Request $request): JsonResponse
    {
        $months = max(1, min(24, (int) $request->input('months', 12)));
        $filters = $request->only([
            'product_id', 'employee_id', 'team_id', 'payment_status',
        ]);

        return response()->json($this->analytics->monthlyReport($months, $filters));
    }

    /**
     * GET /api/v1/admin/analytics/summary
     *
     * Backward-compatible summary endpoint.
     */
    public function summary(): JsonResponse
    {
        return response()->json($this->analytics->summary());
    }
}
