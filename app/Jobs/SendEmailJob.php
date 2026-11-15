<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Send a pre-rendered email. Runs on the `emails` queue so the
 * application never blocks on SMTP during an HTTP request.
 *
 * If the admin has configured dynamic SMTP settings in the database,
 * a dedicated Symfony SmtpTransport is built from those values — the
 * .env credentials are never touched. Otherwise the application's
 * default mailer is used.
 *
 * Retries are handled by the queue worker; failed jobs end up in
 * failed_jobs for manual re-dispatch. After the final failure a
 * system-failure alert is sent to the admin recipients.
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $emailLogId,
        public string $subject,
        public string $body,
        public string $toEmail,
        public array $context = [],
    ) {
    }

    public function handle(): void
    {
        $log = EmailLog::findOrFail($this->emailLogId);

        if (in_array($log->status, ['sent', 'failed'], true) && ($log->retry_count ?? 0) >= $this->tries) {
            return; // retries already exhausted
        }

        $log->update([
            'status'      => 'processing',
            'retry_count' => ($log->retry_count ?? 0) + 1,
        ]);

        try {
            $this->send($log);

            $log->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $safeError = (new EmailService())->sanitizeError($e->getMessage());

            $log->update([
                'status'        => 'failed',
                'error_summary' => $safeError,
            ]);

            // Alert the admin only on the final failure (non-blocking).
            if ($this->attempts() >= $this->tries) {
                try {
                    app(EmailService::class)->forceSend('admin.system.failure', [
                        'error'     => $safeError,
                        'recipient' => $this->toEmail,
                        'subject'   => $this->subject,
                        'log_id'    => $log->id,
                    ], false);
                } catch (\Throwable) {
                    // The alert path must never mask the original failure.
                }
            }

            throw $e;
        }
    }

    protected function send(EmailLog $log): void
    {
        $payload = EmailSetting::payload();

        $message = new Email();
        $message
            ->subject($this->subject)
            ->to($this->toEmail)
            ->html($this->body);

        $fromAddress = (string) ($payload['from_email'] ?? config('mail.from.address'));
        $fromName = (string) ($payload['from_name'] ?? config('mail.from.name', config('app.name')));
        $message->from(new Address($fromAddress, $fromName));

        if (!empty($payload['smtp_host'])) {
            // Build a dedicated transport from the admin's stored settings
            // (reuses the same factory-based builder as the service).
            $transport = (new EmailService())->buildSmtpTransport();
            $sent = (new SymfonyMailer($transport))->send($message);

            // Verify the transport actually reported success (e.g. in
            // transport tests where no real delivery happened).
            if (! $sent instanceof SentMessage) {
                throw new \RuntimeException('Mail transport did not send the message.');
            }
        } else {
            // No dynamic SMTP — use the application default mailer (.env).
            // Re-render through the app's Mailer so the transport falls
            // back correctly when no admin settings are configured.
            $fallbackMessage = new \Symfony\Component\Mime\Email();
            $fallbackMessage
                ->subject($this->subject)
                ->to($this->toEmail)
                ->html($this->body)
                ->from($message->getFrom()[0] ?? config('mail.from.address'));

            $sent = Mail::mailer()->getSymfonyTransport()->send($fallbackMessage);

            if (! $sent instanceof SentMessage) {
                throw new \RuntimeException('Mail transport did not send the message.');
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $log = EmailLog::find($this->emailLogId);
        if ($log) {
            $log->update([
                'status'        => 'failed',
                'error_summary' => (new EmailService())->sanitizeError($exception->getMessage()),
            ]);
        }
    }
}
