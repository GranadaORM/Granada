<?php

use Granada\ORM;
use Granada\Granada;

class CamelCaseTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new MockPDO('sqlite::memory:');
        ORM::setDb($db);
    }

    protected function tearDown(): void
    {
        ORM::resetConfig();
        ORM::resetDb();
    }

    public function testFindMany()
    {
        Granada::for_table('widget')->findMany();
        $expected = 'SELECT * FROM `widget`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testFindOne()
    {
        Granada::for_table('widget')->findOne();
        $expected = 'SELECT * FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereIdIs()
    {
        Granada::for_table('widget')->whereIdIs(5)->findOne();
        $expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testSingleWhereClause()
    {
        Granada::for_table('widget')->where('name', 'Fred')->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testClearWhereClause()
    {
        Granada::for_table('widget')
            ->where('name', 'Fred')
            ->clearWhere()
            ->where('name', 'Joe')
            ->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOptionalOrderClause1()
    {
        $order_by_age  = true;
        $order_by_name = true;
        Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->orderByAsc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->orderByAsc('name');
            })
            ->findOne();
        $expected = 'SELECT * FROM `widget` ORDER BY `age` ASC, `name` ASC LIMIT 1';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotEqual()
    {
        Granada::for_table('widget')->whereNotEqual('name', 'Fred')->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` != 'Fred'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereLike()
    {
        Granada::for_table('widget')->whereLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotLike()
    {
        Granada::for_table('widget')->whereNotLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereIn()
    {
        Granada::for_table('widget')->whereIn('name', ['Fred', 'Joe'])->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotIn()
    {
        Granada::for_table('widget')->whereNotIn('name', ['Fred', 'Joe'])->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereAnyIsSingleCol()
    {
        Granada::for_table('widget')->whereAnyIs([
            ['name' => 'Joe'],
            ['name' => 'Fred'],
        ])->findMany();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' ))";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByDesc()
    {
        Granada::for_table('widget')->orderByDesc('name')->findOne();
        $expected = 'SELECT * FROM `widget` ORDER BY `name` DESC LIMIT 1';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByAsc()
    {
        Granada::for_table('widget')->orderByAsc('name')->findOne();
        $expected = 'SELECT * FROM `widget` ORDER BY `name` ASC LIMIT 1';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByExpression()
    {
        Granada::for_table('widget')->orderByExpr('SOUNDEX(`name`)')->findOne();
        $expected = 'SELECT * FROM `widget` ORDER BY SOUNDEX(`name`) LIMIT 1';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByClear()
    {
        Granada::for_table('widget')->orderByAsc('name')->orderByDesc('age')->orderByClear()->findOne();
        $expected = 'SELECT * FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testGroupBy()
    {
        Granada::for_table('widget')->groupBy('name')->findMany();
        $expected = 'SELECT * FROM `widget` GROUP BY `name`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testGroupByExpression()
    {
        Granada::for_table('widget')->groupByExpr("FROM_UNIXTIME(`time`, '%Y-%m')")->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY FROM_UNIXTIME(`time`, '%Y-%m')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testClearHaving()
    {
        Granada::for_table('widget')->groupBy('name')
            ->having('name', 'Fred')
            ->clearHaving()
            ->having('name', 'Joe')
            ->findOne();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingLike()
    {
        Granada::for_table('widget')->groupBy('name')->havingLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNotLike()
    {
        Granada::for_table('widget')->groupBy('name')->havingNotLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingIn()
    {
        Granada::for_table('widget')->groupBy('name')->havingIn('name', ['Fred', 'Joe'])->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNotIn()
    {
        Granada::for_table('widget')->groupBy('name')->havingNotIn('name', ['Fred', 'Joe'])->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingLessThan()
    {
        Granada::for_table('widget')->groupBy('name')->havingLt('age', 10)->havingGt('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingLessThanOrEqualAndGreaterThanOrEqual()
    {
        Granada::for_table('widget')->groupBy('name')->havingLte('age', 10)->havingGte('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNull()
    {
        Granada::for_table('widget')->groupBy('name')->havingNull('name')->findMany();
        $expected = 'SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NULL';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNotNull()
    {
        Granada::for_table('widget')->groupBy('name')->havingNotNull('name')->findMany();
        $expected = 'SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NOT NULL';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRawHaving()
    {
        Granada::for_table('widget')->groupBy('name')->havingRaw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereLessThanAndGreaterThan()
    {
        Granada::for_table('widget')->whereLt('age', 10)->whereGt('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` WHERE `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereLessThanAndEqualAndGreaterThanAndEqual()
    {
        Granada::for_table('widget')->whereLte('age', 10)->whereGte('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` WHERE `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNull()
    {
        Granada::for_table('widget')->whereNull('name')->findMany();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NULL';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotNull()
    {
        Granada::for_table('widget')->whereNotNull('name')->findMany();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NOT NULL';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRawWhereClause()
    {
        Granada::for_table('widget')->whereRaw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRawQuery()
    {
        Granada::for_table('widget')->rawQuery('SELECT `w`.* FROM `widget` w')->findMany();
        $expected = 'SELECT `w`.* FROM `widget` w';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testMainTableAlias()
    {
        Granada::for_table('widget')->tableAlias('w')->findMany();
        $expected = 'SELECT * FROM `widget` `w`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testAliasesInSelectManyResults()
    {
        Granada::for_table('widget')->selectMany(['widget_name' => 'widget.name'], 'widget_handle')->findMany();
        $expected = 'SELECT `widget`.`name` AS `widget_name`, `widget_handle` FROM `widget`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testLiteralExpressionInResultColumn()
    {
        Granada::for_table('widget')->selectExpr('COUNT(*)', 'count')->findMany();
        $expected = 'SELECT COUNT(*) AS `count` FROM `widget`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testLiteralExpressionInSelectManyResultColumns()
    {
        Granada::for_table('widget')->selectManyExpr(['count' => 'COUNT(*)'], 'SUM(widget_order)')->findMany();
        $expected = 'SELECT COUNT(*) AS `count`, SUM(widget_order) FROM `widget`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testInnerJoin()
    {
        Granada::for_table('widget')->innerJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->findMany();
        $expected = 'SELECT * FROM `widget` INNER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testLeftOuterJoin()
    {
        Granada::for_table('widget')->leftOuterJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->findMany();
        $expected = 'SELECT * FROM `widget` LEFT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRightOuterJoin()
    {
        Granada::for_table('widget')->rightOuterJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->findMany();
        $expected = 'SELECT * FROM `widget` RIGHT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testFullOuterJoin()
    {
        Granada::for_table('widget')->fullOuterJoin('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->findMany();
        $expected = 'SELECT * FROM `widget` FULL OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testDeleteMany()
    {
        Granada::for_table('widget')->whereEqual('age', 10)->deleteMany();
        $expected = "DELETE FROM `widget` WHERE `age` = '10'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }
}
