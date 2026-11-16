<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class InstallController extends Controller
{
    private function sessionStep(int $step): void
    {
        session(['install_step' => $step]);
    }

    private function step(): int
    {
        return (int) (session('install_step') ?? 1);
    }

    // ── Step 1: Environment Requirements ──────────────────────

    public function requirements(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(1);

        $checks = [
            'PHP >= 8.2' => [
                'pass' => version_compare(PHP_VERSION, '8.2.0', '>='),
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

        $allPass = ! in_array(false, array_column($checks, 'pass'));

        return view('install.requirements', compact('checks', 'allPass'));
    }

    // ── Step 2: Application Configuration ─────────────────────

    public function environment(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(2);

        $env = $this->readEnv();

        return view('install.environment', [
            'env' => $env,
        ]);
    }

    public function saveEnvironment(Request $request)
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

        $env = $this->readEnv();
        $env['APP_NAME'] = $validated['app_name'];
        $env['APP_URL'] = rtrim($validated['app_url'], '/');
        $env['APP_TIMEZONE'] = $validated['timezone'];
        $env['APP_CURRENCY'] = strtoupper($validated['currency']);
        $env['APP_CURRENCY_SYMBOL'] = $validated['currency_symbol'] ?? '$';
        $env['APP_LOCALE'] = 'en';

        $this->writeEnv($env);

        return redirect()->route('install.database');
    }

    // ── Step 3: Database Configuration ────────────────────────

    public function database(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(3);

        $env = $this->readEnv();
        $testResult = session('db_test_result');

        return view('install.database', [
            'env' => $env,
            'testResult' => $testResult,
        ]);
    }

    public function testDatabase(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $request->validate([
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string',
        ]);

        try {
            $config = [
                'driver' => 'mysql',
                'host' => $request->input('db_host', '127.0.0.1'),
                'port' => $request->input('db_port', 3306),
                'database' => $request->input('db_database'),
                'username' => $request->input('db_username'),
                'password' => $request->input('db_password', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];

            DB::purge('install_test');
            Config::set('database.connections.install_test', $config);

            DB::connection('install_test')->getPdo();

            // Try to read existing tables
            $tables = [];
            try {
                $tables = DB::connection('install_test')
                    ->select('SHOW TABLES');
                $tables = array_map(fn($t) => array_values((array) $t)[0] ?? null, $tables);
                $tables = array_filter($tables);
            } catch (\Throwable $e) {
                // User may not have SHOW TABLES privilege; that's fine
            }

            $result = [
                'success' => true,
                'message' => 'Database connection successful.',
                'tables' => array_values($tables),
                'table_count' => count($tables),
            ];
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'message' => 'Connection failed: ' . $this->sanitizeDbError($e->getMessage()),
                'tables' => [],
                'table_count' => 0,
            ];
        }

        // Persist form values in session so user doesn't re-type them
        $request->session()->flash('db_form_values', [
            'db_host' => $request->input('db_host'),
            'db_port' => $request->input('db_port'),
            'db_database' => $request->input('db_database'),
            'db_username' => $request->input('db_username'),
        ]);

        return back()->with('db_test_result', $result);
    }

    public function saveDatabase(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $request->validate([
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string',
        ]);

        $env = $this->readEnv();
        $env['DB_CONNECTION'] = 'mysql';
        $env['DB_HOST'] = $request->input('db_host', '127.0.0.1');
        $env['DB_PORT'] = $request->input('db_port', 3306);
        $env['DB_DATABASE'] = $request->input('db_database');
        $env['DB_USERNAME'] = $request->input('db_username');
        $env['DB_PASSWORD'] = $request->input('db_password', '');

        $this->writeEnv($env);

        return redirect()->route('install.migrate');
    }

    // ── Step 4: Database Installation ─────────────────────────

    public function migrate(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(4);

        return view('install.migrate');
    }

    public function runMigrations(Request $request)
    {
        if ($this->isInstalled()) {
            return response()->json(['success' => false, 'message' => 'Already installed.']);
        }

        $request->validate([
            'force' => 'required|accepted',
        ]);

        try {
            // Refresh database configuration
            Artisan::call('config:clear', []);

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            $output = Artisan::output();

            // Seed database if seeder exists
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
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ]);
        }
    }

    // ── Step 5: Initial Super Admin Setup ─────────────────────

    public function admin(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(5);

        return view('install.admin');
    }

    public function saveAdmin(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
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

        try {
            // 1. Create or update Admin in admins table (for /admin/login)
            \App\Models\Admin::updateOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                    'email_verified_at' => now(),
                ]
            );

            // 2. Also create or update User in users table (for general /login)
            \App\Models\User::updateOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                    'role' => 'admin',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $request->session()->put('admin_created', true);
            $request->session()->put('admin_email', $validated['email']);

            return redirect()->route('install.config');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create admin: ' . $e->getMessage());
        }
    }

    // ── Step 6: System Configuration ──────────────────────────

    public function config(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(6);

        return view('install.config');
    }

    public function saveConfig(Request $request)
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
            'queue_driver' => 'nullable|in:sync,database,redis,rabbitmq',
            'scheduler_enabled' => 'nullable|in:1,0',
        ]);

        $env = $this->readEnv();

        // Mail settings
        $env['MAIL_MAILER'] = $request->input('mail_mailer', 'log');
        $env['MAIL_HOST'] = $request->input('mail_host', '');
        $env['MAIL_PORT'] = $request->input('mail_port', '');
        $env['MAIL_USERNAME'] = $request->input('mail_username', '');
        $env['MAIL_PASSWORD'] = $request->input('mail_password', '');
        $env['MAIL_ENCRYPTION'] = $request->input('mail_encryption', '');
        $env['MAIL_FROM_ADDRESS'] = $request->input('mail_from_address', '');
        $env['MAIL_FROM_NAME'] = $env['APP_NAME'];

        // Pusher settings
        $env['PUSHER_APP_ID'] = $request->input('pusher_app_id', '');
        $env['PUSHER_APP_KEY'] = $request->input('pusher_app_key', '');
        $env['PUSHER_APP_SECRET'] = $request->input('pusher_app_secret', '');
        $env['PUSHER_APP_CLUSTER'] = $request->input('pusher_app_cluster', '');

        // Google Calendar
        $env['GOOGLE_CLIENT_ID'] = $request->input('google_client_id', '');
        $env['GOOGLE_CLIENT_SECRET'] = $request->input('google_client_secret', '');

        // Queue
        $env['QUEUE_CONNECTION'] = $request->input('queue_driver', 'sync');

        // Scheduler
        $schedulerEnabled = $request->input('scheduler_enabled', '0') === '1';
        $env['INSTALL_SCHEDULER'] = $schedulerEnabled ? '1' : '0';

        $this->writeEnv($env);

        return redirect()->route('install.verify');
    }

    // ── Step 7: Final Verification ────────────────────────────

    public function verify(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(7);

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
                'pass' => ! empty(env('APP_KEY')),
                'value' => ! empty(env('APP_KEY')) ? 'APP_KEY configured' : 'Missing APP_KEY',
            ],
            'Required directories exist' => $this->checkRequiredDirs(),
            'Admin account exists' => $this->checkAdminExists(),
            'Routes available' => $this->checkRoutes(),
        ];

        $allPass = ! in_array(false, array_column($checks, 'pass'));

        return view('install.verify', compact('checks', 'allPass'));
    }

    // ── Step 8: Finalize ──────────────────────────────────────

    public function finish(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $this->sessionStep(8);

        try {
            // Create installation lock
            File::put(storage_path('app/installed.lock'), now()->toDateTimeString() . "\n");

            // Clear all caches
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');

            // Optimize for production
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            // Set up scheduler if requested
            if (env('INSTALL_SCHEDULER') === '1') {
                $this->addSchedulerCron();
            }

            // Clean up session
            $adminEmail = $request->session()->get('admin_email') ?? \App\Models\Admin::first()?->email;
            $request->session()->forget(['install_step', 'db_test_result', 'db_form_values', 'admin_created', 'admin_email']);

            return view('install.finish', [
                'admin_email' => $adminEmail,
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('install.verify')
                ->with('error', 'Installation finalization encountered an issue: ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────

    private function isInstalled(): bool
    {
        return File::exists(storage_path('app/installed.lock'));
    }

    private function readEnv(): array
    {
        $env = [];
        if (File::exists(base_path('.env'))) {
            $lines = File::lines(base_path('.env'));
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && ! str_starts_with($line, '#') && str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }
        }
        return $env;
    }

    private function writeEnv(array $env): void
    {
        $lines = [];
        foreach ($env as $key => $value) {
            $value = str_replace(['"', "'"], '', $value);
            $lines[] = $key . '=' . $value;
        }
        File::put(base_path('.env'), implode("\n", $lines) . "\n");
    }

    private function sanitizeDbError(string $message): string
    {
        // Strip credentials from error messages before displaying
        $message = preg_replace('/Unknown database \'[^\']*\'/', 'Unknown database (name hidden)', $message);
        $message = preg_replace('/Access denied for user \'[^\']*\'/', 'Access denied for user (credentials hidden)', $message);
        return $message;
    }

    private function checkDbConnection(): array
    {
        try {
            DB::connection()->getPdo();
            return ['pass' => true, 'value' => 'Connected to ' . Config::get('database.connections.mysql.database')];
        } catch (\Throwable $e) {
            return ['pass' => false, 'value' => $this->sanitizeDbError($e->getMessage())];
        }
    }

    private function checkMigrations(): array
    {
        try {
            $count = DB::table('migrations')->count();
            return ['pass' => $count > 0, 'value' => $count . ' migrations applied'];
        } catch (\Throwable $e) {
            return ['pass' => false, 'value' => 'Could not read migrations table'];
        }
    }

    private function checkRequiredDirs(): array
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

    private function checkAdminExists(): array
    {
        try {
            $count = \App\Models\Admin::count() + \App\Models\User::where('role', 'admin')->count();
            return ['pass' => $count > 0, 'value' => $count . ' admin user(s) found'];
        } catch (\Throwable $e) {
            return ['pass' => false, 'value' => 'Could not query admin accounts: ' . $e->getMessage()];
        }
    }

    private function checkRoutes(): array
    {
        try {
            $count = count(\Illuminate\Support\Facades\Route::getRoutes());
            return ['pass' => $count > 0, 'value' => $count . ' routes registered'];
        } catch (\Throwable $e) {
            return ['pass' => false, 'value' => 'Route check failed: ' . $e->getMessage()];
        }
    }

    private function addSchedulerCron(): void
    {
        // We don't auto-add system cron from PHP, but we log the command
        $command = 'php ' . base_path('artisan') . ' schedule:run --no-interaction';
        File::append(storage_path('logs/install.log'), "[Scheduler] Add this to your crontab:\n* * * * * $command\n");
    }
}
