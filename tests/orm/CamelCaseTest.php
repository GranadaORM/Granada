<?php

use Granada\ORM;

class CamelCaseTest extends PHPUnit_Framework_TestCase {

    public function setUp(): void {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new MockPDO('sqlite::memory:');
        ORM::setDb($db);
    }

    public function tearDown(): void {
        ORM::resetConfig();
        ORM::resetDb();
    }

    public function testFindMany() {
        ORM::forTable('widget')->findMany();
        $expected = "SELECT * FROM `widget`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testFindOne() {
        ORM::forTable('widget')->findOne();
        $expected = "SELECT * FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereIdIs() {
        ORM::forTable('widget')->whereIdIs(5)->findOne();
        $expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testSingleWhereClause() {
        ORM::forTable('widget')->where('name', 'Fred')->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testClearWhereClause() {
        ORM::forTable('widget')
				->where('name', 'Fred')
				->clearWhere()
				->where('name', 'Joe')
				->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOptionalOrderClause1() {
        $order_by_age = true;
        $order_by_name = true;
        ORM::forTable('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->orderByAsc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->orderByAsc('name');
            })
            ->findOne();
        $expected = "SELECT * FROM `widget` ORDER BY `age` ASC, `name` ASC LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotEqual() {
        ORM::forTable('widget')->whereNotEqual('name', 'Fred')->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` != 'Fred'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereLike() {
        ORM::forTable('widget')->whereLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotLike() {
        ORM::forTable('widget')->whereNotLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereIn() {
        ORM::forTable('widget')->whereIn('name', array('Fred', 'Joe'))->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotIn() {
        ORM::forTable('widget')->whereNotIn('name', array('Fred', 'Joe'))->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereAnyIsSingleCol() {
        ORM::forTable('widget')->whereAnyIs(array(
            array('name' => 'Joe'),
            array('name' => 'Fred')))->findMany();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' ))";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByDesc() {
        ORM::forTable('widget')->orderByDesc('name')->findOne();
        $expected = "SELECT * FROM `widget` ORDER BY `name` DESC LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByAsc() {
        ORM::forTable('widget')->orderByAsc('name')->findOne();
        $expected = "SELECT * FROM `widget` ORDER BY `name` ASC LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByExpression() {
        ORM::forTable('widget')->orderByExpr('SOUNDEX(`name`)')->findOne();
        $expected = "SELECT * FROM `widget` ORDER BY SOUNDEX(`name`) LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testOrderByClear() {
        ORM::forTable('widget')->orderByAsc('name')->orderByDesc('age')->orderByClear()->findOne();
        $expected = "SELECT * FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testGroupBy() {
        ORM::forTable('widget')->groupBy('name')->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testGroupByExpression() {
        ORM::forTable('widget')->groupByExpr("FROM_UNIXTIME(`time`, '%Y-%m')")->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY FROM_UNIXTIME(`time`, '%Y-%m')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testClearHaving() {
        ORM::forTable('widget')->groupBy('name')
				->having('name', 'Fred')
				->clearHaving()
				->having('name', 'Joe')
				->findOne();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingLike() {
        ORM::forTable('widget')->groupBy('name')->havingLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNotLike() {
        ORM::forTable('widget')->groupBy('name')->havingNotLike('name', '%Fred%')->findOne();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingIn() {
        ORM::forTable('widget')->groupBy('name')->havingIn('name', array('Fred', 'Joe'))->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNotIn() {
        ORM::forTable('widget')->groupBy('name')->havingNotIn('name', array('Fred', 'Joe'))->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingLessThan() {
        ORM::forTable('widget')->groupBy('name')->havingLt('age', 10)->havingGt('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingLessThanOrEqualAndGreaterThanOrEqual() {
        ORM::forTable('widget')->groupBy('name')->havingLte('age', 10)->havingGte('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNull() {
        ORM::forTable('widget')->groupBy('name')->havingNull('name')->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NULL";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testHavingNotNull() {
        ORM::forTable('widget')->groupBy('name')->havingNotNull('name')->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NOT NULL";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRawHaving() {
        ORM::forTable('widget')->groupBy('name')->havingRaw('`name` = ? AND (`age` = ? OR `age` = ?)', array('Fred', 5, 10))->findMany();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereLessThanAndGreaterThan() {
        ORM::forTable('widget')->whereLt('age', 10)->whereGt('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` WHERE `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereLessThanAndEqualAndGreaterThanAndEqual() {
        ORM::forTable('widget')->whereLte('age', 10)->whereGte('age', 5)->findMany();
        $expected = "SELECT * FROM `widget` WHERE `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNull() {
        ORM::forTable('widget')->whereNull('name')->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NULL";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testWhereNotNull() {
        ORM::forTable('widget')->whereNotNull('name')->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NOT NULL";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRawWhereClause() {
        ORM::forTable('widget')->whereRaw('`name` = ? AND (`age` = ? OR `age` = ?)', array('Fred', 5, 10))->findMany();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRawQuery() {
        ORM::forTable('widget')->rawQuery('SELECT `w`.* FROM `widget` w')->findMany();
        $expected = "SELECT `w`.* FROM `widget` w";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testMainTableAlias() {
        ORM::forTable('widget')->tableAlias('w')->findMany();
        $expected = "SELECT * FROM `widget` `w`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testAliasesInSelectManyResults() {
        ORM::forTable('widget')->selectMany(array('widget_name' => 'widget.name'), 'widget_handle')->findMany();
        $expected = "SELECT `widget`.`name` AS `widget_name`, `widget_handle` FROM `widget`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testLiteralExpressionInResultColumn() {
        ORM::forTable('widget')->selectExpr('COUNT(*)', 'count')->findMany();
        $expected = "SELECT COUNT(*) AS `count` FROM `widget`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testLiteralExpressionInSelectManyResultColumns() {
        ORM::forTable('widget')->selectManyExpr(array('count' => 'COUNT(*)'), 'SUM(widget_order)')->findMany();
        $expected = "SELECT COUNT(*) AS `count`, SUM(widget_order) FROM `widget`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testInnerJoin() {
        ORM::forTable('widget')->innerJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
        $expected = "SELECT * FROM `widget` INNER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testLeftOuterJoin() {
        ORM::forTable('widget')->leftOuterJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
        $expected = "SELECT * FROM `widget` LEFT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testRightOuterJoin() {
        ORM::forTable('widget')->rightOuterJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
        $expected = "SELECT * FROM `widget` RIGHT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testFullOuterJoin() {
        ORM::forTable('widget')->fullOuterJoin('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->findMany();
        $expected = "SELECT * FROM `widget` FULL OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testDeleteMany() {
        ORM::forTable('widget')->whereEqual('age', 10)->deleteMany();
        $expected = "DELETE FROM `widget` WHERE `age` = '10'";
        $this->assertEquals($expected, ORM::getLastQuery());
    }

}
