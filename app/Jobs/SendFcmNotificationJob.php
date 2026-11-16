<?php

namespace App\Jobs;

use App\Services\FcmService;
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
 * Asynchronously delivers a push notification to an employee's registered
 * mobile devices over Firebase Cloud Messaging.
 *
 * Mirrors {@see SendWebPushNotificationJob}: it is afterCommit so a rolled-back
 * transaction never triggers a push, and it uses a 10-minute dedup window so a
 * single business event is delivered at most once even if both the web-push
 * (Beams) and FCM paths fire for the same event.
 *
 * Only the dedup key, title, and target employee are carried — the FCM device
 * tokens are resolved at execution time against the active registrations.
 */
class SendFcmNotificationJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public int $tries = 3;

    /** @var int */
    public int $maxExceptions = 3;

    /** @var int */
    public int $timeout = 20;

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    /**
     * @param  int  $employeeId  target employee (owner of the tokens)
     * @param  string[]  $data  navigation keys for the FCM data payload
     */
    public function __construct(
        public int $employeeId,
        public string $title,
        public string $body,
        public array $data = [],
        public ?string $deduplicationKey = null,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit = true;
    }

    public function handle(FcmService $fcm): void
    {
        if (! $fcm->isConfigured()) {
            // No service account configured -> FCM is a no-op everywhere. Safe.
            return;
        }

        $dedupKey = $this->resolveDedupKey();

        if (Cache::has($dedupKey)) {
            Log::info('FCM notification skipped: already delivered.', [
                'employee_id' => $this->employeeId,
                'dedup_key' => $dedupKey,
            ]);

            return;
        }

        $tokens = \App\Models\EmployeeDeviceToken::query()
            ->where('employee_id', $this->employeeId)
            ->where('is_active', true)
            ->pluck('fcm_token');

        if ($tokens->isEmpty()) {
            // No registered devices: nothing to do, but mark deduped so we do
            // not re-queue endlessly.
            Cache::put($dedupKey, true, now()->addMinutes(10));

            return;
        }

        try {
            $result = $fcm->send(
                $tokens->all(),
                $this->title,
                $this->body,
                $this->data,
                $this->deduplicationKey
            );
        } catch (Throwable $e) {
            Log::warning('FCM push job encountered an exception', [
                'employee_id' => $this->employeeId,
                'error' => substr($e->getMessage(), 0, 300),
                'attempt' => $this->attempts(),
            ]);

            throw $e; // let the queue retry per tries/backoff
        }

        if ($result['failed'] > 0) {
            // Transient failures: rethrow so the queue retries once more.
            throw new \RuntimeException('FCM push had transient failures for employee '.$this->employeeId);
        }

        Cache::put($dedupKey, true, now()->addMinutes(10));

        Log::debug('FCM push notification delivered', [
            'employee_id' => $this->employeeId,
            'sent' => $result['sent'],
            'removed' => $result['removed'],
            'title' => $this->title,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('FCM push permanently failed after retries', [
            'employee_id' => $this->employeeId,
            'title' => $this->title,
            'error' => $exception ? substr($exception->getMessage(), 0, 300) : null,
            'attempts' => $this->attempts(),
        ]);
    }

    protected function resolveDedupKey(): string
    {
        if (! empty($this->deduplicationKey)) {
            return 'fcm_dedup_'.$this->deduplicationKey;
        }

        $dataHash = sha1($this->title.'|'.$this->body.'|'.json_encode($this->data));

        return 'fcm_dedup_emp_'.$this->employeeId.'_'.$dataHash;
    }
}
