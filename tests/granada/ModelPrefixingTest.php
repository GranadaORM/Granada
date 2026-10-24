<?php

use Granada\Model;
use Granada\ORM;

class ModelPrefixingTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_db();

        Model::$auto_prefix_models = null;
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();

        Model::$auto_prefix_models = null;
    }

    public function testStaticPropertyExists()
    {
        $this->assertTrue(property_exists(Model::class, 'auto_prefix_models'));
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

        $statement = Simple::q()->to_statement();

        $this->assertSame('SELECT * FROM `simple`', $statement->query);
    }

    public function testPrefixOnAutoTableName()
    {
        Model::$auto_prefix_models = 'MockPrefix_';

        $statement = Simple::q()->to_statement();

        $this->assertSame('SELECT * FROM `mock_prefix_simple`', $statement->query);
    }

    public function testPrefixOnAutoTableNameWithTableSpecified()
    {
        Model::$auto_prefix_models = 'MockPrefix_';

        $statement = TableSpecified::q()->to_statement();

        $this->assertSame('SELECT * FROM `simple`', $statement->query);
    }
}
