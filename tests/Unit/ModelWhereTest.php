<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Atlas\Connection\ConnectionPool;
use Libxa\Atlas\Model;
use PHPUnit\Framework\TestCase;

/**
 * `Model::where()` with two arguments.
 *
 * The builder decides whether the second argument is an operator or a value by
 * counting arguments. The model forwarded three unconditionally, so the value
 * was read as an operator and the most common query anyone writes against a
 * model threw instead of running.
 */
final class ModelWhereTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT, qty INTEGER)');
        $this->pdo->exec("INSERT INTO widgets (name, qty) VALUES ('bolt', 5), ('nut', 12), ('washer', 5)");

        ConnectionPool::getInstance()->setConnection('default', $this->pdo);
    }

    protected function tearDown(): void
    {
        ConnectionPool::getInstance()->reset();
    }

    public function test_two_arguments_means_equals(): void
    {
        $rows = WhereTestWidget::where('name', 'bolt')->get();

        self::assertCount(1, $rows);
        self::assertSame('bolt', $rows[0]->name);
    }

    public function test_a_value_containing_an_at_sign_is_not_read_as_an_operator(): void
    {
        // The exact failure: an email address was passed as the second
        // argument and reported as "Unsupported SQL operator".
        $this->pdo->exec("INSERT INTO widgets (name, qty) VALUES ('ada@example.com', 1)");

        $rows = WhereTestWidget::where('name', 'ada@example.com')->get();

        self::assertCount(1, $rows);
    }

    public function test_three_arguments_still_uses_the_operator(): void
    {
        $rows = WhereTestWidget::where('qty', '>', 5)->get();

        self::assertCount(1, $rows);
        self::assertSame('nut', $rows[0]->name);
    }

    public function test_a_null_value_with_two_arguments_becomes_is_null(): void
    {
        $this->pdo->exec("INSERT INTO widgets (name, qty) VALUES ('spare', NULL)");

        $rows = WhereTestWidget::where('qty', null)->get();

        self::assertCount(1, $rows);
        self::assertSame('spare', $rows[0]->name);
    }

    public function test_the_builder_behaves_the_same_way_directly(): void
    {
        // The model and the builder must not disagree about what two
        // arguments mean, or which one you reached for changes the result.
        $viaBuilder = \Libxa\Atlas\DB::table('widgets')->where('name', 'bolt')->get();
        $viaModel = WhereTestWidget::where('name', 'bolt')->get();

        self::assertSame(count($viaBuilder), count($viaModel));
    }

    public function test_an_actual_bad_operator_is_still_refused(): void
    {
        // The fix must not turn the operator check off: three arguments still
        // means the middle one is an operator, and a wrong one is a mistake
        // worth reporting.
        $this->expectException(\InvalidArgumentException::class);

        WhereTestWidget::where('qty', 'NOT-AN-OPERATOR', 5)->get();
    }
}

class WhereTestWidget extends Model
{
    protected string $table = 'widgets';
}
