<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrdersController extends Controller
{
    public function index(Request $request): mixed
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $viewMode = $request->query('view', 'my_data');
        $hasTeamAccess = $employee->hasActiveTeamAccess();
        $activeGrant = null;

        if ($viewMode === 'team_data') {
            if (! $hasTeamAccess || ! $employee->team_id) {
                return redirect()->route('employee.orders', ['view' => 'my_data'])
                    ->with('warning', 'You do not have an active Team Data access grant from Super Admin.');
            }

            $today = now()->startOfDay()->toDateString();
            $activeGrant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $orders = SalesOrder::where('team_id', $employee->team_id)
                ->whereDate('created_at', '>=', $activeGrant->start_date)
                ->whereDate('created_at', '<=', $activeGrant->end_date)
                ->with(['product', 'assignedEmployee'])
                ->orderByDesc('id')
                ->paginate(20)
                ->appends(['view' => 'team_data']);
        } else {
            $viewMode = 'my_data';
            $orders = SalesOrder::where('assigned_employee_id', $employee->id)
                ->with(['product'])
                ->orderByDesc('id')
                ->paginate(20);
        }

        return view('employee.orders.index', [
            'orders' => $orders,
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
        ]);
    }

    public function show(Request $request, SalesOrder $order): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $isViewOnly = false;

        if ((int) $order->assigned_employee_id === (int) $employee->id) {
            $isViewOnly = false;
        } elseif ($employee->hasActiveTeamAccess() && (int) $order->team_id === (int) $employee->team_id) {
            $today = now()->startOfDay()->toDateString();
            $grant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            if (! $grant) {
                abort(403, 'Unauthorized access to this order.');
            }

            $orderDate = $order->created_at->toDateString();
            if ($orderDate < $grant->start_date->toDateString() || $orderDate > $grant->end_date->toDateString()) {
                abort(403, 'This order is outside your granted historical team date window.');
            }

            // Allowed in view-only mode
            $isViewOnly = true;
        } else {
            abort(403, 'Unauthorized access to this order.');
        }

        $order->loadMissing(['product', 'subscription', 'payments', 'team']);
        $apiKeyStatus = app(\App\Services\OrderCredentialService::class)->status($order);
        $whatsappShare = app(\App\Services\WhatsappShareService::class)->prepare($order, $employee->hasPermission('orders.view_api_key'));

        return view('employee.orders.show', [
            'order' => $order,
            'employee' => $employee,
            'isViewOnly' => $isViewOnly,
            'apiKeyStatus' => $apiKeyStatus,
            'whatsappShare' => $whatsappShare,
        ]);
    }

    public function complete(Request $request, SalesOrder $order): \Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        abort_if((int) $order->assigned_employee_id !== (int) $employee->id, 403, 'Unauthorized access to this order.');
        abort_unless($employee->hasPermission('orders.complete'), 403, 'You do not have permission to complete orders.');

        if ($order->closing_value_status === 'Requested') {
            return back()->with('error', 'Cannot complete order: Closing Value Approval is pending.');
        }

        if ($order->closing_value_status === 'Rejected') {
            return back()->with('error', 'Cannot complete order: Closing Value was rejected.');
        }

        if ($order->api_key_required_snapshot && ! \App\Services\OrderCredentialService::hasKey($order)) {
            return back()->with('error', 'Cannot complete order: A customer API key is required for this product.');
        }

        $order->update(['order_status' => 'confirmed']);

        // Attempt activation if verified payment satisfies policy
        $subscription = app(\App\Services\SubscriptionActivator::class)->maybeActivate($order, null);

        $msg = $subscription
            ? "Order {$order->order_number} completed and subscription activated!"
            : "Order {$order->order_number} status updated to confirmed.";

        return back()->with('status', $msg);
    }

    public function revealApiKey(Request $request, SalesOrder $order): \Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        abort_if((int) $order->assigned_employee_id !== (int) $employee->id, 403, 'Unauthorized access to this order.');
        abort_unless($employee->hasPermission('orders.view_api_key'), 403, 'You do not have permission to view API keys.');

        $key = app(\App\Services\OrderCredentialService::class)->reveal($order, $employee);
        if ($key === null) {
            return back()->with('error', 'No API key is stored for this order, or it could not be decrypted.');
        }

        session(['order_api_key_reveal' => ['order_id' => $order->id, 'key' => $key]]);

        return back()->with('status', 'API key revealed for '.$order->order_number.'. Use Copy, then it will be cleared.');
    }

    public function storeApiKey(Request $request, SalesOrder $order): \Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        abort_if((int) $order->assigned_employee_id !== (int) $employee->id, 403, 'Unauthorized access to this order.');
        abort_unless($employee->hasPermission('orders.complete'), 403, 'You do not have permission to deliver API keys / complete orders.');

        $data = $request->validate([
            'api_key' => ['required', 'string'],
        ]);

        $result = app(\App\Services\OrderCredentialService::class)->store($order, $data['api_key'], $employee, $request->ip());

        return back()->with($result['saved'] ? 'status' : 'error', $result['message']);
    }
}
