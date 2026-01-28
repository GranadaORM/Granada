<?php

namespace Granada\Orm;

use Granada\ORM;
use Granada\Eager;
use Exception;

/**
 * Subclass of Idiorm's ORM class that supports
 * returning instances of a specified class rather
 * than raw instances of the ORM class.
 *
 * You shouldn't need to interact with this class
 * directly. It is used internally by the Model base
 * class.
 */
class Wrapper extends ORM
{
    /**
     * The wrapped find_one and find_many classes will
     * return an instance or instances of this class.
     */
    protected $_class_name;

    public $relationships = [];

    /**
     * static lookup tables for __call method suffix patterns
     */
    private static $_where_suffixes = [
        '_not_in_or_null' => ['length' => 15, 'method' => 'where_not_in_or_null', 'timezone' => false],
        '_lte_or_null' => ['length' => 12, 'method' => 'where_lte_or_null', 'timezone' => true],
        '_gte_or_null' => ['length' => 12, 'method' => 'where_gte_or_null', 'timezone' => true],
        '_lt_or_null' => ['length' => 11, 'method' => 'where_lt_or_null', 'timezone' => true],
        '_gt_or_null' => ['length' => 11, 'method' => 'where_gt_or_null', 'timezone' => true],
        '_not_equal' => ['length' => 10, 'method' => 'where_not_equal', 'timezone' => true],
        '_not_like' => ['length' => 9, 'method' => 'where_not_like', 'timezone' => true],
        '_not_null' => ['length' => 9, 'method' => 'where_not_null', 'timezone' => false],
        '_not_in' => ['length' => 7, 'method' => 'where_not_in', 'timezone' => false],
        '_like' => ['length' => 5, 'method' => 'where_like', 'timezone' => true],
        '_null' => ['length' => 5, 'method' => 'where_null', 'timezone' => false],
        '_gte' => ['length' => 4, 'method' => 'where_gte', 'timezone' => true],
        '_lte' => ['length' => 4, 'method' => 'where_lte', 'timezone' => true],
        '_gt' => ['length' => 3, 'method' => 'where_gt', 'timezone' => true],
        '_lt' => ['length' => 3, 'method' => 'where_lt', 'timezone' => true],
        '_in' => ['length' => 3, 'method' => 'where_in', 'timezone' => false],
    ];

    private static $_order_by_suffixes = [
        '_natural_desc' => ['length' => 13, 'method' => '_order_by_natural_desc', 'direction' => 'desc'],
        '_natural_asc' => ['length' => 12, 'method' => '_order_by_natural_asc', 'direction' => 'asc'],
        '_desc' => ['length' => 5, 'method' => 'order_by_desc'],
        '_asc' => ['length' => 4, 'method' => 'order_by_asc'],
    ];

    /**
     * Set the name of the class which the wrapped
     * methods should return instances of.
     * @param string $class_name
     */
    public function set_class_name($class_name)
    {
        $this->_class_name = $class_name;
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
    public function filter($filter_function, ...$args)
    {
        array_unshift($args, $this);

        if (method_exists($this->_class_name, $filter_function)) {
            return call_user_func_array([$this->_class_name, $filter_function], $args);
        }

        return $this;
    }

    /**
     * Factory method, return an instance of this
     * class bound to the supplied table name.
     *
     * A repeat of content in parent::for_table, so that
     * created class is Wrapper, not ORM
     * @return Wrapper
     */
    public static function for_table($table_name, $connection_name = parent::DEFAULT_CONNECTION)
    {
        self::_setup_db($connection_name);

        return new self($table_name, [], $connection_name);
    }

    /**
     * Method to create an instance of the model class
     * associated with this wrapper and populate
     * it with the supplied Idiorm instance.
     */
    protected function _create_model_instance($orm)
    {
        if (is_null($orm)) {
            return null;
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
    public function select_raw($expr, $alias = null)
    {
        return $this->select_expr($expr, $alias);
    }

    /**
     * Special method to query the table by its primary key
     */
    public function where_id_in($ids)
    {
        return $this->where_in($this->_get_id_column_name(), $ids);
    }

    /**
     * Create raw_join
     */
    public function raw_join($join)
    {
        $this->_join_sources[] = "$join";

        return $this;
    }

    /**
     * Add an unquoted expression to the list of columns to GROUP BY
     */
    public function group_by_raw($expr)
    {
        $this->_group_by[] = $expr;

        return $this;
    }

    /**
     * Add an unquoted expression as an ORDER BY clause
     */
    public function order_by_raw($clause)
    {
        $this->_order_by[] = $clause;

        return $this;
    }

    /**
     * To create and save multiple elements, easy way
     * Using an array with rows array(array('name'=>'value',...), array('name2'=>'value2',...),..)
     * or a array multiple
     */
    public function insert($rows, $ignore = false)
    {
        ORM::get_db()->beginTransaction();
        $class = $this->_class_name;
        foreach ($rows as $row) {
            $class::create($row)->save($ignore);
        }
        ORM::get_db()->commit();

        return ORM::get_db()->lastInsertId();
    }

    /**
     * Wrap Idiorm's find_one method to return
     * an instance of the class associated with
     * this wrapper instead of the raw ORM class.
     * Added: hidrate the model instance before returning
     * @param integer $id
     */
    public function find_one($id = null)
    {
        $result = $this->_create_model_instance(parent::find_one($id));
        if ($result) {
            // set result on an result set for the eager load to work
            $key     = (isset($result->{$this->_instance_id_column}) && $this->_associative_results) ? $result->id() : 0;
            $results = [$key => $result];
            Eager::hydrate($this, $results, self::$_config[$this->_connection_name]['return_result_sets']);
            // return the result as element, not result set
            $result = $results[$key];
        }

        return $result;
    }

    /**
     * Tell the ORM that you are expecting multiple results
     * from your query, and execute it. Will return an array
     * or ResultSet of instances of the ORM class
     * @return array|\Granada\ResultSet
     */
    public function find_many()
    {
        $instances = parent::find_many();

        // Check if now rows returned
        if (is_array($instances)) {
            if (!$instances) {
                return $instances;
            }
        } else {
            if (!$instances->has_results()) {
                return $instances;
            }
        }

        // Add eager relationships
        return Eager::hydrate($this, $instances, self::$_config[$this->_connection_name]['return_result_sets']);
    }

    /**
     * Override Idiorm _instances_with_id_as_key
     * Create instances of each row in the result and map
     * them to an associative array with the primary IDs as
     * the array keys.
     * Added: the array result key = primary key from the model
     * Added: Eager loading of relationships defined "with()"
     * @param array $rows
     * @return array
     */
    protected function _get_instances($rows)
    {
        $instances = [];
        foreach ($rows as $current_key => $current_row) {
            $row             = $this->_create_instance_from_row($current_row);
            $row             = $this->_create_model_instance($row);
            $key             = (isset($row->{$this->_instance_id_column}) && $this->_associative_results && $row->id()) ? $row->id() : $current_key;
            $instances[$key] = $row;
        }

        return $instances;
    }

    /**
     * Pluck a single column from the result.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
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
    public function create($data = null)
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
     * @return Wrapper
     */
    public function with(...$args)
    {
        array_push($this->relationships, ...$args);

        return $this;
    }

    /**
     * Added: Reset relation deletes the relationship "where" condition.
     *
     * @return Wrapper
     */
    public function reset_relation()
    {
        array_shift($this->_where_conditions);

        return $this;
    }

    /**
     * Added: Return pairs as result array('keyrecord_value'=>'valuerecord_value',.....)
     */
    public function find_pairs($key = false, $value = false)
    {
        $key   = ($key) ? $key : 'id';
        $value = ($value) ? $value : 'name';
        if (count($this->_result_columns) == 2) {
            // The select fields have already been set
            return self::assoc_to_keyval($this->find_array(), $key, $value);
        }

        return self::assoc_to_keyval($this->select_raw("$key,$value")->order_by_asc($value)->find_array(), $key, $value);
    }

    /**
     * Converts a multi-dimensional associative array into an array of key => values with the provided field names
     *
     * @param array $assoc the array to convert
     * @param string $key_field the field name of the key field
     * @param string $val_field the field name of the value field
     * @return array
     */
    public static function assoc_to_keyval($assoc = null, $key_field = null, $val_field = null)
    {
        if (empty($assoc) or empty($key_field) or empty($val_field)) {
            return [];
        }

        $output = [];
        foreach ($assoc as $row) {
            if (isset($row[$key_field]) and isset($row[$val_field])) {
                $output[$row[$key_field]] = $row[$val_field];
            }
        }

        return $output;
    }

    private static $_has_timezone_adjustment_cache = [];

    public function adjustTimezoneForWhere($varname, $parameters)
    {
        $classname = $this->_class_name;
        self::$_has_timezone_adjustment_cache[$classname] ??= method_exists($classname, 'adjustTimezoneForWhere');

        if (self::$_has_timezone_adjustment_cache[$classname]) {
            return (new $classname())->adjustTimezoneForWhere($varname, $parameters);
        }

        return $parameters;
    }

    /**
     * Overrides __call to check for filter_$method names defined
     * You can now define filters methods on the Granada Model as
     * public static function filter_{filtermethodname} and call it from a static call
     * ModelName::filtermethodname->......
     */
    public function __call($method, $parameters)
    {
        // Check for filter methods first (as they override)
        if (method_exists($this->_class_name, 'filter_' . $method)) {
            array_unshift($parameters, $this);

            return call_user_func_array([$this->_class_name, 'filter_' . $method], $parameters);
        }

        // Handle special order_by methods
        if ($method === 'order_by_rand') {
            return $this->order_by_expr('RAND()');
        }

        if ($method === 'order_by_list') {
            if ($parameters[1]) {
                return $this->order_by_expr('FIELD(`' . $parameters[0] . '`,' . implode(',', $parameters[1]) . ')');
            }

            return $this;
        }

        // Handle where_*
        if (str_starts_with($method, 'where_')) {
            return $this->_handleWhereMethod($method, $parameters);
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

        throw new Exception(" no static $method found or static method 'filter_$method' not defined in " . $this->_class_name);
    }

    /**
     * Performance optimized handler for where_* methods
     */
    private function _handleWhereMethod($method, $parameters)
    {
        $tablename = $this->_table_name . '.';
        $method_name = substr($method, 6);

        foreach (self::$_where_suffixes as $suffix => $config) {
            if (str_ends_with($method_name, $suffix)) {
                $varname = substr($method_name, 0, -$config['length']);
                $column_name = $tablename . $varname;

                $target_method = $config['method'];
                $needs_timezone = $config['timezone'] ?? false;

                if ($needs_timezone && isset($parameters[0])) {
                    $parameters[0] = $this->adjustTimezoneForWhere($varname, $parameters[0]);
                }

                if (isset($parameters[0])) {
                    return call_user_func([$this, $target_method], $column_name, $parameters[0]);
                } else {
                    return call_user_func([$this, $target_method], $column_name);
                }
            }
        }

        $varname = $method_name;
        $column_name = $tablename . $varname;

        $adjusted_value = isset($parameters[0]) ? $this->adjustTimezoneForWhere($varname, $parameters[0]) : null;
        return $this->where_equal($column_name, $adjusted_value);
    }

    /**
     * Performance optimized handler for order_by_* methods
     */
    private function _handleOrderByMethod($method, $parameters)
    {
        $method_name = substr($method, 9);

        foreach (self::$_order_by_suffixes as $suffix => $config) {
            if (str_ends_with($method_name, $suffix)) {
                $varname = substr($method_name, 0, -$config['length']);
                $target_method = $config['method'];

                if ($target_method === '_order_by_natural_desc') {
                    return $this->order_by_expr('LENGTH(`' . $varname . '`), `' . $varname . '` DESC');
                }
                if ($target_method === '_order_by_natural_asc') {
                    return $this->order_by_expr('LENGTH(`' . $varname . '`), `' . $varname . '` ASC');
                }

                return call_user_func([$this, $target_method], $varname);
            }
        }

        $varname = $method_name;

        return $this->order_by_asc($varname);
    }
}
