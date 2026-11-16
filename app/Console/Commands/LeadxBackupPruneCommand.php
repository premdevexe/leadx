<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManagerService;
use App\Services\Backup\BackupSettings;
use Illuminate\Console\Command;

class LeadxBackupPruneCommand extends Command
{
    protected $signature = 'leadx:backup:prune {--retention-days= : Days to retain backups (defaults to saved settings)}';

    protected $description = 'Safely remove expired backups older than retention window (only if newer verified backup exists)';

    public function handle(BackupManagerService $manager): int
    {
        $days = (int) ($this->option('retention-days') ?? BackupSettings::get('retention_days', 30));
        if ($days < 1 || $days > 365) {
            $this->error('Retention days must be between 1 and 365.');
            return self::FAILURE;
        }
        $this->info("Pruning backups older than {$days} days...");

        $result = $manager->prune($days);

        $this->info("Pruning complete. Deleted: {$result['pruned_count']}, Preserved for safety: {$result['preserved_count']}.");

        return self::SUCCESS;
    }
}
