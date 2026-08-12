<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Atlas\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * The column types a real schema needs and Blueprint did not have.
 *
 * All three were found by building an admin panel against it: a foreign key to
 * an `id()` column had no correct type, an IP address column had no type that
 * fits IPv6, and a pivot table had no way to declare a composite primary key.
 */
final class BlueprintColumnTypesTest extends TestCase
{
    /** Blueprint needs a connection to build against; in-memory is enough to render SQL. */
    private static function pdo(): \PDO
    {
        return new \PDO('sqlite::memory:');
    }

    public function test_unsigned_big_integer_matches_what_id_produces(): void
    {
        // A foreign key referencing id() has to be an unsigned big integer.
        // bigInteger() is signed and unsignedInteger() is too narrow, so
        // neither could be used for the most common foreign key there is.
        $table = new Blueprint('audit_logs', self::pdo());
        $table->unsignedBigInteger('resource_id');

        $sql = $table->toSql();

        self::assertStringContainsString('BIGINT UNSIGNED', $sql);
        self::assertStringContainsString('resource_id', $sql);
    }

    public function test_ip_address_is_wide_enough_for_ipv6(): void
    {
        // 45 characters: the longest an IPv6 address gets once it carries an
        // embedded IPv4 one. VARCHAR(15) holds every IPv4 address and silently
        // truncates every IPv6 one, which is the bug this prevents.
        $table = new Blueprint('audit_logs', self::pdo());
        $table->ipAddress('ip_address');

        self::assertStringContainsString('VARCHAR(45)', $table->toSql());
    }

    public function test_an_ipv6_address_fits_in_45_characters(): void
    {
        // The longest form there is, so the width above is not a guess.
        $longest = '0000:0000:0000:0000:0000:ffff:255.255.255.255';

        self::assertSame(45, strlen($longest));
    }

    public function test_a_composite_primary_key_is_declared_in_the_table(): void
    {
        $table = new Blueprint('permission_role', self::pdo());
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);

        $sql = $table->toSql();

        self::assertStringContainsString('PRIMARY KEY (`permission_id`, `role_id`)', $sql);

        // Inside CREATE TABLE, not appended after it. SQLite cannot add a
        // primary key with ALTER TABLE at all, so a key emitted separately
        // would simply never exist.
        self::assertStringContainsString('CREATE TABLE', $sql);
        self::assertStringNotContainsString('ALTER TABLE', $sql);
    }

    public function test_a_single_column_primary_key_works_too(): void
    {
        $table = new Blueprint('settings', self::pdo());
        $table->string('key');
        $table->primary('key');

        self::assertStringContainsString('PRIMARY KEY (`key`)', $table->toSql());
    }

    public function test_a_pivot_table_needs_no_surrogate_id(): void
    {
        // The point of composite keys: the pairing is the identity, and an
        // extra auto-incrementing column would let the same pair be inserted
        // twice while looking unique.
        $table = new Blueprint('role_user', self::pdo());
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('admin_user_id');
        $table->primary(['role_id', 'admin_user_id']);

        $sql = $table->toSql();

        self::assertStringNotContainsString('AUTOINCREMENT', $sql);
        self::assertStringContainsString('PRIMARY KEY', $sql);
    }
}
