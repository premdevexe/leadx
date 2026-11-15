<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\OrderNumberService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateOrderController extends Controller
{
    public function create(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $products = Product::where('status', 'active')->orderBy('name')->get();
        $numbers = app(OrderNumberService::class);
        $previewNumber = $numbers->peekNext($team);

        return view('team.orders.create', [
            'team' => $team,
            'products' => $products,
            'previewOrderNumber' => $previewNumber,
        ]);
    }

    public function store(Request $request)
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();
        $numbers = app(OrderNumberService::class);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'product_id' => ['nullable', 'exists:products,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'duration' => ['required', 'string', 'in:3d,7d,15d,30d,3m,6m,1y'],
            'closing_value' => ['required', 'numeric', 'min:0'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'order_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = null;
        if (! empty($validated['product_id'])) {
            $product = \App\Models\Product::where('id', $validated['product_id'])
                ->where('status', 'active')
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => 'The selected product is not available.',
                ]);
            }
        }

        $productPrice = $product ? (float) $product->price : (float) $validated['closing_value'];
        $productDuration = $product ? (string) $product->duration : $validated['duration'];
        $productName = $product ? $product->name : ($validated['product_name'] ?? 'Custom');

        $closingValue = (float) $validated['closing_value'];
        $discountAmount = round(max(0.0, $productPrice - $closingValue), 2);

        $orderNumber = $numbers->next($team);

        $order = SalesOrder::create([
            'order_number' => $orderNumber,
            'team_id' => $team->id,
            'team_code_snapshot' => $team->team_code,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'product_id' => $product?->id,
            'product_name' => $productName,
            'product_name_snapshot' => $productName,
            'product_price_snapshot' => $productPrice,
            'product_duration_snapshot' => $productDuration,
            'duration' => $productDuration,
            'product_value' => $productPrice,
            'discount_amount' => $discountAmount,
            'closing_value' => $closingValue,
            'amount_received' => $validated['amount_received'] ?? 0,
            'payment_method' => $validated['payment_method'] ?? null,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ]);

        return redirect()->route('team.orders.show', $order)->with('success', 'Order created successfully.');
    }
}
