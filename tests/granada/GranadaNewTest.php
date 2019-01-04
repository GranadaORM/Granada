<?php

use Granada\Orm;
use Granada\Model;

/**
 * Testing eager loading
 *
 * @author Peter Schumacher <peter@schumacher.dk>
 *
 * Modified by Tom van Oorschot <tomvanoorschot@gmail.com>
 * Additions:
 *  - Test will also check for double records on a has_many relation
 */
class GranadaNewTest extends PHPUnit_Framework_TestCase {

    public function setUp() {

        // The tests for eager loading requires a real database.
        // Set up SQLite in memory
        ORM::set_db(new PDO('sqlite::memory:'));

        // Create schemas and populate with data
        ORM::get_db()->exec(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..'.DIRECTORY_SEPARATOR.'models.sql'));

        // Enable logging
        ORM::configure('logging', true);
    }

    public function tearDown() {
        ORM::configure('logging', false);
        ORM::set_db(null);
    }

    public function testGetter(){
        $car = Model::factory('Car')->find_one(1);
        $expected = 'Car1';
        $this->assertEquals($expected, $car->get('name'), 'Get method test');
        $this->assertEquals($expected, $car->name, '__get magic method test');

    	$car = Model::factory('Car')->find_one(1);
    	$expected = null;
        $this->assertEquals($expected, $car->nonExistentProperty, 'NULL returned if no property found');

        $car = Model::factory('Car')->find_one(1);
        $expected = 'test test';
        $car->existingProperty = 'TEST TeSt';
        $this->assertEquals($expected, $car->existingProperty, 'get_ method overload test');

        $car = Model::factory('Car')->find_one(1);
        $expected = 'This property is missing';
        $this->assertEquals($expected, $car->someProperty, 'Missing property fallback test');
    }

    public function testSetterForProperty(){
    	$car = Model::factory('Car')->find_one(1);
    	$car->name = 'Car1';
    	$car->save();
    	$expected = 'test';
        $this->assertEquals($expected, $car->name);
    }

    public function testNewItemNoID(){
    	$car = Model::factory('Car')->create(array(
            'name' => 'New Car',
        ));
        $car->save();
    	$expected = 5;
        $this->assertEquals($expected, $car->id);
    }

    public function testNewItemBlankID(){
    	$car = Model::factory('Car')->create(array(
            'id' => '',
            'name' => 'New Car',
        ));
        $car->save();
    	$expected = 9;
        $this->assertEquals($expected, $car->id);
    }

    public function testSetterForRelationship(){
    	$car = Model::factory('Car')->with('manufactor')->find_one(1);
    	$expected = 'Manufactor1';
        $this->assertEquals($expected, $car->manufactor->name, 'Relationship loaded');

    	$expected = 'test';
        $car->manufactor = 'test';

        $this->assertEquals($expected, $car->relationships['manufactor'], 'Relationship overloaded');
    }

    public function testCallStaticForModel(){
    	$expected  = Model::factory('Car')->with('manufactor')->find_one(1);
		$car       = Car::with('manufactor')->find_one(1);
        $this->assertEquals($expected, $car, 'Call from static and from factory are the same');
    }

    public function testPluckValid(){
        $id = Car::where_id_is(1)->pluck('id');
        $this->assertEquals(1, $id, 'PLuck a column');
    }

    public function testPluckInvalid(){
        $id = Car::where_id_is(10)->pluck('id');
        $this->assertNull($id);
    }

    public function testfindPairs(){
        $pairs = Car::find_pairs('id', 'name');
        $expected = array(
            '1' => 'Car1',
            '2' => 'Car2',
            '3' => 'Car3',
            '4' => 'Car4'
        );
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsOrdered(){
        $pairs = car::order_by_desc('id')->find_pairs();
        $expected = array(
            '4' => 'Car4',
            '3' => 'Car3',
            '2' => 'Car2',
            '1' => 'Car1',
        );
        $this->assertequals($expected, $pairs);
    }

    public function testfindpairsforceselect(){
        $pairs = car::select('id')->select('manufactor_id', 'name')->find_pairs();
        $expected = array(
            '1' => '1',
            '2' => '1',
            '3' => '2',
            '4' => '2',
        );
        $this->assertequals($expected, $pairs);
    }

    public function testfindPairsWithJoin(){
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->select('car.name', 'car_name')
            ->select('manufactor.name', 'manufactor_name')
            ->find_pairs('car_name', 'manufactor_name');
        $expected = array(
            'Car1' => 'Manufactor1',
            'Car2' => 'Manufactor1',
            'Car3' => 'Manufactor2',
            'Car4' => 'Manufactor2',
        );
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsWithJoinIDName(){
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->select('car.name', 'id')
            ->select('manufactor.name', 'name')
            ->find_pairs();
        $expected = array(
            'Car1' => 'Manufactor1',
            'Car2' => 'Manufactor1',
            'Car3' => 'Manufactor2',
            'Car4' => 'Manufactor2',
        );
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsWithJoinOrdered(){
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->select('car.name', 'car_name')
            ->select('manufactor.name', 'manufactor_name')
            ->order_by_desc('car.name')
            ->find_pairs('car_name', 'manufactor_name');
        $expected = array(
            'Car4' => 'Manufactor2',
            'Car3' => 'Manufactor2',
            'Car2' => 'Manufactor1',
            'Car1' => 'Manufactor1',
        );
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsWithJoinExpr(){
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->join('owner', 'owner.id=car.owner_id')
            ->select('car.name', 'car_name')
            ->select_expr('manufactor.name || " " || owner.name', 'manufactor_name') // For MySQL select_expr('CONCAT(manufactor.name, " ", owner.name)', 'manufactor_name')
            ->find_pairs('car_name', 'manufactor_name');
        $expected = array(
            'Car1' => 'Manufactor1 Owner1',
            'Car2' => 'Manufactor1 Owner2',
            'Car3' => 'Manufactor2 Owner3',
            'Car4' => 'Manufactor2 Owner4',
        );
        $this->assertEquals($expected, $pairs);
    }

    public function testNoResultsfindPairs(){
        $pairs = Car::where('id',10)->find_pairs('id', 'name');
        $this->assertEquals(array(), $pairs);
    }

    public function testfindMany(){
        $cars = Car::find_many();
		// Not an empty array
		$this->assertNotSame(array(), $cars);

        $expected = array(
            '1' => 'Car1',
            '2' => 'Car2',
            '3' => 'Car3',
            '4' => 'Car4'
        );
		foreach ($cars as $id => $car) {
        	$this->assertEquals($id, $car->id);
        	$this->assertEquals($expected[$id], $car->name);
		}
    }

    public function testfindManyFiltered(){
        $cars = Car::where('id',3)->find_many();
		// Not an empty array
		$this->assertNotSame(array(), $cars);

        $expected = array(
            '3' => 'Car3',
        );
		foreach ($cars as $id => $car) {
        	$this->assertEquals($id, $car->id);
        	$this->assertEquals($expected[$id], $car->name);
		}
    }

    public function testNoResultsfindMany(){
        $cars = Car::where('id',10)->find_many();
        $this->assertSame(array(), $cars->as_array());
        $this->assertEquals(0, count($cars));
    }

    public function testfilters(){
        $car = Car::byName('Car1')->find_one();
        $this->assertEquals($car->name, 'Car1');
    }

    /**
     * @expectedException Exception
     */
    public function testnonExistentFilter(){
        $car = Car::test('Car1')->find_one();
    }

    public function testInsert(){
        Car::insert(array(
            array(
                'id'=> '20',
                'name' =>'Car20',
                'manufactor_id'=>  1,
                'owner_id'=>  1
            )
        ));
        $count = Car::count();
        $this->assertEquals(5, $count, 'Car must be Inserted');
    }
}
