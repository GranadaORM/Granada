<?php

use Granada\ORM;

/**
 * The order_by_list() and order_by_rand() query methods run on SQLite.
 */
class OrderByTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_db();
        ORM::reset_config();
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::reset_db();
        \Granada\LazyItemCache::clear();
    }

    private function car_ids(iterable $cars): array
    {
        $ids = [];
        foreach ($cars as $car) {
            $ids[] = $car->id;
        }

        return $ids;
    }

    public function testOrderByListPutsTheRestBeforeTheListedValuesInTheOrderGiven()
    {
        $ids = [];
        foreach (['a', 'b', 'c', 'd'] as $name) {
            $car = Car::create([
                'name'       => $name,
                'enabled'    => 1,
                'is_deleted' => 0,
            ]);
            $car->save();
            $ids[] = (int) $car->id;
        }
        [$a, $b, $c, $d] = $ids;

        $cars = Car::q()
            ->where_in('id', $ids)
            ->order_by_list('id', [$c, $a])
            ->find_many();

        $this->assertSame([$b, $d, $c, $a], $this->car_ids($cars));
    }

    public function testOrderByRandReturnsEveryRowOnce()
    {
        $ids = [];
        foreach (['a', 'b', 'c', 'd'] as $name) {
            $car = Car::create([
                'name'       => $name,
                'enabled'    => 1,
                'is_deleted' => 0,
            ]);
            $car->save();
            $ids[] = (int) $car->id;
        }

        $found = $this->car_ids(Car::q()
            ->where_in('id', $ids)
            ->order_by_rand()
            ->find_many());
        sort($found);

        $this->assertSame($ids, $found);
    }
}
