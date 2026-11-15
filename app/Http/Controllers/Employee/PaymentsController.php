<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $orderIds = SalesOrder::where('assigned_employee_id', $employee->id)->pluck('id');

        $payments = Payment::whereIn('order_id', $orderIds)
            ->orderByDesc('id')
            ->paginate(20);

        return view('employee.payments.index', [
            'payments' => $payments,
            'employee' => $employee,
        ]);
    }
}
