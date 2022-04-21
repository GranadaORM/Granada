<?php

use Granada\ORM;

class QueryBuilderTest extends PHPUnit_Framework_TestCase {

    /**
     * @before
     */
    protected function beforeTest() {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new MockPDO('sqlite::memory:');
        ORM::set_db($db);
    }

    /**
     * @after
     */
    protected function afterTest() {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testFindMany() {
        ORM::for_table('widget')->find_many();
        $expected = "SELECT * FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFindOne() {
        ORM::for_table('widget')->find_one();
        $expected = "SELECT * FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFindOneWithPrimaryKeyFilter() {
        ORM::for_table('widget')->find_one(5);
        $expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereIdIs() {
        ORM::for_table('widget')->where_id_is(5)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereClause() {
        ORM::for_table('widget')->where('name', 'Fred')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testClearWhereClause() {
        ORM::for_table('widget')
				->where('name', 'Fred')
				->clear_where()
				->where('name', 'Joe')
				->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testClearWhereClauseDiffField() {
        ORM::for_table('widget')
				->where('name', 'Fred')
				->clear_where()
				->where('age', 10)
				->find_one();
        $expected = "SELECT * FROM `widget` WHERE `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereClauseEqEmpty() {
        ORM::for_table('widget')->where('name', '')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = '' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereClauseEqNULL() {
        ORM::for_table('widget')->where('name', NULL)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NULL LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereEqualsClauseEqNULL() {
        ORM::for_table('widget')->where_equal('name', NULL)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NULL LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereNotEqNULL() {
        ORM::for_table('widget')->where_not_equal('name', NULL)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NOT NULL LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleWhereNULLClauses() {
        ORM::for_table('widget')->where('name', NULL)->where('age', NULL)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NULL AND `age` IS NULL LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleWhereClausesOneNULL() {
        ORM::for_table('widget')->where('name', 'Fred')->where('age', NULL)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` IS NULL LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleWhereClauses() {
        ORM::for_table('widget')->where('name', 'Fred')->where('age', 10)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClauses() {
        ORM::for_table('widget')
            ->onlyif(true, function($q) {
                return $q->where('name', 'Fred');
            })
            ->where('age', 10)
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClauses2() {
        ORM::for_table('widget')
            ->onlyif(false, function($q) {
                return $q->where('name', 'Fred');
            })
            ->where('age', 10)
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClausesExtraParams() {
        $where_name = 'Fred';
        ORM::for_table('widget')
            ->onlyif(true, function($q) use($where_name) {
                return $q->where('name', $where_name);
            })
            ->where('age', 10)
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClausesVariable1() {
        $min_age = 10;
        ORM::for_table('widget')
            ->onlyif($min_age > 0, function ($q) use($min_age) {
                return $q->where_gte('age', $min_age);
            })
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `age` >= '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClausesVariable2() {
        $min_age = 0;
        ORM::for_table('widget')
            ->onlyif($min_age > 0, function ($q) use($min_age) {
                return $q->where_gte('age', $min_age);
            })
            ->find_one();
        $expected = "SELECT * FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalOrderClause1() {
        $order_by_age = true;
        $order_by_name = true;
        ORM::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->find_one();
        $expected = "SELECT * FROM `widget` ORDER BY `age` ASC, `name` ASC LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalOrderClause2() {
        $order_by_age = true;
        $order_by_name = false;
        ORM::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->find_one();
        $expected = "SELECT * FROM `widget` ORDER BY `age` ASC LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalOrderClause3() {
        $order_by_age = false;
        $order_by_name = false;
        ORM::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->find_one();
        $expected = "SELECT * FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotEqual() {
        ORM::for_table('widget')->where_not_equal('name', 'Fred')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` != 'Fred'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLike() {
        ORM::for_table('widget')->where_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotLike() {
        ORM::for_table('widget')->where_not_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereIn() {
        ORM::for_table('widget')->where_in('name', array('Fred', 'Joe'))->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereInNoItems() {
        ORM::for_table('widget')->where_in('name', array())->find_many();
        $expected = "SELECT * FROM `widget` WHERE 0";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereInNULL() {
        $query = ORM::for_table('widget')->where_in('custid', NULL);
        $query->_build_select();
        $expected = array();
        $this->assertEquals($expected, $query->testValues());
    }

    public function testWhereNotIn() {
        ORM::for_table('widget')->where_not_in('name', array('Fred', 'Joe'))->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotInNoItems() {
        ORM::for_table('widget')->where_not_in('name', array())->find_many();
        $expected = "SELECT * FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLtOrNull() {
        ORM::for_table('widget')->where_lt_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` < '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLteOrNull() {
        ORM::for_table('widget')->where_lte_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` <= '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereGtOrNull() {
        ORM::for_table('widget')->where_gt_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` > '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereGteOrNull() {
        ORM::for_table('widget')->where_gte_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` >= '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsSingleCol() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe'),
            array('name' => 'Fred')))->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIs() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => 10),
            array('name' => 'Fred', 'age' => 20)))->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` = '10' ) OR ( `name` = 'Fred' AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsAssymetricComparisons() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe'),
            array('name' => 'Fred', 'age' => 20)))->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsOverrideOneColumn() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => 10),
            array('name' => 'Fred', 'age' => 20)), array('age' => '>'))->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsOverrideAllOperators() {
        ORM::for_table('widget')->where_any_is(array(
            array('score' => '5', 'age' => 10),
            array('score' => '15', 'age' => 20)), '>')->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `score` > '5' AND `age` > '10' ) OR ( `score` > '15' AND `age` > '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsNULLs() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => NULL),
            array('name' => NULL, 'age' => 20)))->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` IS NULL ) OR ( `name` IS NULL AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsNOTNULLs() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => NULL),
            array('name' => NULL, 'age' => 20)), '!=')->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` != 'Joe' AND `age` IS NOT NULL ) OR ( `name` IS NOT NULL AND `age` != '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsIns() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => array(18, 19)),
            array('name' => array('Bob', 'Jack'), 'age' => 20)))->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` IN ('18', '19') ) OR ( `name` IN ('Bob', 'Jack') AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsNOTIns() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => array(18, 19)),
            array('name' => array('Bob', 'Jack'), 'age' => 20)), '!=')->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` != 'Joe' AND `age` NOT IN ('18', '19') ) OR ( `name` NOT IN ('Bob', 'Jack') AND `age` != '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsInsMixedComparator() {
        ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => array(18, 19)),
            array('name' => array('Bob', 'Jack'), 'age' => 20)), array( 'age' => '!='))->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` NOT IN ('18', '19') ) OR ( `name` IN ('Bob', 'Jack') AND `age` != '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLimit() {
        ORM::for_table('widget')->limit(5)->find_many();
        $expected = "SELECT * FROM `widget` LIMIT 5";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLimitAndOffset() {
        ORM::for_table('widget')->limit(5)->offset(5)->find_many();
        $expected = "SELECT * FROM `widget` LIMIT 5 OFFSET 5";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByDesc() {
        ORM::for_table('widget')->order_by_desc('name')->find_one();
        $expected = "SELECT * FROM `widget` ORDER BY `name` DESC LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByAsc() {
        ORM::for_table('widget')->order_by_asc('name')->find_one();
        $expected = "SELECT * FROM `widget` ORDER BY `name` ASC LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByExpression() {
        ORM::for_table('widget')->order_by_expr('SOUNDEX(`name`)')->find_one();
        $expected = "SELECT * FROM `widget` ORDER BY SOUNDEX(`name`) LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleOrderBy() {
        ORM::for_table('widget')->order_by_asc('name')->order_by_desc('age')->find_one();
        $expected = "SELECT * FROM `widget` ORDER BY `name` ASC, `age` DESC LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByClear() {
        ORM::for_table('widget')->order_by_asc('name')->order_by_desc('age')->order_by_clear()->find_one();
        $expected = "SELECT * FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByClearAddMore() {
        ORM::for_table('widget')->order_by_asc('name')->order_by_desc('age')->order_by_clear()->order_by_asc('id')->find_one();
        $expected = "SELECT * FROM `widget` ORDER BY `id` ASC LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testGroupBy() {
        ORM::for_table('widget')->group_by('name')->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleGroupBy() {
        ORM::for_table('widget')->group_by('name')->group_by('age')->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name`, `age`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testGroupByExpression() {
        ORM::for_table('widget')->group_by_expr("FROM_UNIXTIME(`time`, '%Y-%m')")->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY FROM_UNIXTIME(`time`, '%Y-%m')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHaving() {
        ORM::for_table('widget')->group_by('name')->having('name', 'Fred')->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testClearHaving() {
        ORM::for_table('widget')->group_by('name')
				->having('name', 'Fred')
				->clear_having()
				->having('name', 'Joe')
				->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleHaving() {
        ORM::for_table('widget')->group_by('name')->having('name', 'Fred')->having('age', 10)->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotEqual() {
        ORM::for_table('widget')->group_by('name')->having_not_equal('name', 'Fred')->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` != 'Fred'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingLike() {
        ORM::for_table('widget')->group_by('name')->having_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotLike() {
        ORM::for_table('widget')->group_by('name')->having_not_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingIn() {
        ORM::for_table('widget')->group_by('name')->having_in('name', array('Fred', 'Joe'))->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotIn() {
        ORM::for_table('widget')->group_by('name')->having_not_in('name', array('Fred', 'Joe'))->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingLessThan() {
        ORM::for_table('widget')->group_by('name')->having_lt('age', 10)->having_gt('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingLessThanOrEqualAndGreaterThanOrEqual() {
        ORM::for_table('widget')->group_by('name')->having_lte('age', 10)->having_gte('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNull() {
        ORM::for_table('widget')->group_by('name')->having_null('name')->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NULL";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotNull() {
        ORM::for_table('widget')->group_by('name')->having_not_null('name')->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NOT NULL";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawHaving() {
        ORM::for_table('widget')->group_by('name')->having_raw('`name` = ? AND (`age` = ? OR `age` = ?)', array('Fred', 5, 10))->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testComplexQuery() {
        ORM::for_table('widget')->where('name', 'Fred')->limit(5)->offset(5)->order_by_asc('name')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' ORDER BY `name` ASC LIMIT 5 OFFSET 5";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLessThanAndGreaterThan() {
        ORM::for_table('widget')->where_lt('age', 10)->where_gt('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` WHERE `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLessThanAndEqualAndGreaterThanAndEqual() {
        ORM::for_table('widget')->where_lte('age', 10)->where_gte('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` WHERE `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNull() {
        ORM::for_table('widget')->where_null('name')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NULL";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotNull() {
        ORM::for_table('widget')->where_not_null('name')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` IS NOT NULL";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClause() {
        ORM::for_table('widget')->where_raw('`name` = ? AND (`age` = ? OR `age` = ?)', array('Fred', 5, 10))->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClauseWithPercentSign() {
        ORM::for_table('widget')->where_raw('STRFTIME("%Y", "now") = ?', array(2012))->find_many();
        $expected = "SELECT * FROM `widget` WHERE STRFTIME(\"%Y\", \"now\") = '2012'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClauseWithNoParameters() {
        ORM::for_table('widget')->where_raw('`name` = "Fred"')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` = \"Fred\"";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClauseInMethodChain() {
        ORM::for_table('widget')->where('age', 18)->where_raw('(`name` = ? OR `name` = ?)', array('Fred', 'Bob'))->where('size', 'large')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `age` = '18' AND (`name` = 'Fred' OR `name` = 'Bob') AND `size` = 'large'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawQuery() {
        ORM::for_table('widget')->raw_query('SELECT `w`.* FROM `widget` w')->find_many();
        $expected = "SELECT `w`.* FROM `widget` w";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawQueryWithParameters() {
        ORM::for_table('widget')->raw_query('SELECT `w`.* FROM `widget` w WHERE `name` = ? AND `age` = ?', array('Fred', 5))->find_many();
        $expected = "SELECT `w`.* FROM `widget` w WHERE `name` = 'Fred' AND `age` = '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSimpleResultColumn() {
        ORM::for_table('widget')->select('name')->find_many();
        $expected = "SELECT `name` FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleSimpleResultColumns() {
        ORM::for_table('widget')->select('name')->select('age')->find_many();
        $expected = "SELECT `name`, `age` FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSpecifyTableNameAndColumnInResultColumns() {
        ORM::for_table('widget')->select('widget.name')->find_many();
        $expected = "SELECT `widget`.`name` FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMainTableAlias() {
        ORM::for_table('widget')->table_alias('w')->find_many();
        $expected = "SELECT * FROM `widget` `w`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testAliasesInResultColumns() {
        ORM::for_table('widget')->select('widget.name', 'widget_name')->find_many();
        $expected = "SELECT `widget`.`name` AS `widget_name` FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testAliasesInSelectManyResults() {
        ORM::for_table('widget')->select_many(array('widget_name' => 'widget.name'), 'widget_handle')->find_many();
        $expected = "SELECT `widget`.`name` AS `widget_name`, `widget_handle` FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLiteralExpressionInResultColumn() {
        ORM::for_table('widget')->select_expr('COUNT(*)', 'count')->find_many();
        $expected = "SELECT COUNT(*) AS `count` FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLiteralExpressionInSelectManyResultColumns() {
        ORM::for_table('widget')->select_many_expr(array('count' => 'COUNT(*)'), 'SUM(widget_order)')->find_many();
        $expected = "SELECT COUNT(*) AS `count`, SUM(widget_order) FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSimpleJoin() {
        ORM::for_table('widget')->join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->find_many();
        $expected = "SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSimpleJoinWithWhereIdIsMethod() {
        ORM::for_table('widget')->join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->find_one(5);
        $expected = "SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` WHERE `widget`.`id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInnerJoin() {
        ORM::for_table('widget')->inner_join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->find_many();
        $expected = "SELECT * FROM `widget` INNER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLeftOuterJoin() {
        ORM::for_table('widget')->left_outer_join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->find_many();
        $expected = "SELECT * FROM `widget` LEFT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRightOuterJoin() {
        ORM::for_table('widget')->right_outer_join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->find_many();
        $expected = "SELECT * FROM `widget` RIGHT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFullOuterJoin() {
        ORM::for_table('widget')->full_outer_join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))->find_many();
        $expected = "SELECT * FROM `widget` FULL OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleJoinSources() {
        ORM::for_table('widget')
        ->join('widget_handle', array('widget_handle.widget_id', '=', 'widget.id'))
        ->join('widget_nozzle', array('widget_nozzle.widget_id', '=', 'widget.id'))
        ->find_many();
        $expected = "SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` JOIN `widget_nozzle` ON `widget_nozzle`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testJoinWithAliases() {
        ORM::for_table('widget')->join('widget_handle', array('wh.widget_id', '=', 'widget.id'), 'wh')->find_many();
        $expected = "SELECT * FROM `widget` JOIN `widget_handle` `wh` ON `wh`.`widget_id` = `widget`.`id`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testJoinWithAliasesAndWhere() {
        ORM::for_table('widget')->table_alias('w')->join('widget_handle', array('wh.widget_id', '=', 'w.id'), 'wh')->where_equal('id', 1)->find_many();
        $expected = "SELECT * FROM `widget` `w` JOIN `widget_handle` `wh` ON `wh`.`widget_id` = `w`.`id` WHERE `w`.`id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testJoinWithStringConstraint() {
        ORM::for_table('widget')->join('widget_handle', "widget_handle.widget_id = widget.id")->find_many();
        $expected = "SELECT * FROM `widget` JOIN `widget_handle` ON widget_handle.widget_id = widget.id";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSelectWithDistinct() {
        ORM::for_table('widget')->distinct()->select('name')->find_many();
        $expected = "SELECT DISTINCT `name` FROM `widget`";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertData() {
        $widget = ORM::for_table('widget')->create();
        $widget->name = "Fred";
        $widget->age = 10;
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`) VALUES ('Fred', '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertDataContainingAnExpression() {
        $widget = ORM::for_table('widget')->create();
        $widget->name = "Fred";
        $widget->age = 10;
        $widget->set_expr('added', 'NOW()');
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`, `added`) VALUES ('Fred', '10', NOW())";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertDataUsingArrayAccess() {
        $widget = ORM::for_table('widget')->create();
        $widget['name'] = "Fred";
        $widget['age'] = 10;
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`) VALUES ('Fred', '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertDataWithNull() {
        $widget = ORM::for_table('widget')->create();
        $widget->name = "Fred";
        $widget->age = null;
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`) VALUES ('Fred', NULL)";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateSameData() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->name = "Fred"; // Does not change so does not write in the database
        $widget->age = 12;
        $widget->save();
        $expected = "UPDATE `widget` SET `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateNoUpdates() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->name = "Fred"; // Does not change so does not write to database
        $widget->age = 10; // Does not change so does not write to database
        $widget->save();
        $expected = "SELECT * FROM `widget` WHERE `id` = '1' LIMIT 1"; // No update command sent, we only see the select above
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateData() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->name = "Bob";
        $widget->age = 11;
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '11' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateDataContainingAnExpression() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->name = "Bob";
        $widget->age = 12;
        $widget->set_expr('added', 'NOW()');
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12', `added` = NOW() WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateMultipleFields() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->set(array("name" => "Bob", "age" => 12));
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateMultipleFieldsContainingAnExpression() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->set(array("name" => "Bob", "age" => 12));
        $widget->set_expr(array("added" => "NOW()", "lat_long" => "GeomFromText('POINT(1.2347 2.3436)')"));
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12', `added` = NOW(), `lat_long` = GeomFromText('POINT(1.2347 2.3436)') WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateMultipleFieldsContainingAnExpressionAndOverridePreviouslySetExpression() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->set(array("name" => "Bob", "age" => 12));
        $widget->set_expr(array("added" => "NOW()", "lat_long" => "GeomFromText('POINT(1.2347 2.3436)')"));
        $widget->lat_long = 'unknown';
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12', `added` = NOW(), `lat_long` = 'unknown' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateFieldThereAndBack() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->set(array("name" => "Bob", "age" => 12));
        $widget->name = 'Fred';
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Fred', `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testDeleteData() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->delete();
        $expected = "DELETE FROM `widget` WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testDeleteMany() {
        ORM::for_table('widget')->where_equal('age', 10)->delete_many();
        $expected = "DELETE FROM `widget` WHERE `age` = '10'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testCount() {
        ORM::for_table('widget')->count();
        $expected = "SELECT COUNT(*) AS `count` FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIgnoreSelectAndCount() {
    	ORM::for_table('widget')->select('test')->count();
    	$expected = "SELECT COUNT(*) AS `count` FROM `widget` LIMIT 1";
    	$this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMax() {
        ORM::for_table('person')->max('height');
        $expected = "SELECT MAX(`height`) AS `max` FROM `person` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMin() {
        ORM::for_table('person')->min('height');
        $expected = "SELECT MIN(`height`) AS `min` FROM `person` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testAvg() {
        ORM::for_table('person')->avg('height');
        $expected = "SELECT AVG(`height`) AS `avg` FROM `person` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSum() {
        ORM::for_table('person')->sum('height');
        $expected = "SELECT SUM(`height`) AS `sum` FROM `person` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    /**
     * Regression tests
     */
    public function testIssue12IncorrectQuotingOfColumnWildcard() {
        ORM::for_table('widget')->select('widget.*')->find_one();
        $expected = "SELECT `widget`.* FROM `widget` LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIssue57LogQueryRaisesWarningWhenPercentSymbolSupplied() {
        ORM::for_table('widget')->where_raw('username LIKE "ben%"')->find_many();
        $expected = 'SELECT * FROM `widget` WHERE username LIKE "ben%"';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIssue57LogQueryRaisesWarningWhenQuestionMarkSupplied() {
        ORM::for_table('widget')->where_raw('comments LIKE "has been released?%"')->find_many();
        $expected = 'SELECT * FROM `widget` WHERE comments LIKE "has been released?%"';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIssue90UsingSetExprAloneDoesTriggerQueryGeneration() {
        $widget = ORM::for_table('widget')->find_one(1);
        $widget->set_expr('added', 'NOW()');
        $widget->save();
        $expected = "UPDATE `widget` SET `added` = NOW() WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testGetSelectQuery() {
        $this->assertSame("SELECT * FROM `widget`", ORM::for_table('widget')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `name` != 'Fred'", ORM::for_table('widget')->where_not_equal('name', 'Fred')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `name` IN ('Fred', 'Joe')", ORM::for_table('widget')->where_in('name', array('Fred', 'Joe'))->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE 0", ORM::for_table('widget')->where_in('name', array())->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `name` NOT IN ('Fred', 'Joe')", ORM::for_table('widget')->where_not_in('name', array('Fred', 'Joe'))->get_select_query());
        $this->assertSame("SELECT * FROM `widget`", ORM::for_table('widget')->where_not_in('name', array())->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` < '20' OR `age` IS NULL )", ORM::for_table('widget')->where_lt_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` <= '20' OR `age` IS NULL )", ORM::for_table('widget')->where_lte_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` > '20' OR `age` IS NULL )", ORM::for_table('widget')->where_gt_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` >= '20' OR `age` IS NULL )", ORM::for_table('widget')->where_gte_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' ))", ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe'),
            array('name' => 'Fred')))->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` = '10' ) OR ( `name` = 'Fred' AND `age` = '20' ))", ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => 10),
            array('name' => 'Fred', 'age' => 20)))->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))", ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe'),
            array('name' => 'Fred', 'age' => 20)))->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))", ORM::for_table('widget')->where_any_is(array(
            array('name' => 'Joe', 'age' => 10),
            array('name' => 'Fred', 'age' => 20)), array('age' => '>'))->get_select_query());
        $this->assertSame('SELECT * FROM `widget` WHERE username LIKE "ben%"', ORM::for_table('widget')->where_raw('username LIKE "ben%"')->get_select_query());
    }
}
