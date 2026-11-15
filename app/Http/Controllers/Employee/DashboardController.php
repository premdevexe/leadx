<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $myOrders = SalesOrder::where('assigned_employee_id', $employee->id);

        $totalOrders = (clone $myOrders)->count();
        $closingValue = (clone $myOrders)->sum('closing_value');

        $staleAfterDays = (int) config('leadx.payments.stale_after_days', 90);
        $cutoff = now()->subDays($staleAfterDays);

        $verifiedRevenue = (clone $myOrders)
            ->join('payments', 'sales_orders.id', '=', 'payments.order_id')
            ->where(function ($q) {
                $q->whereNotNull('payments.verified_by_admin_id')
                    ->orWhere('payments.verification_source', 'gateway');
            })
            ->whereNotIn('payments.payment_status', ['failed', 'refunded'])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('payments.verified_at')
                    ->orWhere('payments.verified_at', '>=', $cutoff);
            })
            ->sum('payments.amount');

        $pendingAmount = (clone $myOrders)
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->sum('closing_value');

        $teamId = $employee->team_id;

        $activeSubscriptions = Subscription::where('team_id', $teamId)
            ->where('status', 'active')->count();

        $expiringSubscriptions = Subscription::where('team_id', $teamId)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        return view('employee.dashboard', [
            'employee' => $employee,
            'totalOrders' => $totalOrders,
            'closingValue' => number_format((float) $closingValue, 2),
            'verifiedRevenue' => number_format((float) $verifiedRevenue, 2),
            'pendingAmount' => number_format((float) $pendingAmount, 2),
            'activeSubscriptions' => $activeSubscriptions,
            'expiringSubscriptions' => $expiringSubscriptions,
        ]);
    }
}
