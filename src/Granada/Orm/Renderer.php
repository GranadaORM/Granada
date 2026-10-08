<?php

namespace Granada\Orm;

/**
 * Renders SQL statements from a spec.
 *
 * Pure: it reads no global state and requires no database connection.
 * Every statement method returns [sql, parameters].
 *
 * @internal
 */
class Renderer
{
    public static function select(SelectSpec $spec): Statement
    {
        $where  = self::build_where_condition($spec->where_conditions, $spec->dialect);
        $having = self::build_having_condition($spec->having_conditions, $spec->dialect);

        $sql = self::join_if_not_empty(' ', [
            self::select_start($spec),
            self::join_fragments($spec->join_sources, $spec->dialect),
            $where->fragment,
            self::group_by($spec),
            $having->fragment,
            self::order_by($spec),
            self::limit($spec),
            self::offset($spec),
        ]);

        return new Statement($sql, array_merge($where->values, $having->values));
    }

    private static function select_start(SelectSpec $spec): string
    {
        $fragment       = 'SELECT ';
        $result_columns = implode(', ', self::result_columns($spec));

        $fragment .= $spec->dialect->select_top_fragment($spec->limit);

        if ($spec->distinct) {
            $result_columns = 'DISTINCT ' . $result_columns;
        }

        $fragment .= "{$result_columns} FROM " . $spec->dialect->quote_identifier($spec->table_name);

        if (!is_null($spec->table_alias)) {
            $fragment .= ' ' . $spec->dialect->quote_identifier($spec->table_alias);
        }

        return $fragment;
    }

    /**
     * The SELECT list. Plain strings arrive already quoted or raw;
     * Aggregate entries get their column reference quoted here.
     * @return string[]
     */
    private static function result_columns(SelectSpec $spec): array
    {
        return array_map(function (Aggregate|string $column) use ($spec): string {
            if ($column instanceof Aggregate) {
                $reference = $column->column === '*' ? '*' : $spec->dialect->quote_identifier($column->column);

                return "{$column->function}({$reference}) AS {$spec->dialect->quote_identifier($column->alias)}";
            }

            return $column;
        }, $spec->result_columns);
    }

    /**
     * @param JoinSource[]|string[] $join_sources
     */
    private static function join_fragments(array $join_sources, Dialect $dialect): string
    {
        $parts = [];
        foreach ($join_sources as $source) {
            $parts[] = is_string($source) ? $source : self::join_source($source, $dialect);
        }

        return implode(' ', $parts);
    }

    private static function join_source(JoinSource $source, Dialect $dialect): string
    {
        $operator = trim("{$source->operator} JOIN");
        $table    = $dialect->quote_identifier($source->table);
        if (!is_null($source->alias)) {
            $table .= ' ' . $dialect->quote_identifier($source->alias);
        }

        $constraint = $source->constraint;
        if (is_array($constraint)) {
            [$first_column, $constraint_operator, $second_column] = $constraint;
            $constraint                                           = $dialect->quote_identifier($first_column) . " {$constraint_operator} " . $dialect->quote_identifier($second_column);
        }

        return "{$operator} {$table} ON {$constraint}";
    }

    private static function group_by(SelectSpec $spec): string
    {
        return self::term_list('GROUP BY', $spec->group_by, $spec->dialect);
    }

    private static function order_by(SelectSpec $spec): string
    {
        return self::term_list('ORDER BY', $spec->order_by, $spec->dialect);
    }

    /**
     * @param Term[] $terms
     */
    private static function term_list(string $keyword, array $terms, Dialect $dialect): string
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

        $column = $dialect->quote_identifier($term->column);
        if ($term->field_list !== []) {
            return $dialect->order_by_field_expression($column, $term->field_list);
        }

        if ($term->natural) {
            return "LENGTH({$column}), {$column} {$term->direction}";
        }

        if ($term->direction === '') {
            return $column;
        }

        return "{$column} {$term->direction}";
    }

    private static function limit(SelectSpec $spec): string
    {
        return $spec->dialect->limit_fragment($spec->limit);
    }

    private static function offset(SelectSpec $spec): string
    {
        return $spec->dialect->offset_fragment($spec->offset);
    }

    private static function build_where_condition(array $conditions, Dialect $dialect): Condition
    {
        if ($conditions === []) {
            return Condition::raw('');
        }

        [$fragment, $values] = self::render_conditions($conditions, $dialect);

        return Condition::raw('WHERE ' . $fragment, $values);
    }

    private static function build_having_condition(array $conditions, Dialect $dialect): Condition
    {
        if ($conditions === []) {
            return Condition::raw('');
        }

        [$fragment, $values] = self::render_conditions($conditions, $dialect);

        return Condition::raw('HAVING ' . $fragment, $values);
    }

    /**
     * Render each condition, joining each with its own AND or OR.
     * @param Condition[] $conditions
     * @return array{string, mixed[]} fragment and bound values
     */
    private static function render_conditions(array $conditions, Dialect $dialect): array
    {
        $fragments = $values = [];
        foreach ($conditions as $condition) {
            [$fragment, $condition_values] = self::render_condition($condition, $dialect);
            if ($fragments !== []) {
                $fragments[] = $condition->connected_by->value;
            }
            $fragments[] = $fragment;
            $values      = array_merge($values, $condition_values);
        }

        return [implode(' ', $fragments), $values];
    }

    /**
     * One condition to its SQL fragment plus bound values. Group and
     * EXISTS conditions wrap themselves in parens and carry their own
     * NOT; every other negated condition renders as NOT ( condition ).
     * @return array{string, mixed[]}
     */
    private static function render_condition(Condition $condition, Dialect $dialect): array
    {
        [$fragment, $values] = self::render_bare_condition($condition, $dialect);

        if ($condition->negate && !in_array($condition->type, [Condition::GROUP, Condition::EXISTS], true)) {
            $fragment = "NOT ( {$fragment} )";
        }

        return [$fragment, $values];
    }

    /**
     * @return array{string, mixed[]}
     */
    private static function render_bare_condition(Condition $condition, Dialect $dialect): array
    {
        $quoted = $condition->column === '' ? '' : $dialect->quote_identifier($condition->column);

        return match ($condition->type) {
            Condition::RAW                   => [$condition->fragment, $condition->values],
            Condition::COMPARE               => ["{$quoted} {$condition->operator} ?", $condition->values],
            Condition::IS_NULL               => ["{$quoted} IS NULL", []],
            Condition::IS_NOT_NULL           => ["{$quoted} IS NOT NULL", []],
            Condition::IN, Condition::NOT_IN => self::render_in($condition, $quoted),
            Condition::OR_NULL               => ["( {$quoted} {$condition->operator} ? OR {$quoted} IS NULL )", $condition->values],
            Condition::NOT_IN_OR_NULL        => self::render_in_or_null($condition, $quoted),
            Condition::ANY_IS                => self::render_any_is($condition, $dialect),
            Condition::GROUP                 => self::render_group($condition, $dialect),
            Condition::EXISTS                => self::render_exists($condition),
            default                          => throw new \LogicException("Unknown condition type {$condition->type}"),
        };
    }

    private static function render_group(Condition $condition, Dialect $dialect): array
    {
        [$fragment, $values] = self::render_conditions($condition->conditions, $dialect);
        $fragment            = "( {$fragment} )";
        if ($condition->negate) {
            $fragment = "NOT {$fragment}";
        }

        return [$fragment, $values];
    }

    /**
     * The subquery's placeholders stay bound; its values merge into
     * the outer statement.
     * @return array{string, mixed[]}
     */
    private static function render_exists(Condition $condition): array
    {
        $fragment = "EXISTS ( {$condition->subquery} )";
        if ($condition->negate) {
            $fragment = "NOT {$fragment}";
        }

        return [$fragment, $condition->values];
    }

    /** @return array{string, mixed[]} */
    private static function render_in(Condition $condition, string $quoted): array
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
                throw new \LogicException("Unexpected condition type in render_in: {$condition->type}");
        }

        if ($condition->subquery !== '') {
            return ["{$quoted} {$word} ({$condition->subquery})", $condition->values];
        }

        return ["{$quoted} {$word} (" . self::placeholders($condition->values) . ')', $condition->values];
    }

    /** @return array{string, mixed[]} */
    private static function render_in_or_null(Condition $condition, string $quoted): array
    {
        [$fragment, $values] = self::render_in($condition, $quoted);

        return ["( {$fragment} OR {$quoted} IS NULL )", $values];
    }

    /**
     * The groups are ANDed within and ORed between, wrapped in double
     * parens: (( a = ? AND b = ? ) OR ( c IS NULL )).
     */
    private static function render_any_is(Condition $condition, Dialect $dialect): array
    {
        $groups = $values = [];
        foreach ($condition->groups as $group) {
            [$fragment, $group_values] = self::render_conditions($group, $dialect);
            $groups[]                  = "( {$fragment} )";
            $values                    = array_merge($values, $group_values);
        }

        return ['(' . implode(' OR ', $groups) . ')', $values];
    }

    public static function update(WriteSpec $spec): Statement
    {
        $query  = ['UPDATE ' . $spec->dialect->quote_identifier($spec->table_name) . ' SET'];
        $values = [];
        $fields = [];
        foreach ($spec->dirty_fields as $key => $value) {
            if (array_key_exists($key, $spec->expr_fields)) {
                $fields[] = $spec->dialect->quote_identifier($key) . " = {$value}";
            } else {
                $fields[] = $spec->dialect->quote_identifier($key) . ' = ?';
                $values[] = $value;
            }
        }
        $query[]  = implode(', ', $fields);
        $query[]  = 'WHERE';
        $query[]  = $spec->dialect->quote_identifier($spec->id_column);
        $query[]  = '= ?';
        $values[] = $spec->id_value;

        return new Statement(implode(' ', $query), $values);
    }

    public static function insert(WriteSpec $spec): Statement
    {
        $query = [
            'INSERT INTO',
            $spec->dialect->quote_identifier($spec->table_name),
            '(' . implode(', ', $spec->dialect->quote_fields(array_keys($spec->dirty_fields))) . ')',
            'VALUES',
            '(' . self::placeholders($spec->dirty_fields, $spec->expr_fields) . ')',
        ];

        $returning = $spec->dialect->insert_returning_fragment($spec->id_column);
        if ($returning !== '') {
            $query[] = $returning;
        }

        return new Statement(implode(' ', $query), self::bound_values($spec));
    }

    /**
     * Dialects without an upsert equivalent render a plain INSERT.
     */
    public static function insert_update(WriteSpec $spec): Statement
    {
        $dialect = $spec->dialect;
        $fields  = $dialect->quote_fields(array_keys($spec->dirty_fields));
        $values  = self::bound_values($spec);
        $query   = [
            'INSERT INTO',
            $dialect->quote_identifier($spec->table_name),
            '(' . implode(', ', $fields) . ')',
            'VALUES',
            '(' . self::placeholders($spec->dirty_fields, $spec->expr_fields) . ')',
        ];

        $upsert = $dialect->insert_update_fragment($fields);
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
        $query = self::join_if_not_empty(' ', [
            'DELETE FROM',
            $spec->dialect->quote_identifier($spec->table_name),
            'WHERE',
            $spec->dialect->quote_identifier($spec->id_column),
            '= ?',
        ]);

        return new Statement($query, [$spec->id_value]);
    }

    /**
     * DELETE from the accumulated WHERE conditions. With join sources, the
     * MySQL `DELETE target FROM table JOIN .. WHERE ..` form, where `target`
     * names the table or alias the delete removes from.
     */
    public static function delete_many(BulkDeleteSpec $spec): Statement
    {
        $where = self::build_where_condition($spec->where_conditions, $spec->dialect);

        if ($spec->join_sources !== []) {
            $query = self::join_if_not_empty(' ', [
                "DELETE {$spec->target} FROM",
                $spec->dialect->quote_identifier($spec->table_name),
                self::join_fragments($spec->join_sources, $spec->dialect),
                $where->fragment,
            ]);

            return new Statement($query, $where->values);
        }

        $query = self::join_if_not_empty(' ', [
            'DELETE FROM',
            $spec->dialect->quote_identifier($spec->table_name),
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
    private static function placeholders(array $fields, array $expr_fields = []): string
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
    private static function bound_values(WriteSpec $spec): array
    {
        return array_values(array_diff_key($spec->dirty_fields, $spec->expr_fields));
    }

    /**
     * @param string[] $pieces
     */
    private static function join_if_not_empty(string $glue, array $pieces): string
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
     * Inline bound values into the query, outside quoted strings.
     * The result is an inlined query, ready to run as-is.
     * @param array<int|string, mixed> $parameters
     */
    public static function inline_query(string $query, array $parameters, callable $quote): string
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
            $query = self::replace_placeholders_outside_quotes($query);
        } else {
            $query = str_replace('?', '%s', $query);
        }

        return vsprintf($query, $parameters);
    }

    private static function replace_placeholders_outside_quotes(string $query): string
    {
        $re_valid = '/^(?:"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^\'"\\\\])*\z/s';
        if (!preg_match($re_valid, $query)) {
            throw new \InvalidArgumentException('Cannot replace ? placeholders in a query with unbalanced quotes');
        }

        // Replace ? placeholders with inline data
        return preg_replace_callback(
            '/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|\?/s',
            static fn(array $matches): string => $matches[0] === '?' ? '%s' : $matches[0],
            $query,
        );
    }
}
