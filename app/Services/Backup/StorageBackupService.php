<?php

namespace App\Services\Backup;

use ZipArchive;

class StorageBackupService
{
    /**
     * Archive relevant user uploads and storage directories into a zip file.
     */
    public function archive(string $targetZipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($targetZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Unable to create storage zip archive: {$targetZipPath}");
        }

        $directoriesToInclude = [
            'public_uploads' => public_path('uploads'),
            'storage_public' => storage_path('app/public'),
        ];

        $filesCount = 0;
        $totalBytes = 0;

        foreach ($directoriesToInclude as $prefix => $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dir) + 1);

                // Exclude dangerous or temporary files
                if ($this->shouldExclude($relativePath)) {
                    continue;
                }

                $zipEntryName = $prefix . '/' . str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $zipEntryName);
                $filesCount++;
                $totalBytes += $file->getSize();
            }
        }

        $zip->close();

        return [
            'files_count' => $filesCount,
            'uncompressed_bytes' => $totalBytes,
            'archive_bytes' => file_exists($targetZipPath) ? filesize($targetZipPath) : 0,
        ];
    }

    /**
     * Exclude cache, sessions, temporary, sensitive files.
     */
    protected function shouldExclude(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', strtolower($relativePath));

        $blockedPatterns = [
            '.env',
            '.git',
            'backups/',
            'cache/',
            'sessions/',
            'logs/',
            '.ds_store',
            'thumbs.db',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (str_contains($normalized, $pattern) || str_ends_with($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
