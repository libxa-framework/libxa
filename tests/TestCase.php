<?php

declare(strict_types=1);

namespace Tests;

use Libxa\Container\Container;
use Libxa\Foundation\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case.
 *
 * Boots a *real* Application against a throwaway application skeleton in the
 * system temp directory. The previous tests mocked Application and tried to
 * stub Application::env(), which is static and therefore cannot be stubbed:
 * every one of those tests failed with "Static method env cannot be invoked on
 * mock object". Running against the real container also means these tests
 * actually exercise service-provider wiring, which is where most of the
 * framework's crash-on-boot bugs lived.
 */
abstract class TestCase extends BaseTestCase
{
    protected Application $app;

    /** Absolute path to this test's throwaway app skeleton. */
    protected string $appPath;

    /** Extra .env lines for a specific test case. */
    protected array $env = [];

    /** Extra config files: ['app' => [...], 'session' => [...]] */
    protected array $config = [];

    /** Saved error_log ini value, restored in tearDown. */
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appPath = $this->makeSkeleton();

        // Tests that deliberately throw would otherwise spray the kernel's
        // (correct) error_log output all over the PHPUnit report.
        $this->previousErrorLog = ini_set('error_log', $this->appPath . '/src/storage/logs/test.log');

        $this->app = new Application($this->appPath);
    }

    protected function tearDown(): void
    {
        if ($this->previousErrorLog !== false) {
            ini_set('error_log', $this->previousErrorLog);
        }

        // A leaked container instance makes the *next* test resolve services
        // from a dead application with a deleted base path.
        Container::setInstance(new Application(sys_get_temp_dir()));

        $this->deleteDirectory($this->appPath);

        $_SESSION = [];
        $_SERVER  = array_diff_key($_SERVER, array_flip([
            'HTTP_REFERER', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_CSRF_TOKEN',
            'HTTPS', 'REQUEST_METHOD', 'REQUEST_URI',
        ]));

        parent::tearDown();
    }

    /**
     * Build a minimal but complete application skeleton on disk.
     */
    protected function makeSkeleton(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'libxa_test_' . bin2hex(random_bytes(6));

        foreach ([
            'src/config',
            'src/routes',
            'src/resources/views',
            'src/storage/framework/views',
            'src/storage/framework/cache/data',
            'src/storage/logs',
            'src/public',
            'database',
        ] as $dir) {
            mkdir($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir), 0777, true);
        }

        $env = array_merge([
            'APP_NAME'      => 'LibxaTest',
            'APP_ENV'       => 'testing',
            'APP_DEBUG'     => 'true',
            'APP_URL'       => 'http://localhost',
            'APP_KEY'       => 'base64:' . base64_encode(str_repeat('k', 32)),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE'   => $base . '/database/database.sqlite',
            'CACHE_DRIVER'  => 'file',
        ], $this->env);

        $lines = '';
        foreach ($env as $key => $value) {
            $lines .= "{$key}={$value}\n";
        }
        file_put_contents($base . '/.env', $lines);

        $config = array_merge($this->defaultConfig(), $this->config);

        foreach ($config as $name => $values) {
            file_put_contents(
                $base . '/src/config/' . $name . '.php',
                "<?php\n\nreturn " . var_export($values, true) . ";\n"
            );
        }

        touch($base . '/database/database.sqlite');

        return $base;
    }

    protected function defaultConfig(): array
    {
        return [
            'app' => [
                'name'      => 'LibxaTest',
                'env'       => 'testing',
                'debug'     => true,
                'url'       => 'http://localhost',
                'providers' => [],
            ],
            'session' => [
                'driver'    => 'file',
                'lifetime'  => 120,
                'cookie'    => 'libxa_test_session',
                'path'      => '/',
                'domain'    => '',
                'secure'    => false,
                'http_only' => true,
                'same_site' => 'lax',
            ],
            'cache' => [
                'default' => 'file',
                'stores'  => ['file' => ['driver' => 'file']],
            ],
            'database' => [
                'default'     => 'sqlite',
                'connections' => [
                    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ];
    }

    /**
     * Write a file inside the skeleton, creating parent directories.
     */
    protected function writeAppFile(string $relative, string $contents): string
    {
        $path = $this->appPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    /**
     * Build a Request without touching PHP superglobals.
     */
    protected function makeRequest(
        string $method = 'GET',
        string $uri = '/',
        array $post = [],
        array $headers = [],
        array $server = [],
    ): \Libxa\Http\Request {
        return new \Libxa\Http\Request(
            method: $method,
            uri: $uri,
            headers: $headers,
            query: [],
            post: $post,
            files: [],
            server: array_merge(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'], $server),
            cookies: [],
        );
    }

    /**
     * An in-memory SQLite PDO with sane error reporting.
     */
    protected function makePdo(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
