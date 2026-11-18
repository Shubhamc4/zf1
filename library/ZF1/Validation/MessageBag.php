<?php

declare(strict_types=1);

namespace ZF1\Validation;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

/**
 * Modern Validation Error Message Bag.
 *
 * @implements ArrayAccess<string, array<string>>
 * @implements IteratorAggregate<string, array<string>>
 */
final class MessageBag implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable, Stringable
{
    /**
     * All registered error messages.
     *
     * @var array<string, array<string>>
     */
    protected array $messages = [];

    /**
     * Create a new message bag instance.
     *
     * @param  array<string, array<string>|string>  $messages
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $key => $value) {
            $this->messages[$key] = (array) $value;
        }
    }

    /**
     * Add a message to the bag.
     */
    public function add(string $key, string $message): static
    {
        $this->messages[$key][] = $message;

        return $this;
    }

    /**
     * Determine if messages exist for a given key.
     */
    public function has(string $key): bool
    {
        return ! empty($this->messages[$key]);
    }

    /**
     * Determine if messages are missing for a given key.
     */
    public function missing(string $key): bool
    {
        return ! $this->has($key);
    }

    /**
     * Determine if any messages exist in the bag, or for a given key.
     */
    public function any(?string $key = null): bool
    {
        return $key !== null ? $this->has($key) : $this->isNotEmpty();
    }

    /**
     * Conditionally return a CSS class if an error exists for a field.
     */
    public function class(string $key, string $class = 'is-invalid', string $fallback = ''): string
    {
        return $this->has($key) ? $class : $fallback;
    }

    /**
     * Get the first message for a given key, or the first message in the bag.
     */
    public function first(?string $key = null, ?string $format = null): ?string
    {
        if ($key === null) {
            foreach ($this->messages as $fieldMessages) {
                if (! empty($fieldMessages)) {
                    return $format !== null ? sprintf($format, $fieldMessages[0]) : $fieldMessages[0];
                }
            }

            return null;
        }

        $messages = $this->get($key);
        if (empty($messages)) {
            return null;
        }

        return $format !== null ? sprintf($format, $messages[0]) : $messages[0];
    }

    /**
     * Get all message keys present in the bag.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->messages);
    }

    /**
     * Return a new MessageBag containing messages only for the specified keys.
     *
     * @param  array<string>|string  ...$keys
     */
    public function only(array|string ...$keys): static
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;

        $filtered = [];
        foreach ($keys as $key) {
            if (isset($this->messages[$key])) {
                $filtered[$key] = $this->messages[$key];
            }
        }

        return new self($filtered);
    }

    /**
     * Return a new MessageBag excluding messages for the specified keys.
     *
     * @param  array<string>|string  ...$keys
     */
    public function except(array|string ...$keys): static
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;

        $filtered = $this->messages;
        foreach ($keys as $key) {
            unset($filtered[$key]);
        }

        return new self($filtered);
    }

    /**
     * Get a list of unique error messages across all fields.
     *
     * @return array<string>
     */
    public function unique(?string $format = null): array
    {
        $messages = array_values(array_unique($this->all()));

        if ($format !== null) {
            return array_map(fn (string $m) => sprintf($format, $m), $messages);
        }

        return $messages;
    }

    /**
     * Get all messages for a given key.
     *
     * @return array<string>
     */
    public function get(string $key): array
    {
        return $this->messages[$key] ?? [];
    }

    /**
     * Get all messages in a flattened single array.
     *
     * @return array<string>
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->messages as $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $all[] = $message;
            }
        }

        return $all;
    }

    /**
     * Get the raw associative array of messages.
     *
     * @return array<string, array<string>>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Check if the message bag is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    /**
     * Check if the message bag has messages.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Count the total number of messages across all keys.
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Get an iterator for the messages.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }

    /**
     * Determine if a key exists at an offset.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    /**
     * Get messages at a given offset.
     *
     * @return array<string>
     */
    public function offsetGet(mixed $offset): array
    {
        return $this->get((string) $offset);
    }

    /**
     * Set messages at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->messages[(string) $offset] = (array) $value;
    }

    /**
     * Unset messages at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->messages[(string) $offset]);
    }

    /**
     * Convert the message bag to an array.
     *
     * @return array<string, array<string>>
     */
    public function toArray(): array
    {
        return $this->messages;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, array<string>>
     */
    public function jsonSerialize(): array
    {
        return $this->messages;
    }

    /**
     * Convert message bag to string (first error or JSON).
     */
    public function __toString(): string
    {
        return $this->first() ?? '';
    }
}
