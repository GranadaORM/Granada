<?php

namespace Granada\Orm\Dialect;

use Granada\Orm\Dialect;

/**
 * The SQLite dialect: backtick identifier quoting, and a CASE
 * expression for list ordering because SQLite has no FIELD().
 *
 * @internal
 */
class Sqlite extends Dialect
{
    public function order_by_random_expression(): string
    {
        return 'RANDOM()';
    }
}
