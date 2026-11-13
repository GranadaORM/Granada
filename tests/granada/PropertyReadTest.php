<?php

use Granada\ORM;

/**
 * A property read resolves column values, computed values and relationship
 * loaders. It never runs a method that is not a relationship loader.
 */
class PropertyReadTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
    }

    public function testReadingDeleteLeavesTheRow()
    {
        $car = Car::q()->find_one(1);

        $this->assertNull($car->delete);
        $this->assertNotNull(Car::q()->find_one(1));
    }

    public function testReadingSaveWritesNothing()
    {
        $car                = Car::q()->find_one(1);
        $car->manufactor_id = 2;

        $this->assertNull($car->save);

        $manufactor_id = Car::q()
            ->find_one(1)
            ->manufactor_id;

        $this->assertSame(1, (int) $manufactor_id);
    }

    public function testReadingAsArrayReturnsNull()
    {
        $car = Car::q()->find_one(1);

        $this->assertNull($car->as_array);
    }

    public function testReadingIdWithACustomIdColumnDoesNotCallId()
    {
        $row = KeyedRowCustomId::q()->find_one(7);

        $this->assertNull($row->id);
        $this->assertSame(7, $row->custom_id);
    }

    public function testReadingAPropertyDoesNotCallAModelMethodWithTheSameName()
    {
        $car = Car::create();

        $this->assertNull($car->name);
        $this->assertSame(0, $car->nameMethodCalls);
    }

    public function testABareRelationshipCallReachesTheLoader()
    {
        $car = Car::q()->find_one(1);

        $this->assertSame(1, $car->manufactor()->sum('id'));
    }

    public function testABareRelationshipCallForwardsItsArguments()
    {
        $car = Car::q()->find_one(1);

        $manufactor = $car->manufactor_by_name('Manufactor1');

        $this->assertSame('Manufactor1', $manufactor->find_one()->name);
    }
}
