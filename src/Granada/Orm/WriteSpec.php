<?php

namespace Granada\Orm;

/**
 * Spec for writing one row: insert, update, or delete by id.
 */
class WriteSpec
{
    public function __construct(
        public readonly string $table_name,
        public readonly string $id_column,
        public readonly Dialect $dialect,
        /** @var array<string, mixed> */
        public readonly array $dirty_fields = [],
        /**
         * Fields whose value is raw SQL, inlined instead of bound.
         * @var array<string, mixed>
         */
        public readonly array $expr_fields = [],
        public readonly mixed $id_value = null,
    ) {}
}
