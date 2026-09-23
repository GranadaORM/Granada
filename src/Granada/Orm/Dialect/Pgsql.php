<?php

namespace Granada\Orm\Dialect;

use Granada\Orm\Dialect;
use PDO;
use PDOStatement;

/**
 * PostgreSQL: double-quote identifiers and report a new row's id
 * from the INSERT itself via RETURNING.
 */
class Pgsql extends Dialect
{
    public const QUOTE_CHARACTER = '"';

    public function insertReturningFragment(string $id_column): string
    {
        return 'RETURNING ' . $this->quoteIdentifier($id_column);
    }

    public function fetchNewId(PDO $db, PDOStatement $statement): false|string
    {
        return $statement->fetchColumn();
    }
}
