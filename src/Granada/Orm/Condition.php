<?php

namespace Granada\Orm;

/**
 * One WHERE or HAVING entry as plain data: a column, an operator and
 * the bound values. The Renderer turns it into SQL through the
 * Dialect. Only RAW holds SQL (where_raw).
 */
final class Condition
{
    public const RAW            = 'raw';
    public const COMPARE        = 'compare';
    public const IS_NULL        = 'is_null';
    public const IS_NOT_NULL    = 'is_not_null';
    public const IN             = 'in';
    public const NOT_IN         = 'not_in';
    public const OR_NULL        = 'or_null';
    public const NOT_IN_OR_NULL = 'not_in_or_null';
    public const ANY_IS         = 'any_is';

    /**
     * @param mixed[]                           $values
     * @param array<int, array<int, Condition>> $groups ANY_IS: ORed groups, ANDed within
     */
    public function __construct(
        public readonly string $type,
        public readonly string $column = '',
        public readonly string $operator = '',
        public readonly string $fragment = '',
        public readonly string $subquery = '',
        public readonly array $values = [],
        public readonly array $groups = [],
    ) {}

    public static function raw(string $fragment, array $values = []): self
    {
        return new self(self::RAW, fragment: $fragment, values: $values);
    }

    public static function compare(string $column, string $operator, mixed $value): self
    {
        return new self(self::COMPARE, column: $column, operator: $operator, values: [$value]);
    }

    public static function isNull(string $column): self
    {
        return new self(self::IS_NULL, column: $column);
    }

    public static function isNotNull(string $column): self
    {
        return new self(self::IS_NOT_NULL, column: $column);
    }

    /** @param mixed[] $values */
    public static function in(string $column, array $values): self
    {
        return new self(self::IN, column: $column, values: $values);
    }

    /** @param mixed[] $values */
    public static function notIn(string $column, array $values): self
    {
        return new self(self::NOT_IN, column: $column, values: $values);
    }

    public static function inSubquery(string $column, string $subquery): self
    {
        return new self(self::IN, column: $column, subquery: $subquery);
    }

    public static function notInSubquery(string $column, string $subquery): self
    {
        return new self(self::NOT_IN, column: $column, subquery: $subquery);
    }

    /**
     * One comparison against one value: ( column op ? OR column IS NULL )
     */
    public static function orNull(string $column, string $operator, mixed $value): self
    {
        return new self(self::OR_NULL, column: $column, operator: $operator, values: [$value]);
    }

    /** @param mixed[] $values */
    public static function notInOrNull(string $column, array $values): self
    {
        return new self(self::NOT_IN_OR_NULL, column: $column, values: $values);
    }

    /** @param array<int, array<int, Condition>> $groups */
    public static function anyIs(array $groups): self
    {
        return new self(self::ANY_IS, groups: $groups);
    }
}
