<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = Auth::guard('team')->user();

        // Fetch notifications for this team
        $notifications = $team->notifications()
            ->orderByDesc('created_at')
            ->paginate(20);

        // Mark as read when viewed
        $team->unreadNotifications->markAsRead();

        return view('team.notifications.index', [
            'notifications' => $notifications,
            'team' => $team,
        ]);
    }
}
