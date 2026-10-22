<?php

namespace Granada\Orm;

use Closure;
use Granada\Eager;
use Granada\Granada;
use Granada\ORM;
use Granada\ResultSet;

/**
 * Turns the rows fetched by a query into keyed model instances and
 * runs the query's eager loads.
 *
 * @internal
 */
class RowHydrator
{
    /**
     * @param Closure(array<string, mixed>): (ORM|Granada) $row_instance Builds the instance for one raw row.
     */
    public function __construct(
        private readonly string $connection_name,
        private readonly ?string $id_column,
        private readonly bool $associative_results,
        private readonly Closure $row_instance,
    ) {}

    /**
     * Keyed instances for the fetched rows. A row is keyed by its id
     * when associative results are on and the id is truthy, and by
     * its position otherwise. With duplicate ids the last row wins.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int|string, ORM|Granada>
     */
    public function instances(array $rows): array
    {
        if (!$rows) {
            return [];
        }

        $this->resolve_connection();

        $instances = [];
        foreach ($rows as $index => $row) {
            $instance        = ($this->row_instance)($row);
            $id              = $instance->id();
            $key             = ($this->associative_results && $this->id_column !== null && $id) ? $id : $index;
            $instances[$key] = $instance;
        }

        return $instances;
    }

    /**
     * The instance for one raw row, with no array key.
     *
     * @param array<string, mixed> $row
     */
    public function instance(array $row): Granada|ORM
    {
        $this->resolve_connection();

        return ($this->row_instance)($row);
    }

    /**
     * Loads the query's eager relationships onto the instances. Empty
     * results pass through untouched.
     *
     * @param array<int|string, ORM|Granada>|ResultSet $instances
     * @return array<int|string, ORM|Granada>|ResultSet
     */
    public function many(\Granada\Query $query, array|ResultSet $instances, bool $return_result_set): array|ResultSet
    {
        if ($instances instanceof ResultSet) {
            if (!$instances->has_results()) {
                return $instances;
            }
        } else {
            if (!$instances) {
                return $instances;
            }
        }

        $this->place_related_counts($query, $instances);

        return Eager::hydrate($query, $instances, $return_result_set);
    }

    /**
     * The single instance for find_one. Eager::hydrate needs keyed
     * results, so the instance is hydrated as a one-entry array.
     * Null passes through.
     */
    public function one(\Granada\Query $query, null|Granada|ORM $instance, bool $return_result_set): mixed
    {
        if ($instance === null) {
            return null;
        }

        $this->resolve_connection();

        $key     = ($this->associative_results && $this->id_column !== null) ? ($instance->id() ?? '') : 0;
        $results = [$key => $instance];
        $this->place_related_counts($query, $results);
        Eager::hydrate($query, $results, $return_result_set);

        return $results[$key];
    }

    /**
     * Move each related count out of the row data and onto the model's
     * relationships, as a set value the way eager loads are stored.
     * The value reads back from the alias, {relation}_count or
     * {relation}_exists.
     *
     * @param array<int|string, ORM|Granada>|ResultSet $instances
     */
    private function place_related_counts(\Granada\Query $query, array|ResultSet $instances): void
    {
        if ($query->related_counts === []) {
            return;
        }

        foreach ($instances as $instance) {
            $data = $instance->as_array();
            foreach ($query->related_counts as $alias => $related_count) {
                if (!array_key_exists($alias, $data)) {
                    continue;
                }

                $instance->offsetUnset($alias);
                $instance->relationships[$alias] = $related_count->exists
                    ? (bool) $data[$alias]
                    : (int) $data[$alias];
            }
        }
    }

    /**
     * Resolves the connection once for the whole result. Row
     * instances are built without one of their own.
     */
    private function resolve_connection(): void
    {
        ORM::get_db($this->connection_name);
    }
}
