<?php

declare(strict_types=1);

namespace Libxa\Atlas\Migrations;

use Libxa\Atlas\Connection\ConnectionPool;

/**
 * Atlas Migrator
 *
 * Discovers and runs migration files.
 * Tracks which migrations have been run in a `libxa_migrations` table.
 */
class Migrator
{
    protected array $paths = [];
    protected \PDO  $pdo;

    public function __construct()
    {
        $this->pdo = ConnectionPool::getInstance()->get();
        $this->ensureMigrationsTable();
    }

    public function addPath(string $path): void
    {
        $this->paths[] = $path;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Run
    // ─────────────────────────────────────────────────────────────────

    public function run(array $paths = []): array
    {
        $paths   = array_merge($this->paths, $paths);
        $ran     = $this->getRanMigrations();
        $batch   = $this->getNextBatchNumber();
        $pending = $this->getPendingMigrations($paths, $ran);
        $results = [];

        foreach ($pending as $file => $instance) {
            $instance->up();
            $this->recordMigration($file, $batch);
            $results[] = $file;
        }

        return $results;
    }

    public function rollback(array $paths = []): array
    {
        $ran     = $this->getLastBatchMigrations();
        $results = [];

        foreach (array_reverse($ran) as $migration) {
            $instance = $this->resolveMigration($migration['migration'], $paths);

            if ($instance === null) continue;

            $instance->down();
            $this->deleteMigration($migration['migration']);
            $results[] = $migration['migration'];
        }

        return $results;
    }

    public function fresh(array $paths = []): void
    {
        $this->dropAllTables();
        $this->ensureMigrationsTable();
        $this->run($paths);
    }

    protected function dropAllTables(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'sqlite':
                $tables = $this->pdo->query(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
                )?->fetchAll(\PDO::FETCH_COLUMN) ?? [];

                $this->pdo->exec('PRAGMA foreign_keys = OFF');
                foreach ($tables as $table) {
                    $this->pdo->exec("DROP TABLE IF EXISTS \"$table\"");
                }
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                break;

            case 'mysql':
                $db     = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
                $tables = $this->pdo->query("SHOW TABLES")?->fetchAll(\PDO::FETCH_COLUMN) ?? [];

                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach ($tables as $table) {
                    $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
                }
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                break;

            case 'pgsql':
                $tables = $this->pdo->query(
                    "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
                )?->fetchAll(\PDO::FETCH_COLUMN) ?? [];

                foreach ($tables as $table) {
                    $this->pdo->exec("DROP TABLE IF EXISTS \"$table\" CASCADE");
                }
                break;

            default:
                throw new \RuntimeException("Unsupported driver for fresh migration: $driver");
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Discovery
    // ─────────────────────────────────────────────────────────────────

    protected function getPendingMigrations(array $paths, array $ran): array
    {
        $all     = $this->discoverMigrations($paths);
        $pending = [];

        foreach ($all as $file => $instance) {
            if (! in_array($file, $ran)) {
                $pending[$file] = $instance;
            }
        }

        return $pending;
    }

    protected function discoverMigrations(array $paths): array
    {
        $migrations = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) continue;

            $files = glob("$path/*.php") ?: [];
            sort($files);

            foreach ($files as $file) {
                $name    = pathinfo($file, PATHINFO_FILENAME);
                $content = file_get_contents($file);

                if (preg_match('/return\s+new\s+class/i', $content)) {
                    // Anonymous class migration
                    $migration = require $file;
                    if (is_object($migration) && method_exists($migration, 'up')) {
                        $migrations[$name] = $migration;
                    }
                } else {
                    // Named class migration
                    require_once $file;
                    $class = $this->fileToClass($name);
                    if (class_exists($class)) {
                        $migrations[$name] = new $class($this->pdo);
                    }
                }
            }
        }

        return $migrations;
    }

    protected function resolveMigration(string $name, array $paths): ?object
    {
        $allPaths = array_merge($this->paths, $paths);

        foreach ($allPaths as $path) {
            $file = "$path/$name.php";

            if (! file_exists($file)) continue;

            $content = file_get_contents($file);

            if (preg_match('/return\s+new\s+class/i', $content)) {
                $migration = require $file;
                if (is_object($migration) && method_exists($migration, 'down')) {
                    return $migration;
                }
            }

            require_once $file;
            $class = $this->fileToClass($name);
            if (class_exists($class)) {
                return new $class($this->pdo);
            }
        }

        return null;
    }

    protected function fileToClass(string $filename): string
    {
        $parts = explode('_', $filename);
        // Skip date/time prefix (first 4 segments: YYYY_MM_DD_HHMMSS)
        $name  = implode('_', array_slice($parts, 4));
        return str_replace('_', '', ucwords($name, '_'));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Migrations table
    // ─────────────────────────────────────────────────────────────────

    protected function ensureMigrationsTable(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $sql = match ($driver) {
            'mysql'  => "CREATE TABLE IF NOT EXISTS `libxa_migrations` (
                            `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            `migration` VARCHAR(255) NOT NULL,
                            `batch`     INT NOT NULL,
                            `ran_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'pgsql'  => "CREATE TABLE IF NOT EXISTS libxa_migrations (
                            id        SERIAL PRIMARY KEY,
                            migration VARCHAR(255) NOT NULL,
                            batch     INTEGER NOT NULL,
                            ran_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                         )",
            default  => "CREATE TABLE IF NOT EXISTS libxa_migrations (
                            id        INTEGER PRIMARY KEY AUTOINCREMENT,
                            migration VARCHAR(255) NOT NULL,
                            batch     INTEGER NOT NULL,
                            ran_at    DATETIME DEFAULT CURRENT_TIMESTAMP
                         )",
        };

        $this->pdo->exec($sql);
    }

    public function getRanMigrations(): array
    {
        return $this->pdo->query("SELECT migration FROM libxa_migrations")
            ?->fetchAll(\PDO::FETCH_COLUMN) ?? [];
    }

    protected function getLastBatchMigrations(): array
    {
        $batch = $this->getCurrentBatchNumber();
        return $this->pdo->query(
            "SELECT migration FROM libxa_migrations WHERE batch = $batch ORDER BY id DESC"
        )?->fetchAll(\PDO::FETCH_ASSOC) ?? [];
    }

    protected function getNextBatchNumber(): int
    {
        return $this->getCurrentBatchNumber() + 1;
    }

    protected function getCurrentBatchNumber(): int
    {
        $result = $this->pdo->query("SELECT MAX(batch) FROM libxa_migrations")?->fetchColumn();
        return (int) $result;
    }

    protected function recordMigration(string $migration, int $batch): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO libxa_migrations (migration, batch) VALUES (?, ?)"
        );
        $stmt->execute([$migration, $batch]);
    }

    protected function deleteMigration(string $migration): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM libxa_migrations WHERE migration = ?");
        $stmt->execute([$migration]);
    }
}
