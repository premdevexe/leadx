<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomersController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $customerPhones = \DB::table('sales_orders')
            ->where('assigned_employee_id', $employee->id)
            ->distinct()
            ->pluck('customer_phone');

        $customers = Customer::whereIn('phone', $customerPhones)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('employee.customers.index', [
            'customers' => $customers,
            'employee' => $employee,
        ]);
    }
}
