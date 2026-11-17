<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppNotificationController extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Display the Mobile Push Notification Dashboard & History
     */
    public function index(): View
    {
        $notifications = AppNotification::with(['sender', 'adminSender'])
            ->latest()
            ->paginate(15);

        // Fetch active employees/users for target dropdown
        $users = User::select('id', 'name', 'email')
            ->where('role', 'employee')
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        }

        $isBeamsConfigured = $this->pushService->isConfigured();

        return view('admin.notifications.app', compact('notifications', 'users', 'isBeamsConfigured'));
    }

    /**
     * Send push notification from the admin composer
     */
    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'title'        => 'required|string|max:120',
            'body'         => 'required|string|max:500',
            'target'       => 'required|in:all,hello,user',
            'user_id'      => 'required_if:target,user|nullable|exists:users,id',
            'action_route' => 'nullable|string|max:100',
        ]);

        $title = $request->input('title');
        $body = $request->input('body');
        $target = $request->input('target');
        $targetValue = null;
        $publishId = null;

        $extraData = [
            'action' => $request->input('action_route') ?? 'home',
        ];

        try {
            if ($target === 'user') {
                $targetValue = $request->input('user_id');
                // Target a specific employee via their user interest: "user_{id}"
                $publishId = $this->pushService->sendToInterests(
                    ["user_{$targetValue}"],
                    $title,
                    $body,
                    $extraData
                );
            } elseif ($target === 'hello') {
                $publishId = $this->pushService->sendToInterests(
                    ['hello'],
                    $title,
                    $body,
                    $extraData
                );
            } else {
                // Send to both 'hello' and 'all' broadcast channels
                $publishId = $this->pushService->sendToInterests(
                    ['hello', 'all'],
                    $title,
                    $body,
                    $extraData
                );
            }

            // Save to database log
            AppNotification::create([
                'title'        => $title,
                'body'         => $body,
                'target'       => $target,
                'target_value' => (string) $targetValue,
                'action_url'   => $request->input('action_route'),
                'publish_id'   => $publishId,
                'created_by'   => auth('admin')->id() ?? auth()->id(),
            ]);

            return redirect()
                ->route('admin.notifications.index')
                ->with('success', 'Notification dispatched successfully! (ID: ' . $publishId . ')');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to send notification: ' . $e->getMessage());
        }
    }
}
