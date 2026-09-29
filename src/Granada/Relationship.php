<?php

namespace Granada;

/**
 * Holds what a relationship method specified: the kind, the related class,
 * the keys, and for has_many_through the join table.
 *
 * A relationship method attaches it to the query it returns, and loading
 * reads it to fetch the related models.
 *
 * Calls chained on the relationship run on that query. Calls that change the
 * query are remembered, so eager loading can rebuild it without the parent's
 * own condition.
 *
 * @internal
 */
class Relationship
{
    /** @var array<int, array{string, mixed[]}> */
    private array $filters      = [];
    private ?Orm\Wrapper $query = null;

    /**
     * @param string[]    $keys        For has_one and has_many: the foreign
     *                                 key on the associated table. For
     *                                 belongs_to: the foreign key on this
     *                                 model. For has_many_through: the two
     *                                 join table columns, this model's first.
     * @param string|null $table       The join table, for has_many_through.
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
        public readonly array $keys,
        public readonly ?string $table = null,
        public readonly ?string $local_key = null,
        public readonly ?string $related_key = null,
        public readonly ?string $connection = null,
    ) {}

    public function __call(string $method, array $args): mixed
    {
        $this->query ??= $this->model->_relationship_query($this);
        $result = $this->query->$method(...$args);

        if ($result instanceof Orm\Wrapper) {
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
