<?php

namespace Granada\Orm;

/**
 * Spec for the reorder UPDATE: one CASE writes each listed row's
 * position to the order column.
 */
class ReorderSpec
{
    /**
     * @param array<int|string, int> $positions row id => position
     * @param Condition[]            $where_conditions
     */
    public function __construct(
        public readonly string $table_name,
        public readonly Dialect $dialect,
        public readonly string $id_column,
        public readonly string $order_column,
        public readonly array $positions = [],
        public readonly array $where_conditions = [],
    ) {}
}
