<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRecord;
use App\Models\BackupRestoreLog;
use App\Services\Backup\BackupManagerService;
use App\Services\Backup\BackupSettings;
use App\Services\Backup\RestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupManagerService $manager,
        protected RestoreService $restoreService
    ) {}

    /**
     * Backup Dashboard & Searchable History
     */
    public function index(Request $request): View
    {
        $lastSuccessful = BackupRecord::where('integrity_status', 'verified')->latest()->first();
        $totalBackups = BackupRecord::count();
        $totalBytes = BackupRecord::sum('file_size');

        $totalStorageFormatted = $this->formatBytes($totalBytes);

        $dbStatus = BackupRecord::where('database_status', 'success')->latest()->first() ? 'Operational' : 'No Backups';
        $storageStatus = BackupRecord::where('storage_status', 'success')->latest()->first() ? 'Operational' : 'No Backups';
        $disk = BackupSettings::get('disk', 'local');
        $remoteStatus = $disk !== 'local' ? strtoupper($disk) : 'Local only';

        $recentFailures = BackupRecord::where('integrity_status', 'failed')
            ->orWhere('database_status', 'failed')
            ->orWhere('storage_status', 'failed')
            ->latest()
            ->take(5)
            ->get();

        // History query
        $query = BackupRecord::query()->with('creator')->latest();

        if ($type = $request->input('type')) {
            $query->where('backup_type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('integrity_status', $status);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where('backup_id', 'like', "%{$search}%");
        }

        $backups = $query->paginate(15)->appends($request->query());

        return view('admin.backups.index', [
            'lastSuccessful' => $lastSuccessful,
            'totalBackups' => $totalBackups,
            'totalStorageFormatted' => $totalStorageFormatted,
            'dbStatus' => $dbStatus,
            'storageStatus' => $storageStatus,
            'remoteStatus' => $remoteStatus,
            'nextScheduled' => BackupSettings::get('schedule_time', '02:00') . ' ' . config('app.timezone', 'UTC') . ' daily',
            'recentFailures' => $recentFailures,
            'backups' => $backups,
        ]);
    }

    /**
     * Trigger manual backup creation
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:full,database,storage']]);
        $type = $data['type'];

        $admin = auth('admin')->user();

        try {
            $backup = $this->manager->create($type, $admin?->id);

            if ($backup->integrity_status !== 'verified' || $backup->database_status === 'failed' || $backup->storage_status === 'failed') {
                return redirect()->route('admin.backups.show', $backup)
                    ->with('error', 'Backup needs attention. Review the component status and error details before using this archive.');
            }

            return redirect()->route('admin.backups.index')
                ->with('status', "Backup #{$backup->backup_id} created successfully ({$backup->formatted_size}).");
        } catch (\Throwable $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', "Backup creation failed: " . $e->getMessage());
        }
    }

    /**
     * Backup Details
     */
    public function show(BackupRecord $backup): View
    {
        $backup->load(['creator', 'restoreLogs.admin']);

        return view('admin.backups.show', [
            'backup' => $backup,
        ]);
    }

    /**
     * Verify backup integrity
     */
    public function verify(BackupRecord $backup): RedirectResponse
    {
        $valid = $this->manager->verify($backup);

        if ($valid) {
            return back()->with('status', "Backup integrity verified successfully! Checksum: {$backup->checksum}");
        }

        return back()->with('error', "Integrity check failed: " . ($backup->error_message ?? 'Corrupted or missing archive.'));
    }

    /**
     * Secure file download (Strict path traversal protection)
     */
    public function download(BackupRecord $backup): BinaryFileResponse|RedirectResponse
    {
        $allowedBase = realpath(storage_path('app/backups'));
        $targetFile = realpath(storage_path('app/' . $backup->file_path));

        // Strict traversal check: file must exist and reside inside storage/app/backups
        if (! $targetFile || ! $allowedBase || ! str_starts_with($targetFile, $allowedBase . DIRECTORY_SEPARATOR) || ! is_file($targetFile)) {
            abort(404, 'Backup archive not found or access denied.');
        }

        return response()->download($targetFile, basename($backup->file_path), [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Delete a backup archive (Super Admin only)
     */
    public function destroy(BackupRecord $backup): RedirectResponse
    {
        $backupId = $backup->backup_id;
        $this->manager->delete($backup);

        return redirect()->route('admin.backups.index')
            ->with('status', "Backup {$backupId} has been deleted.");
    }

    /**
     * Restore Management page
     */
    public function restoreView(): View
    {
        $verifiedBackups = BackupRecord::where('integrity_status', 'verified')
            ->where('database_status', '!=', 'failed')->where('storage_status', '!=', 'failed')->latest()->get();
        $restoreLogs = BackupRestoreLog::with(['admin', 'backup'])->latest()->paginate(10);

        return view('admin.backups.restore', [
            'verifiedBackups' => $verifiedBackups,
            'restoreLogs' => $restoreLogs,
        ]);
    }

    /**
     * Execute Restore with safeguards
     */
    public function performRestore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup_id' => ['required', 'string', 'exists:backups,backup_id'],
            'confirm_text' => ['required', 'in:CONFIRM RESTORE'],
            'restore_type' => ['required', 'in:full,database,storage'],
        ]);

        $backup = BackupRecord::where('backup_id', $data['backup_id'])->firstOrFail();

        if ($backup->integrity_status !== 'verified' || $backup->database_status === 'failed' || $backup->storage_status === 'failed') {
            return back()->withErrors(['backup_id' => 'Choose a verified backup with no failed components.'])->withInput();
        }

        if ($backup->backup_type !== 'full' && $backup->backup_type !== $data['restore_type']) {
            return back()->withErrors(['restore_type' => 'The restore type must match the contents of the selected backup.'])->withInput();
        }
        $admin = auth('admin')->user();

        if (! $admin) {
            abort(403, 'Unauthorized.');
        }

        $log = $this->restoreService->restore($backup, $admin, true, $data['restore_type']);

        if ($log->status === 'completed') {
            return redirect()->route('admin.backups.restore')
                ->with('status', "Restore completed successfully! Pre-restore snapshot ID: {$log->pre_restore_backup_id}");
        }

        return redirect()->route('admin.backups.restore')
            ->with('error', "Restore failed: " . ($log->error_message ?? 'Check restore logs.'));
    }

    /**
     * Settings Page for Backup Scheduling & Retention
     */
    public function settings(): View
    {
        return view('admin.backups.settings', [
            'scheduleTime' => BackupSettings::get('schedule_time', '02:00'),
            'retentionDays' => BackupSettings::get('retention_days', 30),
            'currentDisk' => BackupSettings::get('disk', 'local'),
            'availableDisks' => $this->availableDisks(),
        ]);
    }

    /**
     * Update Backup Settings
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'schedule_time' => ['required', 'date_format:H:i'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'disk' => ['required', Rule::in($this->availableDisks())],
        ]);

        BackupSettings::save($data);

        return back()->with('status', 'Backup configuration updated successfully.');
    }

    protected function availableDisks(): array
    {
        return array_values(array_filter(['local', 's3', 'r2', 'sftp'], fn ($disk) => config('filesystems.disks.'.$disk) !== null));
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $size = $bytes / pow(1024, $power);

        return number_format($size, 2) . ' ' . $units[$power];
    }
}
