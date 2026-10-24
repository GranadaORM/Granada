<?php

use Granada\ORM;
use Granada\Granada;

class CamelCaseTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::resetDb();
    }

    protected function tearDown(): void
    {
        ORM::resetConfig();
        ORM::resetDb();
    }

    public function testFindManyAliasRunsTheQuery()
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        $widgets = Granada::for_table('widget')->findMany();

        $this->assertCount(5, $widgets);
    }

    public function testFindOneAliasRunsTheQuery()
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        $widget = Granada::for_table('widget')->findOne(3);

        $this->assertSame('Widget3', $widget->name);
    }

    public function testModelFindOneAliasWithoutAnIdFindsTheFirstRow()
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        $part = Part::q()->findOne();

        $this->assertSame('Part1', $part->name);
    }

    public function testModelFindManyAliasRunsTheQuery()
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('return_result_sets', false);

        $parts = Part::q()->findMany();

        $this->assertCount(5, $parts);
    }

    public function testModelFindOneAliasWithAnIdFindsThatRow()
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        $part = Part::q()->findOne(3);

        $this->assertSame('Part3', $part->name);
    }

    public function testWhereIdIs()
    {
        $statement = Granada::for_table('widget')
            ->whereIdIs(5)
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
            ->clearWhere()
            ->where('name', 'Joe')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Joe'], $statement->values);
    }

    public function testOptionalOrderClause1()
    {
        $order_by_age  = true;
        $order_by_name = true;
        $statement     = Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->orderByAsc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->orderByAsc('name');
            })
            ->limit(1)
            ->toStatement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `age` ASC, `name` ASC LIMIT 1', $statement->query);
    }

    public function testWhereNotEqual()
    {
        $statement = Granada::for_table('widget')
            ->whereNotEqual('name', 'Fred')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` != ?', $statement->query);
        $this->assertSame(['Fred'], $statement->values);
    }

    public function testWhereLike()
    {
        $statement = Granada::for_table('widget')
            ->whereLike('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testWhereNotLike()
    {
        $statement = Granada::for_table('widget')
            ->whereNotLike('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` NOT LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testWhereIn()
    {
        $statement = Granada::for_table('widget')
            ->whereIn('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testWhereNotIn()
    {
        $statement = Granada::for_table('widget')
            ->whereNotIn('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` NOT IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testWhereAnyIsSingleCol()
    {
        $statement = Granada::for_table('widget')
            ->whereAnyIs([
                ['name' => 'Joe'],
                ['name' => 'Fred'],
            ])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE (( `name` = ? ) OR ( `name` = ? ))', $statement->query);
        $this->assertSame(['Joe', 'Fred'], $statement->values);
    }

    public function testOrderByDesc()
    {
        $statement = Granada::for_table('widget')
            ->orderByDesc('name')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `name` DESC LIMIT 1', $statement->query);
    }

    public function testOrderByAsc()
    {
        $statement = Granada::for_table('widget')
            ->orderByAsc('name')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY `name` ASC LIMIT 1', $statement->query);
    }

    public function testOrderByExpression()
    {
        $statement = Granada::for_table('widget')
            ->orderByExpr('SOUNDEX(`name`)')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` ORDER BY SOUNDEX(`name`) LIMIT 1', $statement->query);
    }

    public function testOrderByClear()
    {
        $statement = Granada::for_table('widget')
            ->orderByAsc('name')
            ->orderByDesc('age')
            ->orderByClear()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LIMIT 1', $statement->query);
    }

    public function testGroupBy()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name`', $statement->query);
    }

    public function testGroupByExpression()
    {
        $statement = Granada::for_table('widget')
            ->groupByExpr("FROM_UNIXTIME(`time`, '%Y-%m')")
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY FROM_UNIXTIME(`time`, \'%Y-%m\')', $statement->query);
    }

    public function testClearHaving()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->having('name', 'Fred')
            ->clearHaving()
            ->having('name', 'Joe')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` = ? LIMIT 1', $statement->query);
        $this->assertSame(['Joe'], $statement->values);
    }

    public function testHavingLike()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingLike('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testHavingNotLike()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingNotLike('name', '%Fred%')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT LIKE ? LIMIT 1', $statement->query);
        $this->assertSame(['%Fred%'], $statement->values);
    }

    public function testHavingIn()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingIn('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testHavingNotIn()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingNotIn('name', ['Fred', 'Joe'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT IN (?, ?)', $statement->query);
        $this->assertSame(['Fred', 'Joe'], $statement->values);
    }

    public function testHavingLessThan()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingLt('age', 10)
            ->havingGt('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `age` < ? AND `age` > ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testHavingLessThanOrEqualAndGreaterThanOrEqual()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingLte('age', 10)
            ->havingGte('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `age` <= ? AND `age` >= ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testHavingNull()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingNull('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NULL', $statement->query);
    }

    public function testHavingNotNull()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingNotNull('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NOT NULL', $statement->query);
    }

    public function testRawHaving()
    {
        $statement = Granada::for_table('widget')
            ->groupBy('name')
            ->havingRaw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` GROUP BY `name` HAVING `name` = ? AND (`age` = ? OR `age` = ?)', $statement->query);
        $this->assertSame(['Fred', 5, 10], $statement->values);
    }

    public function testWhereLessThanAndGreaterThan()
    {
        $statement = Granada::for_table('widget')
            ->whereLt('age', 10)
            ->whereGt('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` < ? AND `widget`.`age` > ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testWhereLessThanAndEqualAndGreaterThanAndEqual()
    {
        $statement = Granada::for_table('widget')
            ->whereLte('age', 10)
            ->whereGte('age', 5)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`age` <= ? AND `widget`.`age` >= ?', $statement->query);
        $this->assertSame([10, 5], $statement->values);
    }

    public function testWhereNull()
    {
        $statement = Granada::for_table('widget')
            ->whereNull('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NULL', $statement->query);
    }

    public function testWhereNotNull()
    {
        $statement = Granada::for_table('widget')
            ->whereNotNull('name')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`name` IS NOT NULL', $statement->query);
    }

    public function testRawWhereClause()
    {
        $statement = Granada::for_table('widget')
            ->whereRaw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `name` = ? AND (`age` = ? OR `age` = ?)', $statement->query);
        $this->assertSame(['Fred', 5, 10], $statement->values);
    }

    public function testRawQuery()
    {
        $statement = Granada::for_table('widget')
            ->rawQuery('SELECT `w`.* FROM `widget` w')
            ->to_statement();

        $this->assertSame('SELECT `w`.* FROM `widget` w', $statement->query);
    }

    public function testMainTableAlias()
    {
        $statement = Granada::for_table('widget')
            ->tableAlias('w')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` `w`', $statement->query);
    }

    public function testAliasesInSelectManyResults()
    {
        $statement = Granada::for_table('widget')
            ->selectMany(['widget_name' => 'widget.name'], 'widget_handle')
            ->to_statement();

        $this->assertSame('SELECT `widget`.`name` AS `widget_name`, `widget_handle` FROM `widget`', $statement->query);
    }

    public function testLiteralExpressionInResultColumn()
    {
        $statement = Granada::for_table('widget')
            ->selectExpr('COUNT(*)', 'count')
            ->to_statement();

        $this->assertSame('SELECT COUNT(*) AS `count` FROM `widget`', $statement->query);
    }

    public function testLiteralExpressionInSelectManyResultColumns()
    {
        $statement = Granada::for_table('widget')
            ->selectManyExpr(['count' => 'COUNT(*)'], 'SUM(widget_order)')
            ->to_statement();

        $this->assertSame('SELECT COUNT(*) AS `count`, SUM(widget_order) FROM `widget`', $statement->query);
    }

    public function testInnerJoin()
    {
        $statement = Granada::for_table('widget')
            ->innerJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` INNER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testLeftOuterJoin()
    {
        $statement = Granada::for_table('widget')
            ->leftOuterJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` LEFT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testRightOuterJoin()
    {
        $statement = Granada::for_table('widget')
            ->rightOuterJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` RIGHT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testFullOuterJoin()
    {
        $statement = Granada::for_table('widget')
            ->fullOuterJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` FULL OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`', $statement->query);
    }

    public function testCamelCaseDeleteManyReachesTheWrite()
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        Granada::for_table('widget')
            ->whereEqual('gadget_id', 1)
            ->deleteMany();

        $this->assertNull(Granada::for_table('widget')
            ->find_one(1));
        $this->assertSame('Widget3', Granada::for_table('widget')
            ->find_one(3)
            ->name);
    }
}
