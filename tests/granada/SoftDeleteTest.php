<?php

use Granada\Granada;
use Granada\LazyItemCache;
use Granada\ORM;

/**
 * Soft deletes: a model with $_soft_delete set makes delete() keep the
 * row and hide it, and its queries leave soft-deleted rows out.
 */
class SoftDeleteTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('logging', true);
        LazyItemCache::clear();
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
        LazyItemCache::clear();
    }

    public function testSoftDeletedRowsDropOutOfFindsAndCounts()
    {
        $names = [];
        foreach (Gadget::find_many() as $gadget) {
            $names[] = $gadget->name;
        }

        // Gadget3 has hidden = 1; Gadget2 is dropped by the model's
        // own filter (enabled = 1) on top of the hiding.
        $this->assertSame(['Gadget1', 'Gadget4'], $names);
        $this->assertSame(2, Gadget::count());
        $this->assertNull(Gadget::find_one(3));
    }

    public function testDeleteKeepsTheRowButHidesIt()
    {
        $gadget = Gadget::find_one(1);

        $this->assertTrue($gadget->delete());

        $this->assertNull(Gadget::find_one(1));
        $this->assertSame(1, Granada::for_table('gadget')
            ->where('id', 1)
            ->count());
    }

    public function testRestoreBringsTheRowBack()
    {
        $this->assertNull(Gadget::find_one(3));

        $gadget = Gadget::clear_where()->find_one(3);

        $this->assertTrue($gadget->restore());
        $this->assertSame('Gadget3', Gadget::find_one(3)->name);
    }

    public function testDeleteForcedRemovesTheRow()
    {
        $gadget = Gadget::find_one(4);

        $this->assertTrue($gadget->delete_forced());
        $this->assertSame(0, Granada::for_table('gadget')
            ->where('id', 4)
            ->count());
    }

    public function testPlainModelsKeepTheHardDelete()
    {
        $car = Car::find_one(1);

        $this->assertTrue($car->delete());
        $this->assertSame(0, Granada::for_table('car')
            ->where('id', 1)
            ->count());
    }

    public function testDeleteManySoftDeletesMatchingRows()
    {
        $this->assertTrue(Gadget::where('enabled', 1)->delete_many());
        $this->assertStringStartsWith('UPDATE `gadget` SET', ORM::get_last_query());

        // Every gadget still exists; 1 and 4 were soft-deleted by the
        // call, 3 already was.
        $this->assertSame(4, Granada::for_table('gadget')->count());
        $this->assertSame(3, Granada::for_table('gadget')
            ->where('hidden', 1)
            ->count());
    }

    public function testDeleteManyWithoutASoftDeleteModelDeletes()
    {
        $this->assertTrue(Granada::for_table('gadget')
            ->where('enabled', 1)
            ->delete_many());

        $this->assertSame(0, Granada::for_table('gadget')
            ->where('enabled', 1)
            ->count());
        $this->assertSame(1, Granada::for_table('gadget')->count());
    }

    public function testLazyRelationshipsDropSoftDeletedRows()
    {
        $this->assertSame('Gadget1', Widget::find_one(1)
            ->gadget
            ->name);

        // Gadget2 fails the model's own filter (enabled = 1) through the
        // relationship query as well.
        $this->assertNull(Widget::find_one(3)->gadget);

        Gadget::find_one(4)->delete();
        $this->assertNull(Widget::find_one(5)->gadget);
    }

    public function testEagerLoadingDropsSoftDeletedRows()
    {
        Gadget::find_one(4)->delete();

        $widget = Widget::with('gadget')->find_one(5);

        $this->assertNull($widget->relationships['gadget']);
        $this->assertSame('Gadget1', Widget::with('gadget')
            ->find_one(1)
            ->relationships['gadget']
            ->name);
    }

    public function testClearWhereListsTheSoftDeletedRows()
    {
        Gadget::clear_where()
            ->find_one(2)
            ->delete();

        $names = [];
        foreach (Gadget::clear_where()
            ->where('gadget.hidden', 1)
            ->find_many() as $gadget) {
            $names[] = $gadget->name;
        }

        $this->assertSame(['Gadget2', 'Gadget3'], $names);
    }

    public function testRestoreWithoutSoftDeletesReturnsFalse()
    {
        $car = Car::find_one(1);

        $this->assertFalse($car->restore());
    }

    public function testDeleteManyOnJoinedSoftDeleteQueryThrows()
    {
        $query = Gadget::join('widget', ['gadget.id', '=', 'widget.gadget_id']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support joined queries');

        $query->delete_many();
    }

    public function testDeleteOnUnsavedModelWritesNothing()
    {
        $gadget = Gadget::create(['name' => 'Gadget5']);

        $this->assertTrue($gadget->delete());
        $this->assertSame(4, Granada::for_table('gadget')->count());
    }
}
