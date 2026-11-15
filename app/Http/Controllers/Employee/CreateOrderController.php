<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\OrderNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreateOrderController extends Controller
{
    public function create(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        /** @var User $employee */
        $employee = $request->user();

        if (! $employee->team_id) {
            return redirect()->route('employee.orders')->withErrors(['team_id' => 'You are not assigned to a team and cannot create orders.']);
        }

        $products = Product::where('status', 'active')->orderBy('name')->get();

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

        $team = \App\Models\Team::findOrFail($employee->team_id);
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
                ->firstOrFail();
        }

        $productPrice = $product ? (float) $product->price : (float) $validated['closing_value'];
        $productDuration = $product ? (string) $product->duration : $validated['duration'];
        $productName = $product ? $product->name : ($validated['product_name'] ?? 'Custom');
        $minClosingPrice = $product ? (float) ($product->minimum_closing_price ?? 0) : 0;
        $apiKeyRequired = $product ? (bool) ($product->api_key_required ?? false) : false;

        $closingValue = (float) $validated['closing_value'];

        if ($product && $minClosingPrice > 0 && $closingValue < $minClosingPrice) {
            return back()->withInput()->withErrors([
                'closing_value' => "The closing value (\${$closingValue}) cannot be less than the product minimum closing price of \${$minClosingPrice}.",
            ]);
        }

        $discountAmount = round(max(0.0, $productPrice - $closingValue), 2);
        $needsApproval = $product && $closingValue < $productPrice;
        $closingStatus = $needsApproval ? 'Requested' : 'Accepted';

        $order = DB::transaction(function () use ($numbers, $team, $employee, $product, $productName, $productPrice, $productDuration, $minClosingPrice, $apiKeyRequired, $closingStatus, $needsApproval, $closingValue, $discountAmount, $validated) {
            $orderNumber = $numbers->next($team);

            return SalesOrder::create([
                'order_number' => $orderNumber,
                'team_id' => $team->id,
                'team_code_snapshot' => $team->team_code,
                'assigned_employee_id' => $employee->id,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'product_id' => $product?->id,
                'product_name' => $productName,
                'product_name_snapshot' => $productName,
                'product_price_snapshot' => $productPrice,
                'product_duration_snapshot' => $productDuration,
                'minimum_closing_price_snapshot' => $minClosingPrice,
                'api_key_required_snapshot' => $apiKeyRequired,
                'closing_value_status' => $closingStatus,
                'closing_approval_requested_at' => $needsApproval ? now() : null,
                'duration' => $productDuration,
                'product_value' => $productPrice,
                'discount_amount' => $discountAmount,
                'closing_value' => $closingValue,
                'amount_received' => $validated['amount_received'] ?? 0,
                'payment_method' => $validated['payment_method'] ?? null,
                'payment_status' => 'pending',
                'order_status' => 'pending',
            ]);
        });

        $message = $needsApproval
            ? 'Order created successfully. Closing value discount requires Super Admin approval.'
            : 'Order created successfully.';

        app(\App\Services\NotificationHubService::class)->notifyOrderCreated($order);
        if ($needsApproval) {
            $order->closingValueHistories()->create([
                'actor_type' => 'employee',
                'actor_id' => $employee->id,
                'status' => 'Requested',
                'previous_value' => $product ? $product->price : null,
                'proposed_value' => $order->closing_value,
                'notes' => 'Initial discounted order submission requiring approval.',
            ]);
            app(\App\Services\NotificationHubService::class)->notifyClosingApprovalRequested($order);
        }

        return redirect()->route('employee.orders.show', $order)->with('success', $message);
    }
}
