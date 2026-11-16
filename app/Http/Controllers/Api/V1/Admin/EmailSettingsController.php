<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailNotificationPreference;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin email settings — API mirror of the web /admin/email panel:
 * SMTP config (password masked), event toggles, templates, logs and
 * resend. The SMTP payload serializer already strips smtp_password, so
 * no credential crosses the wire in a read.
 */
class EmailSettingsController extends Controller
{
    public function __construct(protected EmailService $emailService)
    {
    }

    /**
     * GET /api/v1/admin/email
     */
    public function index(): JsonResponse
    {
        $templates = EmailTemplate::orderBy('name')->get();

        return response()->json([
            'smtp' => EmailSetting::payload(), // smtp_password is stripped
            'events' => EmailService::EVENTS,
            'preferences' => EmailNotificationPreference::all()->keyBy('event_key'),
            'templates' => $templates,
            'reminder_config' => \App\Models\Setting::emailReminders(),
            'available_variables' => EmailService::ALLOWED_VARIABLES,
        ]);
    }

    /**
     * PUT /api/v1/admin/email/reminders
     */
    public function updateReminders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reminders_enabled' => ['sometimes', 'boolean'],
            'reminder_days' => ['array', 'max:10'],
            'reminder_days.*' => ['integer', 'between:1,90', 'distinct'],
            'reminder_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'notify_customer' => ['sometimes', 'boolean'],
            'notify_team' => ['sometimes', 'boolean'],
        ]);

        $current = \App\Models\Setting::emailReminders();

        \App\Models\Setting::setEmailReminders(
            enabled: $validated['reminders_enabled'] ?? $current['enabled'],
            days: isset($validated['reminder_days']) ? $validated['reminder_days'] : null,
            time: $validated['reminder_time'],
            notifyCustomer: $validated['notify_customer'] ?? $current['notify_customer'],
            notifyTeam: $validated['notify_team'] ?? $current['notify_team'],
        );

        return response()->json([
            'message' => 'Expiry reminder settings saved.',
            'reminder_config' => \App\Models\Setting::emailReminders(),
        ]);
    }

    /**
     * PUT /api/v1/admin/email/smtp
     *
     * A blank password means "keep the existing one" (same rule as the
     * web panel); the password is encrypted individually at rest.
     */
    public function updateSmtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['required', 'in:tls,ssl,none'],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'connection_timeout' => ['nullable', 'integer', 'between:1,120'],
        ]);

        EmailSetting::savePayload($validated);

        return response()->json(['message' => 'SMTP settings saved.', 'smtp' => EmailSetting::payload()]);
    }

    /**
     * POST /api/v1/admin/email/test
     */
    public function testEmail(Request $request): JsonResponse
    {
        $request->validate(['test_email' => ['required', 'email']]);

        $result = $this->emailService->sendTest($request->input('test_email'));

        if (! $result['success']) {
            throw ValidationException::withMessages(['test_email' => 'Test failed: '.$result['error']]);
        }

        return response()->json(['message' => 'Test email sent successfully.']);
    }

    /**
     * PUT /api/v1/admin/email/events
     */
    public function updateEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled_events' => ['array'],
            'enabled_events.*' => ['string'],
        ]);

        $enabled = array_map('strval', $validated['enabled_events'] ?? []);

        EmailNotificationPreference::query()->update(['is_enabled' => false]);

        if ($enabled !== []) {
            EmailNotificationPreference::query()
                ->whereIn('event_key', $enabled)
                ->update(['is_enabled' => true]);
        }

        return response()->json([
            'message' => 'Notification events updated.',
            'preferences' => EmailNotificationPreference::all()->keyBy('event_key'),
        ]);
    }

    /**
     * PUT /api/v1/admin/email/templates/{template}
     */
    public function updateTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $template->update($validated);

        return response()->json(['message' => "Template \"{$template->name}\" updated.", 'template' => $template->fresh()]);
    }

    /**
     * POST /api/v1/admin/email/templates/{template}/toggle
     */
    public function toggleTemplate(EmailTemplate $template): JsonResponse
    {
        $template->update(['is_active' => ! $template->is_active]);

        return response()->json([
            'message' => 'Template '.($template->is_active ? 'enabled' : 'disabled').'.',
            'template' => $template->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/admin/email/logs
     */
    public function clearLogs(): JsonResponse
    {
        $deleted = EmailLog::where('created_at', '<', now()->subDays(90))->delete();

        return response()->json([
            'message' => "Logs older than 90 days cleared ({$deleted} record(s)).",
            'deleted' => $deleted,
        ]);
    }

    /**
     * POST /api/v1/admin/email/logs/{log}/resend
     */
    public function resendLog(EmailLog $log): JsonResponse
    {
        if ($log->status === 'sent') {
            throw ValidationException::withMessages(['log' => 'This email has already been sent successfully.']);
        }

        $template = EmailTemplate::where('key', $log->template_key)->first();

        if (! $template) {
            throw ValidationException::withMessages(['log' => 'Template not found for this log entry.']);
        }

        $log->update(['status' => 'pending', 'sent_at' => null]);

        SendEmailJob::dispatch(
            $log->id,
            $log->subject,
            strip_tags($log->body_preview ?? ''),
            $log->recipient_email,
            []
        )->onQueue('emails');

        return response()->json(['message' => 'Email queued for resend.', 'log' => $log->fresh()]);
    }
}
