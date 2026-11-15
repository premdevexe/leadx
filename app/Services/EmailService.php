<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\EmailNotificationPreference;
use App\Models\EmailTemplate;
use App\Models\EmailSetting;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;

/**
 * Central email dispatch service.
 *
 * Responsibilities:
 *  - resolve and apply dynamic SMTP configuration
 *  - look up enabled notification preferences
 *  - select and render the correct email template
 *  - queue the actual send via SendEmailJob
 *  - create an EmailLog row before the job runs (so every attempted
 *    send is always accounted for, even if the job fails)
 */
class EmailService
{
    /**
     * Allowed dynamic placeholders — any variable outside this set is
     * stripped from the rendered template to prevent injection.
     */
    public const ALLOWED_VARIABLES = [
        // Recipient
        'customer_name',
        'customer_email',
        'team_name',
        'team_code',
        'employee_name',
        'admin_name',
        // Order
        'order_id',
        'order_number',
        'product_name',
        'product_price',
        'product_duration',
        'closing_value',
        'amount_received',
        'payment_method',
        'payment_status',
        // Subscription
        'activation_date',
        'expiry_date',
        'renewal_date',
        'billing_cycle',
        'plan_name',
        // Contextual
        'days_before',
        'app_name',
        'support_email',
        'api_key',
        // Support follow-up
        'support_topic',
        'support_outcome',
        'support_note',
    ];

    /**
     * Event key → email event metadata. Used to seed preferences and
     * drive the dispatcher's route() method.
     */
    public const EVENTS = [
        // Customer events
        'customer.welcome'          => ['label' => 'Welcome Email',                  'category' => 'customer',  'template_key' => 'customer.welcome'],
        'customer.registered'       => ['label' => 'Registration Confirmation',     'category' => 'customer',  'template_key' => 'customer.registered'],
        'order.created'             => ['label' => 'Order Created',                 'category' => 'customer',  'template_key' => 'order.confirmation'],
        'payment.pending'           => ['label' => 'Payment Pending',               'category' => 'customer',  'template_key' => 'payment.pending'],
        'payment.verified'          => ['label' => 'Payment Verified',              'category' => 'customer',  'template_key' => 'payment.verified'],
        'subscription.activated'    => ['label' => 'Subscription Activated',        'category' => 'customer',  'template_key' => 'subscription.activated'],
        'order.api_key_delivered'   => ['label' => 'API Key Delivery',              'category' => 'customer',  'template_key' => 'order.api_key_delivered'],
        'subscription.expiring'     => ['label' => 'Subscription Expiring',         'category' => 'customer',  'template_key' => 'subscription.expiring'],
        'subscription.expired'      => ['label' => 'Subscription Expired',          'category' => 'customer',  'template_key' => 'subscription.expired'],
        'subscription.renewed'      => ['label' => 'Renewal Confirmation',          'category' => 'customer',  'template_key' => 'subscription.renewed'],
        'payment.receipt'           => ['label' => 'Payment Receipt',               'category' => 'customer',  'template_key' => 'payment.receipt'],
        'support.response'          => ['label' => 'Support Response',              'category' => 'customer',  'template_key' => 'support.response'],

        // Team events
        'team.order_created'        => ['label' => 'New Team Order',                'category' => 'team',      'template_key' => 'team.order_created'],
        'team.employee_order'       => ['label' => 'Employee Order Created',        'category' => 'team',      'template_key' => 'team.order_created'],
        'team.payment_status'       => ['label' => 'Payment Status Updated',        'category' => 'team',      'template_key' => 'team.payment_status_updated'],
        'team.subscription.expiring'=> ['label' => 'Subscription Expiring (Team)',   'category' => 'team',      'template_key' => 'subscription.expiring'],
        'team.renewal.followup'     => ['label' => 'Customer Renewal Follow-up',    'category' => 'team',      'template_key' => 'team.renewal_followup'],

        // Employee events
        'employee.order_created'    => ['label' => 'Order Created',                 'category' => 'employee',  'template_key' => 'employee.order_created'],
        'employee.assigned_customer'=> ['label' => 'Assigned Customer',             'category' => 'employee',  'template_key' => 'employee.assigned_customer'],
        'employee.payment_update'   => ['label' => 'Payment Update',               'category' => 'employee',  'template_key' => 'employee.payment_update'],
        'employee.followup'         => ['label' => 'Follow-up Reminder',            'category' => 'employee',  'template_key' => 'employee.followup'],

        // Admin events
        'admin.customer_registered' => ['label' => 'New Customer Registration',     'category' => 'admin',     'template_key' => 'admin.new_customer'],
        'admin.new_order'           => ['label' => 'New Order',                     'category' => 'admin',     'template_key' => 'admin.new_order'],
        'admin.payment_verify'      => ['label' => 'Payment Verification Required', 'category' => 'admin',     'template_key' => 'admin.payment_verify'],
        'admin.payment.failed'      => ['label' => 'Failed Payment',                'category' => 'admin',     'template_key' => 'admin.payment_failed'],
        'admin.subscription.expiry' => ['label' => 'Subscription Expiry Alert',     'category' => 'admin',     'template_key' => 'admin.subscription_expiry'],
        'admin.system.failure'      => ['label' => 'System Email Failure',          'category' => 'admin',     'template_key' => 'admin.system_failure'],
    ];

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------

    /**
     * Enqueue an email for the given event, respecting notification
     * preferences. Returns the created EmailLog or null if the event
     * is disabled or no template is available.
     */
    public function dispatch(string $eventKey, array $context = []): ?EmailLog
    {
        $prefs = EmailNotificationPreference::where('event_key', $eventKey)
            ->where('is_enabled', true)
            ->first();

        if (!$prefs) {
            return null; // event not configured or disabled
        }

        $template = EmailTemplate::where('key', $prefs->template_key)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return null;
        }

        $recipients = $this->resolveRecipients($eventKey, $context);

        $first = null;

        foreach ($recipients as $recipient) {
            $log = $this->enqueue($recipient, $eventKey, $template, $context);

            // The 24h dedup guard returns null when this exact combination
            // was already enqueued (retried webhook / repeated job).
            $first ??= $log;
        }

        return $first;
    }

    /**
     * Queue a single email send. Creates the EmailLog row first, then
     * dispatches the SendEmailJob so failures are always traceable.
     *
     * Duplicate guard: if the same (event, recipient, order) combination
     * was already logged within the last 24h, the email is skipped. This
     * makes repeated webhook deliveries and retried jobs idempotent.
     *
     * @param  bool  $dispatchJob  When false (e.g. in tests), only the
     *                              log row is created and no job is sent.
     */
    public function enqueue(
        string $toEmail,
        string $eventKey,
        EmailTemplate $template,
        array $context = [],
        bool $dispatchJob = true,
    ): ?EmailLog {
        // Duplicate prevention (per event + recipient + order within 24h).
        $recent = EmailLog::query()
            ->where('event_key', $eventKey)
            ->where('recipient_email', $toEmail)
            ->where('created_at', '>', now()->subHours(24))
            ->whereIn('status', ['pending', 'processing', 'sent']);

        if ($context['order_id'] ?? null) {
            $recent->where('related_order_id', $context['order_id']);
        }

        if ($recent->exists()) {
            return null;
        }

        $subject = $this->render($template->subject, $context);
        $body    = $this->render($template->body, $context);

        // Secret hygiene: the stored body preview must never contain the
        // customer's API key — redact it before it reaches the log table.
        $preview = strip_tags($body);
        if (isset($context['api_key']) && $context['api_key'] !== '') {
            $preview = str_replace((string) $context['api_key'], '••••••••', $preview);
        }

        $log = EmailLog::create([
            'recipient_email'         => $toEmail,
            'event_key'               => $eventKey,
            'template_key'            => $template->key,
            'subject'                 => $subject,
            'body_preview'            => mb_substr($preview, 0, 500),
            'related_order_id'        => $context['order_id'] ?? null,
            'related_subscription_id' => $context['subscription_id'] ?? null,
            'related_customer_id'     => $context['customer_id'] ?? null,
            'status'                  => 'pending',
            'retry_count'             => 0,
        ]);

        if ($dispatchJob) {
            SendEmailJob::dispatch($log->id, $subject, $body, $toEmail, $context)->onQueue('emails');
        }

        return $log;
    }

    /**
     * Force-send an email regardless of notification preferences.
     * Used for system alerts (e.g. admin.system.failure) which must
     * always be delivered.
     */
    public function forceSend(string $eventKey, array $context = [], bool $dispatchJob = true): ?EmailLog
    {
        $templateKey = self::EVENTS[$eventKey]['template_key']
            ?? $context['template_key']
            ?? str_replace('.', '_', $eventKey);

        $template = EmailTemplate::where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            // Build a minimal fallback template and persist it on the fly
            // so the system failure alert is never silently dropped.
            $template = EmailTemplate::updateOrCreate(
                ['key' => $templateKey],
                [
                    'name'      => ucwords(str_replace(['_', '.'], ' ', $eventKey)),
                    'subject'   => $context['subject'] ?? 'System Notification',
                    'body'      => '<p>{{message}}</p>',
                    'is_active' => true,
                    'is_default'=> false,
                ]
            );
        }

        $recipients = $this->resolveRecipients($eventKey, $context);

        if (empty($recipients)) {
            return null;
        }

        return $this->enqueue($recipients[0], $eventKey, $template, $context, $dispatchJob);
    }

    // ------------------------------------------------------------------
    // Test email
    // ------------------------------------------------------------------

    /**
     * Send a test email using the admin-configured SMTP settings.
     *
     * This builds a fresh Symfony SMTP transport from the stored settings
     * so the test always exercises the same path a real email would take.
     * SMTP acceptance (returning true) does not guarantee inbox delivery.
     *
     * @return array{success: bool, error: string|null}
     */
    public function sendTest(string $recipientEmail): array
    {
        $payload = EmailSetting::payload();

        if (empty($payload['smtp_host'])) {
            return ['success' => false, 'error' => 'No SMTP host configured. Please configure SMTP settings first.'];
        }

        $subject = '[LeadX] SMTP Configuration Test';
        $body    = '<p>This is a test email sent from LeadX. If you received this, your SMTP configuration is correct.</p>';

        $log = EmailLog::create([
            'recipient_email'  => $recipientEmail,
            'event_key'        => 'admin.smtp_test',
            'template_key'     => 'system.test',
            'subject'          => $subject,
            'body_preview'     => mb_substr(strip_tags($body), 0, 500),
            'status'          => 'processing',
        ]);

        try {
            $transport = $this->buildSmtpTransport();

            $message = new \Symfony\Component\Mime\Email();
            $message
                ->subject($subject)
                ->from(new \Symfony\Component\Mime\Address(
                    (string) ($payload['from_email'] ?? config('mail.from.address')),
                    (string) ($payload['from_name'] ?? config('mail.from.name', config('app.name')))
                ))
                ->to($recipientEmail)
                ->html($body);

            $sent = (new \Symfony\Component\Mailer\Mailer($transport))->send($message);

            if (! $sent instanceof \Symfony\Component\Mailer\SentMessage) {
                throw new \RuntimeException('Mail transport did not send the message.');
            }

            $log->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            $safeError = $this->sanitizeError($e->getMessage());

            $log->update([
                'status'        => 'failed',
                'error_summary' => $safeError,
                'sent_at'       => now(),
            ]);

            return ['success' => false, 'error' => $safeError];
        }
    }

    /**
     * Build a Symfony SMTP transport from the stored admin settings using
     * the same factory Laravel uses (EsmtpTransportFactory) so the correct
     * stream (STARTTLS / implicit-SSL / plain) is wired for the encryption.
     */
    public function buildSmtpTransport(): \Symfony\Component\Mailer\Transport\TransportInterface
    {
        $payload = EmailSetting::payload();

        $encryption = $payload['smtp_encryption'] ?? 'tls';

        // Map the admin's encryption choice onto Symfony's scheme + auto_tls:
        //   ssl  -> implicit TLS (smtps, usually :465)
        //   tls  -> STARTTLS upgrade (smtp + auto_tls)
        //   none -> plain, no TLS
        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
        $autoTls = match ($encryption) {
            'ssl'  => false,
            'none' => false,
            default => true,
        };

        $dsn = new \Symfony\Component\Mailer\Transport\Dsn(
            scheme: $scheme,
            host: (string) $payload['smtp_host'],
            user: $payload['smtp_username'] ?? null,
            password: EmailSetting::decryptedPassword(),
            port: isset($payload['smtp_port']) ? (int) $payload['smtp_port'] : null,
            options: ['auto_tls' => $autoTls ? 'true' : 'false'],
        );

        $factory = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory();
        $transport = $factory->create($dsn);

        // Connection timeout applies to the socket stream when present.
        $stream = $transport->getStream();
        if ($stream instanceof \Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream) {
            $stream->setTimeout((int) ($payload['connection_timeout'] ?? 10));
        }

        return $transport;
    }

    // ------------------------------------------------------------------
    // Recipients
    // ------------------------------------------------------------------

    /**
     * Build the email recipient list for an event. Order of precedence:
     *  1. Explicit `to_email` passed in $context
     *  2. Customer / Team / Employee / Admin email resolvable from the
     *     $context relationships
     *  3. Fallback to site admin addresses (never external by default).
     */
    private function resolveRecipients(string $eventKey, array $context): array
    {
        if (!empty($context['to_email'])) {
            return [(string) $context['to_email']];
        }

        return match ($eventKey) {
            // Customer-facing
            'order.created',
            'payment.pending',
            'payment.verified',
            'order.api_key_delivered',
            'subscription.activated',
            'subscription.expiring',
            'subscription.expired',
            'subscription.renewed',
            'payment.receipt',
            'support.response',
            'customer.welcome',
            'customer.registered'
                => $this->customerRecipient($context),

            // Team-facing
            'team.order_created',
            'team.employee_order',
            'team.payment_status',
            'team.subscription.expiring',
            'team.renewal.followup'
                => $this->teamRecipient($context),

            // Employee-facing
            'employee.order_created',
            'employee.assigned_customer',
            'employee.payment_update',
            'employee.followup'
                => $this->employeeRecipient($context),

            // Admin-facing
            default => $this->adminRecipients(),
        };
    }

    private function customerRecipient(array $context): array
    {
        if (!empty($context['customer_email'])) {
            return [(string) $context['customer_email']];
        }

        $order = $context['order'] ?? null;
        if ($order instanceof SalesOrder && !empty($order->customer_email)) {
            return [$order->customer_email];
        }

        $sub = $context['subscription'] ?? null;
        if ($sub instanceof Subscription && $sub->customer && $sub->customer->email) {
            return [$sub->customer->email];
        }

        $customerId = $context['customer_id'] ?? null;
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer && $customer->email) {
                return [$customer->email];
            }
        }

        return [];
    }

    /**
     * Teams are addressed through their active members (users table),
     * matching the existing team-notification pattern. The order's
     * assigned employee is always included when set.
     */
    private function teamRecipient(array $context): array
    {
        $order = $context['order'] ?? null;
        $team = $context['team'] ?? $order?->team;

        if (!$team) {
            $sub = $context['subscription'] ?? null;
            $team = $sub?->team;
        }

        if (!$team) {
            return [];
        }

        $emails = $team->activeMembers()->pluck('email')->filter()->all();

        if ($order && $order->assignedEmployee && $order->assignedEmployee->email) {
            $emails[] = $order->assignedEmployee->email;
        }

        return array_values(array_unique($emails));
    }

    private function employeeRecipient(array $context): array
    {
        $employee = $context['employee'] ?? $context['user'] ?? null;

        if ($employee instanceof User && $employee->email) {
            return [$employee->email];
        }

        $order = $context['order'] ?? null;
        if ($order && $order->assignedEmployee && $order->assignedEmployee->email) {
            return [$order->assignedEmployee->email];
        }

        return [];
    }

    private function adminRecipients(): array
    {
        return Admin::pluck('email')->filter()->unique()->values()->all();
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------

    /**
     * Render a template string with {{variable}} substitutions. Any
     * placeholder not resolved from the context is silently removed
     * (not leaked to the rendered output).
     */
    public function render(string $template, array $context = []): string
    {
        $vars = $this->allContextVars($context);

        // Replace {{var}} placeholders.
        $rendered = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/',
            function (array $m) use ($vars) {
                $key = $m[1];
                return $vars[$key] ?? ''; // unknown vars become empty
            },
            $template
        );

        // Strip any remaining {{…}} to prevent leakage.
        return (string) preg_replace('/\{\{.*?\}\}/', '', $rendered);
    }

    /**
     * Build a sanitized variable map from $context. Only keys that are
     * explicitly in ALLOWED_VARIABLES or come from trusted entity
     * unwrapping are included. This prevents sensitive fields like
     * smtp_password or API keys from leaking into template output.
     */
    private function allContextVars(array $context): array
    {
        $vars = [
            'app_name'       => config('app.name', 'LeadX'),
            'support_email'  => config('mail.from.address', ''),
        ];

        // Flatten explicit context values that are in the allowed list.
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                if (in_array($key, static::ALLOWED_VARIABLES, true)) {
                    $vars[$key] = (string) $value;
                }
            }
        }

        // Unwrap common entity fields (trusted and explicitly authorized).
        if (isset($context['order']) && $context['order'] instanceof SalesOrder) {
            $o = $context['order'];
            $vars = array_merge($vars, [
                'order_id'         => $o->id,
                'order_number'     => $o->order_number ?? '#'.$o->id,
                'product_name'     => $o->product_name_snapshot ?? $o->product?->name ?? '—',
                'product_price'    => $o->product_price_snapshot ?? '—',
                'product_duration' => $o->product_duration_snapshot ?? $o->duration ?? '—',
                'closing_value'    => number_format((float) $o->closing_value, 2),
                'amount_received'  => number_format((float) $o->amount_received, 2),
                'payment_method'   => $o->payment_method ?? '—',
                'payment_status'   => $o->payment_status ?? '—',
            ]);
        }

        if (isset($context['subscription']) && $context['subscription'] instanceof Subscription) {
            $s = $context['subscription'];
            $vars = array_merge($vars, [
                'activation_date'  => $s->started_at?->format('M d, Y') ?? '—',
                'expiry_date'      => $s->expires_at?->format('M d, Y') ?? '—',
                'renewal_date'     => $s->renewal_date?->format('M d, Y') ?? '—',
                'billing_cycle'    => $s->billing_cycle ?? '—',
                'plan_name'        => $s->plan_snapshot ?? '—',
            ]);
        }

        if (isset($context['team']) && $context['team'] instanceof Team) {
            $vars['team_name'] = $context['team']->team_name ?? '—';
            $vars['team_code'] = $context['team']->team_code ?? '—';
        }

        if (isset($context['customer']) && $context['customer'] instanceof Customer) {
            $vars['customer_name']  = $context['customer']->name ?? '—';
            $vars['customer_email'] = $context['customer']->email ?? '—';
        }

        if (isset($context['employee']) && $context['employee'] instanceof User) {
            $vars['employee_name'] = $context['employee']->name ?? $context['employee']->username ?? '—';
        }

        if (isset($context['admin']) && $context['admin'] instanceof Admin) {
            $vars['admin_name'] = $context['admin']->name ?? $context['admin']->username ?? '—';
        }

        if (isset($context['days_before'])) {
            $vars['days_before'] = (int) $context['days_before'];
        }

        // API key delivery workflow explicitly allowed.
        if (isset($context['api_key']) && in_array('api_key', static::ALLOWED_VARIABLES, true)) {
            $vars['api_key'] = (string) $context['api_key'];
        }

        return $vars;
    }

    // ------------------------------------------------------------------
    // Error handling
    // ------------------------------------------------------------------

    /**
     * Strip potential SMTP credentials from an error string.
     * Never includes raw credentials in the returned message.
     */
    public function sanitizeError(string $error): string
    {
        // Remove anything that looks like a username/password pair in
        // the error text (common in SwiftMailer / SMTP error messages).
        $error = preg_replace('/Could not authenticate[^:]*/', 'Could not authenticate (credentials rejected)', $error);
        $error = preg_replace('/Invalid credentials[^:]*/', 'Invalid credentials', $error);
        $error = preg_replace('/stream_socket_client\(\).[^:]+/', 'Connection to mail server failed', $error);

        // Truncate to a safe length.
        return mb_substr((string) $error, 0, 500);
    }

    /**
     * Return whether a "system failure" admin alert can be sent.
     */
    public static function canSendSystemAlert(): bool
    {
        $payload = EmailSetting::payload();

        return !empty($payload['smtp_host']);
    }
}
