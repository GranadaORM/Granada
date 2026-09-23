<?php

namespace Granada\Orm;

/**
 * One JOIN entry as plain data: the operator word, the table with its
 * optional alias, and the ON constraint — either the three-part
 * [first column, operator, second column] form or raw SQL.
 */
final class JoinSource
{
    /** @param array{0: string, 1: string, 2: string}|string $constraint */
    public function __construct(
        public readonly string $operator,
        public readonly string $table,
        public readonly ?string $alias,
        public readonly array|string $constraint,
    ) {}
}
