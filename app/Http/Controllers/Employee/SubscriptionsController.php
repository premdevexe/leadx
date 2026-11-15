<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $subscriptions = Subscription::whereHas('order', function ($q) use ($employee) {
                $q->where('assigned_employee_id', $employee->id);
            })
            ->with(['order', 'product', 'plan'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('employee.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'employee' => $employee,
        ]);
    }
}
