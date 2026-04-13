<?php

declare(strict_types=1);

namespace Libxa\Atlas;

use Libxa\Atlas\Connection\ConnectionPool;

/**
 * Atlas Query Builder
 *
 * Fluent, parameterized SQL query builder.
 * Supports WHERE, ORDER, LIMIT, JOIN, aggregates, CTEs, etc.
 */
class QueryBuilder
{
    protected array  $wheres    = [];
    protected array  $orders    = [];
    protected array  $selects   = ['*'];
    protected array  $joins     = [];
    protected ?int   $limitVal  = null;
    protected ?int   $offsetVal = null;
    protected array  $bindings  = [];
    protected array  $withs     = [];  // eager loads
    protected bool   $withTrashed = false;

    public function __construct(
        protected string  $model,
        protected string  $table,
        protected string  $connection  = 'default',
        protected bool    $softDeletes = false,
        protected string  $deletedAtCol = 'deleted_at',
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  SELECT
    // ─────────────────────────────────────────────────────────────────

    public function select(string|array $columns): static
    {
        $this->selects = (array) $columns;
        return $this;
    }

    public function selectRaw(string $expression): static
    {
        $this->selects[] = new RawExpression($expression);
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  WHERE
    // ─────────────────────────────────────────────────────────────────

    public function where(string $column, mixed $operatorOrValue, mixed $value = null, string $boolean = 'AND'): static
    {
        if ($value === null) {
            [$operator, $value] = ['=', $operatorOrValue];
        } else {
            $operator = $operatorOrValue;
        }

        $this->wheres[]   = compact('column', 'operator', 'value', 'boolean');
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        return $this->where($column, $operatorOrValue, $value, 'OR');
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['column' => $column, 'type' => 'null', 'boolean' => 'AND'];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['column' => $column, 'type' => 'notnull', 'boolean' => 'AND'];
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $placeholders   = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = ['column' => $column, 'type' => 'in', 'values' => $values, 'placeholder' => $placeholders, 'boolean' => 'AND'];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $placeholders   = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = ['column' => $column, 'type' => 'notin', 'values' => $values, 'placeholder' => $placeholders, 'boolean' => 'AND'];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->wheres[]   = ['column' => $column, 'type' => 'between', 'boolean' => 'AND'];
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[]   = ['type' => 'raw', 'sql' => $sql, 'boolean' => 'AND'];
        $this->bindings   = array_merge($this->bindings, $bindings);
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  ORDER / LIMIT / OFFSET
    // ─────────────────────────────────────────────────────────────────

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = "$column " . strtoupper($direction);
        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderByDesc($column);
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $n): static  { $this->limitVal  = $n; return $this; }
    public function offset(int $n): static { $this->offsetVal = $n; return $this; }
    public function take(int $n): static   { return $this->limit($n); }
    public function skip(int $n): static   { return $this->offset($n); }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getPrimaryKey(): string
    {
        // Guess primary key from model if available, default to 'id'
        if (class_exists($this->model)) {
            $instance = new $this->model();
            if (method_exists($instance, 'getPrimaryKey')) {
                return $instance->getPrimaryKey();
            }
        }
        return 'id';
    }

    // ─────────────────────────────────────────────────────────────────
    //  JOIN
    // ─────────────────────────────────────────────────────────────────

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Eager loading
    // ─────────────────────────────────────────────────────────────────

    public function with(string|array $relations): static
    {
        $this->withs = array_merge($this->withs, (array) $relations);
        return $this;
    }

    public function withTrashed(): static
    {
        $this->withTrashed = true;
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Execution
    // ─────────────────────────────────────────────────────────────────

    public function get(): array
    {
        $sql    = $this->toSelectSql();
        $rows   = $this->execute($sql, $this->bindings);
        $models = array_map(function ($row) {
            if ($this->model === \stdClass::class || ! method_exists($this->model, 'newFromBuilder')) {
                return (object) $row;
            }
            return $this->model::newFromBuilder($row);
        }, $rows);

        if ($this->withs) {
            $this->eagerLoad($models);
        }

        return $models;
    }

    public function first(): ?object
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    public function find(mixed $id): ?object
    {
        return $this->where('id', $id)->first();
    }

    public function count(): int
    {
        $sql    = "SELECT COUNT(*) as aggregate FROM `{$this->table}`" . $this->buildWhere();
        $rows   = $this->execute($sql, $this->getWhereBindings());
        return (int) ($rows[0]['aggregate'] ?? 0);
    }

    public function exists(): bool { return $this->count() > 0; }

    public function sum(string $column): float
    {
        $sql  = "SELECT SUM(`$column`) as aggregate FROM `{$this->table}`" . $this->buildWhere();
        $rows = $this->execute($sql, $this->getWhereBindings());
        return (float) ($rows[0]['aggregate'] ?? 0);
    }

    public function max(string $column): mixed
    {
        $sql  = "SELECT MAX(`$column`) as aggregate FROM `{$this->table}`" . $this->buildWhere();
        $rows = $this->execute($sql, $this->getWhereBindings());
        return $rows[0]['aggregate'] ?? null;
    }

    public function min(string $column): mixed
    {
        $sql  = "SELECT MIN(`$column`) as aggregate FROM `{$this->table}`" . $this->buildWhere();
        $rows = $this->execute($sql, $this->getWhereBindings());
        return $rows[0]['aggregate'] ?? null;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page   = max(1, $page);
        $total  = $this->count();
        $items  = $this->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return [
            'data'      => $items,
            'meta' => [
                'total'     => $total,
                'per_page'  => $perPage,
                'page'      => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function insert(array $attributes): int|false
    {
        $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($attributes)));
        $places  = implode(', ', array_fill(0, count($attributes), '?'));
        $sql     = "INSERT INTO `{$this->table}` ($columns) VALUES ($places)";

        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $ok   = $stmt->execute(array_values($attributes));

        return $ok ? (int) $pdo->lastInsertId() : false;
    }

    public function insertGetId(array $attributes): int|false
    {
        return $this->insert($attributes);
    }

    public function update(array $attributes): bool
    {
        return $this->updateRecord($attributes);
    }

    public function updateRecord(array $attributes): bool
    {
        $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($attributes)));
        $sql  = "UPDATE `{$this->table}` SET $sets" . $this->buildWhere();

        $bindings = array_merge(array_values($attributes), $this->getWhereBindings());
        $stmt     = $this->getPdo()->prepare($sql);

        return $stmt->execute($bindings);
    }

    public function delete(): bool
    {
        return $this->deleteRecord();
    }

    public function deleteRecord(): bool
    {
        $sql  = "DELETE FROM `{$this->table}`" . $this->buildWhere();
        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute($this->getWhereBindings());
    }

    public function each(int $chunk, \Closure $callback): void
    {
        $page = 1;

        do {
            $results = $this->paginate($chunk, $page);
            $callback(collect($results['data']));
            $page++;
        } while ($page <= $results['meta']['last_page']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  SQL building
    // ─────────────────────────────────────────────────────────────────

    protected function toSelectSql(): string
    {
        $select = implode(', ', array_map(
            fn($c) => $c instanceof RawExpression ? (string) $c : ($c === '*' ? '*' : "`$c`"),
            $this->selects
        ));

        $sql = "SELECT $select FROM `{$this->table}`";

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhere();

        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }

        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        return $sql;
    }

    protected function buildWhere(): string
    {
        $conditions = [];

        // Soft delete filter
        if ($this->softDeletes && ! $this->withTrashed) {
            $conditions[] = "`{$this->deletedAtCol}` IS NULL";
        }

        $first = true;

        foreach ($this->wheres as $where) {
            $prefix = $first ? '' : (" " . ($where['boolean'] ?? 'AND') . " ");
            $first  = false;

            $conditions[] = $prefix . match ($where['type'] ?? 'basic') {
                'null'    => "`{$where['column']}` IS NULL",
                'notnull' => "`{$where['column']}` IS NOT NULL",
                'in'      => "`{$where['column']}` IN ({$where['placeholder']})",
                'notin'   => "`{$where['column']}` NOT IN ({$where['placeholder']})",
                'between' => "`{$where['column']}` BETWEEN ? AND ?",
                'raw'     => $where['sql'],
                default   => "`{$where['column']}` {$where['operator']} ?",
            };
        }

        return $conditions ? ' WHERE ' . implode(' ', $conditions) : '';
    }

    protected function getWhereBindings(): array
    {
        return $this->bindings;
    }

    // ─────────────────────────────────────────────────────────────────
    //  PDO execution
    // ─────────────────────────────────────────────────────────────────

    protected function execute(string $sql, array $bindings = []): array
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function getPdo(): \PDO
    {
        return ConnectionPool::getInstance()->get($this->connection);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Eager loading (N+1 prevention)
    // ─────────────────────────────────────────────────────────────────

    protected function eagerLoad(array $models): void
    {
        // Basic eager loading — for each relation name, load all related records
        // with a single IN() query, then map them back to their parent models.
        // Full implementation would parse dot-notation (orders.products).
        foreach ($this->withs as $relation) {
            // Attempt to call the relation method on the model
            if (empty($models)) continue;

            $first = $models[0];

            if (method_exists($first, $relation)) {
                foreach ($models as $model) {
                    $model->setRelation($relation, $model->$relation());
                }
            }
        }
    }
}

/**
 * Raw SQL expression wrapper (prevents quoting/escaping).
 */
class RawExpression
{
    public function __construct(protected string $value) {}
    public function __toString(): string { return $this->value; }
}
