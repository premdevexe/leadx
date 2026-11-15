<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $memberCount = $team->activeMembers()->count();

        $baseOrderQuery = SalesOrder::where('team_id', $team->id);

        $totalOrders = (clone $baseOrderQuery)->count();
        $closingValue = (clone $baseOrderQuery)->sum('closing_value');

        $staleAfterDays = (int) config('leadx.payments.stale_after_days', 90);
        $cutoff = now()->subDays($staleAfterDays);

        $verifiedRevenue = (clone $baseOrderQuery)
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

        $pendingAmount = (clone $baseOrderQuery)
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->sum('closing_value');

        $baseSubQuery = Subscription::where('team_id', $team->id);

        $activeSubscriptions = (clone $baseSubQuery)->where('status', 'active')->count();
        $expiredSubscriptions = (clone $baseSubQuery)->where('status', 'expired')->count();
        $expiringSubscriptions = (clone $baseSubQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        $currencySymbol = \App\Models\Setting::currencySymbol();

        return view('team.dashboard', [
            'team' => $team,
            'memberCount' => $memberCount,
            'totalOrders' => $totalOrders,
            'closingValue' => number_format((float) $closingValue, 2),
            'verifiedRevenue' => number_format((float) $verifiedRevenue, 2),
            'pendingAmount' => number_format((float) $pendingAmount, 2),
            'activeSubscriptions' => $activeSubscriptions,
            'expiredSubscriptions' => $expiredSubscriptions,
            'expiringSubscriptions' => $expiringSubscriptions,
            'currencySymbol' => $currencySymbol,
        ]);
    }
}
