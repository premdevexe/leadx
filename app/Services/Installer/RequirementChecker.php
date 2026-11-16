<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\File;

class RequirementChecker
{
    /**
     * Minimum supported PHP version.
     */
    public const MIN_PHP_VERSION = '8.2.0';

    /**
     * Required PHP extensions for LeadX.
     */
    protected array $requiredExtensions = [
        'openssl' => 'Required for secure token generation, encryption and HTTPS requests',
        'pdo' => 'Required for relational database abstraction',
        'pdo_mysql' => 'Required for MySQL and MariaDB database connectivity',
        'mbstring' => 'Required for multi-byte string handling and internationalization',
        'tokenizer' => 'Required by Laravel Blade and framework compiler',
        'xml' => 'Required for XML parsing and document processing',
        'ctype' => 'Required for string type checking',
        'json' => 'Required for JSON payloads and API serializations',
        'fileinfo' => 'Required for secure file upload detection and MIME validation',
        'bcmath' => 'Required for arbitrary precision decimal calculations in financial orders',
        'curl' => 'Required for Pusher Beams and external HTTP integrations',
    ];

    /**
     * Directories requiring write permissions.
     */
    protected array $writablePaths = [
        'storage' => 'storage',
        'storage/app' => 'storage/app',
        'storage/app/installer' => 'storage/app/installer',
        'storage/framework/cache' => 'storage/framework/cache',
        'storage/framework/sessions' => 'storage/framework/sessions',
        'storage/framework/views' => 'storage/framework/views',
        'storage/logs' => 'storage/logs',
        'bootstrap/cache' => 'bootstrap/cache',
    ];

    /**
     * Check if PHP version meets requirement.
     */
    public function checkPhpVersion(): array
    {
        $pass = version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');

        return [
            'label' => 'PHP >= ' . self::MIN_PHP_VERSION,
            'pass' => $pass,
            'value' => PHP_VERSION,
            'required' => self::MIN_PHP_VERSION,
        ];
    }

    /**
     * Check all required PHP extensions.
     */
    public function checkExtensions(): array
    {
        $results = [];

        foreach ($this->requiredExtensions as $ext => $description) {
            $loaded = extension_loaded($ext);
            $results[$ext] = [
                'label' => ucfirst($ext) . ' extension',
                'pass' => $loaded,
                'value' => $loaded ? 'Installed' : 'Missing',
                'description' => $description,
            ];
        }

        return $results;
    }

    /**
     * Check all required directories for writability.
     */
    public function checkDirectories(): array
    {
        $results = [];

        foreach ($this->writablePaths as $path => $relativePath) {
            $fullPath = base_path($relativePath);

            // Create directory if missing and parent is writable
            if (! File::exists($fullPath)) {
                @File::makeDirectory($fullPath, 0775, true, true);
            }

            $writable = File::exists($fullPath) && is_writable($fullPath);

            $results[$path] = [
                'label' => 'Writable ' . $relativePath . '/',
                'pass' => $writable,
                'value' => $writable ? 'Writable' : 'Not writable (chmod 775 required)',
                'path' => $fullPath,
            ];
        }

        // Check .env writability (or base path writability if .env does not exist yet)
        $envPath = base_path('.env');
        $envWritable = File::exists($envPath) ? is_writable($envPath) : is_writable(base_path());
        $results['.env'] = [
            'label' => 'Writable .env configuration',
            'pass' => $envWritable,
            'value' => $envWritable ? 'Writable' : 'Not writable (chmod 664 required)',
            'path' => $envPath,
        ];

        return $results;
    }

    /**
     * Compile comprehensive requirements check report.
     */
    public function checkAll(): array
    {
        $php = $this->checkPhpVersion();
        $extensions = $this->checkExtensions();
        $directories = $this->checkDirectories();

        $allPass = $php['pass']
            && ! in_array(false, array_column($extensions, 'pass'), true)
            && ! in_array(false, array_column($directories, 'pass'), true);

        return [
            'php' => $php,
            'extensions' => $extensions,
            'directories' => $directories,
            'all_pass' => $allPass,
        ];
    }

    /**
     * Check if all requirements pass.
     */
    public function meetsAllRequirements(): bool
    {
        return $this->checkAll()['all_pass'];
    }
}
