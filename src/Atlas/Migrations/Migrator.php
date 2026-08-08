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
                    if (! is_object($migration) || ! method_exists($migration, 'up')) {
                        throw new \RuntimeException(
                            "Migration [{$name}] returns an anonymous class without an up() method."
                        );
                    }

                    $migrations[$name] = $migration;
                } else {
                    // Named class migration
                    $migrations[$name] = $this->instantiateNamedMigration($file, $name);
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

            return $this->instantiateNamedMigration($file, $name);
        }

        return null;
    }

    /**
     * Load a migration file and instantiate the migration class inside it.
     *
     * The class name used to be derived from the filename alone, and a
     * mismatch simply meant `class_exists()` returned false and the migration
     * was skipped — silently. No warning, no error, exit code 0: you deployed,
     * the column was never added, and the first sign of trouble was a
     * "no such column" error in production. (The starter kit shipped exactly
     * such a file: add_refresh_token_to_tokens_table.php declaring
     * AddRefreshTokenToPersonalAccessTokensTable.)
     *
     * The conventional name is still tried first; otherwise the class the file
     * actually declared is used, and a file that declares nothing usable is a
     * hard error.
     */
    protected function instantiateNamedMigration(string $file, string $name): object
    {
        require_once $file;

        $class = $this->fileToClass($name);

        if (! class_exists($class)) {
            // Read the class name out of the file itself. A get_declared_classes()
            // diff around the require would not work: the file may already be
            // loaded (Composer's classmap does exactly that for the migrations
            // directory, and require_once is a no-op the second time), in which
            // case nothing new is declared and the diff is empty.
            $class = $this->classDeclaredIn($file) ?? '';
        }

        if ($class === '' || ! class_exists($class)) {
            throw new \RuntimeException(
                "Migration [{$name}] does not define a loadable migration class. "
                . "Expected [{$this->fileToClass($name)}] in {$file}."
            );
        }

        if (! method_exists($class, 'up')) {
            throw new \RuntimeException(
                "Migration class [{$class}] in {$file} has no up() method."
            );
        }

        return new $class($this->pdo);
    }

    /**
     * The fully-qualified name of the first class declared in a PHP file.
     */
    protected function classDeclaredIn(string $file): ?string
    {
        $tokens    = @token_get_all((string) file_get_contents($file));
        $namespace = '';
        $count     = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                        $namespace .= $tokens[$j][1];
                    }
                }
                continue;
            }

            if ($token[0] === T_CLASS) {
                // Skip "::class" and anonymous "new class".
                $prev = $tokens[$i - 1] ?? null;
                if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        return ($namespace === '' ? '' : $namespace . '\\') . $tokens[$j][1];
                    }
                    if ($tokens[$j] === '{' || $tokens[$j] === '(') {
                        break; // anonymous class
                    }
                }
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
