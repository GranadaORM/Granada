<?php

namespace Granada\Orm;

/**
 * Spec for one SELECT statement.
 */
class SelectSpec
{
    public function __construct(
        public readonly string $table_name,
        public readonly Dialect $dialect,
        public readonly ?string $table_alias = null,
        /** @var string[]|Aggregate[] */
        public readonly array $result_columns = ['*'],
        /** @var JoinSource[]|string[] */
        public readonly array $join_sources = [],
        public readonly bool $distinct = false,
        /** @var Condition[] */
        public readonly array $where_conditions = [],
        /** @var Condition[] */
        public readonly array $having_conditions = [],
        /** @var Term[] */
        public readonly array $group_by = [],
        /** @var Term[] */
        public readonly array $order_by = [],
        public readonly ?int $limit = null,
        public readonly ?int $offset = null,
    ) {}
}
