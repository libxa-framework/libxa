<?php

declare(strict_types=1);

namespace Libxa\Atlas\Migrations;

use Libxa\Atlas\Connection\ConnectionPool;
use Libxa\Atlas\Schema\SchemaBuilder;

/**
 * Atlas Migrator
 *
 * Discovers and runs migration files.
 * Tracks which migrations have been run in a `Libxa_migrations` table.
 *
 * Commands:
 *   php Libxa migrate             — Run pending migrations
 *   php Libxa migrate:rollback    — Rollback the last batch
 *   php Libxa migrate:fresh       — Drop all tables and re-run
 *   php Libxa schema:diff         — Detect drift between models and DB
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
            echo "  Migrating: $file\n";

            $instance->up();

            $this->recordMigration($file, $batch);
            $results[] = $file;

            echo "  Migrated:  $file ✓\n";
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

            echo "  Rolling back: {$migration['migration']}\n";

            $instance->down();

            $this->deleteMigration($migration['migration']);
            $results[] = $migration['migration'];

            echo "  Rolled back:  {$migration['migration']} ✓\n";
        }

        return $results;
    }

    public function fresh(array $paths = []): void
    {
        // Drop all tables
        $tables = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence'"
        )?->fetchAll(\PDO::FETCH_COLUMN) ?? [];

        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
        }

        $this->ensureMigrationsTable();
        $this->run($paths);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Discovery
    // ─────────────────────────────────────────────────────────────────

    protected function getPendingMigrations(array $paths, array $ran): array
    {
        $all     = $this->discoverMigrations($paths);
        $pending = [];

        foreach ($all as $file => $instance) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (! in_array($name, $ran)) {
                $pending[$name] = $instance;
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
                require_once $file;
                $name  = pathinfo($file, PATHINFO_FILENAME);
                $class = $this->fileToClass($name);

                if (class_exists($class)) {
                    $migrations[$name] = new $class($this->pdo);
                }
            }
        }

        return $migrations;
    }

    protected function resolveMigration(string $name, array $paths): ?object
    {
        foreach ($paths as $path) {
            $file  = "$path/$name.php";
            $class = $this->fileToClass($name);

            if (file_exists($file)) {
                require_once $file;
                return class_exists($class) ? new $class($this->pdo) : null;
            }
        }
        return null;
    }

    protected function fileToClass(string $filename): string
    {
        // 2024_01_01_000000_create_users_table → CreateUsersTable
        $parts = explode('_', $filename);
        // Skip date/time prefix (first 4 segments)
        $name  = implode('_', array_slice($parts, 4));
        return str_replace('_', '', ucwords($name, '_'));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Migration tracking
    // ─────────────────────────────────────────────────────────────────

    protected function ensureMigrationsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS Libxa_migrations (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch     INTEGER NOT NULL,
                ran_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    protected function getRanMigrations(): array
    {
        return $this->pdo->query("SELECT migration FROM Libxa_migrations")
            ?->fetchAll(\PDO::FETCH_COLUMN) ?? [];
    }

    protected function getLastBatchMigrations(): array
    {
        $batch = $this->getCurrentBatchNumber();
        return $this->pdo->query(
            "SELECT migration FROM Libxa_migrations WHERE batch = $batch ORDER BY id DESC"
        )?->fetchAll(\PDO::FETCH_ASSOC) ?? [];
    }

    protected function getNextBatchNumber(): int
    {
        return $this->getCurrentBatchNumber() + 1;
    }

    protected function getCurrentBatchNumber(): int
    {
        $result = $this->pdo->query("SELECT MAX(batch) FROM Libxa_migrations")?->fetchColumn();
        return (int) $result;
    }

    protected function recordMigration(string $migration, int $batch): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO Libxa_migrations (migration, batch) VALUES (?, ?)"
        );
        $stmt->execute([$migration, $batch]);
    }

    protected function deleteMigration(string $migration): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM Libxa_migrations WHERE migration = ?");
        $stmt->execute([$migration]);
    }
}

/**
 * Base Migration class — all migration files extend this.
 */
abstract class Migration
{
    protected SchemaBuilder $schema;

    public function __construct(protected \PDO $pdo)
    {
        $this->schema = new SchemaBuilder($this->pdo);
    }

    abstract public function up(): void;
    abstract public function down(): void;
}
