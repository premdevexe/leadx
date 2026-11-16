<?php

namespace App\Services\Backup;

use App\Models\Admin;
use App\Models\BackupRecord;
use App\Models\BackupRestoreLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

class RestoreService
{
    public function __construct(
        protected BackupManagerService $backupManager,
        protected BackupEncryptionService $encryption
    ) {}

    /**
     * Restore from a verified backup archive with safeguards.
     *
     * @param BackupRecord $backup
     * @param Admin $admin
     * @param bool $confirmed Explicit Super Admin confirmation
     * @param string $restoreType 'full', 'database', or 'storage'
     * @return BackupRestoreLog
     */
    public function restore(
        BackupRecord $backup,
        Admin $admin,
        bool $confirmed = false,
        string $restoreType = 'full'
    ): BackupRestoreLog {
        if (! $confirmed) {
            throw new \InvalidArgumentException("Explicit Super Admin confirmation is required to proceed with restore.");
        }

        $log = BackupRestoreLog::create([
            'backup_id' => $backup->backup_id,
            'initiated_by_admin_id' => $admin->id,
            'status' => 'in_progress',
            'restore_type' => $restoreType,
            'environment' => app()->environment(),
            'started_at' => now(),
            'log_output' => "Restore initiated by Super Admin #{$admin->id} ({$admin->username}) on " . now()->toIso8601String() . "\n",
        ]);

        $archivePath = $this->backupManager->getAbsolutePath($backup);

        // 1. Verify archive existence and integrity
        if (! file_exists($archivePath) || ! $this->backupManager->verify($backup)) {
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => 'Restore aborted: Backup archive integrity verification failed.',
                'log_output' => $log->log_output . "FAILED: Integrity verification failed.\n",
            ]);

            return $log;
        }

        // 2. Take a pre-restore safety snapshot
        $preRestoreBackupId = null;
        try {
            $log->log_output .= "Creating pre-restore safety snapshot...\n";
            $safetyBackup = $this->backupManager->create('full', $admin->id);
            $preRestoreBackupId = $safetyBackup->backup_id;
            $log->log_output .= "Safety snapshot created: {$preRestoreBackupId}\n";
            $log->update(['pre_restore_backup_id' => $preRestoreBackupId]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => 'Restore aborted: Could not create pre-restore safety snapshot: ' . $e->getMessage(),
                'log_output' => $log->log_output . "FAILED: Pre-restore snapshot failed.\n",
            ]);

            return $log;
        }

        // 3. Decrypt and extract to staging directory
        $stagingDir = storage_path('app/backups/restore_staging/' . $backup->backup_id);
        if (! is_dir($stagingDir)) {
            mkdir($stagingDir, 0755, true);
        }

        $decryptedZip = $stagingDir . '/decrypted.zip';

        try {
            $log->log_output .= "Decrypting backup archive...\n";
            $this->encryption->decryptFile($archivePath, $decryptedZip);

            $zip = new ZipArchive();
            if ($zip->open($decryptedZip) !== true) {
                throw new \RuntimeException("Could not open decrypted zip archive.");
            }
            $zip->extractTo($stagingDir);
            $zip->close();
            @unlink($decryptedZip);

            // 4. Restore Database if requested
            if (in_array($restoreType, ['full', 'database'], true)) {
                $sqlFile = $stagingDir . '/database.sql';
                if (file_exists($sqlFile)) {
                    $log->log_output .= "Restoring database SQL dump...\n";
                    $this->executeSqlDump($sqlFile);
                    $log->log_output .= "Database restored successfully.\n";
                }
            }

            // 5. Restore Storage if requested
            if (in_array($restoreType, ['full', 'storage'], true)) {
                $storageZip = $stagingDir . '/storage.zip';
                if (file_exists($storageZip)) {
                    $log->log_output .= "Restoring storage files...\n";
                    $this->extractStorageFiles($storageZip);
                    $log->log_output .= "Storage files restored successfully.\n";
                }
            }

            // Cleanup staging
            $this->deleteDirectory($stagingDir);

            // Re-persist the log row into the restored database so the audit trail survives the restore
            BackupRestoreLog::updateOrCreate(
                ['id' => $log->id],
                [
                    'backup_id' => $log->backup_id,
                    'initiated_by_admin_id' => $log->initiated_by_admin_id,
                    'status' => 'completed',
                    'restore_type' => $restoreType,
                    'environment' => $log->environment,
                    'pre_restore_backup_id' => $log->pre_restore_backup_id,
                    'started_at' => $log->started_at,
                    'completed_at' => now(),
                    'log_output' => $log->log_output . "Restore completed successfully on " . now()->toIso8601String() . "\n",
                ]
            );

            $log->status = 'completed';
            $log->completed_at = now();

            return $log;
        } catch (Throwable $e) {
            $this->deleteDirectory($stagingDir);
            Log::error("Restore execution failed", ['backup_id' => $backup->backup_id, 'error' => $e->getMessage()]);

            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
                'log_output' => $log->log_output . "ERROR: " . $e->getMessage() . "\n",
            ]);

            return $log;
        }
    }

    /**
     * Safely execute SQL statements from dump file.
     */
    protected function executeSqlDump(string $sqlFilePath): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();

        $content = file_get_contents($sqlFilePath);
        if ($content === false) {
            throw new \RuntimeException("Unable to read SQL dump file.");
        }

        if ($driver === 'sqlite') {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
            $pdo->exec($content);
            $pdo->exec("PRAGMA foreign_keys = ON;");
        } else {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec($content);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }
    }

    /**
     * Restore storage files.
     */
    protected function extractStorageFiles(string $storageZipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($storageZipPath) !== true) {
            throw new \RuntimeException("Could not open storage archive.");
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (str_starts_with($entryName, 'public_uploads/')) {
                $subPath = substr($entryName, strlen('public_uploads/'));
                $target = public_path('uploads/' . $subPath);
                $dir = dirname($target);
                if (! is_dir($dir)) mkdir($dir, 0755, true);
                copy("zip://{$storageZipPath}#{$entryName}", $target);
            } elseif (str_starts_with($entryName, 'storage_public/')) {
                $subPath = substr($entryName, strlen('storage_public/'));
                $target = storage_path('app/public/' . $subPath);
                $dir = dirname($target);
                if (! is_dir($dir)) mkdir($dir, 0755, true);
                copy("zip://{$storageZipPath}#{$entryName}", $target);
            }
        }

        $zip->close();
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
