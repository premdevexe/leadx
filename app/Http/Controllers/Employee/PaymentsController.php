<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $viewMode = $request->query('view', 'my_data');
        $hasTeamAccess = $employee->hasActiveTeamAccess();
        $activeGrant = null;

        $orderIdsQuery = SalesOrder::query();

        if ($viewMode === 'team_data') {
            if (! $hasTeamAccess || ! $employee->team_id) {
                return redirect()->route('employee.payments', ['view' => 'my_data'])
                    ->with('warning', 'You do not have an active Team Data access grant from Super Admin.');
            }

            $today = now()->startOfDay()->toDateString();
            $activeGrant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $orderIdsQuery->where('team_id', $employee->team_id)
                ->whereDate('created_at', '>=', $activeGrant->start_date)
                ->whereDate('created_at', '<=', $activeGrant->end_date);
        } else {
            $viewMode = 'my_data';
            $orderIdsQuery->where('assigned_employee_id', $employee->id);
        }

        $orderIds = $orderIdsQuery->pluck('id');

        $payments = Payment::whereIn('order_id', $orderIds)
            ->orderByDesc('id')
            ->paginate(20)->appends(['view' => $viewMode]);

        return view('employee.payments.index', [
            'payments' => $payments,
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
        ]);
    }
}
