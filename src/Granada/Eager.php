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
 * @internal
 */
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

            if ($relationship instanceof Orm\Wrapper) {
                // Chained calls return the query, with the relationship attached
                $relationship = $relationship->_relationship;
            }
            if (!$relationship instanceof Relationship) {
                continue;
            }

            self::eagerly($relationship, $results, $include, $return_result_set);
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
     * @param Relationship $relationship
     * @param array|ResultSet $parents
     * @param array<string, mixed> $include
     * @param boolean $return_result_set
     * @return void
     */
    private static function eagerly(Relationship $relationship, array|ResultSet &$parents, array $include, bool $return_result_set): void
    {
        $query = Granada::_eager_relationship_query($relationship);

        if ($include['query'] instanceof \Closure) {
            // Might have non-standard selects, we need to clear them to set a limited subset
            $query->clear_select();
            // Fetch the further filters from the callback
            ($include['query'])($query);
            // Add required columns as minimum to do the with relationahip
            self::auto_include_required_columns($query, $relationship);
        }

        if ($include['with']) {
            $query->with($include['with']);
        }

        $result_set_class = null;
        if ($return_result_set) {
            $result_set_class = ORM::_result_set_class($relationship->class::$resultSetClass);
        }

        // Eager and lazy loading must hold the same type. "Many"
        // relationships start as an empty result set when
        // return_result_sets is on, an empty array when it is off.
        // "One" relationships start as null.
        foreach ($parents as &$parent) {
            if (in_array($relationship->kind, ['has_many', 'has_many_through'])) {
                $parent->relationships[$include['name']] = $result_set_class === null ? [] : new $result_set_class();
            } else {
                $parent->relationships[$include['name']] = null;
            }
        }

        switch ($relationship->kind) {
            case 'has_one':
                self::has_one($query, $parents, $relationship->keys[0], $include['name']);
                break;

            case 'has_many':
                self::has_many($query, $parents, $relationship->keys[0], $include['name']);
                break;

            case 'belongs_to':
                self::belongs_to($query, $parents, $relationship->keys[0], $include['name']);
                break;

            default:
                self::has_many_through($query, $parents, $relationship->keys, $relationship->table, $include['name']);
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
    private static function has_one(Orm\Wrapper $relationship, array|ResultSet &$parents, array|string $relating_key, string $include): void
    {
        $keys    = static::getKeys($parents);
        $related = $relationship->where_in($relating_key, $keys)->find_many();

        $parents_by_id = self::parents_by_id($parents);

        foreach ($related as $child) {
            foreach ($parents_by_id[$child[$relating_key]] ?? [] as $parent) {
                if (isset($parent->relationships[$include])) {
                    continue;
                }

                $parent->relationships[$include] = $child;
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
    private static function has_many(Orm\Wrapper $relationship, array|ResultSet &$parents, array|string $relating_key, string $include): void
    {
        $keys    = static::getKeys($parents);
        $related = $relationship->where_in($relating_key, $keys)->find_many();

        $parents_by_id = self::parents_by_id($parents);

        foreach ($related as $child) {
            foreach ($parents_by_id[$child[$relating_key]] ?? [] as $parent) {
                $parent->relationships[$include][$child->id] = $child;
            }
        }
    }

    /**
     * The parents grouped by id. A repeated id lists every parent row
     * with that id.
     *
     * @param  array|ResultSet  $parents
     * @return array<int|string, array>
     */
    private static function parents_by_id(array|ResultSet $parents): array
    {
        $parents_by_id = [];
        foreach ($parents as $parent) {
            $parents_by_id[$parent->id][] = $parent;
        }

        return $parents_by_id;
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
    private static function belongs_to(Orm\Wrapper $relationship, array|ResultSet &$parents, string $relating_key, string $include): void
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
    private static function has_many_through(Orm\Wrapper $relationship, array|ResultSet &$parents, array $relating_key, string $relating_table, string $include): void
    {
        $keys = static::getKeys($parents);

        // The foreign key is added to the select to allow us to easily match the models back to their parents.
        // Otherwise, there would be no apparent connection between the models to allow us to match them.
        $children = $relationship->select($relating_table . '.' . $relating_key[0])->where_in($relating_table . '.' . $relating_key[0], $keys)
            ->non_associative()
            ->find_many();

        // The parent list may be position-keyed, so children match by
        // parent id, not by list position. Repeated ids mean repeated
        // parent rows, and each of those rows gets the children.
        $parents_by_id = self::parents_by_id($parents);

        foreach ($children as $child) {
            $parent_id = $child[$relating_key[0]];
            unset($child[$relating_key[0]]);  // foreign key does not belongs to the related model

            foreach ($parents_by_id[$parent_id] ?? [] as $parent) {
                // no associative result sets for has_many_through, so we can have multiple rows with the same primary_key
                $parent->relationships[$include][] = $child;
            }
        }
    }

    private static function auto_include_required_columns(Orm\Wrapper $query, Relationship $relationship): void
    {
        switch ($relationship->kind) {
            case 'belongs_to':
                $query->select(Granada::_get_id_column_name($relationship->class));
                break;

            case 'has_one':
            case 'has_many':
                $query->select($relationship->keys[0]);
                break;
        }
    }
}
