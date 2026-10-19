<?php

namespace Granada\Orm;

/**
 * Raw SQL as a value: renders into the statement as-is, where a plain
 * value binds to a placeholder.
 */
final class Expression
{
    public function __construct(
        public readonly string $sql,
    ) {}
}
