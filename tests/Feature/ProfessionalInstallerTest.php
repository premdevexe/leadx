<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Services\Installer\AdminAccountManager;
use App\Services\Installer\DatabaseManager;
use App\Services\Installer\EnvironmentManager;
use App\Services\Installer\InstallationStateManager;
use App\Services\Installer\RequirementChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProfessionalInstallerTest extends TestCase
{
    use RefreshDatabase;

    protected InstallationStateManager $stateManager;
    protected RequirementChecker $checker;
    protected DatabaseManager $dbManager;
    protected EnvironmentManager $envManager;
    protected AdminAccountManager $adminManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateManager = app(InstallationStateManager::class);
        $this->checker = app(RequirementChecker::class);
        $this->dbManager = app(DatabaseManager::class);
        $this->envManager = app(EnvironmentManager::class);
        $this->adminManager = app(AdminAccountManager::class);

        // Backup existing lock files
        foreach ($this->stateManager->getPossibleLockFiles() as $path) {
            if (File::exists($path)) {
                @File::copy($path, $path . '.bak');
            }
        }
    }

    protected function tearDown(): void
    {
        // Restore backup lock files
        foreach ($this->stateManager->getPossibleLockFiles() as $path) {
            if (File::exists($path . '.bak')) {
                @File::move($path . '.bak', $path);
            }
        }

        parent::tearDown();
    }

    /**
     * Test 1: Requirement checker accurately evaluates runtime, extensions and directories.
     */
    public function test_requirement_checker_evaluates_system_accurately(): void
    {
        $report = $this->checker->checkAll();

        $this->assertArrayHasKey('php', $report);
        $this->assertArrayHasKey('extensions', $report);
        $this->assertArrayHasKey('directories', $report);
        $this->assertArrayHasKey('all_pass', $report);

        $this->assertTrue($report['php']['pass'], 'PHP version must be >= 8.2');
        $this->assertArrayHasKey('pdo', $report['extensions']);
        $this->assertArrayHasKey('openssl', $report['extensions']);
        $this->assertArrayHasKey('mbstring', $report['extensions']);
        $this->assertTrue($report['extensions']['openssl']['pass']);
    }

    /**
     * Test 2: DatabaseManager handles invalid credentials safely without leaking secrets in errors.
     */
    public function test_database_manager_sanitizes_errors_and_detects_credentials_failure(): void
    {
        $secretPassword = 'SuperSecretDbPassword123!';
        $result = $this->dbManager->testConnection([
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'nonexistent_test_db_' . uniqid(),
            'username' => 'invalid_user_' . uniqid(),
            'password' => $secretPassword,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection failed', $result['message']);
        // Password must NEVER appear in the error message
        $this->assertStringNotContainsString($secretPassword, $result['message']);
    }

    /**
     * Test 3: EnvironmentManager atomically updates target keys while preserving unrelated keys and APP_KEY.
     */
    public function test_environment_manager_atomically_updates_preserving_keys(): void
    {
        $tempEnvPath = storage_path('app/test_env_' . uniqid() . '.env');
        $initialContent = implode("\n", [
            '# Custom Application Header Comment',
            'APP_NAME=LeadXOriginal',
            'APP_KEY=base64:InitialOriginalSecretKey1234567890=',
            'UNTOUCHED_CUSTOM_CONFIG=PreserveMePlease',
            'DB_HOST=127.0.0.1',
        ]) . "\n";

        File::put($tempEnvPath, $initialContent);

        $customEnvManager = new EnvironmentManager($tempEnvPath);

        // Update DB_HOST and APP_NAME only
        $customEnvManager->update([
            'APP_NAME' => 'LeadX Renamed',
            'DB_HOST' => '10.0.0.50',
            'NEWLY_ADDED_KEY' => 'NewValueWithSpaces And Symbols #1',
        ]);

        $updatedContent = File::get($tempEnvPath);

        // Verify updated values
        $this->assertStringContainsString('APP_NAME="LeadX Renamed"', $updatedContent);
        $this->assertStringContainsString('DB_HOST=10.0.0.50', $updatedContent);
        $this->assertStringContainsString('NEWLY_ADDED_KEY="NewValueWithSpaces And Symbols #1"', $updatedContent);

        // Verify preserved keys and comments
        $this->assertStringContainsString('# Custom Application Header Comment', $updatedContent);
        $this->assertStringContainsString('UNTOUCHED_CUSTOM_CONFIG=PreserveMePlease', $updatedContent);
        $this->assertStringContainsString('APP_KEY=base64:InitialOriginalSecretKey1234567890=', $updatedContent);

        // Verify ensureAppKey never overwrites existing key
        $key = $customEnvManager->ensureAppKey();
        $this->assertSame('base64:InitialOriginalSecretKey1234567890=', $key);

        @File::delete($tempEnvPath);
    }

    /**
     * Test 4: AdminAccountManager provisions the Super Admin and is
     * create-only: an existing admin (matched by email) is preserved, never
     * overwritten. This blocks the re-opened install wizard from being
     * abused to silently change a live super admin's password.
     */
    public function test_admin_account_manager_creates_and_preserves_super_admin(): void
    {
        $adminEmail = 'superadmin_' . uniqid() . '@leadx.com';

        $createResult = $this->adminManager->createOrUpdateAdmin([
            'name' => 'Initial Super Admin',
            'username' => 'superadmin',
            'email' => $adminEmail,
            'password' => 'SecurePass123!',
        ]);

        $this->assertTrue($createResult['success']);
        $this->assertFalse($createResult['skipped']);
        $this->assertDatabaseHas('admins', ['email' => $adminEmail]);
        $this->assertDatabaseHas('users', ['email' => $adminEmail, 'role' => 'admin']);

        // A second call with the same email must NOT overwrite the existing
        // credentials or rename the admin — it reports skipped=true and the
        // original account stays intact.
        $repeatResult = $this->adminManager->createOrUpdateAdmin([
            'name' => 'Hijacker Attempt Name',
            'username' => 'hijacker',
            'email' => $adminEmail,
            'password' => 'AttackerPass999!',
        ]);

        $this->assertTrue($repeatResult['success']);
        $this->assertTrue($repeatResult['skipped']);

        // Original name/username preserved, no row added or modified.
        $this->assertDatabaseHas('admins', [
            'email' => $adminEmail,
            'name' => 'Initial Super Admin',
            'username' => 'superadmin',
        ]);
        $this->assertSame(1, Admin::where('email', $adminEmail)->count());
        $this->assertDatabaseHas('users', [
            'email' => $adminEmail,
            'role' => 'admin',
            'name' => 'Initial Super Admin',
            'username' => 'superadmin',
        ]);
        $this->assertSame(1, User::where('email', $adminEmail)->count());
    }

    /**
     * Test 5: InstallationStateManager manages multi-location lock files and telemetry.
     */
    public function test_installation_state_manager_multi_location_locks_and_telemetry(): void
    {
        // 1. Lock the application
        $this->stateManager->lock(['locked_by' => 'test_suite']);
        $this->assertTrue($this->stateManager->isInstalled());

        $telemetry = $this->stateManager->getTelemetry();
        $this->assertTrue($telemetry['is_installed']);
        $this->assertNotEmpty($telemetry['lock_files_found']);

        // 2. Unlock the application
        $this->stateManager->unlock();
        $this->assertFalse($this->stateManager->isInstalled());
    }

    /**
     * Test 6: CheckInstallation middleware blocks unauthorized visitors from accessing installer when locked.
     */
    public function test_middleware_blocks_unauthorized_access_when_installer_is_locked(): void
    {
        $this->stateManager->lock(['locked_by' => 'test_lock']);

        // Enable middleware enforcement during testing
        config(['installer.testing_middleware' => true]);

        // Standard web browser request is redirected to /
        $response = $this->get('/install');
        $response->assertRedirect('/');

        // JSON/AJAX request receives HTTP 403 Forbidden
        $ajaxResponse = $this->getJson('/install');
        $ajaxResponse->assertStatus(403);
        $ajaxResponse->assertJson(['success' => false]);
    }

    /**
     * Test 7: CheckInstallation middleware redirects uninstalled visitors to /install.
     */
    public function test_middleware_redirects_uninstalled_visitors_to_installer(): void
    {
        $this->stateManager->unlock();

        config(['installer.testing_middleware' => true]);

        $response = $this->get('/');
        $response->assertRedirect('/install');
    }

    /**
     * Test 8: Super Admin can access Recovery Center and execute safe diagnostic actions.
     */
    public function test_admin_can_access_recovery_center_and_run_safe_actions(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'recovery_admin_' . uniqid() . '@leadx.com',
        ]);

        // Scope any .env writes to a temp file so the suite never mutates the
        // real repository .env (and stops piling plaintext .env backups into
        // storage/app/installer/backups). The controller's EnvironmentManager
        // is resolved through the container, so binding an instance here
        // redirects its update() calls to the temp path.
        $tempEnv = storage_path('app/test_recovery_env_' . uniqid() . '.env');
        File::put($tempEnv, "APP_NAME=TestApp\nAPP_URL=http://localhost\nQUEUE_CONNECTION=sync\n");
        app()->instance(EnvironmentManager::class, new EnvironmentManager($tempEnv));

        // 1. View recovery center
        $response = $this->actingAs($admin, 'admin')->get(route('admin.installer.recovery'));
        $response->assertOk();
        $response->assertSee('System Installation and Recovery Diagnostics');
        $response->assertSee('Maintenance and Recovery Actions');

        // 2. Run safe forward migrations action
        $migrateResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.installer.recovery.action'), ['action' => 'run_migrations']);
        $migrateResponse->assertRedirect();
        $migrateResponse->assertSessionHas('status');

        // 3. Clear cache action
        $cacheResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.installer.recovery.action'), ['action' => 'clear_cache']);
        $cacheResponse->assertRedirect();
        $cacheResponse->assertSessionHas('status');

        // 4. Test DB action
        $dbResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.installer.recovery.action'), ['action' => 'test_db']);
        $dbResponse->assertRedirect();

        // 5. Update environment settings safely
        $envResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.installer.recovery.action'), [
                'action' => 'update_env',
                'app_name' => 'LeadX Production',
                'app_url' => 'https://leadx.company.com',
                'queue_driver' => 'database',
            ]);
        $envResponse->assertRedirect();
        $envResponse->assertSessionHas('status');

        // Confirm the .env write landed in the temp file, not the repository .env
        $this->assertStringContainsString('APP_NAME="LeadX Production"', File::get($tempEnv));
        @File::delete($tempEnv);
    }

    /**
     * Test 9: The install database step never pre-fills live DB credentials
     * into the unauthenticated wizard HTML.
     */
    public function test_database_step_does_not_leak_env_credentials(): void
    {
        $this->stateManager->unlock();

        // Scope the wizard's .env reads to a temp file so the test never
        // touches the repository .env, then seed it with live-looking
        // credentials.
        $tempEnv = storage_path('app/test_db_leak_env_' . uniqid() . '.env');
        File::put($tempEnv, "DB_HOST=db.internal.example\nDB_DATABASE=leadx_live\nDB_USERNAME=live_user\nDB_PASSWORD=TopSecretLivePassword123\n");
        app()->instance(EnvironmentManager::class, new EnvironmentManager($tempEnv));

        $response = $this->get(route('install.database'));
        $response->assertOk();

        // The live password must never appear in the rendered form HTML.
        $response->assertDontSee('TopSecretLivePassword123');

        @File::delete($tempEnv);
    }

    /**
     * Test 9b: The admin step refuses to mint a new admin on a live system
     * where admin accounts already exist (post-unlock backdoor guard).
     */
    public function test_save_admin_refuses_when_admins_already_exist(): void
    {
        $this->stateManager->unlock();

        Admin::factory()->create(['email' => 'existing_admin@leadx.com']);

        $response = $this->post(route('install.save-admin'), [
            'name' => 'Attacker',
            'email' => 'attacker@leadx.com',
            'username' => 'attacker',
            'password' => 'AttackerPass123!',
            'password_confirmation' => 'AttackerPass123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // No new admin row for the attacker email.
        $this->assertDatabaseMissing('admins', ['email' => 'attacker@leadx.com']);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@leadx.com']);
    }

    /**
     * Test 9c: The unauthenticated wizard cannot probe arbitrary or internal
     * database hosts (SSRF / DB re-aiming guard).
     */
    public function test_database_test_rejects_internal_and_metadata_hosts(): void
    {
        $this->stateManager->unlock();

        foreach (['10.0.0.5', '192.168.1.10', '172.16.0.1', '169.254.169.254', '100.100.100.200', '0.0.0.0'] as $internalHost) {
            $response = $this->post(route('install.test-database'), [
                'db_host' => $internalHost,
                'db_port' => 3306,
                'db_database' => 'leadx',
                'db_username' => 'probe',
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('db_test_result');
            $result = session('db_test_result');
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('reserved or internal', $result['message']);
        }

        // A malformed database name is rejected before any connection attempt.
        $this->stateManager->unlock();
        $badDb = $this->post(route('install.test-database'), [
            'db_host' => 'db.example.com',
            'db_port' => 3306,
            'db_database' => 'leadx;evil=1',
            'db_username' => 'probe',
        ]);
        $badDb->assertRedirect();
        $this->assertFalse(session('db_test_result')['success']);
    }

    /**
     * Test 9: Unauthenticated guest cannot access Super Admin Recovery Center.
     */
    public function test_unauthenticated_user_cannot_access_recovery_center(): void
    {
        $response = $this->get(route('admin.installer.recovery'));
        $response->assertRedirect(route('admin.login'));
    }

    /**
     * Test 10: Artisan commands installer:status, installer:lock, and reset:install work correctly.
     */
    public function test_artisan_installer_cli_commands(): void
    {
        // 1. installer:status
        $exitCode = Artisan::call('installer:status');
        $this->assertSame(0, $exitCode);

        // 2. installer:lock --force
        $lockCode = Artisan::call('installer:lock', ['--force' => true]);
        $this->assertSame(0, $lockCode);
        $this->assertTrue($this->stateManager->isInstalled());

        // 3. reset:install --force
        $resetCode = Artisan::call('reset:install', ['--force' => true]);
        $this->assertSame(0, $resetCode);
        $this->assertFalse($this->stateManager->isInstalled());
    }
}
