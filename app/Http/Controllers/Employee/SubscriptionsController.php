<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $viewMode = $request->query('view', 'my_data');
        $hasTeamAccess = $employee->hasActiveTeamAccess();
        $activeGrant = null;
        $isExpiringOnly = $request->query('filter') === 'expiring';

        $baseQuery = Subscription::query()
            ->with(['order.product', 'order.team', 'plan']);

        if ($viewMode === 'team_data') {
            if (! $hasTeamAccess || ! $employee->team_id) {
                return redirect()->route('employee.subscriptions', ['view' => 'my_data'])
                    ->with('warning', 'You do not have an active Team Data access grant from Super Admin.');
            }

            $today = now()->startOfDay()->toDateString();
            $activeGrant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $baseQuery->whereHas('order', function ($q) use ($employee, $activeGrant) {
                $q->where('team_id', $employee->team_id)
                    ->whereDate('created_at', '>=', $activeGrant->start_date)
                    ->whereDate('created_at', '<=', $activeGrant->end_date);
            });
        } else {
            $viewMode = 'my_data';
            $baseQuery->whereHas('order', function ($q) use ($employee) {
                $q->where('assigned_employee_id', $employee->id);
            });
        }

        if ($isExpiringOnly) {
            $baseQuery->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->where('expires_at', '>', now())
                ->orderBy('expires_at');
        } else {
            $baseQuery->orderByDesc('id');
        }

        $subscriptions = $baseQuery->paginate(20)->appends(['view' => $viewMode]);

        return view('employee.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
            'isExpiringOnly' => $isExpiringOnly,
        ]);
    }

    public function expiring(Request $request): View
    {
        $request->merge(['filter' => 'expiring']);
        return $this->index($request);
    }
}
