<?php

namespace Granada\Orm\Dialect;

use Granada\ORM;
use Granada\Orm\Dialect;

/**
 * Firebird: double-quote identifiers; pagination is a one-based
 * ROWS range.
 *
 * @internal
 */
class Firebird extends Dialect
{
    public const QUOTE_CHARACTER = '"';
    public const LIMIT_KEYWORD   = 'ROWS';
    public const OFFSET_KEYWORD  = 'TO';

    /**
     * Firebird paginates with a one-based ROWS range: ROWS m takes
     * the first m rows, and ROWS m TO n takes rows m through n. An
     * offset without a limit has no last row, so the range ends at
     * PHP_INT_MAX, the way the Firebird manual suggests for SKIP
     * without FIRST.
     */
    public function pagination_fragment(?int $limit, ?int $offset, bool $ordered): string
    {
        if ($this->limit_clause_style !== ORM::LIMIT_STYLE_LIMIT) {
            return parent::pagination_fragment($limit, $offset, $ordered);
        }

        if ($offset === null) {
            return parent::pagination_fragment($limit, null, $ordered);
        }

        $first = $offset + 1;
        $last  = $limit === null ? PHP_INT_MAX : $offset + $limit;

        return static::LIMIT_KEYWORD . " {$first} TO {$last}";
    }
}
