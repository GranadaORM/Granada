<?php

use Granada\ORM;
use Granada\Granada;

class QueryBuilderTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_db();
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testFindMany()
    {
        $statement = Granada::for_table('widget')->to_statement();

        $this->assertSame('SELECT * FROM `widget`', $statement->query);
        $this->assertSame([], $statement->values);
    }

    public function testStatementRendersBoundValuesWithoutAConnection()
    {
        $query = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where_gt('age', 10);

        ORM::reset_db();

        $statement = $query->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? AND `widget`.`age` > ?', $statement->query);
        $this->assertSame(['Fred', 10], $statement->values);
    }

    public function testStatementLeavesTheQueryReadyToRun()
    {
        $query = Granada::for_table('widget')->where('name', 'Widget1');

        $query->to_statement();

        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        $this->assertSame('Widget1', $query->find_one()->name);
    }

    public function testWhereIdIs()
    {
        $statement = Granada::for_table('widget')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`id` = ? LIMIT 1', $statement->query);
        $this->assertSame([5], $statement->values);
    }

    public function testSingleWhereClause()
    {
        $statement = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Fred'], $statement->values);
    }

    public function testClearWhereClause()
    {
        $statement = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->clear_where()
            ->where('name', 'Joe')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Joe'], $statement->values);
    }

    public function testClearWhereClauseDiffField()
    {
        $statement = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->clear_where()
            ->where('age', 10)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` = ? LIMIT 1', $statement->query);
        $this->assertSame([10], $statement->values);
    }

    public function testSingleWhereClauseEqEmpty()
    {
        $statement = Granada::for_table('widget')
            ->where('name', '')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? LIMIT 1', $statement->query);
        $this->assertSame([''], $statement->values);
    }

    public function testSingleWhereClauseEqNULL()
    {
        $statement = Granada::for_table('widget')
            ->where('name', null)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NULL LIMIT 1', $statement->query);
    }

    public function testSingleWhereEqualsClauseEqNULL()
    {
        $statement = Granada::for_table('widget')
            ->where_equal('name', null)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NULL LIMIT 1', $statement->query);
    }

    public function testSingleWhereNotEqNULL()
    {
        $statement = Granada::for_table('widget')
            ->where_not_equal('name', null)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NOT NULL LIMIT 1', $statement->query);
    }

    public function testMultipleWhereNULLClauses()
    {
        $statement = Granada::for_table('widget')
            ->where('name', null)
            ->where('age', null)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NULL AND `widget`.`age` IS NULL LIMIT 1', $statement->query);
    }

    public function testMultipleWhereClausesOneNULL()
    {
        $statement = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where('age', null)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? AND `widget`.`age` IS NULL LIMIT 1', $statement->query);
        $this->assertSame(['Fred'], $statement->values);
    }

    public function testMultipleWhereClauses()
    {
        $statement = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where('age', 10)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? AND `widget`.`age` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Fred', 10], $statement->values);
    }

    public function testRemoveWhereClause()
    {
        $statement = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where('age', 10)
            ->remove_where('name')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` = ? LIMIT 1', $statement->query);
        $this->assertSame([10], $statement->values);
    }

    public function testOptionalWhereClauses()
    {
        $statement = Granada::for_table('widget')
            ->onlyif(true, function ($q) {
                return $q->where('name', 'Fred');
            })
            ->where('age', 10)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? AND `widget`.`age` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Fred', 10], $statement->values);
    }

    public function testOptionalWhereClauses2()
    {
        $statement = Granada::for_table('widget')
            ->onlyif(false, function ($q) {
                return $q->where('name', 'Fred');
            })
            ->where('age', 10)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` = ? LIMIT 1', $statement->query);
        $this->assertSame([10], $statement->values);
    }

    public function testOptionalWhereClausesExtraParams()
    {
        $where_name = 'Fred';
        $statement  = Granada::for_table('widget')
            ->onlyif(true, function ($q) use ($where_name) {
                return $q->where('name', $where_name);
            })
            ->where('age', 10)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? AND `widget`.`age` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Fred', 10], $statement->values);
    }

    public function testOptionalWhereClausesVariable1()
    {
        $min_age   = 10;
        $statement = Granada::for_table('widget')
            ->onlyif($min_age > 0, function ($q) use ($min_age) {
                return $q->where_gte('age', $min_age);
            })
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` >= ? LIMIT 1', $statement->query);
        $this->assertSame([10], $statement->values);
    }

    public function testOptionalWhereClausesVariable2()
    {
        $min_age   = 0;
        $statement = Granada::for_table('widget')
            ->onlyif($min_age > 0, function ($q) use ($min_age) {
                return $q->where_gte('age', $min_age);
            })
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LIMIT 1', $statement->query);
    }

    public function testOptionalOrderClause1()
    {
        $order_by_age  = true;
        $order_by_name = true;
        $statement     = Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `age` ASC, `name` ASC LIMIT 1', $statement->query);
    }

    public function testOptionalOrderClause2()
    {
        $order_by_age  = true;
        $order_by_name = false;
        $statement     = Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `age` ASC LIMIT 1', $statement->query);
    }

    public function testOptionalOrderClause3()
    {
        $order_by_age  = false;
        $order_by_name = false;
        $statement     = Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LIMIT 1', $statement->query);
    }

    public function testWhereNotEqual()
    {
        $statement = Granada::for_table('widget')
            ->where_not_equal('name', 'Fred')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` != ?', $statement->query);
        $this->assertSame(['Fred'], $statement->values);
    }

    public function testWhereLike()
    {
        $statement = Granada::for_table('widget')
            ->where_like('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testWhereNotLike()
    {
        $statement = Granada::for_table('widget')
            ->where_not_like('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` NOT LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testWhereIn()
    {
        $statement = Granada::for_table('widget')
            ->where_in('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testWhereInNoItems()
    {
        $statement = Granada::for_table('widget')
            ->where_in('name', [])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE 0', $statement->query);
    }

    public function testWhereInNULL()
    {
        $statement = Granada::for_table('widget')
            ->where_in('custid', null)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE 0', $statement->query);
    }

    public function testWhereNotIn()
    {
        $statement = Granada::for_table('widget')
            ->where_not_in('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` NOT IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testWhereNotInOrNull()
    {
        $statement = Granada::for_table('widget')
            ->where_not_in_or_null('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame(
            'SELECT * FROM `widget` WHERE ( `widget`.`name` NOT IN (?, ?) OR `widget`.`name` IS NULL )',
            $statement->query
        );
        $this->assertSame(['Fred', 'Joe'], $statement->values);

        $statement = Granada::for_table('widget')
            ->where_not_in_or_null('name', [])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget`', $statement->query);
    }

    public function testWhereNotInNoItems()
    {
        $statement = Granada::for_table('widget')
            ->where_not_in('name', [])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget`', $statement->query);
    }

    public function testWhereLtOrNull()
    {
        $statement = Granada::for_table('widget')
            ->where_lt_or_null('age', '20')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE ( `widget`.`age` < ? OR `widget`.`age` IS NULL )', $statement->query);
        $this->assertSame(['20'], $statement->values);
    }

    public function testWhereLteOrNull()
    {
        $statement = Granada::for_table('widget')
            ->where_lte_or_null('age', '20')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE ( `widget`.`age` <= ? OR `widget`.`age` IS NULL )', $statement->query);
        $this->assertSame(['20'], $statement->values);
    }

    public function testWhereGtOrNull()
    {
        $statement = Granada::for_table('widget')
            ->where_gt_or_null('age', '20')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE ( `widget`.`age` > ? OR `widget`.`age` IS NULL )', $statement->query);
        $this->assertSame(['20'], $statement->values);
    }

    public function testWhereGteOrNull()
    {
        $statement = Granada::for_table('widget')
            ->where_gte_or_null('age', '20')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE ( `widget`.`age` >= ? OR `widget`.`age` IS NULL )', $statement->query);
        $this->assertSame(['20'], $statement->values);
    }

    public function testWhereAnyIsSingleCol()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                ['name' => 'Joe'],
                ['name' => 'Fred'],
            ])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? ) OR ( `name` = ? ))', $statement->query);
        $this->assertSame(['Joe', 'Fred'], $statement->values);
    }

    public function testWhereAnyIs()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => 10,
                ],
                [
                    'name' => 'Fred',
                    'age'  => 20,
                ],
            ])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? AND `age` = ? ) OR ( `name` = ? AND `age` = ? ))', $statement->query);
        $this->assertSame(['Joe', 10, 'Fred', 20], $statement->values);
    }

    public function testWhereAnyIsAssymetricComparisons()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                ['name' => 'Joe'],
                [
                    'name' => 'Fred',
                    'age'  => 20,
                ],
            ])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? ) OR ( `name` = ? AND `age` = ? ))', $statement->query);
        $this->assertSame(['Joe', 'Fred', 20], $statement->values);
    }

    public function testWhereAnyIsOverrideOneColumn()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => 10,
                ],
                [
                    'name' => 'Fred',
                    'age'  => 20,
                ],
            ], ['age' => '>'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? AND `age` > ? ) OR ( `name` = ? AND `age` > ? ))', $statement->query);
        $this->assertSame(['Joe', 10, 'Fred', 20], $statement->values);
    }

    public function testWhereAnyIsOverrideAllOperators()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'score' => '5',
                    'age'   => 10,
                ],
                [
                    'score' => '15',
                    'age'   => 20,
                ],
            ], '>')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `score` > ? AND `age` > ? ) OR ( `score` > ? AND `age` > ? ))', $statement->query);
        $this->assertSame(['5', 10, '15', 20], $statement->values);
    }

    public function testWhereAnyIsNULLs()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => null,
                ],
                [
                    'name' => null,
                    'age'  => 20,
                ],
            ])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? AND `age` IS NULL ) OR ( `name` IS NULL AND `age` = ? ))', $statement->query);
        $this->assertSame(['Joe', 20], $statement->values);
    }

    public function testWhereAnyIsNOTNULLs()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => null,
                ],
                [
                    'name' => null,
                    'age'  => 20,
                ],
            ], '!=')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` != ? AND `age` IS NOT NULL ) OR ( `name` IS NOT NULL AND `age` != ? ))', $statement->query);
        $this->assertSame(['Joe', 20], $statement->values);
    }

    public function testWhereAnyIsIns()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => [18, 19],
                ],
                [
                    'name' => ['Bob', 'Jack'],
                    'age'  => 20,
                ],
            ])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? AND `age` IN (?, ?) ) OR ( `name` IN (?, ?) AND `age` = ? ))', $statement->query);
        $this->assertSame(['Joe', 18, 19, 'Bob', 'Jack', 20], $statement->values);
    }

    public function testWhereAnyIsNOTIns()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => [18, 19],
                ],
                [
                    'name' => ['Bob', 'Jack'],
                    'age'  => 20,
                ],
            ], '!=')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` != ? AND `age` NOT IN (?, ?) ) OR ( `name` NOT IN (?, ?) AND `age` != ? ))', $statement->query);
        $this->assertSame(['Joe', 18, 19, 'Bob', 'Jack', 20], $statement->values);
    }

    public function testWhereAnyIsInsMixedComparator()
    {
        $statement = Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => [18, 19],
                ],
                [
                    'name' => ['Bob', 'Jack'],
                    'age'  => 20,
                ],
            ], ['age' => '!='])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? AND `age` NOT IN (?, ?) ) OR ( `name` IN (?, ?) AND `age` != ? ))', $statement->query);
        $this->assertSame(['Joe', 18, 19, 'Bob', 'Jack', 20], $statement->values);
    }

    public function testLimit()
    {
        $statement = Granada::for_table('widget')
            ->limit(5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LIMIT 5', $statement->query);
    }

    public function testLimitAndOffset()
    {
        $statement = Granada::for_table('widget')
            ->limit(5)
            ->offset(5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LIMIT 5 OFFSET 5', $statement->query);
    }

    public function testClearLimitAndOffset()
    {
        $statement = Granada::for_table('widget')
            ->limit(5)
            ->offset(5)
            ->limit(null)
            ->offset(null)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget`', $statement->query);
    }

    public function testOrderByDesc()
    {
        $statement = Granada::for_table('widget')
            ->order_by_desc('name')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `name` DESC LIMIT 1', $statement->query);
    }

    public function testOrderByAsc()
    {
        $statement = Granada::for_table('widget')
            ->order_by_asc('name')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `name` ASC LIMIT 1', $statement->query);
    }

    public function testOrderByExpression()
    {
        $statement = Granada::for_table('widget')
            ->order_by_expr('SOUNDEX(`name`)')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY SOUNDEX(`name`) LIMIT 1', $statement->query);
    }

    public function testMultipleOrderBy()
    {
        $statement = Granada::for_table('widget')
            ->order_by_asc('name')
            ->order_by_desc('age')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `name` ASC, `age` DESC LIMIT 1', $statement->query);
    }

    public function testOrderByClear()
    {
        $statement = Granada::for_table('widget')
            ->order_by_asc('name')
            ->order_by_desc('age')
            ->order_by_clear()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LIMIT 1', $statement->query);
    }

    public function testOrderByClearAddMore()
    {
        $statement = Granada::for_table('widget')
            ->order_by_asc('name')
            ->order_by_desc('age')
            ->order_by_clear()
            ->order_by_asc('id')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `id` ASC LIMIT 1', $statement->query);
    }

    public function testGroupBy()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name`', $statement->query);
    }

    public function testMultipleGroupBy()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->group_by('age')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name`, `age`', $statement->query);
    }

    public function testGroupByExpression()
    {
        $statement = Granada::for_table('widget')
            ->group_by_expr("FROM_UNIXTIME(`time`, '%Y-%m')")
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY FROM_UNIXTIME(`time`, \'%Y-%m\')', $statement->query);
    }

    public function testHaving()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having('name', 'Fred')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Fred'], $statement->values);
    }

    public function testClearHaving()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having('name', 'Fred')
            ->clear_having()
            ->having('name', 'Joe')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Joe'], $statement->values);
    }

    public function testMultipleHaving()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having('name', 'Fred')
            ->having('age', 10)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` = ? AND `age` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Fred', 10], $statement->values);
    }

    public function testHavingNotEqual()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_not_equal('name', 'Fred')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` != ?', $statement->query);
        $this->assertSame(['Fred'], $statement->values);
    }

    public function testHavingIdIs()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `id` = ? LIMIT 1', $statement->query);
        $this->assertSame([5], $statement->values);
    }

    public function testHavingLike()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_like('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testHavingNotLike()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_not_like('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testHavingIn()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_in('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testHavingNotIn()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_not_in('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testHavingLessThan()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_lt('age', 10)
            ->having_gt('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `age` < ? AND `age` > ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testHavingLessThanOrEqualAndGreaterThanOrEqual()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_lte('age', 10)
            ->having_gte('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `age` <= ? AND `age` >= ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testHavingNull()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_null('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NULL', $statement->query);
    }

    public function testHavingNotNull()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_not_null('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NOT NULL', $statement->query);
    }

    public function testRawHaving()
    {
        $statement = Granada::for_table('widget')
            ->group_by('name')
            ->having_raw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` = ? AND (`age` = ? OR `age` = ?)', $statement->query);
        $this->assertSame(['Fred', 5, 10], $statement->values);
    }

    public function testComplexQuery()
    {
        $statement = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->limit(5)
            ->offset(5)
            ->order_by_asc('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? ORDER BY `name` ASC LIMIT 5 OFFSET 5', $statement->query);
        $this->assertSame(['Fred'], $statement->values);
    }

    public function testWhereLessThanAndGreaterThan()
    {
        $statement = Granada::for_table('widget')
            ->where_lt('age', 10)
            ->where_gt('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` < ? AND `widget`.`age` > ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testWhereLessThanAndEqualAndGreaterThanAndEqual()
    {
        $statement = Granada::for_table('widget')
            ->where_lte('age', 10)
            ->where_gte('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` <= ? AND `widget`.`age` >= ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testWhereNull()
    {
        $statement = Granada::for_table('widget')
            ->where_null('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NULL', $statement->query);
    }

    public function testWhereNotNull()
    {
        $statement = Granada::for_table('widget')
            ->where_not_null('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NOT NULL', $statement->query);
    }

    public function testRawWhereClause()
    {
        $statement = Granada::for_table('widget')
            ->where_raw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `name` = ? AND (`age` = ? OR `age` = ?)', $statement->query);
        $this->assertSame(['Fred', 5, 10], $statement->values);
    }

    public function testRawWhereClauseWithPercentSign()
    {
        $statement = Granada::for_table('widget')
            ->where_raw('STRFTIME("%Y", "now") = ?', [2012])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE STRFTIME("%Y", "now") = ?', $statement->query);
        $this->assertSame([2012], $statement->values);
    }

    public function testRawWhereClauseWithNoParameters()
    {
        $statement = Granada::for_table('widget')
            ->where_raw('`name` = "Fred"')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `name` = "Fred"', $statement->query);
    }

    public function testRawWhereClauseInMethodChain()
    {
        $statement = Granada::for_table('widget')
            ->where('age', 18)
            ->where_raw('(`name` = ? OR `name` = ?)', ['Fred', 'Bob'])
            ->where('size', 'large')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` = ? AND (`name` = ? OR `name` = ?) AND `widget`.`size` = ?', $statement->query);
        $this->assertSame([18, 'Fred', 'Bob', 'large'], $statement->values);
    }

    public function testRawQuery()
    {
        $statement = Granada::for_table('widget')
            ->raw_query('SELECT `w`.* FROM `widget` w')
            ->to_statement();

        $this->assertSame('SELECT `w`.* FROM `widget` w', $statement->query);
    }

    public function testRawQueryWithParameters()
    {
        $statement = Granada::for_table('widget')
            ->raw_query('SELECT `w`.* FROM `widget` w WHERE `name` = ? AND `age` = ?', ['Fred', 5])
            ->to_statement();

        $this->assertSame('SELECT `w`.* FROM `widget` w WHERE `name` = ? AND `age` = ?', $statement->query);
        $this->assertSame(['Fred', 5], $statement->values);
    }

    public function testSelectAsteriskColumn()
    {
        $statement = Granada::for_table('widget')
            ->select('name')
            ->to_statement();

        $this->assertSame('SELECT `name` FROM `widget`', $statement->query);

        $statement = Granada::for_table('widget')
            ->select('name')
            ->select('*')
            ->to_statement();

        $this->assertSame('SELECT *, `name` FROM `widget`', $statement->query);

        $statement = Granada::for_table('widget')
            ->select('*')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget`', $statement->query);
    }

    public function testSelectAsteriskColumnTwice()
    {
        $statement = Granada::for_table('widget')
            ->select('name')
            ->to_statement();

        $this->assertSame('SELECT `name` FROM `widget`', $statement->query);

        $statement = Granada::for_table('widget')
            ->select('name')
            ->select('*')
            ->select('*')
            ->to_statement();

        $this->assertSame('SELECT *, `name` FROM `widget`', $statement->query);

        $statement = Granada::for_table('widget')
            ->select('*')
            ->select('*')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget`', $statement->query);
    }

    public function testSimpleResultColumn()
    {
        $statement = Granada::for_table('widget')
            ->select('name')
            ->to_statement();

        $this->assertSame('SELECT `name` FROM `widget`', $statement->query);
    }

    public function testMultipleSimpleResultColumns()
    {
        $statement = Granada::for_table('widget')
            ->select('name')
            ->select('age')
            ->to_statement();

        $this->assertSame('SELECT `name`, `age` FROM `widget`', $statement->query);
    }

    public function testSpecifyTableNameAndColumnInResultColumns()
    {
        $statement = Granada::for_table('widget')
            ->select('widget.name')
            ->to_statement();

        $this->assertSame('SELECT `widget`.`name` FROM `widget`', $statement->query);
    }

    public function testMainTableAlias()
    {
        $statement = Granada::for_table('widget')
            ->table_alias('w')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` `w`', $statement->query);
    }

    public function testAliasesInResultColumns()
    {
        $statement = Granada::for_table('widget')
            ->select('widget.name', 'widget_name')
            ->to_statement();

        $this->assertSame('SELECT `widget`.`name` AS `widget_name` FROM `widget`', $statement->query);
    }

    public function testAliasesInSelectManyResults()
    {
        $statement = Granada::for_table('widget')
            ->select_many(['widget_name' => 'widget.name'], 'widget_handle')
            ->to_statement();

        $this->assertSame('SELECT `widget`.`name` AS `widget_name`, `widget_handle` FROM `widget`', $statement->query);
    }

    public function testSelectManyAsList()
    {
        $statement = Granada::for_table('widget')
            ->select_many(['name', 'price'])
            ->to_statement();

        $this->assertSame('SELECT `name`, `price` FROM `widget`', $statement->query);
    }

    public function testLiteralExpressionInResultColumn()
    {
        $statement = Granada::for_table('widget')
            ->select_expr('COUNT(*)', 'count')
            ->to_statement();

        $this->assertSame('SELECT COUNT(*) AS `count` FROM `widget`', $statement->query);
    }

    public function testLiteralExpressionInSelectManyResultColumns()
    {
        $statement = Granada::for_table('widget')
            ->select_many_expr(['count' => 'COUNT(*)'], 'SUM(widget_order)')
            ->to_statement();

        $this->assertSame('SELECT COUNT(*) AS `count`, SUM(widget_order) FROM `widget`', $statement->query);
    }

    public function testSimpleJoin()
    {
        $statement = Granada::for_table('widget')
            ->join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testSimpleJoinWithWhereIdIsMethod()
    {
        $statement = Granada::for_table('widget')
            ->join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` WHERE `widget`.`id` = ? LIMIT 1', $statement->query);
        $this->assertSame([5], $statement->values);
    }

    public function testInnerJoin()
    {
        $statement = Granada::for_table('widget')
            ->inner_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` INNER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testLeftOuterJoin()
    {
        $statement = Granada::for_table('widget')
            ->left_outer_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LEFT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testRightOuterJoin()
    {
        $statement = Granada::for_table('widget')
            ->right_outer_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` RIGHT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testFullOuterJoin()
    {
        $statement = Granada::for_table('widget')
            ->full_outer_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` FULL OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testMultipleJoinSources()
    {
        $statement = Granada::for_table('widget')
            ->join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->join('widget_nozzle', ['widget_nozzle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` JOIN `widget_nozzle` ON `widget_nozzle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testJoinWithAliases()
    {
        $statement = Granada::for_table('widget')
            ->join('widget_handle', ['wh.widget_id', '=', 'widget.id'], 'wh')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` JOIN `widget_handle` `wh` ON `wh`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testJoinWithAliasesAndWhere()
    {
        $statement = Granada::for_table('widget')
            ->table_alias('w')
            ->join('widget_handle', ['wh.widget_id', '=', 'w.id'], 'wh')
            ->where_equal('id', 1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` `w` JOIN `widget_handle` `wh` ON `wh`.`widget_id` = `w`.`id` WHERE `w`.`id` = ?', $statement->query);
        $this->assertSame([1], $statement->values);
    }

    public function testJoinWithStringConstraint()
    {
        $statement = Granada::for_table('widget')
            ->join('widget_handle', 'widget_handle.widget_id = widget.id')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` JOIN `widget_handle` ON widget_handle.widget_id = widget.id', $statement->query);
    }

    public function testSelectWithDistinct()
    {
        $statement = Granada::for_table('widget')
            ->distinct()
            ->select('name')
            ->to_statement();

        $this->assertSame('SELECT DISTINCT `name` FROM `widget`', $statement->query);
    }

    public function testIssue12IncorrectQuotingOfColumnWildcard()
    {
        $statement = Granada::for_table('widget')
            ->select('widget.*')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT `widget`.* FROM `widget` LIMIT 1', $statement->query);
    }

    public function testIssue57LogQueryRaisesWarningWhenPercentSymbolSupplied()
    {
        $statement = Granada::for_table('widget')
            ->where_raw('username LIKE "ben%"')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE username LIKE "ben%"', $statement->query);
    }

    public function testIssue57LogQueryRaisesWarningWhenQuestionMarkSupplied()
    {
        $statement = Granada::for_table('widget')
            ->where_raw('comments LIKE "has been released?%"')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE comments LIKE "has been released?%"', $statement->query);
    }

    public function testGetSelectQuery()
    {
        $this->assertSame('SELECT * FROM `widget`', Granada::for_table('widget')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `widget`.`name` != 'Fred'", Granada::for_table('widget')
            ->where_not_equal('name', 'Fred')
            ->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `widget`.`name` IN ('Fred', 'Joe')", Granada::for_table('widget')
            ->where_in('name', ['Fred', 'Joe'])
            ->get_select_query());
        $this->assertSame('SELECT * FROM `widget` WHERE 0', Granada::for_table('widget')
            ->where_in('name', [])
            ->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `widget`.`name` NOT IN ('Fred', 'Joe')", Granada::for_table('widget')
            ->where_not_in('name', ['Fred', 'Joe'])
            ->get_select_query());
        $this->assertSame('SELECT * FROM `widget`', Granada::for_table('widget')
            ->where_not_in('name', [])
            ->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `widget`.`age` < '20' OR `widget`.`age` IS NULL )", Granada::for_table('widget')
            ->where_lt_or_null('age', '20')
            ->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `widget`.`age` <= '20' OR `widget`.`age` IS NULL )", Granada::for_table('widget')
            ->where_lte_or_null('age', '20')
            ->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `widget`.`age` > '20' OR `widget`.`age` IS NULL )", Granada::for_table('widget')
            ->where_gt_or_null('age', '20')
            ->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `widget`.`age` >= '20' OR `widget`.`age` IS NULL )", Granada::for_table('widget')
            ->where_gte_or_null('age', '20')
            ->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' ))", Granada::for_table('widget')
            ->where_any_is([
                ['name' => 'Joe'],
                ['name' => 'Fred'],
            ])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` = '10' ) OR ( `name` = 'Fred' AND `age` = '20' ))", Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => 10,
                ],
                [
                    'name' => 'Fred',
                    'age'  => 20,
                ],
            ])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))", Granada::for_table('widget')
            ->where_any_is([
                ['name' => 'Joe'],
                [
                    'name' => 'Fred',
                    'age'  => 20,
                ],
            ])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))", Granada::for_table('widget')
            ->where_any_is([
                [
                    'name' => 'Joe',
                    'age'  => 10,
                ],
                [
                    'name' => 'Fred',
                    'age'  => 20,
                ],
            ], ['age' => '>'])->get_select_query());
        $this->assertSame('SELECT * FROM `widget` WHERE username LIKE "ben%"', Granada::for_table('widget')
            ->where_raw('username LIKE "ben%"')
            ->get_select_query());
    }

    public function testWhereClosureBuildsGroup()
    {
        $query = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where(function ($q): void {
                $q->where('age', 18)->where('role', 'admin');
            });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE `widget`.`name` = 'Fred' AND ( `widget`.`age` = '18' AND `widget`.`role` = 'admin' )",
            $query->get_select_query()
        );
    }

    public function testOrWhereClosureJoinsGroupWithOr()
    {
        $query = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->or_where(function ($q): void {
                $q->where('age', 18)->where('role', 'admin');
            });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE `widget`.`name` = 'Fred' OR ( `widget`.`age` = '18' AND `widget`.`role` = 'admin' )",
            $query->get_select_query()
        );
    }

    public function testNestedClosuresBuildNestedGroups()
    {
        $query = Granada::for_table('widget')
            ->where(function ($q): void {
                $q->where('role', 'admin')
                    ->or_where(function ($inner): void {
                        $inner->where_gt('age', 18)->where_lt('age', 65);
                    });
            });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`role` = 'admin' OR ( `widget`.`age` > '18' AND `widget`.`age` < '65' ) )",
            $query->get_select_query()
        );
    }

    public function testClosureReturnValueIgnored()
    {
        $query = Granada::for_table('widget')->where(function ($q) {
            return $q->where('age', 18)->where('name', 'Fred');
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`age` = '18' AND `widget`.`name` = 'Fred' )",
            $query->get_select_query()
        );
    }

    public function testEmptyClosureAddsNoCondition()
    {
        $query = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where(function ($q): void {});

        $this->assertSame(
            "SELECT * FROM `widget` WHERE `widget`.`name` = 'Fred'",
            $query->get_select_query()
        );
    }

    public function testTypedClosureReceivesTheQuery()
    {
        $query = Granada::for_table('widget')->where(function (\Granada\Query $q): void {
            $q->where('age', 18)->where('role', 'admin');
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`age` = '18' AND `widget`.`role` = 'admin' )",
            $query->get_select_query()
        );
    }

    public function testClosureOnAModelLessQuery()
    {
        $query = Granada::for_table('widget')->where(function ($q): void {
            $q->where('age', 18);
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`age` = '18' )",
            $query->get_select_query()
        );
    }

    public function testMagicWhereSuffixOnAModelLessQuery()
    {
        $query = Granada::for_table('user')
            ->where_last_login_gt('2020-01-01')
            ->or_where_role('admin');

        $this->assertSame(
            "SELECT * FROM `user` WHERE ( `user`.`last_login` > '2020-01-01' OR `user`.`role` = 'admin' )",
            $query->get_select_query()
        );
    }

    public function testSuffixWhereAndNamedWhereAgreeOnAPlainQuery()
    {
        $named = Granada::for_table('widget')
            ->where_gt('age', 10)
            ->get_select_query();
        $suffix = Granada::for_table('widget')
            ->where_age_gt(10)
            ->get_select_query();

        $this->assertSame($named, $suffix);
    }

    public function testSuffixWhereAndNamedWhereAgreeUnderAJoin()
    {
        $suffix = Granada::for_table('widget')
            ->join('gadget', ['gadget.id', '=', 'widget.gadget_id'])
            ->where_age_gt(10);

        $named = Granada::for_table('widget')
            ->join('gadget', ['gadget.id', '=', 'widget.gadget_id'])
            ->where_gt('age', 10);

        $this->assertSame(
            "SELECT * FROM `widget` JOIN `gadget` ON `gadget`.`id` = `widget`.`gadget_id` WHERE `widget`.`age` > '10'",
            $suffix->get_select_query()
        );
        $this->assertSame($named->get_select_query(), $suffix->get_select_query());
    }

    public function testSuffixWhereUsesTheTableAliasUnderAJoin()
    {
        $suffix = Granada::for_table('widget')
            ->table_alias('w')
            ->join('gadget', ['gadget.id', '=', 'w.gadget_id'])
            ->where_age_gt(10);

        $named = Granada::for_table('widget')
            ->table_alias('w')
            ->join('gadget', ['gadget.id', '=', 'w.gadget_id'])
            ->where_gt('age', 10);

        $this->assertSame(
            "SELECT * FROM `widget` `w` JOIN `gadget` ON `gadget`.`id` = `w`.`gadget_id` WHERE `w`.`age` > '10'",
            $suffix->get_select_query()
        );
        $this->assertSame($named->get_select_query(), $suffix->get_select_query());
    }

    public function testSuffixWhereAndNamedWhereAgreeForEverySuffix()
    {
        $aliased = fn(): \Granada\Query => Granada::for_table('widget')
            ->table_alias('w')
            ->join('gadget', ['gadget.id', '=', 'w.gadget_id']);

        $pairs = [
            'where_age_not_equal'      => ['where_not_equal', 10],
            'where_age_like'           => ['where_like', '%1%'],
            'where_age_not_like'       => ['where_not_like', '%1%'],
            'where_age_lt'             => ['where_lt', 10],
            'where_age_lte'            => ['where_lte', 10],
            'where_age_gt'             => ['where_gt', 10],
            'where_age_gte'            => ['where_gte', 10],
            'where_age_lt_or_null'     => ['where_lt_or_null', 10],
            'where_age_lte_or_null'    => ['where_lte_or_null', 10],
            'where_age_gt_or_null'     => ['where_gt_or_null', 10],
            'where_age_gte_or_null'    => ['where_gte_or_null', 10],
            'where_age_in'             => ['where_in', [10, 11]],
            'where_age_not_in'         => ['where_not_in', [10, 11]],
            'where_age_not_in_or_null' => ['where_not_in_or_null', [10, 11]],
        ];

        foreach ($pairs as $suffix_method => [$named_method, $argument]) {
            $suffix = $aliased()->{$suffix_method}($argument);
            $named  = $aliased()->{$named_method}('age', $argument);

            $this->assertSame($named->get_select_query(), $suffix->get_select_query(), $suffix_method);
        }

        $null_pairs = [
            'where_null'     => ['age', 'where_age_null'],
            'where_not_null' => ['age', 'where_age_not_null'],
        ];

        foreach ($null_pairs as $named_method => [$column, $suffix_method]) {
            $suffix = $aliased()->{$suffix_method}();
            $named  = $aliased()->{$named_method}($column);

            $this->assertSame($named->get_select_query(), $suffix->get_select_query(), $suffix_method);
        }
    }

    public function testSuffixWhereAdjustsTheTimezoneAndTheNamedMethodDoesNot()
    {
        $suffix = StampedChild::q()
            ->where_last_login_gt('2020-01-01')
            ->get_select_query();
        $named = StampedChild::q()
            ->where_gt('last_login', '2020-01-01')
            ->get_select_query();

        $this->assertSame("SELECT * FROM `stamped_child` WHERE `stamped_child`.`last_login` > '2020-01-01@utc'", $suffix);
        $this->assertSame("SELECT * FROM `stamped_child` WHERE `stamped_child`.`last_login` > '2020-01-01'", $named);
    }

    public function testMagicOrderBySuffixOnAModelLessQuery()
    {
        $query = Granada::for_table('part')->order_by_name_desc();

        $this->assertSame(
            'SELECT * FROM `part` ORDER BY `name` DESC',
            $query->get_select_query()
        );
    }

    public function testMagicWithSuffixOnAModelLessQuery()
    {
        $query = Granada::for_table('widget')->with_user();

        $this->assertSame(
            'SELECT * FROM `widget`',
            $query->get_select_query()
        );
    }

    public function testWhereNotWithCompareRendersNot()
    {
        $query = Granada::for_table('widget')->where_not('role', 'banned');

        $this->assertSame(
            "SELECT * FROM `widget` WHERE NOT ( `widget`.`role` = 'banned' )",
            $query->get_select_query()
        );
    }

    public function testWhereNotWithClosure()
    {
        $query = Granada::for_table('widget')->where_not(function ($q): void {
            $q->where('role', 'banned')->where('active', 0);
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE NOT ( `widget`.`role` = 'banned' AND `widget`.`active` = '0' )",
            $query->get_select_query()
        );
    }

    public function testWhereNotWithNullCompare()
    {
        $query = Granada::for_table('widget')->where_not('role', null);

        $this->assertSame(
            'SELECT * FROM `widget` WHERE NOT ( `widget`.`role` IS NULL )',
            $query->get_select_query()
        );
    }

    public function testOrWhereCompareJoinsWithOr()
    {
        $query = Granada::for_table('widget')
            ->where('role', 'admin')
            ->or_where('role', 'owner');

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`role` = 'admin' OR `widget`.`role` = 'owner' )",
            $query->get_select_query()
        );
    }

    public function testOrWhereNotCompare()
    {
        $query = Granada::for_table('widget')
            ->where('role', 'admin')
            ->or_where_not('role', 'banned');

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`role` = 'admin' OR NOT ( `widget`.`role` = 'banned' ) )",
            $query->get_select_query()
        );
    }

    public function testOrWhereNotClosure()
    {
        $query = Granada::for_table('widget')
            ->where('role', 'admin')
            ->or_where_not(function ($q): void {
                $q->where('active', 0);
            });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE `widget`.`role` = 'admin' OR NOT ( `widget`.`active` = '0' )",
            $query->get_select_query()
        );
    }

    public function testWhereExistsRendersBoundSubquery()
    {
        $subquery = Granada::for_table('role')
            ->select('id')
            ->where_raw('user_id = widget.id')
            ->where('name', 'admin');

        $query = Granada::for_table('widget')->where_exists($subquery);

        $this->assertSame(
            "SELECT * FROM `widget` WHERE EXISTS ( SELECT `id` FROM `role` WHERE user_id = widget.id AND `role`.`name` = 'admin' )",
            $query->get_select_query()
        );
    }

    public function testWhereNotExists()
    {
        $query = Granada::for_table('widget')->where_not_exists(
            Granada::for_table('ban')->where_raw('ban.user_id = widget.id')
        );

        $this->assertSame(
            'SELECT * FROM `widget` WHERE NOT EXISTS ( SELECT * FROM `ban` WHERE ban.user_id = widget.id )',
            $query->get_select_query()
        );
    }

    public function testWhereExistsInsideClosure()
    {
        $query = Granada::for_table('widget')->where(function ($q): void {
            $q->where('active', 1)->where_exists(
                Granada::for_table('ban')->where_raw('ban.user_id = widget.id')
            );
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`active` = '1' AND EXISTS ( SELECT * FROM `ban` WHERE ban.user_id = widget.id ) )",
            $query->get_select_query()
        );
    }

    public function testHavingClosureBuildsGroup()
    {
        $query = Granada::for_table('widget')
            ->group_by('role')
            ->having('count', 3)
            ->having(function ($q): void {
                $q->having_gt('total', 10)->having_lt('total', 100);
            });

        $this->assertSame(
            "SELECT * FROM `widget` GROUP BY `role` HAVING `count` = '3' AND ( `total` > '10' AND `total` < '100' )",
            $query->get_select_query()
        );
    }

    public function testOrHavingCompare()
    {
        $query = Granada::for_table('widget')
            ->group_by('role')
            ->having('count', 3)
            ->or_having('count', 5);

        $this->assertSame(
            "SELECT * FROM `widget` GROUP BY `role` HAVING ( `count` = '3' OR `count` = '5' )",
            $query->get_select_query()
        );
    }

    public function testOrHavingClosure()
    {
        $query = Granada::for_table('widget')
            ->group_by('role')
            ->having('count', 3)
            ->or_having(function ($q): void {
                $q->having_gt('total', 10)->having_lt('total', 100);
            });

        $this->assertSame(
            "SELECT * FROM `widget` GROUP BY `role` HAVING `count` = '3' OR ( `total` > '10' AND `total` < '100' )",
            $query->get_select_query()
        );
    }

    public function testHavingNotClosure()
    {
        $query = Granada::for_table('widget')
            ->group_by('role')
            ->having_not(function ($q): void {
                $q->having_gt('total', 10);
            });

        $this->assertSame(
            "SELECT * FROM `widget` GROUP BY `role` HAVING NOT ( `total` > '10' )",
            $query->get_select_query()
        );
    }

    public function testHavingNotCompare()
    {
        $query = Granada::for_table('widget')
            ->group_by('role')
            ->having_not('count', 3);

        $this->assertSame(
            "SELECT * FROM `widget` GROUP BY `role` HAVING NOT ( `count` = '3' )",
            $query->get_select_query()
        );
    }

    public function testOrHavingNotClosure()
    {
        $query = Granada::for_table('widget')
            ->group_by('role')
            ->having('count', 3)
            ->or_having_not(function ($q): void {
                $q->having_gt('total', 10);
            });

        $this->assertSame(
            "SELECT * FROM `widget` GROUP BY `role` HAVING `count` = '3' OR NOT ( `total` > '10' )",
            $query->get_select_query()
        );
    }

    public function testHavingExists()
    {
        $query = Granada::for_table('widget')
            ->group_by('role')
            ->having_exists(Granada::for_table('role')->where_raw('role.count = widget.count'))
            ->having_not_exists(Granada::for_table('role')->where_raw('role.count = widget.count'));

        $this->assertSame(
            'SELECT * FROM `widget` GROUP BY `role`'
            . ' HAVING EXISTS ( SELECT * FROM `role` WHERE role.count = widget.count )'
            . ' AND NOT EXISTS ( SELECT * FROM `role` WHERE role.count = widget.count )',
            $query->get_select_query()
        );
    }

    public function testRemoveWhereReachesIntoGroups()
    {
        $query = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where(function ($q): void {
                $q->where('age', 10);
            })
            ->where_exists(Granada::for_table('ban')->where_raw('`name` = 1'));

        $query->remove_where('name');

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `widget`.`age` = '10' )",
            $query->get_select_query()
        );
    }
}
