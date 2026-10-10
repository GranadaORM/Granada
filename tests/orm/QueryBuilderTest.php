<?php

use Granada\ORM;
use Granada\Granada;

class QueryBuilderTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new MockPDO('sqlite::memory:');
        ORM::set_db($db);
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testFindMany()
    {
        Granada::for_table('widget')->find_many();
        $expected = 'SELECT * FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFindOne()
    {
        Granada::for_table('widget')->find_one();
        $expected = 'SELECT * FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFindOneWithPrimaryKeyFilter()
    {
        Granada::for_table('widget')->find_one(5);
        $expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereIdIs()
    {
        Granada::for_table('widget')->where_id_is(5)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereClause()
    {
        Granada::for_table('widget')->where('name', 'Fred')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testClearWhereClause()
    {
        Granada::for_table('widget')
            ->where('name', 'Fred')
            ->clear_where()
            ->where('name', 'Joe')
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testClearWhereClauseDiffField()
    {
        Granada::for_table('widget')
            ->where('name', 'Fred')
            ->clear_where()
            ->where('age', 10)
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereClauseEqEmpty()
    {
        Granada::for_table('widget')->where('name', '')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = '' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereClauseEqNULL()
    {
        Granada::for_table('widget')->where('name', null)->find_one();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NULL LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereEqualsClauseEqNULL()
    {
        Granada::for_table('widget')->where_equal('name', null)->find_one();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NULL LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSingleWhereNotEqNULL()
    {
        Granada::for_table('widget')->where_not_equal('name', null)->find_one();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NOT NULL LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleWhereNULLClauses()
    {
        Granada::for_table('widget')->where('name', null)->where('age', null)->find_one();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NULL AND `age` IS NULL LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleWhereClausesOneNULL()
    {
        Granada::for_table('widget')->where('name', 'Fred')->where('age', null)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` IS NULL LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleWhereClauses()
    {
        Granada::for_table('widget')->where('name', 'Fred')->where('age', 10)->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRemoveWhereClause()
    {
        Granada::for_table('widget')->where('name', 'Fred')->where('age', 10)->remove_where('name')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClauses()
    {
        Granada::for_table('widget')
            ->onlyif(true, function ($q) {
                return $q->where('name', 'Fred');
            })
            ->where('age', 10)
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClauses2()
    {
        Granada::for_table('widget')
            ->onlyif(false, function ($q) {
                return $q->where('name', 'Fred');
            })
            ->where('age', 10)
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClausesExtraParams()
    {
        $where_name = 'Fred';
        Granada::for_table('widget')
            ->onlyif(true, function ($q) use ($where_name) {
                return $q->where('name', $where_name);
            })
            ->where('age', 10)
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClausesVariable1()
    {
        $min_age = 10;
        Granada::for_table('widget')
            ->onlyif($min_age > 0, function ($q) use ($min_age) {
                return $q->where_gte('age', $min_age);
            })
            ->find_one();
        $expected = "SELECT * FROM `widget` WHERE `age` >= '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalWhereClausesVariable2()
    {
        $min_age = 0;
        Granada::for_table('widget')
            ->onlyif($min_age > 0, function ($q) use ($min_age) {
                return $q->where_gte('age', $min_age);
            })
            ->find_one();
        $expected = 'SELECT * FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalOrderClause1()
    {
        $order_by_age  = true;
        $order_by_name = true;
        Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->find_one();
        $expected = 'SELECT * FROM `widget` ORDER BY `age` ASC, `name` ASC LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalOrderClause2()
    {
        $order_by_age  = true;
        $order_by_name = false;
        Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->find_one();
        $expected = 'SELECT * FROM `widget` ORDER BY `age` ASC LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOptionalOrderClause3()
    {
        $order_by_age  = false;
        $order_by_name = false;
        Granada::for_table('widget')
            ->onlyif($order_by_age, function ($q) {
                return $q->order_by_asc('age');
            })
            ->onlyif($order_by_name, function ($q) {
                return $q->order_by_asc('name');
            })
            ->find_one();
        $expected = 'SELECT * FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotEqual()
    {
        Granada::for_table('widget')->where_not_equal('name', 'Fred')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` != 'Fred'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLike()
    {
        Granada::for_table('widget')->where_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotLike()
    {
        Granada::for_table('widget')->where_not_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereIn()
    {
        Granada::for_table('widget')->where_in('name', ['Fred', 'Joe'])->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereInNoItems()
    {
        Granada::for_table('widget')->where_in('name', [])->find_many();
        $expected = 'SELECT * FROM `widget` WHERE 0';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereInNULL()
    {
        Granada::for_table('widget')->where_in('custid', null)->find_many();
        $expected = 'SELECT * FROM `widget` WHERE 0';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotIn()
    {
        Granada::for_table('widget')->where_not_in('name', ['Fred', 'Joe'])->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotInOrNull()
    {
        Granada::for_table('widget')->where_not_in_or_null('name', ['Fred', 'Joe'])->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `name` NOT IN ('Fred', 'Joe') OR `name` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());

        Granada::for_table('widget')->where_not_in_or_null('name', [])->find_many();
        // If empty, no where
        $expected = 'SELECT * FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotInNoItems()
    {
        Granada::for_table('widget')->where_not_in('name', [])->find_many();
        $expected = 'SELECT * FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLtOrNull()
    {
        Granada::for_table('widget')->where_lt_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` < '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLteOrNull()
    {
        Granada::for_table('widget')->where_lte_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` <= '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereGtOrNull()
    {
        Granada::for_table('widget')->where_gt_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` > '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereGteOrNull()
    {
        Granada::for_table('widget')->where_gte_or_null('age', '20')->find_many();
        $expected = "SELECT * FROM `widget` WHERE ( `age` >= '20' OR `age` IS NULL )";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsSingleCol()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe'],
            ['name' => 'Fred'],
        ])->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIs()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => 10],
            ['name' => 'Fred', 'age' => 20],
        ])->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` = '10' ) OR ( `name` = 'Fred' AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsAssymetricComparisons()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe'],
            ['name' => 'Fred', 'age' => 20],
        ])->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsOverrideOneColumn()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => 10],
            ['name' => 'Fred', 'age' => 20],
        ], ['age' => '>'])->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsOverrideAllOperators()
    {
        Granada::for_table('widget')->where_any_is([
            ['score' => '5', 'age' => 10],
            ['score' => '15', 'age' => 20],
        ], '>')->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `score` > '5' AND `age` > '10' ) OR ( `score` > '15' AND `age` > '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsNULLs()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => null],
            ['name' => null, 'age' => 20],
        ])->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` IS NULL ) OR ( `name` IS NULL AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsNOTNULLs()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => null],
            ['name' => null, 'age' => 20],
        ], '!=')->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` != 'Joe' AND `age` IS NOT NULL ) OR ( `name` IS NOT NULL AND `age` != '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsIns()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => [18, 19]],
            ['name' => ['Bob', 'Jack'], 'age' => 20],
        ])->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` IN ('18', '19') ) OR ( `name` IN ('Bob', 'Jack') AND `age` = '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsNOTIns()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => [18, 19]],
            ['name' => ['Bob', 'Jack'], 'age' => 20],
        ], '!=')->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` != 'Joe' AND `age` NOT IN ('18', '19') ) OR ( `name` NOT IN ('Bob', 'Jack') AND `age` != '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereAnyIsInsMixedComparator()
    {
        Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => [18, 19]],
            ['name' => ['Bob', 'Jack'], 'age' => 20],
        ], ['age' => '!='])->find_many();
        $expected = "SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` NOT IN ('18', '19') ) OR ( `name` IN ('Bob', 'Jack') AND `age` != '20' ))";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLimit()
    {
        Granada::for_table('widget')->limit(5)->find_many();
        $expected = 'SELECT * FROM `widget` LIMIT 5';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLimitAndOffset()
    {
        Granada::for_table('widget')->limit(5)->offset(5)->find_many();
        $expected = 'SELECT * FROM `widget` LIMIT 5 OFFSET 5';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testClearLimitAndOffset()
    {
        Granada::for_table('widget')
            ->limit(5)->offset(5)
            ->limit(null)->offset(null)
            ->find_many();
        $expected = 'SELECT * FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByDesc()
    {
        Granada::for_table('widget')->order_by_desc('name')->find_one();
        $expected = 'SELECT * FROM `widget` ORDER BY `name` DESC LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByAsc()
    {
        Granada::for_table('widget')->order_by_asc('name')->find_one();
        $expected = 'SELECT * FROM `widget` ORDER BY `name` ASC LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByExpression()
    {
        Granada::for_table('widget')->order_by_expr('SOUNDEX(`name`)')->find_one();
        $expected = 'SELECT * FROM `widget` ORDER BY SOUNDEX(`name`) LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleOrderBy()
    {
        Granada::for_table('widget')->order_by_asc('name')->order_by_desc('age')->find_one();
        $expected = 'SELECT * FROM `widget` ORDER BY `name` ASC, `age` DESC LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByClear()
    {
        Granada::for_table('widget')->order_by_asc('name')->order_by_desc('age')->order_by_clear()->find_one();
        $expected = 'SELECT * FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testOrderByClearAddMore()
    {
        Granada::for_table('widget')->order_by_asc('name')->order_by_desc('age')->order_by_clear()->order_by_asc('id')->find_one();
        $expected = 'SELECT * FROM `widget` ORDER BY `id` ASC LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testGroupBy()
    {
        Granada::for_table('widget')->group_by('name')->find_many();
        $expected = 'SELECT * FROM `widget` GROUP BY `name`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleGroupBy()
    {
        Granada::for_table('widget')->group_by('name')->group_by('age')->find_many();
        $expected = 'SELECT * FROM `widget` GROUP BY `name`, `age`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testGroupByExpression()
    {
        Granada::for_table('widget')->group_by_expr("FROM_UNIXTIME(`time`, '%Y-%m')")->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY FROM_UNIXTIME(`time`, '%Y-%m')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHaving()
    {
        Granada::for_table('widget')->group_by('name')->having('name', 'Fred')->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testClearHaving()
    {
        Granada::for_table('widget')->group_by('name')
            ->having('name', 'Fred')
            ->clear_having()
            ->having('name', 'Joe')
            ->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Joe' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleHaving()
    {
        Granada::for_table('widget')->group_by('name')->having('name', 'Fred')->having('age', 10)->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' AND `age` = '10' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotEqual()
    {
        Granada::for_table('widget')->group_by('name')->having_not_equal('name', 'Fred')->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` != 'Fred'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingIdIs()
    {
        Granada::for_table('widget')->group_by('name')->having_id_is(5)->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingLike()
    {
        Granada::for_table('widget')->group_by('name')->having_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotLike()
    {
        Granada::for_table('widget')->group_by('name')->having_not_like('name', '%Fred%')->find_one();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT LIKE '%Fred%' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingIn()
    {
        Granada::for_table('widget')->group_by('name')->having_in('name', ['Fred', 'Joe'])->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotIn()
    {
        Granada::for_table('widget')->group_by('name')->having_not_in('name', ['Fred', 'Joe'])->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` NOT IN ('Fred', 'Joe')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingLessThan()
    {
        Granada::for_table('widget')->group_by('name')->having_lt('age', 10)->having_gt('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingLessThanOrEqualAndGreaterThanOrEqual()
    {
        Granada::for_table('widget')->group_by('name')->having_lte('age', 10)->having_gte('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNull()
    {
        Granada::for_table('widget')->group_by('name')->having_null('name')->find_many();
        $expected = 'SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NULL';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHavingNotNull()
    {
        Granada::for_table('widget')->group_by('name')->having_not_null('name')->find_many();
        $expected = 'SELECT * FROM `widget` GROUP BY `name` HAVING `name` IS NOT NULL';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawHaving()
    {
        Granada::for_table('widget')->group_by('name')->having_raw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])->find_many();
        $expected = "SELECT * FROM `widget` GROUP BY `name` HAVING `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testComplexQuery()
    {
        Granada::for_table('widget')->where('name', 'Fred')->limit(5)->offset(5)->order_by_asc('name')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' ORDER BY `name` ASC LIMIT 5 OFFSET 5";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLessThanAndGreaterThan()
    {
        Granada::for_table('widget')->where_lt('age', 10)->where_gt('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` WHERE `age` < '10' AND `age` > '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereLessThanAndEqualAndGreaterThanAndEqual()
    {
        Granada::for_table('widget')->where_lte('age', 10)->where_gte('age', 5)->find_many();
        $expected = "SELECT * FROM `widget` WHERE `age` <= '10' AND `age` >= '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNull()
    {
        Granada::for_table('widget')->where_null('name')->find_many();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NULL';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testWhereNotNull()
    {
        Granada::for_table('widget')->where_not_null('name')->find_many();
        $expected = 'SELECT * FROM `widget` WHERE `name` IS NOT NULL';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClause()
    {
        Granada::for_table('widget')->where_raw('`name` = ? AND (`age` = ? OR `age` = ?)', ['Fred', 5, 10])->find_many();
        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' AND (`age` = '5' OR `age` = '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClauseWithPercentSign()
    {
        Granada::for_table('widget')->where_raw('STRFTIME("%Y", "now") = ?', [2012])->find_many();
        $expected = "SELECT * FROM `widget` WHERE STRFTIME(\"%Y\", \"now\") = '2012'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClauseWithNoParameters()
    {
        Granada::for_table('widget')->where_raw('`name` = "Fred"')->find_many();
        $expected = 'SELECT * FROM `widget` WHERE `name` = "Fred"';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawWhereClauseInMethodChain()
    {
        Granada::for_table('widget')->where('age', 18)->where_raw('(`name` = ? OR `name` = ?)', ['Fred', 'Bob'])->where('size', 'large')->find_many();
        $expected = "SELECT * FROM `widget` WHERE `age` = '18' AND (`name` = 'Fred' OR `name` = 'Bob') AND `size` = 'large'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawQuery()
    {
        Granada::for_table('widget')->raw_query('SELECT `w`.* FROM `widget` w')->find_many();
        $expected = 'SELECT `w`.* FROM `widget` w';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRawQueryWithParameters()
    {
        Granada::for_table('widget')->raw_query('SELECT `w`.* FROM `widget` w WHERE `name` = ? AND `age` = ?', ['Fred', 5])->find_many();
        $expected = "SELECT `w`.* FROM `widget` w WHERE `name` = 'Fred' AND `age` = '5'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSelectAsteriskColumn()
    {
        Granada::for_table('widget')->select('name')->find_many();
        $expected = 'SELECT `name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());

        Granada::for_table('widget')->select('name')->select('*')->find_many();
        $expected = 'SELECT *, `name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());

        Granada::for_table('widget')->select('*')->find_many();
        $expected = 'SELECT * FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSelectAsteriskColumnTwice()
    {
        Granada::for_table('widget')->select('name')->find_many();
        $expected = 'SELECT `name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());

        Granada::for_table('widget')->select('name')->select('*')->select('*')->find_many();
        $expected = 'SELECT *, `name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());

        Granada::for_table('widget')->select('*')->select('*')->find_many();
        $expected = 'SELECT * FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSimpleResultColumn()
    {
        Granada::for_table('widget')->select('name')->find_many();
        $expected = 'SELECT `name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleSimpleResultColumns()
    {
        Granada::for_table('widget')->select('name')->select('age')->find_many();
        $expected = 'SELECT `name`, `age` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSpecifyTableNameAndColumnInResultColumns()
    {
        Granada::for_table('widget')->select('widget.name')->find_many();
        $expected = 'SELECT `widget`.`name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMainTableAlias()
    {
        Granada::for_table('widget')->table_alias('w')->find_many();
        $expected = 'SELECT * FROM `widget` `w`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testAliasesInResultColumns()
    {
        Granada::for_table('widget')->select('widget.name', 'widget_name')->find_many();
        $expected = 'SELECT `widget`.`name` AS `widget_name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testAliasesInSelectManyResults()
    {
        Granada::for_table('widget')->select_many(['widget_name' => 'widget.name'], 'widget_handle')->find_many();
        $expected = 'SELECT `widget`.`name` AS `widget_name`, `widget_handle` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSelectManyAsList()
    {
        Granada::for_table('widget')->select_many(['name', 'price'])->find_many();
        $expected = 'SELECT `name`, `price` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLiteralExpressionInResultColumn()
    {
        Granada::for_table('widget')->select_expr('COUNT(*)', 'count')->find_many();
        $expected = 'SELECT COUNT(*) AS `count` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLiteralExpressionInSelectManyResultColumns()
    {
        Granada::for_table('widget')->select_many_expr(['count' => 'COUNT(*)'], 'SUM(widget_order)')->find_many();
        $expected = 'SELECT COUNT(*) AS `count`, SUM(widget_order) FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSimpleJoin()
    {
        Granada::for_table('widget')->join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->find_many();
        $expected = 'SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSimpleJoinWithWhereIdIsMethod()
    {
        Granada::for_table('widget')->join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->find_one(5);
        $expected = "SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` WHERE `widget`.`id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInnerJoin()
    {
        Granada::for_table('widget')->inner_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->find_many();
        $expected = 'SELECT * FROM `widget` INNER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLeftOuterJoin()
    {
        Granada::for_table('widget')->left_outer_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->find_many();
        $expected = 'SELECT * FROM `widget` LEFT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testRightOuterJoin()
    {
        Granada::for_table('widget')->right_outer_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->find_many();
        $expected = 'SELECT * FROM `widget` RIGHT OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFullOuterJoin()
    {
        Granada::for_table('widget')->full_outer_join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])->find_many();
        $expected = 'SELECT * FROM `widget` FULL OUTER JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMultipleJoinSources()
    {
        Granada::for_table('widget')
            ->join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->join('widget_nozzle', ['widget_nozzle.widget_id', '=', 'widget.id'])
            ->find_many();
        $expected = 'SELECT * FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` JOIN `widget_nozzle` ON `widget_nozzle`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testJoinWithAliases()
    {
        Granada::for_table('widget')->join('widget_handle', ['wh.widget_id', '=', 'widget.id'], 'wh')->find_many();
        $expected = 'SELECT * FROM `widget` JOIN `widget_handle` `wh` ON `wh`.`widget_id` = `widget`.`id`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testJoinWithAliasesAndWhere()
    {
        Granada::for_table('widget')->table_alias('w')->join('widget_handle', ['wh.widget_id', '=', 'w.id'], 'wh')->where_equal('id', 1)->find_many();
        $expected = "SELECT * FROM `widget` `w` JOIN `widget_handle` `wh` ON `wh`.`widget_id` = `w`.`id` WHERE `w`.`id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testJoinWithStringConstraint()
    {
        Granada::for_table('widget')->join('widget_handle', 'widget_handle.widget_id = widget.id')->find_many();
        $expected = 'SELECT * FROM `widget` JOIN `widget_handle` ON widget_handle.widget_id = widget.id';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSelectWithDistinct()
    {
        Granada::for_table('widget')->distinct()->select('name')->find_many();
        $expected = 'SELECT DISTINCT `name` FROM `widget`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertData()
    {
        $widget       = Granada::for_table('widget')->create();
        $widget->name = 'Fred';
        $widget->age  = 10;
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`) VALUES ('Fred', '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertDataContainingAnExpression()
    {
        $widget       = Granada::for_table('widget')->create();
        $widget->name = 'Fred';
        $widget->age  = 10;
        $widget->set_expr('added', 'NOW()');
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`, `added`) VALUES ('Fred', '10', NOW())";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertDataUsingArrayAccess()
    {
        $widget         = Granada::for_table('widget')->create();
        $widget['name'] = 'Fred';
        $widget['age']  = 10;
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`) VALUES ('Fred', '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertDataWithNull()
    {
        $widget       = Granada::for_table('widget')->create();
        $widget->name = 'Fred';
        $widget->age  = null;
        $widget->save();
        $expected = "INSERT INTO `widget` (`name`, `age`) VALUES ('Fred', NULL)";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateSameData()
    {
        $widget       = Granada::for_table('widget')->find_one(1);
        $widget->name = 'Fred'; // Does not change so does not write in the database
        $widget->age  = 12;
        $widget->save();
        $expected = "UPDATE `widget` SET `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateNoUpdates()
    {
        $widget       = Granada::for_table('widget')->find_one(1);
        $widget->name = 'Fred'; // Does not change so does not write to database
        $widget->age  = 10; // Does not change so does not write to database
        $widget->save();
        $expected = "SELECT * FROM `widget` WHERE `id` = '1' LIMIT 1"; // No update command sent, we only see the select above
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateData()
    {
        $widget       = Granada::for_table('widget')->find_one(1);
        $widget->name = 'Bob';
        $widget->age  = 11;
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '11' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateDataContainingAnExpression()
    {
        $widget       = Granada::for_table('widget')->find_one(1);
        $widget->name = 'Bob';
        $widget->age  = 12;
        $widget->set_expr('added', 'NOW()');
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12', `added` = NOW() WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateMultipleFields()
    {
        $widget = Granada::for_table('widget')->find_one(1);
        $widget->set(['name' => 'Bob', 'age' => 12]);
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateMultipleFieldsContainingAnExpression()
    {
        $widget = Granada::for_table('widget')->find_one(1);
        $widget->set(['name' => 'Bob', 'age' => 12]);
        $widget->set_expr(['added' => 'NOW()', 'lat_long' => "GeomFromText('POINT(1.2347 2.3436)')"]);
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12', `added` = NOW(), `lat_long` = GeomFromText('POINT(1.2347 2.3436)') WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateMultipleFieldsContainingAnExpressionAndOverridePreviouslySetExpression()
    {
        $widget = Granada::for_table('widget')->find_one(1);
        $widget->set(['name' => 'Bob', 'age' => 12]);
        $widget->set_expr(['added' => 'NOW()', 'lat_long' => "GeomFromText('POINT(1.2347 2.3436)')"]);
        $widget->lat_long = 'unknown';
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Bob', `age` = '12', `added` = NOW(), `lat_long` = 'unknown' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateFieldThereAndBack()
    {
        $widget = Granada::for_table('widget')->find_one(1);
        $widget->set(['name' => 'Bob', 'age' => 12]);
        $widget->name = 'Fred';
        $widget->save();
        $expected = "UPDATE `widget` SET `name` = 'Fred', `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testDeleteData()
    {
        $widget = Granada::for_table('widget')->find_one(1);
        $widget->delete();
        $expected = "DELETE FROM `widget` WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testDeleteMany()
    {
        Granada::for_table('widget')->where_equal('age', 10)->delete_many();
        $expected = "DELETE FROM `widget` WHERE `age` = '10'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testDeleteManyJoin()
    {
        Granada::for_table('widget')->join('widget_handle', ['widget_handle.widget_id', '=', 'widget.id'])
            ->where_equal('widget_handle.name', 'test')
            ->delete_many();
        $expected = "DELETE  FROM `widget` JOIN `widget_handle` ON `widget_handle`.`widget_id` = `widget`.`id` WHERE `widget_handle`.`name` = 'test'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testCount()
    {
        Granada::for_table('widget')->count();
        $expected = 'SELECT COUNT(*) AS `count` FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIgnoreSelectAndCount()
    {
        Granada::for_table('widget')->select('test')->count();
        $expected = 'SELECT COUNT(*) AS `count` FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMax()
    {
        Granada::for_table('person')->max('height');
        $expected = 'SELECT MAX(`height`) AS `max` FROM `person` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testMin()
    {
        Granada::for_table('person')->min('height');
        $expected = 'SELECT MIN(`height`) AS `min` FROM `person` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testAvg()
    {
        Granada::for_table('person')->avg('height');
        $expected = 'SELECT AVG(`height`) AS `avg` FROM `person` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSum()
    {
        Granada::for_table('person')->sum('height');
        $expected = 'SELECT SUM(`height`) AS `sum` FROM `person` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    /**
     * Regression tests
     */
    public function testIssue12IncorrectQuotingOfColumnWildcard()
    {
        Granada::for_table('widget')->select('widget.*')->find_one();
        $expected = 'SELECT `widget`.* FROM `widget` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIssue57LogQueryRaisesWarningWhenPercentSymbolSupplied()
    {
        Granada::for_table('widget')->where_raw('username LIKE "ben%"')->find_many();
        $expected = 'SELECT * FROM `widget` WHERE username LIKE "ben%"';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIssue57LogQueryRaisesWarningWhenQuestionMarkSupplied()
    {
        Granada::for_table('widget')->where_raw('comments LIKE "has been released?%"')->find_many();
        $expected = 'SELECT * FROM `widget` WHERE comments LIKE "has been released?%"';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testIssue90UsingSetExprAloneDoesTriggerQueryGeneration()
    {
        $widget = Granada::for_table('widget')->find_one(1);
        $widget->set_expr('added', 'NOW()');
        $widget->save();
        $expected = "UPDATE `widget` SET `added` = NOW() WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testGetSelectQuery()
    {
        $this->assertSame('SELECT * FROM `widget`', Granada::for_table('widget')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `name` != 'Fred'", Granada::for_table('widget')->where_not_equal('name', 'Fred')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `name` IN ('Fred', 'Joe')", Granada::for_table('widget')->where_in('name', ['Fred', 'Joe'])->get_select_query());
        $this->assertSame('SELECT * FROM `widget` WHERE 0', Granada::for_table('widget')->where_in('name', [])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE `name` NOT IN ('Fred', 'Joe')", Granada::for_table('widget')->where_not_in('name', ['Fred', 'Joe'])->get_select_query());
        $this->assertSame('SELECT * FROM `widget`', Granada::for_table('widget')->where_not_in('name', [])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` < '20' OR `age` IS NULL )", Granada::for_table('widget')->where_lt_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` <= '20' OR `age` IS NULL )", Granada::for_table('widget')->where_lte_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` > '20' OR `age` IS NULL )", Granada::for_table('widget')->where_gt_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE ( `age` >= '20' OR `age` IS NULL )", Granada::for_table('widget')->where_gte_or_null('age', '20')->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' ))", Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe'],
            ['name' => 'Fred'],
        ])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` = '10' ) OR ( `name` = 'Fred' AND `age` = '20' ))", Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => 10],
            ['name' => 'Fred', 'age' => 20],
        ])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))", Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe'],
            ['name' => 'Fred', 'age' => 20],
        ])->get_select_query());
        $this->assertSame("SELECT * FROM `widget` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))", Granada::for_table('widget')->where_any_is([
            ['name' => 'Joe', 'age' => 10],
            ['name' => 'Fred', 'age' => 20],
        ], ['age' => '>'])->get_select_query());
        $this->assertSame('SELECT * FROM `widget` WHERE username LIKE "ben%"', Granada::for_table('widget')->where_raw('username LIKE "ben%"')->get_select_query());
    }

    public function testWhereClosureBuildsGroup()
    {
        $query = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where(function ($q): void {
                $q->where('age', 18)->where('role', 'admin');
            });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE `name` = 'Fred' AND ( `age` = '18' AND `role` = 'admin' )",
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
            "SELECT * FROM `widget` WHERE `name` = 'Fred' OR ( `age` = '18' AND `role` = 'admin' )",
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
            "SELECT * FROM `widget` WHERE ( `role` = 'admin' OR ( `age` > '18' AND `age` < '65' ) )",
            $query->get_select_query()
        );
    }

    public function testClosureReturnValueIgnored()
    {
        $query = Granada::for_table('widget')->where(function ($q) {
            return $q->where('age', 18)->where('name', 'Fred');
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `age` = '18' AND `name` = 'Fred' )",
            $query->get_select_query()
        );
    }

    public function testEmptyClosureAddsNoCondition()
    {
        $query = Granada::for_table('widget')
            ->where('name', 'Fred')
            ->where(function ($q): void {});

        $this->assertSame(
            "SELECT * FROM `widget` WHERE `name` = 'Fred'",
            $query->get_select_query()
        );
    }

    public function testTypedClosureReceivesTheQuery()
    {
        $query = Granada::for_table('widget')->where(function (ORM $q): void {
            $q->where('age', 18)->where('role', 'admin');
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `age` = '18' AND `role` = 'admin' )",
            $query->get_select_query()
        );
    }

    public function testClosureOnAWrapperWithoutModelClass()
    {
        $query = Granada::for_table('widget')->where(function ($q): void {
            $q->where('age', 18);
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `age` = '18' )",
            $query->get_select_query()
        );
    }

    public function testMagicWhereSuffixOnAWrapperWithoutModelClass()
    {
        $query = Granada::for_table('user')
            ->where_last_login_gt('2020-01-01')
            ->or_where_role('admin');

        $this->assertSame(
            "SELECT * FROM `user` WHERE ( `user`.`last_login` > '2020-01-01' OR `user`.`role` = 'admin' )",
            $query->get_select_query()
        );
    }

    public function testMagicOrderBySuffixOnAWrapperWithoutModelClass()
    {
        $query = Granada::for_table('part')->order_by_name_desc();

        $this->assertSame(
            'SELECT * FROM `part` ORDER BY `name` DESC',
            $query->get_select_query()
        );
    }

    public function testMagicWithSuffixOnAWrapperWithoutModelClass()
    {
        $query = Granada::for_table('widget')->with_user();

        $this->assertSame(
            'SELECT * FROM `widget`',
            $query->get_select_query()
        );
    }

    public function testFilterOnAWrapperWithoutModelClass()
    {
        $query = Granada::for_table('widget')->filter('by_name', 'Fred');

        $this->assertSame(
            'SELECT * FROM `widget`',
            $query->get_select_query()
        );
    }

    public function testWhereNotWithCompareRendersNot()
    {
        $query = Granada::for_table('widget')->where_not('role', 'banned');

        $this->assertSame(
            "SELECT * FROM `widget` WHERE NOT ( `role` = 'banned' )",
            $query->get_select_query()
        );
    }

    public function testWhereNotWithClosure()
    {
        $query = Granada::for_table('widget')->where_not(function ($q): void {
            $q->where('role', 'banned')->where('active', 0);
        });

        $this->assertSame(
            "SELECT * FROM `widget` WHERE NOT ( `role` = 'banned' AND `active` = '0' )",
            $query->get_select_query()
        );
    }

    public function testWhereNotWithNullCompare()
    {
        $query = Granada::for_table('widget')->where_not('role', null);

        $this->assertSame(
            'SELECT * FROM `widget` WHERE NOT ( `role` IS NULL )',
            $query->get_select_query()
        );
    }

    public function testOrWhereCompareJoinsWithOr()
    {
        $query = Granada::for_table('widget')
            ->where('role', 'admin')
            ->or_where('role', 'owner');

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `role` = 'admin' OR `role` = 'owner' )",
            $query->get_select_query()
        );
    }

    public function testOrWhereNotCompare()
    {
        $query = Granada::for_table('widget')
            ->where('role', 'admin')
            ->or_where_not('role', 'banned');

        $this->assertSame(
            "SELECT * FROM `widget` WHERE ( `role` = 'admin' OR NOT ( `role` = 'banned' ) )",
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
            "SELECT * FROM `widget` WHERE `role` = 'admin' OR NOT ( `active` = '0' )",
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
            "SELECT * FROM `widget` WHERE EXISTS ( SELECT `id` FROM `role` WHERE user_id = widget.id AND `name` = 'admin' )",
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
            "SELECT * FROM `widget` WHERE ( `active` = '1' AND EXISTS ( SELECT * FROM `ban` WHERE ban.user_id = widget.id ) )",
            $query->get_select_query()
        );
    }

    public function testWhereInSubqueryLogsOnlyTheOuterQuery()
    {
        $logged   = count(ORM::get_query_log());
        $subquery = Granada::for_table('other')->select('widget_id')->where('tag', 'x');

        Granada::for_table('widget')->where_in('id', $subquery)->find_many();

        $this->assertSame(
            "SELECT * FROM `widget` WHERE `id` IN (SELECT `widget_id` FROM `other` WHERE `tag` = 'x')",
            ORM::get_last_query()
        );
        $this->assertCount($logged + 1, ORM::get_query_log());
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
            "SELECT * FROM `widget` WHERE ( `age` = '10' )",
            $query->get_select_query()
        );
    }
}
