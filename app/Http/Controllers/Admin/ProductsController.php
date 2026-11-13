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
        $query = Product::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
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
     * Delete a product. Orders keep their snapshot; only active ones matter
     * going forward, so a hard delete is fine.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products')->with('status', 'Product deleted.');
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
