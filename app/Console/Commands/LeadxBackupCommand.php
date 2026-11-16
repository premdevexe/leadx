<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManagerService;
use Illuminate\Console\Command;

class LeadxBackupCommand extends Command
{
    protected $signature = 'leadx:backup 
                            {--database : Backup only database} 
                            {--storage : Backup only storage files} 
                            {--type= : Specific backup type (full, database, storage)}';

    protected $description = 'Create an automated, encrypted backup of the LeadX database and storage files';

    public function handle(BackupManagerService $manager): int
    {
        $this->info('Starting LeadX backup process...');

        $type = 'full';
        if ($this->option('database')) {
            $type = 'database';
        } elseif ($this->option('storage')) {
            $type = 'storage';
        } elseif ($this->option('type')) {
            $type = strtolower((string) $this->option('type'));
        }

        try {
            $backup = $manager->create($type);

            $this->info("Backup completed successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Backup ID', $backup->backup_id],
                    ['Type', $backup->backup_type],
                    ['Size', $backup->formatted_size],
                    ['Database Status', $backup->database_status],
                    ['Storage Status', $backup->storage_status],
                    ['Remote Status', $backup->remote_status],
                    ['Integrity Status', $backup->integrity_status],
                    ['Path', $backup->file_path],
                    ['Checksum', substr((string) $backup->checksum, 0, 16) . '...'],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
