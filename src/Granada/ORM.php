<?php

namespace Granada;

use ArrayAccess;

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
/**
 * A row: the data of one database row and the writes that touch it.
 *
 * @internal
 * @implements ArrayAccess<string, mixed>
 */
class ORM extends Orm\Db implements ArrayAccess
{
    // Limit clause style
    public const LIMIT_STYLE_TOP_N = 'top';
    public const LIMIT_STYLE_LIMIT = 'limit';

    // --------------------------- //
    // --- INSTANCE PROPERTIES --- //
    // --------------------------- //

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

    /**
     * The write rules of this row's model.
     */
    public Orm\WriteRules $_write_rules;

    // ---------------------- //
    // --- STATIC METHODS --- //
    // ---------------------- //

    /**
     * Build an empty row for a table.
     *
     * @internal
     */
    public static function _new_row(string $table_name, string $connection_name = self::DEFAULT_CONNECTION, ?string $id_column = null): self
    {
        $row = new self($table_name, [], $connection_name);
        $row->use_id_column($id_column);

        return $row;
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
        $this->_table_name      = $table_name;
        $this->_data            = $data;
        $this->_clean_data      = [];
        $this->_write_rules     = new Orm\WriteRules();
        $this->_connection_name = $connection_name;
    }

    /**
     * Mark this row as new, so save() inserts it. May optionally
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
     * This method can be called to hydrate (populate) this
     * instance of the class from an associative array of data.
     * This will usually be called only from inside the class,
     * but it's public in case you need to call it directly.
     */
    public function hydrate(array $data = []): static
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
    public function set_expr(array|string $key, mixed $value = null): static
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
            $set_as_dirty        = false;
            if ($this->is_new() || $expr) {
                // New field or setting as an sql expression
                $set_as_dirty = true;
            } elseif (is_null($oldval) != is_null($value)) {
                // If the field is changing null status
                $set_as_dirty = true;
            } elseif (is_float($value)) {
                // Float comparison can have precision errors
                $set_as_dirty = abs($oldval - $value) > 0.000_000_000_000_01;
            } elseif (is_string($oldval)) {
                if ($oldval !== $value) {
                    // Strings must match exactly, e.g. '0123' == '123' so need ===
                    $set_as_dirty = true;
                }
            } elseif ($oldval != $value) {
                // Fall back to a loose equality match
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
     */
    public function save()
    {
        // Fix if id field is blank but not null
        if (!($this->id() && array_key_exists($this->_get_id_column_name(), $this->_dirty_fields))) {
            unset($this->_dirty_fields[$this->_get_id_column_name()]);
        }

        $this->_apply_auto_timestamps();

        // remove any expression fields as they are already baked into the query
        $values              = array_values(array_diff_key($this->_dirty_fields, $this->_expr_fields));
        $has_no_dirty_fields = empty($values) && empty($this->_expr_fields);

        // A new record with nothing to write would emit an INSERT with no columns
        if ($this->_is_new && $has_no_dirty_fields) {
            throw new \InvalidArgumentException('Cannot save a new record with no fields set');
        }

        if (!$this->_is_new) { // UPDATE
            // If there are no dirty values, do nothing
            if ($has_no_dirty_fields) {
                return true;
            }
            $statement = Orm\Renderer::update($this->_write_spec());
        } else { // INSERT
            $statement = Orm\Renderer::insert($this->_write_spec());
        }

        $success = self::_execute_write($statement->query, $statement->values, $this->_connection_name);

        // A failed write (a silent or warning error mode returns false)
        // leaves the record as it is, so a retry can work
        if (!$success) {
            return false;
        }

        // If we've just inserted a new record, set the ID of this object
        if ($this->_is_new) {
            $this->_is_new = false;
            if (!($this->id())) {
                $this->_data[$this->_get_id_column_name()] = self::_dialect($this->_connection_name)->fetch_new_id(
                    self::get_db($this->_connection_name),
                    self::get_last_statement()
                );
            }
        }
        $this->clear_cache();
        $this->_expr_fields  = [];
        $this->_dirty_fields = [];
        $this->_clean_data   = [];

        return true;
    }

    /**
     * Write the automatic timestamps for this write, when the model sets
     * $_auto_timestamps.
     */
    protected function _apply_auto_timestamps(): void
    {
        $stamped = $this->_write_rules->stamp($this->_dirty_fields + $this->_expr_fields, $this->_is_new);
        foreach ($stamped as $column => $value) {
            $this->_dirty_fields[$column] = $value;
            $this->_data[$column]         = $value;
        }
    }

    /**
     * Snapshot the row's write state into a WriteSpec for the Renderer.
     */
    protected function _write_spec(): Orm\WriteSpec
    {
        return new Orm\WriteSpec(
            table_name: $this->_table_name,
            id_column: $this->_get_id_column_name(),
            dialect: self::_dialect($this->_connection_name),
            dirty_fields: $this->_dirty_fields,
            expr_fields: $this->_expr_fields,
            id_value: $this->id(),
        );
    }

    /**
     * Build an UPDATE query
     */
    protected function _build_update(): string
    {
        return Orm\Renderer::update($this->_write_spec())->query;
    }

    /**
     * Build an INSERT query
     */
    protected function _build_insert(): string
    {
        return Orm\Renderer::insert($this->_write_spec())->query;
    }

    /**
     * Delete this record from the database
     */
    public function delete(): ?bool
    {
        $statement = Orm\Renderer::delete($this->_write_spec());

        return self::_execute_write($statement->query, $statement->values, $this->_connection_name);
    }

    /**
     * Add $amount to a column in one UPDATE.
     * Returns the new database value, or false if it fails.
     */
    public function increment(string $column, float|int $amount = 1): mixed
    {
        return $this->_adjust_counter($column, $amount);
    }

    /**
     * Subtract $amount from a column in one UPDATE.
     * Returns the new database value, or false if it fails.
     */
    public function decrement(string $column, float|int $amount = 1): mixed
    {
        return $this->_adjust_counter($column, -$amount);
    }

    private function _adjust_counter(string $column, float|int $amount): mixed
    {
        if ($this->is_new() || $this->id() === null) {
            $this->$column = ($this->$column ?? 0) + $amount;

            return $this->$column;
        }

        // Save a clone, so pending changes on this row stay pending
        $counter                = clone $this;
        $counter->_dirty_fields = [];
        $counter->_expr_fields  = [];
        $quoted                 = $counter->_quote_identifier($column);
        $counter->set_expr($column, "COALESCE({$quoted}, 0) + {$amount}");
        if (!$counter->save()) {
            return false;
        }

        $value = Query::_for_table($this->_table_name, $this->_connection_name)
            ->where_id_is($this->id())
            ->pluck($column);
        if ($value === null) {
            return false;
        }

        $this->_data[$column] = $value;
        unset($this->_dirty_fields[$column], $this->_expr_fields[$column]);

        return $value;
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

        // A snake_case name maps to itself here, so an unknown name
        // would re-enter this dispatch; refuse it instead.
        if (!method_exists(static::class, $method)) {
            throw new \BadMethodCallException("Method {$name} does not exist");
        }

        return call_user_func_array([static::class, $method], $arguments);
    }
}
