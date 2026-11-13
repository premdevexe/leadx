<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushNotificationLog;
use App\Services\BeamsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function __construct(protected BeamsService $beams)
    {
    }

    /**
     * Clear all notification history.
     */
    public function clear(): RedirectResponse
    {
        $deleted = PushNotificationLog::query()->delete();

        return redirect()
            ->route('admin.notifications')
            ->with('status', $deleted > 0
                ? "Notification history cleared ({$deleted} record(s) removed)."
                : 'History is already empty.');
    }

    /**
     * Show the "send notification" page, with recent send history.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'limit' => ['sometimes', 'integer', 'min:5', 'max:100'],
        ]);

        $limit = $request->integer('limit', 25);

        $history = PushNotificationLog::query()
            ->latest()
            ->take($limit)
            ->get();

        return view('admin.notifications.index', compact('history', 'limit'));
    }

    /**
     * Send a Web push notification to one or more Beams interests.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:300'],
            'interests' => ['required', 'string'],
        ]);

        $interests = array_values(array_filter(array_map(
            'trim',
            explode(',', $data['interests'])
        )));

        if ($interests === []) {
            return back()
                ->withInput()
                ->withErrors(['interests' => 'Enter at least one interest.']);
        }

        $adminId = auth('admin')->id();

        try {
            $result = $this->beams->publishWebNotification(
                $data['title'],
                $data['body'],
                $interests,
            );
        } catch (\Throwable $e) {
            PushNotificationLog::create([
                'admin_id' => $adminId,
                'title' => $data['title'],
                'body' => $data['body'],
                'interests' => implode(',', $interests),
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Could not reach the Pusher Beams service: '.$e->getMessage());
        }

        if ($result['status'] === 200) {
            PushNotificationLog::create([
                'admin_id' => $adminId,
                'title' => $data['title'],
                'body' => $data['body'],
                'interests' => implode(',', $interests),
                'publish_id' => $result['publish_id'],
                'status' => 'sent',
            ]);

            return redirect()
                ->route('admin.notifications')
                ->with('status', "Notification sent to ".count($interests).
                    ' interest(s) — publish id '.$result['publish_id'].'.');
        }

        PushNotificationLog::create([
            'admin_id' => $adminId,
            'title' => $data['title'],
            'body' => $data['body'],
            'interests' => implode(',', $interests),
            'status' => 'failed',
            'error' => 'HTTP status '.$result['status'],
        ]);

        return back()
            ->withInput()
            ->with('error', 'Beams publish request failed with HTTP status '.$result['status'].
                '. Check your PUSHER_INSTANCE_ID and PUSHER_SERVER_KEY in .env.');
    }
}
