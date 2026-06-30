<?php

namespace Granada;

use ArrayAccess;
use PDO;

/**
 * Granada
 *
 * Copyright (c) 2013, Erik Wiesenthal
 * All rights reserved
 *
 * https://github.com/GranadaORM/Granada/
 *
 * Idiorm with some small changes
 * ( https://github.com/GranadaORM/Granada/ ).
 *
 * Idiorm
 *
 * https://github.com/j4mie/idiorm/
 *
 * A single-class super-simple database abstraction layer for PHP.
 * Provides (nearly) zero-configuration object-relational mapping
 * and a fluent interface for building basic, commonly-used queries.
 *
 * BSD Licensed.
 *
 * Copyright (c) 2010, Jamie Matthews
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
/** @implements ArrayAccess<string, mixed> */
class ORM implements ArrayAccess
{
    // ----------------------- //
    // --- CLASS CONSTANTS --- //
    // ----------------------- //

    // WHERE and HAVING condition array keys
    public const CONDITION_FRAGMENT = 0;
    public const CONDITION_VALUES   = 1;

    public const DEFAULT_CONNECTION = 'default';

    // Limit clause style
    public const LIMIT_STYLE_TOP_N = 'top';
    public const LIMIT_STYLE_LIMIT = 'limit';

    // ------------------------ //
    // --- CLASS PROPERTIES --- //
    // ------------------------ //

    // Class configuration
    /** @var array<string, mixed> */
    protected static array $_default_config = [
        'connection_string'           => 'sqlite::memory:',
        'id_column'                   => 'id',
        'id_column_overrides'         => [],
        'error_mode'                  => PDO::ERRMODE_EXCEPTION,
        'username'                    => null,
        'password'                    => null,
        'driver_options'              => null,
        'identifier_quote_character'  => null, // if this is null, will be autodetected
        'limit_clause_style'          => null, // if this is null, will be autodetected
        'logging'                     => false,
        'logger'                      => null,
        'caching'                     => false,
        'return_result_sets'          => true,
        'find_many_primary_id_as_key' => true,
    ];

    protected static array $_config = [];

    // Map of database connections, instances of the PDO class
    /** @var array<string, \PDO> */
    protected static array $_db = [];

    // Last query run, only populated if logging is enabled
    protected static ?string $_last_query = null;

    // Log of all queries run, mapped by connection key, only populated if logging is enabled
    /** @var array<string, array<string, mixed>> */
    protected static array $_query_log = [];

    // Query cache, only used if query caching is enabled
    /** @var array<string, mixed> */
    protected static array $_query_cache = [];

    // Reference to previously used PDOStatement object to enable low-level access, if needed
    protected static $_last_statement = null;

    // --------------------------- //
    // --- INSTANCE PROPERTIES --- //
    // --------------------------- //

    // Key name of the connections in self::$_db used by this instance
    protected string $_connection_name;

    // The name of the table the current ORM instance is associated with
    protected string $_table_name;

    // Alias for the table to be used in SELECT queries
    protected ?string $_table_alias = null;

    // Values to be bound to the query
    /** @var array<int, mixed> */
    protected array $_values = [];

    // Columns to select in the result
    /** @var array<int, string> */
    protected array $_result_columns = ['*'];

    // Are we using the default result column or have these been manually changed?
    protected bool $_using_default_result_columns = true;

    // Join sources
    /** @var array<int, string> */
    protected array $_join_sources = [];

    // Should the query include a DISTINCT keyword?
    protected bool $_distinct = false;

    // Is this a raw query?
    protected bool $_is_raw_query = false;

    // The raw query
    protected string $_raw_query = '';

    // The raw query parameters
    /** @var array<int, mixed> */
    protected array $_raw_parameters = [];

    // Array of WHERE clauses
    /** @var array<int, array<string, mixed>> */
    protected array $_where_conditions = [];

    /** @var array<int, array<string, mixed>> */
    protected array $_where_conditions_stash = [];

    // LIMIT
    protected ?int $_limit = null;

    // OFFSET
    protected ?int $_offset = null;

    // ORDER BY
    /** @var array<int, string> */
    protected array $_order_by = [];

    // GROUP BY
    /** @var array<int, string> */
    protected array $_group_by = [];

    // HAVING
    /** @var array<int, array<string, mixed>> */
    protected array $_having_conditions = [];

    // The data for a hydrated instance of the class
    /** @var array<string, mixed> */
    protected array $_data = [];

    // Fields that have been modified during the
    // lifetime of the object
    /** @var array<string, mixed> */
    protected array $_dirty_fields = [];

    // The data as at hydration time, used for comparison of dirty fields
    /** @var array<string, mixed> */
    protected array $_clean_data = [];

    // Fields that are to be inserted in the DB raw
    /** @var array<string, mixed> */
    protected array $_expr_fields = [];

    // Is this a new object (has create() been called)?
    protected bool $_is_new = false;

    // Name of the column to use as the primary key for
    // this instance only. Overrides the config settings.
    protected ?string $_instance_id_column = null;

    // name of the ResultSet Object
    public string $resultSetClass = 'Granada\ResultSet';

    // associative results flag
    protected bool $_associative_results = true;

    // ---------------------- //
    // --- STATIC METHODS --- //
    // ---------------------- //

    /**
     * Pass configuration settings to the class in the form of
     * key/value pairs. As a shortcut, if the second argument
     * is omitted and the key is a string, the setting is
     * assumed to be the DSN string used by PDO to connect
     * to the database (often, this will be the only configuration
     * required to use Idiorm). If you have more than one setting
     * you wish to configure, another shortcut is to pass an array
     * of settings (and omit the second argument).
     * @param array|string $key
     * @param mixed $value
     * @param string $connection_name Which connection to use
     */
    public static function configure(array|string $key, mixed $value = null, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_setup_db_config($connection_name); // ensures at least default config is set

        if (is_array($key)) {
            // Shortcut: If only one array argument is passed,
            // assume it's an array of configuration settings
            foreach ($key as $conf_key => $conf_value) {
                self::configure($conf_key, $conf_value, $connection_name);
            }
        } else {
            if (is_null($value)) {
                // Shortcut: If only one string argument is passed,
                // assume it's a connection string
                $value = $key;
                $key   = 'connection_string';
            }
            self::$_config[$connection_name][$key] = $value;
        }

        if ($key === 'connection_string') {
            self::_setup_default_driver_options();
        }
    }

    private static function _setup_default_driver_options(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        if (self::$_config[$connection_name]['driver_options']) {
            return;
        }

        if (str_starts_with(self::$_config[$connection_name]['connection_string'], 'mysql:')) {
            // Set default connection mode for MySQL to be SSL without verifying certificate
            self::$_config[$connection_name]['driver_options'] = [
                PDO::MYSQL_ATTR_SSL_CA                 => true,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ];
        }
    }

    /**
     * Retrieve configuration options by key, or as whole array.
     * @param string $key
     * @param string $connection_name Which connection to use
     */
    public static function get_config(?string $key = null, string $connection_name = self::DEFAULT_CONNECTION): mixed
    {
        if ($key) {
            return self::$_config[$connection_name][$key];
        }

        return self::$_config[$connection_name];
    }

    /**
     * Delete all configs in _config array.
     */
    public static function reset_config(): void
    {
        self::$_config = [];
    }

    /**
     * Despite its slightly odd name, this is actually the factory
     * method used to acquire instances of the class. It is named
     * this way for the sake of a readable interface, ie
     * ORM::for_table('table_name')->find_one()-> etc. As such,
     * this will normally be the first method called in a chain.
     * @param string $table_name
     * @param string $connection_name Which connection to use
     * @return static
     */
    public static function for_table(string $table_name, string $connection_name = self::DEFAULT_CONNECTION): static
    {
        self::_setup_db($connection_name);

        return new static($table_name, [], $connection_name);
    }

    /**
     * Set up the database connection used by the class
     * @param string $connection_name Which connection to use
     */
    protected static function _setup_db(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        if (
            array_key_exists($connection_name, self::$_db)
            && is_object(self::$_db[$connection_name])
        ) {
            return;
        }

        self::_setup_db_config($connection_name);

        $db = new PDO(
            self::$_config[$connection_name]['connection_string'],
            self::$_config[$connection_name]['username'],
            self::$_config[$connection_name]['password'],
            is_array(self::$_config[$connection_name]['driver_options']) ? self::$_config[$connection_name]['driver_options'] : null
        );

        $db->setAttribute(PDO::ATTR_ERRMODE, self::$_config[$connection_name]['error_mode']);
        self::set_db($db, $connection_name);
    }

    /**
     * Ensures configuration (mulitple connections) is at least set to default.
     * @param string $connection_name Which connection to use
     */
    protected static function _setup_db_config(string $connection_name): void
    {
        if (!array_key_exists($connection_name, self::$_config)) {
            self::$_config[$connection_name] = self::$_default_config;
        }
    }

    /**
     * Set the PDO object used by Idiorm to communicate with the database.
     * This is public in case the ORM should use a ready-instantiated
     * PDO object as its database connection. Accepts an optional string key
     * to identify the connection if multiple connections are used.
     * @param PDO $db
     * @param string $connection_name Which connection to use
     */
    public static function set_db(?\PDO $db, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        self::_setup_db_config($connection_name);
        self::$_db[$connection_name] = $db;
        self::_setup_identifier_quote_character($connection_name);
        self::_setup_limit_clause_style($connection_name);
    }

    /**
     * Close and delete all registered PDO objects in _db array.
     */
    public static function reset_db(): void
    {
        self::$_db = [];
    }

    /**
     * Detect and initialise the character used to quote identifiers
     * (table names, column names etc). If this has been specified
     * manually using ORM::configure('identifier_quote_character', 'some-char'),
     * this will do nothing.
     * @param string $connection_name Which connection to use
     */
    protected static function _setup_identifier_quote_character(string $connection_name): void
    {
        if (is_null(self::$_config[$connection_name]['identifier_quote_character'])) {
            self::$_config[$connection_name]['identifier_quote_character'] = self::_detect_identifier_quote_character($connection_name);
        }
    }

    /**
     * Detect and initialise the limit clause style ("SELECT TOP 5" /
     * "... LIMIT 5"). If this has been specified manually using
     * ORM::configure('limit_clause_style', 'top'), this will do nothing.
     * @param string $connection_name Which connection to use
     */
    public static function _setup_limit_clause_style(string $connection_name): void
    {
        if (is_null(self::$_config[$connection_name]['limit_clause_style'])) {
            self::$_config[$connection_name]['limit_clause_style'] = self::_detect_limit_clause_style($connection_name);
        }
    }

    /**
     * Return the correct character used to quote identifiers (table
     * names, column names etc) by looking at the driver being used by PDO.
     * @param string $connection_name Which connection to use
     * @return string
     */
    protected static function _detect_identifier_quote_character(string $connection_name): string
    {
        switch (self::get_db($connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'pgsql':
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
            case 'sybase':
            case 'firebird':
                return '"';

            case 'mysql':
            case 'sqlite':
            case 'sqlite2':
            default:
                return '`';
        }
    }

    /**
     * Returns a constant after determining the appropriate limit clause
     * style
     * @param string $connection_name Which connection to use
     * @return string Limit clause style keyword/constant
     */
    protected static function _detect_limit_clause_style(string $connection_name): string
    {
        switch (self::get_db($connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
                return ORM::LIMIT_STYLE_TOP_N;

            default:
                return ORM::LIMIT_STYLE_LIMIT;
        }
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
        self::_setup_db($connection_name); // required in case this is called before Idiorm is instantiated

        return self::$_db[$connection_name];
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

        return self::_execute($query, $parameters, $connection_name);
    }

    /**
     * Returns the PDOStatement instance last used by any connection wrapped by the ORM.
     * Useful for access to PDOStatement::rowCount() or error information
     * @return \PDOStatement
     */
    public static function get_last_statement(): mixed
    {
        return self::$_last_statement;
    }

    /**
     * Internal helper method for executing statments. Logs queries, and
     * stores statement object in ::_last_statment, accessible publicly
     * through ::get_last_statement()
     * @param string $query
     * @param array $parameters An array of parameters to be bound in to the query
     * @param string $connection_name Which connection to use
     * @return bool Response of PDOStatement::execute()
     */
    protected static function _execute(string $query, array $parameters = [], string $connection_name = self::DEFAULT_CONNECTION): ?bool
    {
        self::_log_query($query, $parameters, $connection_name);
        $statement = self::get_db($connection_name)->prepare($query);

        self::$_last_statement = $statement;

        return $statement->execute($parameters);
    }

    /**
     * Add a query to the internal query log. Only works if the
     * 'logging' config option is set to true.
     *
     * This works by manually binding the parameters to the query - the
     * query isn't executed like this (PDO normally passes the query and
     * parameters to the database which takes care of the binding) but
     * doing it this way makes the logged queries more readable.
     * @param string $query
     * @param array<int, mixed> $parameters An array of parameters to be bound in to the query
     * @param string $connection_name Which connection to use
     * @return bool
     */
    protected static function _log_query(string $query, array $parameters, string $connection_name): bool
    {
        // If logging is not enabled, do nothing
        if (!self::$_config[$connection_name]['logging']) {
            return false;
        }

        if (!isset(self::$_query_log[$connection_name])) {
            self::$_query_log[$connection_name] = [];
        }

        if (count($parameters) > 0) {
            // Escape the parameters it not null
            foreach ($parameters as $key => $parameter) {
                $parameters[$key] = ($parameter === null) ? 'NULL' : self::$_db[$connection_name]->quote($parameter);
            }

            // Avoid %format collision for vsprintf
            $query = str_replace('%', '%%', $query);

            // Replace placeholders in the query for vsprintf
            if (str_contains($query, "'") || str_contains($query, '"')) {
                $query = Orm\Str::str_replace_outside_quotes('?', '%s', $query);
            } else {
                $query = str_replace('?', '%s', $query);
            }

            // Replace the question marks in the query with the parameters
            $bound_query = vsprintf($query, $parameters);
        } else {
            $bound_query = $query;
        }

        self::$_last_query                    = $bound_query;
        self::$_query_log[$connection_name][] = $bound_query;

        if (is_callable(self::$_config[$connection_name]['logger'])) {
            $logger = self::$_config[$connection_name]['logger'];
            $logger($bound_query);
        }

        return true;
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
        if ($connection_name === null) {
            return self::$_last_query;
        }
        if (!isset(self::$_query_log[$connection_name])) {
            return '';
        }

        return end(self::$_query_log[$connection_name]);
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
        if (isset(self::$_query_log[$connection_name])) {
            return self::$_query_log[$connection_name];
        }

        return [];
    }

    /**
     * Get a list of the available connection names
     * @return array<int, string>
     */
    public static function get_connection_names(): array
    {
        return array_keys(self::$_db);
    }

    // ------------------------ //
    // --- INSTANCE METHODS --- //
    // ------------------------ //

    /**
     * "Private" constructor; shouldn't be called directly.
     * @param array<string, mixed> $data
     * @param string $connection_name Which connection to use
     */
    protected function __construct(string $table_name, array $data = [], string $connection_name = self::DEFAULT_CONNECTION)
    {
        $this->_table_name = $table_name;
        $this->_data       = $data;
        $this->_clean_data = [];

        $this->_connection_name = $connection_name;

        // Set the flag as config dictates
        $this->_associative_results = self::$_config[$this->_connection_name]['find_many_primary_id_as_key'];

        self::_setup_db_config($connection_name);
    }

    /**
     * Create a new, empty instance of the class. Used
     * to add a new row to your database. May optionally
     * be passed an associative array of data to populate
     * the instance. If so, all fields will be flagged as
     * dirty so all will be saved to the database when
     * save() is called.
     */
    public function create(?array $data = null)
    {
        $this->_is_new = true;
        if (!is_null($data)) {
            return $this->hydrate($data)->force_all_dirty();
        }

        return $this;
    }

    /**
     * Set the ORM instance to return non associative results sets
     * @return static instance
     */
    public function non_associative(): static
    {
        $this->_associative_results = false;

        return $this;
    }

    /**
     * Set the ORM instance to return associative results sets
     * @return static instance
     */
    public function associative(): static
    {
        $this->_associative_results = true;

        return $this;
    }

    /**
     * Set the ORM instance to return associative (or not) results sets, as config dictates
     * @return static instance
     */
    public function reset_associative(): static
    {
        $this->_associative_results = self::$_config[$this->_connection_name]['find_many_primary_id_as_key'];

        return $this;
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

    /**
     * Create an ORM instance from the given row (an associative
     * array of data fetched from the database)
     * @param array<string, mixed> $row
     */
    protected function _create_instance_from_row(array $row): static
    {
        $instance = static::for_table($this->_table_name, $this->_connection_name);
        $instance->use_id_column($this->_instance_id_column);
        $instance->hydrate($row);

        return $instance;
    }

    /**
     * Tell the ORM that you are expecting a single result
     * back from your query, and execute it. Will return
     * a single instance of the ORM class, or false if no
     * rows were returned.
     * As a shortcut, you may supply an ID as a parameter
     * to this method. This will perform a primary key
     * lookup on the table.
     */
    public function find_one(mixed $id = null)
    {
        if (!is_null($id)) {
            $this->where_id_is($id);
        }

        $rows = $this->limit(1)->_run();

        if (empty($rows)) {
            return null;
        }

        return $this->_create_instance_from_row($rows[0]);
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return an array
     * of instances of the ORM class, or an empty array if
     * no rows were returned.
     * @return array|ResultSet
     */
    public function find_many()
    {
        if (self::$_config[$this->_connection_name]['return_result_sets']) {
            return $this->find_result_set();
        }

        return $this->_find_many();
    }

    /**
     * Perform a find_many then map the results through a function
     * @param callable $func
     */
    public function find_map(callable $func): array
    {
        return array_map($func, $this->find_many()->as_array());
    }

    /**
     * Instead of running the query, get the query that would be run for a find_many() call
     * @param string $connection_name
     * @return string
     */
    public function get_select_query(string $connection_name = self::DEFAULT_CONNECTION): string
    {
        // Ensure logging works
        $before_log                                 = self::$_config[$connection_name]['logging'];
        self::$_config[$connection_name]['logging'] = true;

        $this->_log_query($this->_build_select(), $this->_values, $connection_name);

        self::$_config[$connection_name]['logging'] = $before_log;

        return $this->get_last_query();
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return an array
     * of instances of the ORM class, or an empty array if
     * no rows were returned.
     * @return array<int|static, static>
     */
    protected function _find_many(bool $associative = true): array
    {
        $rows = $this->_run();

        return $this->_get_instances($rows);
    }

    /**
     * Create instances of each row in the result and map
     * them to an associative array with the primary IDs as
     * the array keys.
     * @param array<int, array<string, mixed>> $rows
     * @return array
     */
    protected function _get_instances(array $rows): array
    {
        $size      = count($rows);
        $instances = [];
        for ($i = 0; $i < $size; $i++) {
            $row = $this->_create_instance_from_row($rows[$i]);
            if (
                isset($row->{$this->_instance_id_column})
                && $this->_associative_results
                && $row->id()
            ) {
                $instances[$row->id()] = $row;

                continue;
            }
            $instances[$i] = $row;
        }

        return $instances;
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return a result set object
     * containing instances of the ORM class.
     * @return \Granada\ResultSet
     */
    public function find_result_set(): ResultSet
    {
        $resultSetClass = $this->resultSetClass;
        if (is_a($resultSetClass, 'Granada\ResultSet', true)) {
            $result = new $resultSetClass($this->_find_many());
        } else {
            $result = new ResultSet($this->_find_many());
        }

        return $result;
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return an array,
     * or an empty array if no rows were returned.
     * @return array
     */
    public function find_array(): array
    {
        return $this->_run();
    }

    /**
     * Tell the ORM that you wish to execute a COUNT query.
     * Will return an integer representing the number of
     * rows returned.
     */
    public function count(string $column = '*'): int
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a MAX query.
     * Will return the max value of the choosen column.
     * @param string $column
     */
    public function max(string $column): int
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a MIN query.
     * Will return the min value of the choosen column.
     * @param string $column
     */
    public function min(string $column): int
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a AVG query.
     * Will return the average value of the choosen column.
     * @param string $column
     */
    public function avg(string $column): float|int
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the ORM that you wish to execute a SUM query.
     * Will return the sum of the choosen column.
     * @param string $column
     */
    public function sum(string $column): float|int
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Execute an aggregate query on the current connection.
     * @param string $sql_function The aggregate function to call eg. MIN, COUNT, etc
     * @param string $column The column to execute the aggregate query against
     * @return int
     */
    protected function _call_aggregate_db_function(string $sql_function, string $column): float|int|string
    {
        $alias        = strtolower($sql_function);
        $sql_function = strtoupper($sql_function);
        if ($column !== '*') {
            $column = $this->_quote_identifier($column);
        }
        $result_columns        = $this->_result_columns;
        $this->_result_columns = [];
        $this->select_expr("{$sql_function}({$column})", $alias);
        $result                = $this->find_one();
        $this->_result_columns = $result_columns;

        $return_value = 0;
        if ($result !== false && isset($result->$alias)) {
            if (!is_numeric($result->$alias)) {
                $return_value = $result->$alias;
            } elseif ((int) $result->$alias == (float) $result->$alias) {
                $return_value = (int) $result->$alias;
            } else {
                $return_value = (float) $result->$alias;
            }
        }

        return $return_value;
    }

    /**
     * This method can be called to hydrate (populate) this
     * instance of the class from an associative array of data.
     * This will usually be called only from inside the class,
     * but it's public in case you need to call it directly.
     */
    public function hydrate(array $data = [])
    {
        $this->_data       = $data;
        $this->_clean_data = [];

        return $this;
    }

    /**
     * Force the ORM to flag all the fields in the $data array
     * as "dirty" and therefore update them when save() is called.
     */
    public function force_all_dirty(): static
    {
        $this->_dirty_fields = $this->_data;

        return $this;
    }

    /**
     * Perform a raw query. The query can contain placeholders in
     * either named or question mark style. If placeholders are
     * used, the parameters should be an array of values which will
     * be bound to the placeholders in the query. If this method
     * is called, all other query building methods will be ignored.
     * @param string $query
     */
    public function raw_query(string $query, array $parameters = []): static
    {
        $this->_is_raw_query   = true;
        $this->_raw_query      = $query;
        $this->_raw_parameters = $parameters;

        return $this;
    }

    /**
     * Add an alias for the main table to be used in SELECT queries
     * @param string $alias
     */
    public function table_alias(string $alias): static
    {
        $this->_table_alias = $alias;

        return $this;
    }

    /**
     * Internal method to add an unquoted expression to the set
     * of columns returned by the SELECT query. The second optional
     * argument is the alias to return the expression as.
     */
    protected function _add_result_column(string $expr, ?string $alias = null): static
    {
        if (!is_null($alias)) {
            $expr .= ' AS ' . $this->_quote_identifier($alias);
        }

        if ($this->_using_default_result_columns) {
            $this->_result_columns               = [$expr];
            $this->_using_default_result_columns = false;
        } else {
            if (!in_array($expr, $this->_result_columns)) {
                $this->_result_columns[] = $expr;
            }
        }

        return $this;
    }

    /**
     * Add a column to the list of columns returned by the SELECT
     * query. This defaults to '*'. The second optional argument is
     * the alias to return the column as.
     * @param string $alias
     */
    public function select(string $column_list, ?string $alias = null): static
    {
        $columns = array_map('trim', explode(',', $column_list));
        foreach ($columns as $column) {
            if ($column === '*') {
                if (!$this->_using_default_result_columns) {
                    // Check if we are already selecting '*'
                    if (($this->_result_columns[\array_key_first($this->_result_columns)] ?? '') !== '*') {
                        // Put the * to the front of the list
                        $this->_result_columns = array_merge(['*'], $this->_result_columns);
                    }
                }
            } else {
                $column = $this->_quote_identifier($column);
                $this->_add_result_column($column, $alias);
            }
        }

        return $this;
    }

    /**
     * Reset result columns to default (SELECT *).
     */
    public function clear_select(): static
    {
        $this->_result_columns               = ['*'];
        $this->_using_default_result_columns = true;

        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns returned
     * by the SELECT query. The second optional argument is
     * the alias to return the column as.
     */
    public function select_expr(string $expr, ?string $alias = null): static
    {
        return $this->_add_result_column($expr, $alias);
    }

    /**
     * Add columns to the list of columns returned by the SELECT
     * query. This defaults to '*'. Many columns can be supplied
     * as either an array or as a list of parameters to the method.
     *
     * Note that the alias must not be numeric - if you want a
     * numeric alias then prepend it with some alpha chars. eg. a1
     *
     * @example select_many(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5');
     * @example select_many('column', 'column2', 'column3');
     * @example select_many(array('column', 'column2', 'column3'), 'column4', 'column5');
     *
     * @return static
     */
    public function select_many(...$columns): static
    {
        if (!($columns ?? false)) {
            return $this;
        }

        $columns = $this->_normalise_select_many_columns($columns);
        foreach ($columns as $alias => $column) {
            if (is_numeric($alias)) {
                $alias = null;
            }
            $this->select($column, $alias);
        }

        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns returned
     * by the SELECT query. Many columns can be supplied as either
     * an array or as a list of parameters to the method.
     *
     * Note that the alias must not be numeric - if you want a
     * numeric alias then prepend it with some alpha chars. eg. a1
     *
     * @example select_many_expr(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5')
     * @example select_many_expr('column', 'column2', 'column3')
     * @example select_many_expr(array('column', 'column2', 'column3'), 'column4', 'column5')
     *
     * @return static
     */
    public function select_many_expr(...$columns): static
    {
        if (empty($columns)) {
            return $this;
        }

        $columns = $this->_normalise_select_many_columns($columns);
        foreach ($columns as $alias => $column) {
            if (is_numeric($alias)) {
                $alias = null;
            }
            $this->select_expr($column, $alias);
        }

        return $this;
    }

    /**
     * Take a column specification for the select many methods and convert it
     * into a normalised array of columns and aliases.
     *
     * It is designed to turn the following styles into a normalised array:
     *
     * array(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5'))
     *
     * @param array<int, string|array>  $columns
     * @return array
     */
    /** @return array<string|int, string> */
    protected function _normalise_select_many_columns(array $columns): array
    {
        $return = [];
        foreach ($columns as $column) {
            if (is_array($column)) {
                foreach ($column as $key => $value) {
                    if (!is_numeric($key)) {
                        $return[$key] = $value;
                    } else {
                        $return[] = $value;
                    }
                }
            } else {
                $return[] = $column;
            }
        }

        return $return;
    }

    /**
     * Add a DISTINCT keyword before the list of columns in the SELECT query
     */
    public function distinct(): static
    {
        $this->_distinct = true;

        return $this;
    }

    /**
     * Internal method to add a JOIN source to the query.
     *
     * The join_operator should be one of INNER, LEFT OUTER, CROSS etc - this
     * will be prepended to JOIN.
     *
     * The table should be the name of the table to join to.
     *
     * The constraint may be either a string or an array with three elements. If it
     * is a string, it will be compiled into the query as-is, with no escaping. The
     * recommended way to supply the constraint is as an array with three elements:
     *
     * first_column, operator, second_column
     *
     * Example: array('user.id', '=', 'profile.user_id')
     *
     * will compile to
     *
     * ON `user`.`id` = `profile`.`user_id`
     *
     * The final (optional) argument specifies an alias for the joined table.
     * @param string $join_operator
     */
    protected function _add_join_source(string $join_operator, string $table, array|string $constraint, ?string $table_alias = null): static
    {
        $join_operator = trim("{$join_operator} JOIN");

        $table = $this->_quote_identifier($table);

        // Add table alias if present
        if (!is_null($table_alias)) {
            $table_alias = $this->_quote_identifier($table_alias);
            $table .= " {$table_alias}";
        }

        // Build the constraint
        if (is_array($constraint)) {
            [$first_column, $operator, $second_column] = $constraint;
            $first_column                              = $this->_quote_identifier($first_column);
            $second_column                             = $this->_quote_identifier($second_column);
            $constraint                                = "{$first_column} {$operator} {$second_column}";
        }

        $this->_join_sources[] = "{$join_operator} {$table} ON {$constraint}";

        return $this;
    }

    /**
     * Add a simple JOIN source to the query
     * @param string $table_alias
     */
    public function join(string $table, array|string $constraint, ?string $table_alias = null): static
    {
        return $this->_add_join_source('', $table, $constraint, $table_alias);
    }

    /**
     * Add an INNER JOIN souce to the query
     * @param string $table
     * @param string[] $constraint
     */
    public function inner_join(string $table, array|string $constraint, ?string $table_alias = null): static
    {
        return $this->_add_join_source('INNER', $table, $constraint, $table_alias);
    }

    /**
     * Add a LEFT OUTER JOIN souce to the query
     * @param string $table
     * @param string[] $constraint
     */
    public function left_outer_join(string $table, array|string $constraint, ?string $table_alias = null): static
    {
        return $this->_add_join_source('LEFT OUTER', $table, $constraint, $table_alias);
    }

    /**
     * Add an RIGHT OUTER JOIN souce to the query
     * @param string $table
     * @param string[] $constraint
     */
    public function right_outer_join(string $table, array|string $constraint, ?string $table_alias = null): static
    {
        return $this->_add_join_source('RIGHT OUTER', $table, $constraint, $table_alias);
    }

    /**
     * Add an FULL OUTER JOIN souce to the query
     * @param string $table
     * @param string[] $constraint
     */
    public function full_outer_join(string $table, array|string $constraint, ?string $table_alias = null): static
    {
        return $this->_add_join_source('FULL OUTER', $table, $constraint, $table_alias);
    }

    /**
     * Internal method to add a HAVING condition to the query
     */
    protected function _add_having(string $fragment, mixed $values = []): static
    {
        return $this->_add_condition('having', $fragment, $values);
    }

    /**
     * Internal method to add a HAVING condition to the query
     * @param string $separator
     */
    protected function _add_simple_having(string $column_name, string $separator, mixed $value): static
    {
        return $this->_add_simple_condition('having', $column_name, $separator, $value);
    }

    /**
     * Internal method to add a WHERE condition to the query
     */
    protected function _add_where(string $fragment, mixed $values = []): static
    {
        return $this->_add_condition('where', $fragment, $values);
    }

    /**
     * Internal method to add a WHERE condition to the query
     * @param string $separator
     */
    protected function _add_simple_where(string $column_name, string $separator, mixed $value): static
    {
        return $this->_add_simple_condition('where', $column_name, $separator, $value);
    }

    /**
     * Internal method to add a HAVING or WHERE condition to the query
     */
    protected function _add_condition(string $type, string $fragment, mixed $values = []): static
    {
        $conditions_class_property_name = "_{$type}_conditions";
        if (!is_array($values)) {
            $values = [$values];
        }
        $filter = [
            self::CONDITION_FRAGMENT => $fragment,
            self::CONDITION_VALUES   => $values,
        ];
        if (in_array($filter, $this->$conditions_class_property_name)) {
            // Condition already exists, de-dupe
            return $this;
        }
        array_push($this->$conditions_class_property_name, $filter);

        return $this;
    }

    /**
     * Save the where conditions and clear
     * Use pop_where to get them back
     *
     * @return static
     */
    public function stash_where(): static
    {
        $this->_where_conditions_stash = $this->_where_conditions;

        return $this->clear_where();
    }

    /**
     * Reinstate the stashed conditions to the end of the where list
     *
     * @return static
     */
    public function pop_where(): static
    {
        foreach ($this->_where_conditions_stash as $stash) {
            $this->_where_conditions[] = $stash;
        }
        $this->_where_conditions_stash = [];

        return $this;
    }

    /**
     * Cleare all WHERE clauses that reference column
     * @param string $column
     * @return static
     */
    public function remove_where(string $column): static
    {
        $new_conditions = [];
        foreach ($this->_where_conditions as $idx => $where_condition) {
            if (str_contains($where_condition[self::CONDITION_FRAGMENT], '`' . $column . '`')) {
                continue;
            }

            $new_conditions[] = $where_condition;
        }
        $this->_where_conditions = $new_conditions;

        return $this;
    }

    /**
     * Clear / Reset the WHERE clause(s)
     * @return static
     */
    public function clear_where(): static
    {
        $this->_where_conditions = [];

        return $this;
    }

    /**
     * Clear / Reset the HAVING clause(s)
     * @return static
     */
    public function clear_having(): static
    {
        $this->_having_conditions = [];

        return $this;
    }

    /**
     * Helper method to compile a simple COLUMN SEPARATOR VALUE
     * style HAVING or WHERE condition into a string and value ready to
     * be passed to the _add_condition method. Avoids duplication
     * of the call to _quote_identifier
     * @param string $type
     */
    protected function _add_simple_condition(string $type, string $column_name, string $separator, mixed $value): static
    {
        // Add the table name in case of ambiguous columns
        if (count($this->_join_sources) > 0 && !str_contains($column_name, '.')) {
            $table = $this->_table_name;
            if (!is_null($this->_table_alias)) {
                $table = $this->_table_alias;
            }

            $column_name = "{$table}.{$column_name}";
        }
        $column_name = $this->_quote_identifier($column_name);

        return $this->_add_condition($type, "{$column_name} {$separator} ?", $value);
    }

    /**
     * Return a string containing the given number of question marks,
     * separated by commas. Eg "?, ?, ?"
     */
    protected function _create_placeholders(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        $db_fields = [];
        foreach ($fields as $key => $value) {
            // Process expression fields directly into the query
            if (array_key_exists($key, $this->_expr_fields)) {
                $db_fields[] = $value;
            } else {
                $db_fields[] = '?';
            }
        }

        return implode(', ', $db_fields);
    }

    /**
     * Optionally add to a chain
     * To avoid breaking long chain commands, calls the function only if the first parameter is truthy.
     * Use like:
     *  Car::where('id', 3)
     *    ->onlyif($only_enabled, function($q) {
     *          return $q->where('enabled', 1);
     *      });
     *    ->find_many();
     *
     * @param boolean $condition
     * @param callable $callback
     * @return static
     */
    public function onlyif(bool $condition, callable $callback): static
    {
        if ($condition) {
            return $callback($this);
        }

        return $this;
    }

    /**
     * Add a WHERE column = value clause to your query. Each time
     * this is called in the chain, an additional WHERE will be
     * added, and these will be ANDed together when the final query
     * is built.
     */
    public function where(string $column_name, mixed $value): static
    {
        if (is_null($value)) {
            return $this->where_null($column_name);
        }

        return $this->where_equal($column_name, $value);
    }

    /**
     * More explicitly named version of for the where() method.
     * Can be used if preferred.
     */
    public function where_equal(string $column_name, mixed $value): static
    {
        if (is_null($value)) {
            return $this->where_null($column_name);
        }

        return $this->_add_simple_where($column_name, '=', $value);
    }

    /**
     * Add a WHERE column != value clause to your query.
     * @param string $column_name
     * @param string|null $value
     */
    public function where_not_equal(string $column_name, mixed $value): static
    {
        if (is_null($value)) {
            return $this->where_not_null($column_name);
        }

        return $this->_add_simple_where($column_name, '!=', $value);
    }

    /**
     * Special method to query the table by its primary key
     */
    public function where_id_is(mixed $id): static
    {
        return $this->where($this->_get_id_column_name(), $id);
    }

    /**
     * Allows adding a WHERE clause that matches any of the conditions
     * specified in the array. Each element in the associative array will
     * be a different condition, where the key will be the column name.
     *
     * By default, an equal operator will be used against all columns, but
     * it can be overriden for any or every column using the second parameter.
     *
     * Each condition will be ORed together when added to the final query.
     */
    public function where_any_is(array $values, array|string $operator = '='): static
    {
        $data  = [];
        $query = ['(('];
        $first = true;
        foreach ($values as $value) {
            if ($first) {
                $first = false;
            } else {
                $query[] = ') OR (';
            }
            $firstsub = true;
            foreach ($value as $key => $item) {
                $op = is_string($operator) ? $operator : ($operator[$key] ?? '=');
                if ($firstsub) {
                    $firstsub = false;
                } else {
                    $query[] = 'AND';
                }
                $query[] = $this->_quote_identifier($key);
                if (is_array($item)) {
                    $placeholders = $this->_create_placeholders($item);
                    $data         = array_merge($data, $item);
                    if ($op === '=') {
                        $query[] = 'IN (' . $placeholders . ')';
                    } elseif ($op === '!=') {
                        $query[] = 'NOT IN (' . $placeholders . ')';
                    } else {
                        throw new \InvalidArgumentException('You only pass an array for = and !=.');
                    }
                } else {
                    if (is_null($item) && ($op === '=')) {
                        $query[] = 'IS NULL';
                    } elseif (is_null($item) && ($op === '!=')) {
                        $query[] = 'IS NOT NULL';
                    } else {
                        $query[] = $op . ' ?';
                        $data[]  = $item;
                    }
                }
            }
        }
        $query[] = '))';

        return $this->where_raw(implode(' ', $query), $data);
    }

    /**
     * Add a WHERE ... LIKE clause to your query.
     * @param string $column_name
     * @param string $value
     */
    public function where_like(string $column_name, mixed $value): static
    {
        return $this->_add_simple_where($column_name, 'LIKE', $value);
    }

    /**
     * Add where WHERE ... NOT LIKE clause to your query.
     * @param string $column_name
     * @param string $value
     */
    public function where_not_like(string $column_name, mixed $value): static
    {
        return $this->_add_simple_where($column_name, 'NOT LIKE', $value);
    }

    /**
     * Add a WHERE ... > clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gt(string $column_name, mixed $value): static
    {
        return $this->_add_simple_where($column_name, '>', $value);
    }

    /**
     * Add a WHERE ... < clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lt(string $column_name, mixed $value): static
    {
        return $this->_add_simple_where($column_name, '<', $value);
    }

    /**
     * Add a WHERE ... >= clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gte(string $column_name, mixed $value): static
    {
        return $this->_add_simple_where($column_name, '>=', $value);
    }

    /**
     * Add a WHERE ... <= clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lte(string $column_name, mixed $value): static
    {
        return $this->_add_simple_where($column_name, '<=', $value);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lt_or_null(string $column_name, mixed $value): static
    {
        return $this->where_raw('( ' . $this->_quote_identifier($column_name) . ' < ? OR ' . $this->_quote_identifier($column_name) . ' IS NULL )', $value);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lte_or_null(string $column_name, mixed $value): static
    {
        return $this->where_raw('( ' . $this->_quote_identifier($column_name) . ' <= ? OR ' . $this->_quote_identifier($column_name) . ' IS NULL )', $value);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gt_or_null(string $column_name, mixed $value): static
    {
        return $this->where_raw('( ' . $this->_quote_identifier($column_name) . ' > ? OR ' . $this->_quote_identifier($column_name) . ' IS NULL )', $value);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gte_or_null(string $column_name, mixed $value): static
    {
        return $this->where_raw('( ' . $this->_quote_identifier($column_name) . ' >= ? OR ' . $this->_quote_identifier($column_name) . ' IS NULL )', $value);
    }

    /**
     * Add a WHERE ... IN clause to your query
     * @param string[]|Orm\Wrapper $values
     */
    public function where_in(string $column_name, mixed $values): static
    {
        if (!$values) {
            return $this->_add_where('0');
        }
        $column_name = $this->_quote_identifier($column_name);

        if (is_a($values, \Granada\Orm\Wrapper::class)) {
            return $this->_add_where("{$column_name} IN ({$values->get_select_query()})");
        }

        $placeholders = $this->_create_placeholders($values);

        return $this->_add_where("{$column_name} IN ({$placeholders})", $values);
    }

    /**
     * Add a WHERE ... NOT IN clause to your query
     * @param string $column_name
     * @param string[]|Orm\Wrapper $values
     */
    public function where_not_in(string $column_name, mixed $values): static
    {
        if (!$values) {
            return $this;
        }

        $column_name = $this->_quote_identifier($column_name);

        if (is_a($values, \Granada\Orm\Wrapper::class)) {
            return $this->_add_where("{$column_name} NOT IN ({$values->get_select_query()})");
        }

        $placeholders = $this->_create_placeholders($values);

        return $this->_add_where("{$column_name} NOT IN ({$placeholders})", $values);
    }

    /**
     * Add a WHERE ... NOT IN OR ... IS NULL clause to your query
     * @param string $column_name
     * @param string[] $values
     */
    public function where_not_in_or_null(string $column_name, array $values): static
    {
        if (!$values) {
            return $this;
        }

        $column_name  = $this->_quote_identifier($column_name);
        $placeholders = $this->_create_placeholders($values);

        return $this->where_raw('( ' . $column_name . ' NOT IN (' . $placeholders . ') OR ' . $column_name . ' IS NULL )', $values);
    }

    /**
     * Add a WHERE column IS NULL clause to your query
     * @param string $column_name
     */
    public function where_null(string $column_name): static
    {
        $column_name = $this->_quote_identifier($column_name);

        return $this->_add_where("{$column_name} IS NULL");
    }

    /**
     * Add a WHERE column IS NOT NULL clause to your query
     * @param string $column_name
     */
    public function where_not_null(string $column_name): static
    {
        $column_name = $this->_quote_identifier($column_name);

        return $this->_add_where("{$column_name} IS NOT NULL");
    }

    /**
     * Add a raw WHERE clause to the query. The clause should
     * contain question mark placeholders, which will be bound
     * to the parameters supplied in the second argument.
     * @param string $clause
     */
    public function where_raw(string $clause, mixed $parameters = []): static
    {
        return $this->_add_where($clause, $parameters);
    }

    /**
     * Add a LIMIT to the query
     */
    public function limit(?int $limit): static
    {
        $this->_limit = $limit;

        return $this;
    }

    /**
     * Add an OFFSET to the query
     */
    public function offset(?int $offset): static
    {
        $this->_offset = $offset;

        return $this;
    }

    /**
     * Add an ORDER BY clause to the query
     * @param string $ordering
     */
    protected function _add_order_by(string $column_name, string $ordering): static
    {
        $column_name       = $this->_quote_identifier($column_name);
        $this->_order_by[] = "{$column_name} {$ordering}";

        return $this;
    }

    /**
     * Clear all ORDER BY clauses, used to override previous orders
     */
    public function order_by_clear(): static
    {
        $this->_order_by = [];

        return $this;
    }

    /**
     * Add an ORDER BY column DESC clause
     * @param string $column_name
     */
    public function order_by_desc(string $column_name): static
    {
        return $this->_add_order_by($column_name, 'DESC');
    }

    /**
     * Add an ORDER BY column ASC clause
     * @param string $column_name
     */
    public function order_by_asc(string $column_name): static
    {
        return $this->_add_order_by($column_name, 'ASC');
    }

    /**
     * Add an unquoted expression as an ORDER BY clause
     * @param string $clause
     */
    public function order_by_expr(string $clause): static
    {
        $this->_order_by[] = $clause;

        return $this;
    }

    /**
     * Add a column to the list of columns to GROUP BY
     * @param string $column_name
     */
    public function group_by(string $column_name): static
    {
        $column_name       = $this->_quote_identifier($column_name);
        $this->_group_by[] = $column_name;

        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns to GROUP BY
     * @param string $expr
     */
    public function group_by_expr(string $expr): static
    {
        $this->_group_by[] = $expr;

        return $this;
    }

    /**
     * Add a HAVING column = value clause to your query. Each time
     * this is called in the chain, an additional HAVING will be
     * added, and these will be ANDed together when the final query
     * is built.
     */
    public function having(string $column_name, mixed $value): static
    {
        return $this->having_equal($column_name, $value);
    }

    /**
     * More explicitly named version of for the having() method.
     * Can be used if preferred.
     */
    public function having_equal(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, '=', $value);
    }

    /**
     * Add a HAVING column != value clause to your query.
     * @param string $column_name
     * @param string $value
     */
    public function having_not_equal(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, '!=', $value);
    }

    /**
     * Special method to query the table by its primary key
     */
    public function having_id_is(mixed $id): static
    {
        return $this->having($this->_get_id_column_name(), $id);
    }

    /**
     * Add a HAVING ... LIKE clause to your query.
     * @param string $column_name
     * @param string $value
     */
    public function having_like(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, 'LIKE', $value);
    }

    /**
     * Add where HAVING ... NOT LIKE clause to your query.
     * @param string $column_name
     * @param string $value
     */
    public function having_not_like(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, 'NOT LIKE', $value);
    }

    /**
     * Add a HAVING ... > clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function having_gt(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, '>', $value);
    }

    /**
     * Add a HAVING ... < clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function having_lt(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, '<', $value);
    }

    /**
     * Add a HAVING ... >= clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function having_gte(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, '>=', $value);
    }

    /**
     * Add a HAVING ... <= clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function having_lte(string $column_name, mixed $value): static
    {
        return $this->_add_simple_having($column_name, '<=', $value);
    }

    /**
     * Add a HAVING ... IN clause to your query
     * @param string $column_name
     * @param string[] $values
     */
    public function having_in(string $column_name, array $values): static
    {
        $column_name  = $this->_quote_identifier($column_name);
        $placeholders = $this->_create_placeholders($values);

        return $this->_add_having("{$column_name} IN ({$placeholders})", $values);
    }

    /**
     * Add a HAVING ... NOT IN clause to your query
     * @param string $column_name
     * @param string[] $values
     */
    public function having_not_in(string $column_name, array $values): static
    {
        $column_name  = $this->_quote_identifier($column_name);
        $placeholders = $this->_create_placeholders($values);

        return $this->_add_having("{$column_name} NOT IN ({$placeholders})", $values);
    }

    /**
     * Add a HAVING column IS NULL clause to your query
     * @param string $column_name
     */
    public function having_null(string $column_name): static
    {
        $column_name = $this->_quote_identifier($column_name);

        return $this->_add_having("{$column_name} IS NULL");
    }

    /**
     * Add a HAVING column IS NOT NULL clause to your query
     * @param string $column_name
     */
    public function having_not_null(string $column_name): static
    {
        $column_name = $this->_quote_identifier($column_name);

        return $this->_add_having("{$column_name} IS NOT NULL");
    }

    /**
     * Add a raw HAVING clause to the query. The clause should
     * contain question mark placeholders, which will be bound
     * to the parameters supplied in the second argument.
     * @param string $clause
     */
    public function having_raw(string $clause, array $parameters = []): static
    {
        return $this->_add_having($clause, $parameters);
    }

    /**
     * Build a SELECT statement based on the clauses that have
     * been passed to this instance by chaining method calls.
     */
    protected function _build_select(): string
    {
        // If the query is raw, just set the $this->_values to be
        // the raw query parameters and return the raw query
        if ($this->_is_raw_query) {
            $this->_values = $this->_raw_parameters;

            return $this->_raw_query;
        }

        // Build and return the full SELECT statement by concatenating
        // the results of calling each separate builder method.
        return $this->_join_if_not_empty(' ', [
            $this->_build_select_start(),
            $this->_build_join(),
            $this->_build_where(),
            $this->_build_group_by(),
            $this->_build_having(),
            $this->_build_order_by(),
            $this->_build_limit(),
            $this->_build_offset(),
        ]);
    }

    /**
     * Used to perform unit tests
     * Note must call _build_select_start() for this to be populated
     */
    public function testValues(): array
    {
        return $this->_values;
    }

    /**
     * Build the start of the SELECT statement
     */
    protected function _build_select_start(): string
    {
        $fragment       = 'SELECT ';
        $result_columns = implode(', ', $this->_result_columns);

        if (
            !is_null($this->_limit)
            && self::$_config[$this->_connection_name]['limit_clause_style'] === ORM::LIMIT_STYLE_TOP_N
        ) {
            $fragment .= "TOP {$this->_limit} ";
        }

        if ($this->_distinct) {
            $result_columns = 'DISTINCT ' . $result_columns;
        }

        $fragment .= "{$result_columns} FROM " . $this->_quote_identifier($this->_table_name);

        if (!is_null($this->_table_alias)) {
            $fragment .= ' ' . $this->_quote_identifier($this->_table_alias);
        }

        return $fragment;
    }

    /**
     * Build the JOIN sources
     */
    protected function _build_join(): string
    {
        if (count($this->_join_sources) === 0) {
            return '';
        }

        return implode(' ', $this->_join_sources);
    }

    /**
     * Build the WHERE clause(s)
     */
    protected function _build_where(): string
    {
        return $this->_build_conditions('where');
    }

    /**
     * Build the HAVING clause(s)
     */
    protected function _build_having(): string
    {
        return $this->_build_conditions('having');
    }

    /**
     * Build GROUP BY
     */
    protected function _build_group_by(): string
    {
        if (count($this->_group_by) === 0) {
            return '';
        }

        return 'GROUP BY ' . implode(', ', $this->_group_by);
    }

    /**
     * Build a WHERE or HAVING clause
     * @param string $type
     * @return string
     */
    protected function _build_conditions(string $type): string
    {
        $conditions_class_property_name = "_{$type}_conditions";
        // If there are no clauses, return empty string
        if (count($this->$conditions_class_property_name) === 0) {
            return '';
        }

        $conditions = [];
        foreach ($this->$conditions_class_property_name as $condition) {
            $conditions[]  = $condition[self::CONDITION_FRAGMENT];
            $this->_values = array_merge($this->_values, $condition[self::CONDITION_VALUES]);
        }

        return strtoupper($type) . ' ' . implode(' AND ', $conditions);
    }

    /**
     * Build ORDER BY
     */
    protected function _build_order_by(): string
    {
        if (count($this->_order_by) === 0) {
            return '';
        }

        return 'ORDER BY ' . implode(', ', $this->_order_by);
    }

    /**
     * Build LIMIT
     */
    protected function _build_limit(): string
    {
        if (
            is_null($this->_limit)
            || self::$_config[$this->_connection_name]['limit_clause_style'] !== ORM::LIMIT_STYLE_LIMIT
        ) {
            return '';
        }

        if (self::$_db[$this->_connection_name]->getAttribute(PDO::ATTR_DRIVER_NAME) === 'firebird') {
            $limiter = 'ROWS';
        } else {
            $limiter = 'LIMIT';
        }

        return "{$limiter} {$this->_limit}";
    }

    /**
     * Build OFFSET
     */
    protected function _build_offset(): string
    {
        if (!is_null($this->_offset)) {
            $clause = 'OFFSET';
            if (self::$_db[$this->_connection_name]->getAttribute(PDO::ATTR_DRIVER_NAME) === 'firebird') {
                $clause = 'TO';
            }

            return $clause . ' ' . $this->_offset;
        }

        return '';
    }

    /**
     * Wrapper around PHP's join function which
     * only adds the pieces if they are not empty.
     * @param string $glue
     * @return string
     */
    protected function _join_if_not_empty(string $glue, array $pieces): string
    {
        $filtered_pieces = [];
        foreach ($pieces as $piece) {
            if (is_string($piece)) {
                $piece = trim($piece);
            }
            if (!empty($piece)) {
                $filtered_pieces[] = $piece;
            }
        }

        return implode($glue, $filtered_pieces);
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     */
    protected function _quote_identifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        $parts = array_map($this->_quote_identifier_part(...), $parts);

        return implode('.', $parts);
    }

    /**
     * This method performs the actual quoting of a single
     * part of an identifier, using the identifier quote
     * character specified in the config (or autodetected).
     */
    protected function _quote_identifier_part(string $part): string
    {
        if ($part === '*') {
            return $part;
        }

        $quote_character = self::$_config[$this->_connection_name]['identifier_quote_character'];

        // double up any identifier quotes to escape them
        return $quote_character
            . str_replace(
                $quote_character,
                $quote_character . $quote_character,
                $part
            ) . $quote_character;
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
     */
    protected static function _check_query_cache(string $cache_key, string $connection_name = self::DEFAULT_CONNECTION): mixed
    {
        if (isset(self::$_query_cache[$connection_name][$cache_key])) {
            return self::$_query_cache[$connection_name][$cache_key];
        }

        return false;
    }

    /**
     * Clear the query cache
     */
    public static function clear_cache(): void
    {
        self::$_query_cache = [];
    }

    /**
     * Add the given value to the query cache.
     * @param string $cache_key
     */
    protected static function _cache_query_result(string $cache_key, mixed $value, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        if (!isset(self::$_query_cache[$connection_name])) {
            self::$_query_cache[$connection_name] = [];
        }
        self::$_query_cache[$connection_name][$cache_key] = $value;
    }

    /**
     * Execute the SELECT query that has been built up by chaining methods
     * on this class. Return an array of rows as associative arrays.
     */
    protected function _run(): array
    {
        $query           = $this->_build_select();
        $caching_enabled = self::$_config[$this->_connection_name]['caching'];

        $cache_key = '';
        if ($caching_enabled) {
            $cache_key     = self::_create_cache_key($query, $this->_values);
            $cached_result = self::_check_query_cache($cache_key, $this->_connection_name);

            if ($cached_result !== false) {
                $this->reset();

                return $cached_result;
            }
        }

        self::_execute($query, $this->_values, $this->_connection_name);
        $statement = self::get_last_statement();

        $rows = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        if ($caching_enabled) {
            self::_cache_query_result($cache_key, $rows, $this->_connection_name);
        }

        // reset Idiorm after executing the query
        $this->reset();

        return $rows;
    }

    /**
     * reset Idiorm after executing the query
     */
    protected function reset(): void
    {
        $this->_values                       = [];
        $this->_result_columns               = ['*'];
        $this->_using_default_result_columns = true;
    }

    /**
     * Return the raw data wrapped by this ORM
     * instance as an associative array. Column
     * names may optionally be supplied as arguments,
     * if so, only those keys will be returned.
     */
    public function as_array(...$args): array
    {
        if (count($args) === 0) {
            return $this->_data;
        }

        return array_intersect_key($this->_data, array_flip($args));
    }

    /**
     * Return the value of a property of this object (database row)
     * or null if not present.
     */
    public function get(string $key): mixed
    {
        return $this->_data[$key] ?? null;
    }

    /**
     * Return the name of the column in the database table which contains
     * the primary key ID of the row.
     */
    protected function _get_id_column_name(): string
    {
        if (!is_null($this->_instance_id_column)) {
            return $this->_instance_id_column;
        }
        if (isset(self::$_config[$this->_connection_name]['id_column_overrides'][$this->_table_name])) {
            return self::$_config[$this->_connection_name]['id_column_overrides'][$this->_table_name];
        }

        return self::$_config[$this->_connection_name]['id_column'];
    }

    /**
     * Get the primary key ID of this object.
     */
    public function id(): mixed
    {
        return $this->get($this->_get_id_column_name());
    }

    /**
     * Set a property to a particular value on this object.
     * To set multiple properties at once, pass an associative array
     * as the first parameter and leave out the second parameter.
     * Flags the properties as 'dirty' so they will be saved to the
     * database when save() is called.
     */
    public function set(array|string $key, mixed $value = null): static
    {
        return $this->_set_orm_property($key, $value);
    }

    /**
     * Set a property to a particular value on this object.
     * To set multiple properties at once, pass an associative array
     * as the first parameter and leave out the second parameter.
     * Flags the properties as 'dirty' so they will be saved to the
     * database when save() is called.
     * @param string|array $key
     * @param string|null $value
     */
    public function set_expr(array|string $key, mixed $value = null)
    {
        return $this->_set_orm_property($key, $value, true);
    }

    /**
     * Set a property on the ORM object.
     * @param string|array $key
     * @param string|null $value
     */
    protected function _set_orm_property(array|string $key, mixed $value = null, bool $expr = false): static
    {
        if (!is_array($key)) {
            $key = [$key => $value];
        }
        foreach ($key as $field => $value) {
            if ($field === '_isFirstResult') {
                continue;
            }
            if ($field === '_isLastResult') {
                continue;
            }
            if (!array_key_exists($field, $this->_clean_data) && array_key_exists($field, $this->_data)) {
                // Save the data the first time only
                $this->_clean_data[$field] = $this->_data[$field];
            }
            $oldval              = array_key_exists($field, $this->_data) ? $this->_data[$field] : null;
            $this->_data[$field] = $value;
            $set_as_dirty        = $this->is_new() || $expr;
            if (is_float($value)) {
                $set_as_dirty = abs($oldval - $value) > 0.000_000_000_000_01;
            } elseif (is_string($oldval)) {
                if ($oldval !== $value) {
                    $set_as_dirty = true;
                }
            } elseif ($oldval != $value) {
                $set_as_dirty = true;
            }
            if ($set_as_dirty) {
                $this->_dirty_fields[$field] = $value;
            }
            if (false === $expr && isset($this->_expr_fields[$field])) {
                unset($this->_expr_fields[$field]);
            } elseif (true === $expr) {
                $this->_expr_fields[$field] = true;
            }
        }

        return $this;
    }

    /**
     * Check whether the given field has been changed since this
     * object was saved.
     * @param string $key
     * @return bool
     */
    public function is_dirty(string $key): bool
    {
        return array_key_exists($key, $this->_dirty_fields);
    }

    /**
     * Check whether the any field has been changed since this
     * object was saved.
     * @return bool
     */
    public function is_any_dirty(): bool
    {
        return count($this->_dirty_fields) > 0;
    }

    /**
     * List the dirty fields that need updating on next save
     * @return array
     */
    public function list_dirty_fields(): array
    {
        return $this->_dirty_fields;
    }

    /**
     * Get the clean data for this record before it was made dirty
     * @return array
     */
    public function clean_values(): array
    {
        return array_merge($this->_data, $this->_clean_data);
    }

    /**
     * Get the value of this field when the data was last hydrated
     * ie before it became dirty
     * @return mixed
     */
    public function clean_value(string $key): mixed
    {
        if (array_key_exists($key, $this->_clean_data)) {
            return $this->_clean_data[$key];
        }
        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }

        return null;
    }

    /**
     * Check whether the model was the result of a call to create() or not
     * @return bool
     */
    public function is_new(): bool
    {
        return $this->_is_new;
    }

    /**
     * Save any fields which have been modified on this object
     * to the database.
     * Added: on duplicate key update, only for mysql
     * If you want to insert a record, or update it if any of the unique keys already exists on db
     */
    public function save(bool $ignore = false)
    {
        $query = [];

        // Fix if id field is blank but not null
        if (!($this->id() && array_key_exists($this->_get_id_column_name(), $this->_dirty_fields))) {
            unset($this->_dirty_fields[$this->_get_id_column_name()]);
        }

        // remove any expression fields as they are already baked into the query
        $values = array_values(array_diff_key($this->_dirty_fields, $this->_expr_fields));

        if ($ignore) {
            $query  = $this->_build_insert_update();
            $values = array_merge($values, $values);
        } else {
            if (!$this->_is_new) { // UPDATE
                // If there are no dirty values, do nothing
                if (empty($values) && empty($this->_expr_fields)) {
                    return true;
                }
                $query    = $this->_build_update();
                $values[] = $this->id();
            } else { // INSERT
                $query = $this->_build_insert();
            }
        }

        $success = self::_execute($query, $values, $this->_connection_name);

        // If we've just inserted a new record, set the ID of this object
        if ($this->_is_new) {
            $this->_is_new = false;
            if (!($this->id())) {
                if (self::$_db[$this->_connection_name]->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                    $this->_data[$this->_get_id_column_name()] = self::get_last_statement()->fetchColumn();
                } else {
                    $this->_data[$this->_get_id_column_name()] = self::$_db[$this->_connection_name]->lastInsertId();
                }
            }
        }
        $this->clear_cache();
        $this->_expr_fields  = [];
        $this->_dirty_fields = [];
        $this->_clean_data   = [];

        return $success;
    }

    /**
     * Build an UPDATE query
     */
    protected function _build_update(): string
    {
        $query   = [];
        $query[] = "UPDATE {$this->_quote_identifier($this->_table_name)} SET";

        $field_list = [];
        foreach ($this->_dirty_fields as $key => $value) {
            if (!array_key_exists($key, $this->_expr_fields)) {
                $value = '?';
            }
            $field_list[] = "{$this->_quote_identifier($key)} = {$value}";
        }
        $query[] = implode(', ', $field_list);
        $query[] = 'WHERE';
        $query[] = $this->_quote_identifier($this->_get_id_column_name());
        $query[] = '= ?';

        return implode(' ', $query);
    }

    /**
     * Build an INSERT query
     */
    protected function _build_insert(): string
    {
        $query      = [];
        $query[]    = 'INSERT INTO';
        $query[]    = $this->_quote_identifier($this->_table_name);
        $field_list = array_map($this->_quote_identifier(...), array_keys($this->_dirty_fields));
        $query[]    = '(' . implode(', ', $field_list) . ')';
        $query[]    = 'VALUES';

        $placeholders = $this->_create_placeholders($this->_dirty_fields);
        $query[]      = "({$placeholders})";

        if (self::$_db[$this->_connection_name]->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $query[] = 'RETURNING ' . $this->_quote_identifier($this->_get_id_column_name());
        }

        return implode(' ', $query);
    }

    /**
     * Added: Build an INSERT ON DUPLICATE KEY UPDATE query
     * Attention: This method only works on Mysql Databases
     */
    protected function _build_insert_update(): string
    {
        $query        = [];
        $query[]      = 'INSERT INTO';
        $query[]      = $this->_quote_identifier($this->_table_name);
        $field_list   = array_map($this->_quote_identifier(...), array_keys($this->_dirty_fields));
        $query[]      = '(' . implode(', ', $field_list) . ')';
        $query[]      = 'VALUES';
        $placeholders = $this->_create_placeholders($this->_dirty_fields);
        $query[]      = "({$placeholders})";

        $query[] = ' ON DUPLICATE KEY UPDATE ';
        $query[] = implode(' = ?, ', $field_list) . ' = ? ';

        return implode(' ', $query);
    }

    /**
     * Delete this record from the database
     */
    public function delete(): ?bool
    {
        $query = implode(' ', [
            'DELETE FROM',
            $this->_quote_identifier($this->_table_name),
            'WHERE',
            $this->_quote_identifier($this->_get_id_column_name()),
            '= ?',
        ]);

        return self::_execute($query, [$this->id()], $this->_connection_name);
    }

    /**
     * Delete many records from the database
     * Added: could delete many of a join query, if you define $join to true
     * and the table where you want to delete the records
     */
    public function delete_many(bool $join = false, mixed $table = false): ?bool
    {
        if ($join) {
            // Build and return the full DELETE statement by concatenating
            // the results of calling each separate builder method.
            $query = $this->_join_if_not_empty(' ', [
                "DELETE {$table} FROM",
                $this->_quote_identifier($this->_table_name),
                $this->_build_join(),
                $this->_build_where(),
            ]);
        } else {
            // Build and return the full DELETE statement by concatenating
            // the results of calling each separate builder method.
            $query = $this->_join_if_not_empty(' ', [
                'DELETE FROM',
                $this->_quote_identifier($this->_table_name),
                $this->_build_where(),
            ]);
        }

        $result = self::_execute($query, $this->_values, $this->_connection_name);
        \Granada\LazyItemCache::clear();

        return $result;
    }

    // --------------------- //
    // ---  ArrayAccess  --- //
    // --------------------- //
    public function offsetExists(mixed $key): bool
    {
        return isset($this->_data[$key]);
    }

    public function offsetGet(mixed $key): mixed
    {
        return $this->get($key);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        if (is_null($key)) {
            throw new \InvalidArgumentException('You must specify a key/array index.');
        }
        $this->set($key, $value);
    }

    public function offsetUnset(mixed $key): void
    {
        unset($this->_data[$key]);
        unset($this->_dirty_fields[$key]);
    }

    // --------------------- //
    // --- MAGIC METHODS --- //
    // --------------------- //
    public function __get(mixed $key): mixed
    {
        return $this->offsetGet($key);
    }

    public function __set(mixed $key, mixed $value): void
    {
        $this->offsetSet($key, $value);
    }

    public function __unset(mixed $key): void
    {
        $this->offsetUnset($key);
    }

    public function __isset(mixed $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Magic method to capture calls to undefined class methods.
     * In this case we are attempting to convert camel case formatted
     * methods into underscore formatted methods.
     *
     * This allows us to call ORM methods using camel case and remain
     * backwards compatible.
     *
     * @param  string   $name
     * @param  array    $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        $method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        // return call_user_func_array(array($this, $method), $arguments);

        if (method_exists($this, $method)) {
            return $this->$method(...$arguments);
        }

        throw new \BadMethodCallException("Method {$name} does not exist");
    }

    /**
     * Magic method to capture calls to undefined static class methods.
     * In this case we are attempting to convert camel case formatted
     * methods into underscore formatted methods.
     *
     * This allows us to call ORM methods using camel case and remain
     * backwards compatible.
     *
     * @param  string   $name
     * @param  array    $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

        return call_user_func_array([static::class, $method], $arguments);
    }
}
