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
 * Parses the with() marks on a query and hands each named relationship
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
    public static function hydrate(Query $orm, array|ResultSet &$results, bool $return_result_set = false): array|ResultSet
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
            $relationship       = false;
            $relationship_with  = null;
            $relationship_args  = [];
            $relationship_query = null;

            if (is_array($entry)) {
                $relationship = key($entry);
                $value        = $entry[$relationship];

                if ($value instanceof \Closure) {
                    $relationship_query = $value;
                    $relationship_args  = [];
                } else {
                    if (isset($value['with'])) {
                        $relationship_with = $value['with'];
                        unset($value['with']);
                    }
                    $relationship_args = $value;
                }
            } else {
                $relationship = $entry;
            }

            if ($pos = strpos($relationship, '.')) {
                $relationship_with = substr($relationship, $pos + 1, strlen($relationship));
                $relationship      = substr($relationship, 0, $pos);
                $relationship_args = [];
            }

            $include = [
                'name'  => $relationship,
                'with'  => $relationship_with,
                'args'  => (array) $relationship_args,
                'query' => $relationship_query,
            ];

            // check if relationship exists on the model
            if (!method_exists($model, $include['name'])) {
                throw new Exception("Attempting to eager load [{$include['name']}], but the relationship is not defined.", 500);
            }

            $relationship = $model->{$include['name']}(...$include['args']);

            if ($relationship instanceof Query) {
                // Chained calls return the query, with the relationship attached
                $relationship = $relationship->_relationship();
            }
            if (!$relationship instanceof Relationship) {
                continue;
            }

            if ($include['query'] instanceof \Closure) {
                $relationship->constrain($include['query']);
            }

            if ($include['with']) {
                $relationship->with($include['with']);
            }

            $relationship->load(
                $results instanceof ResultSet ? $results->as_array() : $results,
                $include['name'],
                $return_result_set
            );
        }

        return $results;
    }

    /**
     * Every parent's id, in parent order, via
     * Relationship::parent_column_values().
     *
     * @param array<int|string, Granada>|ResultSet $parents
     * @return mixed[]
     */
    public static function getKeys(array|ResultSet $parents): array
    {
        return Relationship::parent_column_values($parents, 'id');
    }
}
