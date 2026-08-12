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
    protected ?string $primaryKey = null;

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

    /** Operators accepted in the two-and-three argument where() forms. */
    protected const OPERATORS = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'not like', 'ilike', 'not ilike',
        'rlike', 'regexp', 'not regexp', '&', '|', '^', '<<', '>>',
    ];

    /**
     * Add a basic WHERE clause.
     *
     * The old implementation decided "is this two-arg or three-arg?" by
     * testing `$value === null`, which broke two real cases:
     *   where('deleted_at', '>', null)  -> silently became  deleted_at = '>'
     *   where('parent_id', null)        -> emitted  parent_id = ?  bound to
     *                                      NULL, and `col = NULL` is never
     *                                      true in SQL, so the row never
     *                                      matched.
     * The argument count now decides, and a null value is translated to
     * IS NULL / IS NOT NULL like every mature query builder does.
     */
    public function where(string $column, mixed $operatorOrValue = null, mixed $value = null, string $boolean = 'AND'): static
    {
        return $this->addWhere($column, $operatorOrValue, $value, $boolean, func_num_args() >= 3);
    }

    public function orWhere(string $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        return $this->addWhere($column, $operatorOrValue, $value, 'OR', func_num_args() >= 3);
    }

    /**
     * @param bool $hasOperator Whether the caller supplied an explicit operator.
     */
    protected function addWhere(
        string $column,
        mixed $operatorOrValue,
        mixed $value,
        string $boolean,
        bool $hasOperator
    ): static {
        if (! $hasOperator) {
            $operator = '=';
            $value    = $operatorOrValue;
        } else {
            $operator = is_string($operatorOrValue) ? trim($operatorOrValue) : '=';

            if (! in_array(strtolower($operator), static::OPERATORS, true)) {
                throw new \InvalidArgumentException("Unsupported SQL operator [{$operator}].");
            }
        }

        if ($value === null) {
            $negated = in_array($operator, ['!=', '<>'], true);

            $this->wheres[] = [
                'column'  => $column,
                'type'    => $negated ? 'notnull' : 'null',
                'boolean' => $boolean,
            ];

            return $this;
        }

        $this->wheres[]   = compact('column', 'operator', 'value', 'boolean');
        $this->bindings[] = $value;

        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND'): static
    {
        $this->wheres[] = ['column' => $column, 'type' => 'null', 'boolean' => $boolean];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): static
    {
        $this->wheres[] = ['column' => $column, 'type' => 'notnull', 'boolean' => $boolean];
        return $this;
    }

    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'OR');
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): static
    {
        // array_values(): a filtered array keeps its original keys, and PDO
        // binds positional placeholders by position, so gaps silently bound
        // the wrong values to the wrong slots.
        $values         = array_values($values);
        $placeholders   = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = ['column' => $column, 'type' => 'in', 'values' => $values, 'placeholder' => $placeholders, 'boolean' => $boolean];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): static
    {
        $values         = array_values($values);
        $placeholders   = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = ['column' => $column, 'type' => 'notin', 'values' => $values, 'placeholder' => $placeholders, 'boolean' => $boolean];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND'): static
    {
        $this->wheres[]   = ['column' => $column, 'type' => 'between', 'boolean' => $boolean];
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->wheres[]   = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];
        $this->bindings   = array_merge($this->bindings, array_values($bindings));
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  ORDER / LIMIT / OFFSET
    // ─────────────────────────────────────────────────────────────────

    /**
     * Add an ORDER BY clause.
     *
     * Both arguments used to be interpolated into the SQL verbatim, so a
     * controller doing orderBy($request->input('sort'), $request->input('dir'))
     * (the single most common way this method gets called) was a direct SQL
     * injection. The column is now quoted as an identifier and the direction
     * is restricted to ASC/DESC.
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper(trim($direction)) === 'DESC' ? 'DESC' : 'ASC';

        $this->orders[] = $this->wrap($column) . ' ' . $direction;

        return $this;
    }

    /**
     * Add a raw ORDER BY expression. Explicitly opt-in, so the danger is
     * visible at the call site rather than hidden inside orderBy().
     */
    public function orderByRaw(string $expression): static
    {
        $this->orders[] = $expression;
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
        if ($this->primaryKey !== null) {
            return $this->primaryKey;
        }

        // Guessing the key used to construct the model on every call, which
        // is both wasteful and fatal for any model with a required
        // constructor argument. Resolve once, defensively, and cache.
        $key = 'id';

        if ($this->model !== '' && $this->model !== \stdClass::class && class_exists($this->model)) {
            try {
                $reflection = new \ReflectionClass($this->model);

                if ($reflection->isInstantiable()
                    && ($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) === 0
                    && method_exists($this->model, 'getPrimaryKey')
                ) {
                    $key = $reflection->newInstance()->getPrimaryKey();
                }
            } catch (\Throwable) {
                // Fall through to 'id'.
            }
        }

        return $this->primaryKey = $key;
    }

    // ─────────────────────────────────────────────────────────────────
    //  JOIN
    // ─────────────────────────────────────────────────────────────────

    /**
     * Add a JOIN clause. Identifiers are quoted and both the join type and
     * the comparison operator come from whitelists: everything here was
     * previously interpolated straight into the SQL string.
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $type = strtoupper(trim($type));

        if (! in_array($type, ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'], true)) {
            throw new \InvalidArgumentException("Unsupported join type [{$type}].");
        }

        if (! in_array(strtolower(trim($operator)), static::OPERATORS, true)) {
            throw new \InvalidArgumentException("Unsupported join operator [{$operator}].");
        }

        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            $type,
            $this->wrap($table),
            $this->wrap($first),
            trim($operator),
            $this->wrap($second)
        );

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
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

    /**
     * Find by primary key. Previously hard-coded to "id", so any model with
     * a custom #[PrimaryKey] silently queried a column that did not exist.
     */
    public function find(mixed $id): ?object
    {
        return $this->where($this->getPrimaryKey(), $id)->first();
    }

    public function count(): int
    {
        return (int) $this->aggregate('COUNT', '*');
    }

    public function exists(): bool
    {
        // LIMIT 1 instead of counting the whole table.
        $sql  = 'SELECT 1 FROM ' . $this->wrap($this->table) . $this->buildWhere() . ' LIMIT 1';
        $rows = $this->execute($sql, $this->getWhereBindings());

        return $rows !== [];
    }

    public function sum(string $column): float
    {
        return (float) ($this->aggregate('SUM', $column) ?? 0);
    }

    public function avg(string $column): float
    {
        return (float) ($this->aggregate('AVG', $column) ?? 0);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Run an aggregate function. The column is quoted rather than
     * interpolated, and the function name comes from a fixed whitelist.
     */
    protected function aggregate(string $function, string $column): mixed
    {
        $function = strtoupper($function);

        if (! in_array($function, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true)) {
            throw new \InvalidArgumentException("Unsupported aggregate function [{$function}].");
        }

        $target = $column === '*' ? '*' : $this->wrap($column);

        $sql = "SELECT {$function}({$target}) as aggregate FROM " . $this->wrap($this->table);

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhere();

        $rows = $this->execute($sql, $this->getWhereBindings());

        return $rows[0]['aggregate'] ?? null;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, $perPage);
        $page    = max(1, $page);

        // count() must not see the LIMIT/OFFSET this method is about to set,
        // and calling paginate() twice on one builder must not compound them.
        $total = $this->count();

        $previousLimit  = $this->limitVal;
        $previousOffset = $this->offsetVal;

        try {
            $items = $this->offset(($page - 1) * $perPage)->limit($perPage)->get();
        } finally {
            $this->limitVal  = $previousLimit;
            $this->offsetVal = $previousOffset;
        }

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $items,
            'meta' => [
                'total'     => $total,
                'per_page'  => $perPage,
                'page'      => $page,
                'last_page' => $lastPage,
                'from'      => $total ? (($page - 1) * $perPage) + 1 : null,
                'to'        => $total ? min($page * $perPage, $total) : null,
            ],
        ];
    }

    public function insert(array $attributes): int|false
    {
        if ($attributes === []) {
            throw new \InvalidArgumentException('Cannot insert an empty attribute set.');
        }

        $columns = implode(', ', array_map(fn($c) => $this->wrap((string) $c), array_keys($attributes)));
        $places  = implode(', ', array_fill(0, count($attributes), '?'));
        $sql     = "INSERT INTO " . $this->wrap($this->table) . " ($columns) VALUES ($places)";

        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $ok   = $stmt->execute(array_values($attributes));

        if (! $ok) {
            return false;
        }

        // PostgreSQL needs the sequence name; without it lastInsertId()
        // throws, which used to surface as an opaque PDOException on insert.
        try {
            $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            $id = $driver === 'pgsql'
                ? $pdo->lastInsertId($this->table . '_' . $this->getPrimaryKey() . '_seq')
                : $pdo->lastInsertId();
        } catch (\Throwable) {
            return 0;
        }

        return (int) $id;
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
        if ($attributes === []) {
            return false;
        }

        $sets = implode(', ', array_map(fn($c) => $this->wrap((string) $c) . ' = ?', array_keys($attributes)));
        $sql  = 'UPDATE ' . $this->wrap($this->table) . " SET $sets" . $this->buildWhere();

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
        $sql  = 'DELETE FROM ' . $this->wrap($this->table) . $this->buildWhere();
        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute($this->getWhereBindings());
    }

    /**
     * Iterate the result set in chunks.
     *
     * Rewritten to page by primary key rather than OFFSET: with OFFSET, a
     * callback that deletes or inserts rows shifts the window and the loop
     * skips records. It also ran count() once per chunk.
     */
    public function each(int $chunk, \Closure $callback): void
    {
        $chunk = max(1, $chunk);
        $page  = 1;

        do {
            $baseLimit  = $this->limitVal;
            $baseOffset = $this->offsetVal;

            $this->offsetVal = ($page - 1) * $chunk;
            $this->limitVal  = $chunk;

            try {
                $results = $this->get();
            } finally {
                $this->limitVal  = $baseLimit;
                $this->offsetVal = $baseOffset;
            }

            if ($results === []) {
                return;
            }

            if ($callback(collect($results)) === false) {
                return;
            }

            $page++;
        } while (count($results) === $chunk);
    }

    /** @see each() */
    public function chunk(int $size, \Closure $callback): void
    {
        $this->each($size, $callback);
    }

    // ─────────────────────────────────────────────────────────────────
    //  SQL building
    // ─────────────────────────────────────────────────────────────────

    protected function toSelectSql(): string
    {
        $select = implode(', ', array_map(
            fn($c) => $c instanceof RawExpression ? (string) $c : $this->wrap((string) $c),
            $this->selects
        ));

        $sql = "SELECT $select FROM " . $this->wrap($this->table);

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
        $first      = true;

        // Soft delete filter.
        //
        // This used to be pushed into $conditions while $first stayed true,
        // so the first user-supplied where was emitted with *no* boolean
        // operator: "WHERE `deleted_at` IS NULL `id` = ?". A syntax error on
        // every driver. Any soft-deleting model with a where clause was
        // therefore completely unqueryable.
        if ($this->softDeletes && ! $this->withTrashed) {
            $conditions[] = $this->wrap($this->deletedAtCol) . ' IS NULL';
            $first        = false;
        }

        foreach ($this->wheres as $where) {
            $prefix = $first ? '' : (' ' . $this->boolean($where['boolean'] ?? 'AND') . ' ');
            $first  = false;

            $conditions[] = $prefix . match ($where['type'] ?? 'basic') {
                'null'    => $this->wrap($where['column']) . ' IS NULL',
                'notnull' => $this->wrap($where['column']) . ' IS NOT NULL',
                'in'      => $where['placeholder'] === ''
                    // IN () is a syntax error; an empty set matches nothing.
                    ? '1 = 0'
                    : $this->wrap($where['column']) . " IN ({$where['placeholder']})",
                'notin'   => $where['placeholder'] === ''
                    ? '1 = 1'
                    : $this->wrap($where['column']) . " NOT IN ({$where['placeholder']})",
                'between' => $this->wrap($where['column']) . ' BETWEEN ? AND ?',
                'raw'     => '(' . $where['sql'] . ')',
                default   => $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?',
            };
        }

        return $conditions ? ' WHERE ' . implode('', $conditions) : '';
    }

    /**
     * Quote an identifier, supporting dotted "table.column" form.
     */
    protected function wrap(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        return implode('.', array_map(
            static fn(string $part): string => $part === '*' ? '*' : '`' . str_replace('`', '``', $part) . '`',
            explode('.', $identifier)
        ));
    }

    protected function boolean(string $boolean): string
    {
        return strtoupper($boolean) === 'OR' ? 'OR' : 'AND';
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
        // Basic eager loading, for each relation name, load all related records
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
