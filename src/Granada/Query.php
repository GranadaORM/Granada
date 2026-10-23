<?php

namespace Granada;

use Closure;
use Granada\Orm\BulkUpdateSpec;
use Granada\Orm\Renderer;
use Granada\Orm\RowHydrator;
use Granada\Orm\Term;
use PDO;

/**
 * The query. Holds the where/join/group/order conditions, the
 * where_* and order_by_* suffix magic, the camelCase aliases, the
 * aggregates, and the finds. It holds no row data and performs no
 * row writes.
 */
class Query extends Orm\Db
{
    /**
     * The class finds hydrate rows into. Null for a model-less
     * query, whose finds return ORM rows.
     */
    protected ?string $_class_name = null;

    /**
     * The write rules of this query's model.
     */
    public Orm\WriteRules $_write_rules;

    /**
     * The relationship this query was built from,
     * when a relationship method built it.
     */
    public ?Relationship $_relationship = null;

    /**
     * The relationships marked for eager loading, as with() received them.
     * @var array<int, mixed>
     */
    public array $relationships = [];

    /**
     * The related counts and exists flags this query selects, as the
     * alias each is stored under: {relation}_count or {relation}_exists.
     * @var array<string, Orm\RelatedCount>
     */
    public array $related_counts = [];

    // Alias for the table to be used in SELECT queries
    protected ?string $_table_alias = null;

    // Values to be bound to the query
    /** @var array<int, mixed> */
    protected array $_values = [];

    // Columns to select in the result
    /** @var array<int, string|Orm\Aggregate> */
    protected array $_result_columns = ['*'];

    // Are we using the default result column or have these been manually changed?
    protected bool $_using_default_result_columns = true;

    // Join sources
    /** @var array<int, Orm\JoinSource|string> */
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
    /** @var array<int, Orm\Condition> */
    protected array $_where_conditions = [];

    // LIMIT
    protected ?int $_limit = null;

    // OFFSET
    protected ?int $_offset = null;

    // ORDER BY
    /** @var array<int, Orm\Term> */
    protected array $_order_by = [];

    // GROUP BY
    /** @var array<int, Orm\Term> */
    protected array $_group_by = [];

    // HAVING
    /** @var array<int, Orm\Condition> */
    protected array $_having_conditions = [];

    // name of the ResultSet Object
    public string $resultSetClass = 'Granada\ResultSet';

    // associative results flag
    protected bool $_associative_results = true;

    /**
     * @return array<string, array{factory: callable(string, mixed): ?Orm\Condition, timezone: bool}>
     */
    private static function _where_suffixes(): array
    {
        static $suffixes = [
            '_not_in_or_null' => [
                'timezone' => false,
                'factory'  => static fn(string $column, mixed $value): ?Orm\Condition
                => $value ? Orm\Condition::not_in_or_null($column, (array) $value) : null,
            ],
            '_lte_or_null' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::or_null($column, '<=', $value),
            ],
            '_gte_or_null' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::or_null($column, '>=', $value),
            ],
            '_lt_or_null' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::or_null($column, '<', $value),
            ],
            '_gt_or_null' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::or_null($column, '>', $value),
            ],
            '_not_equal' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => is_null($value)
                    ? Orm\Condition::is_not_null($column)
                    : Orm\Condition::compare($column, '!=', $value),
            ],
            '_not_like' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::compare($column, 'NOT LIKE', $value),
            ],
            '_not_null' => [
                'timezone' => false,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::is_not_null($column),
            ],
            '_not_in' => [
                'timezone' => false,
                'factory'  => static fn(string $column, mixed $value): ?Orm\Condition
                => self::_in_condition($column, $value, true),
            ],
            '_like' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::compare($column, 'LIKE', $value),
            ],
            '_null' => [
                'timezone' => false,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::is_null($column),
            ],
            '_gte' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::compare($column, '>=', $value),
            ],
            '_lte' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::compare($column, '<=', $value),
            ],
            '_gt' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::compare($column, '>', $value),
            ],
            '_lt' => [
                'timezone' => true,
                'factory'  => static fn(string $column, mixed $value): Orm\Condition
                => Orm\Condition::compare($column, '<', $value),
            ],
            '_in' => [
                'timezone' => false,
                'factory'  => static fn(string $column, mixed $value): ?Orm\Condition
                => self::_in_condition($column, $value, false),
            ],
        ];

        return $suffixes;
    }

    /**
     * @return array{factory: callable(string, mixed): ?Orm\Condition, timezone: bool}
     */
    private static function _where_suffix(string $suffix): array
    {
        $suffixes = self::_where_suffixes();

        if (!array_key_exists($suffix, $suffixes)) {
            throw new \LogicException("Unknown where suffix {$suffix}");
        }

        return $suffixes[$suffix];
    }

    /**
     * @return array<string, callable(string): Term>
     */
    private static function _order_by_suffixes(): array
    {
        static $suffixes = [
            '_natural_desc' => static fn(string $column): Term => Term::natural($column, 'DESC'),
            '_natural_asc'  => static fn(string $column): Term => Term::natural($column, 'ASC'),
            '_desc'         => static fn(string $column): Term => Term::column($column, 'DESC'),
            '_asc'          => static fn(string $column): Term => Term::column($column, 'ASC'),
        ];

        return $suffixes;
    }

    /**
     * The factory for one suffix.
     * @return callable(string): Term
     */
    private static function _order_by_suffix(string $suffix): callable
    {
        $suffixes = self::_order_by_suffixes();

        if (!array_key_exists($suffix, $suffixes)) {
            throw new \LogicException("Unknown order_by suffix {$suffix}");
        }

        return $suffixes[$suffix];
    }

    // ------------------------ //
    // --- STATIC METHODS   --- //
    // ------------------------ //

    /**
     * Build a query bound to a table.
     */
    public static function _for_table(string $table_name, string $connection_name = self::DEFAULT_CONNECTION): static
    {
        self::_setup_db($connection_name);

        return new static($table_name, $connection_name);
    }

    /**
     * Build the query for a model, with the soft-delete hiding and
     * the model's defaultFilter() applied.
     *
     * @internal
     */
    public static function _for_model(string $model_class, ?string $connection_name = null, bool $apply_model_filter = true): static
    {
        $connection_name ??= Granada::_get_static_property($model_class, '_connection_name', self::DEFAULT_CONNECTION);
        self::_setup_db($connection_name);

        /** @var static $query */
        $query = new (self::_model_query_class($model_class))(Granada::_get_table_name($model_class), $connection_name);
        $query->_setup_model($model_class, $apply_model_filter);

        return $query;
    }

    /**
     * With $apply_model_filter, the soft-delete hiding goes on before
     * the model's defaultFilter(), so the filter adds only its own
     * conditions.
     */
    protected function _setup_model(string $model_class, bool $apply_model_filter): static
    {
        $this->_class_name    = $model_class;
        $this->resultSetClass = $model_class::$resultSetClass;
        $this->use_id_column(Granada::_get_id_column_name($model_class));
        $this->_write_rules = $model_class::_write_rules();

        if (!$apply_model_filter) {
            return $this;
        }

        if ($this->_write_rules->soft_delete_column !== null) {
            $this->where($this->_table_name . '.' . $this->_write_rules->soft_delete_column, 0);
        }

        return $this->defaultFilter();
    }

    /**
     * The query class for a model: Query<Model> when one exists, then
     * BaseQuery<Model>, then Granada\Query, each in the model's
     * namespace.
     */
    private static function _model_query_class(string $model_class): string
    {
        $namespace = ($pos = strrpos($model_class, '\\')) === false
            ? ''
            : substr($model_class, 0, $pos);
        $short_name = $namespace === ''
            ? $model_class
            : substr($model_class, strlen($namespace) + 1);

        foreach (['Query' . $short_name, 'BaseQuery' . $short_name] as $candidate) {
            $candidate_class = $namespace === '' ? $candidate : "{$namespace}\\{$candidate}";
            if (class_exists($candidate_class)) {
                return $candidate_class;
            }
        }

        return self::class;
    }

    // ------------------------ //
    // --- INSTANCE METHODS --- //
    // ------------------------ //

    /**
     * @param string $connection_name Which connection to use
     */
    public function __construct(string $table_name, string $connection_name = self::DEFAULT_CONNECTION)
    {
        $this->_table_name      = $table_name;
        $this->_write_rules     = new Orm\WriteRules();
        $this->_connection_name = $connection_name;

        // Set the flag as config dictates
        $this->_associative_results = self::get_config('find_many_primary_id_as_key', $this->_connection_name);
    }

    /**
     * The model's own extra conditions, applied to every query for the
     * model. A per-model query class overrides this.
     */
    public function defaultFilter(): static
    {
        return $this;
    }

    /**
     * Set the name of the model class finds hydrate rows into.
     */
    public function set_class_name(string $class_name): static
    {
        $this->_class_name = $class_name;

        return $this;
    }

    /**
     * Set the query to return non associative results sets
     */
    public function non_associative(): static
    {
        $this->_associative_results = false;

        return $this;
    }

    /**
     * Set the query to return associative results sets
     */
    public function associative(): static
    {
        $this->_associative_results = true;

        return $this;
    }

    /**
     * Set the query to return associative (or not) results sets, as config dictates
     */
    public function reset_associative(): static
    {
        $this->_associative_results = self::get_config('find_many_primary_id_as_key', $this->_connection_name);

        return $this;
    }

    /**
     * Create an instance from the given row (an associative
     * array of data fetched from the database): an ORM row for a
     * model-less query, the model for a model query.
     * @param array<string, mixed> $row
     */
    protected function _create_instance_from_row(array $row): Granada|ORM
    {
        $instance = ORM::_new_row($this->_table_name, $this->_connection_name, $this->_instance_id_column);
        $instance->hydrate($row);

        return $this->_create_model_instance($instance);
    }

    /**
     * Method to create an instance of the model class
     * associated with this query and populate
     * it with the supplied row.
     */
    protected function _create_model_instance(?ORM $orm): mixed
    {
        if (is_null($orm)) {
            return null;
        }

        if ($this->_class_name === null) {
            return $orm;
        }

        $model = new $this->_class_name();
        $model->set_orm($orm);

        return $model;
    }

    protected function _row_hydrator(): RowHydrator
    {
        return new RowHydrator(
            $this->_connection_name,
            $this->_instance_id_column,
            $this->_associative_results,
            fn(array $row): Granada|ORM => $this->_create_instance_from_row($row),
        );
    }

    /**
     * Tell the query that you are expecting a single result
     * back from your query, and execute it. Will return
     * a single instance of the model class, or null if no
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

        $instance          = $this->_create_instance_from_row($rows[0]);
        $return_result_set = (bool) self::get_config('return_result_sets', $this->_connection_name);

        return $this->_row_hydrator()->one($this, $instance, $return_result_set);
    }

    /**
     * Tell the query that you are expecting multiple results
     * from your query, and execute it. Will return an array
     * or ResultSet of instances of the model class
     * @return array|ResultSet
     */
    public function find_many()
    {
        $return_result_set = (bool) self::get_config('return_result_sets', $this->_connection_name);

        if ($return_result_set) {
            return $this->_row_hydrator()->many($this, $this->find_result_set(), $return_result_set);
        }

        return $this->_row_hydrator()->many($this, $this->_find_many(), $return_result_set);
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
     * Instead of running the query, get the query that would be run for a find_many() call.
     * @param string $connection_name
     * @return string
     */
    public function get_select_query(string $connection_name = self::DEFAULT_CONNECTION): string
    {
        $query  = $this->_build_select();
        $values = $this->_values;

        if (count($values) === 0) {
            $inlined_query = $query;
        } else {
            if (!self::_manager()->has_db($connection_name)) {
                throw new \InvalidArgumentException('Cannot inline bound values without a database connection');
            }

            $inlined_query = Orm\Renderer::inline_query(
                $query,
                $values,
                fn($parameter) => self::get_db($connection_name)->quote($parameter)
            );
        }

        self::_record_query($inlined_query, $connection_name);

        return $inlined_query;
    }

    /**
     * Tell the query that you are expecting multiple results
     * from your query, and execute it. Will return an array
     * of instances of the model class, or an empty array if
     * no rows were returned.
     * @return array<int|string, ORM|Granada>
     */
    protected function _find_many(bool $associative = true): array
    {
        $rows = $this->_run();

        return $this->_row_hydrator()->instances($rows);
    }

    /**
     * The class to hold results in: the given class when it extends
     * Granada\ResultSet, Granada\ResultSet otherwise.
     */
    public static function _result_set_class(string $resultSetClass): string
    {
        if (is_a($resultSetClass, ResultSet::class, true)) {
            return $resultSetClass;
        }

        return ResultSet::class;
    }

    /**
     * Tell the query that you are expecting multiple results
     * from your query, and execute it. Will return a result set object
     * containing instances of the model class.
     */
    public function find_result_set(): ResultSet
    {
        $resultSetClass = self::_result_set_class($this->resultSetClass);

        return new $resultSetClass($this->_find_many());
    }

    /**
     * Tell the query that you are expecting multiple results
     * from your query, and execute it. Will return an array,
     * or an empty array if no rows were returned.
     * @return array
     */
    public function find_array(): array
    {
        return $this->_run();
    }

    /**
     * Return a generator that retrieves the records on demand, one
     * model at a time, with identical results as find_many(). The
     * rows load in chunks of $chunk_size (default 1000); only the
     * list of row ids stays in memory.
     *
     * The generator is single use: to loop again, call
     * find_many_lazy() again, and every query runs again.
     * return_result_sets does not apply.
     *
     * group_by, raw_query() and a join with a custom select() throw
     * before any query runs.
     *
     * @param int $chunk_size How many rows to load per query.
     * @return \Generator<int|string, ORM|Granada>
     */
    public function find_many_lazy(int $chunk_size = 1000): \Generator
    {
        if ($this->_group_by !== []) {
            throw new \InvalidArgumentException('find_many_lazy cannot run a grouped query: one row must be one id');
        }

        if ($this->_is_raw_query) {
            throw new \InvalidArgumentException('find_many_lazy cannot run a raw query: build the query with the chain methods');
        }

        // The rows load from the table alone, so a select() naming
        // another table's columns cannot run.
        if ($this->_join_sources !== [] && !$this->_using_default_result_columns) {
            throw new \InvalidArgumentException('find_many_lazy cannot run a query with a join and a custom select()');
        }

        if ($chunk_size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1');
        }

        return $this->_lazy_walk($chunk_size);
    }

    /**
     * Generate the records: the first query fetches the ids of all
     * the rows, then the rows load in chunks on demand.
     * @return \Generator<int|string, ORM|Granada>
     */
    private function _lazy_walk(int $chunk_size): \Generator
    {
        $id_column = $this->_get_id_column_name();

        // The id pass keeps every clause of the caller's query and
        // narrows the select to the id column. With joins on the
        // query, a bare id column is ambiguous, so it is qualified,
        // as where() does.
        $id_query = clone $this;
        $id_query->clear_select();
        $id_query->related_counts = [];
        $id_pass_column           = $id_column;
        if ($this->_join_sources !== []) {
            $id_pass_column = ($this->_table_alias ?? $this->_table_name) . '.' . $id_column;
        }
        $id_query->select($id_pass_column);
        $ids = array_column($id_query->find_array(), $id_column);

        // The id column is fetched for matching and dropped again
        // when the caller's select() did not include it, so the
        // models come back as find_many() would build them.
        $id_hidden = !$this->_using_default_result_columns
            && !in_array($this->_quote_identifier($id_column), $this->_result_columns, true);

        $hydrator    = $this->_row_hydrator();
        $keyed_by_id = $this->_associative_results && $this->_instance_id_column !== null;
        $chunk_start = 0;

        foreach (array_chunk($ids, $chunk_size) as $chunk_ids) {
            // Each chunk's rows load by id from the table alone.
            $query                 = static::_for_table($this->_table_name, $this->_connection_name);
            $query->related_counts = $this->related_counts;
            if ($this->_table_alias !== null) {
                $query->table_alias($this->_table_alias);
            }
            if ($this->_instance_id_column !== null) {
                $query->use_id_column($this->_instance_id_column);
            }
            if (!$this->_using_default_result_columns) {
                foreach ($this->_result_columns as $column) {
                    $query->select_expr($column);
                }
            }
            if ($id_hidden) {
                $query->select_expr($this->_quote_identifier($id_column));
            }
            $query->where_in($id_column, $chunk_ids);

            // The row query returns the rows in database order.
            // Grouping them by id lets the loop below yield them
            // in $chunk_ids order instead. A chunk id with no row
            // was deleted between the two queries.
            $rows_by_id = [];
            foreach ($query->find_array() as $row) {
                $rows_by_id[$row[$id_column]][] = $row;
            }

            $instances = [];
            foreach ($chunk_ids as $index => $id) {
                $row = null;
                if (isset($rows_by_id[$id])) {
                    $row = array_shift($rows_by_id[$id]);
                }
                if ($row === null) {
                    continue;
                }
                if ($id_hidden) {
                    unset($row[$id_column]);
                }
                $instances[$index] = $hydrator->instance($row);
            }

            // The query's with() eager loads run here, once per
            // chunk against the chunk's parents. The walk yields
            // one model at a time, so return_result_sets does
            // not apply and the chunk stays an array.
            $instances = $hydrator->many($this, $instances, false);

            foreach ($instances as $index => $instance) {
                // The yielded key is the instance's id, the way
                // find_many keys its results. When the caller's
                // select() left the id out - or the id is zero -
                // the key falls back to the position.
                $id  = $instance->id();
                $key = ($keyed_by_id && $id) ? $id : $chunk_start + $index;

                yield $key => $instance;
            }

            $chunk_start += count($chunk_ids);
        }
    }

    /**
     * Pluck a single column from the result.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck(string $column): mixed
    {
        $result = $this->select($column)->find_one();

        if ($result) {
            return $result[$column];
        }

        return null;
    }

    /**
     * Return pairs as result array('keyrecord_value'=>'valuerecord_value',.....)
     */
    public function find_pairs(false|string $key = false, false|string $value = false): array
    {
        $key   = ($key) ? $key : 'id';
        $value = ($value) ? $value : 'name';
        if (count($this->_result_columns) === 2) {
            // The select fields have already been set
            return self::assoc_to_keyval($this->find_array(), $key, $value);
        }

        return self::assoc_to_keyval(
            $this->select_raw($key . ',' . $value)
                ->order_by_asc($value)
                ->find_array(),
            $key,
            $value
        );
    }

    /**
     * Converts a multi-dimensional associative array into an array of key => values with the provided field names
     *
     * @param array $assoc the array to convert
     * @param string $key_field the field name of the key field
     * @param string $val_field the field name of the value field
     * @return array
     */
    /** @param array<int, array<string, mixed>> $assoc */
    public static function assoc_to_keyval(?array $assoc = null, ?string $key_field = null, ?string $val_field = null): array
    {
        if (empty($assoc) or empty($key_field) or empty($val_field)) {
            return [];
        }

        $output = [];
        foreach ($assoc as $row) {
            if (!(isset($row[$key_field]) and array_key_exists($val_field, $row))) {
                continue;
            }

            $output[$row[$key_field]] = $row[$val_field];
        }

        return $output;
    }

    /** @var array<string, bool> */
    private static array $_has_timezone_adjustment_cache = [];

    public function adjustTimezoneForWhere(string $varname, mixed $parameters): mixed
    {
        if ($this->_class_name === null) {
            return $parameters;
        }

        $classname = $this->_class_name;
        self::$_has_timezone_adjustment_cache[$classname] ??= method_exists($classname, 'adjustTimezoneForWhere');

        if (self::$_has_timezone_adjustment_cache[$classname]) {
            return (new $classname())->adjustTimezoneForWhere($varname, $parameters);
        }

        return $parameters;
    }

    /**
     * Tell the query that you wish to execute a COUNT query.
     * Will return an integer representing the number of
     * rows returned.
     */
    public function count(string $column = '*'): int
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the query that you wish to execute a MAX query.
     * Will return the max value of the choosen column.
     * @param string $column
     */
    public function max(string $column): float|int|string
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the query that you wish to execute a MIN query.
     * Will return the min value of the choosen column.
     * @param string $column
     */
    public function min(string $column): float|int|string
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the query that you wish to execute a AVG query.
     * Will return the average value of the choosen column.
     * @param string $column
     */
    public function avg(string $column): float|int
    {
        return $this->_call_aggregate_db_function(__FUNCTION__, $column);
    }

    /**
     * Tell the query that you wish to execute a SUM query.
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
        $alias                 = strtolower($sql_function);
        $result_columns        = $this->_result_columns;
        $related_counts        = $this->related_counts;
        $this->_result_columns = [new Orm\Aggregate(strtoupper($sql_function), $column, $alias)];
        $this->related_counts  = [];
        $result                = $this->find_one();
        $this->_result_columns = $result_columns;
        $this->related_counts  = $related_counts;

        $return_value = 0;
        if ($result !== null && isset($result->$alias)) {
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
     * To create and save multiple elements, easy way
     * Using an array with rows array(array('name'=>'value',...), array('name2'=>'value2',...),..)
     * or a array multiple
     */
    /** @param array<int, array<string, mixed>> $rows */
    public function insert(array $rows): false|string
    {
        $class = $this->_class_name;
        ORM::transaction(function () use ($rows, $class): void {
            foreach ($rows as $row) {
                $class::create($row)->save();
            }
        }, $this->_connection_name);

        return ORM::get_db($this->_connection_name)->lastInsertId();
    }

    /**
     * Flag every matching row instead of deleting it, on a model that
     * names $_soft_delete_column. `$target` names the table or alias the
     * hard delete removes from in the join form.
     */
    public function delete_many(string $target = ''): ?bool
    {
        $flag = $this->_write_rules->soft_delete_column;
        if ($flag === null) {
            $statement = Renderer::delete_many($this->_bulk_delete_spec($target));

            return self::_execute_write($statement->query, $statement->values, $this->_connection_name);
        }

        if ($this->_join_sources !== []) {
            throw new \InvalidArgumentException('delete_many() on a soft-delete model does not support joined queries');
        }

        $set = [$flag => 1];
        $set += $this->_write_rules->stamp($set, false);

        $statement = Renderer::update_many(new BulkUpdateSpec(
            table_name: $this->_table_name,
            dialect: self::_dialect($this->_connection_name),
            set: $set,
            where_conditions: $this->_where_conditions,
        ));

        return self::_execute_write($statement->query, $statement->values, $this->_connection_name);
    }

    /**
     * Write the same values to every row the query matches, in one
     * UPDATE. True when the statement runs, false when the write fails.
     * An empty array is a no-op; a query with a join throws.
     * @param array<string, mixed> $values
     */
    public function update_many(array $values): bool
    {
        if ($this->_join_sources !== []) {
            throw new \InvalidArgumentException('update_many() does not support joined queries');
        }

        if ($values === []) {
            return true;
        }

        if ($this->_class_name !== null) {
            $values = (new $this->_class_name())->_convert_update_values($values);
        }

        $values += $this->_write_rules->stamp($values, false);

        $statement = Renderer::update_many(new BulkUpdateSpec(
            table_name: $this->_table_name,
            dialect: self::_dialect($this->_connection_name),
            set: $values,
            where_conditions: $this->_where_conditions,
        ));

        return (bool) self::_execute_write($statement->query, $statement->values, $this->_connection_name);
    }

    /**
     * Snapshot the state a bulk delete needs into a BulkDeleteSpec for the Renderer.
     */
    protected function _bulk_delete_spec(string $target = ''): Orm\BulkDeleteSpec
    {
        return new Orm\BulkDeleteSpec(
            table_name: $this->_table_name,
            target: $target,
            dialect: self::_dialect($this->_connection_name),
            where_conditions: $this->_where_conditions,
            join_sources: $this->_join_sources,
        );
    }

    /**
     * Set the eagerly loaded models on the queryable model.
     *
     * @return static
     */
    public function with(...$args): static
    {
        array_push($this->relationships, ...$args);

        return $this;
    }

    /**
     * The callback builds onto this query when the condition is
     * true.
     */
    public function onlyif(bool $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this);
        }

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
     * Overload select_expr name
     */
    public function select_raw(string $expr, ?string $alias = null): static
    {
        return $this->select_expr($expr, $alias);
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
        if (!$columns) {
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
     * Create raw_join
     */
    public function raw_join(string $join): static
    {
        $this->_join_sources[] = $join;

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
        $this->_join_sources[] = new Orm\JoinSource($join_operator, $table, $table_alias, $constraint);

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
     * The having-side builder behind the having_* comparison methods.
     */
    protected function _add_simple_having(string $column_name, string $separator, mixed $value): static
    {
        // A HAVING column is usually an aggregate's alias, which a table
        // prefix would break.
        return $this->_add_having_condition(Orm\Condition::compare($column_name, $separator, $value));
    }

    /**
     * The where-side twin of _add_simple_having.
     */
    protected function _add_simple_where(string $column_name, string $separator, mixed $value): static
    {
        return $this->_add_where_condition($this->_simple_condition($column_name, $separator, $value));
    }

    /**
     * A condition connected by OR joins the condition before it, so
     * an or_where_* groups with the condition it ORs against instead
     * of leaking past an AND into the rest of the query.
     * @param Orm\Condition[] $pile
     */
    private function _append_connected_by(array &$pile, Orm\Condition $condition): void
    {
        if (
            $condition->connected_by !== Orm\ConnectedBy::Or
            || $condition->type === Orm\Condition::GROUP
            || $pile === []
        ) {
            $pile[] = $condition;

            return;
        }

        $previous = array_pop($pile);

        if (
            $previous->type === Orm\Condition::GROUP
            && $previous->connected_by === Orm\ConnectedBy::Or
            && !$previous->negate
        ) {
            $pile[] = Orm\Condition::group([...$previous->conditions, $condition], Orm\ConnectedBy::Or);

            return;
        }

        $pile[] = Orm\Condition::group([$previous, $condition], $previous->connected_by);
    }

    protected function _add_where_condition(Orm\Condition $condition): static
    {
        if (in_array($condition, $this->_where_conditions)) {
            return $this; // already present, do not duplicate
        }
        $this->_append_connected_by($this->_where_conditions, $condition);

        return $this;
    }

    protected function _add_having_condition(Orm\Condition $condition): static
    {
        if (in_array($condition, $this->_having_conditions)) {
            return $this; // already present, do not duplicate
        }
        $this->_append_connected_by($this->_having_conditions, $condition);

        return $this;
    }

    /**
     * Cleare all WHERE clauses that reference column
     * @param string $column
     * @return static
     */
    public function remove_where(string $column): static
    {
        $this->_where_conditions = array_values(array_filter(
            $this->_where_conditions,
            fn(Orm\Condition $condition) => !self::_condition_references_column($condition, $column),
        ));

        return $this;
    }

    /**
     * Structured conditions reference a column when it appears as any
     * dot-segment of their (possibly qualified) column; raw conditions
     * when the quoted column name appears in the fragment.
     */
    private static function _condition_references_column(Orm\Condition $condition, string $column): bool
    {
        if ($condition->type === Orm\Condition::RAW) {
            return str_contains($condition->fragment, '`' . $column . '`');
        }

        if ($condition->type === Orm\Condition::EXISTS) {
            return str_contains($condition->subquery, '`' . $column . '`');
        }

        if ($condition->type === Orm\Condition::GROUP) {
            foreach ($condition->conditions as $inner) {
                if (self::_condition_references_column($inner, $column)) {
                    return true;
                }
            }

            return false;
        }

        if ($condition->type === Orm\Condition::ANY_IS) {
            foreach ($condition->groups as $group) {
                foreach ($group as $inner) {
                    if (self::_condition_references_column($inner, $column)) {
                        return true;
                    }
                }
            }

            return false;
        }

        return $condition->column !== '' && in_array($column, explode('.', $condition->column), true);
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
     * A WHERE COLUMN SEPARATOR VALUE condition. Adds the table name so a
     * subquery cannot read the outer table; quoting is left to the
     * Renderer.
     */
    private function _simple_condition(string $column_name, string $separator, mixed $value): Orm\Condition
    {
        return Orm\Condition::compare($this->_qualified_column($column_name), $separator, $value);
    }

    /**
     * The column name qualified with the table, or its alias, unless it
     * already names a table.
     */
    private function _qualified_column(string $column_name): string
    {
        if (str_contains($column_name, '.')) {
            return $column_name;
        }

        return ($this->_table_alias ?? $this->_table_name) . '.' . $column_name;
    }

    /**
     * Add a WHERE column = value clause to your query. Each time
     * this is called in the chain, an additional WHERE will be
     * added, and these will be ANDed together when the final query
     * is built.
     *
     * With a closure, the closure's conditions collected on a fresh
     * query for the same table become one AND group.
     */
    public function where(Closure|string $column_name, mixed $value = null): static
    {
        if ($column_name instanceof Closure) {
            return $this->_add_group($column_name, Orm\ConnectedBy::And, false, $this->_add_where_condition(...));
        }

        if (is_null($value)) {
            return $this->where_null($column_name);
        }

        return $this->where_equal($column_name, $value);
    }

    /**
     * Adds one condition to the WHERE or HAVING list: the closure's
     * group, or a column compare. The connected_by value says
     * whether it ANDs or ORs against the conditions before it;
     * negate renders it with NOT.
     * @param callable(Orm\Condition): static $add_condition
     */
    protected function _add_composed_condition(Closure|string $column_name, mixed $value, Orm\ConnectedBy $connected_by, bool $negate, callable $add_condition, string $list = 'where'): static
    {
        if ($column_name instanceof Closure) {
            return $this->_add_group($column_name, $connected_by, $negate, $add_condition, $list);
        }

        if ($list === 'where') {
            $column_name = $this->_qualified_column($column_name);
        }

        $condition = is_null($value)
            ? Orm\Condition::is_null($column_name)
            : Orm\Condition::compare($column_name, '=', $value);

        return $add_condition($condition->negate_if($negate)->joined_by($connected_by));
    }

    /**
     * Runs the closure on a fresh query for the same table and
     * collects the conditions it built into one group. A closure
     * that builds no conditions adds no group.
     * @param callable(Orm\Condition): static $add_condition
     */
    protected function _add_group(Closure $closure, Orm\ConnectedBy $connected_by, bool $negate, callable $add_condition, string $list = 'where'): static
    {
        $group_query = $this->_group_query();
        $closure($group_query);

        $conditions = $list === 'having'
            ? $group_query->_having_conditions
            : $group_query->_where_conditions;
        if ($conditions === []) {
            return $this;
        }

        return $add_condition(Orm\Condition::group($conditions, $connected_by, $negate));
    }

    /**
     * A fresh query for the same table, to collect the conditions of
     * one group closure. The alias and joins are kept, so column
     * qualification matches the outer query.
     */
    protected function _group_query(): static
    {
        $query                      = new static($this->_table_name, $this->_connection_name);
        $query->_table_alias        = $this->_table_alias;
        $query->_join_sources       = $this->_join_sources;
        $query->_instance_id_column = $this->_instance_id_column;
        $query->_class_name         = $this->_class_name;

        return $query;
    }

    /**
     * More explicitly named version of for the where() method.
     * Can be used if preferred.
     */
    public function where_equal(string $column_name, mixed $value): static
    {
        return $this->_add_equal_condition($column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE column != value clause to your query.
     * @param string $column_name
     * @param string|null $value
     */
    public function where_not_equal(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_not_equal', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Special method to query the table by its primary key
     */
    public function where_id_is(mixed $id): static
    {
        return $this->where($this->_get_id_column_name(), $id);
    }

    /**
     * Special method to query the table by its primary key
     */
    public function where_id_in(null|array|Query $ids): static
    {
        return $this->where_in($this->_get_id_column_name(), $ids);
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
        $groups = [];
        foreach ($values as $value) {
            $group = [];
            foreach ($value as $key => $item) {
                $op = is_string($operator) ? $operator : ($operator[$key] ?? '=');
                if (is_array($item)) {
                    if ($op === '=') {
                        $group[] = Orm\Condition::in($key, $item);
                    } elseif ($op === '!=') {
                        $group[] = Orm\Condition::not_in($key, $item);
                    } else {
                        throw new \InvalidArgumentException('You only pass an array for = and !=.');
                    }
                } elseif (is_null($item) && ($op === '=')) {
                    $group[] = Orm\Condition::is_null($key);
                } elseif (is_null($item) && ($op === '!=')) {
                    $group[] = Orm\Condition::is_not_null($key);
                } else {
                    $group[] = Orm\Condition::compare($key, $op, $item);
                }
            }
            $groups[] = $group;
        }

        return $this->_add_where_condition(Orm\Condition::any_is($groups));
    }

    /**
     * Add a WHERE ... LIKE clause to your query.
     * @param string $column_name
     * @param string $value
     */
    public function where_like(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_like', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add where WHERE ... NOT LIKE clause to your query.
     * @param string $column_name
     * @param string $value
     */
    public function where_not_like(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_not_like', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... > clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gt(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_gt', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... < clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lt(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_lt', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... >= clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gte(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_gte', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... <= clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lte(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_lte', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lt_or_null(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_lt_or_null', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_lte_or_null(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_lte_or_null', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gt_or_null(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_gt_or_null', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... < OR NULL clause to your query
     * @param string $column_name
     * @param integer $value
     */
    public function where_gte_or_null(string $column_name, mixed $value): static
    {
        return $this->_add_suffix_condition('_gte_or_null', $column_name, $value, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... IN clause to your query
     * @param mixed[]|self $values A subquery's values bind into this query
     */
    public function where_in(string $column_name, mixed $values): static
    {
        return $this->_add_suffix_condition('_in', $column_name, $values, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE ... NOT IN clause to your query
     * @param mixed[]|self $values A subquery's values bind into this query
     */
    public function where_not_in(string $column_name, mixed $values): static
    {
        return $this->_add_suffix_condition('_not_in', $column_name, $values, Orm\ConnectedBy::And);
    }

    /**
     * One IN condition over a list or a subquery. A subquery renders
     * with bound placeholders; its values merge into this query's
     * statement. An empty list matches no rows; with NOT, it adds no
     * condition, so every row matches.
     * @param mixed[]|self $values
     */
    private static function _in_condition(string $column_name, mixed $values, bool $negate): ?Orm\Condition
    {
        if (!$values) {
            return $negate
                ? null
                : Orm\Condition::raw('0');
        }

        if ($values instanceof self) {
            $query = $values->_build_select();

            return $negate
                ? Orm\Condition::not_in_subquery($column_name, $query, $values->_values)
                : Orm\Condition::in_subquery($column_name, $query, $values->_values);
        }

        return $negate
            ? Orm\Condition::not_in($column_name, $values)
            : Orm\Condition::in($column_name, $values);
    }

    /**
     * Add a WHERE ... NOT IN OR ... IS NULL clause to your query
     * @param string $column_name
     * @param string[] $values
     */
    public function where_not_in_or_null(string $column_name, array $values): static
    {
        return $this->_add_suffix_condition('_not_in_or_null', $column_name, $values, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE column IS NULL clause to your query
     * @param string $column_name
     */
    public function where_null(string $column_name): static
    {
        return $this->_add_suffix_condition('_null', $column_name, null, Orm\ConnectedBy::And);
    }

    /**
     * Add a WHERE column IS NOT NULL clause to your query
     * @param string $column_name
     */
    public function where_not_null(string $column_name): static
    {
        return $this->_add_suffix_condition('_not_null', $column_name, null, Orm\ConnectedBy::And);
    }

    /**
     * Adds the condition a suffix stands for, connected as asked.
     */
    private function _add_suffix_condition(string $suffix, string $column_name, mixed $value, Orm\ConnectedBy $connected_by): static
    {
        $suffix_type = self::_where_suffix($suffix);
        $column      = $this->_qualified_column($column_name);
        $condition   = ($suffix_type['factory'])($column, $value);

        return $this->_add_condition($condition, $connected_by);
    }

    /**
     * The equality condition, where a null value means IS NULL.
     */
    private function _add_equal_condition(string $column_name, mixed $value, Orm\ConnectedBy $connected_by): static
    {
        $column    = $this->_qualified_column($column_name);
        $condition = is_null($value)
            ? Orm\Condition::is_null($column)
            : Orm\Condition::compare($column, '=', $value);

        return $this->_add_condition($condition, $connected_by);
    }

    /**
     * Adds the condition, unless the suffix's factory returned null.
     */
    private function _add_condition(?Orm\Condition $condition, Orm\ConnectedBy $connected_by): static
    {
        if ($condition === null) {
            return $this;
        }

        return $this->_add_where_condition($condition->joined_by($connected_by));
    }

    /**
     * Add a raw WHERE clause to the query. The clause should
     * contain question mark placeholders, which will be bound
     * to the parameters supplied in the second argument.
     * @param string $clause
     */
    public function where_raw(string $clause, mixed $parameters = []): static
    {
        return $this->_add_where_condition(Orm\Condition::raw($clause, is_array($parameters) ? $parameters : [$parameters]));
    }

    /**
     * Add a WHERE clause ORed against the conditions before it.
     * Takes a closure for a group, or a column compare.
     */
    public function or_where(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, Orm\ConnectedBy::Or, false, $this->_add_where_condition(...));
    }

    /**
     * Add a negated WHERE clause: a closure becomes NOT ( ... ), a
     * column compare renders NOT ( column = value ).
     */
    public function where_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, Orm\ConnectedBy::And, true, $this->_add_where_condition(...));
    }

    /**
     * Add a negated WHERE clause ORed against the conditions before it.
     */
    public function or_where_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, Orm\ConnectedBy::Or, true, $this->_add_where_condition(...));
    }

    /**
     * Add an EXISTS ( subquery ) condition. The subquery is a query
     * for another table; correlation is spelled inside it with
     * where_raw. Its bound values merge into this query.
     */
    public function where_exists(self $subquery): static
    {
        return $this->_add_subquery_exists($subquery, false, $this->_add_where_condition(...));
    }

    /**
     * Add a NOT EXISTS ( subquery ) condition.
     */
    public function where_not_exists(self $subquery): static
    {
        return $this->_add_subquery_exists($subquery, true, $this->_add_where_condition(...));
    }

    /**
     * @param callable(Orm\Condition): static $add_condition
     */
    protected function _add_subquery_exists(self $subquery, bool $negate, callable $add_condition): static
    {
        $query = $subquery->_build_select();

        return $add_condition(Orm\Condition::exists($query, $subquery->_values, $negate));
    }

    /**
     * Keep only the rows with a related row matching the conditions.
     * The paired relationship is matched by EXISTS, so each row stays
     * one row.
     *
     * $conditions is one of:
     * - null, to match any related row;
     * - an array of column => value pairs, ANDed together, where a null
     *   value becomes IS NULL the way where() treats it;
     * - a closure receiving the related model's query.
     *
     * $count keeps only rows with at least that many matching related
     * rows.
     *
     * @param array<string, mixed>|Closure|null $conditions
     */
    public function where_has(string $relation, null|array|Closure $conditions = null, ?int $count = null): static
    {
        $relationship = $this->_existence_relationship($relation, 'where_has');
        $subquery     = $this->_correlated_relationship_query($relationship, $conditions);

        if ($count === null) {
            [$sql, $values] = $this->_subquery_statement($subquery, '1');

            return $this->_add_where_condition(Orm\Condition::exists($sql, $values));
        }

        [$sql, $values] = $this->_subquery_statement($subquery, 'COUNT(*)');

        // The threshold is a caller-supplied int, inlined so the
        // comparison is numeric on every dialect.
        return $this->_add_where_condition(Orm\Condition::raw('( ' . $sql . ' ) >= ' . $count, $values));
    }

    /**
     * Keep only the rows with no related row matching the conditions.
     * $conditions takes the same three forms as where_has().
     *
     * @param array<string, mixed>|Closure|null $conditions
     */
    public function where_not_has(string $relation, null|array|Closure $conditions = null): static
    {
        $relationship   = $this->_existence_relationship($relation, 'where_not_has');
        $subquery       = $this->_correlated_relationship_query($relationship, $conditions);
        [$sql, $values] = $this->_subquery_statement($subquery, '1');

        return $this->_add_where_condition(Orm\Condition::exists($sql, $values, negate: true));
    }

    /**
     * Count the related rows on each row, without loading them.
     * The count is stored on the model as {relation}_count. $constraint
     * takes the same three forms as where_has()'s conditions.
     *
     * @param array<string, mixed>|Closure|null $constraint
     */
    public function with_count(string $relation, null|array|Closure $constraint = null): static
    {
        return $this->_add_related_count($relation, $constraint, false);
    }

    /**
     * Record whether each row has a related row, without loading it.
     * The boolean is stored on the model as {relation}_exists. $constraint
     * takes the same three forms as where_has()'s conditions.
     *
     * @param array<string, mixed>|Closure|null $constraint
     */
    public function with_exists(string $relation, null|array|Closure $constraint = null): static
    {
        return $this->_add_related_count($relation, $constraint, true);
    }

    /**
     * @param array<string, mixed>|Closure|null $constraint
     */
    private function _add_related_count(string $relation, null|array|Closure $constraint, bool $exists): static
    {
        $method         = $exists ? 'with_exists' : 'with_count';
        $relationship   = $this->_existence_relationship($relation, $method);
        $subquery       = $this->_correlated_relationship_query($relationship, $constraint);
        [$sql, $values] = $this->_subquery_statement($subquery, $exists ? '1' : 'COUNT(*)');

        $alias                        = $relation . ($exists ? '_exists' : '_count');
        $this->related_counts[$alias] = new Orm\RelatedCount($alias, $sql, $values, $exists);

        return $this;
    }

    /**
     * A related query as a scalar subquery: its select list becomes the
     * one expression, so a relationship's own select() does not leak a
     * second column into it.
     *
     * @return array{string, mixed[]}
     */
    private function _subquery_statement(self $subquery, string $select): array
    {
        $subquery->clear_select();
        $subquery->select_expr($select);
        $sql = $subquery->_build_select();

        return [$sql, $subquery->_values];
    }

    /**
     * The relationship an existence filter or related count names,
     * read from a fresh instance of this query's model.
     */
    private function _existence_relationship(string $relation, string $method): Relationship
    {
        if ($this->_class_name === null) {
            throw new \LogicException("Cannot {$method}(): the query has no model to read the {$relation} relationship from");
        }

        $model        = Granada::_new_model_row($this->_class_name);
        $relationship = $model->_relationship($relation);
        if ($relationship === null) {
            throw new \LogicException("Cannot {$method}(): {$this->_class_name} has no {$relation} relationship");
        }
        if ($relationship->kind === 'has_many_through') {
            throw new \LogicException("Cannot {$method}(): the {$relation} relationship is a has_many_through; existence filters and related counts do not support it");
        }

        return $relationship;
    }

    /**
     * The related query an existence filter or related count runs on:
     * the relationship's own query, correlated to this query's table.
     * $conditions filter it as where_has() received them.
     *
     * @param array<string, mixed>|Closure|null $conditions
     */
    private function _correlated_relationship_query(Relationship $relationship, null|array|Closure $conditions): Query
    {
        $related = Granada::_related_query($relationship);
        // A scalar subquery needs no ordering, and a relation's own
        // order_by would make SQL Server reject it.
        $related->order_by_clear();

        [$related_column, $outer_column] = match ($relationship->kind) {
            'belongs_to' => [$relationship->related_key, $relationship->key],
            default      => [$relationship->key, $relationship->local_key ?? $this->_get_id_column_name()],
        };

        $related_table = Granada::_get_table_name($relationship->class);
        $outer_table   = $this->_table_alias ?? $this->_table_name;

        $related->where_raw(
            $this->_quote_identifier($related_table . '.' . $related_column)
                . ' = '
                . $this->_quote_identifier($outer_table . '.' . $outer_column)
        );

        if ($conditions === null) {
            return $related;
        }

        if ($conditions instanceof Closure) {
            $conditions($related);

            return $related;
        }

        foreach ($conditions as $column => $value) {
            $related->where($column, $value);
        }

        return $related;
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
        return $this->_add_order_term(Orm\Term::column($column_name, $ordering));
    }

    private function _add_order_term(Term $term): static
    {
        $this->_order_by[] = $term;

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
        return $this->_add_order_term(self::_order_by_suffix('_desc')($column_name));
    }

    /**
     * Add an ORDER BY column ASC clause
     * @param string $column_name
     */
    public function order_by_asc(string $column_name): static
    {
        return $this->_add_order_term(self::_order_by_suffix('_asc')($column_name));
    }

    /**
     * Add an unquoted expression as an ORDER BY clause
     * @param string $clause
     */
    public function order_by_expr(string $clause): static
    {
        $this->_order_by[] = Orm\Term::expression($clause);

        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns to GROUP BY
     */
    public function group_by_raw(string $expr): static
    {
        $this->_group_by[] = Term::expression($expr);

        return $this;
    }

    /**
     * Add an unquoted expression as an ORDER BY clause
     */
    public function order_by_raw(string $clause): static
    {
        $this->_order_by[] = Term::expression($clause);

        return $this;
    }

    /**
     * Add a column to the list of columns to GROUP BY
     * @param string $column_name
     */
    public function group_by(string $column_name): static
    {
        $this->_group_by[] = Orm\Term::column($column_name);

        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns to GROUP BY
     * @param string $expr
     */
    public function group_by_expr(string $expr): static
    {
        $this->_group_by[] = Orm\Term::expression($expr);

        return $this;
    }

    /**
     * Add a HAVING column = value clause to your query. Each time
     * this is called in the chain, an additional HAVING will be
     * added, and these will be ANDed together when the final query
     * is built.
     *
     * With a closure, the closure's conditions collected on a fresh
     * query for the same table become one AND group.
     */
    public function having(Closure|string $column_name, mixed $value = null): static
    {
        if ($column_name instanceof Closure) {
            return $this->_add_group($column_name, Orm\ConnectedBy::And, false, $this->_add_having_condition(...), 'having');
        }

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
        return $this->_add_having_condition(Orm\Condition::in($column_name, $values));
    }

    /**
     * Add a HAVING ... NOT IN clause to your query
     * @param string $column_name
     * @param string[] $values
     */
    public function having_not_in(string $column_name, array $values): static
    {
        return $this->_add_having_condition(Orm\Condition::not_in($column_name, $values));
    }

    /**
     * Add a HAVING column IS NULL clause to your query
     * @param string $column_name
     */
    public function having_null(string $column_name): static
    {
        return $this->_add_having_condition(Orm\Condition::is_null($column_name));
    }

    /**
     * Add a HAVING column IS NOT NULL clause to your query
     * @param string $column_name
     */
    public function having_not_null(string $column_name): static
    {
        return $this->_add_having_condition(Orm\Condition::is_not_null($column_name));
    }

    /**
     * Add a raw HAVING clause to the query. The clause should
     * contain question mark placeholders, which will be bound
     * to the parameters supplied in the second argument.
     * @param string $clause
     */
    public function having_raw(string $clause, array $parameters = []): static
    {
        return $this->_add_having_condition(Orm\Condition::raw($clause, $parameters));
    }

    /**
     * Add a HAVING clause ORed against the conditions before it.
     * Takes a closure for a group, or a column compare.
     */
    public function or_having(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, Orm\ConnectedBy::Or, false, $this->_add_having_condition(...), 'having');
    }

    /**
     * Add a negated HAVING clause: a closure becomes NOT ( ... ), a
     * column compare renders NOT ( column = value ).
     */
    public function having_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, Orm\ConnectedBy::And, true, $this->_add_having_condition(...), 'having');
    }

    /**
     * Add a negated HAVING clause ORed against the conditions before it.
     */
    public function or_having_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, Orm\ConnectedBy::Or, true, $this->_add_having_condition(...), 'having');
    }

    /**
     * Add an EXISTS ( subquery ) condition to HAVING.
     */
    public function having_exists(self $subquery): static
    {
        return $this->_add_subquery_exists($subquery, false, $this->_add_having_condition(...));
    }

    /**
     * Add a NOT EXISTS ( subquery ) condition to HAVING.
     */
    public function having_not_exists(self $subquery): static
    {
        return $this->_add_subquery_exists($subquery, true, $this->_add_having_condition(...));
    }

    /**
     * Build a SELECT statement based on the clauses that have
     * been passed to this instance by chaining method calls.
     */
    protected function _build_select(): string
    {
        if ($this->_is_raw_query) {
            $this->_values = $this->_raw_parameters;

            return $this->_raw_query;
        }

        $statement     = Orm\Renderer::select($this->_select_spec());
        $this->_values = $statement->values;

        return $statement->query;
    }

    /**
     * Snapshot the builder's read state into a SelectSpec for the
     * Renderer, so statements render without a database connection.
     */
    protected function _select_spec(): Orm\SelectSpec
    {
        return new Orm\SelectSpec(
            table_name: $this->_table_name,
            dialect: self::_dialect($this->_connection_name),
            table_alias: $this->_table_alias,
            result_columns: $this->_result_columns,
            join_sources: $this->_join_sources,
            related_counts: array_values($this->related_counts),
            distinct: $this->_distinct,
            where_conditions: $this->_where_conditions,
            having_conditions: $this->_having_conditions,
            group_by: $this->_group_by,
            order_by: $this->_order_by,
            limit: $this->_limit,
            offset: $this->_offset,
        );
    }

    /**
     * Execute the SELECT query that has been built up by chaining methods
     * on this class. Return an array of rows as associative arrays.
     */
    protected function _run(): array
    {
        $query           = $this->_build_select();
        $caching_enabled = self::get_config('caching', $this->_connection_name);

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

        // reset the query after executing it
        $this->reset();

        return $rows;
    }

    /**
     * reset the bound values and result columns after executing the query
     */
    protected function reset(): void
    {
        $this->_values                       = [];
        $this->_result_columns               = ['*'];
        $this->_using_default_result_columns = true;
    }

    // --------------------- //
    // --- MAGIC METHODS --- //
    // --------------------- //

    /**
     * Dispatches the where_*, order_by_* and with_* magic, then the
     * camelCase aliases. Anything else is an unknown query method.
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Handle special order_by methods
        if ($method === 'order_by_rand') {
            return $this->order_by_expr('RAND()');
        }

        if ($method === 'order_by_list') {
            if ($parameters[1]) {
                $this->_order_by[] = Term::by_field_list($parameters[0], $parameters[1]);

                return $this;
            }

            return $this;
        }

        // Handle with_* eager loading
        if (str_starts_with($method, 'with_')) {
            $this->relationships[] = [substr($method, 5) => $parameters[0] ?? null];

            return $this;
        }

        // Handle where_*
        if (str_starts_with($method, 'or_where_')) {
            return $this->_handle_where_method('where_' . substr($method, 9), $parameters, Orm\ConnectedBy::Or);
        }

        // Handle where_has_* / where_not_has_* relationship methods
        if (str_starts_with($method, 'where_not_has_')) {
            return $this->where_not_has(substr($method, 14), $parameters[0] ?? null);
        }

        if (str_starts_with($method, 'where_has_')) {
            return $this->where_has(substr($method, 10), $parameters[0] ?? null, $parameters[1] ?? null);
        }

        if (str_starts_with($method, 'where_')) {
            return $this->_handle_where_method($method, $parameters, Orm\ConnectedBy::And);
        }

        // Handle order_by_*
        if (str_starts_with($method, 'order_by_')) {
            return $this->_handle_order_by_method($method, $parameters);
        }

        // Fallback: convert camelCase to snake_case and check if method exists
        $underscore_method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
        if (method_exists($this, $underscore_method)) {
            return call_user_func_array([$this, $underscore_method], $parameters);
        }

        throw new \Error('Call to undefined method ' . static::class . "::{$method}()");
    }

    /**
     * Dispatches a suffix method. Only this path adjusts values for the
     * model's timezone hook.
     */
    private function _handle_where_method(string $method, array $parameters, Orm\ConnectedBy $connected_by = Orm\ConnectedBy::And): mixed
    {
        $method_name     = substr($method, 6);
        $adjust_timezone = isset($parameters[0]);

        foreach (self::_where_suffixes() as $suffix => $config) {
            if (!str_ends_with($method_name, $suffix)) {
                continue;
            }

            $varname = substr($method_name, 0, -strlen($suffix));

            if ($config['timezone'] && $adjust_timezone) {
                $parameters[0] = $this->adjustTimezoneForWhere($varname, $parameters[0]);
            }

            return $this->_add_suffix_condition($suffix, $varname, $parameters[0] ?? null, $connected_by);
        }

        $value = $adjust_timezone ? $this->adjustTimezoneForWhere($method_name, $parameters[0]) : null;

        return $this->_add_equal_condition($method_name, $value, $connected_by);
    }

    /**
     * Dispatches an order_by suffix method.
     */
    private function _handle_order_by_method(string $method, array $parameters): mixed
    {
        $method_name = substr($method, 9);

        foreach (self::_order_by_suffixes() as $suffix => $factory) {
            if (!str_ends_with($method_name, $suffix)) {
                continue;
            }

            return $this->_add_order_term($factory(substr($method_name, 0, -strlen($suffix))));
        }

        return $this->order_by_asc($method_name);
    }
}
