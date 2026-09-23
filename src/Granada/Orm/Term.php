<?php

namespace Granada\Orm;

/**
 * One GROUP BY or ORDER BY entry as plain data: a column reference
 * (quoted by the Renderer) or a plain SQL expression. Column terms
 * can carry an ORDER BY direction, a natural-sort LENGTH() prefix, or
 * a field list to order by (CASE expression).
 */
final class Term
{
    private function __construct(
        public readonly string $column = '',
        public readonly string $expression = '',
        public readonly string $direction = '',
        public readonly bool $natural = false,
        public readonly array $field_list = [],
    ) {}

    public static function column(string $column, string $direction = ''): self
    {
        return new self(column: $column, direction: $direction);
    }

    public static function natural(string $column, string $direction): self
    {
        return new self(column: $column, direction: $direction, natural: true);
    }

    /** @param (string|int)[] $values plain SQL fragments */
    public static function byFieldList(string $column, array $values): self
    {
        return new self(column: $column, field_list: $values);
    }

    public static function expression(string $expr): self
    {
        return new self(expression: $expr);
    }
}
