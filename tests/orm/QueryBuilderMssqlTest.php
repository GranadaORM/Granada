<?php

use Granada\ORM;
use Granada\Granada;

class QueryBuilderMssqlTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new MockMsSqlPDO('sqlite::memory:');
        ORM::set_db($db);
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testFindOne()
    {
        Granada::for_table('widget')->find_one();
        $expected = 'SELECT TOP 1 * FROM "widget"';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testLimit()
    {
        Granada::for_table('widget')->limit(5)->find_many();
        $expected = 'SELECT TOP 5 * FROM "widget"';
        $this->assertSame($expected, Granada::for_table('widget')->limit(5)->get_select_query());
        $this->assertEquals($expected, ORM::get_last_query());
    }
}
