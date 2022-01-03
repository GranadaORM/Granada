<?php

use Granada\ORM;

class QueryBuilderMssqlTest extends PHPUnit_Framework_TestCase {

    /**
     * @before
     */
    protected function beforeTest() {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new MockMsSqlPDO('sqlite::memory:');
        ORM::set_db($db);
    }

    /**
     * @after
     */
    protected function afterTest() {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testFindOne() {
        ORM::for_table('widget')->find_one();
        $expected = 'SELECT TOP 1 * FROM "widget"';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLimit() {
        ORM::for_table('widget')->limit(5)->find_many();
        $expected = 'SELECT TOP 5 * FROM "widget"';
        $this->assertSame($expected, ORM::for_table('widget')->limit(5)->get_select_query());
        $this->assertEquals($expected, ORM::get_last_query());
    }

}

