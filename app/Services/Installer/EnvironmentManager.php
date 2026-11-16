<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\File;

class EnvironmentManager
{
    /**
     * Absolute path to .env file.
     */
    protected string $envPath;

    public function __construct(?string $envPath = null)
    {
        $this->envPath = $envPath ?: base_path('.env');
    }

    /**
     * Read current environment values as an associative key-value map.
     *
     * @return array<string, string>
     */
    public function read(): array
    {
        $variables = [];

        if (! File::exists($this->envPath)) {
            return $variables;
        }

        $lines = File::lines($this->envPath);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip wrapping quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $variables[$key] = $value;
            }
        }

        return $variables;
    }

    /**
     * Get a single environment value.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->read();

        return $all[$key] ?? $default;
    }

    /**
     * Create a timestamped backup of the current .env file.
     */
    public function createBackup(): ?string
    {
        if (! File::exists($this->envPath)) {
            return null;
        }

        $backupDir = storage_path('app/installer/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0775, true, true);
        }

        $backupPath = sprintf('%s/env-backup-%s.env', $backupDir, date('Ymd-His'));
        File::copy($this->envPath, $backupPath);

        // Prune: keep only the 5 most recent backups. Every backup is a
        // plaintext copy of .env (secrets included), so the directory must
        // not grow without bound.
        $backups = File::files($backupDir);
        if (count($backups) > 5) {
            usort($backups, static fn (string $a, string $b): int => File::lastModified($a) <=> File::lastModified($b));
            foreach (array_slice($backups, 0, count($backups) - 5) as $stale) {
                @File::delete($stale);
            }
        }

        return $backupPath;
    }

    /**
     * Atomically update target keys in .env without overwriting unrelated settings,
     * comments, or whitespace structure.
     *
     * @param  array<string, scalar|null>  $data
     * @return bool
     */
    public function update(array $data): bool
    {
        $this->createBackup();

        if (! File::exists($this->envPath)) {
            if (File::exists(base_path('.env.example'))) {
                File::copy(base_path('.env.example'), $this->envPath);
            } else {
                File::put($this->envPath, "# LeadX Environment Configuration\n");
            }
        }

        $content = File::get($this->envPath);
        $lines = explode("\n", $content);
        $updatedKeys = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, ] = explode('=', $line, 2);
                $key = trim($key);

                if (array_key_exists($key, $data)) {
                    $lines[$index] = $key . '=' . $this->formatValue($data[$key]);
                    $updatedKeys[] = $key;
                }
            }
        }

        // Append any new keys that were not present in existing .env
        $missingKeys = array_diff(array_keys($data), $updatedKeys);
        if (! empty($missingKeys)) {
            $lines[] = '';
            foreach ($missingKeys as $key) {
                $lines[] = $key . '=' . $this->formatValue($data[$key]);
            }
        }

        $newContent = implode("\n", $lines);
        File::put($this->envPath, $newContent);

        return true;
    }

    /**
     * Ensure APP_KEY exists. Never overwrites an existing valid key.
     */
    public function ensureAppKey(): string
    {
        $existing = $this->get('APP_KEY');
        if (! empty($existing) && strlen($existing) >= 16 && $existing !== 'SomeRandomString') {
            return $existing;
        }

        $newKey = $this->generateAppKey();
        $this->update(['APP_KEY' => $newKey]);

        return $newKey;
    }

    /**
     * Generate a cryptographic 32-byte base64 application key.
     */
    public function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Format a value for safe .env persistence.
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $str = (string) $value;

        // Quote if containing spaces, quotes, or special characters
        if (preg_match('/[\s#="\'`$\\\\]/', $str) || $str === '') {
            $escaped = str_replace('"', '\"', $str);

            return '"' . $escaped . '"';
        }

        return $str;
    }
}
