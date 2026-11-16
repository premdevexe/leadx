<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RemoteStorageService
{
    /**
     * Upload backup archive to the configured destination disk.
     */
    public function upload(string $localFilePath, string $remoteRelativePath, ?string $disk = null): array
    {
        $targetDisk = $disk ?: BackupSettings::get('disk', 'local');

        if ($targetDisk === 'local') {
            return [
                'status' => 'skipped',
                'disk' => 'local',
                'message' => 'Local storage selected; remote upload skipped.',
            ];
        }

        try {
            if (! file_exists($localFilePath)) {
                return [
                    'status' => 'failed',
                    'disk' => $targetDisk,
                    'message' => "Local file not found for remote upload: {$localFilePath}",
                ];
            }

            $storage = Storage::disk($targetDisk);
            $stream = fopen($localFilePath, 'r');
            $success = $storage->put($remoteRelativePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($success) {
                return [
                    'status' => 'uploaded',
                    'disk' => $targetDisk,
                    'message' => "Successfully uploaded to {$targetDisk}: {$remoteRelativePath}",
                ];
            }

            return [
                'status' => 'failed',
                'disk' => $targetDisk,
                'message' => "Remote storage driver returned false on put.",
            ];
        } catch (Throwable $e) {
            Log::error("Remote backup upload failed to {$targetDisk}", [
                'error' => $e->getMessage(),
                'file' => $remoteRelativePath,
            ]);

            return [
                'status' => 'failed',
                'disk' => $targetDisk,
                'message' => $e->getMessage(),
            ];
        }
    }
}
