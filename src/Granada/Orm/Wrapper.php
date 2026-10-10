<?php

namespace Granada\Orm;

use Closure;
use Granada\ORM;
use Granada\Granada;
use Granada\Relationship;
use Exception;

/**
 * Subclass of Idiorm's ORM class that supports
 * returning instances of a specified class rather
 * than raw instances of the ORM class.
 *
 * You shouldn't need to interact with this class
 * directly. It is used internally by the Model base
 * class.
 *
 * @internal
 */
class Wrapper extends ORM
{
    /**
     * The wrapped find_one and find_many classes will
     * return an instance or instances of this class.
     */
    protected ?string $_class_name = null;

    /**
     * The relationship this query was built from,
     * when a relationship method built it.
     */
    public ?Relationship $_relationship = null;

    /** @var array<string, mixed> */
    public array $relationships = [];

    /**
     * static lookup tables for __call method suffix patterns
     */
    /** @var array<string, array{length: int, method: string, timezone: bool}> */
    private static array $_where_suffixes = [
        '_not_in_or_null' => ['length' => 15, 'method' => 'where_not_in_or_null', 'timezone' => false],
        '_lte_or_null'    => ['length' => 12, 'method' => 'where_lte_or_null', 'timezone' => true],
        '_gte_or_null'    => ['length' => 12, 'method' => 'where_gte_or_null', 'timezone' => true],
        '_lt_or_null'     => ['length' => 11, 'method' => 'where_lt_or_null', 'timezone' => true],
        '_gt_or_null'     => ['length' => 11, 'method' => 'where_gt_or_null', 'timezone' => true],
        '_not_equal'      => ['length' => 10, 'method' => 'where_not_equal', 'timezone' => true],
        '_not_like'       => ['length' => 9, 'method' => 'where_not_like', 'timezone' => true],
        '_not_null'       => ['length' => 9, 'method' => 'where_not_null', 'timezone' => false],
        '_not_in'         => ['length' => 7, 'method' => 'where_not_in', 'timezone' => false],
        '_like'           => ['length' => 5, 'method' => 'where_like', 'timezone' => true],
        '_null'           => ['length' => 5, 'method' => 'where_null', 'timezone' => false],
        '_gte'            => ['length' => 4, 'method' => 'where_gte', 'timezone' => true],
        '_lte'            => ['length' => 4, 'method' => 'where_lte', 'timezone' => true],
        '_gt'             => ['length' => 3, 'method' => 'where_gt', 'timezone' => true],
        '_lt'             => ['length' => 3, 'method' => 'where_lt', 'timezone' => true],
        '_in'             => ['length' => 3, 'method' => 'where_in', 'timezone' => false],
    ];

    /** @var array<string, array{length: int, method: string, direction?: string}> */
    private static array $_order_by_suffixes = [
        '_natural_desc' => ['length' => 13, 'method' => '_order_by_natural_desc', 'direction' => 'desc'],
        '_natural_asc'  => ['length' => 12, 'method' => '_order_by_natural_asc', 'direction' => 'asc'],
        '_desc'         => ['length' => 5, 'method' => 'order_by_desc'],
        '_asc'          => ['length' => 4, 'method' => 'order_by_asc'],
    ];

    /**
     * Set the name of the class which the wrapped
     * methods should return instances of.
     * @param string $class_name
     */
    public function set_class_name(string $class_name): static
    {
        $this->_class_name = $class_name;

        return $this;
    }

    /**
     * Add a custom filter to the method chain specified on the
     * model class. This allows custom queries to be added
     * to models. The filter should take an instance of the
     * ORM wrapper as its first argument and return an instance
     * of the ORM wrapper. Any arguments passed to this method
     * after the name of the filter will be passed to the called
     * filter function as arguments after the ORM class.
     */
    public function filter(string $filter_function, ...$args): mixed
    {
        array_unshift($args, $this);

        // A wrapper without a model class has no filters to look up.
        if (!$this->_class_name) {
            return $this;
        }

        if (!method_exists($this->_class_name, $filter_function)) {
            return $this;
        }

        return call_user_func_array([$this->_class_name, $filter_function], $args);
    }

    /**
     * Method to create an instance of the model class
     * associated with this wrapper and populate
     * it with the supplied Idiorm instance.
     */
    protected function _create_model_instance(?self $orm): mixed
    {
        if (is_null($orm)) {
            return null;
        }

        if ($this->_class_name === null) {
            return $orm;
        }

        $model               = new $this->_class_name();
        $orm->resultSetClass = $model->get_resultSetClass();
        $orm->set_class_name($this->_class_name);
        $model->set_orm($orm);

        return $model;
    }

    /**
     * Overload select_expr name
     */
    public function select_raw(string $expr, ?string $alias = null): static
    {
        return $this->select_expr($expr, $alias);
    }

    /**
     * Special method to query the table by its primary key
     */
    public function where_id_in(null|array|Wrapper $ids): static
    {
        return $this->where_in($this->_get_id_column_name(), $ids);
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
     * To create and save multiple elements, easy way
     * Using an array with rows array(array('name'=>'value',...), array('name2'=>'value2',...),..)
     * or a array multiple
     */
    /** @param array<int, array<string, mixed>> $rows */
    public function insert(array $rows, bool $ignore = false): false|string
    {
        $class = $this->_class_name;
        ORM::transaction(function () use ($rows, $ignore, $class): void {
            foreach ($rows as $row) {
                $class::create($row)->save($ignore);
            }
        }, $this->_connection_name);

        return ORM::get_db($this->_connection_name)->lastInsertId();
    }

    /**
     * Wrap Idiorm's find_one method to return
     * an instance of the class associated with
     * this wrapper instead of the raw ORM class.
     * @param integer $id
     */
    public function find_one(mixed $id = null)
    {
        $instance          = $this->_create_model_instance(parent::find_one($id));
        $return_result_set = (bool) self::get_config('return_result_sets', $this->_connection_name);

        return $this->_row_hydrator()->one($this, $instance, $return_result_set);
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return an array
     * or ResultSet of instances of the ORM class
     * @return array|\Granada\ResultSet
     */
    public function find_many()
    {
        $return_result_set = (bool) self::get_config('return_result_sets', $this->_connection_name);

        return $this->_row_hydrator()->many($this, parent::find_many(), $return_result_set);
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
        $id_pass_column = $id_column;
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
            $query = static::_for_table($this->_table_name, $this->_connection_name);
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

    protected function _row_hydrator(): RowHydrator
    {
        return new RowHydrator(
            $this->_connection_name,
            $this->_instance_id_column,
            $this->_associative_results,
            fn(array $row): Granada|ORM => $this->_create_model_instance($this->_create_instance_from_row($row)),
        );
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
     * Wrap Idiorm's create method to return an
     * empty instance of the class associated with
     * this wrapper instead of the raw ORM class.
     */
    public function create(?array $data = null)
    {
        $model = $this->_create_model_instance(parent::create(null));
        if ($data !== null) {
            $model->set($data);
        }

        return $model;
    }

    /**
     * Added: Set the eagerly loaded models on the queryable model.
     *
     * @return static
     */
    public function with(...$args): static
    {
        array_push($this->relationships, ...$args);

        return $this;
    }

    /**
     * Added: Return pairs as result array('keyrecord_value'=>'valuerecord_value',.....)
     */
    public function find_pairs(false|string $key = false, false|string $value = false): array
    {
        $key   = ($key) ? $key : 'id';
        $value = ($value) ? $value : 'name';
        if (count($this->_result_columns) === 2) {
            // The select fields have already been set
            return self::assoc_to_keyval($this->find_array(), $key, $value);
        }

        return self::assoc_to_keyval($this->select_raw($key . ',' . $value)->order_by_asc($value)->find_array(), $key, $value);
    }

    /**
     * Converts a multi-dimensional associative graarray into an array of key => values with the provided field names
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
     * A query closure receives a model of the query's class standing
     * in for the query, so the closure can type the parameter as the
     * model class. Queries without a model class receive the query
     * itself.
     */
    protected function _query_standin(ORM $query): object
    {
        if ($this->_class_name === null) {
            return $query;
        }

        assert($query instanceof Wrapper);

        $class_name = $this->_class_name;

        /** @var \Granada\Granada $model */
        $model                 = new ($class_name::_query_standin_class())();
        $model->_query_standin = $query;

        return $model;
    }

    /**
     * The callback builds onto this query when the condition is
     * true. With a model class, the callback receives a model
     * standing in for the query, so it can type the parameter as
     * the model.
     */
    public function onlyif(bool $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this->_query_standin($this));
        }

        return $this;
    }

    /**
     * Add a WHERE clause ORed against the conditions before it.
     * Takes a closure for a group, or a column compare.
     */
    public function or_where(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, ConnectedBy::Or, false, $this->_add_where_condition(...));
    }

    /**
     * Add a negated WHERE clause: a closure becomes NOT ( ... ), a
     * column compare renders NOT ( column = value ).
     */
    public function where_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, ConnectedBy::And, true, $this->_add_where_condition(...));
    }

    /**
     * Add a negated WHERE clause ORed against the conditions before it.
     */
    public function or_where_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, ConnectedBy::Or, true, $this->_add_where_condition(...));
    }

    /**
     * Add an EXISTS ( subquery ) condition. The subquery is a query
     * for another table; correlation is spelled inside it with
     * where_raw. Its bound values merge into this query.
     */
    public function where_exists(ORM $subquery): static
    {
        return $this->_add_subquery_exists($subquery, false, $this->_add_where_condition(...));
    }

    /**
     * Add a NOT EXISTS ( subquery ) condition.
     */
    public function where_not_exists(ORM $subquery): static
    {
        return $this->_add_subquery_exists($subquery, true, $this->_add_where_condition(...));
    }

    /**
     * Add a HAVING clause ORed against the conditions before it.
     * Takes a closure for a group, or a column compare.
     */
    public function or_having(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, ConnectedBy::Or, false, $this->_add_having_condition(...), 'having');
    }

    /**
     * Add a negated HAVING clause: a closure becomes NOT ( ... ), a
     * column compare renders NOT ( column = value ).
     */
    public function having_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, ConnectedBy::And, true, $this->_add_having_condition(...), 'having');
    }

    /**
     * Add a negated HAVING clause ORed against the conditions before it.
     */
    public function or_having_not(Closure|string $column_name, mixed $value = null): static
    {
        return $this->_add_composed_condition($column_name, $value, ConnectedBy::Or, true, $this->_add_having_condition(...), 'having');
    }

    /**
     * Add an EXISTS ( subquery ) condition to HAVING.
     */
    public function having_exists(ORM $subquery): static
    {
        return $this->_add_subquery_exists($subquery, false, $this->_add_having_condition(...));
    }

    /**
     * Add a NOT EXISTS ( subquery ) condition to HAVING.
     */
    public function having_not_exists(ORM $subquery): static
    {
        return $this->_add_subquery_exists($subquery, true, $this->_add_having_condition(...));
    }

    /**
     * The group closure's query carries the model class name, so the
     * where_* suffix magic and filter_* methods work inside.
     */
    protected function _group_query(): static
    {
        $query = parent::_group_query();
        if ($this->_class_name !== null) {
            $query->set_class_name($this->_class_name);
        }

        return $query;
    }

    /**
     * Overrides __call to check for filter_$method names defined
     * You can now define filters methods on the Granada Model as
     * public static function filter_{filtermethodname} and call it from a static call
     * ModelName::filtermethodname->......
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Check for filter methods first (as they override).
        // A wrapper without a model class falls through to the
        // suffix dispatch.
        if ($this->_class_name && method_exists($this->_class_name, 'filter_' . $method)) {
            array_unshift($parameters, $this);

            return call_user_func_array([$this->_class_name, 'filter_' . $method], $parameters);
        }

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
            return $this->_handleWhereMethod('where_' . substr($method, 9), $parameters, ConnectedBy::Or);
        }

        if (str_starts_with($method, 'where_')) {
            return $this->_handleWhereMethod($method, $parameters, ConnectedBy::And);
        }

        // Handle order_by_*
        if (str_starts_with($method, 'order_by_')) {
            return $this->_handleOrderByMethod($method, $parameters);
        }

        // Fallback: convert camelCase to snake_case and check if method exists
        $underscore_method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
        if (method_exists($this, $underscore_method)) {
            return call_user_func_array([$this, $underscore_method], $parameters);
        }

        throw new Exception(" no static {$method} found or static method 'filter_{$method}' not defined in " . $this->_class_name);
    }

    /**
     * Performance optimized handler for where_* methods. Each suffix
     * builds one condition that ANDs or ORs as asked.
     */
    private function _handleWhereMethod(string $method, array $parameters, ConnectedBy $connected_by = ConnectedBy::And): mixed
    {
        $tablename       = $this->_table_name . '.';
        $method_name     = substr($method, 6);
        $adjust_timezone = isset($parameters[0]);

        foreach (self::$_where_suffixes as $suffix => $config) {
            if (!(str_ends_with($method_name, $suffix))) {
                continue;
            }

            $varname     = substr($method_name, 0, -$config['length']);
            $column_name = $tablename . $varname;

            if ($config['timezone'] && $adjust_timezone) {
                $parameters[0] = $this->adjustTimezoneForWhere($varname, $parameters[0]);
            }

            return $this->_add_where_suffix_condition($config['method'], $column_name, $parameters[0] ?? null, $connected_by);
        }

        $varname     = $method_name;
        $column_name = $tablename . $varname;

        $value = $adjust_timezone ? $this->adjustTimezoneForWhere($varname, $parameters[0]) : null;

        return $this->_add_where_suffix_condition('where_equal', $column_name, $value, $connected_by);
    }

    /**
     * Performance optimized handler for order_by_* methods
     */
    private function _handleOrderByMethod(string $method, array $parameters): mixed
    {
        $method_name = substr($method, 9);

        foreach (self::$_order_by_suffixes as $suffix => $config) {
            if (!(str_ends_with($method_name, $suffix))) {
                continue;
            }

            $varname       = substr($method_name, 0, -$config['length']);
            $target_method = $config['method'];

            if ($target_method === '_order_by_natural_desc') {
                $this->_order_by[] = Term::natural($varname, 'DESC');

                return $this;
            }
            if ($target_method === '_order_by_natural_asc') {
                $this->_order_by[] = Term::natural($varname, 'ASC');

                return $this;
            }

            return call_user_func([$this, $target_method], $varname);
        }

        $varname = $method_name;

        return $this->order_by_asc($varname);
    }
}
