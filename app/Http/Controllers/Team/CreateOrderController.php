<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Services\OrderNumberService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateOrderController extends Controller
{
    public function __construct(protected OrderService $orders)
    {
    }

    public function create(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $products = $this->orders->activeProducts();
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

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_phone_local' => ['nullable', 'string', 'max:30'],
            'customer_country_code' => ['nullable', 'string', 'max:10'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.duration' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'duration' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'closing_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'order_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Team portal order creation has no assigned employee and uses the same
        // centralized OrderService validation, snapshots, and line-item persistence.
        $validated['skip_notifications'] = true;
        $order = $this->orders->createForTeam($team, null, $validated);

        return redirect()->route('team.orders.show', $order)->with('success', 'Order created successfully.');
    }
}
