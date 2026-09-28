<?php

namespace Granada;

/**
 * A stash of values held for the request, so the model does not repeat
 * database work.
 *
 * Computed values are ones the model worked out, so a later read of the
 * property is free. Set values were put in from outside the model:
 * eager-load results, set() values and direct writes.
 * clear_computed_values() drops the computed ones; set values stay.
 */
final class ValueStash
{
    /**
     * @var array<string, mixed>
     */
    private array $computed_values = [];

    /**
     * @var array<string, mixed>
     */
    private array $set_values = [];

    /**
     * Which array each property name belongs to: 'computed' or 'set'.
     * The first write of the name decides.
     *
     * @var array<string, 'computed'|'set'>
     */
    private array $types = [];

    /**
     * Everything in the stash, merged into one array.
     * Direct writes to $relationships land here, and are moved into the
     * two arrays by apply_direct_writes().
     *
     * @var array<string, mixed>|null
     */
    private ?array $all = null;

    /**
     * Everything in the stash, merged into one array.
     */
    public function &all(): array
    {
        if ($this->all === null) {
            $this->merge();
        }

        return $this->all;
    }

    /**
     * Replace the whole stash with one array.
     */
    public function replace_all(array $values): void
    {
        $this->all = $values;
        $this->apply_direct_writes();
    }

    /**
     * Set a value the model worked out, so the next read is free.
     *
     * @return mixed The value that was set.
     */
    public function set_computed_value(string $property, mixed $value): mixed
    {
        return $this->store($property, $value, 'computed');
    }

    /**
     * Set a value that came from outside the model.
     */
    public function set_value(string $property, mixed $value): void
    {
        $this->store($property, $value, 'set');
    }

    /**
     * Drop the computed values, so the next read works them out again.
     * Set values stay.
     */
    public function clear_computed_values(): void
    {
        $this->apply_direct_writes();
        $this->computed_values = [];
        foreach ($this->types as $property => $type) {
            if ($type === 'computed') {
                unset($this->types[$property]);
            }
        }
        $this->merge();
    }

    /**
     * Write the value under its type. The first write of a property name
     * fixes the type.
     *
     * @return mixed The value that was set.
     */
    private function store(string $property, mixed $value, string $default): mixed
    {
        $this->apply_direct_writes();
        $type = $this->types[$property] ??= $default;
        if ($type === 'computed') {
            $this->computed_values[$property] = $value;
        } else {
            $this->set_values[$property] = $value;
        }
        $this->merge();

        return $value;
    }

    /**
     * Move direct writes into the two arrays.
     * A property name that is new counts as set.
     */
    private function apply_direct_writes(): void
    {
        if ($this->all === null) {
            return;
        }
        foreach ($this->all as $property => $value) {
            $type = $this->types[$property] ??= 'set';
            if ($type === 'computed') {
                $this->computed_values[$property] = $value;
            } else {
                $this->set_values[$property] = $value;
            }
        }
        foreach ($this->types as $property => $type) {
            if (array_key_exists($property, $this->all)) {
                continue;
            }
            unset($this->types[$property]);
            if ($type === 'computed') {
                unset($this->computed_values[$property]);
            } else {
                unset($this->set_values[$property]);
            }
        }
    }

    private function merge(): void
    {
        $this->all = array_replace($this->set_values, $this->computed_values);
    }
}
