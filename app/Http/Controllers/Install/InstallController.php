<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\Installer\AdminAccountManager;
use App\Services\Installer\DatabaseManager;
use App\Services\Installer\EnvironmentManager;
use App\Services\Installer\InstallationStateManager;
use App\Services\Installer\RequirementChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Throwable;

class InstallController extends Controller
{
    public function __construct(
        protected RequirementChecker $checker,
        protected DatabaseManager $dbManager,
        protected EnvironmentManager $envManager,
        protected AdminAccountManager $adminManager,
        protected InstallationStateManager $stateManager
    ) {}

    protected function sessionStep(int $step): void
    {
        session(['install_step' => $step]);
    }

    protected function isInstalled(): bool
    {
        return $this->stateManager->isInstalled();
    }

    // ── Step 1: Environment Requirements ──────────────────────

    public function requirements(Request $request): View|RedirectResponse|JsonResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(1);

        $checks = [
            'PHP >= 8.2' => [
                'pass' => version_compare(PHP_VERSION, RequirementChecker::MIN_PHP_VERSION, '>='),
                'value' => PHP_VERSION,
            ],
            'Laravel 12' => [
                'pass' => defined('Illuminate\Foundation\Application::VERSION'),
                'value' => app()->version(),
            ],
            'Writable storage/' => [
                'pass' => is_writable(storage_path()),
                'value' => is_writable(storage_path()) ? 'Writable' : 'Not writable',
            ],
            'Writable bootstrap/cache/' => [
                'pass' => is_writable(base_path('bootstrap/cache')),
                'value' => is_writable(base_path('bootstrap/cache')) ? 'Writable' : 'Not writable',
            ],
            'Database driver (MySQL/MariaDB)' => [
                'pass' => extension_loaded('pdo_mysql'),
                'value' => extension_loaded('pdo_mysql') ? 'PDO MySQL loaded' : 'PDO MySQL missing',
            ],
            'OpenSSL extension' => [
                'pass' => extension_loaded('openssl'),
                'value' => extension_loaded('openssl') ? 'Loaded' : 'Missing',
            ],
            'Mbstring extension' => [
                'pass' => extension_loaded('mbstring'),
                'value' => extension_loaded('mbstring') ? 'Loaded' : 'Missing',
            ],
            'Tokenizer extension' => [
                'pass' => extension_loaded('tokenizer'),
                'value' => extension_loaded('tokenizer') ? 'Loaded' : 'Missing',
            ],
            'JSON extension' => [
                'pass' => extension_loaded('json'),
                'value' => extension_loaded('json') ? 'Loaded' : 'Missing',
            ],
            'Ctype extension' => [
                'pass' => extension_loaded('ctype'),
                'value' => extension_loaded('ctype') ? 'Loaded' : 'Missing',
            ],
            'Fileinfo extension' => [
                'pass' => extension_loaded('fileinfo'),
                'value' => extension_loaded('fileinfo') ? 'Loaded' : 'Missing',
            ],
            'Composer autoload' => [
                'pass' => file_exists(base_path('vendor/autoload.php')),
                'value' => file_exists(base_path('vendor/autoload.php')) ? 'Found' : 'Missing — run composer install',
            ],
        ];

        $allPass = ! in_array(false, array_column($checks, 'pass'), true);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'all_pass' => $allPass,
                'checks' => $checks,
            ]);
        }

        return view('install.requirements', compact('checks', 'allPass'));
    }

    // ── Step 2: Application Configuration ─────────────────────

    public function environment(Request $request): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(2);

        $env = $this->envManager->read();

        return view('install.environment', compact('env'));
    }

    public function saveEnvironment(Request $request): RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url|max:255',
            'timezone' => 'required|string|max:64',
            'currency' => 'required|string|max:10',
            'currency_symbol' => 'nullable|string|max:10',
        ], [
            'app_name.required' => 'Application name is required.',
            'app_url.required' => 'Application URL is required.',
            'app_url.url' => 'Please enter a valid URL.',
        ]);

        $this->envManager->update([
            'APP_NAME' => $validated['app_name'],
            'APP_URL' => rtrim($validated['app_url'], '/'),
            'APP_TIMEZONE' => $validated['timezone'],
            'APP_CURRENCY' => strtoupper($validated['currency']),
            'APP_CURRENCY_SYMBOL' => $validated['currency_symbol'] ?? '$',
            'APP_LOCALE' => 'en',
        ]);

        return redirect()->route('install.database');
    }

    // ── Step 3: Database Configuration ────────────────────────

    public function database(Request $request): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(3);

        $env = $this->envManager->read();
        $testResult = session('db_test_result');

        return view('install.database', compact('env', 'testResult'));
    }

    public function testDatabase(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->isInstalled()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Already installed.'], 403);
            }

            return redirect('/');
        }

        $validated = $request->validate([
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string',
        ]);

        // The wizard is unauthenticated: never allow it to be used as a
        // network probe against arbitrary hosts, and only allow the
        // application's database to be re-aimed at safe locations.
        if ($error = $this->dbManager->validateDbTarget($validated['db_host'], $validated['db_port'], $validated['db_database'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            return back()->withInput()->with('db_test_result', [
                'success' => false,
                'message' => $error,
            ]);
        }

        $result = $this->dbManager->testConnection([
            'host' => $validated['db_host'],
            'port' => $validated['db_port'],
            'database' => $validated['db_database'],
            'username' => $validated['db_username'],
            'password' => $validated['db_password'] ?? '',
        ]);

        // If AJAX call, return JSON response immediately
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result);
        }

        // Persist form values in session for traditional form post
        $request->session()->flash('db_form_values', [
            'db_host' => $validated['db_host'],
            'db_port' => $validated['db_port'],
            'db_database' => $validated['db_database'],
            'db_username' => $validated['db_username'],
        ]);

        return back()->with('db_test_result', $result);
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $validated = $request->validate([
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string',
        ]);

        if ($error = $this->dbManager->validateDbTarget($validated['db_host'], $validated['db_port'], $validated['db_database'])) {
            return back()->withInput()->with('error', $error);
        }

        // Atomically update .env without touching other keys
        $this->envManager->update([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $validated['db_host'],
            'DB_PORT' => $validated['db_port'],
            'DB_DATABASE' => $validated['db_database'],
            'DB_USERNAME' => $validated['db_username'],
            'DB_PASSWORD' => $validated['db_password'] ?? '',
        ]);

        // Refresh in-memory config
        Config::set('database.connections.mysql.host', $validated['db_host']);
        Config::set('database.connections.mysql.port', $validated['db_port']);
        Config::set('database.connections.mysql.database', $validated['db_database']);
        Config::set('database.connections.mysql.username', $validated['db_username']);
        Config::set('database.connections.mysql.password', $validated['db_password'] ?? '');

        return redirect()->route('install.migrate');
    }

    // ── Step 4: Database Installation (Migrations) ────────────

    public function migrate(Request $request): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(4);

        return view('install.migrate');
    }

    public function runMigrations(Request $request): JsonResponse
    {
        if ($this->isInstalled()) {
            return response()->json(['success' => false, 'message' => 'Already installed.'], 403);
        }

        $request->validate([
            'force' => 'required|accepted',
        ]);

        try {
            // Reconnect database cleanly
            DB::purge('mysql');

            // Run migrations forward safely (never migrate:fresh or reset)
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            // Seed database idempotently if seeder exists
            $seeded = false;
            if (file_exists(database_path('seeders/DatabaseSeeder.php'))) {
                Artisan::call('db:seed', ['--force' => true]);
                $seeded = true;
            }

            return response()->json([
                'success' => true,
                'message' => 'Migrations completed successfully.',
                'migration_output' => $output,
                'seeded' => $seeded,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $this->dbManager->sanitizeDbError($e->getMessage()),
            ], 422);
        }
    }

    // ── Step 5: Initial Super Admin Setup ─────────────────────

    public function admin(Request $request): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(5);

        return view('install.admin');
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        // A live system already has admin accounts (e.g. after unlock via the
        // recovery center). The wizard must not let an unauthenticated visitor
        // mint a new admin — refuse the step entirely.
        if ($this->adminManager->hasAdminAccount()) {
            return back()->withInput()
                ->with('error', 'An admin account already exists. To change credentials, unlock the installer and use the admin recovery center instead of the wizard.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $result = $this->adminManager->createOrUpdateAdmin($validated);

        if (! $result['success']) {
            return back()->withInput()->with('error', 'Failed to create admin: ' . $result['error']);
        }

        $request->session()->put('admin_created', true);
        $request->session()->put('admin_email', $validated['email']);

        return redirect()->route('install.config');
    }

    // ── Step 6: System Configuration ──────────────────────────

    public function config(Request $request): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(6);

        return view('install.config');
    }

    public function saveConfig(Request $request): RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $request->validate([
            'mail_mailer' => 'nullable|string|max:64',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|in:tls,ssl,null',
            'mail_from_address' => 'nullable|email|max:255',
            'pusher_app_id' => 'nullable|string|max:255',
            'pusher_app_key' => 'nullable|string|max:255',
            'pusher_app_secret' => 'nullable|string|max:255',
            'pusher_app_cluster' => 'nullable|string|max:64',
            'google_client_id' => 'nullable|string|max:255',
            'google_client_secret' => 'nullable|string|max:255',
            // rabbitmq is not a supported Laravel queue driver; picking it
            // would write QUEUE_CONNECTION=rabbitmq and silently break every
            // queued email/push. Keep only real drivers.
            'queue_driver' => 'nullable|in:sync,database,redis,beanstalkd,sqs',
            'scheduler_enabled' => 'nullable|in:1,0',
        ]);

        $appName = $this->envManager->get('APP_NAME', 'LeadX');

        // Mark the wizard as in progress from its final configuration step,
        // so the completion screen stays reachable (and a refresh of it works)
        // even after finish() locks the application.
        $request->session()->put('install_in_progress', true);

        $updates = [
            'MAIL_MAILER' => $request->input('mail_mailer', 'log'),
            'MAIL_HOST' => $request->input('mail_host', ''),
            'MAIL_PORT' => $request->input('mail_port', ''),
            'MAIL_USERNAME' => $request->input('mail_username', ''),
            'MAIL_PASSWORD' => $request->input('mail_password', ''),
            'MAIL_ENCRYPTION' => $request->input('mail_encryption', ''),
            'MAIL_FROM_ADDRESS' => $request->input('mail_from_address', ''),
            'MAIL_FROM_NAME' => $appName,
            'PUSHER_APP_ID' => $request->input('pusher_app_id', ''),
            'PUSHER_APP_KEY' => $request->input('pusher_app_key', ''),
            'PUSHER_APP_SECRET' => $request->input('pusher_app_secret', ''),
            'PUSHER_APP_CLUSTER' => $request->input('pusher_app_cluster', ''),
            'GOOGLE_CLIENT_ID' => $request->input('google_client_id', ''),
            'GOOGLE_CLIENT_SECRET' => $request->input('google_client_secret', ''),
            'QUEUE_CONNECTION' => $request->input('queue_driver', 'sync'),
            'INSTALL_SCHEDULER' => $request->input('scheduler_enabled', '0') === '1' ? '1' : '0',
        ];

        $this->envManager->update($updates);

        return redirect()->route('install.verify');
    }

    // ── Step 7: Final Verification ────────────────────────────

    public function verify(Request $request): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(7);

        // Ensure APP_KEY exists without overwriting
        $this->envManager->ensureAppKey();

        $checks = [
            'Database connectivity' => $this->checkDbConnection(),
            'Migrations applied' => $this->checkMigrations(),
            'Storage writable' => [
                'pass' => is_writable(storage_path('app')) && is_writable(storage_path('framework')) && is_writable(storage_path('logs')),
                'value' => 'storage/app, framework, logs',
            ],
            'Cache writable' => [
                'pass' => is_writable(storage_path('framework/cache')),
                'value' => 'storage/framework/cache',
            ],
            'Application key set' => [
                'pass' => ! empty(config('app.key')) || ! empty(env('APP_KEY')),
                'value' => (! empty(config('app.key')) || ! empty(env('APP_KEY'))) ? 'APP_KEY configured' : 'Missing APP_KEY',
            ],
            'Required directories exist' => $this->checkRequiredDirs(),
            'Admin account exists' => $this->checkAdminExists(),
            'Routes available' => $this->checkRoutes(),
        ];

        $allPass = ! in_array(false, array_column($checks, 'pass'), true);

        return view('install.verify', compact('checks', 'allPass'));
    }

    // ── Step 8: Finalize & Lock ───────────────────────────────

    public function finish(Request $request): View|RedirectResponse
    {
        // If already locked and user is just visiting finish directly without active install session
        if ($this->isInstalled() && ! $request->session()->has('install_in_progress')) {
            return redirect('/');
        }

        $this->sessionStep(8);

        try {
            // Lock the installer across multi-locations
            $this->stateManager->lock([
                'installed_by' => $request->session()->get('admin_email', 'installer_wizard'),
                'installed_at' => now()->toIso8601String(),
                'ip' => $request->ip(),
            ]);

            // Lock the instance into production mode - .env.example ships APP_DEBUG=true
            // and no earlier wizard step turns it off.
            $this->envManager->update([
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
            ]);

            // Clear all application caches
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');

            // Log scheduler notice if requested
            if (env('INSTALL_SCHEDULER') === '1') {
                $command = 'php ' . base_path('artisan') . ' schedule:run --no-interaction';
                File::append(storage_path('logs/install.log'), "[Scheduler] Add to crontab: * * * * * $command\n");
            }

            // If a non-sync queue driver was chosen, web-push and email jobs
            // queue on the "notifications" queue — a worker NOT started with
            // that queue will never process them (silent loss). Warn loudly.
            $queueDriver = env('QUEUE_CONNECTION', 'sync');
            if ($queueDriver !== 'sync') {
                $workerCmd = "php " . base_path('artisan') . " queue:work database --queue=notifications,default --tries=3 --timeout=30";
                File::append(storage_path('logs/install.log'), "[Queue] Start a worker for the notifications queue: $workerCmd\n");
                $request->session()->flash('queue_warning', [
                    'driver' => $queueDriver,
                    'worker_command' => 'php artisan queue:work '.($queueDriver === 'database' ? 'database' : $queueDriver).' --queue=notifications,default --tries=3 --timeout=30',
                ]);
            }

            // Retrieve admin email for success screen and clean up transient session keys
            $adminEmail = $request->session()->get('admin_email') ?? \App\Models\Admin::first()?->email;
            // Transient wizard markers are cleared now that the install is
            // complete and the app is locked, so a repeated POST /install/finish
            // redirects to '/' instead of re-running finalization.
            $request->session()->forget([
                'install_step',
                'db_test_result',
                'db_form_values',
                'admin_created',
                'admin_email',
                'install_in_progress',
            ]);

            return view('install.finish', compact('adminEmail'));
        } catch (Throwable $e) {
            // The wizard is unauthenticated: log the real diagnostic, show a
            // generic message so internal paths and PDO error text never
            // reach a random visitor.
            Log::error('Installer finalization failed', ['exception' => $e]);

            return redirect()->route('install.verify')
                ->with('error', 'Installation finalization encountered an issue. See the application logs for details.');
        }
    }

    // ── Internal Diagnostics Helpers ──────────────────────────

    protected function checkDbConnection(): array
    {
        try {
            DB::connection()->getPdo();

            return ['pass' => true, 'value' => 'Connected to ' . Config::get('database.connections.mysql.database')];
        } catch (Throwable $e) {
            return ['pass' => false, 'value' => $this->dbManager->sanitizeDbError($e->getMessage())];
        }
    }

    protected function checkMigrations(): array
    {
        try {
            $count = DB::table('migrations')->count();

            return ['pass' => $count > 0, 'value' => $count . ' migrations applied'];
        } catch (Throwable) {
            return ['pass' => false, 'value' => 'Could not read migrations table'];
        }
    }

    protected function checkRequiredDirs(): array
    {
        $dirs = [
            storage_path('app'),
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/views'),
            storage_path('logs'),
            public_path('assets'),
            public_path('uploads'),
        ];

        $missing = [];
        foreach ($dirs as $dir) {
            if (! File::isDirectory($dir) || ! is_writable($dir)) {
                $missing[] = $dir;
            }
        }

        if (! empty($missing)) {
            return ['pass' => false, 'value' => count($missing) . ' directories missing or not writable'];
        }

        return ['pass' => true, 'value' => 'All directories exist and are writable'];
    }

    protected function checkAdminExists(): array
    {
        try {
            $count = \App\Models\Admin::count() + \App\Models\User::where('role', 'admin')->count();

            return ['pass' => $count > 0, 'value' => $count . ' admin user(s) found'];
        } catch (Throwable $e) {
            return ['pass' => false, 'value' => 'Could not query admin accounts: ' . $e->getMessage()];
        }
    }

    protected function checkRoutes(): array
    {
        try {
            $count = count(Route::getRoutes());

            return ['pass' => $count > 0, 'value' => $count . ' routes registered'];
        } catch (Throwable $e) {
            return ['pass' => false, 'value' => 'Route check failed: ' . $e->getMessage()];
        }
    }
}
