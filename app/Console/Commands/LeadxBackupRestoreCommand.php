<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\BackupRecord;
use App\Services\Backup\RestoreService;
use Illuminate\Console\Command;

class LeadxBackupRestoreCommand extends Command
{
    protected $signature = 'leadx:backup:restore 
                            {backup_id : The unique Backup ID to restore} 
                            {--force : Force restore without interactive confirmation} 
                            {--type=full : Restore type (full, database, storage)}';

    protected $description = 'Safely restore a LeadX backup archive with pre-restore snapshot and verification';

    public function handle(RestoreService $service): int
    {
        $backupId = $this->argument('backup_id');
        $backup = BackupRecord::where('backup_id', $backupId)->first();

        if (! $backup) {
            $this->error("Backup not found: {$backupId}");
            return self::FAILURE;
        }

        $admin = Admin::first();
        if (! $admin) {
            $this->error("No administrator account found to authorize restore.");
            return self::FAILURE;
        }

        $this->warn("=================================================================");
        $this->warn("⚠️  DANGER: RESTORING WILL OVERWRITE EXISTING DATA! ⚠️");
        $this->warn("Backup ID: {$backup->backup_id}");
        $this->warn("Created: {$backup->created_at}");
        $this->warn("Type: {$backup->backup_type}");
        $this->warn("Environment: " . app()->environment());
        $this->warn("A safety pre-restore snapshot will be created automatically.");
        $this->warn("=================================================================");

        if (! $this->option('force')) {
            if (! $this->confirm('Are you ABSOLUTELY SURE you want to restore this backup into the database?', false)) {
                $this->info("Restore cancelled by operator.");
                return self::SUCCESS;
            }
        }

        try {
            $restoreType = $this->option('type') ?: 'full';
            $log = $service->restore($backup, $admin, true, $restoreType);

            if ($log->status === 'completed') {
                $this->info("Restore completed successfully!");
                $this->line($log->log_output);
                return self::SUCCESS;
            }

            $this->error("Restore failed: " . ($log->error_message ?? 'Unknown error'));
            $this->line($log->log_output);
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("Restore execution threw an exception: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
