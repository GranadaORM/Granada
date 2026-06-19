<?php

use Granada\ORM;
use Granada\LazyItemCache;

class LazyItemCacheTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('logging', true);
        LazyItemCache::clear();
        LazyItemCache::setMax(500);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
        LazyItemCache::clear();
    }

    public function testBelongsToRelationIsCachedOnSecondAccess()
    {
        $car = Car::find_one(1);
        $manufactor1 = $car->manufactor;
        $manufactor2 = $car->manufactor;

        $this->assertSame($manufactor1, $manufactor2);
    }

    public function testBelongsToRelationCachedAcrossDifferentParentInstances()
    {
        $car1 = Car::find_one(1);
        $manufactor1 = $car1->manufactor;

        $car2 = Car::find_one(2);
        $manufactor2 = $car2->manufactor;

        $this->assertSame($manufactor1, $manufactor2);
    }

    public function testBelongsToDifferentIdsReturnDifferentInstances()
    {
        $car1 = Car::find_one(1);
        $manufactor1 = $car1->manufactor;

        $car3 = Car::find_one(3);
        $manufactor2 = $car3->manufactor;

        $this->assertNotSame($manufactor1, $manufactor2);
    }

    public function testHasOneRelationIsCachedOnSecondAccess()
    {
        $owner = Owner::find_one(1);
        $car1 = $owner->car;
        $car2 = $owner->car;

        $this->assertSame($car1, $car2);
    }

    public function testHasManyRelationNotCachedInLazyItemCache()
    {
        $manufactor = Manufactor::find_one(1);
        $manufactor->cars;

        $this->assertEquals(0, LazyItemCache::size());
    }

    public function testHasManyThroughRelationNotCachedInLazyItemCache()
    {
        $car = Car::find_one(1);
        $car->parts;

        $this->assertEquals(0, LazyItemCache::size());
    }

    public function testClearLazyItemCacheForcesRequery()
    {
        $car1 = Car::find_one(1);
        $manufactor1 = $car1->manufactor;

        $this->assertEquals(1, LazyItemCache::size());

        LazyItemCache::clear();

        $this->assertEquals(0, LazyItemCache::size());

        $car2 = Car::find_one(2);
        $manufactor2 = $car2->manufactor;

        $this->assertNotSame($manufactor1, $manufactor2);
        $this->assertEquals(1, LazyItemCache::size());
    }

    public function testLazyItemCacheMaxEvictsOldestEntries()
    {
        LazyItemCache::setMax(2);

        $car_1 = Car::find_one(1);
        $car_1->manufactor;

        $car_2 = Car::find_one(2);
        $car_2->manufactor;

        $car_3 = Car::find_one(3);
        $car_3->manufactor;

        $this->assertEquals(2, LazyItemCache::size());

        LazyItemCache::clear();
        $car_4 = Car::find_one(4);
        $manufactor4 = $car_4->manufactor;

        $this->assertNotNull($manufactor4);
    }

    public function testLazyItemCacheSizeTracksCorrectly()
    {
        $this->assertEquals(0, LazyItemCache::size());

        $car_1 = Car::find_one(1);
        $car_1->manufactor;

        $this->assertEquals(1, LazyItemCache::size());

        $car_3 = Car::find_one(3);
        $car_3->manufactor;

        $this->assertEquals(2, LazyItemCache::size());
    }

    public function testRelatingClassSetOnHasOne()
    {
        $owner = Owner::find_one(1);
        $owner->car;

        $this->assertEquals('has_one', $owner->relating);
        $this->assertEquals('Car', $owner->relating_class);
    }

    public function testRelatingClassSetOnBelongsTo()
    {
        $car = Car::find_one(1);
        $car->manufactor;

        $this->assertEquals('belongs_to', $car->relating);
        $this->assertEquals('Manufactor', $car->relating_class);
    }

    public function testBelongsToWithCustomFkColumnCached()
    {
        $car = Car::find_one(1);
        $owner1 = $car->owner;
        $owner2 = $car->owner;

        $this->assertSame($owner1, $owner2);
    }

    public function testOwnerRelationshipIsCachedAcrossCars()
    {
        $car4 = Car::find_one(4);
        $owner1 = $car4->owner;

        $car6 = Car::find_one(6);
        $owner2 = $car6->owner;

        $this->assertSame($owner1, $owner2);
    }

    public function testSaveInvalidatesCacheEntry()
    {
        $car1 = Car::find_one(1);
        $manufactor1 = $car1->manufactor;
        $this->assertEquals('Manufactor1', $manufactor1->name);
        $this->assertEquals(1, LazyItemCache::size());

        $manufactor1->name = 'UpdatedManufactor';
        $manufactor1->save();

        $this->assertEquals(0, LazyItemCache::size());

        $car2 = Car::find_one(2);
        $manufactor2 = $car2->manufactor;

        $this->assertEquals('UpdatedManufactor', $manufactor2->name);
        $this->assertNotSame($manufactor1, $manufactor2);
    }

    public function testBelongsToWithNullFkReturnsNull()
    {
        $car = Car::create([
            'name'          => 'NoMfg',
            'manufactor_id' => null,
            'owner_id'      => null,
            'enabled'       => 1,
        ]);
        $this->assertNull($car->manufactor);
        $this->assertNull($car->owner);
        $this->assertEquals(0, LazyItemCache::size());
    }

    public function testDeleteInvalidatesCacheEntry()
    {
        $car_1 = Car::find_one(1);
        $manufactor = $car_1->manufactor;
        $this->assertEquals('Manufactor1', $manufactor->name);
        $this->assertEquals(1, LazyItemCache::size());

        $manufactor->delete();

        $this->assertEquals(0, LazyItemCache::size());

        $car_2 = Car::find_one(2);
        $manufactor2 = $car_2->manufactor;

        $this->assertNull($manufactor2);
    }

    public function testDeleteManyClearsCache()
    {
        $car_1 = Car::find_one(1);
        $manufactor = $car_1->manufactor;
        $this->assertNotNull($manufactor);
        $this->assertEquals(1, LazyItemCache::size());

        Manufactor::where('id', 10)->delete_many();

        $this->assertEquals(0, LazyItemCache::size());
    }
}
