<?php

declare(strict_types=1);

namespace ZF1\Database;

use AllowDynamicProperties;
use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use stdClass;
use Traversable;

/**
 * Fluent database record representation extending stdClass.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
#[AllowDynamicProperties]
class FluentRow extends stdClass implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Create a new fluent database row instance.
     *
     * @param  object|array<string, mixed>  $attributes
     */
    public function __construct(object|array $attributes = [])
    {
        foreach ((array) $attributes as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Safe property accessor returning null when undefined to prevent warnings.
     */
    public function __get(string $name): mixed
    {
        return null;
    }

    /**
     * Safe property isset check.
     */
    public function __isset(string $name): bool
    {
        return isset($this->{$name});
    }

    /**
     * Get an attribute from the record with an optional fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->{$key} ?? $default;
    }

    /**
     * Determine if an attribute exists and is not null.
     */
    public function has(string $key): bool
    {
        return isset($this->{$key});
    }

    /**
     * Get all attribute keys.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->toArray());
    }

    /**
     * Get all attribute values.
     *
     * @return array<int, mixed>
     */
    public function values(): array
    {
        return array_values($this->toArray());
    }

    /**
     * Determine if this record is identical to another record.
     */
    public function is(mixed $row): bool
    {
        if (! is_object($row)) {
            return false;
        }

        $id1 = $this->id ?? null;
        $id2 = $row->id ?? null;

        if ($id1 !== null && $id2 !== null) {
            return (string) $id1 === (string) $id2;
        }

        return $this == $row;
    }

    /**
     * Determine if this record is not identical to another record.
     */
    public function isNot(mixed $row): bool
    {
        return ! $this->is($row);
    }

    /**
     * Get a subset of the record attributes.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    /**
     * Get all attributes except the specified keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    /**
     * Convert the record to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return (array) $this;
    }

    /**
     * Convert the record to JSON string.
     */
    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->toArray(), $options | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Dump the record attributes and continue chaining.
     */
    public function dump(mixed ...$keys): static
    {
        $payload = empty($keys) ? $this : $this->only($keys);

        if (class_exists(\ZF1\Support\Dumper::class)) {
            \ZF1\Support\Dumper::dump($payload);
        } elseif (function_exists('dump')) {
            dump($payload);
        } else {
            var_dump($payload);
        }

        return $this;
    }

    /**
     * Dump the record attributes and terminate execution ("dump and die").
     */
    public function dd(mixed ...$keys): never
    {
        $payload = empty($keys) ? $this : $this->only($keys);

        if (class_exists(\ZF1\Support\Dumper::class)) {
            \ZF1\Support\Dumper::dd($payload);
        }

        if (function_exists('dd')) {
            dd($payload);
        }

        var_dump($payload);
        exit(1);
    }

    /**
     * Determine if an offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->{(string) $offset});
    }

    /**
     * Get the value at a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->{(string) $offset} ?? null;
    }

    /**
     * Set the value at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->{(string) $offset} = $value;
    }

    /**
     * Unset the value at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->{(string) $offset});
    }

    /**
     * Count the total number of attributes on the record.
     */
    public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * Get an iterator for the record attributes.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * String representation of the record (JSON).
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
