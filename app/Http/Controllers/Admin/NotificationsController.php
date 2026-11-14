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
    /**
     * Pusher Beams interest names may only contain letters, numbers and the
     * characters _ - = @ , . ; — spaces and most other symbols are rejected
     * with a 422. We validate before hitting the API so the user sees a
     * helpful message instead of decoding Pusher's error.
     */
    protected const INTEREST_PATTERN = '/^[a-zA-Z0-9_\-=@,.;]+$/';

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
            'status' => ['sometimes', 'in:sent,failed'],
        ]);

        $limit = $request->integer('limit', 25);
        $status = $request->input('status');

        $history = PushNotificationLog::query()
            ->latest()
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->take($limit)
            ->get();

        return view('admin.notifications.index', compact('history', 'limit', 'status'));
    }

    /**
     * Send a Web push notification to one or more Beams interests.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:300'],
            'interests' => ['required', 'string'],
        ]);

        $interests = array_values(array_filter(array_map(
            'trim',
            explode(',', $request->input('interests'))
        )));

        if ($interests === []) {
            return back()
                ->withInput()
                ->withErrors(['interests' => 'Enter at least one interest.']);
        }

        $invalid = array_filter(
            $interests,
            fn (string $interest) => preg_match(self::INTEREST_PATTERN, $interest) !== 1,
        );

        if ($invalid !== []) {
            return back()
                ->withInput()
                ->withErrors(['interests' => 'Invalid interest(s): '.implode(', ', array_map(
                    fn ($interest) => "'{$interest}'",
                    $invalid,
                )).' — Beams interest names may only contain letters, numbers and _ - = @ , . ; (no spaces).']);
        }

        $adminId = auth('admin')->id();

        return $this->publishAndLog(
            adminId: $adminId,
            title: $request->input('title'),
            body: $request->input('body'),
            interests: $interests,
        );
    }

    /**
     * Re-send a previously failed notification from the send history.
     *
     * The admin may supply corrected interests (the retry form carries the
     * recorded interests in an editable field), so an entry that originally
     * failed due to invalid interests can still be retried after a fix.
     */
    public function resend(PushNotificationLog $log, Request $request): RedirectResponse
    {
        if ($log->status !== 'failed') {
            return back()
                ->with('error', 'Only failed notifications can be retried.');
        }

        $interests = $this->resolveRetryInterests($log, $request->input('interests'));

        if ($interests === []) {
            return back()
                ->with('error', 'This entry has no interests to send to — cannot retry.');
        }

        $invalid = array_filter(
            $interests,
            fn (string $interest) => preg_match(self::INTEREST_PATTERN, $interest) !== 1,
        );

        if ($invalid !== []) {
            return back()
                ->with("retry_error.{$log->id}", 'Invalid interest(s): '.implode(', ', array_map(
                    fn ($interest) => "'{$interest}'",
                    $invalid,
                )).' — interest names may only contain letters, numbers and _ - = @ , . ; (no spaces), e.g. "Divi-Chellow". Fix the interests above and retry.')
                ->with("retry_interests.{$log->id}", $request->input('interests'));
        }

        return $this->publishAndLog(
            adminId: auth('admin')->id(),
            title: $log->title,
            body: $log->body,
            interests: $interests,
        );
    }

    /**
     * Determine which interests a retry should target: the corrected
     * interests supplied with the retry request if any, otherwise the
     * interests recorded on the failed entry.
     *
     * @return string[]
     */
    protected function resolveRetryInterests(PushNotificationLog $log, ?string $submitted): array
    {
        $interests = $submitted !== null && trim($submitted) !== ''
            ? array_filter(array_map('trim', explode(',', $submitted)))
            : $log->interestList();

        return array_values(array_filter($interests, fn (string $interest) => $interest !== ''));
    }

    /**
     * Publish a notification to Beams and record the outcome in the log.
     *
     * @param  string[]  $interests
     */
    protected function publishAndLog(
        int $adminId,
        string $title,
        string $body,
        array $interests,
    ): RedirectResponse {
        try {
            $result = $this->beams->publishWebNotification($title, $body, $interests);
        } catch (\Throwable $e) {
            PushNotificationLog::create([
                'admin_id' => $adminId,
                'title' => $title,
                'body' => $body,
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
                'title' => $title,
                'body' => $body,
                'interests' => implode(',', $interests),
                'publish_id' => $result['publish_id'],
                'status' => 'sent',
            ]);

            return redirect()
                ->route('admin.notifications')
                ->with('status', "Notification '{$title}' sent to ".count($interests).
                    ' interest(s) — publish id '.$result['publish_id'].'.');
        }

        $reason = trim($result['body']);

        PushNotificationLog::create([
            'admin_id' => $adminId,
            'title' => $title,
            'body' => $body,
            'interests' => implode(',', $interests),
            'status' => 'failed',
            'error' => 'HTTP status '.$result['status'].($reason !== '' ? ' — '.$reason : ''),
        ]);

        return back()
            ->withInput()
            ->with('error', "Beams publish request for '{$title}' failed with HTTP status ".$result['status'].
                ($reason !== '' ? ' — '.$reason : '').
                '. Check your PUSHER_INSTANCE_ID and PUSHER_SERVER_KEY in .env.');
    }
}
