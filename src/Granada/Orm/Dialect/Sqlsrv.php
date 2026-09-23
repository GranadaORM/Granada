<?php

namespace Granada\Orm\Dialect;

use Granada\ORM;
use Granada\Orm\Dialect;

/**
 * The SQL Server dialect: sqlsrv, dblib, mssql, sybase. Double-quote
 * identifiers and reserve rows with SELECT TOP, leaving no trailing
 * limit clause.
 */
class Sqlsrv extends Dialect
{
    public const QUOTE_CHARACTER    = '"';
    public const LIMIT_CLAUSE_STYLE = ORM::LIMIT_STYLE_TOP_N;
}
