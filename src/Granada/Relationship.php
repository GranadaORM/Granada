<?php

namespace Granada;

/**
 * Holds what a relationship method specified: the kind, the related class,
 * the key columns, and for has_many_through the link table.
 *
 * A relationship method attaches it to the query it returns, and loading
 * reads it to fetch the related models. A has_many_through relationship
 * also writes its link table, through attach(), detach(), and sync().
 *
 * Calls chained on the relationship run on that query. Calls that change
 * the query are remembered, so eager loading can rebuild it without the
 * parent's own condition.
 *
 * @internal
 */
class Relationship
{
    /** @var array<int, array{string, mixed[]}> */
    private array $filters = [];
    private ?Query $query  = null;

    private ?string $order_column = null;

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
     *                                 column.
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
        public readonly ?string $related_key = null,
        public readonly ?string $connection = null,
    ) {}

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
            $children         = $this->model->_relationship_query($this, hide_soft_deleted: false);
            $soft_delete_flag = $children->_soft_delete ? $children->_soft_delete_column : null;

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
     * The members as id => data pairs. An id given with no data maps to
     * an empty array.
     *
     * @param mixed[] $members
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

    public function __call(string $method, array $args): mixed
    {
        $this->query ??= $this->model->_relationship_query($this);
        $result = $this->query->$method(...$args);

        if ($result instanceof Query) {
            $this->filters[]       = [$method, $args];
            $result->_relationship = $this;
        }

        return $result;
    }

    /** @return array<int, array{string, mixed[]}> */
    public function filters(): array
    {
        return $this->filters;
    }
}
