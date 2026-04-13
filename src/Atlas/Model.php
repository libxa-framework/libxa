<?php

declare(strict_types=1);

namespace Libxa\Atlas;

use Libxa\Atlas\Connection\ConnectionPool;
use Libxa\Atlas\Relations\HasOne;
use Libxa\Atlas\Relations\HasMany;
use Libxa\Atlas\Relations\BelongsTo;
use Libxa\Atlas\Relations\BelongsToMany;
use Libxa\Atlas\Relations\MorphOne;
use Libxa\Atlas\Relations\MorphMany;
use Libxa\Atlas\Relations\MorphTo;
use Libxa\Foundation\Application;

/**
 * Atlas ORM — Active Record Model
 *
 * Base class for all LibxaFrame models.
 * Supports PHP 8.3 attributes for zero-boilerplate feature declaration.
 *
 * Usage:
 *   #[Table('products')]
 *   #[SoftDeletes]
 *   #[Auditable]
 *   class Product extends Model { ... }
 */
abstract class Model
{
    // ─────────────────────────────────────────────────────────────────
    //  Config (can be overridden in subclasses)
    // ─────────────────────────────────────────────────────────────────

    protected string  $table         = '';
    protected string  $primaryKey    = 'id';
    protected bool    $incrementing  = true;
    protected array   $fillable      = [];
    protected array   $guarded       = ['id'];
    protected array   $hidden        = [];
    protected array   $casts         = [];
    protected bool    $timestamps    = true;
    protected string  $createdAtCol  = 'created_at';
    protected string  $updatedAtCol  = 'updated_at';
    protected bool    $softDeletes   = false;
    protected string  $deletedAtCol  = 'deleted_at';
    protected string  $connection    = 'default';

    // ─────────────────────────────────────────────────────────────────
    //  Runtime state
    // ─────────────────────────────────────────────────────────────────

    protected array $attributes = [];
    protected array $original   = [];
    protected bool  $exists     = false;
    protected array $relations  = [];

    public function __construct(array $attributes = [])
    {
        $this->resolveTableFromAttribute();
        $this->resolveFeaturesFromAttributes();
        $this->fill($attributes);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Attribute discovery (PHP 8.3 attributes)
    // ─────────────────────────────────────────────────────────────────

    protected function resolveTableFromAttribute(): void
    {
        if ($this->table !== '') return;

        $reflector = new \ReflectionClass(static::class);

        foreach ($reflector->getAttributes(\Libxa\Atlas\Attributes\Table::class) as $attr) {
            $this->table = $attr->newInstance()->name;
            return;
        }

        // Default: snake_case plural of class name
        $this->table = $this->guessTable();
    }

    protected function resolveFeaturesFromAttributes(): void
    {
        $reflector = new \ReflectionClass(static::class);

        foreach ($reflector->getAttributes() as $attr) {
            $instance = $attr->newInstance();

            match (true) {
                $instance instanceof \Libxa\Atlas\Attributes\SoftDeletes  => $this->softDeletes = true,
                $instance instanceof \Libxa\Atlas\Attributes\TenantScoped => $this->applyTenantScope(),
                default => null,
            };
        }
    }

    protected function guessTable(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($class))) . 's';
    }

    protected function applyTenantScope(): void
    {
        // Tenant scoping applied at query builder level
    }

    // ─────────────────────────────────────────────────────────────────
    //  Fill
    // ─────────────────────────────────────────────────────────────────

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    protected function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded)) return false;
        if (empty($this->fillable))         return true;
        return in_array($key, $this->fillable);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Attribute access
    // ─────────────────────────────────────────────────────────────────

    public function setAttribute(string $key, mixed $value): void
    {
        // Run mutators (setXxxAttribute methods)
        $mutator = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';

        if (method_exists($this, $mutator)) {
            $this->$mutator($value);
            return;
        }

        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed
    {
        // Check relations first
        if (isset($this->relations[$key])) {
            return $this->relations[$key];
        }

        $value = $this->attributes[$key] ?? null;

        // Run accessors (getXxxAttribute methods)
        $accessor = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor($value);
        }

        // Apply casts
        if (isset($this->casts[$key])) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) return null;

        return match ($this->casts[$key]) {
            'int', 'integer'     => (int) $value,
            'float', 'double'    => (float) $value,
            'string'             => (string) $value,
            'bool', 'boolean'    => (bool) $value,
            'array'              => is_string($value) ? json_decode($value, true) : $value,
            'json'               => is_string($value) ? json_decode($value, true) : $value,
            'datetime'           => new \DateTimeImmutable($value),
            default              => $value,
        };
    }

    public function __get(string $key): mixed { return $this->getAttribute($key); }
    public function __set(string $key, mixed $value): void { $this->setAttribute($key, $value); }
    public function __isset(string $key): bool { return isset($this->attributes[$key]); }

    public function toArray(): array
    {
        $array = [];

        foreach (array_keys($this->attributes) as $key) {
            if (in_array($key, $this->hidden)) continue;
            $array[$key] = $this->getAttribute($key);
        }

        // Append loaded relations
        foreach ($this->relations as $key => $value) {
            $array[$key] = $value instanceof self
                ? $value->toArray()
                : (is_array($value) ? array_map(fn($v) => $v instanceof self ? $v->toArray() : $v, $value) : $value);
        }

        return $array;
    }

    public function toJson(): string { return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE); }

    // ─────────────────────────────────────────────────────────────────
    //  CRUD
    // ─────────────────────────────────────────────────────────────────

    public function save(): bool
    {
        $this->fireModelEvent('Saving');

        if ($this->exists) {
            $result = $this->performUpdate();
        } else {
            $result = $this->performInsert();
        }

        if ($result) {
            $this->fireModelEvent('Saved');
        }

        return $result;
    }

    protected function performInsert(): bool
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->attributes[$this->createdAtCol] = $now;
            $this->attributes[$this->updatedAtCol] = $now;
        }

        $this->fireModelEvent('Creating');

        $id = static::query()->insert($this->attributes);

        if ($id !== false) {
            $this->attributes[$this->primaryKey] = $id;
            $this->original = $this->attributes;
            $this->exists   = true;
            
            $this->fireModelEvent('Created');
            return true;
        }

        return false;
    }

    protected function performUpdate(): bool
    {
        if ($this->timestamps) {
            $this->attributes[$this->updatedAtCol] = date('Y-m-d H:i:s');
        }

        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $this->fireModelEvent('Updating');

        $result = static::query()
            ->where($this->primaryKey, $this->attributes[$this->primaryKey])
            ->updateRecord($dirty);

        if ($result) {
            $this->original = $this->attributes;
            $this->fireModelEvent('Updated');
        }

        return $result;
    }

    public function delete(): bool
    {
        if (! $this->exists) return false;

        $this->fireModelEvent('Deleting');

        if ($this->softDeletes) {
            $result = $this->performSoftDelete();
        } else {
            $result = static::query()
                ->where($this->primaryKey, $this->attributes[$this->primaryKey])
                ->deleteRecord();
        }

        if ($result) {
            $this->exists = false;
            $this->fireModelEvent('Deleted');
        }

        return $result;
    }

    protected function performSoftDelete(): bool
    {
        $this->attributes[$this->deletedAtCol] = date('Y-m-d H:i:s');
        return $this->performUpdate();
    }

    public function restore(): bool
    {
        if (! $this->softDeletes) return false;

        $this->attributes[$this->deletedAtCol] = null;
        return $this->performUpdate();
    }

    protected function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Static query interface
    // ─────────────────────────────────────────────────────────────────

    public static function query(): QueryBuilder
    {
        $instance = new static();
        return new QueryBuilder(
            model:      static::class,
            table:      $instance->getTable(),
            connection: $instance->connection,
            softDeletes: $instance->softDeletes,
            deletedAtCol: $instance->deletedAtCol,
        );
    }

    public static function all(): array           { return static::query()->get(); }
    public static function find(mixed $id): ?static { return static::query()->find($id); }
    public static function findOrFail(mixed $id): static
    {
        return static::query()->find($id) ?? throw new \RuntimeException("Model not found: $id");
    }

    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): QueryBuilder
    {
        return static::query()->where($column, $operatorOrValue, $value);
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function firstOrCreate(array $search, array $extra = []): static
    {
        $model = static::query()->where(array_key_first($search), reset($search))->first();
        return $model ?? static::create(array_merge($search, $extra));
    }

    /**
     * AI Query Bridge — ask in English.
     */
    public static function ask(string $question): mixed
    {
        return \Libxa\Atlas\AI\AiQueryBridge::ask($question, static::class);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Relations (Fluent Engine)
    // ─────────────────────────────────────────────────────────────────

    protected function hasMany(string $related, string $foreignKey = '', string $localKey = ''): HasMany
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: $this->guessTable() . '_id';
        $localKey   = $localKey   ?: $this->primaryKey;

        return new HasMany($instance->query(), $this, $foreignKey, $localKey);
    }

    protected function belongsTo(string $related, string $foreignKey = '', string $ownerKey = ''): BelongsTo
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($related))->getShortName()) . '_id';
        $ownerKey   = $ownerKey   ?: $instance->primaryKey;

        return new BelongsTo($instance->query(), $this, $foreignKey, $ownerKey);
    }

    protected function hasOne(string $related, string $foreignKey = '', string $localKey = ''): HasOne
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: $this->guessTable() . '_id';
        $localKey   = $localKey   ?: $this->primaryKey;

        return new HasOne($instance->query(), $this, $foreignKey, $localKey);
    }

    protected function belongsToMany(string $related, string $pivotTable = '', string $foreignPivotKey = '', string $relatedPivotKey = ''): BelongsToMany
    {
        $instance = new $related();

        // Guess pivot table name alphabetically
        if ($pivotTable === '') {
            $tables = [$this->getTable(), $instance->getTable()];
            sort($tables);
            $pivotTable = strtolower($tables[0] . '_' . $tables[1]);
        }

        $foreignPivotKey = $foreignPivotKey ?: $this->guessTable() . '_id';
        $relatedPivotKey = $relatedPivotKey ?: $instance->guessTable() . '_id';

        return new BelongsToMany($instance->query(), $this, $pivotTable, $foreignPivotKey, $relatedPivotKey);
    }

    protected function morphTo(?string $name = null, ?string $type = null, ?string $id = null): MorphTo
    {
        $name = $name ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        $type = $type ?: $name . '_type';
        $id   = $id   ?: $name . '_id';

        return new MorphTo($this->query(), $this, $type, $id);
    }

    protected function morphOne(string $related, string $name, ?string $type = null, ?string $id = null): MorphOne
    {
        $instance = new $related();
        $type     = $type ?: $name . '_type';
        $id       = $id   ?: $name . '_id';

        return new MorphOne($instance->query(), $this, $type, $id);
    }

    protected function morphMany(string $related, string $name, ?string $type = null, ?string $id = null): MorphMany
    {
        $instance = new $related();
        $type     = $type ?: $name . '_type';
        $id       = $id   ?: $name . '_id';

        return new MorphMany($instance->query(), $this, $type, $id);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────

    public function getTable(): string { return $this->table; }
    public function getPrimaryKey(): string { return $this->primaryKey; }
    public function getKey(): mixed { return $this->attributes[$this->primaryKey] ?? null; }

    public function setRelation(string $key, mixed $value): void
    {
        $this->relations[$key] = $value;
    }

    /**
     * Trigger a model lifecycle event if the method exists.
     */
    protected function fireModelEvent(string $event): void
    {
        $method = 'on' . ucfirst($event);
        if (method_exists($this, $method)) {
            $this->$method();
        }
    }

    // Bootstrap a model instance from a result row
    public static function newFromBuilder(array $row, bool $exists = true): static
    {
        $model             = new static();
        $model->attributes = $row;
        $model->original   = $row;
        $model->exists     = $exists;
        return $model;
    }
}
