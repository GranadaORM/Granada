<?php

namespace Granada\Orm;

/**
 * A correlated subquery rendered as a column on the parent query: the
 * number of related rows, or whether any related row exists.
 */
final class RelatedCount
{
    /**
     * @param string  $alias    The result column name, eg comments_count.
     * @param string  $subquery The inner SELECT, correlated to the parent.
     * @param mixed[] $values   The subquery's bound values.
     * @param bool    $exists   Render EXISTS (...) rather than the count.
     */
    public function __construct(
        public readonly string $alias,
        public readonly string $subquery,
        public readonly array $values = [],
        public readonly bool $exists = false,
    ) {}
}
