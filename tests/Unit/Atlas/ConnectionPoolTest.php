<?php

declare(strict_types=1);

namespace Tests\Unit\Atlas;

use Libxa\Atlas\Connection\ConnectionPool;
use Libxa\Atlas\Connection\DriverUnavailableException;
use PHPUnit\Framework\TestCase;

/**
 * Connecting to a database, and failing to.
 *
 * Most of these are about the failure paths. A connection that works needs no
 * explanation; a connection that does not is where an afternoon goes, and PDO
 * on its own says almost nothing useful about why.
 */
final class ConnectionPoolTest extends TestCase
{
    protected function tearDown(): void
    {
        ConnectionPool::resetInstance();

        foreach ($this->tempDirs as $dir) {
            $this->deleteTree($dir);
        }

        $this->tempDirs = [];
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    // ── driver names ─────────────────────────────────────────────────────

    public function test_it_accepts_the_names_people_actually_write(): void
    {
        // These appear in .env files everywhere. Refusing them because the
        // internal name differs is a spelling test, not a safety check.
        self::assertSame('pgsql', ConnectionPool::normalizeDriver('postgres'));
        self::assertSame('pgsql', ConnectionPool::normalizeDriver('postgresql'));
        self::assertSame('pgsql', ConnectionPool::normalizeDriver('PostgreSQL'));
        self::assertSame('mysql', ConnectionPool::normalizeDriver('mariadb'));
        self::assertSame('sqlite', ConnectionPool::normalizeDriver('sqlite3'));
        self::assertSame('sqlite', ConnectionPool::normalizeDriver('  SQLite '));
    }

    public function test_each_driver_knows_which_extension_provides_it(): void
    {
        // "pgsql" is spelled "pdo_pgsql" in php.ini, and nothing about the
        // error tells you that unless we do.
        self::assertSame('pdo_pgsql', ConnectionPool::extensionFor('postgres'));
        self::assertSame('pdo_mysql', ConnectionPool::extensionFor('mariadb'));
        self::assertSame('pdo_sqlite', ConnectionPool::extensionFor('sqlite'));
    }

    public function test_each_driver_has_the_right_default_port(): void
    {
        // A project switching to Postgres without touching DB_PORT would
        // otherwise dial 3306 and get an error that never mentions the port.
        self::assertSame('5432', ConnectionPool::defaultPort('pgsql'));
        self::assertSame('3306', ConnectionPool::defaultPort('mysql'));
        self::assertSame('', ConnectionPool::defaultPort('sqlite'));
    }

    // ── the missing-driver message ───────────────────────────────────────

    public function test_a_missing_driver_names_itself_and_the_fix(): void
    {
        $e = new DriverUnavailableException('pgsql', ['mysql', 'sqlite']);

        $msg = $e->getMessage();

        // PDO's own message is "could not find driver" — it names neither the
        // driver, nor the extension, nor which PHP is running.
        self::assertStringContainsString('pgsql', $msg);
        self::assertStringContainsString('extension=pdo_pgsql', $msg);
        self::assertStringContainsString('mysql, sqlite', $msg);
        self::assertStringContainsString(PHP_BINARY, $msg);
    }

    public function test_no_drivers_at_all_points_at_extension_dir(): void
    {
        // Every driver missing has one cause, and it is not that each is
        // separately absent.
        $e = new DriverUnavailableException('sqlite', []);

        self::assertStringContainsString('extension_dir', $e->getMessage());
    }

    public function test_the_message_mentions_multiple_php_versions(): void
    {
        // On a machine with several PHP builds this is the actual cause more
        // often than a genuinely missing extension.
        $e = new DriverUnavailableException('pgsql', ['sqlite']);

        self::assertStringContainsString('several PHP versions', $e->getMessage());
    }

    // ── unsupported drivers ──────────────────────────────────────────────

    public function test_an_unsupported_driver_lists_the_supported_ones(): void
    {
        $pool = ConnectionPool::getInstance();
        $pool->configure(['default' => ['driver' => 'oracle']]);

        try {
            $pool->get();
            self::fail('expected a failure for an unsupported driver');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('oracle', $e->getMessage());
            self::assertStringContainsString('sqlite, mysql, pgsql', $e->getMessage());
        }
    }

    // ── sqlite paths ─────────────────────────────────────────────────────

    public function test_it_connects_to_an_in_memory_database(): void
    {
        $pool = ConnectionPool::getInstance();
        $pool->configure(['default' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        $pdo = $pool->get();

        self::assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function test_it_connects_to_a_file_and_creates_the_directory(): void
    {
        $dir  = sys_get_temp_dir() . '/libxa-db-' . bin2hex(random_bytes(6));
        $file = $dir . '/nested/app.sqlite';

        $pool = ConnectionPool::getInstance();
        $pool->configure(['default' => ['driver' => 'sqlite', 'database' => $file]]);

        $pdo = $pool->get();
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        self::assertFileExists($file);

        // Windows refuses to unlink a file that is still open, so the handle
        // has to go before the cleanup rather than at the end of the test.
        unset($pdo);
        $pool->disconnectAll();

        @unlink($file);
        @rmdir($dir . '/nested');
        @rmdir($dir);
    }

    public function test_a_windows_absolute_path_is_not_treated_as_relative(): void
    {
        // The old leading-slash check called C:\srv\app.sqlite relative and
        // re-rooted it under the project's database directory, producing
        // C:\proj\src\database\C:\srv\app.sqlite — a path that cannot exist.
        $method = new \ReflectionMethod(ConnectionPool::class, 'isAbsolutePath');

        self::assertTrue($method->invoke(null, 'C:\\srv\\app.sqlite'));
        self::assertTrue($method->invoke(null, 'C:/srv/app.sqlite'));
        self::assertTrue($method->invoke(null, '/var/lib/app.sqlite'));
        self::assertFalse($method->invoke(null, 'app.sqlite'));
        self::assertFalse($method->invoke(null, 'database/app.sqlite'));
    }

    // ── connection failures ──────────────────────────────────────────────

    public function test_a_failed_connection_says_what_it_tried_to_reach(): void
    {
        if (! in_array('mysql', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_mysql not available');
        }

        $pool = ConnectionPool::getInstance();
        $pool->configure(['default' => [
            'driver'   => 'mysql',
            // A port nothing listens on, so this fails fast and locally.
            'host'     => '127.0.0.1',
            'port'     => '1',
            'database' => 'nope',
            'username' => 'someone',
        ]]);

        try {
            $pool->get();
            self::fail('expected the connection to fail');
        } catch (\RuntimeException $e) {
            // "Connection refused" alone does not say to which host, port or
            // database — which matters most when the answer is that the
            // config in use is not the config being edited.
            $msg = $e->getMessage();

            self::assertStringContainsString('127.0.0.1:1', $msg);
            self::assertStringContainsString('nope', $msg);
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    // ── config resolution ────────────────────────────────────────────────

    /**
     * Build a throwaway application whose config/database.php is the given
     * array, so config resolution is exercised the way a real project hits it.
     */
    private function appWithDatabaseConfig(array $database): \Libxa\Foundation\Application
    {
        $base = sys_get_temp_dir() . '/libxa-app-' . bin2hex(random_bytes(6));

        mkdir($base . '/src/config', 0755, true);

        file_put_contents(
            $base . '/src/config/database.php',
            '<?php return ' . var_export($database, true) . ';',
        );

        $this->tempDirs[] = $base;

        return new \Libxa\Foundation\Application($base);
    }

    /** @var list<string> */
    private array $tempDirs = [];

    public function test_the_config_file_decides_the_connection(): void
    {
        // config/database.php used to be decorative: every call site reaches
        // the pool through the static getInstance(), which never ran the
        // service provider that loaded this file. Editing it changed nothing
        // and nothing said so.
        $app = $this->appWithDatabaseConfig([
            'default'     => 'sqlite',
            'connections' => [
                'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
        ]);

        $app->boot();

        $pool = ConnectionPool::getInstance();
        $pool->reset();

        self::assertSame('sqlite', $pool->get()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function test_selecting_an_undefined_connection_is_refused(): void
    {
        // Falling back to SQLite here would let the application run happily
        // against a different database than the one it was configured for.
        $app = $this->appWithDatabaseConfig([
            'default'     => 'pgsql',
            'connections' => [
                'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
        ]);

        $app->boot();

        $pool = ConnectionPool::getInstance();
        $pool->reset();

        try {
            $pool->get();
            self::fail('expected a failure for an undefined connection');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('pgsql', $e->getMessage());
            self::assertStringContainsString('not defined', $e->getMessage());
            self::assertStringContainsString('sqlite', $e->getMessage());
        }
    }

    public function test_configure_drops_live_handles(): void
    {
        $pool = ConnectionPool::getInstance();
        $pool->configure(['default' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        $first = $pool->get();

        // Reconfiguring while a handle is open must not keep serving the old
        // one: that is the "why is it still hitting the dev database" bug.
        $pool->configure(['default' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        self::assertNotSame($first, $pool->get());
    }
}
