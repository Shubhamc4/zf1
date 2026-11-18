<?php

declare(strict_types=1);

namespace ZF1\Database;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Fluent collection container for database result sets.
 *
 * @template TKey of array-key
 * @template TValue of FluentRow|mixed
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
class FluentCollection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The items contained in the collection.
     *
     * @var array<TKey, TValue>
     */
    protected array $items = [];

    /**
     * Create a new fluent collection instance.
     *
     * @param  array<TKey, mixed>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_map(function ($item) {
            if ($item instanceof FluentRow) {
                return $item;
            }

            if (is_object($item) || is_array($item)) {
                return new FluentRow($item);
            }

            return $item;
        }, $items);
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Convert the collection and all its rows to plain arrays.
     *
     * @return array<TKey, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof FluentRow) {
                return $item->toArray();
            }

            if (is_object($item)) {
                return (array) $item;
            }

            return $item;
        }, $this->items);
    }

    /**
     * Convert the collection to its JSON representation.
     */
    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->toArray(), $options | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the first item from the collection, or the first item passing a truth test.
     *
     * @param  (callable(TValue, TKey): bool)|null  $callback
     * @return TValue|null
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($this->items)) {
                return $default;
            }

            return reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get the last item from the collection, or the last item passing a truth test.
     *
     * @param  (callable(TValue, TKey): bool)|null  $callback
     * @return TValue|null
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($this->items)) {
                return $default;
            }

            $copy = $this->items;

            return end($copy);
        }

        return $this->reverse()->first($callback, $default);
    }

    /**
     * Extract a value from an item using dot-notation.
     */
    protected function extractValue(mixed $target, string $key, mixed $default = null): mixed
    {
        if (function_exists('data_get')) {
            return data_get($target, $key, $default);
        }

        if ($target instanceof FluentRow) {
            return $target->get($key, $default);
        }

        return is_array($target) ? ($target[$key] ?? $default) : ($target->{$key} ?? $default);
    }

    /**
     * Get the values of a given column across all items.
     * Supports dot-notation for nested fields (e.g. 'profile.city').
     *
     * @return array<mixed>
     */
    public function pluck(string $value, ?string $key = null): array
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = $this->extractValue($item, $value);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = $this->extractValue($item, $key);
                $results[(string) $itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Key an associative collection by a field or using a callback.
     *
     * @param  (callable(TValue, TKey): (string|int))|string  $keyBy
     */
    public function keyBy(string|callable $keyBy): static
    {
        $results = [];

        foreach ($this->items as $key => $item) {
            if (is_callable($keyBy)) {
                $resolvedKey = $keyBy($item, $key);
            } else {
                $resolvedKey = $this->extractValue($item, $keyBy);
            }

            $results[(string) $resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * Filter items by the given key-value pair.
     */
    public function where(string $key, mixed $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $retrieved = $this->extractValue($item, $key);

            return match ($operator) {
                '=' => $retrieved == $value,
                '===' => $retrieved === $value,
                '!=' => $retrieved != $value,
                '!==' => $retrieved !== $value,
                '<' => $retrieved < $value,
                '<=' => $retrieved <= $value,
                '>' => $retrieved > $value,
                '>=' => $retrieved >= $value,
                default => false,
            };
        });
    }

    /**
     * Run a map over each of the items.
     *
     * @param  callable(TValue, TKey): mixed  $callback
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Run a filter over each of the items.
     *
     * @param  (callable(TValue, TKey): bool)|null  $callback
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Execute a callback over each item.
     *
     * @param  callable(TValue, TKey): mixed  $callback
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Reverse items order.
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Determine if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Reset the keys on the underlying items array.
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Concatenate values of a given column into a single string.
     */
    public function implode(string $value, string $glue = ','): string
    {
        return implode($glue, $this->pluck($value));
    }

    /**
     * Determine if an item exists in the collection.
     */
    public function contains(mixed $key, mixed $value = null): bool
    {
        if (is_callable($key)) {
            return $this->first($key) !== null;
        }

        if ($value === null) {
            return in_array($key, $this->items, true);
        }

        return $this->where($key, $value)->isNotEmpty();
    }

    /**
     * Calculate the sum of values for a given key or callback.
     *
     * @param  (callable(TValue, TKey=): (int|float))|string  $key
     */
    public function sum(string|callable $key): int|float
    {
        $total = 0;
        $callback = is_callable($key) ? $key : fn ($item, $k = null) => $this->extractValue($item, $key, 0);

        foreach ($this->items as $k => $item) {
            $total += $callback($item, $k);
        }

        return $total;
    }

    /**
     * Calculate the average of values for a given key.
     */
    public function avg(string|callable $key): int|float|null
    {
        $count = $this->count();
        if ($count === 0) {
            return null;
        }

        return $this->sum($key) / $count;
    }

    /**
     * Get the minimum value of a given key or callback.
     *
     * @param  (callable(TValue, TKey=): mixed)|string  $key
     */
    public function min(string|callable $key): mixed
    {
        $callback = is_callable($key) ? $key : fn ($item, $k = null) => $this->extractValue($item, $key);
        $min = null;

        foreach ($this->items as $k => $item) {
            $val = $callback($item, $k);
            if ($val !== null && ($min === null || $val < $min)) {
                $min = $val;
            }
        }

        return $min;
    }

    /**
     * Get the maximum value of a given key or callback.
     *
     * @param  (callable(TValue, TKey=): mixed)|string  $key
     */
    public function max(string|callable $key): mixed
    {
        $callback = is_callable($key) ? $key : fn ($item, $k = null) => $this->extractValue($item, $key);
        $max = null;

        foreach ($this->items as $k => $item) {
            $val = $callback($item, $k);
            if ($val !== null && ($max === null || $val > $max)) {
                $max = $val;
            }
        }

        return $max;
    }

    /**
     * Group items by a given key or callback.
     *
     * @param  (callable(TValue, TKey=): (string|int))|string  $groupBy
     */
    public function groupBy(string|callable $groupBy): static
    {
        $groups = [];
        $callback = is_callable($groupBy) ? $groupBy : fn ($item, $k = null) => $this->extractValue($item, $groupBy);

        foreach ($this->items as $k => $item) {
            $key = (string) $callback($item, $k);
            $groups[$key][] = $item;
        }

        foreach ($groups as $key => $items) {
            $groups[$key] = new static($items);
        }

        return new static($groups);
    }

    /**
     * Sort the collection items using a given callback or column key.
     *
     * @param  (callable(TValue, TKey=): mixed)|string  $callback
     */
    public function sortBy(string|callable $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $results = [];
        $cb = is_callable($callback) ? $callback : fn ($item, $k = null) => $this->extractValue($item, $callback);

        foreach ($this->items as $key => $value) {
            $results[$key] = $cb($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection in descending order using a given callback or column key.
     */
    public function sortByDesc(string|callable $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Dump the collection items and continue chaining.
     */
    public function dump(mixed ...$args): static
    {
        if (class_exists(\ZF1\Support\Dumper::class)) {
            \ZF1\Support\Dumper::dump($this, ...$args);
        } elseif (function_exists('dump')) {
            dump($this, ...$args);
        } else {
            var_dump($this);
        }

        return $this;
    }

    /**
     * Dump the collection items and terminate execution ("dump and die").
     */
    public function dd(mixed ...$args): never
    {
        if (class_exists(\ZF1\Support\Dumper::class)) {
            \ZF1\Support\Dumper::dd($this, ...$args);
        }

        if (function_exists('dd')) {
            dd($this, ...$args);
        }

        var_dump($this);
        exit(1);
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Determine if an item exists at an offset.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Get an item at a given offset.
     *
     * @return TValue|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Set the item at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Get an iterator for the items.
     *
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * String representation of the collection (JSON).
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
