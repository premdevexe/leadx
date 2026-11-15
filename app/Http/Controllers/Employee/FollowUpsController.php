<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $followUps = FollowUp::where('assigned_employee_id', $employee->id)
            ->with(['customer', 'order', 'team'])
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return view('employee.followups.index', [
            'followUps' => $followUps,
            'employee' => $employee,
        ]);
    }
}
