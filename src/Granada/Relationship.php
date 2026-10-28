<?php

namespace Granada;

/**
 * Holds what a relationship method specified: the kind, the related class,
 * the key columns, and for has_many_through the link table.
 *
 * A relationship method attaches it to the query it returns, and loading
 * reads it to fetch the related models. The relationship builds every
 * query that loading runs: the lazy query for one parent row, the
 * eager query for many parents, and the query with no parent
 * condition, which existence filters and related counts correlate to
 * the outer query. It runs the eager load and writes each parent's
 * value for the relation. A has_many_through relationship also writes
 * its link table, through attach(), detach(), and sync().
 *
 * Calls chained on the relationship run on the lazy query. The one
 * parent condition that query holds is remembered, so the eager query
 * starts from the same conditions and replaces that condition with
 * the many-parent restriction.
 *
 * @internal
 */
class Relationship
{
    private ?Query $query = null;

    private ?Orm\Condition $parent_condition = null;

    private ?Orm\Condition $hiding_condition = null;

    private ?string $order_column = null;

    public readonly ?string $related_key;

    /**
     * @param string      $key         For has_one and has_many: the foreign key
     *                                 on the associated table. For belongs_to:
     *                                 the foreign key on this model. For
     *                                 has_many_through: the link column
     *                                 pointing at this model.
     * @param string|null $other_key   The link column pointing at the far
     *                                 model, for has_many_through.
     * @param string|null $table       The link table, for has_many_through.
     * @param string|null $local_key   The column on this model the query
     *                                 matches; empty means the id column.
     * @param string|null $related_key The column on the associated model the
     *                                 query matches; empty means its id
     *                                 column for the kinds that read it.
     * @param string|null $connection  The connection of the associated model.
     */
    public function __construct(
        private readonly Granada $model,
        public readonly string $kind,
        public readonly string $class,
        public readonly string $key,
        public readonly ?string $other_key = null,
        public readonly ?string $table = null,
        public readonly ?string $local_key = null,
        ?string $related_key = null,
        public readonly ?string $connection = null,
    ) {
        if ($related_key === null && in_array($this->kind, ['belongs_to', 'has_many_through'])) {
            $related_key = Granada::_get_id_column_name($this->class);
        }
        $this->related_key = $related_key;
    }

    /**
     * Set the link table column that holds each link's position.
     * Reads order by it, and the writes keep it up to date.
     */
    public function ordered_by(string $column): static
    {
        $this->order_column = $column;

        return $this;
    }

    public function order_column(): ?string
    {
        return $this->order_column;
    }

    /**
     * The column on the associated model this relation matches. The
     * lazy item cache keys each entry by it.
     */
    public function cache_column(): string
    {
        return $this->related_key_column();
    }

    /**
     * The columns an existence filter or related count correlates by:
     * the column on the associated model, and the column on this
     * model.
     *
     * @return array{string, string}
     */
    public function correlated_columns(): array
    {
        return [$this->related_key_column(), $this->parent_key_column()];
    }

    /**
     * The column on the associated model this relation matches by:
     * the declared key column for belongs_to, the foreign key
     * otherwise.
     */
    private function related_key_column(): string
    {
        return $this->kind === 'belongs_to'
            ? $this->related_key
            : $this->key;
    }

    /**
     * The related query with no parent condition: the related model's
     * hiding, default filter, link join, and order column, plus every
     * condition the chain added. With $hide_soft_deleted false, the
     * soft-delete hiding is left off. Existence filters and related
     * counts correlate it to the outer query.
     */
    public function query(bool $hide_soft_deleted = true): Query
    {
        if ($this->query !== null) {
            $query = clone $this->query;
            if ($this->parent_condition !== null) {
                $query->_remove_condition($this->parent_condition);
            }
            if (!$hide_soft_deleted && $this->hiding_condition !== null) {
                $query->_remove_condition($this->hiding_condition);
            }

            return $query;
        }

        $query = Query::_for_model($this->class, $this->connection, apply_model_filter: false);
        $this->finish_query($query, $hide_soft_deleted);

        return $query;
    }

    /**
     * The related query restricted to the parents whose key values
     * are in $keys. Every condition the chain added stays on it: a
     * query the chain built is cloned, and the clone holds those
     * conditions.
     *
     * @param mixed[] $keys
     */
    public function query_for_keys(array $keys, bool $hide_soft_deleted = true): Query
    {
        $query = $this->query($hide_soft_deleted);

        if ($this->table === null && !$query->_select_is_default()) {
            // The children match their parents by this column, and
            // the chain's select list does not include it, so it is
            // added here.
            $query->select($this->cache_column());
        }
        if ($this->table !== null) {
            $query->select($this->table . '.' . $this->key);
            $query->non_associative();
        }

        $query->where_in($this->restrict_column(), array_values(array_unique($keys)));

        return $query;
    }

    /**
     * Run the with() constraint closure on the relationship's query.
     * The select list is cleared first, so the closure's own select()
     * sets the columns, and loading adds the match columns to the
     * select again.
     */
    public function constrain(\Closure $constraint): void
    {
        $this->query ??= $this->one_parent_query();
        $this->query->clear_select();
        $constraint($this->query);
    }

    /**
     * Mark more relationships for eager loading on the relationship's
     * query, so the loaded children load their own related rows.
     */
    public function with(...$args): void
    {
        $this->query ??= $this->one_parent_query();
        $this->query->with(...$args);
    }

    /**
     * The related value for this relationship's own parent row: the
     * related model for a "one" kind, the list for a "many" kind.
     * Runs the query the chain built when there is one.
     */
    public function load_one(): mixed
    {
        $this->query ??= $this->one_parent_query();

        if (in_array($this->kind, ['has_one', 'belongs_to'])) {
            $orm        = $this->model->orm;
            $related_id = $this->kind === 'belongs_to'
                ? $orm->get($this->key)
                : $this->model->id();

            if ($related_id === null) {
                return null;
            }

            $cache_column = $this->cache_column();

            $related_item = LazyItemCache::get($this->class, $cache_column, $related_id);
            if ($related_item === null) {
                $related_item = $this->query->find_one();
                if ($related_item) {
                    LazyItemCache::set($this->class, $cache_column, $related_id, $related_item);
                }
            }

            return $related_item;
        }

        return $this->query->find_many();
    }

    /**
     * Eager-load this relationship for every parent row and store each
     * parent's set value under $name, the property the models read.
     * With $return_result_sets on, a "many" relationship's set value
     * is a result set; a "one" relationship's is null until a row
     * arrives.
     *
     * @param Granada[] $parents
     */
    public function load(array $parents, string $name, bool $return_result_sets): void
    {
        $result_set_class = null;
        if ($return_result_sets) {
            $result_set_class = Query::_result_set_class($this->class::$resultSetClass);
        }

        // Eager and lazy loading must hold the same type. "Many"
        // relationships start as an empty result set when
        // return_result_sets is on, an empty array when it is off.
        // "One" relationships start as null.
        foreach ($parents as $parent) {
            $parent->relationships[$name] = $this->empty_value($result_set_class);
        }

        $children = $this
            ->query_for_keys($this->parent_keys($parents))
            ->find_many();

        match ($this->kind) {
            'has_one'    => $this->load_has_one($this->parents_by_key($parents), $children, $name),
            'has_many'   => $this->load_has_many($this->parents_by_key($parents), $children, $name),
            'belongs_to' => $this->load_belongs_to($parents, $children, $name),
            default      => $this->load_has_many_through($this->parents_by_key($parents), $children, $name),
        };
    }

    /**
     * Link this model to $id, creating the link row when it is not there.
     * $data, when given, is written onto the row.
     *
     * @param array<string, mixed> $data Columns to write on the link row.
     */
    public function attach(mixed $id, array $data = []): void
    {
        $link_table = $this->require_link_table('attach');

        $this->in_transaction(function () use ($link_table, $id, $data): void {
            $link = $this->link_row($link_table, $id);

            if ($link !== null) {
                if ($data !== []) {
                    $link->set($data)->save();
                }

                return;
            }

            $link = $this->new_link_row($link_table);
            $link->set($this->key, $this->parent_key());
            $link->set($this->other_key, $id);
            if ($this->order_column !== null) {
                $link->set($this->order_column, $this->next_order($link_table, $this->order_column));
            }
            $link->set($data)->save();
        });
    }

    /**
     * Remove the link to $id, or every link this model has when $id is null.
     */
    public function detach(mixed $id = null): void
    {
        $link_table = $this->require_link_table('detach');

        $this->in_transaction(function () use ($link_table, $id): void {
            $query = $this
                ->link_query($link_table)
                ->where($this->key, $this->parent_key());
            if ($id !== null) {
                $query->where($this->other_key, $id);
            }
            $query->delete_many();
        });
    }

    /**
     * Set the links to match $members: missing links are created, unlisted
     * ones are removed, and the rest stay. $members holds ids, or ids mapped
     * to data for each link.
     *
     * Through a has_many, $members syncs the children to the rows
     * given, adding, updating, and deleting as needed. Each row is a
     * set of columns, with an optional id.
     *
     * @param mixed[] $members
     */
    public function sync(array $members): void
    {
        if ($this->kind === 'has_many') {
            $this->sync_children($members);

            return;
        }

        $link_table = $this->require_link_table('sync');

        $this->in_transaction(function () use ($link_table, $members): void {
            $data_by_id = $this->members_by_id($members);

            $existing = [];
            foreach (
                $this
                    ->link_query($link_table)
                    ->where($this->key, $this->parent_key())
                    ->find_many() as $row
            ) {
                $existing[$row->{$this->other_key}] = $row;
            }

            $order = 0;
            foreach ($data_by_id as $id => $data) {
                $order++;
                $row = $existing[$id] ?? null;
                if ($row === null) {
                    $row = $this->new_link_row($link_table);
                    $row->set($this->key, $this->parent_key());
                    $row->set($this->other_key, $id);
                }
                if ($this->order_column !== null) {
                    $data[$this->order_column] = $order;
                }
                $row->set($data)->save();
                unset($existing[$id]);
            }

            foreach (array_keys($existing) as $id) {
                $this
                    ->link_query($link_table)
                    ->where($this->key, $this->parent_key())
                    ->where($this->other_key, $id)
                    ->delete_many();
            }
        });
    }

    /**
     * Syncs the list of children to the rows given, adding, updating,
     * and deleting as needed, and sets each child's data from its row.
     *
     * @param mixed[] $rows
     */
    private function sync_children(array $rows): void
    {
        $id_column = Granada::_get_id_column_name($this->class);

        $this->in_transaction(function () use ($rows, $id_column): void {
            // Soft-delete hiding is off so a flagged child listed again
            // is matched rather than duplicated.
            $children         = $this->query_including_soft_deleted();
            $soft_delete_flag = $children->_write_rules->soft_delete_column;

            $existing = [];
            foreach ($children->find_many() as $child) {
                $existing[$child->{$id_column}] = $child;
            }

            $order = 0;
            foreach ($rows as $row) {
                $id = $row[$id_column] ?? null;
                unset($row[$id_column]);

                if ($id === null) {
                    $child = $this->class::create();
                } elseif (array_key_exists($id, $existing)) {
                    $child = $existing[$id];
                    unset($existing[$id]);
                } else {
                    continue;
                }

                $order++;
                if ($this->order_column !== null) {
                    $row[$this->order_column] = $order;
                }
                if ($soft_delete_flag !== null) {
                    $row[$soft_delete_flag] = 0;
                }
                $child->set($row);
                $child->set($this->key, $this->parent_key());
                $child->save();
            }

            foreach ($existing as $child) {
                $child->delete();
            }
        });
    }

    /**
     * The related query with the one condition on this model's row:
     * the parent condition first, then the hiding, the default
     * filter, the link join, and the order column. A has_many_through
     * read keeps every link row, so repeated rows stay in the list.
     */
    private function one_parent_query(): Query
    {
        $query = Query::_for_model($this->class, $this->connection, apply_model_filter: false);

        $column                 = $this->restrict_column();
        $value                  = $this->parent_condition_value();
        $this->parent_condition = $this->condition_for($column, $value);
        $query->where($column, $value);

        $this->finish_query($query, true);

        if ($this->table !== null) {
            $query->non_associative();
        }

        $query->_set_relationship($this);

        return $query;
    }

    /**
     * The one-parent query including soft-deleted rows, so a write
     * sees the flagged rows too.
     */
    private function query_including_soft_deleted(): Query
    {
        $this->query ??= $this->one_parent_query();
        $query = clone $this->query;
        if ($this->hiding_condition !== null) {
            $query->_remove_condition($this->hiding_condition);
        }

        return $query;
    }

    /**
     * The hiding, the model's defaultFilter(), the link join, and the
     * order column. The model's hiding and defaultFilter() go on
     * before the join: when a join is present, where() prefixes bare
     * column names with the table.
     */
    private function finish_query(Query $query, bool $hide_soft_deleted): void
    {
        $this->hiding_condition = null;
        if ($hide_soft_deleted && $query->_write_rules->soft_delete_column !== null) {
            $column                 = $this->table_name() . '.' . $query->_write_rules->soft_delete_column;
            $this->hiding_condition = Orm\Condition::compare($column, '=', 0);
            $query->where($column, 0);
        }
        $query->defaultFilter();

        if ($this->table) {
            $query->select($this->table_name() . '.*')
                ->join($this->table, ["{$this->table_name()}.{$this->related_key}", '=', "{$this->table}.{$this->other_key}"]);
        }

        // The order column lives on the link table for a has_many_through,
        // and on the associated table for a has_many.
        if ($this->order_column !== null) {
            $order_table = $this->table ?? $this->table_name();
            $query->order_by_asc($order_table . '.' . $this->order_column);
        }
    }

    /**
     * The column on the related side this relation matches by: for
     * belongs_to, the declared key column on the associated model; the
     * qualified link column for a has_many_through; the foreign key
     * otherwise.
     */
    private function restrict_column(): string
    {
        if ($this->kind === 'has_many_through') {
            return "{$this->table}.{$this->key}";
        }

        return $this->related_key_column();
    }

    /**
     * The value of the lazy query's parent condition: for belongs_to,
     * this row's foreign key; the declared local key column's value
     * when one is set, the id otherwise.
     */
    private function parent_condition_value(): mixed
    {
        if ($this->kind === 'belongs_to') {
            return $this->model->{$this->key};
        }

        return $this->local_key ? $this->model->{$this->local_key} : $this->model->id();
    }

    /**
     * The condition where() stores for one column and value. The
     * eager query drops its twin from the cloned chain.
     */
    private function condition_for(string $column, mixed $value): Orm\Condition
    {
        if (is_null($value)) {
            return Orm\Condition::is_null($this->qualified_column($column));
        }

        return Orm\Condition::compare($this->qualified_column($column), '=', $value);
    }

    private function qualified_column(string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->table_name() . '.' . $column;
    }

    /**
     * The column each parent is matched by: for belongs_to, this
     * model's foreign key; the declared local key column when one is
     * set, the id otherwise.
     */
    private function parent_key_column(): string
    {
        if ($this->kind === 'belongs_to') {
            return $this->key;
        }

        return $this->local_key ?? Granada::_get_id_column_name(get_class($this->model));
    }

    /**
     * The parent key values, one per parent.
     *
     * @param Granada[] $parents
     * @return mixed[]
     */
    private function parent_keys(array $parents): array
    {
        return self::parent_column_values($parents, $this->parent_key_column());
    }

    /**
     * The values of one column across every parent, in parent order.
     * Reads the column off each row, so a keyed map contributes its
     * rows' values, not its keys.
     *
     * @param array<int|string, Granada>|ResultSet $parents
     * @return mixed[]
     */
    public static function parent_column_values(array|ResultSet $parents, string $column): array
    {
        $values = [];
        foreach ($parents as $parent) {
            $values[] = $parent->{$column};
        }

        return $values;
    }

    /**
     * The parents grouped by the value the children match: a repeated
     * value lists every parent row with it.
     *
     * @param Granada[] $parents
     * @return array<mixed, non-empty-list<Granada>>
     */
    private function parents_by_key(array $parents): array
    {
        $column  = $this->parent_key_column();
        $grouped = [];
        foreach ($parents as $parent) {
            $grouped[$parent->{$column}][] = $parent;
        }

        return $grouped;
    }

    /**
     * The value a parent starts with, before any child row arrives.
     */
    private function empty_value(?string $result_set_class): null|array|ResultSet
    {
        if (!in_array($this->kind, ['has_many', 'has_many_through'])) {
            return null;
        }

        return $result_set_class === null ? [] : new $result_set_class();
    }

    /**
     * The first matching child becomes the parent's value; later
     * children for the same parent are skipped.
     *
     * @param array<mixed, non-empty-list<Granada>> $parents_by_key
     * @param array<int|string, Granada>|ResultSet $children
     */
    private function load_has_one(array $parents_by_key, array|ResultSet $children, string $name): void
    {
        foreach ($children as $child) {
            foreach ($parents_by_key[$child[$this->key]] ?? [] as $parent) {
                if (isset($parent->relationships[$name])) {
                    continue;
                }

                $parent->relationships[$name] = $child;
            }
        }
    }

    /**
     * The children are stored in each parent's list under the
     * child's id, so a repeated child id keeps the last row.
     *
     * @param array<mixed, non-empty-list<Granada>> $parents_by_key
     * @param array<int|string, Granada>|ResultSet $children
     */
    private function load_has_many(array $parents_by_key, array|ResultSet $children, string $name): void
    {
        foreach ($children as $child) {
            foreach ($parents_by_key[$child[$this->key]] ?? [] as $parent) {
                $parent->relationships[$name][$child->id] = $child;
            }
        }
    }

    /**
     * Each child found by its declared key column is matched to the
     * parents whose foreign key holds that value. The first child
     * for a value wins, so the eager row is the row the lazy
     * find_one() takes when several children share the value.
     *
     * @param Granada[]                             $parents
     * @param array<int|string, Granada>|ResultSet $children
     */
    private function load_belongs_to(array $parents, array|ResultSet $children, string $name): void
    {
        if ($children instanceof ResultSet) {
            $children = $children->as_array();
        }

        $children_by_key = [];
        foreach ($children as $child) {
            $value = $child[$this->related_key];
            if (array_key_exists($value, $children_by_key)) {
                continue;
            }

            $children_by_key[$value] = $child;
        }

        foreach ($parents as $parent) {
            $value = $parent->{$this->key};
            if (!array_key_exists($value, $children_by_key)) {
                continue;
            }

            $parent->relationships[$name] = $children_by_key[$value];
        }
    }

    /**
     * The link column is added to the select so each child matches
     * back to its parents, and removed from the returned rows: it
     * does not belong to the related model. Every link row becomes a
     * list entry, so repeated rows are kept.
     *
     * @param array<mixed, non-empty-list<Granada>> $parents_by_key
     * @param array<int|string, Granada>|ResultSet $children
     */
    private function load_has_many_through(array $parents_by_key, array|ResultSet $children, string $name): void
    {
        foreach ($children as $child) {
            $parent_value = $child[$this->key];
            unset($child[$this->key]);

            foreach ($parents_by_key[$parent_value] ?? [] as $parent) {
                $parent->relationships[$name][] = $child;
            }
        }
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function members_by_id(array $members): array
    {
        $data_by_id = [];
        foreach ($members as $key => $value) {
            if (is_array($value)) {
                $data_by_id[$key] = $value;
            } else {
                $data_by_id[$value] = [];
            }
        }

        return $data_by_id;
    }

    private function require_link_table(string $write): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        throw new \LogicException("Cannot {$write}: the {$this->kind} relationship has no link table");
    }

    private function link_query(string $link_table): Query
    {
        return Granada::for_table($link_table, $this->connection);
    }

    private function link_row(string $link_table, mixed $id): ?ORM
    {
        return $this->link_query($link_table)
            ->where($this->key, $this->parent_key())
            ->where($this->other_key, $id)
            ->find_one();
    }

    /**
     * An unsaved link row on the link table's connection.
     */
    private function new_link_row(string $link_table): ORM
    {
        return ORM::_new_row($link_table, $this->connection ?? ORM::DEFAULT_CONNECTION)->create(null);
    }

    /**
     * The key the link table matches this model by: the local key column
     * when one is set, otherwise the model's id.
     */
    private function parent_key(): mixed
    {
        return $this->local_key ? $this->model->{$this->local_key} : $this->model->id();
    }

    private function next_order(string $link_table, string $column): int
    {
        $max = $this
            ->link_query($link_table)
            ->where($this->key, $this->parent_key())
            ->max($column);

        return (int) $max + 1;
    }

    /**
     * Run the write in a transaction on the link table's connection.
     * A model with no link value yet has nothing to write, so it is skipped.
     */
    private function in_transaction(callable $writes): void
    {
        if ($this->parent_key() === null) {
            return;
        }

        ORM::transaction($writes, $this->connection ?? ORM::DEFAULT_CONNECTION);
    }

    private function table_name(): string
    {
        return Granada::_get_table_name($this->class);
    }

    public function __call(string $method, array $args): mixed
    {
        $this->query ??= $this->one_parent_query();
        $result = $this->query->$method(...$args);

        if ($result instanceof Query) {
            $result->_set_relationship($this);
        }

        return $result;
    }
}
