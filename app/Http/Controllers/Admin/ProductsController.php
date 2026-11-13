<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-facing product catalog. Orders snapshot the product name and value
 * at creation time, so products are safe to edit or retire later.
 */
class ProductsController extends Controller
{
    /**
     * List products (with how many orders reference each name).
     */
    public function index(Request $request): View
    {
        $query = Product::query()->withCount('orders');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('search'));
            $query->where('name', 'like', '%'.$term.'%');
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
            ],
            'statusColors' => ['active' => 'text-emerald-700', 'inactive' => 'text-neutral-500'],
        ]);
    }

    /**
     * Show the create-product form.
     */
    public function create(): View
    {
        return view('admin.products.create', ['product' => new Product(['status' => 'active'])]);
    }

    /**
     * Store a new product.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Product::create($data);

        return redirect()->route('admin.products')->with('status', 'Product created.');
    }

    /**
     * Show the edit-product form.
     */
    public function edit(Product $product): View
    {
        return view('admin.products.edit', ['product' => $product]);
    }

    /**
     * Update a product.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return redirect()->route('admin.products')->with('status', 'Product updated.');
    }

    /**
     * Delete or safely archive a product. If existing orders reference
     * this product, it is deactivated rather than hard-deleted so historical
     * records and snapshots remain intact.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $ordersCount = SalesOrder::where('product_id', $product->id)->count();

        if ($ordersCount > 0) {
            $product->update(['status' => 'inactive']);

            return redirect()->route('admin.products')->with(
                'status',
                "Product '{$product->name}' is linked to {$ordersCount} order(s). It has been safely archived/deactivated to preserve order history."
            );
        }

        $product->delete();

        return redirect()->route('admin.products')->with('status', 'Product deleted.');
    }

    /**
     * Quick toggle product status (active <-> inactive).
     */
    public function toggleStatus(Product $product): RedirectResponse
    {
        $newStatus = $product->status === 'active' ? 'inactive' : 'active';
        $product->update(['status' => $newStatus]);

        return redirect()->route('admin.products')->with('status', "Product '{$product->name}' status changed to {$newStatus}.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Product $product = null): array
    {
        $isEdit = $product !== null;

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $isEdit ? Rule::unique('products', 'name')->ignore($product->id) : 'unique:products,name'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'duration' => ['sometimes', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'status' => ['sometimes', 'in:'.implode(',', Product::STATUSES)],
        ]);
    }
}
