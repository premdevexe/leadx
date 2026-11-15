<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $notifications = $employee->notifications()
            ->orderByDesc('created_at')
            ->paginate(20);

        $employee->unreadNotifications->markAsRead();

        return view('employee.notifications.index', [
            'notifications' => $notifications,
            'employee' => $employee,
        ]);
    }
}
