<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Installer\AdminAccountManager;
use App\Services\Installer\DatabaseManager;
use App\Services\Installer\EnvironmentManager;
use App\Services\Installer\InstallationStateManager;
use App\Services\Installer\RequirementChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class InstallRecoveryController extends Controller
{
    public function __construct(
        protected InstallationStateManager $stateManager,
        protected RequirementChecker $checker,
        protected DatabaseManager $dbManager,
        protected EnvironmentManager $envManager,
        protected AdminAccountManager $adminManager
    ) {}

    /**
     * Display the System Installation & Recovery Diagnostics Center.
     */
    public function index(): View
    {
        $telemetry = $this->stateManager->getTelemetry();
        $requirements = $this->checker->checkAll();

        // Expose only the non-secret keys the recovery UI edits — never the
        // full parsed .env map (DB_PASSWORD, PUSHER_APP_SECRET,
        // GOOGLE_CLIENT_SECRET, MAIL_PASSWORD, ...) to a template.
        $fullEnv = $this->envManager->read();
        $env = array_intersect_key(
            $fullEnv,
            array_flip(['APP_NAME', 'APP_URL', 'QUEUE_CONNECTION']),
        );

        return view('admin.installer.recovery', compact('telemetry', 'requirements', 'env'));
    }

    /**
     * Execute a safe recovery or diagnostic action.
     */
    public function performAction(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $admin = auth('admin')->user();

        Log::info('Admin triggered installer recovery action', [
            'action' => $action,
            'admin_id' => $admin?->id,
            'admin_email' => $admin?->email,
            'ip' => $request->ip(),
        ]);

        try {
            switch ($action) {
                case 'run_migrations':
                    Artisan::call('migrate', ['--force' => true]);
                    $output = Artisan::output();

                    return back()->with('status', 'Forward database migrations applied safely. Output: ' . trim($output));

                case 'clear_cache':
                    Artisan::call('config:clear');
                    Artisan::call('route:clear');
                    Artisan::call('view:clear');
                    Artisan::call('cache:clear');

                    return back()->with('status', 'System caches cleared successfully.');

                case 'rebuild_cache':
                    Artisan::call('config:cache');
                    Artisan::call('route:cache');
                    Artisan::call('view:cache');

                    return back()->with('status', 'System configuration, routes, and views compiled and cached.');

                case 'test_db':
                    $config = Config::get('database.connections.mysql');
                    $result = $this->dbManager->testConnection([
                        'host' => $config['host'] ?? '127.0.0.1',
                        'port' => $config['port'] ?? 3306,
                        'database' => $config['database'] ?? '',
                        'username' => $config['username'] ?? '',
                        'password' => $config['password'] ?? '',
                    ]);

                    return back()->with(
                        $result['success'] ? 'status' : 'error',
                        $result['message'] . ($result['success'] ? " (Engine: {$result['version']}, Tables: {$result['table_count']})" : '')
                    );

                case 'ensure_key':
                    $key = $this->envManager->ensureAppKey();

                    return back()->with('status', 'Application encryption key verified/secured.');

                case 'lock_installer':
                    $this->stateManager->lock([
                        'locked_by' => 'admin_recovery',
                        'admin_email' => $admin?->email,
                        'locked_at' => now()->toIso8601String(),
                    ]);

                    return back()->with('status', 'Installer locked across all locations.');

                case 'unlock_installer':
                    $this->stateManager->unlock();

                    return back()->with('status', 'Installer unlocked. Public routes will redirect to installer until locked again.');

                case 'update_env':
                    $validated = $request->validate([
                        'app_url' => 'nullable|url|max:255',
                        'app_name' => 'nullable|string|max:255',
                        'queue_driver' => 'nullable|in:sync,database,redis',
                    ]);

                    $updates = array_filter([
                        'APP_URL' => $validated['app_url'] ?? null,
                        'APP_NAME' => $validated['app_name'] ?? null,
                        'QUEUE_CONNECTION' => $validated['queue_driver'] ?? null,
                    ]);

                    if (! empty($updates)) {
                        $this->envManager->update($updates);
                    }

                    return back()->with('status', 'Environment settings updated safely without altering business data.');

                default:
                    return back()->with('error', 'Unrecognized recovery action requested.');
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Action failed: ' . $this->dbManager->sanitizeDbError($e->getMessage()));
        }
    }
}
