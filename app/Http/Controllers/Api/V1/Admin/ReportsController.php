<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin Detailed Reports API.
 *
 * Provides paginated records for orders, payments, and subscriptions
 * with full filtering capabilities.
 */
class ReportsController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/v1/admin/reports/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'product_id', 'employee_id', 'team_id', 'payment_status', 'order_status',
        ]);
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $paginator = $this->analytics->ordersReport($filters, $perPage);

        return response()->json([
            'data' => OrderResource::collection($paginator->items()),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * GET /api/v1/admin/reports/payments
     */
    public function payments(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'payment_status', 'payment_method', 'team_id',
        ]);
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $paginator = $this->analytics->paymentsReport($filters, $perPage);

        return response()->json([
            'data' => PaymentResource::collection($paginator->items()),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * GET /api/v1/admin/reports/subscriptions
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $filters = $request->only([
            'range', 'status', 'team_id', 'product_id',
        ]);
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $paginator = $this->analytics->subscriptionsReport($filters, $perPage);

        return response()->json([
            'data' => SubscriptionResource::collection($paginator->items()),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
