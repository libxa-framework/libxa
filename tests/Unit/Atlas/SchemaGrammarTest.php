<?php

declare(strict_types=1);

namespace Tests\Unit\Atlas;

use Libxa\Atlas\Schema\Blueprint;
use Libxa\Atlas\Schema\Grammar;
use PHPUnit\Framework\TestCase;

/**
 * One migration, three databases.
 *
 * The Blueprint used to emit SQLite's SQL whatever it was connected to, so
 * migrations ran on SQLite and failed on everything else — the first column of
 * the first table was already a syntax error on MySQL, reported at line 2
 * because that is where the parser gave up.
 *
 * These assert the spellings that differ, one per real incompatibility.
 */
final class SchemaGrammarTest extends TestCase
{
    private function sql(string $driver, callable $define): string
    {
        $table = new Blueprint('t', new \PDO('sqlite::memory:'), false, Grammar::forDriver($driver));

        $define($table);

        return $table->toSql();
    }

    // ── auto-incrementing primary key ────────────────────────────────────

    public function test_id_uses_each_databases_own_auto_increment(): void
    {
        // AUTOINCREMENT is SQLite's and nobody else's. This one keyword is
        // what made every migration fail on MySQL.
        self::assertStringContainsString(
            'AUTOINCREMENT',
            $this->sql('sqlite', fn (Blueprint $t) => $t->id()),
        );

        self::assertStringContainsString(
            'AUTO_INCREMENT',
            $this->sql('mysql', fn (Blueprint $t) => $t->id()),
        );

        self::assertStringContainsString(
            'BIGSERIAL',
            $this->sql('pgsql', fn (Blueprint $t) => $t->id()),
        );
    }

    public function test_no_dialect_leaks_another_dialects_auto_increment(): void
    {
        $mysql = $this->sql('mysql', fn (Blueprint $t) => $t->id());
        $pgsql = $this->sql('pgsql', fn (Blueprint $t) => $t->id());

        // MySQL's AUTO_INCREMENT contains "AUTO_INCREMENT" but must not carry
        // SQLite's spelling, and Postgres must have neither.
        self::assertStringNotContainsString('AUTOINCREMENT ', $mysql);
        self::assertStringNotContainsString('AUTO_INCREMENT', $pgsql);
        self::assertStringNotContainsString('AUTOINCREMENT', $pgsql);
    }

    // ── types that genuinely differ ──────────────────────────────────────

    public function test_boolean(): void
    {
        // Postgres has a real boolean and rejects TINYINT outright.
        self::assertStringContainsString('TINYINT(1)', $this->sql('mysql', fn ($t) => $t->boolean('a')));
        self::assertStringContainsString('BOOLEAN', $this->sql('pgsql', fn ($t) => $t->boolean('a')));
        self::assertStringContainsString('INTEGER', $this->sql('sqlite', fn ($t) => $t->boolean('a')));
    }

    public function test_json(): void
    {
        self::assertStringContainsString('JSON', $this->sql('mysql', fn ($t) => $t->json('a')));
        self::assertStringContainsString('JSONB', $this->sql('pgsql', fn ($t) => $t->json('a')));
        // SQLite has no JSON type; TEXT is where the JSON1 functions operate.
        self::assertStringContainsString('TEXT', $this->sql('sqlite', fn ($t) => $t->json('a')));
    }

    public function test_binary(): void
    {
        // BLOB does not exist in Postgres.
        self::assertStringContainsString('BLOB', $this->sql('mysql', fn ($t) => $t->binary('a')));
        self::assertStringContainsString('BYTEA', $this->sql('pgsql', fn ($t) => $t->binary('a')));
    }

    public function test_long_text(): void
    {
        // LONGTEXT is MySQL's alone.
        self::assertStringContainsString('LONGTEXT', $this->sql('mysql', fn ($t) => $t->longText('a')));
        self::assertStringContainsString('TEXT', $this->sql('pgsql', fn ($t) => $t->longText('a')));
        self::assertStringNotContainsString('LONGTEXT', $this->sql('pgsql', fn ($t) => $t->longText('a')));
    }

    public function test_datetime_becomes_timestamp_on_postgres(): void
    {
        // DATETIME is not a Postgres type at all.
        self::assertStringContainsString('DATETIME', $this->sql('mysql', fn ($t) => $t->dateTime('a')));
        self::assertStringContainsString('TIMESTAMP', $this->sql('pgsql', fn ($t) => $t->dateTime('a')));
        self::assertStringNotContainsString('DATETIME', $this->sql('pgsql', fn ($t) => $t->dateTime('a')));
    }

    public function test_unsigned_is_only_emitted_where_it_exists(): void
    {
        // Postgres rejects UNSIGNED. SQLite ignores it silently, which is
        // worse: the column then accepts negatives the schema claims it
        // cannot hold.
        self::assertStringContainsString('UNSIGNED', $this->sql('mysql', fn ($t) => $t->integer('a')->unsigned()));
        self::assertStringNotContainsString('UNSIGNED', $this->sql('pgsql', fn ($t) => $t->integer('a')->unsigned()));
        self::assertStringNotContainsString('UNSIGNED', $this->sql('sqlite', fn ($t) => $t->integer('a')->unsigned()));
    }

    // ── identifiers ──────────────────────────────────────────────────────

    public function test_identifiers_are_quoted_for_the_target(): void
    {
        // Backticks are a syntax error in Postgres.
        self::assertStringContainsString('`t`', $this->sql('mysql', fn ($t) => $t->string('a')));
        self::assertStringContainsString('"t"', $this->sql('pgsql', fn ($t) => $t->string('a')));
        self::assertStringNotContainsString('`', $this->sql('pgsql', fn ($t) => $t->string('a')));
    }

    public function test_a_reserved_word_column_survives(): void
    {
        // `order` and `group` are reserved everywhere; unquoted they are a
        // syntax error rather than a column.
        foreach (['mysql', 'pgsql', 'sqlite'] as $driver) {
            $sql = $this->sql($driver, fn ($t) => $t->string('order'));

            self::assertMatchesRegularExpression('/[`"]order[`"]/', $sql, $driver);
        }
    }

    // ── defaults ─────────────────────────────────────────────────────────

    public function test_boolean_defaults_suit_the_column_type(): void
    {
        // Postgres will not take 1 for a boolean column.
        self::assertStringContainsString('DEFAULT TRUE', $this->sql('pgsql', fn ($t) => $t->boolean('a')->default(true)));
        self::assertStringContainsString('DEFAULT 1', $this->sql('mysql', fn ($t) => $t->boolean('a')->default(true)));
    }

    public function test_string_defaults_are_escaped(): void
    {
        $sql = $this->sql('mysql', fn ($t) => $t->string('a')->default("O'Brien"));

        self::assertStringContainsString("'O''Brien'", $sql);
    }

    public function test_a_null_default_is_the_keyword_not_a_string(): void
    {
        $sql = $this->sql('mysql', fn ($t) => $t->string('a')->nullable()->default(null));

        self::assertStringContainsString('DEFAULT NULL', $sql);
        self::assertStringNotContainsString("DEFAULT ''", $sql);
    }

    // ── nullability ──────────────────────────────────────────────────────

    public function test_mysql_timestamp_keeps_exactly_one_nullability_keyword(): void
    {
        // MySQL's TIMESTAMP carries NULL in its type to opt out of the
        // implicit NOT NULL DEFAULT CURRENT_TIMESTAMP. Appending another
        // keyword would be a syntax error.
        $nullable = $this->sql('mysql', fn ($t) => $t->timestamp('a')->nullable());

        self::assertSame(1, substr_count($nullable, 'NULL'), $nullable);

        $notNull = $this->sql('mysql', fn ($t) => $t->timestamp('a'));

        self::assertStringContainsString('NOT NULL', $notNull);
        self::assertStringNotContainsString('NULL NOT NULL', $notNull);
    }

    // ── indexes ──────────────────────────────────────────────────────────

    public function test_index_if_not_exists_only_where_supported(): void
    {
        // MySQL has never supported IF NOT EXISTS on CREATE INDEX.
        self::assertFalse(Grammar::forDriver('mysql')->supportsIndexIfNotExists());
        self::assertTrue(Grammar::forDriver('sqlite')->supportsIndexIfNotExists());
        self::assertTrue(Grammar::forDriver('pgsql')->supportsIndexIfNotExists());
    }

    // ── the factory ──────────────────────────────────────────────────────

    public function test_an_unknown_driver_names_the_supported_ones(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/mysql, pgsql, sqlite/');

        Grammar::forDriver('oracle');
    }

    public function test_the_grammar_is_chosen_from_the_connection(): void
    {
        $grammar = Grammar::for(new \PDO('sqlite::memory:'));

        self::assertStringContainsString('AUTOINCREMENT', $grammar->increments('id'));
    }

    // ── errors that are not "already exists" ─────────────────────────────

    public function test_already_exists_is_recognised_but_other_errors_are_not(): void
    {
        // Swallowing every error, which the builder used to do, makes a
        // genuinely broken index indistinguishable from one that already
        // exists: the migration reports success and the index is absent.
        $grammar = Grammar::forDriver('mysql');

        self::assertTrue($grammar->isAlreadyExists(new \RuntimeException('Table "users" already exists')));
        self::assertTrue($grammar->isAlreadyExists(new \RuntimeException('Duplicate key name idx_a')));
        self::assertFalse($grammar->isAlreadyExists(new \RuntimeException('You have an error in your SQL syntax')));
        self::assertFalse($grammar->isAlreadyExists(new \RuntimeException('Access denied for user')));
    }
}
