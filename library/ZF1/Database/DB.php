<?php

declare(strict_types=1);

namespace ZF1\Database;

use Closure;
use PDO;
use RuntimeException;
use Throwable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;
use Zend_Registry;
use ZF1\Foundation\Kernel;

/**
 * Modern Database Manager and Query Facade for Zend Framework 1.
 *
 * @mixin Zend_Db_Adapter_Abstract
 */
final class DB
{
    /**
     * Named database connections.
     *
     * @var array<string, Zend_Db_Adapter_Abstract>
     */
    private static array $connections = [];

    /**
     * Resolve the active Zend_Db adapter.
     */
    public static function connection(?string $name = null): Zend_Db_Adapter_Abstract
    {
        $key = $name ?: 'default';

        if (isset(self::$connections[$key])) {
            return self::$connections[$key];
        }

        $adapter = self::resolveAdapter($name);
        self::$connections[$key] = $adapter;

        return $adapter;
    }

    /**
     * Execute a Closure within a database transaction.
     * Automatically commits on success, or rolls back and rethrows on failure.
     *
     * @template T
     *
     * @param  callable(Zend_Db_Adapter_Abstract): T  $callback
     * @param  int  $attempts  Number of retry attempts if deadlock occurs
     * @return T
     *
     * @throws Throwable
     */
    public static function transaction(callable $callback, int $attempts = 1, ?string $connection = null): mixed
    {
        $db = self::connection($connection);

        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $db->beginTransaction();

            try {
                $result = $callback($db);
                $db->commit();

                return $result;
            } catch (Throwable $e) {
                try {
                    $db->rollBack();
                } catch (Throwable) {
                    // Ignore rollback failures caused by connection loss
                }

                if ($currentAttempt < $attempts && self::isDeadlockException($e)) {
                    usleep(100000 * $currentAttempt); // 100ms backoff

                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException("Transaction failed after {$attempts} attempts.");
    }

    /**
     * Begin a new database transaction.
     */
    public static function beginTransaction(?string $connection = null): void
    {
        self::connection($connection)->beginTransaction();
    }

    /**
     * Commit the active database transaction.
     */
    public static function commit(?string $connection = null): void
    {
        self::connection($connection)->commit();
    }

    /**
     * Rollback the active database transaction.
     */
    public static function rollBack(?string $connection = null): void
    {
        self::connection($connection)->rollBack();
    }

    /**
     * Start a select query builder for a table.
     */
    public static function table(string $table, ?string $connection = null): Zend_Db_Select
    {
        return self::connection($connection)->select()->from($table);
    }

    /**
     * Select rows from a table.
     *
     * @param  array<string>|string  $columns
     * @param  string|array<string, mixed>|null  $where
     * @return array<array<string, mixed>>
     */
    public static function select(
        string $table,
        array|string $columns = '*',
        string|array|null $where = null,
        ?string $order = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $connection = null
    ): array {
        $select = self::table($table, $connection);

        if ($columns !== '*') {
            $select->columns($columns);
        }

        if ($where !== null) {
            if (is_array($where)) {
                foreach ($where as $cond => $val) {
                    if (is_int($cond)) {
                        $select->where($val);
                    } else {
                        $select->where($cond, $val);
                    }
                }
            } else {
                $select->where($where);
            }
        }

        if ($order !== null) {
            $select->order($order);
        }

        if ($limit !== null) {
            $select->limit($limit, $offset);
        }

        return (array) self::connection($connection)->fetchAll($select);
    }

    /**
     * Insert a row into a table and return the last inserted ID.
     *
     * @param  array<string, mixed>  $data
     */
    public static function insert(string $table, array $data, ?string $connection = null): int|string
    {
        $db = self::connection($connection);
        $db->insert($table, $data);

        return $db->lastInsertId();
    }

    /**
     * Update rows in a table and return the number of affected rows.
     *
     * @param  array<string, mixed>  $data
     * @param  string|array<string>  $where
     */
    public static function update(string $table, array $data, string|array $where, ?string $connection = null): int
    {
        return (int) self::connection($connection)->update($table, $data, $where);
    }

    /**
     * Delete rows from a table and return the number of affected rows.
     *
     * @param  string|array<string>  $where
     */
    public static function delete(string $table, string|array $where, ?string $connection = null): int
    {
        return (int) self::connection($connection)->delete($table, $where);
    }

    /**
     * Execute a raw SQL statement with bindings.
     *
     * @param  array<int|string, mixed>  $bind
     */
    public static function statement(string $sql, array $bind = [], ?string $connection = null): Zend_Db_Statement_Interface
    {
        return self::connection($connection)->query($sql, $bind);
    }

    /**
     * Run a raw SQL query and return all results as an array.
     *
     * @param  array<int|string, mixed>  $bind
     * @return array<array<string, mixed>>
     */
    public static function selectRaw(string $sql, array $bind = [], ?string $connection = null): array
    {
        return (array) self::connection($connection)->fetchAll($sql, $bind);
    }

    /**
     * Run a raw SQL query and return the first result row, or null.
     *
     * @param  array<int|string, mixed>  $bind
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $bind = [], ?string $connection = null): ?array
    {
        $row = self::connection($connection)->fetchRow($sql, $bind);

        return is_array($row) ? $row : null;
    }

    /**
     * Run a raw SQL query and return a single scalar value (e.g. COUNT).
     *
     * @param  array<int|string, mixed>  $bind
     */
    public static function scalar(string $sql, array $bind = [], ?string $connection = null): mixed
    {
        $val = self::connection($connection)->fetchOne($sql, $bind);

        return $val !== false ? $val : null;
    }

    /**
     * Create a raw SQL expression.
     */
    public static function raw(string $value): Zend_Db_Expr
    {
        return new Zend_Db_Expr($value);
    }

    /**
     * Get underlying PDO instance if available.
     */
    public static function getPdo(?string $connection = null): ?PDO
    {
        $conn = self::connection($connection)->getConnection();

        return $conn instanceof PDO ? $conn : null;
    }

    /**
     * Resolve the active database adapter from available registries.
     */
    private static function resolveAdapter(?string $name = null): Zend_Db_Adapter_Abstract
    {
        if ($name !== null && Zend_Registry::isRegistered("db_{$name}")) {
            $adapter = Zend_Registry::get("db_{$name}");
            if ($adapter instanceof Zend_Db_Adapter_Abstract) {
                return $adapter;
            }
        }

        $defaultAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($defaultAdapter instanceof Zend_Db_Adapter_Abstract) {
            return $defaultAdapter;
        }

        if (Zend_Registry::isRegistered('db')) {
            $regAdapter = Zend_Registry::get('db');
            if ($regAdapter instanceof Zend_Db_Adapter_Abstract) {
                return $regAdapter;
            }
        }

        $app = Kernel::getApplication();
        if ($app !== null) {
            $boot = $app->getBootstrap();
            if ($boot !== null && $boot->hasResource('db')) {
                $bootAdapter = $boot->getResource('db');
                if ($bootAdapter instanceof Zend_Db_Adapter_Abstract) {
                    return $bootAdapter;
                }
            }
        }

        throw new RuntimeException('No active Zend_Db_Adapter_Abstract found in Zend_Db_Table_Abstract or Zend_Registry.');
    }

    /**
     * Check if exception was caused by a database deadlock or lock wait timeout.
     */
    private static function isDeadlockException(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'deadlock')
            || str_contains($msg, 'lock wait timeout')
            || str_contains($msg, '1213')
            || str_contains($msg, '1205');
    }

    /**
     * Reset cached connection instances.
     */
    public static function reset(): void
    {
        self::$connections = [];
    }

    /**
     * Dynamically forward static method calls to the default database connection.
     *
     * @param  array<mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::connection()->{$method}(...$arguments);
    }
}
