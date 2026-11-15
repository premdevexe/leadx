<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomersController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $viewMode = $request->query('view', 'my_data');
        $hasTeamAccess = $employee->hasActiveTeamAccess();
        $activeGrant = null;

        $orderQuery = \DB::table('sales_orders');

        if ($viewMode === 'team_data') {
            if (! $hasTeamAccess || ! $employee->team_id) {
                return redirect()->route('employee.customers', ['view' => 'my_data'])
                    ->with('warning', 'You do not have an active Team Data access grant from Super Admin.');
            }

            $today = now()->startOfDay()->toDateString();
            $activeGrant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $orderQuery->where('team_id', $employee->team_id)
                ->whereDate('created_at', '>=', $activeGrant->start_date)
                ->whereDate('created_at', '<=', $activeGrant->end_date);
        } else {
            $viewMode = 'my_data';
            $orderQuery->where('assigned_employee_id', $employee->id);
        }

        $customerPhones = $orderQuery->distinct()->pluck('customer_phone');

        $customers = Customer::whereIn('phone', $customerPhones)
            ->orderByDesc('created_at')
            ->paginate(20)->appends(['view' => $viewMode]);

        return view('employee.customers.index', [
            'customers' => $customers,
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
        ]);
    }
}
