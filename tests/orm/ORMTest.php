<?php

use Granada\Granada;
use Granada\ORM;

class ORMTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testForTable()
    {
        $result = Granada::for_table('test');
        $this->assertTrue(is_a($result, 'Granada\Query'));
    }

    public function testCreate()
    {
        $model = ORM::_new_row('test')->create();
        $this->assertTrue(is_a($model, 'Granada\ORM'));
        $this->assertTrue($model->is_new());
    }

    public function testIsNew()
    {
        $model = ORM::_new_row('test')->create();
        $this->assertTrue($model->is_new());

        $model = ORM::_new_row('test')->create(['test' => 'test']);
        $this->assertTrue($model->is_new());
    }

    public function testIsDirty()
    {
        $model = ORM::_new_row('test')->create();
        $this->assertFalse($model->is_dirty('test'));
        $this->assertFalse($model->is_any_dirty());

        $model = ORM::_new_row('test')->create(['test' => 'test']);
        $this->assertTrue($model->is_dirty('test'));
        $this->assertTrue($model->is_any_dirty());
    }

    public function testIsDirtySimilarFloats()
    {
        $model       = ORM::_new_row('test')->create();
        $model->test = 5.2;
        $this->assertTrue($model->is_dirty('test'));
        $model->save();
        $this->assertFalse($model->is_dirty('test'));
        $model->test = 5.2 + 0.00000000000001;
        $this->assertFalse($model->is_dirty('test'));
        $model->save();
    }

    public function testNewRecordSetZeroNullNanIsDirty()
    {
        $model       = ORM::_new_row('test')->create();
        $model->test = 0;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = 0.0;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = null;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = NAN;
        $this->assertTrue($model->is_dirty('test'));
    }

    public function testNewRecordSetExprFloatZeroIsDirty()
    {
        $model = ORM::_new_row('test')->create();
        $model->set_expr('test', 0.0);
        $this->assertTrue($model->is_dirty('test'));
    }

    public function testSavedRecordChangedFloatIsDirty()
    {
        $model       = ORM::_new_row('test')->create();
        $model->test = 5.2;
        $model->save();
        $this->assertFalse($model->is_dirty('test'));
        $model->test = 9.9;
        $this->assertTrue($model->is_dirty('test'));
    }

    public function testIsDirtyIntegerType()
    {
        $model = ORM::_new_row('test')->create([
            'age' => 5,
        ]);
        $model->save();
        $this->assertSame(5, $model->age);
        $model->age = '5';
        $this->assertFalse($model->is_dirty('age'));
    }

    public function testIsDirtyIntegerTypePrefixZero()
    {
        $model = ORM::_new_row('test')->create([
            'age' => 5,
        ]);
        $model->save();
        $this->assertFalse($model->is_dirty('age'));

        $model->age = '05';
        $this->assertFalse($model->is_dirty('age'));
    }

    public function testIsDirtyExactString()
    {
        $model       = ORM::_new_row('test')->create();
        $model->test = '5';
        $this->assertTrue($model->is_dirty('test'));
        $model->save();

        $this->assertSame('5', $model->test);
        $this->assertFalse($model->is_dirty('test'));
        $model->test = '05';
        $this->assertTrue($model->is_dirty('test'));
        $model->save();

        $this->assertSame('05', $model->test);
        $model->save();
    }

    public function testIsDirtyUnset()
    {
        $model       = ORM::_new_row('test')->create();
        $model->test = 5;
        $this->assertTrue($model->is_dirty('test'));
        unset($model->test);
        $this->assertNull($model->test);
        $this->assertFalse($model->is_dirty('test'));
    }

    public function testIsDirtySetNull()
    {
        $model       = ORM::_new_row('test')->create();
        $model->test = 5;
        $this->assertTrue($model->is_dirty('test'));
        $model->save();
        $this->assertFalse($model->is_dirty('test'));
        $model->test = 5;
        $this->assertFalse($model->is_dirty('test'));

        $model->test = null;
        $this->assertTrue($model->is_dirty('test'));
        $model->save();
        $this->assertFalse($model->is_dirty('test'));
        $model->test = null;
        $this->assertFalse($model->is_dirty('test'));
    }

    public function testIsDirtyNullTransitions()
    {
        $model       = ORM::_new_row('test')->create();
        $model->test = 0;
        $model->save();
        $model->test = null;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = 0.0;
        $model->save();
        $model->test = null;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = false;
        $model->save();
        $model->test = null;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = null;
        $model->save();
        $model->test = 0;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = null;
        $model->save();
        $model->test = 0.0;
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = null;
        $model->save();
        $model->test = '';
        $this->assertTrue($model->is_dirty('test'));

        $model       = ORM::_new_row('test')->create();
        $model->test = null;
        $model->save();
        $model->test = false;
        $this->assertTrue($model->is_dirty('test'));
    }

    public function testArrayAccess()
    {
        $value         = 'test';
        $model         = ORM::_new_row('test')->create();
        $model['test'] = $value;
        $this->assertTrue(isset($model['test']));
        $this->assertEquals($model['test'], $value);
        unset($model['test']);
        $this->assertFalse(isset($model['test']));
    }

    public function testFindResultSet()
    {
        $result_set = Granada::for_table('test')->find_result_set();
        $this->assertTrue(is_a($result_set, 'Granada\ResultSet'));
        $this->assertSame(count($result_set), 5);
    }

    public function testFindResultSetByDefault()
    {
        ORM::configure('return_result_sets', true);

        $result_set = Granada::for_table('test')->find_many();
        $this->assertTrue(is_a($result_set, 'Granada\ResultSet'));
        $this->assertSame(count($result_set), 5);

        ORM::configure('return_result_sets', false);

        $result_set = Granada::for_table('test')->find_many();
        $this->assertSame(count($result_set), 5);
    }

    public function testGetLastPdoStatement()
    {
        Granada::for_table('widget')
            ->where('name', 'Fred')
            ->find_one();
        $statement = ORM::get_last_statement();
        $this->assertInstanceOf(PDOStatement::class, $statement);
    }

    public function testSaveInsideLoopWritesEachRow()
    {
        $cars = Granada::for_table('car')->find_many();
        foreach ($cars as $car) {
            $car->name = 'ABC';
            $car->save();
        }

        $names = [];
        foreach (Granada::for_table('car')->find_many() as $car) {
            $names[] = $car->name;
        }

        $this->assertSame(array_fill(0, count($cars), 'ABC'), $names);
    }

    public function testDuplicateFilters()
    {
        $cars_query = Granada::for_table('car')
            ->where('name', 'ABC')
            ->where('name', 'ABC')
            ->get_select_query();

        $this->assertSame("SELECT * FROM `car` WHERE `car`.`name` = 'ABC'", $cars_query);
    }

    public function testDuplicateSelect()
    {
        $cars_query = Granada::for_table('car')
            ->select('id')
            ->select('name')
            ->select('id')
            ->get_select_query();

        $this->assertSame('SELECT `id`, `name` FROM `car`', $cars_query);
    }
}
