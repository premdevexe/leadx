<?php

namespace App\Console\Commands;

use App\Services\Installer\InstallationStateManager;
use Illuminate\Console\Command;

class InstallerLockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'installer:lock {--force : Force locking without interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually lock the LeadX installer across multi-location lock files';

    public function handle(InstallationStateManager $stateManager): int
    {
        if (! $this->option('force') && ! $this->confirm('Lock the LeadX installer and enable production protection?', true)) {
            $this->warn('Operation cancelled.');

            return 1;
        }

        $stateManager->lock([
            'locked_by' => 'cli_artisan',
            'locked_at' => now()->toIso8601String(),
        ]);

        $this->info('LeadX installer successfully locked across all designated locations.');

        return 0;
    }
}
