<?php

namespace Granada\Orm\Dialect;

use Granada\ORM;
use Granada\Orm\Dialect;

/**
 * The SQL Server dialect: sqlsrv, dblib, mssql, sybase. Double-quote
 * identifiers, reserve rows with SELECT TOP, and paginate with
 * OFFSET/FETCH when an offset is set.
 *
 * @internal
 */
class Sqlsrv extends Dialect
{
    public const QUOTE_CHARACTER    = '"';
    public const LIMIT_CLAUSE_STYLE = ORM::LIMIT_STYLE_TOP_N;

    /**
     * SQL Server paginates with ORDER BY ... OFFSET ... FETCH. The
     * OFFSET requires an ORDER BY, so a query without one gets the
     * neutral ORDER BY (SELECT NULL), and a statement cannot combine
     * TOP with the OFFSET.
     */
    public function pagination_fragment(?int $limit, ?int $offset, bool $ordered): string
    {
        if ($this->limit_clause_style !== ORM::LIMIT_STYLE_TOP_N) {
            return parent::pagination_fragment($limit, $offset, $ordered);
        }

        if ($offset === null) {
            return '';
        }

        return ($ordered ? '' : 'ORDER BY (SELECT NULL) ')
            . "OFFSET {$offset} ROWS"
            . ($limit === null ? '' : " FETCH NEXT {$limit} ROWS ONLY");
    }
}
