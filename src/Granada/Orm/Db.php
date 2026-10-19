<?php

namespace Granada\Orm;

use PDO;

/**
 * The connection machinery shared by the row core (ORM) and the query
 * class (Granada\Query): the connection manager behind the static
 * facade, the execute paths, the query cache, and the dialect.
 *
 * @internal
 */
abstract class Db
{
    public const DEFAULT_CONNECTION = 'default';

    // The connection manager all static calls delegate to
    protected static ?ConnectionManager $_connection_manager = null;

    // LazyItemCache uses this to invalidate cache
    /** @var callable[] */
    protected static array $_on_write = [];

    // Name of the connection, in the connection manager, used by this instance
    protected string $_connection_name;

    // The name of the table the instance is associated with
    protected string $_table_name;

    // Name of the column to use as the primary key for
    // this instance only. Overrides the config settings.
    protected ?string $_instance_id_column = null;

    /**
     * The connection manager behind these statics, created on first use.
     */
    protected static function _manager(): ConnectionManager
    {
        return self::$_connection_manager ??= new ConnectionManager();
    }

    /**
     * Swap the connection manager behind the ORM statics, eg for a fresh
     * one per test.
     */
    public static function set_connection_manager(ConnectionManager $manager): void
    {
        self::$_connection_manager = $manager;
    }

    /**
     * Pass configuration settings to the class in the form of
     * key/value pairs. As a shortcut, if the second argument
     * is omitted and the key is a string, the setting is
     * assumed to be the DSN string used by PDO to connect
     * to the database (often, this will be the only configuration
     * required to use Idiorm). If you have more than one setting
     * you wish to configure, another shortcut is to pass an array
     * of settings (and omit the second argument). Setting a key
     * outside the documented set throws.
     * @param array|string $key
     * @param string $connection_name Which connection to use
     */
    public static function configure(array|string $key, mixed $value = null, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_manager()->configure($key, $value, $connection_name);
    }

    /**
     * Retrieve configuration options by key, or as whole array.
     * @param string $connection_name Which connection to use
     */
    public static function get_config(?string $key = null, string $connection_name = self::DEFAULT_CONNECTION): mixed
    {
        return self::_manager()->get_config($key, $connection_name);
    }

    /**
     * Reset every connection's settings to their defaults. Database
     * handles are left open; reset_db() closes those.
     */
    public static function reset_config(): void
    {
        self::_manager()->reset_config();
    }

    /**
     * Set up the database connection used by the class
     * @param string $connection_name Which connection to use
     */
    protected static function _setup_db(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_manager()->get_db($connection_name);
    }

    /**
     * Set the PDO object used by Idiorm to communicate with the database.
     * This is public in case the ORM should use a ready-instantiated
     * PDO object as its database connection. Accepts an optional string key
     * to identify the connection if multiple connections are used. Passing
     * null drops the handle; the next use reconnects from the settings.
     * @param PDO|null $db
     * @param string $connection_name Which connection to use
     */
    public static function set_db(?\PDO $db, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_manager()->set_db($db, $connection_name);
    }

    /**
     * Close and delete all registered PDO objects.
     */
    public static function reset_db(): void
    {
        self::_manager()->reset_db();
    }

    /**
     * The Dialect for a connection: every driver-specific fact the
     * renderer, save() and Query need.
     * @param string $connection_name Which connection to use
     */
    protected static function _dialect(string $connection_name = self::DEFAULT_CONNECTION): Dialect
    {
        return self::_manager()->dialect($connection_name);
    }

    /**
     * Returns the PDO instance used by the the ORM to communicate with
     * the database. This can be called if any low-level DB access is
     * required outside the class. If multiple connections are used,
     * accepts an optional key name for the connection.
     * @param string $connection_name Which connection to use
     * @return PDO
     */
    public static function get_db(string $connection_name = self::DEFAULT_CONNECTION): \PDO
    {
        return self::_manager()->get_db($connection_name);
    }

    /**
     * Run the callable inside one transaction on the connection:
     * success commits and returns the callable's return value; a
     * thrown exception rolls the work back and rethrows. A call
     * inside an open transaction joins the outermost one.
     * @param callable $fn
     * @param string $connection_name Which connection to use
     */
    public static function transaction(callable $fn, string $connection_name = self::DEFAULT_CONNECTION): mixed
    {
        return self::_manager()->transaction($fn, $connection_name);
    }

    /**
     * Begin a transaction on the connection. A call inside an open
     * transaction joins the outermost one and begins nothing. Pairs
     * with commit() and rollBack().
     * @param string $connection_name Which connection to use
     */
    public static function beginTransaction(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_manager()->beginTransaction($connection_name);
    }

    /**
     * Commit the transaction on the connection. A call inside a
     * nested transaction joins the outermost one and commits nothing.
     * @param string $connection_name Which connection to use
     */
    public static function commit(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_manager()->commit($connection_name);
    }

    /**
     * Roll the transaction on the connection back. The rollback ends
     * the outermost transaction, wherever it was asked for.
     * @param string $connection_name Which connection to use
     */
    public static function rollBack(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_manager()->rollBack($connection_name);
    }

    public static function _on_write(callable $callback): void
    {
        self::$_on_write[] = $callback;
    }

    /**
     * Executes a raw query as a wrapper for PDOStatement::execute.
     * Useful for queries that can't be accomplished through Idiorm,
     * particularly those using engine-specific features.
     * @example raw_execute('SELECT `name`, AVG(`order`) FROM `customer` GROUP BY `name` HAVING AVG(`order`) > 10')
     * @example raw_execute('INSERT OR REPLACE INTO `widget` (`id`, `name`) SELECT `id`, `name` FROM `other_table`')
     * @param string $query The raw SQL query
     * @param array<int, mixed>  $parameters Optional bound parameters
     * @param string $connection_name Which connection to use
     * @return bool Success
     */
    public static function raw_execute(string $query, array $parameters = [], string $connection_name = self::DEFAULT_CONNECTION): ?bool
    {
        self::_setup_db($connection_name);

        if (self::_is_write($query)) {
            return self::_execute_write($query, $parameters, $connection_name);
        }

        return self::_execute($query, $parameters, $connection_name);
    }

    /**
     * Returns the PDOStatement instance last used by any connection wrapped by the ORM.
     * Useful for access to PDOStatement::rowCount() or error information
     * @return \PDOStatement
     */
    public static function get_last_statement(): mixed
    {
        return self::_manager()->get_last_statement();
    }

    /**
     * Internal helper method for executing statments. Logs queries, and
     * stores statement object in the connection manager, accessible publicly
     * through ::get_last_statement()
     * @param string $query
     * @param array $parameters An array of parameters to be bound in to the query
     * @param string $connection_name Which connection to use
     * @return bool Response of PDOStatement::execute()
     */
    protected static function _execute(string $query, array $parameters = [], string $connection_name = self::DEFAULT_CONNECTION): ?bool
    {
        return self::_manager()->execute($query, $parameters, $connection_name);
    }

    /**
     * Execute a mutating statement, then run the write callbacks.
     * Every write path goes through here; the read path does not.
     * @param string $query
     * @param mixed[] $parameters
     */
    protected static function _execute_write(string $query, array $parameters, string $connection_name = self::DEFAULT_CONNECTION): ?bool
    {
        $result = self::_execute($query, $parameters, $connection_name);

        foreach (self::$_on_write as $callback) {
            $callback();
        }

        return $result;
    }

    /**
     * Whether a raw statement changes rows.
     * A raw statement is a single statement, so its leading keyword decides.
     * @param string $query
     */
    private static function _is_write(string $query): bool
    {
        $sql = strtoupper(ltrim($query));

        return str_starts_with($sql, 'INSERT')
            || str_starts_with($sql, 'REPLACE')
            || str_starts_with($sql, 'UPDATE')
            || str_starts_with($sql, 'DELETE')
            || str_starts_with($sql, 'TRUNCATE');
    }

    /**
     * Store an inlined query in the query log and invoke the
     * configured logger, if logging is enabled for the connection.
     * @param string $inlined_query
     * @param string $connection_name Which connection to use
     */
    protected static function _record_query(string $inlined_query, string $connection_name): void
    {
        self::_manager()->record_query($inlined_query, $connection_name);
    }

    /**
     * Get the last query executed. Only works if the
     * 'logging' config option is set to true. Otherwise
     * this will return null. Returns last query from all connections if
     * no connection_name is specified
     * @param null|string $connection_name Which connection to use
     * @return string
     */
    public static function get_last_query(?string $connection_name = null): ?string
    {
        return self::_manager()->get_last_query($connection_name);
    }

    /**
     * Get an array containing all the queries run on a
     * specified connection up to now.
     * Only works if the 'logging' config option is
     * set to true. Otherwise, returned array will be empty.
     * @param string $connection_name Which connection to use
     */
    public static function get_query_log(string $connection_name = self::DEFAULT_CONNECTION): array
    {
        return self::_manager()->get_query_log($connection_name);
    }

    /**
     * Get a list of the available connection names
     * @return array<int, string>
     */
    public static function get_connection_names(): array
    {
        return self::_manager()->get_connection_names();
    }

    /**
     * Create a cache key for the given query and parameters.
     */
    protected static function _create_cache_key(string $query, array $parameters): string
    {
        $parameter_string = implode(',', $parameters);
        $key              = $query . ':' . $parameter_string;

        return sha1($key);
    }

    /**
     * Check the query cache for the given cache key. If a value
     * is cached for the key, return the value. Otherwise, return false.
     * @param string $cache_key
     * @param string $connection_name Which connection to use
     */
    protected static function _check_query_cache(string $cache_key, string $connection_name = self::DEFAULT_CONNECTION): mixed
    {
        return self::_manager()->check_query_cache($cache_key, $connection_name);
    }

    /**
     * Clear the query cache
     */
    public static function clear_cache(): void
    {
        self::_manager()->clear_cache();
    }

    /**
     * Add the given value to the query cache.
     * @param string $cache_key
     * @param string $connection_name Which connection to use
     */
    protected static function _cache_query_result(string $cache_key, mixed $value, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_manager()->cache_query_result($cache_key, $value, $connection_name);
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     */
    protected function _quote_identifier(string $identifier): string
    {
        return self::_dialect($this->_connection_name)->quote_identifier($identifier);
    }

    /**
     * The name of the column in the database table which contains
     * the primary key ID of the row.
     */
    protected function _get_id_column_name(): string
    {
        if (!is_null($this->_instance_id_column)) {
            return $this->_instance_id_column;
        }

        $id_column_overrides = self::get_config('id_column_overrides', $this->_connection_name);
        if (isset($id_column_overrides[$this->_table_name])) {
            return $id_column_overrides[$this->_table_name];
        }

        return self::get_config('id_column', $this->_connection_name);
    }

    /**
     * Specify the ID column to use for this instance or array of instances only.
     * This overrides the id_column and id_column_overrides settings.
     *
     * This is mostly useful for libraries built on top of Idiorm, and will
     * not normally be used in manually built queries. If you don't know why
     * you would want to use this, you should probably just ignore it.
     */
    public function use_id_column(?string $id_column): static
    {
        $this->_instance_id_column = $id_column;

        return $this;
    }
}
