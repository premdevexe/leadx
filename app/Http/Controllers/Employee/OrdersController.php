<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrdersController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $orders = SalesOrder::where('assigned_employee_id', $employee->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('employee.orders.index', [
            'orders' => $orders,
            'employee' => $employee,
        ]);
    }

    public function show(Request $request, SalesOrder $order): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        abort_if((int) $order->assigned_employee_id !== (int) $employee->id, 403);

        $order->loadMissing(['product', 'subscription', 'payments', 'team']);

        return view('employee.orders.show', [
            'order' => $order,
            'employee' => $employee,
        ]);
    }
}
