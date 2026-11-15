<?php

use App\Console\Commands\MarkFollowUpsOverdue;
use App\Console\Commands\ProcessSubscriptionsExpiry;
use App\Console\Commands\SendEmailSubscriptionReminders;
use App\Console\Commands\SendSubscriptionReminders;
use App\Console\Commands\SendTeamExpiryReminders;
use App\Services\Google\SubscriptionCalendarService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 07: subscription lifecycle maintenance.
Schedule::command(ProcessSubscriptionsExpiry::class)->dailyAt('00:05');
Schedule::command(SendSubscriptionReminders::class)->dailyAt('08:00');

// Phase 08: follow-up lifecycle maintenance.
Schedule::command(MarkFollowUpsOverdue::class)->dailyAt('00:10');

// Order delivery (Section 5): team-only "expires tomorrow" reminder. Fires at
// the configured notification time; the command itself selects active
// subscriptions whose expiry date is exactly tomorrow and notifies only the
// active members of that subscription's team, once per expiry cycle.
Schedule::command(SendTeamExpiryReminders::class)
    ->dailyAt((string) config('leadx.notifications.expiry_reminder_time', '07:30'));

// Phase 16: email subscription reminders (expiry + renewal). The exact
// send time is admin-configurable (Admin Settings → Email Reminders →
// reminder time), so the scheduler ticks hourly and the command itself
// gates on "the configured time has passed today". Per-window
// idempotency comes from the email_reminder_tracking unique index, and
// the command is a no-op when the admin has disabled expiry emails.
// (No DB access at scheduler boot keeps `migrate:fresh` and the test
// suite safe.)
Schedule::command(SendEmailSubscriptionReminders::class)
    ->hourly()
    ->withoutOverlapping()
    ->name('email-subscription-reminders');

// Order delivery (Section 4): pick up subscriptions whose Google Calendar
// expiry-reminder event is pending/errored (e.g. the employee connected a
// calendar after activation, or a sync failed). Idempotent via the stored
// event id; safe to run daily and repeated.
Schedule::call(function (SubscriptionCalendarService $service): void {
    $service->retryPending();
})->name('calendar-retry-pending')->dailyAt('01:00')->withoutOverlapping();
