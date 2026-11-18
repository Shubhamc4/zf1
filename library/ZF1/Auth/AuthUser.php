<?php

declare(strict_types=1);

namespace ZF1\Auth;

use ArrayAccess;
use JsonSerializable;
use Throwable;

/**
 * Fluent Authenticated User representation.
 *
 * @implements ArrayAccess<string, mixed>
 */
class AuthUser implements ArrayAccess, JsonSerializable
{
    /**
     * Database table associated with the user model.
     */
    protected string $table = 'users';

    /**
     * Primary key column name.
     */
    protected string $primaryKey = 'id';

    /**
     * Password column name.
     */
    protected string $authPasswordName = 'password';

    /**
     * Password hashing algorithm ('md5', 'bcrypt', etc.).
     */
    protected string $authPasswordAlgorithm = 'md5';

    /**
     * Attribute aliases mapping alias => real_column_name.
     *
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * The raw user attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Create a new authenticated user instance.
     *
     * @param  array<string, mixed>|object  $attributes
     */
    public function __construct(array|object $attributes = [])
    {
        if (is_object($attributes)) {
            $attributes = get_object_vars($attributes);
        }

        $this->attributes = $attributes;
    }

    /**
     * Get the database table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key column name.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the primary identifier for the user.
     */
    public function getAuthIdentifier(): int|string|null
    {
        return $this->attributes[$this->getAuthIdentifierName()]
            ?? $this->attributes['id']
            ?? null;
    }

    /**
     * Get the password column name.
     */
    public function getAuthPasswordName(): string
    {
        return $this->authPasswordName;
    }

    /**
     * Get the hashed password for the user.
     */
    public function getAuthPassword(): ?string
    {
        return $this->attributes[$this->getAuthPasswordName()]
            ?? $this->attributes['password']
            ?? null;
    }

    /**
     * Get the password hashing algorithm.
     */
    public function getAuthPasswordAlgorithm(): string
    {
        return $this->authPasswordAlgorithm;
    }

    /**
     * Verify a plain text password against the hashed password.
     */
    public function verifyPassword(string $password): bool
    {
        $hash = $this->getAuthPassword();
        if ($hash === null || $hash === '') {
            return false;
        }

        return match (strtolower($this->getAuthPasswordAlgorithm())) {
            'bcrypt' => password_verify($password, $hash),
            'md5' => hash_equals($hash, md5($password)),
            default => hash_equals($hash, hash($this->getAuthPasswordAlgorithm(), $password)),
        };
    }

    /**
     * Determine if this user is authorized to impersonate other users.
     */
    public function canImpersonate(): bool
    {
        return false;
    }

    /**
     * Determine if this user can be impersonated by another user.
     */
    public function canBeImpersonated(): bool
    {
        return true;
    }

    /**
     * Determine if this user is identical to another user.
     */
    public function is(?self $user): bool
    {
        if ($user === null) {
            return false;
        }

        $id1 = $this->getAuthIdentifier();
        $id2 = $user->getAuthIdentifier();

        return $id1 !== null && $id2 !== null && (string) $id1 === (string) $id2;
    }

    /**
     * Determine if this user is not identical to another user.
     */
    public function isNot(?self $user): bool
    {
        return ! $this->is($user);
    }

    /**
     * Update the timestamp for the user model in the database.
     */
    public function touch(?string $column = null): bool
    {
        $id = $this->getAuthIdentifier();
        if ($id === null || ! class_exists(\ZF1\Database\DB::class)) {
            return false;
        }

        $col = $column ?? (isset($this->attributes['updated_on']) ? 'updated_on' : 'updated_at');
        $now = date('Y-m-d H:i:s');

        $this->set($col, $now);

        try {
            \ZF1\Database\DB::table($this->getTable())
                ->where($this->getAuthIdentifierName(), $id)
                ->update([$col => $now]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get attribute aliases.
     *
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Register an attribute alias.
     */
    public function addAlias(string $alias, string $target): static
    {
        $this->aliases[$alias] = $target;

        return $this;
    }

    /**
     * Set a custom attribute value.
     */
    public function set(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get an attribute from the user (supports attributes, aliases, and custom accessors).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $val = $this->__get($key);

        return $val !== null ? $val : $default;
    }

    /**
     * Determine if an attribute exists and is not null.
     */
    public function has(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Get a subset of the user attributes.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->attributes, array_flip($keys));
    }

    /**
     * Get all attributes except specified keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->attributes, array_flip($keys));
    }

    /**
     * Get all attributes as an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Magic getter for attributes, aliases, and custom accessors.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'id') {
            return $this->getAuthIdentifier();
        }

        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $accessor = 'get'.$studly.'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->{$accessor}();
        }

        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        return $this->attributes[$name] ?? null;
    }

    /**
     * Magic setter for attributes.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Magic isset for attributes, aliases, and custom accessors.
     */
    public function __isset(string $name): bool
    {
        if ($name === 'id') {
            return $this->getAuthIdentifier() !== null;
        }

        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $accessor = 'get'.$studly.'Attribute';
        if (method_exists($this, $accessor)) {
            return true;
        }

        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        return isset($this->attributes[$name]);
    }

    /**
     * Magic unset for attributes.
     */
    public function __unset(string $name): void
    {
        unset($this->attributes[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[(string) $offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[(string) $offset]);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
