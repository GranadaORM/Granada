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
class GranadaNewTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @before
     */
    protected function beforeTest()
    {
        // The tests for eager loading requires a real database.
        // Set up SQLite in memory
        ORM::set_db(new PDO('sqlite::memory:'));

        // Create schemas and populate with data
        ORM::get_db()->exec(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'));

        // Enable logging
        ORM::configure('logging', true);
    }

    /**
     * @after
     */
    protected function afterTest()
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
    }

    public function testGetter()
    {
        $car      = Model::factory('Car')->find_one(1);
        $expected = 'Car1';
        $this->assertEquals($expected, $car->get('name'), 'Get method test');
        $this->assertEquals($expected, $car->name, '__get magic method test');

        $car      = Model::factory('Car')->find_one(1);
        $expected = null;
        $this->assertEquals($expected, $car->nonExistentProperty, 'NULL returned if no property found');

        $car                   = Model::factory('Car')->find_one(1);
        $expected              = 'test test';
        $car->existingProperty = 'TEST TeSt';
        $this->assertEquals($expected, $car->existingProperty, 'get_ method overload test');

        $car      = Model::factory('Car')->find_one(1);
        $expected = 'This property is missing';
        $this->assertEquals($expected, $car->someProperty, 'Missing property fallback test');
    }

    public function testSetterForProperty()
    {
        $car       = Model::factory('Car')->find_one(1);
        $car->name = 'Car1';
        $car->save();
        $expected = 'test';
        $this->assertEquals($expected, $car->name);
    }

    public function testNewItemNoID()
    {
        $car = Model::factory('Car')->create([
            'name' => 'New Car',
        ]);
        $car->save();
        $expected = 7;
        $this->assertEquals($expected, $car->id);
    }

    public function testNewItemBlankID()
    {
        $car = Model::factory('Car')->create([
            'id'   => '',
            'name' => 'New Car',
        ]);
        $car->save();
        $expected = 7;
        $this->assertEquals($expected, $car->id);
    }

    public function testSetterForRelationship()
    {
        $car      = Model::factory('Car')->with('manufactor')->find_one(1);
        $expected = 'Manufactor1';
        $this->assertEquals($expected, $car->manufactor->name, 'Relationship loaded');

        $expected        = 'test';
        $car->manufactor = 'test';

        $this->assertEquals($expected, $car->relationships['manufactor'], 'Relationship overloaded');
    }

    public function testCallStaticForModel()
    {
        $expected = Model::factory('Car')->with('manufactor')->find_one(1);
        $car      = Car::with('manufactor')->find_one(1);
        $this->assertEquals($expected, $car, 'Call from static and from factory are the same');
    }

    public function testPluckValid()
    {
        $id = Car::where_id_is(1)->pluck('id');
        $this->assertEquals(1, $id, 'PLuck a column');
    }

    public function testPluckInvalid()
    {
        $id = Car::where_id_is(10)->pluck('id');
        $this->assertNull($id);
    }

    public function testfindPairs()
    {
        $pairs    = Car::find_pairs('id', 'name');
        $expected = [
            '1' => 'Car1',
            '2' => 'Car2',
            '3' => 'Car3',
            '4' => 'Car4',
            '6' => 'Car6',
        ];
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsOrdered()
    {
        $pairs    = Car::order_by_desc('id')->find_pairs();
        $expected = [
            '6' => 'Car6',
            '4' => 'Car4',
            '3' => 'Car3',
            '2' => 'Car2',
            '1' => 'Car1',
        ];
        $this->assertequals($expected, $pairs);
    }

    public function testfindpairsforceselect()
    {
        $pairs    = car::select('id')->select('manufactor_id', 'name')->find_pairs();
        $expected = [
            '1' => '1',
            '2' => '1',
            '3' => '2',
            '4' => '2',
            '6' => '2',
        ];
        $this->assertequals($expected, $pairs);
    }

    public function testfindPairsWithJoin()
    {
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->select('car.name', 'car_name')
            ->select('manufactor.name', 'manufactor_name')
            ->find_pairs('car_name', 'manufactor_name');
        $expected = [
            'Car1' => 'Manufactor1',
            'Car2' => 'Manufactor1',
            'Car3' => 'Manufactor2',
            'Car4' => 'Manufactor2',
            'Car6' => 'Manufactor2',
        ];
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsWithJoinIDName()
    {
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->select('car.name', 'id')
            ->select('manufactor.name', 'name')
            ->find_pairs();
        $expected = [
            'Car1' => 'Manufactor1',
            'Car2' => 'Manufactor1',
            'Car3' => 'Manufactor2',
            'Car4' => 'Manufactor2',
            'Car6' => 'Manufactor2',
        ];
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsWithJoinOrdered()
    {
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->select('car.name', 'car_name')
            ->select('manufactor.name', 'manufactor_name')
            ->order_by_desc('car.name')
            ->find_pairs('car_name', 'manufactor_name');
        $expected = [
            'Car4' => 'Manufactor2',
            'Car3' => 'Manufactor2',
            'Car2' => 'Manufactor1',
            'Car1' => 'Manufactor1',
            'Car6' => 'Manufactor2',
        ];
        $this->assertEquals($expected, $pairs);
    }

    public function testfindPairsWithJoinExpr()
    {
        $pairs = Car::join('manufactor', 'manufactor.id=car.manufactor_id')
            ->join('owner', 'owner.id=car.owner_id')
            ->select('car.name', 'car_name')
            ->select_expr('manufactor.name || " " || owner.name', 'manufactor_name') // For MySQL select_expr('CONCAT(manufactor.name, " ", owner.name)', 'manufactor_name')
            ->find_pairs('car_name', 'manufactor_name');
        $expected = [
            'Car1' => 'Manufactor1 Owner1',
            'Car2' => 'Manufactor1 Owner2',
            'Car3' => 'Manufactor2 Owner3',
            'Car4' => 'Manufactor2 Owner4',
            'Car6' => 'Manufactor2 Owner4',
        ];
        $this->assertEquals($expected, $pairs);
    }

    public function testNoResultsfindPairs()
    {
        $pairs = Car::where('id', 10)->find_pairs('id', 'name');
        $this->assertEquals([], $pairs);
    }

    public function testfindManySelect()
    {
        $cars = Car::select('name')->find_many();
        // Not an empty array
        $this->assertNotSame([], $cars);
        $this->assertEquals(true, $cars->has_results());

        $expected = [
            '0' => 'Car1',
            '1' => 'Car2',
            '2' => 'Car3',
            '3' => 'Car4',
            '4' => 'Car6',
        ];
        foreach ($cars as $id => $car) {
            $this->assertNull($car->id); // We are only getting the name field
            $this->assertEquals($expected[$id], $car->name);
        }
    }

    public function testfindManyFirstAndLast()
    {
        $cars = Car::find_many();

        $expected = [
            'Car1' => [
                'first' => true,
                'last'  => false,
            ],
            'Car2' => [
                'first' => false,
                'last'  => false,
            ],
            'Car3' => [
                'first' => false,
                'last'  => false,
            ],
            'Car4' => [
                'first' => false,
                'last'  => false,
            ],
            'Car6' => [
                'first' => false,
                'last'  => true,
            ],
        ];
        foreach ($cars as $car) {
            $this->assertSame($expected[$car->name]['first'], $car->isFirstResult());
            $this->assertSame($expected[$car->name]['last'], $car->isLastResult());
        }
    }

    public function testfindManyFirstAndLastDiffOrder()
    {
        $cars = Car::order_by_desc('id')->find_many();

        $expected = [
            'Car1' => [
                'first' => false,
                'last'  => true,
            ],
            'Car2' => [
                'first' => false,
                'last'  => false,
            ],
            'Car3' => [
                'first' => false,
                'last'  => false,
            ],
            'Car4' => [
                'first' => false,
                'last'  => false,
            ],
            'Car6' => [
                'first' => true,
                'last'  => false,
            ],
        ];
        foreach ($cars as $car) {
            $this->assertSame($expected[$car->name]['first'], $car->isFirstResult());
            $this->assertSame($expected[$car->name]['last'], $car->isLastResult());
        }
    }

    public function testRelatedModelFirstAndLast()
    {
        $cars = Car::find_many();
        // SELECT * FROM `car`

        $expected = [
            'Car1' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => false,
                ],
                2 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
            'Car2' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
            'Car3' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
            'Car4' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
        ];
        foreach ($cars as $car) {
            $partcounter = 0;
            foreach ($car->parts as $part) {
                $this->assertSame($expected[$car->name][$partcounter]['first'], $part->isFirstResult());
                $this->assertSame($expected[$car->name][$partcounter]['last'], $part->isLastResult());
                $partcounter++;
            }
        }
    }

    public function testRelatedModelFirstAndLastEager()
    {
        $cars = Car::with('parts')->find_many();
        // SELECT `part`.*, `car_part`.`car_id` FROM `part` JOIN `car_part` ON `part`.`id` = `car_part`.`part_id` WHERE `car_part`.`car_id` IN ('1', '2', '3', '4')

        $expected = [
            'Car1' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => false,
                ],
                2 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
            'Car2' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
            'Car3' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
            'Car4' => [
                0 => [
                    'first' => true,
                    'last'  => false,
                ],
                1 => [
                    'first' => false,
                    'last'  => true,
                ],
            ],
        ];
        foreach ($cars as $car) {
            $partcounter = 0;
            foreach ($car->parts as $part) {
                $this->assertSame($expected[$car->name][$partcounter]['first'], $part->isFirstResult());
                $this->assertSame($expected[$car->name][$partcounter]['last'], $part->isLastResult());
                $partcounter++;
            }
        }
    }

    public function testfindMany()
    {
        $cars = Car::find_many();
        // Not an empty array
        $this->assertNotSame([], $cars);
        $this->assertEquals(true, $cars->has_results());

        $expected = [
            '1' => 'Car1',
            '2' => 'Car2',
            '3' => 'Car3',
            '4' => 'Car4',
            '6' => 'Car6',
        ];
        foreach ($cars as $id => $car) {
            $this->assertEquals($id, $car->id);
            $this->assertEquals($expected[$id], $car->name);
        }
    }

    public function testfindManyFiltered()
    {
        $cars = Car::where('id', 3)->find_many();
        // Not an empty array
        $this->assertNotSame([], $cars);

        $expected = [
            '3' => 'Car3',
        ];
        foreach ($cars as $id => $car) {
            $this->assertEquals($id, $car->id);
            $this->assertEquals($expected[$id], $car->name);
        }
    }

    public function testNoResultsfindMany()
    {
        $cars = Car::where('id', 10)->find_many();
        $this->assertSame([], $cars->as_array());
        $this->assertEquals(0, count($cars));
        $this->assertEquals(false, $cars->has_results());
    }

    public function testfilters()
    {
        $car = Car::byName('Car1')->find_one();
        $this->assertEquals($car->name, 'Car1');
    }

    public function testnonExistentFilter()
    {
        try {
            $car = Car::test('Car1')->find_one();
            $this->assertSame('Bad', 'Should have thrown exception');
        } catch (Exception $e) {
            $this->assertSame(" no static test found or static method 'filter_test' not defined in Car", $e->getMessage());
        }
    }

    public function testVarnameOrderBy()
    {
        Part::order_by_name()->find_many();
        $this->assertSame('SELECT * FROM `part` ORDER BY `name` ASC', ORM::get_last_query());
    }

    public function testVarnameOrderByAsc()
    {
        Part::order_by_name_asc()->find_many();
        $this->assertSame('SELECT * FROM `part` ORDER BY `name` ASC', ORM::get_last_query());
    }

    public function testVarnameOrderByDesc()
    {
        Part::order_by_name_desc()->find_many();
        $this->assertSame('SELECT * FROM `part` ORDER BY `name` DESC', ORM::get_last_query());
    }

    public function testVarnameOrderByNaturalAsc()
    {
        Part::order_by_name_natural_asc()->find_many();
        $this->assertSame('SELECT * FROM `part` ORDER BY LENGTH(`name`), `name` ASC', ORM::get_last_query());
    }

    public function testVarnameOrderByNaturalDesc()
    {
        Part::order_by_name_natural_desc()->find_many();
        $this->assertSame('SELECT * FROM `part` ORDER BY LENGTH(`name`), `name` DESC', ORM::get_last_query());
    }

    public function testVarnameLtOrNULL()
    {
        Part::where_name_lt_or_null(5)->find_many();
        $this->assertSame("SELECT * FROM `part` WHERE ( `part`.`name` < '5' OR `part`.`name` IS NULL )", ORM::get_last_query());
    }

    public function testVarnameLteOrNULL()
    {
        Part::where_name_lte_or_null(5)->find_many();
        $this->assertSame("SELECT * FROM `part` WHERE ( `part`.`name` <= '5' OR `part`.`name` IS NULL )", ORM::get_last_query());
    }

    public function testVarnameGtOrNULL()
    {
        Part::where_name_gt_or_null(5)->find_many();
        $this->assertSame("SELECT * FROM `part` WHERE ( `part`.`name` > '5' OR `part`.`name` IS NULL )", ORM::get_last_query());
    }

    public function testVarnameGteOrNULL()
    {
        Part::where_name_gte_or_null(5)->find_many();
        $this->assertSame("SELECT * FROM `part` WHERE ( `part`.`name` >= '5' OR `part`.`name` IS NULL )", ORM::get_last_query());
    }

    public function testVarnameNotInOrNULL()
    {
        Part::where_name_not_in_or_null([5, 6])->find_many();
        $this->assertSame("SELECT * FROM `part` WHERE ( `part`.`name` NOT IN ('5', '6') OR `part`.`name` IS NULL )", ORM::get_last_query());
    }

    public function testSubSelectIn()
    {
        Car::where_manufactor_id_in(
            Manufactor::where_name('Manufactor2')->select('id')
        )->find_many();

        $this->assertSame("SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`manufactor_id` IN (SELECT `id` FROM `manufactor` WHERE `manufactor`.`name` = 'Manufactor2')", ORM::get_last_query());
    }

    public function testSubSelectNotIn()
    {
        Car::where_manufactor_id_not_in(
            Manufactor::where_name('Manufactor2')->select('id')
        )->find_many();

        $this->assertSame("SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`manufactor_id` NOT IN (SELECT `id` FROM `manufactor` WHERE `manufactor`.`name` = 'Manufactor2')", ORM::get_last_query());
    }

    public function testVarnameEqual()
    {
        $this->assertSame(
            "SELECT * FROM `part` WHERE `part`.`name` = 'Part2'",
            Part::where_name('Part2')->get_select_query()
        );
    }

    public function testVarnameInBlank()
    {
        $this->assertSame(
            'SELECT * FROM `part` WHERE 0',
            Part::where_name_in([])->get_select_query()
        );
    }

    public function testVarnameIn()
    {
        $this->assertSame(
            "SELECT * FROM `part` WHERE `part`.`name` IN ('Part1', 'Part2')",
            Part::where_name_in(['Part1', 'Part2'])->get_select_query()
        );
    }

    public function testVarnameNotIn()
    {
        $this->assertSame(
            "SELECT * FROM `part` WHERE `part`.`name` NOT IN ('Part1', 'Part2')",
            Part::where_name_not_in(['Part1', 'Part2'])->get_select_query()
        );
    }

    public function testInsert()
    {
        Car::insert([
            [
                'id'            => '20',
                'name'          => 'Car20',
                'manufactor_id' => 1,
                'owner_id'      => 1,
                'is_deleted'    => 0,
            ],
        ]);
        $count         = Car::count();
        $expectedSql   = [];
        $expectedSql[] = "INSERT INTO `car` (`id`, `name`, `manufactor_id`, `owner_id`, `is_deleted`) VALUES ('20', 'test', '1', '1', '0')";
        $expectedSql[] = "SELECT COUNT(*) AS `count` FROM `car` WHERE `car`.`is_deleted` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(6, $count, 'Car must be Inserted');
    }

    public function testInsertDeleted()
    {
        Car::insert([
            [
                'id'            => '20',
                'name'          => 'Car20',
                'manufactor_id' => 1,
                'owner_id'      => 1,
                'is_deleted'    => 1,
            ],
        ]);
        $count         = Car::count();
        $expectedSql   = [];
        $expectedSql[] = "INSERT INTO `car` (`id`, `name`, `manufactor_id`, `owner_id`, `is_deleted`) VALUES ('20', 'test', '1', '1', '1')";
        $expectedSql[] = "SELECT COUNT(*) AS `count` FROM `car` WHERE `car`.`is_deleted` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(5, $count);
    }

    public function testCountAll()
    {
        $count         = Car::count();
        $expectedSql   = [];
        $expectedSql[] = "SELECT COUNT(*) AS `count` FROM `car` WHERE `car`.`is_deleted` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(5, $count);
    }

    public function testCountAllCleared()
    {
        $count         = Car::clear_where()->count();
        $expectedSql   = [];
        $expectedSql[] = 'SELECT COUNT(*) AS `count` FROM `car` LIMIT 1';

        $fullQueryLog = ORM::get_query_log();

        // Return last two queries
        $actualSql = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertEquals(6, $count);
    }

    public function testDirty()
    {
        $car = Model::factory('Car')->find_one(1);

        $this->assertSame(false, $car->is_dirty('name'));
        $this->assertSame(false, $car->is_any_dirty());
        $this->assertEquals(1, $car->manufactor_id);

        $car->manufactor_id = 2;
        $this->assertSame(true, $car->is_dirty('manufactor_id'));
        $this->assertSame(true, $car->is_any_dirty());
        $this->assertEquals(2, $car->manufactor_id);
    }

    public function testDirtyNumericString()
    {
        $manufactor = Model::factory('Manufactor')->find_one(1);

        $this->assertSame('Manufactor1', $manufactor->name);
        $this->assertSame(false, $manufactor->is_dirty('name'));
        $this->assertSame(false, $manufactor->is_any_dirty());

        $manufactor->name = '1';
        $this->assertSame(true, $manufactor->is_dirty('name'));
        $this->assertSame(true, $manufactor->is_any_dirty());

        $manufactor->save();
        $this->assertSame(false, $manufactor->is_dirty('name'));
        $this->assertSame(false, $manufactor->is_any_dirty());
        $this->assertSame('1', $manufactor->name);

        $manufactor = Model::factory('Manufactor')->find_one(1);
        $this->assertSame('1', $manufactor->name);

        $manufactor->name = '01';
        $this->assertSame(true, $manufactor->is_dirty('name'));
        $this->assertSame(true, $manufactor->is_any_dirty());
        $manufactor->save();

        $manufactor = Model::factory('Manufactor')->find_one(1);
        $this->assertSame(false, $manufactor->is_dirty('name'));
        $this->assertSame(false, $manufactor->is_any_dirty());
        $this->assertSame('01', $manufactor->name);
    }

    public function testDirtyNumericInteger()
    {
        $car = Model::factory('Car')->find_one(1);

        $this->assertSame(1, $car->manufactor_id);
        $this->assertSame(false, $car->is_dirty('manufactor_id'));
        $this->assertSame(false, $car->is_any_dirty());

        $car->manufactor_id = '1';
        $this->assertSame(false, $car->is_dirty('manufactor_id'));
        $this->assertSame(false, $car->is_any_dirty());

        $car->save();
        $car = Model::factory('Car')->find_one(1);

        $car->manufactor_id = '001';
        $this->assertSame(false, $car->is_dirty('manufactor_id'));
        $this->assertSame(false, $car->is_any_dirty());

        $car->save();
        $car = Model::factory('Car')->find_one(1);

        $this->assertSame(false, $car->is_dirty('manufactor_id'));
        $this->assertSame(false, $car->is_any_dirty());
        $this->assertSame(1, $car->manufactor_id);

        $car = Model::factory('Car')->find_one(1);
        $this->assertSame(1, $car->manufactor_id);

        $car->manufactor_id = '01';
        $this->assertSame(false, $car->is_dirty('manufactor_id'));
        $this->assertSame(false, $car->is_any_dirty());
        $car->save();

        $car = Model::factory('Car')->find_one(1);
        $this->assertSame(false, $car->is_dirty('manufactor_id'));
        $this->assertSame(false, $car->is_any_dirty());
        $this->assertSame(1, $car->manufactor_id);
    }

    public function testCleanValue()
    {
        $car = Model::factory('Car')->find_one(1);

        $this->assertEquals(1, $car->manufactor_id);
        $this->assertEquals(1, $car->clean_value('manufactor_id'));
        $car->manufactor_id = 2;
        $this->assertEquals(1, $car->clean_value('manufactor_id'));
        $this->assertEquals(2, $car->manufactor_id);
        $expected = [
            'id'            => '1',
            'name'          => 'Car1',
            'manufactor_id' => '1',
            'owner_id'      => '1',
            'is_deleted'    => '0',
            'enabled'       => '1',
        ];
        $this->assertEquals($expected, $car->clean_values());
        $car->save();
        // Changes after save
        $expected['manufactor_id'] = 2;
        $this->assertEquals($expected, $car->clean_values());
    }

    public function testNotFound()
    {
        $car = Model::factory('Car')->find_one(20);
        $this->assertNull($car);
    }

    public function testMapped()
    {
        $cars = Model::factory('Car')->order_by_desc('id')->find_map(function ($e) {
            return [
                'id'   => $e->id,
                'name' => $e->name . ' ' . $e->manufactor_id,
            ];
        });

        /**
         * For PHP 7.4+ you can use shorthand functions
         * $cars = Model::factory('Car')->find_map(fn ($e) => [
         *   'id' => $e->id,
         *   'name' => $e->name . ' ' . $e->manufactor_id,
         * ]);
         */
        $expected = [
            6 => [
                'id'   => 6,
                'name' => 'Car6 2',
            ],
            4 => [
                'id'   => 4,
                'name' => 'Car4 2',
            ],
            3 => [
                'id'   => 3,
                'name' => 'Car3 2',
            ],
            2 => [
                'id'   => 2,
                'name' => 'Car2 1',
            ],
            1 => [
                'id'   => 1,
                'name' => 'Car1 1',
            ],
        ];
        $this->assertEquals($expected, $cars);
    }
}
