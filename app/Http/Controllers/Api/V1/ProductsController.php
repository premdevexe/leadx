<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

/**
 * The sellable product catalog, straight from the database.
 *
 * Order creation takes a product_id; this endpoint is what callers use to
 * pick one. Only active products are offered - the database is the single
 * source of truth for names, prices and durations (never client input).
 */
class ProductsController extends Controller
{
    public function __construct(protected OrderService $orders)
    {
    }

    /**
     * GET /api/v1/products
     *
     * Active catalog products available for order selection. A new product
     * an admin creates appears here immediately (no configuration, no
     * hardcoding); inactive products are excluded.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'products' => $this->orders->activeProducts()->map(
                static fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'duration' => $product->duration,
                    'duration_label' => \App\Models\SalesOrder::DURATIONS[$product->duration] ?? $product->duration,
                ]
            )->values(),
        ]);
    }
}