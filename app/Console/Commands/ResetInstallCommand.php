<?php

namespace App\Console\Commands;

use App\Services\Installer\EnvironmentManager;
use App\Services\Installer\InstallationStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ResetInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:install 
                            {--force : Force the reset without interactive confirmation}
                            {--clear-env : Reset database and application credentials in .env while preserving APP_KEY}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely unlock the LeadX installation wizard without deleting database tables';

    public function handle(
        InstallationStateManager $stateManager,
        EnvironmentManager $envManager
    ): int {
        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                'Are you sure you want to unlock the LeadX installer? (Existing database tables will NOT be deleted)',
                false
            );

            if (! $confirmed) {
                $this->warn('Operation cancelled.');

                return 1;
            }
        }

        // 1. Remove all lock files
        $stateManager->unlock();
        $this->info('Removed installer lock files.');

        // 2. Optionally reset DB credentials in .env (preserving APP_KEY)
        if ($this->option('clear-env')) {
            $envManager->update([
                'DB_DATABASE' => '',
                'DB_USERNAME' => '',
                'DB_PASSWORD' => '',
            ]);
            $this->info('Cleared database credentials in .env (APP_KEY preserved).');
        }

        // 3. Clear application caches
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        $this->info('Application caches cleared.');

        $this->newLine();
        $this->info('LeadX installer successfully unlocked. Visitors to "/" will be redirected to "/install".');

        return 0;
    }
}
