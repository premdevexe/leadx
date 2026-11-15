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

        $query = Subscription::whereHas('order', function ($q) use ($employee) {
                $q->where('assigned_employee_id', $employee->id);
            })
            ->with(['order', 'product', 'plan']);

        if ($request->query('filter') === 'expiring') {
            $query->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->where('expires_at', '>', now())
                ->orderBy('expires_at');
        } else {
            $query->orderByDesc('id');
        }

        $subscriptions = $query->paginate(20);

        return view('employee.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'employee' => $employee,
            'isExpiringOnly' => $request->query('filter') === 'expiring',
        ]);
    }

    public function expiring(Request $request): View
    {
        $request->merge(['filter' => 'expiring']);
        return $this->index($request);
    }
}
