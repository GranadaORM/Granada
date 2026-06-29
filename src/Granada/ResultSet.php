<?php

namespace Granada;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;

/**
 * A result set class for working with collections of model instances
 * @author Simon Holywell <treffynnon@php.net>
 *
 * @method integer id() Get the id of this record
 */
class ResultSet implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * The current result set as an array
     * @var array
     */
    /** @var array<mixed, mixed> */
    protected array $_results = [];

    /**
     * Optionally set the contents of the result set by passing in array
     * @param array<mixed, mixed> $results
     */
    public function __construct(array $results = [])
    {
        $this->set_results($results);
    }

    /**
     * Set the contents of the result set by passing in array
     * @param array<mixed, mixed> $results
     */
    public function set_results(array $results): void
    {
        $this->_results = $results;
    }

    /**
     * Get the current result set as an array
     * @return array
     */
    public function get_results(): array
    {
        return $this->_results;
    }

    /**
     * Determine if the result set is empty
     * @return boolean
     */
    public function has_results(): bool
    {
        return !empty($this->_results);
    }

    /**
     * Get the current result set as an array
     * @return array
     */
    public function as_array(): array
    {
        return $this->get_results();
    }

    /**
     * Get the current result set as an array
     * @return string
     */
    public function as_json(): string
    {
        $result = [];
        foreach ($this->_results as $key => $value) {
            $result[] = $value->as_array();
        }

        return json_encode($result);
    }

    /**
     * Get the array keys (primary keys of the results)
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->_results);
    }

    /**
     * Get the first element of the result set
     * @return Model
     */
    public function first(): mixed
    {
        return reset($this->_results);
    }

    /**
     * Get the last element of the result set
     * @return Model
     */
    public function last(): mixed
    {
        return end($this->_results);
    }

    /**
     * Push an element on the result set
     * @return static
     */
    public function add(mixed $value): static
    {
        array_push($this->_results, $value);

        return $this;
    }

    public function rewind(): mixed
    {
        return reset($this->_results);
    }

    public function current(): mixed
    {
        return current($this->_results);
    }

    public function key(): mixed
    {
        return key($this->_results);
    }

    public function next(): mixed
    {
        return next($this->_results);
    }

    public function valid(): bool
    {
        return isset($this->_results[$this->id()]);
    }

    /**
     * Get the number of records in the result set
     * @return int
     */
    public function count(): int
    {
        return count($this->_results);
    }

    /**
     * Get an iterator for this object. In this case it supports foreaching
     * over the result set.
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        // Set first/last flags
        $count         = count($this->_results);
        $result_number = 0;
        foreach ($this->_results as $idx => $result) {
            $result_number++;
            $this->_results[$idx]->_isFirstResult = (1 === $result_number);
            $this->_results[$idx]->_isLastResult  = ($count === $result_number);
        }

        return new ArrayIterator($this->_results);
    }

    /**
     * ArrayAccess
     * @param int|string $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->_results[$offset]);
    }

    /**
     * ArrayAccess
     * @param int|string $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->_results[$offset];
    }

    /**
     * ArrayAccess
     * @param int|string|null $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->_results[] = $value;
        } else {
            $this->_results[$offset] = $value;
        }
    }

    /**
     * ArrayAccess
     * @param int|string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->_results[$offset]);
    }

    /**
     * Call a method on all models in a result set. This allows for method
     * chaining such as setting a property on all models in a result set or
     * any other batch operation across models.
     * @example ORM::for_table('Widget')->find_many()->set('field', 'value')->save();
     * @param string $method
     * @param array $params
     * @return static
     */
    public function __call(string $method, array $params = []): static
    {
        foreach ($this->_results as $model) {
            call_user_func_array([$model, $method], $params);
        }

        return $this;
    }
}
