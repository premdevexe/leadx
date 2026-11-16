<?php

namespace App\Services\Installer;

use PDO;
use Throwable;

class DatabaseManager
{
    /**
     * Test a database connection with given credentials.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password?: string|null}  $config
     * @return array{success: bool, message: string, version?: string, table_count?: int, tables?: array<string>}
     */
    public function testConnection(array $config): array
    {
        $host = trim($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = trim($config['database'] ?? '');
        $username = trim($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if (empty($database)) {
            return [
                'success' => false,
                'message' => 'Database name cannot be empty.',
                'table_count' => 0,
                'tables' => [],
            ];
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            $pdo = new PDO($dsn, $username, $password, $options);
            $versionStmt = $pdo->query('SELECT VERSION() AS ver');
            $versionRow = $versionStmt ? $versionStmt->fetch() : null;
            $version = $versionRow['ver'] ?? 'Unknown MySQL/MariaDB';

            // Discover existing tables non-destructively
            $tables = [];
            try {
                $tablesStmt = $pdo->query('SHOW TABLES');
                if ($tablesStmt) {
                    $rawTables = $tablesStmt->fetchAll(PDO::FETCH_NUM);
                    $tables = array_map(fn ($r) => $r[0], $rawTables);
                }
            } catch (Throwable) {
                // Not all MySQL users have SHOW TABLES privilege; non-fatal
            }

            return [
                'success' => true,
                'message' => 'Database connection established successfully.',
                'version' => $version,
                'table_count' => count($tables),
                'tables' => array_values($tables),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $this->sanitizeDbError($e->getMessage(), $password),
                'table_count' => 0,
                'tables' => [],
            ];
        }
    }

    /**
     * Validate a database target for the unauthenticated installer wizard.
     *
     * The wizard is reachable without auth, so it must never act as a
     * network probe against arbitrary hosts, and the application's database
     * may only be re-aimed at a safe location. Loopback (localhost) is
     * allowed — shared-hosting DBs live there — while private, link-local,
     * reserved and metadata-service ranges are rejected.
     *
     * @return string|null Error message, or null when the target is acceptable.
     */
    public function validateDbTarget(string $host, int|string $port, string $database): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            return 'Database name may only contain letters, numbers and underscores.';
        }

        $ips = $this->resolveHost($host);
        if ($ips === null) {
            return 'Database host could not be resolved to an IP address.';
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedAddress($ip)) {
                return 'Database host resolves to a reserved or internal network address and is not allowed. Only localhost or a public database host may be configured.';
            }
        }

        return null;
    }

    /**
     * Resolve a host to all of its A/AAAA addresses.
     *
     * @return list<string>|null
     */
    protected function resolveHost(string $host): ?array
    {
        $host = strtolower(trim($host));

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($host === 'localhost') {
            return ['127.0.0.1', '::1'];
        }

        $ips = @gethostbynamel($host);
        $ips = is_array($ips) ? $ips : [];

        if (($ipv6 = @gethostbyname2($host, 'A6')) !== false) {
            $ips[] = $ipv6;
        }

        return $ips === [] ? null : $ips;
    }

    /**
     * Whether a resolved IP address is unsafe for the unauthenticated wizard.
     */
    protected function isBlockedAddress(string $ip): bool
    {
        // IPv4-mapped IPv6 (::ffff:10.1.2.3) must be checked as IPv4.
        if (str_starts_with($ip, '::ffff:')) {
            return $this->isBlockedAddress(substr($ip, 7));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $o = explode('.', $ip);
            $first = (int) $o[0];
            $second = (int) $o[1];

            // Loopback allowed (shared hosting / local development DB).
            if ($first === 127) {
                return false;
            }

            if ($first === 0) {
                return true; // 0.0.0.0/8
            }
            if ($first === 10) {
                return true; // RFC1918
            }
            if ($first === 172 && $second >= 16 && $second <= 31) {
                return true; // RFC1918
            }
            if ($first === 192 && $second === 168) {
                return true; // RFC1918
            }
            if ($first === 169 && $second === 254) {
                return true; // link-local incl. cloud metadata 169.254.169.254
            }
            if ($first === 100 && $second >= 64 && $second <= 127) {
                return true; // CGNAT
            }
            if ($first >= 224) {
                return true; // multicast / reserved / broadcast
            }

            return false;
        }

        // Native IPv6.
        if ($ip === '::1') {
            return false; // loopback, consistent with IPv4
        }

        if ($ip === '::'
            || str_starts_with($ip, 'fe80:')
            || str_starts_with($ip, 'fc')
            || str_starts_with($ip, 'fd')
            || str_starts_with($ip, '2001:db8:')) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize database error messages to prevent password or credential leakage.
     */
    public function sanitizeDbError(string $message, ?string $password = null): string
    {
        if (! empty($password)) {
            $message = str_replace($password, '*****', $message);
        }

        // Mask password in DSN error strings
        $message = preg_replace('/password=[^; ]+/i', 'password=*****', $message);
        $message = preg_replace('/Access denied for user \'([^\']+)\'@\'([^\']+)\' \(using password: (YES|NO)\)/i', 'Access denied for user \'$1\' (credentials rejected)', $message);
        $message = preg_replace('/Unknown database \'([^\']+)\'/i', 'Database \'$1\' does not exist or user lacks access', $message);
        $message = preg_replace('/SQLSTATE\[HY000\] \[2002\] Connection refused/i', 'Connection refused (check host and port)', $message);

        return substr($message, 0, 400);
    }
}
