<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderNumberService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateOrderController extends Controller
{
    public function __construct(protected OrderService $orders)
    {
    }

    public function create(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        /** @var User $employee */
        $employee = $request->user();

        if (! $employee->team_id) {
            return redirect()->route('employee.orders')->withErrors(['team_id' => 'You are not assigned to a team and cannot create orders.']);
        }

        $products = $this->orders->activeProducts();
        $numbers = app(OrderNumberService::class);
        $previewNumber = $employee->team
            ? $numbers->peekNext($employee->team)
            : '—';

        return view('employee.orders.create', [
            'employee' => $employee,
            'products' => $products,
            'previewOrderNumber' => $previewNumber,
        ]);
    }

    public function store(Request $request)
    {
        /** @var User $employee */
        $employee = $request->user();

        if (! $employee->team_id) {
            return back()->withErrors(['team_id' => 'You are not assigned to a team and cannot create orders.']);
        }

        $team = Team::findOrFail($employee->team_id);

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

        if (! empty($validated['product_id']) && empty($validated['items'])) {
            Product::where('status', 'active')->findOrFail($validated['product_id']);
        }

        // Preserve legacy employee form expectation: closing_value === '' falls back to list total
        $order = $this->orders->createForTeam($team, $employee, $validated);

        $message = $order->closing_value_status === 'Requested'
            ? 'Order created successfully. Closing value discount requires Super Admin approval.'
            : 'Order created successfully.';

        return redirect()->route('employee.orders.show', $order)->with('success', $message);
    }
}
