<?php

use App\Console\Commands\MarkFollowUpsOverdue;
use App\Console\Commands\ProcessSubscriptionsExpiry;
use App\Console\Commands\SendSubscriptionReminders;
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
