<?php

namespace App\Jobs;

use App\Services\BeamsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asynchronously publishes a Web Push Notification to Pusher Beams.
 *
 * Keeps order creation, payment verification, and other critical business
 * workflows completely independent of external push notification network latency.
 *
 * Configured with afterCommit = true so notifications are never dispatched
 * for database transactions that subsequently roll back.
 */
class SendWebPushNotificationJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 15;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    /**
     * Create a new job instance.
     *
     * @param  string  $title
     * @param  string  $body
     * @param  array<int, string>  $interests
     * @param  string|null  $deduplicationKey
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $interests,
        public ?string $deduplicationKey = null,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(BeamsService $beams): void
    {
        $dedupKey = $this->resolveDedupKey();

        // 1. Accidental duplicate prevention check
        if (Cache::has($dedupKey)) {
            Log::info('Web push notification skipped: already processed.', [
                'dedup_key' => $dedupKey,
                'title' => $this->title,
                'interests' => $this->interests,
            ]);

            return;
        }

        try {
            $result = $beams->publishWebNotification($this->title, $this->body, $this->interests);
        } catch (Throwable $e) {
            $safeError = $this->sanitizeError($e->getMessage());

            Log::warning('Pusher Beams HTTP call encountered an exception', [
                'title' => $this->title,
                'interests' => $this->interests,
                'error' => $safeError,
                'attempt' => $this->attempts(),
            ]);

            // Re-throw so the queue worker retries according to tries and backoff
            throw $e;
        }

        $statusCode = (int) ($result['status'] ?? 0);

        // 2. Success: 2xx response from Pusher Beams
        if ($statusCode >= 200 && $statusCode < 300) {
            // Prevent duplicate notifications within a 10-minute window
            Cache::put($dedupKey, true, now()->addMinutes(10));

            Log::debug('Web push notification published successfully via queue', [
                'publish_id' => $result['publish_id'] ?? null,
                'interests' => $this->interests,
                'title' => $this->title,
            ]);

            return;
        }

        // 3. Transient failure: 5xx server error from Pusher -> retry
        if ($statusCode >= 500) {
            $safeError = $this->sanitizeError($result['body'] ?? 'HTTP ' . $statusCode);
            throw new \RuntimeException("Pusher Beams gateway error: HTTP {$statusCode} - {$safeError}");
        }

        // 4. Permanent failure: 4xx client error (e.g. invalid interest, credentials issue)
        // Retrying 4xx will not succeed, so we record the failure safely without retrying indefinitely.
        $safeError = $this->sanitizeError($result['body'] ?? 'HTTP ' . $statusCode);
        Log::warning('Pusher Beams rejected push notification with client error (non-retryable)', [
            'status' => $statusCode,
            'title' => $this->title,
            'interests' => $this->interests,
            'error' => $safeError,
        ]);
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $safeError = $this->sanitizeError($exception?->getMessage());

        Log::error('Web push notification permanently failed after retries', [
            'title' => $this->title,
            'interests' => $this->interests,
            'error' => $safeError,
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Compute a unique deduplication key for this notification.
     */
    protected function resolveDedupKey(): string
    {
        if (! empty($this->deduplicationKey)) {
            return 'push_dedup_' . $this->deduplicationKey;
        }

        $sortedInterests = $this->interests;
        sort($sortedInterests);

        return 'push_dedup_' . sha1($this->title . '|' . $this->body . '|' . implode(',', $sortedInterests));
    }

    /**
     * Strip any sensitive secrets or server keys from error messages before logging.
     */
    protected function sanitizeError(?string $message): string
    {
        if (empty($message)) {
            return 'Unknown push notification error';
        }

        $secret = config('services.pusher.server_key');
        if (! empty($secret)) {
            $message = str_replace($secret, '***REDACTED***', $message);
        }

        $instanceId = config('services.pusher.instance_id');
        if (! empty($instanceId)) {
            $message = str_replace($instanceId, '***REDACTED_INSTANCE***', $message);
        }

        return substr($message, 0, 500);
    }
}
