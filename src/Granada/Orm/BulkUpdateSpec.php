<?php

namespace Granada\Orm;

/**
 * Spec for an UPDATE that writes the same values to every row matching
 * its where conditions.
 */
class BulkUpdateSpec
{
    public function __construct(
        public readonly string $table_name,
        public readonly Dialect $dialect,
        /** @var array<string, mixed> */
        public readonly array $set = [],
        /** @var Condition[] */
        public readonly array $where_conditions = [],
    ) {}
}
