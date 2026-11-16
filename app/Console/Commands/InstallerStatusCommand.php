<?php

namespace App\Console\Commands;

use App\Services\Installer\InstallationStateManager;
use Illuminate\Console\Command;

class InstallerStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'installer:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display comprehensive status and telemetry for the LeadX installer';

    public function handle(InstallationStateManager $stateManager): int
    {
        $telemetry = $stateManager->getTelemetry();

        $this->newLine();
        $this->info('=== LeadX Installation & Security Telemetry ===');
        $this->newLine();

        $rows = [
            ['Installer Status', $telemetry['is_installed'] ? '<fg=green;options=bold>LOCKED (Production Safe)</>' : '<fg=yellow;options=bold>UNLOCKED (Install Mode)</>'],
            ['PHP Version', $telemetry['php_version']],
            ['Laravel Version', $telemetry['laravel_version']],
            ['Lock Files Found', empty($telemetry['lock_files_found']) ? 'None' : implode("\n", $telemetry['lock_files_found'])],
            ['Database Connected', $telemetry['database']['connected'] ? '<fg=green>YES</>' : '<fg=red>NO</>'],
            ['Database Version', $telemetry['database']['version']],
            ['Database Tables', (string) $telemetry['database']['table_count']],
            ['Applied Migrations', (string) $telemetry['database']['migration_count']],
            ['Admin Accounts', (string) $telemetry['database']['admin_count']],
            ['Storage Writable', $telemetry['storage_writable'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'],
            ['Bootstrap Cache Writable', $telemetry['bootstrap_cache_writable'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'],
        ];

        $this->table(['Metric', 'Value'], $rows);

        return 0;
    }
}
