<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;

/**
 * Admin-only product analytics.
 *
 * The report is catalog-driven: every product in the database gets a row,
 * so a product an admin creates today appears here with zero values and
 * fills in as orders, payments and subscriptions attach to it. No report
 * configuration is required for new products.
 *
 * Access is enforced two ways (defense in depth): the route runs behind
 * `auth:sanctum` + `ensure-role-active:admin`, and this controller is only
 * wired into that group.
 */
class AnalyticsController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/v1/admin/analytics/products
     *
     * Per-product figures, all computed only from verified money:
     *   orders               orders created from that catalog product
     *   collected_revenue    verified payments attributed to it
     *   active_subscriptions active subscriptions for that product
     *   active_customers     distinct paying customers behind those
     *   pending_collection   closing value not yet covered by verified payments
     *
     * A trailing "Uncataloged" bucket covers legacy orders that only carry
     * a product name (product_id null), so the report stays complete.
     */
    public function productReport(): JsonResponse
    {
        return response()->json($this->analytics->productReport());
    }

    /**
     * GET /api/v1/admin/analytics/summary
     *
     * Headline revenue figures, verified money only.
     */
    public function summary(): JsonResponse
    {
        return response()->json($this->analytics->summary());
    }
}