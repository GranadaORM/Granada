<?php

namespace Granada\Orm;

/**
 * A rendered SQL statement and the values bound to its placeholders.
 */
final class Statement
{
    public function __construct(
        public readonly string $query,
        /** @var mixed[] */
        public readonly array $values,
    ) {}
}
