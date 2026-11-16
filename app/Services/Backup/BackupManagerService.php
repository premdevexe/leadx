<?php

namespace App\Services\Backup;

use App\Models\BackupRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class BackupManagerService
{
    public function __construct(
        protected DatabaseBackupService $databaseBackup,
        protected StorageBackupService $storageBackup,
        protected BackupEncryptionService $encryption,
        protected RemoteStorageService $remoteStorage
    ) {}

    /**
     * Create a backup archive (full, database, or storage).
     */
    public function create(string $type = 'full', ?int $adminId = null): BackupRecord
    {
        $backupId = 'leadx-backup-' . date('Ymd-His') . '-' . Str::lower(Str::random(6));
        $baseDir = storage_path('app/backups');
        $tempDir = $baseDir . '/temp/' . $backupId;

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $meta = [
            'type' => $type,
            'backup_id' => $backupId,
            'created_at' => now()->toIso8601String(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
        ];

        $databaseStatus = 'skipped';
        $storageStatus = 'skipped';
        $errorMessage = null;

        $unencryptedZipPath = $tempDir . '/archive.zip';
        $finalEncryptedPath = $baseDir . '/' . $backupId . '.leadx';

        $zip = new ZipArchive();
        if ($zip->open($unencryptedZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create staging zip archive.");
        }

        // 1. Database dump
        if (in_array($type, ['full', 'database'], true)) {
            try {
                $sqlPath = $tempDir . '/database.sql';
                $dbStats = $this->databaseBackup->dump($sqlPath);
                $zip->addFile($sqlPath, 'database.sql');
                $databaseStatus = 'success';
                $meta['database'] = $dbStats;
            } catch (Throwable $e) {
                $databaseStatus = 'failed';
                $errorMessage = "Database dump failed: " . $e->getMessage();
                Log::error("Backup database dump failed", ['backup_id' => $backupId, 'error' => $e->getMessage()]);
            }
        }

        // 2. Storage archive
        if (in_array($type, ['full', 'storage'], true)) {
            try {
                $storageZipPath = $tempDir . '/storage_files.zip';
                $storageStats = $this->storageBackup->archive($storageZipPath);
                if (file_exists($storageZipPath)) {
                    $zip->addFile($storageZipPath, 'storage.zip');
                }
                $storageStatus = 'success';
                $meta['storage'] = $storageStats;
            } catch (Throwable $e) {
                $storageStatus = 'failed';
                $errorMessage = ($errorMessage ? $errorMessage . ' | ' : '') . "Storage backup failed: " . $e->getMessage();
                Log::error("Backup storage archiving failed", ['backup_id' => $backupId, 'error' => $e->getMessage()]);
            }
        }

        // Add metadata JSON
        $zip->addFromString('metadata.json', json_encode($meta, JSON_PRETTY_PRINT));
        $zip->close();

        // 3. Encrypt the archive
        $checksum = null;
        $integrityStatus = 'unverified';
        $fileSize = 0;

        try {
            $checksum = $this->encryption->encryptFile($unencryptedZipPath, $finalEncryptedPath);
            $fileSize = filesize($finalEncryptedPath);

            // Immediate verification
            if ($this->encryption->verifyEncryptedFile($finalEncryptedPath)) {
                $integrityStatus = 'verified';
            } else {
                $integrityStatus = 'failed';
            }
        } catch (Throwable $e) {
            $integrityStatus = 'failed';
            $errorMessage = ($errorMessage ? $errorMessage . ' | ' : '') . "Encryption failed: " . $e->getMessage();
        }

        // 4. Remote storage dispatch
        $remoteConfig = $this->remoteStorage->upload($finalEncryptedPath, 'backups/' . basename($finalEncryptedPath));

        // 5. Cleanup temporary staging directory
        $this->deleteDirectory($tempDir);

        // 6. Record in database
        return BackupRecord::create([
            'backup_id' => $backupId,
            'backup_type' => $type,
            'disk' => $remoteConfig['disk'] ?? 'local',
            'file_path' => 'backups/' . basename($finalEncryptedPath),
            'file_size' => $fileSize,
            'database_status' => $databaseStatus,
            'storage_status' => $storageStatus,
            'remote_status' => $remoteConfig['status'] ?? 'skipped',
            'integrity_status' => $integrityStatus,
            'checksum' => $checksum,
            'is_encrypted' => true,
            'metadata' => $meta,
            'error_message' => $errorMessage,
            'verified_at' => $integrityStatus === 'verified' ? now() : null,
            'created_by_admin_id' => $adminId,
        ]);
    }

    /**
     * Verify backup integrity.
     */
    public function verify(BackupRecord $backup): bool
    {
        $fullPath = storage_path('app/' . $backup->file_path);

        if (! file_exists($fullPath)) {
            $backup->update([
                'integrity_status' => 'failed',
                'error_message' => 'Backup archive file is missing from local disk.',
            ]);

            return false;
        }

        $isValid = $this->encryption->verifyEncryptedFile($fullPath);
        $calculatedChecksum = hash_file('sha256', $fullPath);

        if ($isValid && ($backup->checksum === null || hash_equals($backup->checksum, $calculatedChecksum))) {
            $backup->update([
                'integrity_status' => 'verified',
                'checksum' => $calculatedChecksum,
                'verified_at' => now(),
            ]);

            return true;
        }

        $backup->update([
            'integrity_status' => 'failed',
            'error_message' => 'Integrity verification failed: Checksum or HMAC tag mismatch.',
        ]);

        return false;
    }

    /**
     * Prune backups older than retention days.
     * Rule: Do not delete a backup until a newer backup has passed integrity verification.
     */
    public function prune(int $retentionDays = 30): array
    {
        $cutoff = Carbon::now()->subDays($retentionDays);
        $pruned = 0;
        $preserved = 0;

        $expiredBackups = BackupRecord::where('created_at', '<', $cutoff)->orderBy('created_at', 'asc')->get();

        foreach ($expiredBackups as $backup) {
            // Check if there is at least one newer verified backup
            $hasNewerVerified = BackupRecord::where('created_at', '>', $backup->created_at)
                ->where('integrity_status', 'verified')
                ->exists();

            if (! $hasNewerVerified) {
                // Safeguard: Preserve this backup because no newer verified backup exists!
                $preserved++;
                continue;
            }

            $this->delete($backup);
            $pruned++;
        }

        return [
            'pruned_count' => $pruned,
            'preserved_count' => $preserved,
        ];
    }

    /**
     * Delete a backup archive and its database record.
     */
    public function delete(BackupRecord $backup): bool
    {
        $fullPath = storage_path('app/' . $backup->file_path);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        return (bool) $backup->delete();
    }

    /**
     * Get backup storage path.
     */
    public function getAbsolutePath(BackupRecord $backup): string
    {
        return storage_path('app/' . $backup->file_path);
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
