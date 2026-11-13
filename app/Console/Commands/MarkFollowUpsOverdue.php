<?php

namespace App\Console\Commands;

use App\Services\FollowUpService;
use Illuminate\Console\Command;

/**
 * Flip due-but-unhandled follow-ups into the `overdue` status.
 *
 * Overdue is a *derived* state, not a command: a follow-up is overdue when
 * its scheduled_at has passed and it is still `scheduled`. Running this
 * nightly keeps the status column honest without a background process.
 */
class MarkFollowUpsOverdue extends Command
{
    protected $signature = 'leadx:mark-follow-ups-overdue';

    protected $description = 'Mark scheduled follow-ups whose scheduled_at has passed as overdue';

    public function handle(FollowUpService $service): int
    {
        $count = $service->markOverdue();

        $this->info("Marked {$count} follow-up(s) as overdue.");

        return self::SUCCESS;
    }
}
