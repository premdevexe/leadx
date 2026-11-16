<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use PDO;

class DatabaseBackupService
{
    /**
     * Dump database contents into an SQL script file.
     * Compatible with MySQL, MariaDB, and SQLite (for testing).
     */
    public function dump(string $targetSqlPath): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();

        $fp = fopen($targetSqlPath, 'w');
        if (! $fp) {
            throw new \RuntimeException("Unable to open target SQL file: {$targetSqlPath}");
        }

        fwrite($fp, "-- LeadX Database Backup\n");
        fwrite($fp, "-- Driver: {$driver}\n");
        fwrite($fp, "-- Generated: " . now()->toIso8601String() . "\n\n");

        $tableCount = 0;
        $rowCount = 0;

        if ($driver === 'sqlite') {
            $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            fwrite($fp, "PRAGMA foreign_keys = OFF;\n\n");

            foreach ($tables as $t) {
                $tableName = $t->name;
                $tableCount++;

                $createStmt = $connection->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$tableName]);
                if ($createStmt && isset($createStmt->sql)) {
                    fwrite($fp, "DROP TABLE IF EXISTS \"{$tableName}\";\n");
                    fwrite($fp, $createStmt->sql . ";\n\n");
                }

                $rows = $connection->table($tableName)->get();
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $rowCount++;
                    $cols = array_map(fn ($c) => "\"{$c}\"", array_keys($rowArray));
                    $vals = array_map(function ($val) use ($pdo) {
                        if (is_null($val)) return 'NULL';
                        return $pdo->quote((string) $val);
                    }, array_values($rowArray));

                    fwrite($fp, sprintf(
                        "INSERT INTO \"%s\" (%s) VALUES (%s);\n",
                        $tableName,
                        implode(', ', $cols),
                        implode(', ', $vals)
                    ));
                }
                fwrite($fp, "\n");
            }

            fwrite($fp, "PRAGMA foreign_keys = ON;\n");
        } else {
            // MySQL / MariaDB
            fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n");
            fwrite($fp, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $connection->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
            foreach ($tables as $tableObj) {
                $props = get_object_vars($tableObj);
                $tableName = reset($props);
                $tableCount++;

                $create = $connection->selectOne("SHOW CREATE TABLE `{$tableName}`");
                $createArr = (array) $create;
                $createSql = $createArr['Create Table'] ?? '';

                fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($fp, $createSql . ";\n\n");

                $cursor = $connection->table($tableName)->cursor();
                $chunk = [];
                foreach ($cursor as $row) {
                    $rowArray = (array) $row;
                    $rowCount++;
                    $chunk[] = array_map(function ($val) use ($pdo) {
                        if (is_null($val)) return 'NULL';
                        return $pdo->quote((string) $val);
                    }, array_values($rowArray));

                    if (count($chunk) >= 200) {
                        $this->writeInsertBatch($fp, $tableName, array_keys($rowArray), $chunk);
                        $chunk = [];
                    }
                }
                if (! empty($chunk)) {
                    $firstRow = (array) $connection->table($tableName)->first();
                    $this->writeInsertBatch($fp, $tableName, array_keys($firstRow), $chunk);
                }
                fwrite($fp, "\n");
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        }

        fclose($fp);

        return [
            'tables' => $tableCount,
            'rows' => $rowCount,
            'file_size' => filesize($targetSqlPath),
        ];
    }

    private function writeInsertBatch($fp, string $table, array $columns, array $batch): void
    {
        $cols = array_map(fn ($c) => "`{$c}`", $columns);
        $lines = array_map(fn ($vals) => '(' . implode(', ', $vals) . ')', $batch);

        fwrite($fp, sprintf(
            "INSERT INTO `%s` (%s) VALUES\n%s;\n",
            $table,
            implode(', ', $cols),
            implode(",\n", $lines)
        ));
    }
}
