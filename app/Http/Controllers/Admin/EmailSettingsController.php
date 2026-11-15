<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailNotificationPreference;
use App\Models\EmailTemplate;
use App\Models\EmailSetting;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

/**
 * Full email management panel for the Super Admin:
 *  - SMTP configuration & test
 *  - Notification event toggle
 *  - Template CRUD
 *  - Email log viewer
 */
class EmailSettingsController extends Controller
{
    public function __construct(protected EmailService $emailService)
    {
    }

    /**
     * GET /admin/email
     * Consolidated email management page with tab sections.
     */
    public function index(): View
    {
        // Group templates by the category of the event that uses them
        // (templates themselves carry no category column).
        $templateCategories = collect(EmailService::EVENTS)
            ->mapWithKeys(fn (array $event, string $key) => [$event['template_key'] => $event['category']])
            ->only(EmailTemplate::pluck('key')->all())
            ->toArray();

        $templates = EmailTemplate::orderBy('name')->get()
            ->groupBy(fn (EmailTemplate $t) => $templateCategories[$t->key] ?? 'other')
            ->map
            ->sortBy('name');

        return view('admin.email.index', [
            'smtpPayload'  => EmailSetting::payload(),
            'events'       => collect(EmailService::EVENTS),
            'preferences'  => EmailNotificationPreference::all()->keyBy('event_key'),
            'templates'    => $templates,
            'logs'         => EmailLog::orderByDesc('id')->paginate(50)->withQueryString(),
            'availableVariables' => EmailService::ALLOWED_VARIABLES,
            'reminderConfig' => \App\Models\Setting::emailReminders(),
        ]);
    }

    /**
     * PUT /admin/email/reminders — Section 6: expiry reminder schedule.
     */
    public function updateReminders(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reminders_enabled'   => ['sometimes', 'boolean'],
            'reminder_days'       => ['array', 'max:10'],
            'reminder_days.*'     => ['integer', 'between:1,90', 'distinct'],
            'reminder_time'       => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'notify_customer'     => ['sometimes', 'boolean'],
            'notify_team'         => ['sometimes', 'boolean'],
        ]);

        $current = \App\Models\Setting::emailReminders();

        \App\Models\Setting::setEmailReminders(
            enabled: $validated['reminders_enabled'] ?? $current['enabled'],
            days: isset($validated['reminder_days']) ? $validated['reminder_days'] : null,
            time: $validated['reminder_time'],
            notifyCustomer: $validated['notify_customer'] ?? $current['notify_customer'],
            notifyTeam: $validated['notify_team'] ?? $current['notify_team'],
        );

        return redirect()->route('admin.email.index')->with('status', 'Expiry reminder settings saved.');
    }

    // ------------------------------------------------------------------
    // SMTP
    // ------------------------------------------------------------------

    /**
     * PUT /admin/email/smtp
     */
    public function updateSmtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'smtp_host'           => ['required', 'string', 'max:255'],
            'smtp_port'           => ['required', 'integer', 'between:1,65535'],
            'smtp_username'       => ['nullable', 'string', 'max:255'],
            'smtp_password'       => ['nullable', 'string', 'max:255'],
            'smtp_encryption'     => ['required', 'in:tls,ssl,none'],
            'from_email'          => ['required', 'email', 'max:255'],
            'from_name'           => ['required', 'string', 'max:255'],
            'connection_timeout'  => ['nullable', 'integer', 'between:1,120'],
        ]);

        EmailSetting::savePayload($validated);

        return redirect()->route('admin.email.index')->with('status', 'SMTP settings saved.');
    }

    /**
     * POST /admin/email/test
     */
    public function testEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $result = $this->emailService->sendTest($request->input('test_email'));

        if ($result['success']) {
            return redirect()->route('admin.email.index')
                ->with('status', 'Test email sent successfully. Check the inbox.');
        }

        return redirect()->route('admin.email.index')
            ->with('error', 'Test failed: '.$result['error']);
    }

    // ------------------------------------------------------------------
    // Notification event toggles
    // ------------------------------------------------------------------

    /**
     * PUT /admin/email/events
     */
    public function updateEvents(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled_events' => ['array'],
            'enabled_events.*' => ['string'],
        ]);

        $enabled = array_map('strval', $validated['enabled_events'] ?? []);

        EmailNotificationPreference::query()->update(['is_enabled' => false]);

        if (!empty($enabled)) {
            EmailNotificationPreference::query()
                ->whereIn('event_key', $enabled)
                ->update(['is_enabled' => true]);
        }

        return redirect()->route('admin.email.index')->with('status', 'Notification events updated.');
    }

    // ------------------------------------------------------------------
    // Template CRUD
    // ------------------------------------------------------------------

    /**
     * PUT /admin/email/templates/{template}
     */
    public function updateTemplate(Request $request, EmailTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:150'],
            'subject'   => ['required', 'string', 'max:500'],
            'body'      => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $template->update($validated);

        return redirect()->route('admin.email.index')
            ->with('status', 'Template "'.$template->name.'" updated.');
    }

    /**
     * POST /admin/email/templates/{template}/toggle
     */
    public function toggleTemplate(Request $request, EmailTemplate $template): RedirectResponse
    {
        $template->update(['is_active' => !$template->is_active]);

        return redirect()->route('admin.email.index')
            ->with('status', 'Template '.($template->is_active ? 'enabled' : 'disabled').'.');
    }

    // ------------------------------------------------------------------
    // Email logs
    // ------------------------------------------------------------------

    /**
     * DELETE /admin/email/logs
     */
    public function clearLogs(Request $request): RedirectResponse
    {
        EmailLog::where('created_at', '<', now()->subDays(90))->delete();

        return redirect()->route('admin.email.index')
            ->with('status', 'Logs older than 90 days have been cleared.');
    }

    /**
     * POST /admin/email/logs/{log}/resend
     */
    public function resendLog(EmailLog $log): RedirectResponse
    {
        if ($log->status === 'sent') {
            return redirect()->route('admin.email.index')
                ->with('error', 'This email has already been sent successfully.');
        }

        $template = EmailTemplate::where('key', $log->template_key)->first();

        if (!$template) {
            return redirect()->route('admin.email.index')
                ->with('error', 'Template not found for this log entry.');
        }

        $log->update([
            'status'  => 'pending',
            'sent_at' => null,
        ]);

        SendEmailJob::dispatch(
            $log->id,
            $log->subject,
            strip_tags($log->body_preview ?? ''),
            $log->recipient_email,
            []
        )->onQueue('emails');

        return redirect()->route('admin.email.index')
            ->with('status', 'Email queued for resend.');
    }
}
