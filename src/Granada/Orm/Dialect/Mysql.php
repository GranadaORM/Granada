<?php

namespace Granada\Orm\Dialect;

use Granada\Orm\Dialect;

/**
 * The MySQL dialect: MySQL and SQLite, plus unknown drivers so that
 * rendering works before any connection exists. Backtick identifier
 * quoting, ON DUPLICATE KEY UPDATE upserts, FIELD() list ordering.
 */
class Mysql extends Dialect
{
    public function insertUpdateFragment(array $quoted_fields): string
    {
        // The doubled space after UPDATE matches the historical output
        return ' ON DUPLICATE KEY UPDATE  ' . implode(' = ?, ', $quoted_fields) . ' = ? ';
    }

    public function orderByFieldExpression(string $quoted_column, array $values): string
    {
        return 'FIELD(' . $quoted_column . ',' . implode(',', $values) . ')';
    }
}
