<?php

namespace Granada\Orm;

use Granada\ORM;
use PDO;
use PDOStatement;

/**
 * Everything driver-specific about the database in one place, so the
 * renderer, save() and Wrapper ask a dialect object instead of
 * comparing driver names or querying a live database connection.
 */
abstract class Dialect
{
    public const QUOTE_CHARACTER = '`';

    /** Default limit clause style, an ORM::LIMIT_STYLE_* value. */
    public const LIMIT_CLAUSE_STYLE = ORM::LIMIT_STYLE_LIMIT;
    public const LIMIT_KEYWORD      = 'LIMIT';
    public const OFFSET_KEYWORD     = 'OFFSET';

    public readonly string $quote_character;
    public readonly string $limit_clause_style;

    public function __construct(?string $quote_character = null, ?string $limit_clause_style = null)
    {
        $this->quote_character    = $quote_character    ?? static::QUOTE_CHARACTER;
        $this->limit_clause_style = $limit_clause_style ?? static::LIMIT_CLAUSE_STYLE;
    }

    /**
     * Build the dialect for a stored driver name. Unknown drivers
     * (including no connection at all) get the mysql/sqlite dialect,
     * so statements can be rendered before any connection exists.
     */
    public static function forDriver(?string $driver_name, ?string $quote_character = null, ?string $limit_clause_style = null): self
    {
        return match ($driver_name) {
            'pgsql'                              => new Dialect\Pgsql($quote_character, $limit_clause_style),
            'sqlsrv', 'dblib', 'mssql', 'sybase' => new Dialect\Sqlsrv($quote_character, $limit_clause_style),
            'firebird'                           => new Dialect\Firebird($quote_character, $limit_clause_style),
            default                              => new Dialect\Mysql($quote_character, $limit_clause_style),
        };
    }

    /**
     * SELECT-list prefix reserving rows, eg "TOP 5 " — placed right
     * after SELECT, before the result columns.
     */
    public function selectTopFragment(?int $limit): string
    {
        if ($limit === null) {
            return '';
        }

        if ($this->limit_clause_style !== ORM::LIMIT_STYLE_TOP_N) {
            return '';
        }

        return "TOP {$limit} ";
    }

    /**
     * Trailing row-limit clause, eg "LIMIT 5" / "ROWS 5".
     * Empty when this dialect reserves rows via selectTopFragment().
     */
    public function limitFragment(?int $limit): string
    {
        if ($limit === null) {
            return '';
        }

        if ($this->limit_clause_style !== ORM::LIMIT_STYLE_LIMIT) {
            return '';
        }

        return static::LIMIT_KEYWORD . " {$limit}";
    }

    /**
     * Trailing row-offset clause, eg "OFFSET 10" / "TO 10".
     */
    public function offsetFragment(?int $offset): string
    {
        if ($offset === null) {
            return '';
        }

        return static::OFFSET_KEYWORD . ' ' . $offset;
    }

    /**
     * Clause reporting the new row's id from an INSERT, or '' when
     * the dialect reads it via lastInsertId instead. Meant to be
     * overridden by subclasses (Pgsql returns RETURNING).
     */
    public function insertReturningFragment(string $id_column): string
    {
        return '';
    }

    /**
     * Upsert suffix for save()->save(true), or '' when the dialect
     * has no equivalent of ON DUPLICATE KEY UPDATE. Meant to be
     * overridden by subclasses (Mysql returns ON DUPLICATE KEY UPDATE).
     * @param string[] $quoted_fields
     */
    public function insertUpdateFragment(array $quoted_fields): string
    {
        return '';
    }

    /**
     * Get the id of a freshly inserted row. Meant to be overridden by
     * subclasses: dialects whose INSERT reports the id (RETURNING)
     * read it from the executed statement instead of the connection.
     */
    public function fetchNewId(PDO $db, PDOStatement $statement): false|string
    {
        return $db->lastInsertId();
    }

    /**
     * Expression ordering rows by position in $values: the first
     * value sorts first, values absent from the list before all of
     * them. Meant to be overridden by subclasses (Mysql returns FIELD()).
     * @param (string|int)[] $values raw SQL fragments
     */
    public function orderByFieldExpression(string $quoted_column, array $values): string
    {
        $cases = '';
        foreach (array_values($values) as $index => $value) {
            $cases .= ' WHEN ' . $value . ' THEN ' . ($index + 1);
        }

        return 'CASE ' . $quoted_column . $cases . ' ELSE 0 END';
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     */
    public function quoteIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        $parts = array_map(fn($part) => $this->quoteIdentifierPart($part), $parts);

        return implode('.', $parts);
    }

    /**
     * Perform the actual quoting of a single part of an
     * identifier, doubling up any quote characters to escape
     * them.
     */
    public function quoteIdentifierPart(string $part): string
    {
        if ($part === '*') {
            return $part;
        }

        $quote_character = $this->quote_character;

        return $quote_character
            . str_replace(
                $quote_character,
                $quote_character . $quote_character,
                $part
            ) . $quote_character;
    }

    /**
     * Quote each of the given identifier fields
     * @param string[] $fields
     * @return string[]
     */
    public function quoteFields(array $fields): array
    {
        return array_map(fn($field) => $this->quoteIdentifier($field), $fields);
    }
}
