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
 * A simple Active Record implementation built on top of Idiorm, Paris and Eloquent
 * ( https://github.com/GranadaORM/Granada/ ).
 *
 * You should include Idiorm before you include this file:
 * require_once 'your/path/to/idiorm.php';
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
 * list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
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
 * Model base class. Your model objects should extend
 * this class. A minimal subclass would look like:
 *
 * class Widget extends Model {
 * }
 */
class Granada implements ArrayAccess
{
    // Default ID column for all models. Can be overridden by adding
    // a public static _id_column property to your model classes.
    public const DEFAULT_ID_COLUMN = 'id';

    // Default foreign key suffix used by relationship methods
    public const DEFAULT_FOREIGN_KEY_SUFFIX = '_id';

    /**
     * Set _soft_delete_column to enable soft deletes.
     */
    protected static ?string $_soft_delete_column = null;

    /**
     * Set on a model to write created_at and updated_at in UTC on every
     * write. Off by default.
     */
    protected static bool $_auto_timestamps = false;

    /**
     * The columns the automatic timestamps are written to.
     * @var array{created: string, updated: string}
     */
    protected static array $_auto_timestamp_columns = [
        'created' => 'created_at',
        'updated' => 'updated_at',
    ];

    /**
     * Fields whose change does not write updated_at.
     * @var string[]
     */
    protected static array $_auto_timestamps_ignore = [];

    public static string $resultSetClass = 'Granada\ResultSet';

    /**
     * Set a prefix for model names. This can be a namespace or any other
     * arbitrary prefix such as the PEAR naming convention.
     * @example Model::$auto_prefix_models = 'MyProject_MyModels_'; //PEAR
     * @example Model::$auto_prefix_models = '\MyProject\MyModels\'; //Namespaces
     * @var string
     */
    public static ?string $auto_prefix_models = null;

    /**
     * The ORM instance used by this model
     * instance to communicate with the database.
     * @var ORM
     */
    public $orm;

    /**
     * A stash of values held for the request, so the model does not
     * repeat database work. Backs the $relationships property.
     */
    private ?ValueStash $_value_stash = null;

    /**
     * The model's $relationships attributes.
     *
     * $relationships attributes will not be saved to the database, and are
     * primarily used to hold relationships.
     * __set and __get need the relationship method defined on the model to determine if the relationship exists.
     */
    public array $relationships {
        &get {
            return $this->value_stash()->all();
        }
        set(array $value) {
            $this->value_stash()->replace_all($value);
        }
    }

    /**
     * A clone of the model gets its own stash.
     */
    public function __clone(): void
    {
        $this->_value_stash = clone $this->value_stash();
    }

    /**
     * The model's stash, created on first use.
     */
    private function value_stash(): ValueStash
    {
        return $this->_value_stash ??= new ValueStash();
    }

    /**
     * Retrieve the value of a static property on a class. If the
     * class or the property does not exist, returns the default
     * value supplied as the third argument (which defaults to null).
     * @param string $property
     */
    public static function _get_static_property(string $class_name, string $property, mixed $default = null): mixed
    {
        if (!class_exists($class_name) || !property_exists($class_name, $property)) {
            return $default;
        }
        $properties = get_class_vars($class_name);

        return $properties[$property];
    }

    /**
     * Static method to get a table name given a class name.
     * If the supplied class has a public static property
     * named $_table, the value of this property will be
     * returned. If not, the class name will be converted using
     * the _class_name_to_table_name method method.
     * @param string $class_name
     */
    public static function _get_table_name(string $class_name): string
    {
        $specified_table_name = self::_get_static_property($class_name, '_table');
        if (is_null($specified_table_name)) {
            return self::_class_name_to_table_name($class_name);
        }

        return $specified_table_name;
    }

    /**
     * Convert a namespace to the standard PEAR underscore format.
     *
     * Then convert a class name in CapWords to a table name in
     * lowercase_with_underscores.
     *
     * Finally strip doubled up underscores
     *
     * For example, CarTyre would be converted to car_tyre. And
     * Project\Models\CarTyre would be project_models_car_tyre.
     */
    protected static function _class_name_to_table_name(string $class_name): string
    {
        return strtolower(preg_replace(
            ['/\\\\/', '/(?<=[a-z])([A-Z])/', '/__/'],
            ['_', '_$1', '_'],
            ltrim($class_name, '\\')
        ));
    }

    /**
     * Return the ID column name to use for this class. If it is
     * not set on the class, returns null.
     * @param string $class_name
     */
    public static function _get_id_column_name(string $class_name): string
    {
        return self::_get_static_property($class_name, '_id_column', self::DEFAULT_ID_COLUMN);
    }

    /**
     * Build a foreign key based on a table name. If the first argument
     * (the specified foreign key column name) is null, returns the second
     * argument (the name of the table) with the default foreign key column
     * suffix appended.
     */
    protected static function _build_foreign_key_name(?string $specified_foreign_key_name, string $table_name): string
    {
        if (!is_null($specified_foreign_key_name)) {
            return $specified_foreign_key_name;
        }

        return $table_name . self::DEFAULT_FOREIGN_KEY_SUFFIX;
    }

    /**
     * The table-first entry point: build a query on a table without a
     * model class. Finds return ORM rows, not models, and the default
     * filter does not apply.
     */
    public static function for_table(string $table_name, ?string $connection_name = null): Query
    {
        return Query::_for_table(
            $table_name,
            $connection_name ?? Query::DEFAULT_CONNECTION
        );
    }

    /**
     * The query for this model: conditions, the where_* and
     * order_by_* magic, aggregates, and finds, with the model's
     * defaultFilter() applied. Always table-wide; instance-scoped
     * querying is the relationship methods.
     */
    public static function q(): Query
    {
        return Query::_for_model(self::$auto_prefix_models . static::class);
    }

    /**
     * Create a new, unsaved model row, seeded with the given column
     * values. Every field given is written on the next save().
     */
    public static function create(?array $data = null): static
    {
        $class = self::$auto_prefix_models . static::class;
        $model = new static();
        // create(null) marks the row new, so the next save() inserts
        $model->set_orm(ORM::_new_row(
            self::_get_table_name($class),
            self::_get_static_property($class, '_connection_name', ORM::DEFAULT_CONNECTION),
            self::_get_id_column_name($class),
        )->create(null));
        if ($data !== null) {
            $model->set($data);
        }

        return $model;
    }

    /**
     * A new, unsaved row for an already-resolved model class name,
     * with its ORM attached. Unlike create(), it does not re-apply
     * $auto_prefix_models.
     *
     * @internal
     */
    public static function _new_model_row(string $model_class): Granada
    {
        $model = new $model_class();
        $model->set_orm(ORM::_new_row(
            self::_get_table_name($model_class),
            self::_get_static_property($model_class, '_connection_name', ORM::DEFAULT_CONNECTION),
            self::_get_id_column_name($model_class),
        )->create(null));

        return $model;
    }

    /**
     * Helper method to manage one-to-one relations where the foreign
     * key is on the associated table.
     * @param string $associated_class_name
     * @param string $foreign_key_name
     */
    protected function has_one(string $associated_class_name, ?string $foreign_key_name = null, ?string $foreign_key_name_in_current_models_table = null, ?string $connection_name = null): Relationship
    {
        $base_table_name  = self::_get_table_name(get_class($this));
        $foreign_key_name = self::_build_foreign_key_name($foreign_key_name, $base_table_name);

        return new Relationship(
            $this,
            'has_one',
            self::$auto_prefix_models . $associated_class_name,
            key: $foreign_key_name,
            local_key: $foreign_key_name_in_current_models_table,
            connection: $connection_name,
        );
    }

    /**
     * Helper method to manage one-to-many relations where the foreign
     * key is on the associated table.
     * @param string $associated_class_name
     * @param string $foreign_key_name
     */
    protected function has_many(string $associated_class_name, ?string $foreign_key_name = null, ?string $foreign_key_name_in_current_models_table = null, ?string $connection_name = null): Relationship
    {
        $base_table_name  = self::_get_table_name(get_class($this));
        $foreign_key_name = self::_build_foreign_key_name($foreign_key_name, $base_table_name);

        return new Relationship(
            $this,
            'has_many',
            self::$auto_prefix_models . $associated_class_name,
            key: $foreign_key_name,
            local_key: $foreign_key_name_in_current_models_table,
            connection: $connection_name,
        );
    }

    /**
     * Helper method to manage one-to-one and one-to-many relations where
     * the foreign key is on the base table.
     * @param string $associated_class_name
     * @param string $foreign_key_name
     */
    protected function belongs_to(string $associated_class_name, ?string $foreign_key_name = null, ?string $foreign_key_name_in_associated_models_table = null, ?string $connection_name = null): Relationship
    {
        $associated_class_name = self::$auto_prefix_models . $associated_class_name;
        $associated_table_name = self::_get_table_name($associated_class_name);
        $foreign_key_name      = self::_build_foreign_key_name($foreign_key_name, $associated_table_name);
        $related_key           = $foreign_key_name_in_associated_models_table ?? self::_get_id_column_name($associated_class_name);

        return new Relationship(
            $this,
            'belongs_to',
            $associated_class_name,
            key: $foreign_key_name,
            related_key: $related_key,
            connection: $connection_name,
        );
    }

    /**
     * Read and write a many-to-many relationship through a link table.
     * @param string      $associated_class_name The far model.
     * @param string|null $link_model            The link model; its table is the link table.
     * @param string|null $this_key              The link column pointing at this model.
     * @param string|null $other_key             The link column pointing at the far model.
     * @param string|null $this_table_key        The column on this model the link matches;
     *                                           empty means its id column.
     * @param string|null $other_table_key       The column on the far model the link matches;
     *                                           empty means its id column.
     */
    protected function has_many_through(string $associated_class_name, ?string $link_model = null, ?string $this_key = null, ?string $other_key = null, ?string $this_table_key = null, ?string $other_table_key = null, ?string $connection_name = null): Relationship
    {
        $base_class_name = get_class($this);

        // The class name of the link model, if not supplied, is
        // formed by concatenating the names of the base class
        // and the associated class, in alphabetical order.
        if (is_null($link_model)) {
            $model      = explode('\\', $base_class_name);
            $model_name = end($model);
            if (self::$auto_prefix_models && str_starts_with($model_name, self::$auto_prefix_models)) {
                $model_name = substr($model_name, strlen(self::$auto_prefix_models), strlen($model_name));
            }
            $class_names = [$model_name, $associated_class_name];
            sort($class_names, SORT_STRING);
            $link_model = implode('', $class_names);
        }

        // Get table names for each class
        $base_table_name       = self::_get_table_name($base_class_name);
        $associated_class_name = self::$auto_prefix_models . $associated_class_name;
        $associated_table_name = self::_get_table_name($associated_class_name);
        $link_table_name       = self::_get_table_name(self::$auto_prefix_models . $link_model);

        // Get the column names for each side of the link table
        $this_key  = self::_build_foreign_key_name($this_key, $base_table_name);
        $other_key = self::_build_foreign_key_name($other_key, $associated_table_name);

        return new Relationship(
            $this,
            'has_many_through',
            $associated_class_name,
            key: $this_key,
            other_key: $other_key,
            table: $link_table_name,
            local_key: $this_table_key,
            related_key: $other_table_key ?? self::_get_id_column_name($associated_class_name),
            connection: $connection_name,
        );
    }

    /**
     * Set the row instance associated with this Model instance.
     * @param ORM $orm
     */
    public function set_orm(ORM $orm): void
    {
        $orm->_write_rules = static::_write_rules();
        $this->orm         = $orm;
    }

    /**
     * @internal
     */
    public static function _write_rules(): Orm\WriteRules
    {
        return new Orm\WriteRules(
            soft_delete_column: static::$_soft_delete_column,
            created: static::$_auto_timestamps ? static::$_auto_timestamp_columns['created'] : null,
            updated: static::$_auto_timestamps ? static::$_auto_timestamp_columns['updated'] : null,
            ignored: static::$_auto_timestamps_ignore,
        );
    }

    /**
     * Magic getter method, allows $model->property access to data.
     */
    public function __get(string $property): mixed
    {
        return $this->_resolve_property($property);
    }

    /**
     * Resolve a property access on the model.
     *
     * Resolution order:
     *      1. get_{property_name} method defined in model (if ORM value is not null)
     *      2. A value kept from an earlier read of the same property
     *      3. missing_{property_name} method (computed on each access)
     *      4. missingonce_{property_name} method (computed once, then kept)
     *      5. Lazy-loaded relationship if a method matching the property name exists
     */
    protected function _resolve_property(string $property): mixed
    {
        $class  = static::class;
        $result = $this->orm->get($property);

        static $_has_method = [];

        if ($result !== null) {
            $method = 'get_' . $property;
            if ($_has_method[$class][$method] ??= method_exists($this, $method)) {
                return $this->$method($result);
            }

            return $result;
        }

        if (array_key_exists($property, $this->relationships)) {
            return $this->relationships[$property];
        }

        $method = 'missing_' . $property;
        if ($_has_method[$class][$method] ??= method_exists($this, $method)) {
            return $this->$method();
        }

        $method = 'missingonce_' . $property;
        if ($_has_method[$class][$method] ??= method_exists($this, $method)) {
            return $this
                ->value_stash()
                ->set_computed_value($property, $this->$method());
        }

        $method = $property;
        if ($_has_method[$class][$method] ??= method_exists($this, $method)) {
            if ($property !== self::_get_id_column_name($class)) {
                $result = $this->$method();

                if ($result instanceof Relationship) {
                    return $this
                        ->value_stash()
                        ->set_computed_value($property, $result->load_one());
                }

                if ($result instanceof Query) {
                    $relationship = $result->_relationship();

                    if ($relationship) {
                        return $this
                            ->value_stash()
                            ->set_computed_value($property, $relationship->load_one());
                    }
                }

                // A method returning a plain query is loaded as a many relation
                return $this
                    ->value_stash()
                    ->set_computed_value($property, $result->find_many());
            }
        }

        return null;
    }

    /**
     * Drop the computed values, so the next read of those properties
     * works them out again. Set values stay.
     */
    public function clear_computed_values(): static
    {
        $this->value_stash()->clear_computed_values();

        return $this;
    }

    /**
     * Magic setter method, allows $model->property = 'value' access to data.
     * Added: use Model methods to determine if a relationship exists and populate it on $relationships instead of properties
     */
    public function __set(string $property, mixed $value): void
    {
        $this->set($property, $value);
    }

    /**
     * Magic isset method, allows isset($model->property) to work correctly.
     */
    public function __isset(string $property): bool
    {
        if (array_key_exists($property, $this->relationships)) {
            return true;
        }
        if ($this->orm->__isset($property)) {
            return true;
        }

        $prefix_methods = ['get_', 'missing_', 'missingonce_', ''];
        foreach ($prefix_methods as $prefix) {
            if (method_exists($this, $prefix . $property)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Getter method, allows $model->get('property') access to data
     */
    public function get(string $property): mixed
    {
        return $this->orm->get($property);
    }

    /**
     * Setter method, allows $model->set('property', 'value') access to data.
     * @param string|null $value
     */
    public function set(array|string $property, mixed $value = null)
    {
        if (!is_array($property)) {
            $property = [$property => $value];
        }
        foreach ($property as $field => $val) {
            if (method_exists($this, $method = 'set_' . $field)) {
                $property[$field] = $this->$method($val);
                $value            = null;
            } elseif (method_exists($this, $field)) {
                $this->value_stash()->set_value($field, $val);
            }
        }

        return $this->orm->set($property, $value);
    }

    /**
     * Run each value through its column's set_ method, so objects and
     * dates become database values.
     * Expression values pass through.
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function _convert_update_values(array $values): array
    {
        foreach ($values as $field => $value) {
            if ($value instanceof Orm\Expression) {
                continue;
            }
            if (method_exists($this, $method = 'set_' . $field)) {
                $values[$field] = $this->$method($value);
            }
        }

        return $values;
    }

    /**
     * Setter method, allows $model->set_expr('property', 'value') access to data.
     * @param string|null $value
     */
    public function set_expr(string $property, mixed $value = null): static
    {
        $this->orm->set_expr($property, $value);

        return $this;
    }

    /**
     * Increment a counter column with one atomic write and return the
     * value read back from the row.
     */
    public function increment(string $column, float|int $amount = 1): mixed
    {
        return $this->orm->increment($column, $amount);
    }

    /**
     * Decrement a counter column with one atomic write and return the
     * value read back from the row.
     */
    public function decrement(string $column, float|int $amount = 1): mixed
    {
        return $this->orm->decrement($column, $amount);
    }

    /**
     * ArrayAccess
     * @param int|string $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess
     * @param int|string $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess
     * @param int|string $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess
     * @param int|string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->orm->offsetUnset($offset);
    }

    /**
     * Check whether the given field has changed since the object was created or saved
     * @param string $property
     * @return bool
     */
    public function is_dirty(string $property): bool
    {
        return $this->orm->is_dirty($property);
    }

    /**
     * Check whether the any fields have changed since the object was created or saved
     * @return bool
     */
    public function is_any_dirty(): bool
    {
        return $this->orm->is_any_dirty();
    }

    /**
     * Get the list of fields that need updating on next save
     */
    public function list_dirty_fields(): array
    {
        return $this->orm->list_dirty_fields();
    }

    /**
     * Check whether the given field has not changed since the object was created or saved
     * @return bool
     */
    public function is_clean(string $property): bool
    {
        return !$this->is_dirty($property);
    }

    /**
     * Get the value of this property the last time the object was created or saved
     * @return mixed
     */
    public function clean_value(string $property): mixed
    {
        return $this->orm->clean_value($property);
    }

    /**
     * Get the values of this object the last time it was created or saved
     * @return array
     */
    public function clean_values(): array
    {
        return $this->orm->clean_values();
    }

    /**
     * Check whether the model was the result of a call to create() or not
     * @return bool
     */
    public function is_new(): bool
    {
        return $this->orm->is_new();
    }

    /**
     * Wrapper for Idiorm's as_array method.
     */
    public function as_array(...$args): array
    {
        return $this->orm->as_array(...$args);
    }

    /**
     * Save the data associated with this model instance to the database.
     */
    public function save()
    {
        return $this->orm->save();
    }

    /**
     * Delete the database row associated with this model instance.
     * On a model that names $_soft_delete_column, writes the flag and
     * keeps the row; delete_forced() removes it.
     */
    public function delete(): ?bool
    {
        // If the model has no soft delete, we force delete
        return $this->write_soft_delete_flag(true) ?? $this->delete_forced();
    }

    /**
     * Delete the record from the database even if it has soft deletion enabled
     */
    public function delete_forced(): ?bool
    {
        return $this->orm->delete();
    }

    /**
     * Restore the soft-deleted record
     */
    public function restore(): ?bool
    {
        return $this->write_soft_delete_flag(false) ?? false;
    }

    /**
     * Set or clear the soft-delete flag on this row and save.
     * Returns null when the model has no soft deletes, so delete() can
     * fall through to a real delete.
     */
    private function write_soft_delete_flag(bool $deleted): ?bool
    {
        $column = $this->orm
            ->_write_rules
            ->soft_delete_column;
        if ($column === null) {
            return null;
        }

        if ($this->is_new()) {
            return true;
        }

        if ($this->id() === null) {
            return true;
        }

        $this->$column = $deleted ? 1 : 0;

        return $this->save();
    }

    /**
     * Get the database ID of this model instance.
     */
    public function id(): mixed
    {
        return $this->orm->id();
    }

    /**
     * Is this record the first result in a foreach()
     * @return boolean
     */
    public function isFirstResult(): bool
    {
        return ResultSet::is_first($this);
    }

    /**
     * Is this record the last result in a foreach()
     * @return boolean
     */
    public function isLastResult(): bool
    {
        return ResultSet::is_last($this);
    }

    /**
     * Hydrate this model instance with an associative array of data.
     * WARNING: The keys in the array MUST match with columns in the
     * corresponding database table. If any keys are supplied which
     * do not match up with columns, the database will throw an error.
     */
    public function hydrate(array $data): static
    {
        $this->orm
            ->hydrate($data)
            ->force_all_dirty();

        return $this;
    }

    public function get_resultSetClass(): string
    {
        return static::$resultSetClass;
    }

    /**
     * A call that is not a model method names a relationship write,
     * such as attach_roles.
     */
    public function __call(string $method, array $parameters): mixed
    {
        [$relationship, $write] = $this->resolve_junction_write($method);
        if ($relationship !== null) {
            return $relationship->$write(...$parameters);
        }

        throw new \Error('Call to undefined method ' . static::class . "::{$method}()");
    }

    /**
     * The relationship a relation method such as widgets() declares, or
     * null when the method is absent or does not return one. Chained
     * calls return the query, with the relationship attached.
     *
     * @internal
     */
    public function _relationship(string $relation): ?Relationship
    {
        if (!method_exists($this, $relation)) {
            return null;
        }

        $result = $this->$relation();
        if ($result instanceof Query) {
            $result = $result->_relationship();
        }

        return $result instanceof Relationship ? $result : null;
    }

    /**
     * The relationship and write a method such as attach_roles names.
     * Any other name gives [null, null].
     *
     * @return array{Relationship|null, string|null}
     */
    private function resolve_junction_write(string $method): array
    {
        foreach ([
            'attach_' => 'attach',
            'detach_' => 'detach',
            'sync_'   => 'sync',
        ] as $prefix => $write) {
            if (!str_starts_with($method, $prefix)) {
                continue;
            }

            $relationship = $this->_relationship(substr($method, strlen($prefix)));
            if ($relationship === null) {
                return [null, null];
            }

            return [$relationship, $write];
        }

        return [null, null];
    }
}
