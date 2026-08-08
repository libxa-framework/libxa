<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Atlas\Connection\ConnectionPool;
use Libxa\Atlas\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real in-memory SQLite database, so a query that generates
 * invalid SQL fails here rather than in production.
 */
class QueryBuilderTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        ConnectionPool::resetInstance();

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $this->pdo->exec('CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            views INTEGER NOT NULL DEFAULT 0,
            author_id INTEGER NULL,
            deleted_at TEXT NULL
        )');

        ConnectionPool::getInstance()->setConnection('default', $this->pdo);
    }

    protected function tearDown(): void
    {
        ConnectionPool::resetInstance();

        parent::tearDown();
    }

    private function builder(bool $softDeletes = false): QueryBuilder
    {
        return new QueryBuilder(\stdClass::class, 'posts', 'default', $softDeletes);
    }

    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO posts (title, views, author_id, deleted_at) VALUES
            ('alpha', 10, 1, NULL),
            ('beta',  20, 1, NULL),
            ('gamma', 30, 2, NULL),
            ('trashed', 99, 2, '2026-01-01 00:00:00')");
    }

    public function test_basic_select_and_where(): void
    {
        $this->seed();

        $rows = $this->builder()->where('author_id', 1)->get();

        $this->assertCount(2, $rows);
        $this->assertSame('alpha', $rows[0]->title);
    }

    /**
     * The soft-delete filter was appended while the "is this the first
     * condition" flag was still true, so the first user where() was emitted
     * with no AND: "WHERE deleted_at IS NULL `author_id` = ?". Every
     * soft-deleting model with a where clause produced a SQL syntax error.
     */
    public function test_soft_delete_filter_combines_with_a_where_clause(): void
    {
        $this->seed();

        $rows = $this->builder(softDeletes: true)->where('author_id', 2)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('gamma', $rows[0]->title);
    }

    public function test_soft_deleted_rows_are_excluded_by_default(): void
    {
        $this->seed();

        $this->assertCount(3, $this->builder(softDeletes: true)->get());
        $this->assertCount(4, $this->builder(softDeletes: true)->withTrashed()->get());
    }

    public function test_soft_delete_filter_combines_with_multiple_wheres(): void
    {
        $this->seed();

        $rows = $this->builder(softDeletes: true)
            ->where('author_id', 1)
            ->where('views', '>', 15)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('beta', $rows[0]->title);
    }

    /**
     * where('col', null) used to emit `col = ?` bound to NULL, which is never
     * true in SQL, so the row could never be found.
     */
    public function test_where_null_value_becomes_is_null(): void
    {
        $this->pdo->exec("INSERT INTO posts (title, views, author_id) VALUES ('orphan', 1, NULL)");
        $this->seed();

        $rows = $this->builder()->where('author_id', null)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('orphan', $rows[0]->title);
    }

    public function test_where_not_equal_null_becomes_is_not_null(): void
    {
        $this->pdo->exec("INSERT INTO posts (title, views, author_id) VALUES ('orphan', 1, NULL)");
        $this->seed();

        $this->assertCount(4, $this->builder()->where('author_id', '!=', null)->get());
    }

    public function test_an_unknown_operator_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->where('title', 'DROP TABLE posts; --', 'x');
    }

    public function test_or_where(): void
    {
        $this->seed();

        $rows = $this->builder()->where('title', 'alpha')->orWhere('title', 'gamma')->get();

        $this->assertCount(2, $rows);
    }

    public function test_where_in_and_not_in(): void
    {
        $this->seed();

        $this->assertCount(2, $this->builder()->whereIn('title', ['alpha', 'beta'])->get());
        $this->assertCount(2, $this->builder()->whereNotIn('title', ['alpha', 'beta'])->get());
    }

    /** IN () is a syntax error; an empty set must simply match nothing. */
    public function test_where_in_with_an_empty_set_matches_nothing(): void
    {
        $this->seed();

        $this->assertCount(0, $this->builder()->whereIn('title', [])->get());
        $this->assertCount(4, $this->builder()->whereNotIn('title', [])->get());
    }

    /** A filtered array keeps gapped keys, which mis-binds positional params. */
    public function test_where_in_handles_a_non_sequential_array(): void
    {
        $this->seed();

        $values = array_filter(['alpha', 'skip', 'beta'], fn($v) => $v !== 'skip');

        $this->assertCount(2, $this->builder()->whereIn('title', $values)->get());
    }

    public function test_where_between(): void
    {
        $this->seed();

        $this->assertCount(2, $this->builder()->whereBetween('views', 10, 20)->get());
    }

    /**
     * orderBy() interpolated both arguments verbatim — the single most common
     * SQL-injection sink in an app that sorts by a query parameter.
     */
    public function test_order_by_direction_is_restricted(): void
    {
        $this->seed();

        $rows = $this->builder()->orderBy('views', 'DESC; DROP TABLE posts')->get();

        // Falls back to ASC rather than executing the injected statement.
        $this->assertSame('alpha', $rows[0]->title);
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());
    }

    public function test_order_by_desc(): void
    {
        $this->seed();

        $this->assertSame('trashed', $this->builder()->orderByDesc('views')->get()[0]->title);
    }

    public function test_aggregates(): void
    {
        $this->seed();

        $this->assertSame(4, $this->builder()->count());
        $this->assertSame(159.0, $this->builder()->sum('views'));
        $this->assertSame(99, (int) $this->builder()->max('views'));
        $this->assertSame(10, (int) $this->builder()->min('views'));
    }

    public function test_aggregates_respect_the_soft_delete_filter(): void
    {
        $this->seed();

        $this->assertSame(3, $this->builder(softDeletes: true)->count());
    }

    public function test_exists(): void
    {
        $this->seed();

        $this->assertTrue($this->builder()->where('title', 'alpha')->exists());
        $this->assertFalse($this->builder()->where('title', 'nope')->exists());
    }

    public function test_insert_returns_the_new_id(): void
    {
        $id = $this->builder()->insert(['title' => 'new', 'views' => 5]);

        $this->assertGreaterThan(0, $id);
        $this->assertSame('new', $this->builder()->find($id)->title);
    }

    public function test_update_and_delete_respect_the_where_clause(): void
    {
        $this->seed();

        $this->builder()->where('title', 'alpha')->update(['views' => 111]);
        $this->assertSame(111, (int) $this->builder()->where('title', 'alpha')->first()->views);

        $this->builder()->where('title', 'alpha')->delete();
        $this->assertFalse($this->builder()->where('title', 'alpha')->exists());
        $this->assertSame(3, $this->builder()->count());
    }

    /**
     * paginate() used to leave its LIMIT/OFFSET on the builder, so a second
     * call compounded them and count() saw the wrong window.
     */
    public function test_paginate_does_not_leak_limit_and_offset(): void
    {
        $this->seed();

        $first  = $this->builder()->paginate(2, 1);
        $second = $this->builder()->paginate(2, 2);

        $this->assertSame(4, $first['meta']['total']);
        $this->assertSame(2, $first['meta']['last_page']);
        $this->assertCount(2, $first['data']);
        $this->assertCount(2, $second['data']);
        $this->assertNotSame($first['data'][0]->title, $second['data'][0]->title);
    }

    public function test_paginate_meta(): void
    {
        $this->seed();

        $page = $this->builder()->paginate(3, 2);

        $this->assertSame(4, $page['meta']['total']);
        $this->assertSame(4, $page['meta']['from']);
        $this->assertSame(4, $page['meta']['to']);
    }

    public function test_each_walks_every_row_exactly_once(): void
    {
        $this->seed();

        $seen = [];
        $this->builder()->orderBy('id')->each(2, function ($chunk) use (&$seen) {
            foreach ($chunk as $row) {
                $seen[] = $row->title;
            }
        });

        $this->assertSame(['alpha', 'beta', 'gamma', 'trashed'], $seen);
    }

    public function test_limit_and_offset(): void
    {
        $this->seed();

        $rows = $this->builder()->orderBy('id')->limit(2)->offset(1)->get();

        $this->assertCount(2, $rows);
        $this->assertSame('beta', $rows[0]->title);
    }

    public function test_identifiers_containing_a_dot_are_quoted_per_segment(): void
    {
        $this->seed();

        $rows = $this->builder()->where('posts.title', 'alpha')->get();

        $this->assertCount(1, $rows);
    }

    public function test_an_unknown_join_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->join('users', 'posts.author_id', '=', 'users.id', 'EVIL');
    }
}
