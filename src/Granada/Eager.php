<?php

namespace Granada;

/**
 * @author Erik Wiesenthal
 * @email erikwiesenthal@hotmail.com
 * @project Paris / Granada
 * @copyright 2012
 *
 * Mashed from eloquent https://github.com/taylorotwell/eloquent
 * to works with idiorm + http://github.com/j4mie/paris/
 */

use Exception;

/**
 * Parses the with() marks on a query and passes each named relationship
 * the rows to load. The relationship builds its eager query and writes
 * each parent's value for the relation.
 *
 * @internal
 */
class Eager
{
    /**
     * Attempts to execute any relationship defined for eager loading
     *
     * @param Query $orm
     * @param array|ResultSet $results
     */
    public static function hydrate(Query $orm, array|ResultSet $results, bool $return_result_set = false): array|ResultSet
    {
        if (!$results) {
            return $results;
        }

        $model = null;
        foreach ($results as $result) {
            $model = $result;
            break;
        }
        if (!$model) {
            return $results;
        }

        foreach ($orm->relationships as $entry) {
            $mark = self::relationship_mark($entry);

            if (!method_exists($model, $mark['name'])) {
                throw new Exception("Attempting to eager load [{$mark['name']}], but the relationship is not defined.", 500);
            }

            $relationship = $model->{$mark['name']}(...$mark['args']);

            if ($relationship instanceof Query) {
                // Chained calls return the query, with the relationship attached
                $relationship = $relationship->_relationship();
            }
            if (!$relationship instanceof Relationship) {
                continue;
            }

            if ($mark['query'] instanceof \Closure) {
                $relationship->constrain($mark['query']);
            }

            if ($mark['with']) {
                $relationship->with($mark['with']);
            }

            $relationship->load(
                $results instanceof ResultSet ? $results->as_array() : $results,
                $mark['name'],
                $return_result_set
            );
        }

        return $results;
    }

    /**
     * The fields a with() mark contains, in the shape it was written.
     *
     * @return array{
     *     name: string,
     *     with: mixed,
     *     args: array<int|string, mixed>,
     *     query: \Closure|null
     * }
     */
    private static function relationship_mark(mixed $mark): array
    {
        $name  = $mark;
        $with  = null;
        $args  = [];
        $query = null;

        if (is_array($mark)) {
            $name  = key($mark);
            $value = $mark[$name];

            if ($value instanceof \Closure) {
                $query = $value;
            } else {
                if (isset($value['with'])) {
                    $with = $value['with'];
                    unset($value['with']);
                }

                $args = (array) $value;
            }
        }

        $dot = strpos($name, '.');

        if ($dot > 0) {
            $with = substr($name, $dot + 1);
            $name = substr($name, 0, $dot);
            $args = [];
        }

        return [
            'name'  => $name,
            'with'  => $with,
            'args'  => $args,
            'query' => $query,
        ];
    }
}
