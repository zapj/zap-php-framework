<?php

use zap\db\ZPDO;

class DB
{
    /** @var ZPDO[] Cached connections */
    protected static $connections = [];

    /** @var ZPDO Cached connection used within transactions */
    protected static $transactionConnection = null;

    /**
     * Get a database connection.
     *
     * @param string|null $name Connection name (null = default)
     * @return ZPDO
     */
    public static function connection($name = null): ZPDO
    {
        $name = $name ?? 'default';

        // During a transaction, return the locked connection
        if (self::$transactionConnection !== null) {
            return self::$transactionConnection;
        }

        if (!isset(self::$connections[$name])) {
            $dbConfig = app()->config['database']['connections'][$name]
                ?? app()->config['database']['connections']['default']
                ?? app()->config['database'][$name]
                ?? app()->config['database']['default']
                ?? [];
            self::$connections[$name] = new ZPDO($dbConfig);
        }

        return self::$connections[$name];
    }

    /**
     * Get the default connection (magic alias).
     */
    public static function getConnection($name = null): ZPDO
    {
        return self::connection($name);
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): void
    {
        $conn = self::connection();
        $conn->beginTransaction();
        self::$transactionConnection = $conn;
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): void
    {
        if (self::$transactionConnection !== null) {
            self::$transactionConnection->commit();
            self::$transactionConnection = null;
        }
    }

    /**
     * Rollback the current transaction.
     */
    public static function rollBack(): void
    {
        if (self::$transactionConnection !== null) {
            self::$transactionConnection->rollBack();
            self::$transactionConnection = null;
        }
    }

    /**
     * Execute a callback within a transaction.
     */
    public static function transaction(callable $callback, ...$args)
    {
        try {
            self::beginTransaction();
            $result = $callback(self::connection(), ...$args);
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    /**
     * Get a query builder instance for a table.
     */
    public static function table(string $table, string $alias = null): \zap\db\Query
    {
        return self::connection()->table($table, $alias);
    }

    /**
     * Enable query logging (on the last created connection via this facade).
     */
    public static function enableQueryLog(): void
    {
        self::connection()->enableQueryLog();
    }

    /**
     * Disable query logging.
     */
    public static function disableQueryLog(): void
    {
        self::connection()->disableQueryLog();
    }

    /**
     * Get the query log.
     */
    public static function getQueryLog(): array
    {
        return self::connection()->getQueryLog();
    }

    /**
     * Flush the query log.
     */
    public static function flushQueryLog(): void
    {
        self::connection()->flushQueryLog();
    }

    /**
     * Run a raw SQL statement.
     */
    public static function statement(string $query, array $params = []): \PDOStatement|false
    {
        return self::connection()->statement($query, $params);
    }

    /**
     * Run a SELECT query and return all results.
     */
    public static function select(string $query, array $params = []): array
    {
        return self::connection()->select($query, $params);
    }

    /**
     * Run an INSERT query and return the last insert ID.
     */
    public static function insert(string $query, array $params = []): false|string
    {
        $conn = self::connection();
        if (stripos(trim($query), 'INSERT') === 0) {
            $conn->exec($conn->prepareSQL($query));
            return $conn->lastInsertId();
        }
        $stm = $conn->prepare($query);
        $stm->execute($params);
        return $conn->lastInsertId();
    }

    /**
     * Run an UPDATE query and return affected rows.
     */
    public static function update(string $query, array $params = []): int
    {
        $stm = self::connection()->prepare($query);
        $stm->execute($params);
        return $stm->rowCount();
    }

    /**
     * Run a DELETE query and return affected rows.
     */
    public static function delete(string $query, array $params = []): int
    {
        $stm = self::connection()->prepare($query);
        $stm->execute($params);
        return $stm->rowCount();
    }

    /**
     * Get the PDO server info.
     */
    public static function connectionInfo(): array
    {
        return self::connection()->info();
    }
}
