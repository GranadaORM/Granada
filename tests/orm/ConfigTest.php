<?php

use Granada\ORM;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @before
     */
    protected function beforeTest()
    {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new MockPDO('sqlite::memory:');
        ORM::set_db($db);

        ORM::configure('id_column', 'primary_key');
    }

    /**
     * @after
     */
    protected function afterTest()
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    protected function setUpIdColumnOverrides()
    {
        ORM::configure('id_column_overrides', [
            'widget'        => 'widget_id',
            'widget_handle' => 'widget_handle_id',
        ]);
    }

    protected function tearDownIdColumnOverrides()
    {
        ORM::configure('id_column_overrides', []);
    }

    public function testSettingIdColumn()
    {
        ORM::for_table('widget')->find_one(5);
        $expected = "SELECT * FROM `widget` WHERE `primary_key` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSettingIdColumnOverridesOne()
    {
        $this->setUpIdColumnOverrides();

        ORM::for_table('widget')->find_one(5);
        $expected = "SELECT * FROM `widget` WHERE `widget_id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());

        $this->tearDownIdColumnOverrides();
    }

    public function testSettingIdColumnOverridesTwo()
    {
        $this->setUpIdColumnOverrides();

        ORM::for_table('widget_handle')->find_one(5);
        $expected = "SELECT * FROM `widget_handle` WHERE `widget_handle_id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());

        $this->tearDownIdColumnOverrides();
    }

    public function testSettingIdColumnOverridesThree()
    {
        $this->setUpIdColumnOverrides();

        ORM::for_table('widget_nozzle')->find_one(5);
        $expected = "SELECT * FROM `widget_nozzle` WHERE `primary_key` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());

        $this->tearDownIdColumnOverrides();
    }

    public function testInstanceIdColumnOne()
    {
        $this->setUpIdColumnOverrides();

        ORM::for_table('widget')->use_id_column('new_id')->find_one(5);
        $expected = "SELECT * FROM `widget` WHERE `new_id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());

        $this->tearDownIdColumnOverrides();
    }

    public function testInstanceIdColumnTwo()
    {
        $this->setUpIdColumnOverrides();

        ORM::for_table('widget_handle')->use_id_column('new_id')->find_one(5);
        $expected = "SELECT * FROM `widget_handle` WHERE `new_id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());

        $this->tearDownIdColumnOverrides();
    }

    public function testInstanceIdColumnThree()
    {
        $this->setUpIdColumnOverrides();

        ORM::for_table('widget_nozzle')->use_id_column('new_id')->find_one(5);
        $expected = "SELECT * FROM `widget_nozzle` WHERE `new_id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());

        $this->tearDownIdColumnOverrides();
    }

    public function testGetConfig()
    {
        $this->assertTrue(ORM::get_config('logging'));
        ORM::configure('logging', false);
        $this->assertFalse(ORM::get_config('logging'));
        ORM::configure('logging', true);
    }

    public function testGetConfigArray()
    {
        $expected = [
            'connection_string'           => 'sqlite::memory:',
            'id_column'                   => 'primary_key',
            'id_column_overrides'         => [],
            'error_mode'                  => PDO::ERRMODE_EXCEPTION,
            'username'                    => null,
            'password'                    => null,
            'driver_options'              => null,
            'identifier_quote_character'  => '`',
            'logging'                     => true,
            'logger'                      => null,
            'caching'                     => false,
            'return_result_sets'          => true, // true by default in Granada
            'limit_clause_style'          => 'limit',
            'find_many_primary_id_as_key' => true,
        ];
        $this->assertEquals($expected, ORM::get_config());
    }
}
