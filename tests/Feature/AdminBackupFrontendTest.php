<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BackupRecord;
use App\Models\BackupRestoreLog;
use App\Services\Backup\BackupManagerService;
use App\Services\Backup\BackupSettings;
use App\Services\Backup\RemoteStorageService;
use App\Services\Backup\RestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminBackupFrontendTest extends TestCase
{
    use RefreshDatabase;

    private function backup(array $attributes = []): BackupRecord
    {
        return BackupRecord::create(array_merge([
            'backup_id' => 'leadx-backup-test-'.uniqid(),
            'backup_type' => 'full',
            'disk' => 'local',
            'file_path' => 'backups/test.leadx',
            'file_size' => 2048,
            'database_status' => 'success',
            'storage_status' => 'success',
            'remote_status' => 'skipped',
            'integrity_status' => 'verified',
            'verified_at' => now(),
        ], $attributes));
    }

    public function test_admin_can_open_all_backup_pages_and_filter_history(): void
    {
        $admin = Admin::factory()->create();
        $backup = $this->backup(['backup_id' => 'visible-database-backup', 'backup_type' => 'database']);
        $this->backup(['backup_id' => 'excluded-storage-backup', 'backup_type' => 'storage']);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.backups.index', ['type' => 'database']))->assertOk()
            ->assertSee('visible-database-backup')->assertDontSee('excluded-storage-backup');
        $this->get(route('admin.backups.show', $backup))->assertOk()->assertSee('Verify integrity')->assertSee('Download archive');
        $this->get(route('admin.backups.restore'))->assertOk()->assertSee('CONFIRM RESTORE');
        $this->get(route('admin.backups.settings'))->assertOk()->assertSee('Save backup settings');
    }

    public function test_empty_backup_and_restore_pages_provide_next_steps(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.backups.index'))->assertOk()->assertSee('No backups found')->assertSee('Create backup');
        $this->get(route('admin.backups.restore'))->assertOk()->assertSee('No verified backups available')->assertDontSee('Restore selected backup');
    }

    public function test_backup_management_requires_admin_authentication(): void
    {
        $backup = $this->backup();
        foreach (['admin.backups.index', 'admin.backups.settings', 'admin.backups.restore'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
        $this->get(route('admin.backups.download', $backup))->assertRedirect(route('admin.login'));
        $this->post(route('admin.backups.store'), ['type' => 'full'])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.backups.destroy', $backup))->assertRedirect(route('admin.login'));
    }

    public function test_backup_settings_are_saved_displayed_and_used_for_pruning(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->from(route('admin.backups.settings'))
            ->put(route('admin.backups.settings.update'), ['schedule_time' => '14:35', 'retention_days' => 14, 'disk' => 'local'])
            ->assertRedirect(route('admin.backups.settings'))->assertSessionHasNoErrors();

        $this->assertSame('14:35', BackupSettings::get('schedule_time'));
        $this->assertSame(14, BackupSettings::get('retention_days'));
        $this->get(route('admin.backups.settings'))->assertOk()->assertSee('value="14:35"', false)->assertSee('value="14"', false);
        $this->get(route('admin.backups.index'))->assertOk()->assertSee('14:35');
        $this->mock(BackupManagerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('prune')->once()->with(14)->andReturn(['pruned_count' => 0, 'preserved_count' => 0]);
        });
        $this->artisan('leadx:backup:prune')->assertExitCode(0);
    }

    public function test_invalid_time_and_unconfigured_disk_are_rejected_with_old_input(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')->from(route('admin.backups.settings'))
            ->put(route('admin.backups.settings.update'), ['schedule_time' => '99:99', 'retention_days' => 14, 'disk' => 'not-configured'])
            ->assertSessionHasErrors(['schedule_time', 'disk'])->assertSessionHasInput('retention_days', 14);
    }

    public function test_remote_upload_uses_saved_destination(): void
    {
        Storage::fake('s3');
        BackupSettings::save(['disk' => 's3']);
        $path = tempnam(sys_get_temp_dir(), 'leadx-backup-test-');
        file_put_contents($path, 'test archive');
        try {
            $result = app(RemoteStorageService::class)->upload($path, 'backups/test.leadx');
            $this->assertSame('uploaded', $result['status']);
            Storage::disk('s3')->assertExists('backups/test.leadx');
        } finally {
            unlink($path);
        }
    }

    public function test_create_action_dispatches_chosen_contents_and_reports_partial_failure(): void
    {
        $admin = Admin::factory()->create();
        $backup = $this->backup(['database_status' => 'failed']);
        $this->mock(BackupManagerService::class, function (MockInterface $mock) use ($admin, $backup) {
            $mock->shouldReceive('create')->once()->with('database', $admin->id)->andReturn($backup);
        });
        $this->actingAs($admin, 'admin')->post(route('admin.backups.store'), ['type' => 'database'])
            ->assertRedirect(route('admin.backups.show', $backup))->assertSessionHas('error');
    }

    public function test_restore_rejects_unverified_archive_wrong_contents_and_missing_confirmation(): void
    {
        $backup = $this->backup(['backup_type' => 'database']);
        $this->mock(RestoreService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('restore'));
        $this->actingAs(Admin::factory()->create(), 'admin')->from(route('admin.backups.restore'));
        $payload = ['backup_id' => $backup->backup_id, 'restore_type' => 'full', 'confirm_text' => 'CONFIRM RESTORE'];
        $this->post(route('admin.backups.restore.perform'), $payload)->assertSessionHasErrors('restore_type');
        $payload['restore_type'] = 'database';
        $payload['confirm_text'] = 'yes';
        $this->post(route('admin.backups.restore.perform'), $payload)->assertSessionHasErrors('confirm_text');
        $payload['confirm_text'] = 'CONFIRM RESTORE';
        $backup->update(['integrity_status' => 'unverified']);
        $this->post(route('admin.backups.restore.perform'), $payload)->assertSessionHasErrors('backup_id');
    }

    public function test_confirmed_restore_dispatches_to_service_and_shows_history(): void
    {
        $admin = Admin::factory()->create();
        $backup = $this->backup();
        $log = BackupRestoreLog::create([
            'backup_id' => $backup->backup_id, 'initiated_by_admin_id' => $admin->id,
            'status' => 'completed', 'restore_type' => 'full', 'started_at' => now(),
            'pre_restore_backup_id' => 'safety-snapshot', 'log_output' => 'Archive restored.',
        ]);
        $this->mock(RestoreService::class, function (MockInterface $mock) use ($backup, $admin, $log) {
            $mock->shouldReceive('restore')->once()->withArgs(fn ($selected, $actor, $confirmed, $type) => $selected->is($backup) && $actor->is($admin) && $confirmed && $type === 'full')->andReturn($log);
        });
        $this->actingAs($admin, 'admin')->post(route('admin.backups.restore.perform'), [
            'backup_id' => $backup->backup_id, 'restore_type' => 'full', 'confirm_text' => 'CONFIRM RESTORE',
        ])->assertRedirect(route('admin.backups.restore'))->assertSessionHas('status');
        $this->get(route('admin.backups.restore'))->assertOk()->assertSee('safety-snapshot')->assertSee('Archive restored.');
    }

    public function test_download_rejects_a_sibling_directory_with_the_same_prefix(): void
    {
        $sibling = storage_path('app/backups-sibling-test');
        $created = !is_dir($sibling);
        if ($created) mkdir($sibling, 0755, true);
        $path = tempnam($sibling, 'archive-');
        file_put_contents($path, 'private');
        $backup = $this->backup(['file_path' => 'backups-sibling-test/'.basename($path)]);
        try {
            $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.backups.download', $backup))->assertNotFound();
        } finally {
            unlink($path);
            if ($created) rmdir($sibling);
        }
    }
}
