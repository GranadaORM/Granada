<?php

namespace Granada\Orm;

/**
 * An aggregate result column, eg COUNT(*) AS `count`. The column
 * reference is quoted by the Renderer; '*' is not quoted.
 */
final class Aggregate
{
    public function __construct(
        public readonly string $function,
        public readonly string $column,
        public readonly string $alias,
    ) {}
}
