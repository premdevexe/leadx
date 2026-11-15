<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $viewMode = $request->query('view', 'my_data');
        $hasTeamAccess = $employee->hasActiveTeamAccess();
        $activeGrant = null;

        $query = FollowUp::query()->with(['customer', 'order', 'team']);

        if ($viewMode === 'team_data') {
            if (! $hasTeamAccess || ! $employee->team_id) {
                return redirect()->route('employee.follow-ups', ['view' => 'my_data'])
                    ->with('warning', 'You do not have an active Team Data access grant from Super Admin.');
            }

            $today = now()->startOfDay()->toDateString();
            $activeGrant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $orderIds = \App\Models\SalesOrder::where('team_id', $employee->team_id)
                ->whereDate('created_at', '>=', $activeGrant->start_date)
                ->whereDate('created_at', '<=', $activeGrant->end_date)
                ->pluck('id');

            $query->whereIn('order_id', $orderIds);
        } else {
            $viewMode = 'my_data';
            $query->where('assigned_employee_id', $employee->id);
        }

        $followUps = $query->orderByDesc('scheduled_at')->paginate(20)->appends(['view' => $viewMode]);

        return view('employee.followups.index', [
            'followUps' => $followUps,
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
        ]);
    }
}
