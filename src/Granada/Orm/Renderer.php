<?php

namespace Granada\Orm;

/**
 * Renders SQL statements from a spec.
 *
 * Pure: it reads no global state and requires no database connection.
 * Every statement method returns [sql, parameters].
 */
class Renderer
{
    public static function select(SelectSpec $spec): Statement
    {
        $where  = self::buildWhereCondition($spec->where_conditions, $spec->dialect);
        $having = self::buildHavingCondition($spec->having_conditions, $spec->dialect);

        $sql = self::joinIfNotEmpty(' ', [
            self::selectStart($spec),
            self::joinFragments($spec->join_sources, $spec->dialect),
            $where->fragment,
            self::groupBy($spec),
            $having->fragment,
            self::orderBy($spec),
            self::limit($spec),
            self::offset($spec),
        ]);

        return new Statement($sql, array_merge($where->values, $having->values));
    }

    public static function selectStart(SelectSpec $spec): string
    {
        $fragment       = 'SELECT ';
        $result_columns = implode(', ', self::resultColumns($spec));

        $fragment .= $spec->dialect->selectTopFragment($spec->limit);

        if ($spec->distinct) {
            $result_columns = 'DISTINCT ' . $result_columns;
        }

        $fragment .= "{$result_columns} FROM " . $spec->dialect->quoteIdentifier($spec->table_name);

        if (!is_null($spec->table_alias)) {
            $fragment .= ' ' . $spec->dialect->quoteIdentifier($spec->table_alias);
        }

        return $fragment;
    }

    /**
     * The SELECT list. Plain strings arrive already quoted or raw;
     * Aggregate entries get their column reference quoted here.
     * @return string[]
     */
    private static function resultColumns(SelectSpec $spec): array
    {
        return array_map(function (Aggregate|string $column) use ($spec): string {
            if ($column instanceof Aggregate) {
                $reference = $column->column === '*' ? '*' : $spec->dialect->quoteIdentifier($column->column);

                return "{$column->function}({$reference}) AS {$spec->dialect->quoteIdentifier($column->alias)}";
            }

            return $column;
        }, $spec->result_columns);
    }

    /**
     * @param JoinSource[]|string[] $join_sources
     */
    private static function joinFragments(array $join_sources, Dialect $dialect): string
    {
        $parts = [];
        foreach ($join_sources as $source) {
            $parts[] = is_string($source) ? $source : self::joinSource($source, $dialect);
        }

        return implode(' ', $parts);
    }

    private static function joinSource(JoinSource $source, Dialect $dialect): string
    {
        $operator = trim("{$source->operator} JOIN");
        $table    = $dialect->quoteIdentifier($source->table);
        if (!is_null($source->alias)) {
            $table .= ' ' . $dialect->quoteIdentifier($source->alias);
        }

        $constraint = $source->constraint;
        if (is_array($constraint)) {
            [$first_column, $constraint_operator, $second_column] = $constraint;
            $constraint                                           = $dialect->quoteIdentifier($first_column) . " {$constraint_operator} " . $dialect->quoteIdentifier($second_column);
        }

        return "{$operator} {$table} ON {$constraint}";
    }

    public static function groupBy(SelectSpec $spec): string
    {
        return self::termList('GROUP BY', $spec->group_by, $spec->dialect);
    }

    public static function orderBy(SelectSpec $spec): string
    {
        return self::termList('ORDER BY', $spec->order_by, $spec->dialect);
    }

    /**
     * @param Term[] $terms
     */
    private static function termList(string $keyword, array $terms, Dialect $dialect): string
    {
        if (count($terms) === 0) {
            return '';
        }

        return $keyword . ' ' . implode(', ', array_map(fn(Term $term) => self::term($term, $dialect), $terms));
    }

    private static function term(Term $term, Dialect $dialect): string
    {
        if ($term->expression !== '') {
            return $term->expression;
        }

        $column = $dialect->quoteIdentifier($term->column);
        if ($term->field_list !== []) {
            return $dialect->orderByFieldExpression($column, $term->field_list);
        }

        if ($term->natural) {
            return "LENGTH({$column}), {$column} {$term->direction}";
        }

        if ($term->direction === '') {
            return $column;
        }

        return "{$column} {$term->direction}";
    }

    public static function limit(SelectSpec $spec): string
    {
        return $spec->dialect->limitFragment($spec->limit);
    }

    public static function offset(SelectSpec $spec): string
    {
        return $spec->dialect->offsetFragment($spec->offset);
    }

    private static function buildWhereCondition(array $conditions, Dialect $dialect): Condition
    {
        if ($conditions === []) {
            return Condition::raw('');
        }

        [$fragment, $values] = self::renderConditions($conditions, $dialect);

        return Condition::raw('WHERE ' . $fragment, $values);
    }

    private static function buildHavingCondition(array $conditions, Dialect $dialect): Condition
    {
        if ($conditions === []) {
            return Condition::raw('');
        }

        [$fragment, $values] = self::renderConditions($conditions, $dialect);

        return Condition::raw('HAVING ' . $fragment, $values);
    }

    /**
     * Render each condition and AND them together.
     * @param Condition[] $conditions
     * @return array{string, mixed[]} fragment and bound values
     */
    private static function renderConditions(array $conditions, Dialect $dialect): array
    {
        $fragments = $values = [];
        foreach ($conditions as $condition) {
            [$fragment, $condition_values] = self::renderCondition($condition, $dialect);
            $fragments[]                   = $fragment;
            $values                        = array_merge($values, $condition_values);
        }

        return [implode(' AND ', $fragments), $values];
    }

    /**
     * One condition to its SQL fragment plus bound values.
     * @return array{string, mixed[]}
     */
    private static function renderCondition(Condition $condition, Dialect $dialect): array
    {
        $quoted = $condition->column === '' ? '' : $dialect->quoteIdentifier($condition->column);

        return match ($condition->type) {
            Condition::RAW                   => [$condition->fragment, $condition->values],
            Condition::COMPARE               => ["{$quoted} {$condition->operator} ?", $condition->values],
            Condition::IS_NULL               => ["{$quoted} IS NULL", []],
            Condition::IS_NOT_NULL           => ["{$quoted} IS NOT NULL", []],
            Condition::IN, Condition::NOT_IN => self::renderIn($condition, $quoted),
            Condition::OR_NULL               => ["( {$quoted} {$condition->operator} ? OR {$quoted} IS NULL )", $condition->values],
            Condition::NOT_IN_OR_NULL        => self::renderInOrNull($condition, $quoted),
            Condition::ANY_IS                => self::renderAnyIs($condition, $dialect),
            default                          => throw new \LogicException("Unknown condition type {$condition->type}"),
        };
    }

    /** @return array{string, mixed[]} */
    private static function renderIn(Condition $condition, string $quoted): array
    {
        switch ($condition->type) {
            case Condition::IN:
                $word = 'IN';
                break;

            case Condition::NOT_IN:
            case Condition::NOT_IN_OR_NULL:
                $word = 'NOT IN';
                break;

            default:
                throw new \LogicException("Unexpected condition type in renderIn: {$condition->type}");
        }

        if ($condition->subquery !== '') {
            return ["{$quoted} {$word} ({$condition->subquery})", []];
        }

        return ["{$quoted} {$word} (" . self::placeholders($condition->values) . ')', $condition->values];
    }

    /** @return array{string, mixed[]} */
    private static function renderInOrNull(Condition $condition, string $quoted): array
    {
        [$fragment, $values] = self::renderIn($condition, $quoted);

        return ["( {$fragment} OR {$quoted} IS NULL )", $values];
    }

    /**
     * The groups are ANDed within and ORed between, wrapped in double
     * parens: (( a = ? AND b = ? ) OR ( c IS NULL )).
     */
    private static function renderAnyIs(Condition $condition, Dialect $dialect): array
    {
        $groups = $values = [];
        foreach ($condition->groups as $group) {
            [$fragment, $group_values] = self::renderConditions($group, $dialect);
            $groups[]                  = "( {$fragment} )";
            $values                    = array_merge($values, $group_values);
        }

        return ['(' . implode(' OR ', $groups) . ')', $values];
    }

    public static function update(WriteSpec $spec): Statement
    {
        $query  = ['UPDATE ' . $spec->dialect->quoteIdentifier($spec->table_name) . ' SET'];
        $values = [];
        $fields = [];
        foreach ($spec->dirty_fields as $key => $value) {
            if (array_key_exists($key, $spec->expr_fields)) {
                $fields[] = $spec->dialect->quoteIdentifier($key) . " = {$value}";
            } else {
                $fields[] = $spec->dialect->quoteIdentifier($key) . ' = ?';
                $values[] = $value;
            }
        }
        $query[]  = implode(', ', $fields);
        $query[]  = 'WHERE';
        $query[]  = $spec->dialect->quoteIdentifier($spec->id_column);
        $query[]  = '= ?';
        $values[] = $spec->id_value;

        return new Statement(implode(' ', $query), $values);
    }

    public static function insert(WriteSpec $spec): Statement
    {
        $query = [
            'INSERT INTO',
            $spec->dialect->quoteIdentifier($spec->table_name),
            '(' . implode(', ', $spec->dialect->quoteFields(array_keys($spec->dirty_fields))) . ')',
            'VALUES',
            '(' . self::placeholders($spec->dirty_fields, $spec->expr_fields) . ')',
        ];

        $returning = $spec->dialect->insertReturningFragment($spec->id_column);
        if ($returning !== '') {
            $query[] = $returning;
        }

        return new Statement(implode(' ', $query), self::boundValues($spec));
    }

    /**
     * Dialects without an upsert equivalent render a plain INSERT.
     */
    public static function insertUpdate(WriteSpec $spec): Statement
    {
        $dialect = $spec->dialect;
        $fields  = $dialect->quoteFields(array_keys($spec->dirty_fields));
        $values  = self::boundValues($spec);
        $query   = [
            'INSERT INTO',
            $dialect->quoteIdentifier($spec->table_name),
            '(' . implode(', ', $fields) . ')',
            'VALUES',
            '(' . self::placeholders($spec->dirty_fields, $spec->expr_fields) . ')',
        ];

        $upsert = $dialect->insertUpdateFragment($fields);
        if ($upsert !== '') {
            $query[] = $upsert;
            $values  = array_merge($values, $values);
        }

        return new Statement(implode(' ', $query), $values);
    }

    /**
     * Single-record DELETE by primary key.
     */
    public static function delete(WriteSpec $spec): Statement
    {
        $query = self::joinIfNotEmpty(' ', [
            'DELETE FROM',
            $spec->dialect->quoteIdentifier($spec->table_name),
            'WHERE',
            $spec->dialect->quoteIdentifier($spec->id_column),
            '= ?',
        ]);

        return new Statement($query, [$spec->id_value]);
    }

    /**
     * DELETE from the accumulated WHERE conditions. With join sources, the
     * MySQL `DELETE target FROM table JOIN .. WHERE ..` form, where `target`
     * names the table or alias the delete removes from.
     */
    public static function deleteMany(BulkDeleteSpec $spec): Statement
    {
        $where = self::buildWhereCondition($spec->where_conditions, $spec->dialect);

        if ($spec->join_sources !== []) {
            $query = self::joinIfNotEmpty(' ', [
                "DELETE {$spec->target} FROM",
                $spec->dialect->quoteIdentifier($spec->table_name),
                self::joinFragments($spec->join_sources, $spec->dialect),
                $where->fragment,
            ]);

            return new Statement($query, $where->values);
        }

        $query = self::joinIfNotEmpty(' ', [
            'DELETE FROM',
            $spec->dialect->quoteIdentifier($spec->table_name),
            $where->fragment,
        ]);

        return new Statement($query, $where->values);
    }

    /**
     * Question marks for each field, separated by commas. Eg "?, ?, ?".
     * Expression fields are inlined instead of bound.
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $expr_fields
     */
    public static function placeholders(array $fields, array $expr_fields = []): string
    {
        if (empty($fields)) {
            return '';
        }

        $db_fields = [];
        foreach ($fields as $key => $value) {
            $db_fields[] = array_key_exists($key, $expr_fields) ? $value : '?';
        }

        return implode(', ', $db_fields);
    }

    /**
     * The dirty fields that get bound, except expr fields, in field order.
     * @return mixed[]
     */
    private static function boundValues(WriteSpec $spec): array
    {
        return array_values(array_diff_key($spec->dirty_fields, $spec->expr_fields));
    }

    /**
     * @param string[] $pieces
     */
    public static function joinIfNotEmpty(string $glue, array $pieces): string
    {
        $filtered_pieces = [];
        foreach ($pieces as $piece) {
            if (is_string($piece)) {
                $piece = trim($piece);
            }
            if (!empty($piece)) {
                $filtered_pieces[] = $piece;
            }
        }

        return implode($glue, $filtered_pieces);
    }

    /**
     * Interpolate bound parameters into a statement, outside quoted
     * strings.
     * @param array<int|string, mixed> $parameters
     */
    public static function interpolate(string $query, array $parameters, callable $quote): string
    {
        if (count($parameters) === 0) {
            return $query;
        }

        foreach ($parameters as $key => $parameter) {
            $parameters[$key] = ($parameter === null) ? 'NULL' : $quote($parameter);
        }

        // Avoid %format collision for vsprintf
        $query = str_replace('%', '%%', $query);

        // Replace placeholders in the query for vsprintf
        if (str_contains($query, "'") || str_contains($query, '"')) {
            $query = Str::str_replace_outside_quotes('?', '%s', $query);
        } else {
            $query = str_replace('?', '%s', $query);
        }

        return vsprintf($query, $parameters);
    }
}
