<?php

namespace Granada\Orm;

use Granada\ORM;
use PDO;
use PDOStatement;

/**
 * Everything driver-specific for different SQL engines
 *
 * @internal
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
     * Build the dialect for a stored driver name. Unknown or unspecified drivers
     * get the mysql/sqlite dialect
     */
    public static function for_driver(?string $driver_name, ?string $quote_character = null, ?string $limit_clause_style = null): self
    {
        return match ($driver_name) {
            'pgsql'                              => new Dialect\Pgsql($quote_character, $limit_clause_style),
            'sqlsrv', 'dblib', 'mssql', 'sybase' => new Dialect\Sqlsrv($quote_character, $limit_clause_style),
            'firebird'                           => new Dialect\Firebird($quote_character, $limit_clause_style),
            default                              => new Dialect\Mysql($quote_character, $limit_clause_style),
        };
    }

    /**
     * SELECT-list prefix reserving rows, eg "TOP 5 " - placed after
     * SELECT and DISTINCT, before the result columns. Returns '' when an
     * offset is specified, as that is handled by pagination.
     */
    public function select_top_fragment(?int $limit, ?int $offset = null): string
    {
        if ($limit === null || $offset !== null) {
            return '';
        }

        if ($this->limit_clause_style !== ORM::LIMIT_STYLE_TOP_N) {
            return '';
        }

        return "TOP {$limit} ";
    }

    /**
     * The pagination fragment for a limit and an offset
     * set $ordered if the query has an ORDER BY
     */
    public function pagination_fragment(?int $limit, ?int $offset, bool $ordered): string
    {
        $fragments = [];

        if ($limit !== null && $this->limit_clause_style === ORM::LIMIT_STYLE_LIMIT) {
            $fragments[] = static::LIMIT_KEYWORD . " {$limit}";
        }

        if ($offset !== null) {
            $fragments[] = static::OFFSET_KEYWORD . ' ' . $offset;
        }

        return implode(' ', $fragments);
    }

    /**
     * Fragment reporting the last inserted row's id - if required by the dialect
     */
    public function insert_returning_fragment(string $id_column): string
    {
        return '';
    }

    /**
     * Get the id of the last inserted row
     */
    public function fetch_new_id(PDO $db, PDOStatement $statement): false|string
    {
        return $db->lastInsertId();
    }

    /**
     * How to order rows by a list of values
     *
     * @param (string|int)[] $values raw SQL fragments
     */
    public function order_by_field_expression(string $quoted_column, array $values): string
    {
        $cases = '';
        foreach (array_values($values) as $index => $value) {
            $cases .= ' WHEN ' . $value . ' THEN ' . ($index + 1);
        }

        return 'CASE ' . $quoted_column . $cases . ' ELSE 0 END';
    }

    public function quote_identifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        $parts = array_map(fn($part) => $this->quote_identifier_part($part), $parts);

        return implode('.', $parts);
    }

    private function quote_identifier_part(string $part): string
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
    public function quote_fields(array $fields): array
    {
        return array_map(fn($field) => $this->quote_identifier($field), $fields);
    }
}
