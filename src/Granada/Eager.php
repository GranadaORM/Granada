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

class Eager
{
    /**
     * Attempts to execute any relationship defined for eager loading
     *
     * @param Orm\Wrapper $orm
     * @param array|ResultSet $results
     */
    public static function hydrate(Orm\Wrapper $orm, array|ResultSet &$results, bool $return_result_set = false): array|ResultSet
    {
        if (!$results) {
            return $results;
        }
        foreach ($orm->relationships as $include) {
            $relationship       = false;
            $relationship_with  = null;
            $relationship_args  = [];
            $relationship_query = null;

            if (is_array($include)) {
                $relationship = key($include);
                $value        = $include[$relationship];

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
                $relationship = $include;
            }

            if ($pos = strpos($relationship, '.')) {
                $relationship_with = substr($relationship, $pos + 1, strlen($relationship));
                $relationship      = substr($relationship, 0, $pos);
                $relationship_args = [];
            }

            $relationship = [
                'name'  => $relationship,
                'with'  => $relationship_with,
                'args'  => (array) $relationship_args,
                'query' => $relationship_query,
            ];

            // check if relationship exists on the model
            $model = $orm->create();

            if (!method_exists($model, $relationship['name'])) {
                throw new Exception("Attempting to eager load [{$relationship['name']}], but the relationship is not defined.", 500);
            }

            self::eagerly($model, $results, $relationship, $return_result_set);
        }

        return $results;
    }

    /**
     * return the associative keys of a result set or the ids of an array of objects
     * @param  array|ResultSet  $parents ResultSet or Array to check for keys
     * @return array<int, mixed>           array of primary keys
     */
    public static function getKeys(array|ResultSet $parents): array
    {
        $keys    = [];
        $parents = ($parents instanceof ResultSet) ? $parents->as_array() : $parents;

        if (key($parents) === 0) {
            $count = count($parents);
            for ($i = 0; $i < $count; $i++) {
                $keys[] = $parents[$i]->id;
            }

            return $keys;
        }

        return array_keys($parents);
    }

    /**
     * Eagerly load a relationship.
     *
     * @param Granada $model
     * @param array|ResultSet $parents
     * @param array<string, mixed> $include
     * @param boolean $return_result_set
     * @return void
     */
    private static function eagerly(Granada $model, array|ResultSet &$parents, array $include, bool $return_result_set): void
    {
        $relationship = call_user_func_array([$model, $include['name']], $include['args']);
        if (!$relationship) {
            return;
        }

        $relationship->reset_relation();

        if ($include['query'] instanceof \Closure) {
            // Might have non-standard selects, we need to clear them to set a limited subset
            $relationship->clear_select();
            // Fetch the further filters from the callback
            ($include['query'])($relationship);
            // Add required columns as minimum to do the with relationahip
            self::auto_include_required_columns($relationship, $model);
        }

        if ($include['with']) {
            $relationship->with($include['with']);
        }

        // Initialize the relationship attribute on the parents. As expected, "many" relationships
        // are initialized to an array and "one" relationships are initialized to null.
        // added: many relationships are reset to array since we don't know yet the resultSet applicable
        foreach ($parents as &$parent) {
            $parent->relationships[$include['name']] = (in_array($model->relating, ['has_many', 'has_many_through'])) ? [] : null;
        }

        if (in_array($relating = $model->relating, ['has_one', 'has_many', 'belongs_to'])) {
            self::$relating($relationship, $parents, $model->relating_key, $include['name'], $return_result_set);
        } else {
            self::has_many_through($relationship, $parents, $model->relating_key, $model->relating_table, $include['name'], $return_result_set);
        }
    }

    /**
     * Eagerly load a 1:1 relationship.
     *
     * @param  Orm\Wrapper  $relationship
     * @param  array|ResultSet  $parents
     * @param  string|array  $relating_key
     * @param  string  $include
     * @return void
     */
    private static function has_one(Orm\Wrapper $relationship, array|ResultSet &$parents, array|string $relating_key, string $include, bool $return_result_set): void
    {
        $keys    = static::getKeys($parents);
        $related = $relationship->where_in($relating_key, $keys)->find_many();

        // if parents is not a associative array
        if (array_key_first((array) $parents) === 0) {
            $results = [];
            foreach ($related as $key => $child) {
                if (isset($results[$child[$relating_key]])) {
                    continue;
                }

                $results[$child[$relating_key]] = $child;
            }

            foreach ($parents as $p_key => $parent) {
                foreach ($results as $r_key => $result) {
                    if ($parent->id != $r_key) {
                        continue;
                    }

                    $parents[$p_key]->relationships[$include] = $result;
                }
            }
        } else {
            foreach ($related as $key => $child) {
                if (isset($parents[$child->$relating_key]->relationships[$include])) {
                    continue;
                }

                $parents[$child->$relating_key]->relationships[$include] = $child;
            }
        }
    }

    /**
     * Eagerly load a 1:* relationship.
     *
     * @param  Orm\Wrapper  $relationship
     * @param  array|ResultSet  $parents
     * @param  string|array  $relating_key
     * @param  string  $include
     * @return void
     */
    private static function has_many(Orm\Wrapper $relationship, array|ResultSet &$parents, array|string $relating_key, string $include, bool $return_result_set): void
    {
        $keys    = static::getKeys($parents);
        $related = $relationship->where_in($relating_key, $keys)->find_many();

        // if parents is not a associative array
        if (array_key_first((array) $parents) === 0) {
            $results = [];
            foreach ($related as $key => $child) {
                if (empty($results[$child[$relating_key]]) && $return_result_set) {
                    $resultSetClass = $child->get_resultSetClass();

                    $results[$child[$relating_key]] = new $resultSetClass();
                }
                $results[$child[$relating_key]][$child->id] = $child;
            }

            foreach ($parents as $p_key => $parent) {
                foreach ($results as $r_key => $result) {
                    if ($parent->id != $r_key) {
                        continue;
                    }

                    $parents[$p_key]->relationships[$include] = $result;
                }
            }
        } else {
            // if parents is an associative array
            foreach ($related as $key => $child) {
                // if resultSet must be returned, create it if the relationships key is not defined
                if (empty($parents[$child[$relating_key]]->relationships[$include]) && $return_result_set) {
                    $resultSetClass = $child->get_resultSetClass();

                    $parents[$child->$relating_key]->relationships[$include] = new $resultSetClass();
                }
                // add the instance to the relationship array-resultSet
                $parents[$child->$relating_key]->relationships[$include][$child->id()] = $child;
            }
        }
    }

    /**
     * Eagerly load a 1:1 belonging relationship.
     *
     * @param  Orm\Wrapper  $relationship
     * @param  array|ResultSet  $parents
     * @param  string  $relating_key
     * @param  string  $include
     * @return void
     */
    private static function belongs_to(Orm\Wrapper $relationship, array|ResultSet &$parents, string $relating_key, string $include, bool $return_result_set): void
    {
        $keys = [];
        foreach ($parents as &$parent) {
            $keys[] = $parent->$relating_key;
        }

        $children = $relationship->where_id_in(array_unique($keys))->find_many();
        if ($children  instanceof ResultSet) {
            $children = $children->as_array();
        }

        foreach ($parents as &$parent) {
            if (!(array_key_exists($parent->$relating_key, $children))) {
                continue;
            }

            $parent->relationships[$include] = $children[$parent->$relating_key];
        }
    }

    /**
     * Eagerly load a many-to-many relationship.
     *
     *
     * @param  Orm\Wrapper  $relationship
     * @param  array|ResultSet  $parents
     * @param  array  $relating_key
     * @param  string  $relating_table
     * @param  string  $include
     *
     * @return void
     */
    private static function has_many_through(Orm\Wrapper $relationship, array|ResultSet &$parents, array $relating_key, string $relating_table, string $include, bool $return_result_set): void
    {
        $keys = static::getKeys($parents);

        // The foreign key is added to the select to allow us to easily match the models back to their parents.
        // Otherwise, there would be no apparent connection between the models to allow us to match them.
        $children = $relationship->select($relating_table . '.' . $relating_key[0])->where_in($relating_table . '.' . $relating_key[0], $keys)
            ->non_associative()
            ->find_many();

        foreach ($children as $child) {
            $related = $child[$relating_key[0]];
            unset($child[$relating_key[0]]);  // foreign key does not belongs to the related model

            if (empty($parents[$related]->relationships[$include]) && $return_result_set) {
                $resultSetClass = $child->get_resultSetClass();

                $parents[$related]->relationships[$include] = new $resultSetClass();
            }
            // no associative result sets for has_many_through, so we can have multiple rows with the same primary_key
            $parents[$related]->relationships[$include][] = $child;
        }
    }

    private static function auto_include_required_columns(Orm\Wrapper $relationship, Granada $model): void
    {
        switch ($model->relating) {
            case 'belongs_to':
                $relationship->select(Granada::_get_id_column_name($model->relating_class));
                break;

            case 'has_one':
            case 'has_many':
                $relationship->select($model->relating_key);
                break;
        }
    }
}
