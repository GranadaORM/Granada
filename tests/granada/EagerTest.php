<?php

use Granada\ORM;
use Granada\Orm\ConnectionManager;
use Granada\Model;
use Granada\ResultSet;

/**
 * Testing eager loading
 *
 * @author Peter Schumacher <peter@schumacher.dk>
 *
 * Modified by Tom van Oorschot <tomvanoorschot@gmail.com>
 * Additions:
 *  - Test will also check for double records on a has_many relation
 */
class EagerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // A fresh manager per test, so each test's statements are the
        // only entries in the query log.
        ORM::set_connection_manager(new ConnectionManager('sqlite::memory:'));
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        // Restore the default config so a test that turns a setting off
        // does not leak it into the next test
        ORM::configure('return_result_sets', true);

        // Enable logging
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
    }

    public function testFindOneWith1BelongsTo()
    {
        $car = Car::q()
            ->with('manufactor')
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testFindOneWith2BelongsTo()
    {
        $car = Car::q()
            ->with('owner', 'manufactor')
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` IN ('1')";
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testFindOneWith1HasOne()
    {
        $owner = Owner::q()
            ->with('car')
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`owner_id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testFindOneWith1HasMany()
    {
        $manufactor = Manufactor::q()
            ->with('cars')
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`enabled` = '1' AND `car`.`manufactor_id` IN ('1')";
        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testFindOneWithHasManyThrough()
    {
        $car = Car::q()
            ->with('parts')
            ->find_one(1);
        $actualParts = [];
        foreach ($car->parts as $p) {
            $actualParts[] = $p->as_array();
        }

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `part`.*, `car_part`.`car_id` FROM `part` JOIN `car_part` ON `part`.`id` = `car_part`.`part_id` WHERE `car_part`.`car_id` IN ('1')";

        $expectedParts   = [];
        $expectedParts[] = [
            'id'    => 1,
            'name'  => 'Part1',
            'price' => 5.01,
        ];
        $expectedParts[] = [
            'id'    => 2,
            'name'  => 'Part2',
            'price' => 15.00,
        ];
        $expectedParts[] = [
            'id'    => 1,
            'name'  => 'Part1',
            'price' => 5.01,
        ];

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertEquals($expectedParts, $actualParts);
    }

    public function testFindManyWith2BelongsTo()
    {
        $cars = Car::q()
            ->with('owner', 'manufactor')
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0'";
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` IN ('1', '2', '3', '4')";
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1', '2')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testFindManyWith1HasOne()
    {
        $owner = Owner::q()
            ->with('car')
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = 'SELECT * FROM `owner`';
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`owner_id` IN ('1', '2', '3', '4')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testFindManyWith1HasMany()
    {
        $manufactor = Manufactor::q()
            ->with('cars')
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = 'SELECT * FROM `manufactor`';
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`enabled` = '1' AND `car`.`manufactor_id` IN ('1', '2')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testFindManyWithHasManyThrough()
    {
        $parts = Part::q()
            ->with('cars')
            ->find_many();

        $actualParts = [];

        foreach ($parts as $part) {
            $tmp         = $part->as_array();
            $tmp['cars'] = [];

            if (count($part->cars) > 0) {
                foreach ($part->cars as $car) {
                    $tmp['cars'][] = $car->as_array();
                }
            }

            $actualParts[] = $tmp;
        }

        $expectedParts   = [];
        $expectedParts[] = [
            'id'   => '1',
            'name' => 'Part1',
            'cars' => [
                [
                    'id'               => '1',
                    'name'             => 'Car1',
                    'manufactor_id'    => '1',
                    'owner_id'         => '1',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2020-01-01',
                ],
                [
                    'id'               => '2',
                    'name'             => 'Car2',
                    'manufactor_id'    => '1',
                    'owner_id'         => '2',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2020-02-01',
                ],
                [
                    'id'               => '3',
                    'name'             => 'Car3',
                    'manufactor_id'    => '2',
                    'owner_id'         => '3',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2023-10-05',
                ],
                [
                    'id'               => '4',
                    'name'             => 'Car4',
                    'manufactor_id'    => '2',
                    'owner_id'         => '4',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2022-03-30',
                ],
                [
                    'id'               => '1',
                    'name'             => 'Car1',
                    'manufactor_id'    => '1',
                    'owner_id'         => '1',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2020-01-01',
                ],
            ],
            'price' => 5.01,
        ];

        $expectedParts[] = [
            'id'   => '2',
            'name' => 'Part2',
            'cars' => [
                [
                    'id'               => '1',
                    'name'             => 'Car1',
                    'manufactor_id'    => '1',
                    'owner_id'         => '1',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2020-01-01',
                ],
            ],
            'price' => 15.00,
        ];

        $expectedParts[] = [
            'id'   => '3',
            'name' => 'Part3',
            'cars' => [
                [
                    'id'               => '2',
                    'name'             => 'Car2',
                    'manufactor_id'    => '1',
                    'owner_id'         => '2',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2020-02-01',
                ],
            ],
            'price' => 10.95,
        ];

        $expectedParts[] = [
            'id'   => '4',
            'name' => 'Part4',
            'cars' => [
                [
                    'id'               => '3',
                    'name'             => 'Car3',
                    'manufactor_id'    => '2',
                    'owner_id'         => '3',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2023-10-05',
                ],
            ],
            'price' => 25.99,
        ];

        $expectedParts[] = [
            'id'   => '5',
            'name' => 'Part5',
            'cars' => [
                [
                    'id'               => '4',
                    'name'             => 'Car4',
                    'manufactor_id'    => '2',
                    'owner_id'         => '4',
                    'is_deleted'       => '0',
                    'enabled'          => '1',
                    'manufacture_date' => '2022-03-30',
                ],
            ],
            'price' => 14.49,
        ];

        $expectedSql   = [];
        $expectedSql[] = 'SELECT * FROM `part`';
        $expectedSql[] = "SELECT `car`.*, `car_part`.`part_id` FROM `car` JOIN `car_part` ON `car`.`id` = `car_part`.`car_id` WHERE `car`.`is_deleted` = '0' AND `car_part`.`part_id` IN ('1', '2', '3', '4', '5')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertEquals($expectedParts, $actualParts);
    }

    public function testChainedRelationships()
    {
        $owner = Owner::q()
            ->with(['car' => ['with' => 'manufactor']])
            ->find_one(1);
        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`owner_id` IN ('1')";
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testDotNotationEagerLoading()
    {
        $owner = Owner::q()
            ->with('car.manufactor')
            ->find_one(1);

        $this->assertNotNull($owner);
        $this->assertNotNull($owner->car);
        $this->assertEquals('Car1', $owner->car->name);
        $this->assertNotNull($owner->car->manufactor);
        $this->assertEquals('Manufactor1', $owner->car
            ->manufactor
            ->name);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`owner_id` IN ('1')";
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testDotNotationEagerLoadingFindMany()
    {
        $owners = Owner::q()
            ->with('car.manufactor')
            ->find_many();

        $this->assertCount(4, $owners);

        foreach ($owners as $owner) {
            if ($owner->car) {
                $this->assertNotNull($owner->car->manufactor);
            }
        }

        $expectedSql   = [];
        $expectedSql[] = 'SELECT * FROM `owner`';
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`owner_id` IN ('1', '2', '3', '4')";
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1', '2')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testChainedAdterHas_Many_Through()
    {
        $car = Car::q()
            ->with(['parts' => ['with' => 'cars']])
            ->find_one(1);
        $test_exists = $car->as_array();
        $test_exists = $car->parts->as_array();
        foreach ($car->parts as $part) {
            foreach ($part->cars as $car) {
                $test_exists = $car->as_array();
            }
        }
        // NO FATAL ERRORS OR EXCEPTIONS THROW
        $this->assertInstanceOf('Granada\Model', $car);
    }

    /**
     * The car's parts as rows of [iteration position, part name, car names].
     */
    private function partRows(Model $car): array
    {
        $rows = [];
        foreach ($car->parts as $position => $part) {
            $cars = [];
            foreach ($part->cars as $partCar) {
                $cars[] = $partCar->name;
            }
            $rows[] = [$position, $part->name, $cars];
        }

        return $rows;
    }

    /**
     * The through child list is position-keyed, so the eager loader must
     * match children to parents by id, not by list position.
     */
    public function testChainedHasManyThroughAssignsCarsToTheRightParts()
    {
        $car = Car::q()
            ->with(['parts' => ['with' => 'cars']])
            ->find_one(1);

        $this->assertSame([
            [0, 'Part1', ['Car1', 'Car2', 'Car3', 'Car4', 'Car1']],
            [1, 'Part2', ['Car1']],
            [2, 'Part1', ['Car1', 'Car2', 'Car3', 'Car4', 'Car1']],
        ], $this->partRows($car));
    }

    /**
     * Car 4 has no part at its own list position, so a position-based
     * match corrupts the parts and then crashes on a missing row.
     */
    public function testChainedHasManyThroughOnCarWithoutIdAtOwnPosition()
    {
        $car = Car::q()
            ->with(['parts' => ['with' => 'cars']])
            ->find_one(4);

        $this->assertSame([
            [0, 'Part1', ['Car1', 'Car2', 'Car3', 'Car4', 'Car1']],
            [1, 'Part5', ['Car4']],
        ], $this->partRows($car));
    }

    public function testChainedHasManyThroughEagerMatchesLazy()
    {
        $lazy  = $this->partRows(Car::q()->find_one(1));
        $eager = $this->partRows(Car::q()
            ->with(['parts' => ['with' => 'cars']])
            ->find_one(1));

        $this->assertSame([
            [0, 'Part1', ['Car1', 'Car2', 'Car3', 'Car4', 'Car1']],
            [1, 'Part2', ['Car1']],
            [2, 'Part1', ['Car1', 'Car2', 'Car3', 'Car4', 'Car1']],
        ], $eager);
        $this->assertSame($lazy, $eager);
    }

    public function testChainedHasManyThroughWithPlainArrayResults()
    {
        ORM::configure('return_result_sets', false);

        $car = Car::q()
            ->with(['parts' => ['with' => 'cars']])
            ->find_one(1);

        $this->assertSame([
            [0, 'Part1', ['Car1', 'Car2', 'Car3', 'Car4', 'Car1']],
            [1, 'Part2', ['Car1']],
            [2, 'Part1', ['Car1', 'Car2', 'Car3', 'Car4', 'Car1']],
        ], $this->partRows($car));
    }

    public function testWithMagicBelongsToSelect()
    {
        $car = Car::q()
            ->with_manufactor(fn($q) => $q
                ->select('id, name'))
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `id`, `name` FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertEquals('Manufactor1', $car->manufactor->name);
    }

    public function testWithMagicBelongsToSelectAndOrderBy()
    {
        Car::q()
            ->with_manufactor(fn($q) => $q
                ->select('id, name')
                ->order_by_desc('name'))
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0'";
        $expectedSql[] = "SELECT `id`, `name` FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1', '2') ORDER BY `name` DESC";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testWithMagicBelongsToAutoIncludeId()
    {
        $car = Car::q()
            ->with_owner(fn($q) => $q
                ->select('name'))
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `name`, `id` FROM `owner` WHERE `owner`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertEquals('Owner1', $car->owner->name);
    }

    public function testWithMagicHasManySelect()
    {
        $manufactor = Manufactor::q()
            ->with_cars(fn($q) => $q
                ->select('id, name'))
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `id`, `name`, `manufactor_id` FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`enabled` = '1' AND `car`.`manufactor_id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testWithMagicHasOneSelect()
    {
        $owner = Owner::q()
            ->with_car(fn($q) => $q
                ->select('id, name'))
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `id`, `name`, `owner_id` FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`owner_id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testWithMagicHasManyThroughSelect()
    {
        $car = Car::q()
            ->with_parts(fn($q) => $q
                ->select('part.id, part.name'))
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `part`.`id`, `part`.`name`, `car_part`.`car_id` FROM `part` JOIN `car_part` ON `part`.`id` = `car_part`.`part_id` WHERE `car_part`.`car_id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testWithMagicNoCallback()
    {
        $car = Car::q()
            ->with_manufactor()
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testWithMagicMixedWithExistingWith()
    {
        $car = Car::q()
            ->with_manufactor(fn($q) => $q->select('name'))
            ->with('owner')
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `name`, `id` FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testWithMagicNestedWithCallback()
    {
        $owner = Owner::q()
            ->with_car(
                fn($q) => $q->select('id, manufactor_id')
                    ->with_manufactor(fn($q) => $q->select('name'))
            )->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `owner` WHERE `owner`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT `id`, `manufactor_id`, `owner_id` FROM `car` WHERE `car`.`is_deleted` = '0' AND `car`.`owner_id` IN ('1')";
        $expectedSql[] = "SELECT `name`, `id` FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1')";

        $this->assertNull($owner->car->enabled);
        $this->assertNull($owner->car
            ->manufactor
            ->enabled);
        $this->assertEquals('Manufactor1', $owner->car
            ->manufactor
            ->name);

        $this->assertEquals($expectedSql, ORM::get_query_log());
    }

    public function testWithMagicFindManySelect()
    {
        $cars = Car::q()
            ->with_manufactor(fn($q) => $q
                ->select('id, name'))
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0'";
        $expectedSql[] = "SELECT `id`, `name` FROM `manufactor` WHERE `manufactor`.`enabled` = '1' AND `manufactor`.`id` IN ('1', '2')";

        $this->assertEquals($expectedSql, ORM::get_query_log());

        foreach ($cars as $car) {
            $this->assertNotNull($car->manufactor);
            $this->assertNotNull($car->manufactor->id);
            $this->assertNotNull($car->manufactor->name);
        }
    }

    public function testLazyLoading()
    {
        $owner = Owner::q()->find_one(1);
        $this->assertEquals($owner->car->manufactor_id, 1);
    }

    public function testLazyMissingManufacturerNull()
    {
        $man1          = Manufactor::q()->find_one(1);
        $man1->enabled = false;
        $man1->save();

        $car1 = Car::q()->find_one(1);

        $this->assertNull($car1->manufactor);
    }

    public function testEagerMissingManufacturerNull()
    {
        $man1          = Manufactor::q()->find_one(1);
        $man1->enabled = false;
        $man1->save();

        $car1 = Car::q()
            ->with('manufactor')
            ->find_one(1);

        $this->assertNull($car1->manufactor);
    }

    /**
     * Eager and lazy loading must hold the same type, with child rows
     * or without.
     */
    public function testEagerLoadedEmptyHasManyMatchesLazyLoading()
    {
        ORM::get_db()->exec("INSERT INTO manufactor(id, name, enabled) VALUES (3, 'Manufactor3', 1)");

        $lazy = Manufactor::q()
            ->find_one(3)
            ->cars;
        $eager = Manufactor::q()
            ->with('cars')
            ->find_one(3)
            ->cars;

        $this->assertInstanceOf(ResultSet::class, $lazy);
        $this->assertInstanceOf(ResultSet::class, $eager);
        $this->assertSame(get_class($lazy), get_class($eager));
        $this->assertCount(0, $eager);
        $this->assertSame([], $eager->as_array());

        $iterated = [];
        foreach ($eager as $car) {
            $iterated[] = $car;
        }
        $this->assertSame([], $iterated);
    }

    public function testEagerLoadedEmptyHasManyWithPlainArrayResults()
    {
        ORM::configure('return_result_sets', false);
        ORM::get_db()->exec("INSERT INTO manufactor(id, name, enabled) VALUES (3, 'Manufactor3', 1)");

        $lazy = Manufactor::q()
            ->find_one(3)
            ->cars;
        $eager = Manufactor::q()
            ->with('cars')
            ->find_one(3)
            ->cars;

        $this->assertIsArray($lazy);
        $this->assertIsArray($eager);
        $this->assertSame([], $eager);
    }

    public function testEagerHasManyMatchesPositionKeyedParentsById()
    {
        ORM::get_db()->exec("INSERT INTO manufactor(id, name, enabled) VALUES (3, 'Manufactor3', 1)");

        $manufactors = Manufactor::q()
            ->non_associative()
            ->with('cars')
            ->find_many();

        $withCars    = $manufactors[0]->cars;
        $withoutCars = $manufactors[2]->cars;

        $this->assertSame([
            1 => 'Car1',
            2 => 'Car2',
        ], array_map(fn($car) => $car->name, $withCars->as_array()));
        $this->assertInstanceOf(ResultSet::class, $withoutCars);
        $this->assertCount(0, $withoutCars);
        $this->assertNotSame($withCars, $withoutCars);
    }

    public function testEagerHasManyMatchesPositionKeyedParentsWithPlainArrays()
    {
        ORM::configure('return_result_sets', false);
        ORM::get_db()->exec("INSERT INTO manufactor(id, name, enabled) VALUES (3, 'Manufactor3', 1)");

        $manufactors = Manufactor::q()
            ->non_associative()
            ->with('cars')
            ->find_many();

        $this->assertSame([
            1 => 'Car1',
            2 => 'Car2',
        ], array_map(fn($car) => $car->name, $manufactors[0]->cars));
        $this->assertSame([], $manufactors[2]->cars);
    }

    public function testEagerHasOneMatchesPositionKeyedParentsById()
    {
        $owners = Owner::q()
            ->non_associative()
            ->with('car')
            ->find_many();

        $carNames = [];
        foreach ($owners as $owner) {
            $carNames[] = $owner->car === null ? null : $owner->car->name;
        }

        $this->assertSame(['Car1', 'Car2', 'Car3', 'Car4'], $carNames);
    }

    public function testEagerLoadedEmptyHasManyThroughMatchesLazyLoading()
    {
        $lazy = Car::q()
            ->find_one(6)
            ->parts;
        $eager = Car::q()
            ->with('parts')
            ->find_one(6)
            ->parts;

        $this->assertInstanceOf(ResultSet::class, $lazy);
        $this->assertInstanceOf(ResultSet::class, $eager);
        $this->assertSame(get_class($lazy), get_class($eager));
        $this->assertCount(0, $eager);
        $this->assertSame([], $eager->as_array());

        $iterated = [];
        foreach ($eager as $part) {
            $iterated[] = $part;
        }
        $this->assertSame([], $iterated);
    }

    public function testEagerLoadedEmptyHasManyThroughWithPlainArrayResults()
    {
        ORM::configure('return_result_sets', false);

        $lazy = Car::q()
            ->find_one(6)
            ->parts;
        $eager = Car::q()
            ->with('parts')
            ->find_one(6)
            ->parts;

        $this->assertIsArray($lazy);
        $this->assertIsArray($eager);
        $this->assertSame([], $eager);
    }

    public function testEagerValueTypeDoesNotDependOnChildRows()
    {
        ORM::get_db()->exec("INSERT INTO manufactor(id, name, enabled) VALUES (3, 'Manufactor3', 1)");

        $manufactors = Manufactor::q()
            ->with('cars')
            ->find_many()
            ->as_array();

        $withCars    = $manufactors[1]->cars;
        $withoutCars = $manufactors[3]->cars;

        $this->assertInstanceOf(ResultSet::class, $withCars);
        $this->assertInstanceOf(ResultSet::class, $withoutCars);
        $this->assertSame([
            1 => 'Car1',
            2 => 'Car2',
        ], array_map(fn($car) => $car->name, $withCars->as_array()));
        $this->assertCount(0, $withoutCars);
        $this->assertNotSame($withCars, $withoutCars);
    }

    public function testChainedEagerLoadKeepsEmptyNestedRelationshipAResultSet()
    {
        ORM::get_db()->exec("INSERT INTO manufactor(id, name, enabled) VALUES (3, 'Manufactor3', 1)");
        ORM::get_db()->exec(
            'INSERT INTO car(id, name, manufactor_id, owner_id, manufacture_date, enabled, is_deleted)'
            . " VALUES (7, 'Car7', 3, 1, '2026-01-01', 1, 0)"
        );

        $manufactor = Manufactor::q()
            ->with('cars.parts')
            ->find_one(3);

        $cars = $manufactor->cars->as_array();
        $this->assertSame([7 => 'Car7'], array_map(fn($car) => $car->name, $cars));

        $parts = $cars[7]->parts;
        $this->assertInstanceOf(ResultSet::class, $parts);
        $this->assertCount(0, $parts);
        $this->assertSame([], $parts->as_array());
    }

    public function testEagerEmptyRelationshipUsesTheRelatedModelResultSetClass()
    {
        ORM::get_db()->exec("INSERT INTO gadget(id, name, enabled, hidden) VALUES (5, 'Gadget5', 1, 0)");

        $empty = GadgetWithCustomResultSet::q()
            ->with('custom_widgets')
            ->find_one(5)
            ->custom_widgets;
        $full = GadgetWithCustomResultSet::q()
            ->with('custom_widgets')
            ->find_one(1)
            ->custom_widgets;

        $this->assertInstanceOf(CustomResultSet::class, $empty);
        $this->assertCount(0, $empty);
        $this->assertInstanceOf(CustomResultSet::class, $full);
        $this->assertSame([
            1 => 'Widget1',
            2 => 'Widget2',
        ], array_map(fn($widget) => $widget->name, $full->as_array()));
    }

    /**
     * The gadget_by_name relation matches on the declared related key
     * column, gadget.name, so the eager load must find the row the
     * lazy load finds, not the row whose id is in the foreign key.
     * When two gadgets share the name, both paths take the first.
     */
    public function testEagerBelongsToDeclaredRelatedKeyMatchesLazyLoading()
    {
        Gadget::create([
            'id'      => 7,
            'name'    => '1',
            'enabled' => 1,
            'hidden'  => 0,
        ])->save();
        Gadget::create([
            'id'      => 8,
            'name'    => '1',
            'enabled' => 1,
            'hidden'  => 0,
        ])->save();
        Widget::create([
            'id'        => 6,
            'name'      => 'Widget6',
            'gadget_id' => '1',
            'enabled'   => 1,
            'hidden'    => 0,
        ])->save();

        $lazy = Widget::q()
            ->find_one(6)
            ->gadget_by_name;
        $eager = Widget::q()
            ->with('gadget_by_name')
            ->find_one(6)
            ->gadget_by_name;

        $this->assertNotNull($lazy);
        $this->assertSame($lazy->id, $eager->id);
        $this->assertSame('1', $eager->name);

        // Gadget2's own filter hides it, so both paths find no row
        Widget::create([
            'id'        => 7,
            'name'      => 'Widget7',
            'gadget_id' => 'Gadget2',
            'enabled'   => 1,
            'hidden'    => 0,
        ])->save();

        $lazyHidden = Widget::q()
            ->find_one(7)
            ->gadget_by_name;
        $eagerHidden = Widget::q()
            ->with('gadget_by_name')
            ->find_one(7)
            ->gadget_by_name;

        $this->assertNull($lazyHidden);
        $this->assertNull($eagerHidden);
    }

    /**
     * The bolt_codes_by_name relation matches machines by their name
     * column, so the eager load must restrict and match on the
     * declared key column, not on the id.
     */
    public function testEagerHasManyThroughDeclaredLocalKeyMatchesLazyLoading()
    {
        MachineBoltCode::create([
            'id'         => 1,
            'machine_id' => 'Machine1',
            'bolt_code'  => 'AAA',
        ])->save();
        MachineBoltCode::create([
            'id'         => 2,
            'machine_id' => 'Machine1',
            'bolt_code'  => 'BBB',
        ])->save();
        MachineBoltCode::create([
            'id'         => 3,
            'machine_id' => 'Machine2',
            'bolt_code'  => 'CCC',
        ])->save();

        $lazy  = $this->boltCodeNames(Machine::q()->find_one(1));
        $eager = $this->boltCodeNames(Machine::q()
            ->with('bolt_codes_by_name')
            ->find_one(1));

        $this->assertSame(['Bolt1', 'Bolt2'], $lazy);
        $this->assertSame($lazy, $eager);
    }

    /**
     * The bolt names a machine holds through its name-keyed links.
     */
    private function boltCodeNames(Model $machine): array
    {
        return array_map(
            fn($bolt) => $bolt->name,
            $machine->bolt_codes_by_name->as_array()
        );
    }

    /**
     * The car_by_name relation matches on the declared local key
     * column, owner.name, so the eager load must find the row the
     * lazy load finds, not the row whose name is the owner's id.
     */
    public function testEagerHasOneDeclaredLocalKeyMatchesLazyLoading()
    {
        // set_expr() writes the name clean: the model's set_name hook
        // turns any name value into 'test'.
        Car::create([
            'id'               => 7,
            'manufactor_id'    => 1,
            'owner_id'         => 2,
            'manufacture_date' => '2026-01-01',
            'enabled'          => 1,
            'is_deleted'       => 0,
        ])
            ->set_expr('name', "'Owner2'")
            ->save();

        $lazy = Owner::q()
            ->find_one(2)
            ->car_by_name;
        $eager = Owner::q()
            ->with('car_by_name')
            ->find_one(2)
            ->car_by_name;

        $this->assertNotNull($lazy);
        $this->assertSame($lazy->id, $eager->id);
        $this->assertSame('Owner2', $eager->name);
    }

    /**
     * The cars_by_name relation matches on the declared local key
     * column, owner.name, so the eager load must find the rows the
     * lazy load finds, not the rows whose name is the owner's id.
     */
    public function testEagerHasManyDeclaredLocalKeyMatchesLazyLoading()
    {
        Car::create([
            'id'               => 7,
            'manufactor_id'    => 1,
            'owner_id'         => 2,
            'manufacture_date' => '2026-01-01',
            'enabled'          => 1,
            'is_deleted'       => 0,
        ])
            ->set_expr('name', "'Owner2'")
            ->save();
        Car::create([
            'id'               => 8,
            'manufactor_id'    => 1,
            'owner_id'         => 3,
            'manufacture_date' => '2026-01-02',
            'enabled'          => 1,
            'is_deleted'       => 0,
        ])
            ->set_expr('name', "'Owner2'")
            ->save();

        $lazy = Owner::q()
            ->find_one(2)
            ->cars_by_name;
        $eager = Owner::q()
            ->with('cars_by_name')
            ->find_one(2)
            ->cars_by_name;

        $this->assertCount(2, $lazy);
        $this->assertSame(
            array_map(fn($car) => $car->id, $lazy->as_array()),
            array_map(fn($car) => $car->id, $eager->as_array())
        );
    }

    /**
     * A relation chaining a where, a select, an order_by and an
     * ordered_by returns the same rows lazily and eagerly.
     */
    public function testChainedWhereSelectOrderByAndOrderedByLoadTheSameLazilyAndEagerly()
    {
        Crate::create([
            'id'   => 5,
            'name' => 'Crate5',
        ])->save();
        CrateItem::create([
            'id'         => 31,
            'crate_id'   => 5,
            'name'       => 'Item1',
            'qty'        => 1,
            'sort_order' => 2,
        ])->save();
        CrateItem::create([
            'id'         => 32,
            'crate_id'   => 5,
            'name'       => 'Item1',
            'qty'        => 1,
            'sort_order' => 1,
        ])->save();
        CrateItem::create([
            'id'         => 33,
            'crate_id'   => 5,
            'name'       => 'Item2',
            'qty'        => 9,
            'sort_order' => 3,
        ])->save();

        $lazy  = $this->titledItemIds(Crate::q()->find_one(5));
        $eager = $this->titledItemIds(Crate::q()
            ->with('titled_items')
            ->find_one(5));

        $this->assertSame([32, 31], $lazy);
        $this->assertSame($lazy, $eager);
    }

    /**
     * The titled_items relation keeps only qty 1, selects the id
     * column, and orders by sort position.
     */
    private function titledItemIds(Model $crate): array
    {
        return array_values(array_map(
            fn($item) => $item->id,
            $crate->titled_items->as_array()
        ));
    }
}
