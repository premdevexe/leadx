<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BackupRecord;
use App\Models\BackupRestoreLog;
use App\Models\User;
use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupManagerService;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\RestoreService;
use App\Services\Backup\StorageBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupAndDisasterRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create([
            'username' => 'superadmin',
            'email' => 'admin@leadx.test',
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'username' => 'emp_john',
        ]);
    }

    public function test_backup_encryption_and_decryption_integrity(): void
    {
        $encryptionService = app(BackupEncryptionService::class);

        $tempDir = storage_path('app/backups/test');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $sourceFile = $tempDir . '/test_source.txt';
        $encryptedFile = $tempDir . '/test_source.txt.enc';
        $decryptedFile = $tempDir . '/test_decrypted.txt';

        $secretContent = 'LEADX-CONFIDENTIAL-DATA-' . str_repeat('A1B2C3D4', 500);
        file_put_contents($sourceFile, $secretContent);

        // Encrypt
        $checksum = $encryptionService->encryptFile($sourceFile, $encryptedFile);
        $this->assertNotEmpty($checksum);
        $this->assertFileExists($encryptedFile);
        $this->assertNotEquals($secretContent, file_get_contents($encryptedFile));

        // Verify valid HMAC
        $this->assertTrue($encryptionService->verifyEncryptedFile($encryptedFile));

        // Decrypt
        $decrypted = $encryptionService->decryptFile($encryptedFile, $decryptedFile);
        $this->assertTrue($decrypted);
        $this->assertFileExists($decryptedFile);
        $this->assertEquals($secretContent, file_get_contents($decryptedFile));

        // Tamper test: Corrupt 1 byte in encrypted file
        $content = file_get_contents($encryptedFile);
        $content[20] = chr(ord($content[20]) ^ 0xFF);
        file_put_contents($encryptedFile, $content);

        // Verification should now fail
        $this->assertFalse($encryptionService->verifyEncryptedFile($encryptedFile));

        // Decrypting tampered file must throw RuntimeException
        $this->expectException(\RuntimeException::class);
        $encryptionService->decryptFile($encryptedFile, $decryptedFile . '.bad');

        // Cleanup
        @unlink($sourceFile);
        @unlink($encryptedFile);
        @unlink($decryptedFile);
    }

    public function test_database_backup_creates_valid_sql_dump(): void
    {
        $dbBackupService = app(DatabaseBackupService::class);
        $targetSql = storage_path('app/backups/test_db.sql');

        $stats = $dbBackupService->dump($targetSql);

        $this->assertFileExists($targetSql);
        $this->assertGreaterThan(0, $stats['tables']);
        $this->assertGreaterThan(0, filesize($targetSql));

        $content = file_get_contents($targetSql);
        $this->assertStringContainsString('LeadX Database Backup', $content);

        @unlink($targetSql);
    }

    public function test_storage_backup_archives_files_and_excludes_sensitive_patterns(): void
    {
        $storageBackupService = app(StorageBackupService::class);

        // Ensure dummy test upload file exists
        $uploadDir = public_path('uploads/test');
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        file_put_contents($uploadDir . '/dummy_logo.png', 'fake image bytes');

        $targetZip = storage_path('app/backups/test_storage.zip');
        $stats = $storageBackupService->archive($targetZip);

        $this->assertFileExists($targetZip);
        $this->assertGreaterThan(0, $stats['files_count']);

        // Verify that .env was NOT packaged into the zip
        $zip = new \ZipArchive();
        $zip->open($targetZip);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $this->assertStringNotContainsString('.env', $name);
            $this->assertStringNotContainsString('.git', $name);
        }
        $zip->close();

        @unlink($uploadDir . '/dummy_logo.png');
        @rmdir($uploadDir);
        @unlink($targetZip);
    }

    public function test_backup_manager_creates_full_backup_record(): void
    {
        $manager = app(BackupManagerService::class);

        $backup = $manager->create('full', $this->admin->id);

        $this->assertInstanceOf(BackupRecord::class, $backup);
        $this->assertEquals('full', $backup->backup_type);
        $this->assertEquals('success', $backup->database_status);
        $this->assertEquals('verified', $backup->integrity_status);
        $this->assertGreaterThan(0, $backup->file_size);
        $this->assertFileExists(storage_path('app/' . $backup->file_path));

        // Test manual verify
        $this->assertTrue($manager->verify($backup));
    }

    public function test_backup_pruning_respects_safety_rule(): void
    {
        $manager = app(BackupManagerService::class);

        // Create an old backup 40 days ago
        Carbon::setTestNow(now()->subDays(40));
        $oldBackup = $manager->create('database', $this->admin->id);
        $this->assertEquals('verified', $oldBackup->integrity_status);

        // Case 1: No newer verified backup exists yet -> pruning must PRESERVE the old backup!
        Carbon::setTestNow(); // clear test now, return to real time
        $result = $manager->prune(30);
        $this->assertEquals(0, $result['pruned_count']);
        $this->assertEquals(1, $result['preserved_count']);
        $this->assertDatabaseHas('backups', ['id' => $oldBackup->id]);

        // Case 2: Create a fresh verified backup today -> now the old backup can be pruned safely!
        $newBackup = $manager->create('database', $this->admin->id);
        $this->assertEquals('verified', $newBackup->integrity_status);

        $result2 = $manager->prune(30);
        $this->assertEquals(1, $result2['pruned_count']);
        $this->assertEquals(0, $result2['preserved_count']);
        $this->assertDatabaseMissing('backups', ['id' => $oldBackup->id]);
        $this->assertDatabaseHas('backups', ['id' => $newBackup->id]);
    }

    public function test_restore_service_takes_safety_snapshot_and_requires_confirmation(): void
    {
        $manager = app(BackupManagerService::class);
        $restoreService = app(RestoreService::class);

        $backup = $manager->create('full', $this->admin->id);

        // Unconfirmed restore must fail
        $this->expectException(\InvalidArgumentException::class);
        $restoreService->restore($backup, $this->admin, false, 'full');
    }

    public function test_confirmed_restore_executes_and_creates_restore_log(): void
    {
        $manager = app(BackupManagerService::class);
        $restoreService = app(RestoreService::class);

        $backup = $manager->create('full', $this->admin->id);

        $log = $restoreService->restore($backup, $this->admin, true, 'database');

        $this->assertInstanceOf(BackupRestoreLog::class, $log);
        $this->assertEquals('completed', $log->status);
        $this->assertNotNull($log->pre_restore_backup_id);
        $this->assertDatabaseHas('backup_restore_logs', [
            'id' => $log->id,
            'status' => 'completed',
        ]);
    }

    public function test_super_admin_can_access_backup_dashboard_and_management_views(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Backups');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.backups.restore'))
            ->assertOk()
            ->assertSee('Restore');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.backups.settings'))
            ->assertOk()
            ->assertSee('Daily backup time');
    }

    public function test_super_admin_can_download_and_verify_backups(): void
    {
        $manager = app(BackupManagerService::class);
        $backup = $manager->create('database', $this->admin->id);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.backups.download', $backup));

        $response->assertOk();
        $this->assertEquals('application/octet-stream', $response->headers->get('content-type'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.backups.verify', $backup))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_employee_is_completely_denied_from_all_backup_routes(): void
    {
        // 1. Employee cannot view backup dashboard
        $this->actingAs($this->employee, 'employee')
            ->get(route('admin.backups.index'))
            ->assertRedirect('/admin/login');

        // 2. Employee cannot trigger a backup
        $this->actingAs($this->employee, 'employee')
            ->post(route('admin.backups.store'))
            ->assertRedirect('/admin/login');

        // 3. Employee cannot access restore view
        $this->actingAs($this->employee, 'employee')
            ->get(route('admin.backups.restore'))
            ->assertRedirect('/admin/login');

        // 4. Employee cannot access backup settings
        $this->actingAs($this->employee, 'employee')
            ->get(route('admin.backups.settings'))
            ->assertRedirect('/admin/login');
    }

    public function test_artisan_backup_commands_execute_successfully(): void
    {
        $this->artisan('leadx:backup --database')
            ->assertSuccessful();

        $this->artisan('leadx:backup:verify')
            ->assertSuccessful();

        $this->artisan('leadx:backup:prune --retention-days=30')
            ->assertSuccessful();
    }
}
