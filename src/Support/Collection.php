<?php

declare(strict_types=1);

namespace Libxa\Support;

/**
 * LibxaFrame Collection
 *
 * Fluent, immutable-style wrapper around arrays.
 * Inspired by Laravel Collections but lightweight.
 */
class Collection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    public function __construct(protected array $items = []) {}

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Transformations
    // ─────────────────────────────────────────────────────────────────

    public function map(\Closure $fn): static
    {
        return new static(array_map($fn, $this->items));
    }

    public function filter(?\Closure $fn = null): static
    {
        return new static(array_values(
            $fn ? array_filter($this->items, $fn) : array_filter($this->items)
        ));
    }

    public function reject(\Closure $fn): static
    {
        return $this->filter(fn($item) => ! $fn($item));
    }

    public function each(\Closure $fn): static
    {
        foreach ($this->items as $key => $item) {
            if ($fn($item, $key) === false) break;
        }
        return $this;
    }

    public function flatMap(\Closure $fn): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $mapped = $fn($item);
            foreach ((array) $mapped as $v) {
                $result[] = $v;
            }
        }
        return new static($result);
    }

    public function reduce(\Closure $fn, mixed $carry = null): mixed
    {
        return array_reduce($this->items, $fn, $carry);
    }

    public function groupBy(string|\Closure $key): static
    {
        $groups = [];
        foreach ($this->items as $item) {
            $k = is_string($key)
                ? (is_array($item) ? ($item[$key] ?? '') : $item->$key)
                : $key($item);
            $groups[$k][] = $item;
        }
        return new static(array_map(fn($g) => new static($g), $groups));
    }

    public function keyBy(string|\Closure $key): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $k = is_string($key)
                ? (is_array($item) ? ($item[$key] ?? '') : $item->$key)
                : $key($item);
            $result[$k] = $item;
        }
        return new static($result);
    }

    public function sortBy(string|\Closure $key, bool $descending = false): static
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $descending) {
            $aVal = is_string($key) ? (is_array($a) ? $a[$key] : $a->$key) : $key($a);
            $bVal = is_string($key) ? (is_array($b) ? $b[$key] : $b->$key) : $key($b);
            $cmp  = $aVal <=> $bVal;
            return $descending ? -$cmp : $cmp;
        });
        return new static($items);
    }

    public function sortByDesc(string|\Closure $key): static
    {
        return $this->sortBy($key, descending: true);
    }

    public function unique(string|\Closure|null $key = null): static
    {
        if ($key === null) {
            return new static(array_values(array_unique($this->items)));
        }

        $seen   = [];
        $result = [];

        foreach ($this->items as $item) {
            $k = is_string($key)
                ? (is_array($item) ? ($item[$key] ?? '') : $item->$key)
                : $key($item);

            if (! in_array($k, $seen)) {
                $seen[]   = $k;
                $result[] = $item;
            }
        }

        return new static($result);
    }

    public function flatten(int $depth = INF): static
    {
        $result = [];
        array_walk_recursive($this->items, function ($item) use (&$result) {
            $result[] = $item;
        });
        return new static($result);
    }

    public function pluck(string $key): static
    {
        return $this->map(fn($item) => is_array($item) ? ($item[$key] ?? null) : $item->$key);
    }

    public function only(array $keys): static
    {
        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    public function except(array $keys): static
    {
        return new static(array_diff_key($this->items, array_flip($keys)));
    }

    public function merge(array|Collection $items): static
    {
        return new static(array_merge($this->items, is_array($items) ? $items : $items->all()));
    }

    public function take(int $n): static
    {
        return new static(array_slice($this->items, 0, $n));
    }

    public function skip(int $n): static
    {
        return new static(array_slice($this->items, $n));
    }

    public function reverse(): static
    {
        return new static(array_values(array_reverse($this->items)));
    }

    public function chunk(int $size): static
    {
        return new static(array_map(
            fn($chunk) => new static($chunk),
            array_chunk($this->items, $size)
        ));
    }

    public function zip(array $items): static
    {
        return new static(array_map(null, $this->items, $items));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Search
    // ─────────────────────────────────────────────────────────────────

    public function first(?\Closure $fn = null): mixed
    {
        if ($fn === null) return $this->items[0] ?? null;
        foreach ($this->items as $item) {
            if ($fn($item)) return $item;
        }
        return null;
    }

    public function last(?\Closure $fn = null): mixed
    {
        if ($fn === null) return end($this->items) ?: null;
        return $this->filter($fn)->last();
    }

    public function find(mixed $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function contains(mixed $valueOrFn): bool
    {
        if ($valueOrFn instanceof \Closure) {
            foreach ($this->items as $item) {
                if ($valueOrFn($item)) return true;
            }
            return false;
        }
        return in_array($valueOrFn, $this->items);
    }

    public function every(\Closure $fn): bool
    {
        foreach ($this->items as $item) {
            if (! $fn($item)) return false;
        }
        return true;
    }

    public function some(\Closure $fn): bool { return $this->contains($fn); }

    // ─────────────────────────────────────────────────────────────────
    //  Aggregates
    // ─────────────────────────────────────────────────────────────────

    public function sum(string|\Closure|null $key = null): float|int
    {
        return $this->map(fn($item) => $key
            ? (is_string($key) ? (is_array($item) ? $item[$key] : $item->$key) : $key($item))
            : $item
        )->reduce(fn($c, $v) => $c + $v, 0);
    }

    public function avg(string|\Closure|null $key = null): float
    {
        $count = $this->count();
        return $count ? $this->sum($key) / $count : 0;
    }

    public function max(string|\Closure|null $key = null): mixed
    {
        return $this->map(fn($item) => $key
            ? (is_string($key) ? (is_array($item) ? $item[$key] : $item->$key) : $key($item))
            : $item
        )->reduce(fn($c, $v) => $c === null || $v > $c ? $v : $c, null);
    }

    public function min(string|\Closure|null $key = null): mixed
    {
        return $this->map(fn($item) => $key
            ? (is_string($key) ? (is_array($item) ? $item[$key] : $item->$key) : $key($item))
            : $item
        )->reduce(fn($c, $v) => $c === null || $v < $c ? $v : $c, null);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Output
    // ─────────────────────────────────────────────────────────────────

    public function all(): array     { return $this->items; }
    public function values(): static { return new static(array_values($this->items)); }
    public function keys(): static   { return new static(array_keys($this->items)); }
    public function count(): int     { return count($this->items); }
    public function isEmpty(): bool  { return empty($this->items); }
    public function isNotEmpty(): bool { return ! $this->isEmpty(); }
    public function toArray(): array { return $this->items; }
    public function toJson(): string { return json_encode($this->items, JSON_UNESCAPED_UNICODE); }
    public function jsonSerialize(): mixed { return $this->items; }
    public function getIterator(): \ArrayIterator { return new \ArrayIterator($this->items); }

    public function __toString(): string { return $this->toJson(); }
}
