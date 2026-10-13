<?php

namespace Granada\Orm\Dialect;

use Granada\Orm\Dialect;

/**
 * The MySQL dialect: MySQL and SQLite, plus unknown drivers so that
 * rendering works before any connection exists. Backtick identifier
 * quoting, FIELD() list ordering.
 *
 * @internal
 */
class Mysql extends Dialect
{
    public function order_by_field_expression(string $quoted_column, array $values): string
    {
        return 'FIELD(' . $quoted_column . ',' . implode(',', $values) . ')';
    }
}
