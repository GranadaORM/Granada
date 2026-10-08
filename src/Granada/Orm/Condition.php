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
    public const GROUP          = 'group';
    public const EXISTS         = 'exists';

    /**
     * @param mixed[]                           $values
     * @param array<int, array<int, Condition>> $groups ANY_IS: ORed groups, ANDed within
     * @param Condition[]                       $conditions GROUP: the conditions inside the group
     */
    public function __construct(
        public readonly string $type,
        public readonly string $column = '',
        public readonly string $operator = '',
        public readonly string $fragment = '',
        public readonly string $subquery = '',
        public readonly array $values = [],
        public readonly array $groups = [],
        public readonly array $conditions = [],
        public readonly ConnectedBy $connected_by = ConnectedBy::And,
        public readonly bool $negate = false,
    ) {}

    public static function raw(string $fragment, array $values = []): self
    {
        return new self(self::RAW, fragment: $fragment, values: $values);
    }

    public static function compare(string $column, string $operator, mixed $value): self
    {
        return new self(self::COMPARE, column: $column, operator: $operator, values: [$value]);
    }

    public static function is_null(string $column): self
    {
        return new self(self::IS_NULL, column: $column);
    }

    public static function is_not_null(string $column): self
    {
        return new self(self::IS_NOT_NULL, column: $column);
    }

    /** @param mixed[] $values */
    public static function in(string $column, array $values): self
    {
        return new self(self::IN, column: $column, values: $values);
    }

    /** @param mixed[] $values */
    public static function not_in(string $column, array $values): self
    {
        return new self(self::NOT_IN, column: $column, values: $values);
    }

    /**
     * The subquery's placeholders stay bound; its values merge into
     * the outer statement.
     * @param mixed[] $values
     */
    public static function in_subquery(string $column, string $subquery, array $values = []): self
    {
        return new self(self::IN, column: $column, subquery: $subquery, values: $values);
    }

    /**
     * @param mixed[] $values
     */
    public static function not_in_subquery(string $column, string $subquery, array $values = []): self
    {
        return new self(self::NOT_IN, column: $column, subquery: $subquery, values: $values);
    }

    /**
     * One EXISTS ( subquery ) condition, or NOT EXISTS when negated.
     * The subquery's bound values merge into the outer statement.
     * @param mixed[] $values
     */
    public static function exists(string $subquery, array $values = [], bool $negate = false): self
    {
        return new self(self::EXISTS, subquery: $subquery, values: $values, negate: $negate);
    }

    /**
     * One comparison against one value: ( column op ? OR column IS NULL )
     */
    public static function or_null(string $column, string $operator, mixed $value): self
    {
        return new self(self::OR_NULL, column: $column, operator: $operator, values: [$value]);
    }

    /** @param mixed[] $values */
    public static function not_in_or_null(string $column, array $values): self
    {
        return new self(self::NOT_IN_OR_NULL, column: $column, values: $values);
    }

    /** @param array<int, array<int, Condition>> $groups */
    public static function any_is(array $groups): self
    {
        return new self(self::ANY_IS, groups: $groups);
    }

    /**
     * Other conditions connected to the list by one word, AND or OR,
     * rendered in parens.
     * @param Condition[] $conditions
     */
    public static function group(array $conditions, ConnectedBy $connected_by = ConnectedBy::And, bool $negate = false): self
    {
        return new self(self::GROUP, conditions: $conditions, connected_by: $connected_by, negate: $negate);
    }

    /**
     * The constructor arguments that the copy-with methods pass
     * through unchanged.
     * @return mixed[]
     */
    private function args(): array
    {
        return [
            $this->type,
            $this->column,
            $this->operator,
            $this->fragment,
            $this->subquery,
            $this->values,
            $this->groups,
            $this->conditions,
        ];
    }

    /**
     * A copy of this condition that ANDs or ORs against the
     * conditions before it.
     */
    public function joined_by(ConnectedBy $connected_by): self
    {
        return new self(...$this->args(), connected_by: $connected_by, negate: $this->negate);
    }

    /**
     * This condition negated: rendered as NOT ( condition ).
     */
    public function negated(): self
    {
        return new self(...$this->args(), connected_by: $this->connected_by, negate: !$this->negate);
    }

    /**
     * This condition negated when the flag is set.
     */
    public function negate_if(bool $negate): self
    {
        return $negate ? $this->negated() : $this;
    }
}
