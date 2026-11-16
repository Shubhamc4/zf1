<?php

declare(strict_types=1);

namespace ZF1\Support;

use AllowDynamicProperties;
use ArrayAccess;
use JsonSerializable;
use Stringable;

/**
 * Fluent Optional wrapper for graceful null safety, property access, and method calls.
 *
 * @implements ArrayAccess<string|int, mixed>
 */
#[AllowDynamicProperties]
final class Optional implements ArrayAccess, JsonSerializable, Stringable
{
    /**
     * The underlying wrapped value.
     */
    protected mixed $value;

    /**
     * Create a new optional instance.
     */
    public function __construct(mixed $value = null)
    {
        $this->value = $value;
    }

    /**
     * Dynamically access a property on the underlying object or array.
     */
    public function __get(string $key): mixed
    {
        if (is_object($this->value)) {
            return $this->value->{$key} ?? null;
        }

        if (is_array($this->value)) {
            return $this->value[$key] ?? null;
        }

        return null;
    }

    /**
     * Dynamically set a property on the underlying object or array.
     */
    public function __set(string $key, mixed $value): void
    {
        if (is_object($this->value)) {
            $this->value->{$key} = $value;
        } elseif (is_array($this->value)) {
            $this->value[$key] = $value;
        }
    }

    /**
     * Dynamically check a property's existence on the underlying object or array.
     */
    public function __isset(string $key): bool
    {
        if (is_object($this->value)) {
            return isset($this->value->{$key});
        }

        if (is_array($this->value)) {
            return isset($this->value[$key]);
        }

        return false;
    }

    /**
     * Dynamically unset a property on the underlying object or array.
     */
    public function __unset(string $key): void
    {
        if (is_object($this->value)) {
            unset($this->value->{$key});
        } elseif (is_array($this->value)) {
            unset($this->value[$key]);
        }
    }

    /**
     * Dynamically call a method on the underlying object.
     *
     * @param  array<mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (is_object($this->value)) {
            return $this->value->{$method}(...$parameters);
        }

        return null;
    }

    /**
     * Determine if an item exists at an offset.
     */
    public function offsetExists(mixed $offset): bool
    {
        if (is_array($this->value)) {
            return isset($this->value[$offset]);
        }

        if ($this->value instanceof ArrayAccess) {
            return $this->value->offsetExists($offset);
        }

        return false;
    }

    /**
     * Get an item at a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (is_array($this->value)) {
            return $this->value[$offset] ?? null;
        }

        if ($this->value instanceof ArrayAccess) {
            return $this->value->offsetGet($offset);
        }

        return null;
    }

    /**
     * Set the item at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_array($this->value)) {
            if ($offset === null) {
                $this->value[] = $value;
            } else {
                $this->value[$offset] = $value;
            }
        } elseif ($this->value instanceof ArrayAccess) {
            $this->value->offsetSet($offset, $value);
        }
    }

    /**
     * Unset the item at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (is_array($this->value)) {
            unset($this->value[$offset]);
        } elseif ($this->value instanceof ArrayAccess) {
            $this->value->offsetUnset($offset);
        }
    }

    /**
     * Get the underlying value.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Specify data which should be serialized to JSON.
     */
    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    /**
     * Convert the optional instance to string.
     */
    public function __toString(): string
    {
        return (string) ($this->value ?? '');
    }
}
