<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Admin product catalog — API mirror of the web /admin/products panel.
 *
 * A new product created here is immediately selectable in the employee
 * order form (the database is the source of truth, no hardcoding).
 */
class ProductsController extends Controller
{
    /**
     * GET /api/v1/admin/products
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->withCount('orders');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('search'));
            $query->where('name', 'like', '%'.$term.'%');
        }

        return ProductResource::collection(
            $query->orderBy('name')->paginate(min(max((int) $request->input('per_page', 20), 1), 100))
        );
    }

    /**
     * POST /api/v1/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minimum_closing_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'duration' => ['sometimes', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'api_key_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:'.implode(',', Product::STATUSES)],
        ]);

        $data['minimum_closing_price'] = $data['minimum_closing_price'] ?? 0;
        $data['api_key_required'] = $request->boolean('api_key_required');

        $product = Product::create($data);

        $this->audit('product.created', $product, $request);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/admin/products/{product}
     */
    public function update(Request $request, int $product): JsonResponse
    {
        $model = Product::findOrFail($product);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('products', 'name')->ignore($model->id)],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minimum_closing_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'duration' => ['sometimes', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'api_key_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:'.implode(',', Product::STATUSES)],
        ]);

        $data['minimum_closing_price'] = $data['minimum_closing_price'] ?? $model->minimum_closing_price;
        $data['api_key_required'] = $request->boolean('api_key_required');

        $model->update($data);

        $this->audit('product.updated', $model, $request);

        return (new ProductResource($model->fresh()))->response();
    }

    /**
     * POST /api/v1/admin/products/{product}/toggle-status
     */
    public function toggleStatus(int $product): JsonResponse
    {
        $model = Product::findOrFail($product);

        $newStatus = $model->status === 'active' ? 'inactive' : 'active';
        $model->update(['status' => $newStatus]);

        return response()->json([
            'message' => "Product '{$model->name}' status changed to {$newStatus}.",
            'data' => new ProductResource($model->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/admin/products/{product}
     *
     * Products referenced by orders are archived (deactivated), never
     * hard-deleted, so historical order snapshots remain intact.
     */
    public function destroy(int $product): JsonResponse
    {
        $model = Product::findOrFail($product);
        $ordersCount = SalesOrder::where('product_id', $model->id)->count();

        if ($ordersCount > 0) {
            $model->update(['status' => 'inactive']);

            return response()->json([
                'archived' => true,
                'message' => "Product '{$model->name}' is linked to {$ordersCount} order(s); it has been deactivated to preserve order history.",
                'data' => new ProductResource($model->fresh()),
            ]);
        }

        $name = $model->name;
        $model->delete();

        return response()->json(['message' => "Product '{$name}' deleted."]);
    }

    protected function audit(string $event, Product $product, ?Request $request): void
    {
        DB::table('audit_logs')->insert([
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'event' => $event,
            'new_values' => json_encode(['admin_id' => $request ? $request->user('sanctum')?->id : null]),
            'ip' => $request?->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
