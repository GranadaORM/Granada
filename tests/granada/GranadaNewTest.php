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
    	$expected = 6;
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

    public function testfindManySelect(){
        $cars = Car::select('name')->find_many();
		// Not an empty array
		$this->assertNotSame(array(), $cars);
        $this->assertEquals(true, $cars->has_results());

        $expected = array(
			'0' => 'Car1',
			'1' => 'Car2',
			'2' => 'Car3',
			'3' => 'Car4'
        );
		foreach ($cars as $id => $car) {
			$this->assertNull($car->id); // We are only getting the name field
			$this->assertEquals($expected[$id], $car->name);
		}
    }

    public function testfindManyFirstAndLast(){
        $cars = Car::find_many();

        $expected = array(
			'Car1' => array(
				'first' => true,
				'last' => false,
			),
			'Car2' => array(
				'first' => false,
				'last' => false,
			),
			'Car3' => array(
				'first' => false,
				'last' => false,
			),
			'Car4' => array(
				'first' => false,
				'last' => true,
			),
        );
		foreach ($cars as $car) {
			$this->assertSame($expected[$car->name]['first'], $car->isFirstResult());
			$this->assertSame($expected[$car->name]['last'], $car->isLastResult());
		}
    }

    public function testfindManyFirstAndLastDiffOrder(){
        $cars = Car::order_by_desc('id')->find_many();

        $expected = array(
			'Car1' => array(
				'first' => false,
				'last' => true,
			),
			'Car2' => array(
				'first' => false,
				'last' => false,
			),
			'Car3' => array(
				'first' => false,
				'last' => false,
			),
			'Car4' => array(
				'first' => true,
				'last' => false,
			),
        );
		foreach ($cars as $car) {
			$this->assertSame($expected[$car->name]['first'], $car->isFirstResult());
			$this->assertSame($expected[$car->name]['last'], $car->isLastResult());
		}
    }

    public function testRelatedModelFirstAndLast(){
        $cars = Car::find_many();
		// SELECT * FROM `car`

        $expected = array(
			'Car1' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => false,
				),
				2 => array(
					'first' => false,
					'last' => true,
				),
			),
			'Car2' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => true,
				),
			),
			'Car3' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => true,
				),
			),
			'Car4' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => true,
				),
			),
        );
		foreach ($cars as $car) {
			$partcounter = 0;
			foreach ($car->parts as $part) {
				$this->assertSame($expected[$car->name][$partcounter]['first'], $part->isFirstResult());
				$this->assertSame($expected[$car->name][$partcounter]['last'], $part->isLastResult());
				$partcounter++;
			}
		}
    }

    public function testRelatedModelFirstAndLastEager(){
        $cars = Car::with('parts')->find_many();
		// SELECT `part`.*, `car_part`.`car_id` FROM `part` JOIN `car_part` ON `part`.`id` = `car_part`.`part_id` WHERE `car_part`.`car_id` IN ('1', '2', '3', '4')

        $expected = array(
			'Car1' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => false,
				),
				2 => array(
					'first' => false,
					'last' => true,
				),
			),
			'Car2' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => true,
				),
			),
			'Car3' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => true,
				),
			),
			'Car4' => array(
				0 => array(
					'first' => true,
					'last' => false,
				),
				1 => array(
					'first' => false,
					'last' => true,
				),
			),
        );
		foreach ($cars as $car) {
			$partcounter = 0;
			foreach ($car->parts as $part) {
				$this->assertSame($expected[$car->name][$partcounter]['first'], $part->isFirstResult());
				$this->assertSame($expected[$car->name][$partcounter]['last'], $part->isLastResult());
				$partcounter++;
			}
		}
    }

    public function testfindMany(){
        $cars = Car::find_many();
		// Not an empty array
		$this->assertNotSame(array(), $cars);
        $this->assertEquals(true, $cars->has_results());

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
        $this->assertEquals(false, $cars->has_results());
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
                'owner_id'=>  1,
				'is_deleted' => 0,
            )
        ));
        $count = Car::count();
        $expectedSql   = array();
        $expectedSql[] = "INSERT INTO `car` (`id`, `name`, `manufactor_id`, `owner_id`, `is_deleted`) VALUES ('20', 'test', '1', '1', '0')";
        $expectedSql[] = "SELECT COUNT(*) AS `count` FROM `car` WHERE `car`.`is_deleted` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(5, $count, 'Car must be Inserted');
    }

    public function testInsertDeleted(){
        Car::insert(array(
            array(
                'id'=> '20',
                'name' =>'Car20',
                'manufactor_id'=>  1,
                'owner_id'=>  1,
				'is_deleted' => 1,
            )
        ));
        $count = Car::count();
        $expectedSql   = array();
        $expectedSql[] = "INSERT INTO `car` (`id`, `name`, `manufactor_id`, `owner_id`, `is_deleted`) VALUES ('20', 'test', '1', '1', '1')";
        $expectedSql[] = "SELECT COUNT(*) AS `count` FROM `car` WHERE `car`.`is_deleted` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(4, $count);
    }

    public function testCountAll() {
        $count = Car::count();
        $expectedSql   = array();
        $expectedSql[] = "SELECT COUNT(*) AS `count` FROM `car` WHERE `car`.`is_deleted` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(4, $count);
    }

    public function testCountAllCleared() {
        $count = Car::clear_where()->count();
        $expectedSql   = array();
        $expectedSql[] = "SELECT COUNT(*) AS `count` FROM `car` LIMIT 1";

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(5, $count);
    }

}
