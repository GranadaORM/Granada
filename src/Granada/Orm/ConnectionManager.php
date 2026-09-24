<?php

namespace Granada\Orm;

use Granada\ORM;
use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * Owns every connection: per name the validated settings, the PDO
 * handle (connected lazily on first use), the driver facts detected
 * from that handle (driver name, quote character, limit style), the
 * query log and the query cache. The last statement and last query
 * span all its connections. One instance stands behind the ORM's
 * static calls.
 */
class ConnectionManager
{
    private const DEFAULT_CONNECTION = ORM::DEFAULT_CONNECTION;

    /** The documented settings and their defaults; setting anything else throws. */
    private const SETTINGS = [
        'connection_string'           => 'sqlite::memory:',
        'id_column'                   => 'id',
        'id_column_overrides'         => [],
        'error_mode'                  => PDO::ERRMODE_EXCEPTION,
        'username'                    => null,
        'password'                    => null,
        'driver_options'              => null,
        'identifier_quote_character'  => null, // if this is null, will be autodetected
        'limit_clause_style'          => null, // if this is null, will be autodetected
        'driver_name'                 => null, // if this is null, will be autodetected
        'logging'                     => false,
        'logger'                      => null,
        'caching'                     => false,
        'return_result_sets'          => true,
        'find_many_primary_id_as_key' => true,
    ];

    /** @var array<string, array<string, mixed>> */
    private array $settings = [];

    // Driver facts detected from a handle, kept apart from the settings
    // so they can be re-detected when the handle is dropped
    /** @var array<string, array<string, mixed>> */
    private array $facts = [];

    /** @var array<string, PDO|null> */
    private array $db = [];

    // Log of all queries run, mapped by connection name, only populated if logging is enabled
    /** @var array<string, string[]> */
    private array $query_log = [];

    // Query cache, keyed by connection then cache key
    /** @var array<string, array<string, mixed>> */
    private array $query_cache = [];

    // Shared immutable Dialect instances, keyed by the config values that build them
    /** @var array<string, Dialect> */
    private array $dialect_cache = [];

    // Reference to previously used PDOStatement object to enable low-level access, if needed
    private ?PDOStatement $last_statement = null;

    // Last query run on any connection, only populated if logging is enabled
    private ?string $last_query = null;

    /**
     * Accepts the configure() shortcut forms, applied to the default
     * connection: new ConnectionManager('sqlite::memory:').
     * @param array|string|null $settings
     */
    public function __construct(null|array|string $settings = null)
    {
        if ($settings !== null) {
            $this->configure($settings);
        }
    }

    /**
     * The operation ORM::configure forwards to: pass configuration
     * settings in the form of key/value pairs. As a shortcut, if the
     * second argument is omitted and the key is a string, the setting
     * is assumed to be the connection string; an array is a batch of
     * settings.
     * @param array|string $key
     * @param string $connection_name Which connection to use
     */
    public function configure(array|string $key, mixed $value = null, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        if (is_array($key)) {
            foreach ($key as $conf_key => $conf_value) {
                $this->configure($conf_key, $conf_value, $connection_name);
            }

            return;
        }

        if (is_null($value)) {
            // Shortcut: If only one string argument is passed,
            // assume it's a connection string
            $value = $key;
            $key   = 'connection_string';
        }

        if (!array_key_exists($key, self::SETTINGS)) {
            throw new InvalidArgumentException("Unknown setting '{$key}' for connection '{$connection_name}'");
        }

        $this->config($connection_name); // ensures at least default settings are set
        $this->settings[$connection_name][$key] = $value;

        if ($key === 'connection_string') {
            $this->setup_default_driver_options($connection_name);
        }
    }

    /**
     * Retrieve configuration options by key, or as whole array. Values
     * detected from the handle are merged in.
     * @param string $connection_name Which connection to use
     */
    public function get_config(?string $key = null, string $connection_name = self::DEFAULT_CONNECTION): mixed
    {
        if ($key) {
            return $this->config($connection_name)[$key];
        }

        return $this->config($connection_name);
    }

    /**
     * Set the PDO object used to communicate with the database, taking
     * it ready-made instead of connecting from the settings. Passing
     * null drops the handle; the next use reconnects from the settings
     * and re-detects the driver facts.
     * @param string $connection_name Which connection to use
     */
    public function set_db(?PDO $db, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        $this->config($connection_name);
        $this->db[$connection_name] = $db;

        if ($db === null) {
            $this->facts[$connection_name] = [];

            return;
        }

        $this->setup_driver_name($connection_name);
        $this->setup_identifier_quote_character($connection_name);
        $this->setup_limit_clause_style($connection_name);
    }

    /**
     * Returns the PDO instance for a connection, connecting from the
     * settings if no live handle is held.
     * @param string $connection_name Which connection to use
     */
    public function get_db(string $connection_name = self::DEFAULT_CONNECTION): PDO
    {
        if ($this->has_db($connection_name)) {
            return $this->db[$connection_name];
        }

        $settings = $this->config($connection_name);
        $db       = new PDO(
            $settings['connection_string'],
            $settings['username'],
            $settings['password'],
            is_array($settings['driver_options']) ? $settings['driver_options'] : null
        );

        $db->setAttribute(PDO::ATTR_ERRMODE, $settings['error_mode']);
        $this->set_db($db, $connection_name);

        return $db;
    }

    /**
     * Whether a live handle is held for the connection.
     * @param string $connection_name Which connection to use
     */
    public function has_db(string $connection_name = self::DEFAULT_CONNECTION): bool
    {
        return ($this->db[$connection_name] ?? null) instanceof PDO;
    }

    /**
     * Reset every connection's settings to their defaults, forgetting
     * anything detected from a handle.
     */
    public function reset_config(): void
    {
        $this->settings      = [];
        $this->facts         = [];
        $this->dialect_cache = [];
    }

    /**
     * Close and delete all registered PDO objects.
     */
    public function reset_db(): void
    {
        $this->db = [];
    }

    /**
     * Clear the query cache for every connection.
     */
    public function clear_cache(): void
    {
        $this->query_cache = [];
    }

    /**
     * The Dialect for a connection: every driver-specific fact the
     * renderer, save() and Wrapper need. Falls back to the mysql/sqlite
     * dialect when the driver name is unknown or not yet detected.
     * @param string $connection_name Which connection to use
     */
    public function dialect(string $connection_name = self::DEFAULT_CONNECTION): Dialect
    {
        $config             = $this->config($connection_name);
        $driver_name        = $config['driver_name'];
        $quote_character    = $config['identifier_quote_character'];
        $limit_clause_style = $config['limit_clause_style'];

        $key = ($driver_name ?? '') . '|' . ($quote_character ?? '') . '|' . ($limit_clause_style ?? '');

        return $this->dialect_cache[$key] ??= Dialect::forDriver($driver_name, $quote_character, $limit_clause_style);
    }

    /**
     * Executes a query as a wrapper for PDOStatement::execute. Logs the
     * query and stores the statement object, accessible publicly through
     * get_last_statement().
     * @param string[] $parameters An array of parameters to be bound in to the query
     * @param string $connection_name Which connection to use
     * @return bool Response of PDOStatement::execute()
     */
    public function execute(string $query, array $parameters = [], string $connection_name = self::DEFAULT_CONNECTION): ?bool
    {
        $this->log_query($query, $parameters, $connection_name);
        $statement = $this->get_db($connection_name)->prepare($query);

        $this->last_statement = $statement;

        return $statement->execute($parameters);
    }

    /**
     * Store an interpolated query in the query log and invoke the
     * configured logger, if logging is enabled for the connection.
     * @param string $connection_name Which connection to use
     */
    public function record_query(string $bound_query, string $connection_name): void
    {
        if (!$this->config($connection_name)['logging']) {
            return;
        }

        $this->last_query                    = $bound_query;
        $this->query_log[$connection_name][] = $bound_query;

        $logger = $this->config($connection_name)['logger'];
        if (is_callable($logger)) {
            $logger($bound_query);
        }
    }

    /**
     * Get the last query executed. Returns the last query from all
     * connections if no connection_name is specified, '' if the named
     * connection has logged nothing.
     * @param null|string $connection_name Which connection to use
     */
    public function get_last_query(?string $connection_name = null): ?string
    {
        if ($connection_name === null) {
            return $this->last_query;
        }
        if (!isset($this->query_log[$connection_name])) {
            return '';
        }

        return (string) end($this->query_log[$connection_name]);
    }

    /**
     * Get an array containing all the queries run on a
     * specified connection up to now.
     * @param string $connection_name Which connection to use
     * @return string[]
     */
    public function get_query_log(string $connection_name = self::DEFAULT_CONNECTION): array
    {
        return $this->query_log[$connection_name] ?? [];
    }

    /**
     * Returns the PDOStatement instance last used by any connection
     * the manager holds. Useful for access to PDOStatement::rowCount()
     * or error information.
     */
    public function get_last_statement(): ?PDOStatement
    {
        return $this->last_statement;
    }

    /**
     * Get a list of the available connection names.
     * @return string[]
     */
    public function get_connection_names(): array
    {
        return array_keys($this->db);
    }

    /**
     * Check the query cache for the given cache key. If a value
     * is cached for the key, return the value. Otherwise, return false.
     * @param string $connection_name Which connection to use
     */
    public function check_query_cache(string $cache_key, string $connection_name = self::DEFAULT_CONNECTION): mixed
    {
        return $this->query_cache[$connection_name][$cache_key] ?? false;
    }

    /**
     * Add the given value to the query cache.
     * @param string $connection_name Which connection to use
     */
    public function cache_query_result(string $cache_key, mixed $value, string $connection_name = self::DEFAULT_CONNECTION): void
    {
        $this->query_cache[$connection_name][$cache_key] = $value;
    }

    /**
     * Detect and initialise the limit clause style ("SELECT TOP 5" /
     * "... LIMIT 5"). If this has been specified manually using
     * configure('limit_clause_style', 'top'), this will do nothing.
     * @param string $connection_name Which connection to use
     */
    public function setup_limit_clause_style(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        if ($this->config($connection_name)['limit_clause_style'] !== null) {
            return;
        }

        $this->facts[$connection_name]['limit_clause_style'] = $this->dialect($connection_name)->limit_clause_style;
    }

    /**
     * The connection's settings with detected values merged in; a
     * connection not yet configured starts at SETTINGS.
     *
     * @return array{
     *     connection_string: string,
     *     id_column: string,
     *     id_column_overrides: array<string, string>,
     *     error_mode: int,
     *     username: string|null,
     *     password: string|null,
     *     driver_options: array<int, mixed>|null,
     *     identifier_quote_character: string|null,
     *     limit_clause_style: string|null,
     *     driver_name: string|null,
     *     logging: bool,
     *     logger: callable|null,
     *     caching: bool,
     *     return_result_sets: bool,
     *     find_many_primary_id_as_key: bool,
     * }
     */
    private function config(string $connection_name): array
    {
        if (!isset($this->settings[$connection_name])) {
            $this->settings[$connection_name] = self::SETTINGS;
        }

        return array_merge($this->settings[$connection_name], $this->facts[$connection_name] ?? []);
    }

    /**
     * Detect and initialise the character used to quote identifiers
     * (table names, column names etc), unless specified manually.
     * @param string $connection_name Which connection to use
     */
    private function setup_identifier_quote_character(string $connection_name): void
    {
        if ($this->config($connection_name)['identifier_quote_character'] !== null) {
            return;
        }

        $this->facts[$connection_name]['identifier_quote_character'] = $this->dialect($connection_name)->quote_character;
    }

    /**
     * Detect and initialise the name of the PDO driver backing the
     * connection, if this has not been specified manually.
     * @param string $connection_name Which connection to use
     */
    private function setup_driver_name(string $connection_name): void
    {
        if ($this->config($connection_name)['driver_name'] !== null) {
            return;
        }

        $this->facts[$connection_name]['driver_name'] = $this->get_db($connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * MySQL connections default to SSL without server certificate
     * verification: encrypted, but the server is not authenticated.
     * @param string $connection_name Which connection to use
     */
    private function setup_default_driver_options(string $connection_name = self::DEFAULT_CONNECTION): void
    {
        if ($this->config($connection_name)['driver_options']) {
            return;
        }

        if (str_starts_with($this->config($connection_name)['connection_string'], 'mysql:')) {
            $this->settings[$connection_name]['driver_options'] = [
                PDO::MYSQL_ATTR_SSL_CA                 => true,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ];
        }
    }

    /**
     * Add a query to the query log. Only works if the
     * 'logging' config option is set to true.
     *
     * This works by manually binding the parameters to the query - the
     * query isn't executed like this (PDO normally passes the query and
     * parameters to the database which takes care of the binding) but
     * doing it this way makes the logged queries more readable.
     * @param string[] $parameters An array of parameters to be bound in to the query
     * @param string $connection_name Which connection to use
     */
    private function log_query(string $query, array $parameters, string $connection_name): bool
    {
        if (!$this->config($connection_name)['logging']) {
            return false;
        }

        $this->record_query(
            Renderer::interpolate(
                $query,
                $parameters,
                fn($parameter) => $this->get_db($connection_name)->quote($parameter)
            ),
            $connection_name
        );

        return true;
    }
}
