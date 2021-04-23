<?php

use Granada\ORM;

class ORMTest extends PHPUnit_Framework_TestCase {

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

    public function testStaticAtrributes() {
        $this->assertEquals('0', ORM::CONDITION_FRAGMENT);
        $this->assertEquals('1', ORM::CONDITION_VALUES);
    }

    public function testForTable() {
        $result = ORM::for_table('test');
        $this->assertTrue(is_a($result, 'Granada\ORM'));
    }

    public function testCreate() {
        $model = ORM::for_table('test')->create();
        $this->assertTrue(is_a($model, 'Granada\ORM'));
        $this->assertTrue($model->is_new());
    }

    public function testIsNew() {
        $model = ORM::for_table('test')->create();
        $this->assertTrue($model->is_new());

        $model = ORM::for_table('test')->create(array('test' => 'test'));
        $this->assertTrue($model->is_new());
    }

    public function testIsDirty() {
        $model = ORM::for_table('test')->create();
        $this->assertFalse($model->is_dirty('test'));

        $model = ORM::for_table('test')->create(array('test' => 'test'));
        $this->assertTrue($model->is_dirty('test'));
    }

    public function testArrayAccess() {
        $value = 'test';
        $model = ORM::for_table('test')->create();
        $model['test'] = $value;
        $this->assertTrue(isset($model['test']));
        $this->assertEquals($model['test'], $value);
        unset($model['test']);
        $this->assertFalse(isset($model['test']));
    }

    public function testFindResultSet() {
        $result_set = ORM::for_table('test')->find_result_set();
        $this->assertTrue(is_a($result_set, 'Granada\ResultSet'));
        $this->assertSame(count($result_set), 5);
    }

    public function testFindResultSetByDefault() {
        ORM::configure('return_result_sets', true);

        $result_set = ORM::for_table('test')->find_many();
        $this->assertTrue(is_a($result_set, 'Granada\ResultSet'));
        $this->assertSame(count($result_set), 5);

        ORM::configure('return_result_sets', false);

        $result_set = ORM::for_table('test')->find_many();
        $this->assertSame(count($result_set), 5);
    }

    public function testGetLastPdoStatement() {
        ORM::for_table('widget')->where('name', 'Fred')->find_one();
        $statement = ORM::get_last_statement();
        $this->assertTrue(is_a($statement, 'MockPDOStatement'));
    }

    public function testSaveInsideLoop() {
        $cars = ORM::for_table('car')->find_many();
        foreach ($cars as $car) {
            $car->name = 'ABC';
            $car->save();
            $expected = "UPDATE `car` SET `name` = 'ABC' WHERE `id` = '" . $car->id . "'";
            $this->assertEquals($expected, ORM::get_last_query());
        }
    }

}
