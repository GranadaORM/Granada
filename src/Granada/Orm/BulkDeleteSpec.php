<?php

namespace Granada\Orm;

/**
 * Spec for a DELETE that removes every row matching its where conditions,
 * optionally across joins. `target` names the table or alias the delete
 * removes from (the `DELETE target FROM ...` join form).
 */
class BulkDeleteSpec
{
    public function __construct(
        public readonly string $table_name,
        public readonly string $target,
        public readonly Dialect $dialect,
        /** @var Condition[] */
        public readonly array $where_conditions = [],
        /** @var JoinSource[]|string[] */
        public readonly array $join_sources = [],
    ) {}
}
