<?php

namespace Granada\Orm\Dialect;

use Granada\Orm\Dialect;

/**
 * Firebird: double-quote identifiers; the limit/offset shape is
 * ROWS n TO m.
 */
class Firebird extends Dialect
{
    public const QUOTE_CHARACTER = '"';
    public const LIMIT_KEYWORD   = 'ROWS';
    public const OFFSET_KEYWORD  = 'TO';
}
