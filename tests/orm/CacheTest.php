<?php

use Granada\Granada;
use Granada\ORM;

class CacheTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate'; // Used as name of alternate connection

    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        ORM::set_db(new PDO('sqlite::memory:'), self::ALTERNATE);
        ORM::get_db(self::ALTERNATE)->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        ORM::configure('logging', true);
        ORM::configure('logging', true, self::ALTERNATE);
        ORM::configure('caching', true);
        ORM::configure('caching', true, self::ALTERNATE);
        ORM::clear_cache();
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testQueryGenerationOnlyOccursOnce()
    {
        Granada::for_table('widget')
            ->where('name', 'Widget1')
            ->where('gadget_id', 1)
            ->find_one();
        Granada::for_table('widget')
            ->where('name', 'Widget2')
            ->where('gadget_id', 1)
            ->find_one();

        $statements = count(ORM::get_query_log());

        Granada::for_table('widget')
            ->where('name', 'Widget1')
            ->where('gadget_id', 1)
            ->find_one(); // this call reads the cache

        $this->assertCount($statements, ORM::get_query_log());
    }

    public function testQueryGenerationOnlyOccursOnceWithMultipleConnections()
    {
        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget3')
            ->where('gadget_id', 2)
            ->find_one();
        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget4')
            ->where('gadget_id', 4)
            ->find_one();

        $statements = count(ORM::get_query_log(self::ALTERNATE));

        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget3')
            ->where('gadget_id', 2)
            ->find_one(); // this call reads the cache

        $this->assertCount($statements, ORM::get_query_log(self::ALTERNATE));
    }

    public function testCountServesTheSecondCallFromTheCache()
    {
        Granada::for_table('widget')->count();
        $statements = count(ORM::get_query_log());

        Granada::for_table('widget')->count(); // this call reads the cache

        $this->assertCount($statements, ORM::get_query_log());
    }

    public function testDeleteManyInvalidatesTheQueryCache()
    {
        $this->assertCount(5, Car::q()->find_many());

        Car::q()
            ->where('id', 1)
            ->delete_many();

        $this->assertCount(4, Car::q()->find_many());
    }

    public function testUpdateManyInvalidatesTheQueryCache()
    {
        $car = Car::q()
            ->where('id', 2)
            ->find_one();
        $this->assertSame(1, (int) $car->enabled);

        Car::q()
            ->where('id', 2)
            ->update_many(['enabled' => 0]);

        $car = Car::q()
            ->where('id', 2)
            ->find_one();
        $this->assertSame(0, (int) $car->enabled);
    }

    public function testDeleteInvalidatesTheQueryCache()
    {
        $car = Car::q()->find_one(2);
        $this->assertNotNull($car);

        $car->delete();

        $this->assertNull(Car::q()->find_one(2));
    }

    public function testSaveInvalidatesTheQueryCache()
    {
        $car = Car::q()
            ->where('id', 2)
            ->find_one();
        $car->enabled = 0;
        $car->save();

        $car = Car::q()
            ->where('id', 2)
            ->find_one();
        $this->assertSame(0, (int) $car->enabled);
    }

    public function testRawExecuteOfAWriteInvalidatesTheQueryCache()
    {
        $car = Car::q()
            ->where('id', 2)
            ->find_one();
        $this->assertSame(1, (int) $car->enabled);

        ORM::raw_execute('UPDATE `car` SET `enabled` = 0 WHERE `id` = 2');

        $car = Car::q()
            ->where('id', 2)
            ->find_one();
        $this->assertSame(0, (int) $car->enabled);
    }

    public function testAWriteLeavesTheOtherConnectionsCache()
    {
        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget1')
            ->find_one();
        $statements = count(ORM::get_query_log(self::ALTERNATE));

        Car::q()
            ->where('id', 2)
            ->update_many(['enabled' => 0]);

        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget1')
            ->find_one();
        $this->assertCount($statements, ORM::get_query_log(self::ALTERNATE));
    }
}
