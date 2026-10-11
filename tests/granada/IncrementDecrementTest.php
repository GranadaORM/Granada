<?php

use Granada\ORM;

class IncrementDecrementTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // The counter tests need a real database.
        // Fresh connections: no handle or setting leaks between tests
        ORM::reset_db();
        ORM::reset_config();

        // Set up SQLite in memory
        ORM::set_db(new PDO('sqlite::memory:'));

        // Create schemas and populate with data
        ORM::get_db()->exec(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'));

        // Enable logging
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::reset_db();
    }

    public function testTwoModelsIncrementingTheSameRowReadBackEightThenNine()
    {
        $counter = Counter::create(['name' => 'a', 'views' => 7]);
        $counter->save();

        $first  = Counter::find_one($counter->id);
        $second = Counter::find_one($counter->id);

        $this->assertSame(8, $first->increment('views'));
        $this->assertSame(9, $second->increment('views'));

        // Each model holds the value its own call read back
        $this->assertSame(8, $first->views);
        $this->assertSame(9, $second->views);

        // The counter column is left clean
        $this->assertFalse($first->is_dirty('views'));
        $this->assertFalse($second->is_dirty('views'));

        $this->assertSame(9, Counter::find_one($counter->id)->views);
    }

    public function testNullColumnStartsAtZero()
    {
        $counter = Counter::create(['name' => 'a']);
        $counter->save();

        $counter = Counter::find_one($counter->id);

        $this->assertSame(1, $counter->increment('views'));

        $this->assertSame(1, Counter::find_one($counter->id)->views);
    }

    public function testDecrementSubtractsTheAmount()
    {
        $counter = Counter::create(['name' => 'a', 'views' => 9]);
        $counter->save();

        $counter = Counter::find_one($counter->id);

        $this->assertSame(7, $counter->decrement('views', 2));
        $this->assertSame(6, $counter->decrement('views'));

        $this->assertSame(6, Counter::find_one($counter->id)->views);
    }

    public function testFloatAmount()
    {
        $counter = Counter::create(['name' => 'a', 'views' => 6]);
        $counter->save();

        $counter = Counter::find_one($counter->id);

        $this->assertSame(6.5, $counter->increment('views', 0.5));

        $this->assertSame(6.5, Counter::find_one($counter->id)->views);
    }

    public function testOtherDirtyFieldsStayPending()
    {
        $counter = Counter::create(['name' => 'a', 'views' => 7]);
        $counter->save();

        $counter       = Counter::find_one($counter->id);
        $counter->name = 'b';

        $counter->increment('views');

        // The counter column is clean; the name change is still pending
        $this->assertFalse($counter->is_dirty('views'));
        $this->assertTrue($counter->is_dirty('name'));

        $counter->save();

        $row = Counter::find_one($counter->id);
        $this->assertSame(8, $row->views);
        $this->assertSame('b', $row->name);
    }

    public function testPendingChangeToTheCounterColumnIsSuperseded()
    {
        $counter = Counter::create(['name' => 'a', 'views' => 7]);
        $counter->save();

        $counter        = Counter::find_one($counter->id);
        $counter->views = 101;

        $this->assertSame(8, $counter->increment('views'));

        // The database adds to the value it holds, not the pending 101
        $this->assertSame(8, $counter->views);
        $this->assertFalse($counter->is_dirty('views'));
        $this->assertSame(8, Counter::find_one($counter->id)->views);
    }

    public function testUnsavedModelCountsInMemoryForTheNextSave()
    {
        $counter = Counter::create(['name' => 'a']);

        $this->assertSame(1, $counter->increment('views'));
        $this->assertSame(1, $counter->views);
        $this->assertTrue($counter->is_dirty('views'));

        $counter->save();

        $this->assertSame(1, Counter::find_one($counter->id)->views);
    }

    public function testVanishedRowReturnsFalse()
    {
        $counter = Counter::create(['name' => 'a', 'views' => 7]);
        $counter->save();

        $counter = Counter::find_one($counter->id);
        Counter::find_one($counter->id)->delete();

        $this->assertFalse($counter->increment('views'));
    }
}
