<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Atlas\Connection\ConnectionPool;
use Libxa\Atlas\Schema\Blueprint;
use Libxa\Atlas\Schema\Schema;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionPool::resetInstance();
        Schema::reset();
    }

    protected function tearDown(): void
    {
        ConnectionPool::resetInstance();
        Schema::reset();

        parent::tearDown();
    }

    private function connect(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        ConnectionPool::getInstance()->setConnection('default', $pdo);

        return $pdo;
    }

    private function tables(\PDO $pdo): array
    {
        return $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function test_it_creates_a_table(): void
    {
        $pdo = $this->connect();

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->assertContains('widgets', $this->tables($pdo));
    }

    /**
     * The SchemaBuilder was memoised in a static that nothing invalidated, so
     * it captured the first PDO it ever saw and kept using it forever. After a
     * reconnect: a tenant switch, ConnectionPool::configure(), or simply the
     * next test: every Schema:: call silently wrote DDL to the *previous*
     * database, and the table you asked for never appeared in the current one.
     */
    public function test_it_follows_the_connection_when_the_pool_is_rebound(): void
    {
        $first = $this->connect();
        Schema::create('alpha', fn(Blueprint $t) => $t->id());
        $this->assertContains('alpha', $this->tables($first));

        // A brand new connection, exactly as a second request/test would get.
        ConnectionPool::resetInstance();
        $second = $this->connect();

        Schema::create('beta', fn(Blueprint $t) => $t->id());

        $this->assertContains('beta', $this->tables($second), 'DDL must land on the current connection');
        $this->assertNotContains('beta', $this->tables($first), 'and not on the stale one');
    }

    public function test_has_table_and_has_column(): void
    {
        $this->connect();

        Schema::create('gadgets', function (Blueprint $table) {
            $table->id();
            $table->string('label');
        });

        $this->assertTrue(Schema::hasTable('gadgets'));
        $this->assertFalse(Schema::hasTable('nope'));
        $this->assertTrue(Schema::hasColumn('gadgets', 'label'));
        $this->assertFalse(Schema::hasColumn('gadgets', 'missing'));
    }

    /** table() ran the Blueprint callback but never built the SQL. */
    public function test_altering_a_table_adds_the_column(): void
    {
        $this->connect();

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('nickname')->nullable();
        });

        $this->assertTrue(Schema::hasColumn('accounts', 'nickname'));
    }

    public function test_drop_if_exists(): void
    {
        $pdo = $this->connect();

        Schema::create('temp_table', fn(Blueprint $t) => $t->id());
        $this->assertContains('temp_table', $this->tables($pdo));

        Schema::dropIfExists('temp_table');
        $this->assertNotContains('temp_table', $this->tables($pdo));

        // Must be a no-op, not an error.
        Schema::dropIfExists('temp_table');
        $this->addToAssertionCount(1);
    }
}
