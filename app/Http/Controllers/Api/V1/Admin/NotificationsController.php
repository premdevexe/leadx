<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushNotificationLog;
use App\Services\BeamsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin push notifications — API mirror of the web /admin/notifications
 * panel: send a Web push to Beams interests, retry a failed send, and
 * clear the send history. Interest-name validation mirrors the web
 * controller so the app gets the same 422 messages.
 */
class NotificationsController extends Controller
{
    /**
     * Beams interest names: letters/numbers and _ - = @ , . ; only.
     */
    protected const INTEREST_PATTERN = '/^[a-zA-Z0-9_\-=@,.;]+$/';

    public function __construct(protected BeamsService $beams)
    {
    }

    /**
     * GET /api/v1/admin/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['sometimes', 'integer', 'min:5', 'max:100'],
            'status' => ['sometimes', 'in:sent,failed'],
        ]);

        $limit = $request->integer('limit', 25);
        $status = $request->input('status');

        $history = PushNotificationLog::query()
            ->latest()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->take($limit)
            ->get();

        return response()->json(['data' => $history]);
    }

    /**
     * POST /api/v1/admin/notifications
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:300'],
            'interests' => ['required', 'string'],
        ]);

        $interests = $this->parseInterests($data['interests']);

        $admin = $request->user('sanctum');

        try {
            $result = $this->beams->publishWebNotification($data['title'], $data['body'], $interests);
        } catch (\Throwable $e) {
            PushNotificationLog::create([
                'admin_id' => $admin->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'interests' => implode(',', $interests),
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw ValidationException::withMessages([
                'beams' => 'Could not reach the Pusher Beams service: '.$e->getMessage(),
            ]);
        }

        if ($result['status'] === 200) {
            PushNotificationLog::create([
                'admin_id' => $admin->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'interests' => implode(',', $interests),
                'publish_id' => $result['publish_id'],
                'status' => 'sent',
            ]);

            return response()->json([
                'message' => "Notification '{$data['title']}' sent to ".count($interests).' interest(s).',
                'publish_id' => $result['publish_id'],
            ], 201);
        }

        $reason = trim($result['body'] ?? '');

        PushNotificationLog::create([
            'admin_id' => $admin->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'interests' => implode(',', $interests),
            'status' => 'failed',
            'error' => 'HTTP status '.$result['status'].($reason !== '' ? ' — '.$reason : ''),
        ]);

        throw ValidationException::withMessages([
            'beams' => "Beams publish request failed with HTTP status {$result['status']}".
                ($reason !== '' ? ' — '.$reason : '').
                '. Check your PUSHER_INSTANCE_ID and PUSHER_SERVER_KEY in .env.',
        ]);
    }

    /**
     * POST /api/v1/admin/notifications/{log}/resend
     *
     * Retries a failed send. Corrected interests may be supplied to fix a
     * failure caused by an invalid interest name.
     */
    public function resend(Request $request, PushNotificationLog $log): JsonResponse
    {
        if ($log->status !== 'failed') {
            throw ValidationException::withMessages([
                'log' => 'Only failed notifications can be retried.',
            ]);
        }

        $interests = $request->input('interests') !== null && trim((string) $request->input('interests')) !== ''
            ? $this->parseInterests($request->input('interests'))
            : $log->interestList();

        if ($interests === []) {
            throw ValidationException::withMessages([
                'log' => 'This entry has no interests to send to — cannot retry.',
            ]);
        }

        $this->assertInterestsValid($interests);

        $admin = $request->user('sanctum');

        try {
            $result = $this->beams->publishWebNotification($log->title, $log->body, $interests);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'beams' => 'Could not reach the Pusher Beams service: '.$e->getMessage(),
            ]);
        }

        if ($result['status'] === 200) {
            PushNotificationLog::create([
                'admin_id' => $admin->id,
                'title' => $log->title,
                'body' => $log->body,
                'interests' => implode(',', $interests),
                'publish_id' => $result['publish_id'],
                'status' => 'sent',
            ]);

            return response()->json(['message' => 'Notification resent successfully.', 'publish_id' => $result['publish_id']]);
        }

        throw ValidationException::withMessages([
            'beams' => "Beams publish request failed with HTTP status {$result['status']}.",
        ]);
    }

    /**
     * DELETE /api/v1/admin/notifications
     */
    public function clear(Request $request): JsonResponse
    {
        $deleted = PushNotificationLog::query()->delete();

        return response()->json([
            'message' => $deleted > 0
                ? "Notification history cleared ({$deleted} record(s) removed)."
                : 'History is already empty.',
            'deleted' => $deleted,
        ]);
    }

    /**
     * @return string[]
     */
    protected function parseInterests(string $raw): array
    {
        $interests = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($interests === []) {
            throw ValidationException::withMessages([
                'interests' => 'Enter at least one interest.',
            ]);
        }

        $this->assertInterestsValid($interests);

        return $interests;
    }

    /**
     * @param string[] $interests
     */
    protected function assertInterestsValid(array $interests): void
    {
        $invalid = array_filter(
            $interests,
            fn (string $interest): bool => preg_match(self::INTEREST_PATTERN, $interest) !== 1
        );

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'interests' => "Invalid interest(s): ".implode(', ', array_map(
                    fn (string $interest): string => "'{$interest}'",
                    $invalid
                )).' — Beams interest names may only contain letters, numbers and _ - = @ , . ; (no spaces).',
            ]);
        }
    }
}
