<?php

namespace App\Services\Backup;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class BackupSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = config('leadx.backup.'.$key, $default);

        // Resolve lazily, so a fresh installation can register its scheduler
        // before the settings table exists.
        if (! Schema::hasTable('settings')) {
            return $fallback;
        }

        return Setting::where('key', 'leadx_backup_'.$key)->value('value') ?? $fallback;
    }

    public static function save(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => 'leadx_backup_'.$key], ['value' => $value]);
        }
    }
}
