<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Services\Backup\BackupManagerService;
use Illuminate\Console\Command;

class LeadxBackupVerifyCommand extends Command
{
    protected $signature = 'leadx:backup:verify {backup_id? : Optional specific backup ID to verify}';

    protected $description = 'Verify the checksum, encryption, and archive integrity of backups';

    public function handle(BackupManagerService $manager): int
    {
        $backupId = $this->argument('backup_id');

        if ($backupId) {
            $backup = BackupRecord::where('backup_id', $backupId)->first();
            if (! $backup) {
                $this->error("Backup not found: {$backupId}");
                return self::FAILURE;
            }
            $backups = collect([$backup]);
        } else {
            $backups = BackupRecord::latest()->take(10)->get();
            if ($backups->isEmpty()) {
                $this->info("No backups found to verify.");
                return self::SUCCESS;
            }
        }

        $allValid = true;
        $rows = [];

        foreach ($backups as $b) {
            $valid = $manager->verify($b);
            if (! $valid) {
                $allValid = false;
            }

            $rows[] = [
                $b->backup_id,
                $b->backup_type,
                $b->formatted_size,
                $valid ? 'PASS' : 'FAIL',
                $b->verified_at?->format('Y-m-d H:i:s') ?? '—',
                $b->error_message ?? 'OK',
            ];
        }

        $this->table(['Backup ID', 'Type', 'Size', 'Integrity', 'Verified At', 'Notes'], $rows);

        return $allValid ? self::SUCCESS : self::FAILURE;
    }
}
