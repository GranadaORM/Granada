<?php

namespace Granada\Orm;

/**
 * Spec for a DELETE that removes every row matching its where conditions.
 */
class BulkDeleteSpec
{
    public function __construct(
        public readonly string $table_name,
        public readonly Dialect $dialect,
        /** @var Condition[] */
        public readonly array $where_conditions = [],
    ) {}
}
