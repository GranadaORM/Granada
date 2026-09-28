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

    public function insert_returning_fragment(string $id_column): string
    {
        return 'RETURNING ' . $this->quote_identifier($id_column);
    }

    public function fetch_new_id(PDO $db, PDOStatement $statement): false|string
    {
        return $statement->fetchColumn();
    }
}
