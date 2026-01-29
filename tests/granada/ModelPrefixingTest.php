<?php

use Granada\Orm;
use Granada\Model;

class ModelPrefixingTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @before
     */
    protected function beforeTest()
    {
        // Set up the dummy database connection
        ORM::set_db(new MockPDO('sqlite::memory:'));

        // Enable logging
        ORM::configure('logging', true);

        Model::$auto_prefix_models = null;
    }

    /**
     * @after
     */
    protected function afterTest()
    {
        ORM::configure('logging', false);
        ORM::set_db(null);

        Model::$auto_prefix_models = null;
    }

    public function testStaticPropertyExists()
    {
        $this->assertTrue(property_exists(\Granada\Model::class, 'auto_prefix_models'));
        $this->assertSame(null, Model::$auto_prefix_models);
    }

    public function testSettingAndUnsettingStaticPropertyValue()
    {
        $model_prefix = 'My_Model_Prefix_';
        $this->assertSame(null, Model::$auto_prefix_models);
        Model::$auto_prefix_models = $model_prefix;
        $this->assertSame($model_prefix, Model::$auto_prefix_models);
        Model::$auto_prefix_models = null;
        $this->assertSame(null, Model::$auto_prefix_models);
    }

    public function testNoPrefixOnAutoTableName()
    {
        Model::$auto_prefix_models = null;
        Model::factory('Simple')->find_many();
        $expected = 'SELECT * FROM `simple`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testPrefixOnAutoTableName()
    {
        Model::$auto_prefix_models = 'MockPrefix_';
        Model::factory('Simple')->find_many();
        $expected = 'SELECT * FROM `mock_prefix_simple`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testPrefixOnAutoTableNameWithTableSpecified()
    {
        Model::$auto_prefix_models = 'MockPrefix_';
        Model::factory('TableSpecified')->find_many();
        $expected = 'SELECT * FROM `simple`';
        $this->assertEquals($expected, ORM::get_last_query());
    }
}
