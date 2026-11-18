<?php

declare(strict_types=1);

namespace ZF1\Validation;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Validated Input container with fluent data extraction methods.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
final class ValidatedInput implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Validated input data.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new validated input instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get a subset of the validated data.
     *
     * @param  array<string>|string  ...$keys
     * @return array<string, mixed>
     */
    public function only(array|string ...$keys): array
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;

        return array_only($this->data, $keys);
    }

    /**
     * Get all validated data except the specified keys.
     *
     * @param  array<string>|string  ...$keys
     * @return array<string, mixed>
     */
    public function except(array|string ...$keys): array
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;

        return array_except($this->data, $keys);
    }

    /**
     * Get all of the validated input.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get a specific value or fallback default using dot-notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Determine if a key exists in the validated data.
     */
    public function has(string $key): bool
    {
        return array_has($this->data, $key);
    }

    /**
     * Determine if a key is missing from the validated data.
     */
    public function missing(string $key): bool
    {
        return ! $this->has($key);
    }

    /**
     * Merge additional data into the validated input.
     *
     * @param  array<string, mixed>  $items
     */
    public function merge(array $items): static
    {
        $this->data = array_merge($this->data, $items);

        return $this;
    }

    /**
     * Dump the validated input and continue.
     */
    public function dump(mixed ...$keys): static
    {
        $data = empty($keys) ? $this->data : $this->only($keys);
        if (class_exists(\ZF1\Support\Dumper::class)) {
            \ZF1\Support\Dumper::dump($data);
        } elseif (function_exists('dump')) {
            dump($data);
        } else {
            var_dump($data);
        }

        return $this;
    }

    /**
     * Dump the validated input and terminate execution.
     */
    public function dd(mixed ...$keys): never
    {
        $data = empty($keys) ? $this->data : $this->only($keys);
        if (class_exists(\ZF1\Support\Dumper::class)) {
            \ZF1\Support\Dumper::dd($data);
        } elseif (function_exists('dd')) {
            dd($data);
        }

        var_dump($data);
        exit(1);
    }

    /**
     * Determine if a key exists at an offset.
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->data);
    }

    /**
     * Get data at a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[(string) $offset] ?? null;
    }

    /**
     * Set data at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[(string) $offset] = $value;
    }

    /**
     * Unset data at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[(string) $offset]);
    }

    /**
     * Count the total number of validated items.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Get an iterator for the validated items.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Convert the validated input to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
