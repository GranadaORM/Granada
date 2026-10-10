<?php

use Granada\Model;
use Granada\ORM;

/**
 * Writes to $relationships must not lose what is already there.
 * clear_computed_values() drops computed values only.
 */
class RelationshipValuesTest extends \PHPUnit\Framework\TestCase
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

    public function testLazyResultKeptWhenAnotherKeyIsWritten()
    {
        $gadget  = Model::factory('Gadget')->find_one(1);
        $widgets = $gadget->widgets;
        $this->assertSame($widgets, $gadget->relationships['widgets']);

        $gadget->relationships['extra'] = 'value';

        $this->assertSame('value', $gadget->relationships['extra']);
        $this->assertSame($widgets, $gadget->relationships['widgets'], 'Lazy result survives an unrelated write');
        $this->assertSame($widgets, $gadget->widgets);
    }

    public function testListAppendKeepsExistingEntries()
    {
        $gadget  = Model::factory('Gadget')->find_one(1);
        $widgets = $gadget->widgets;

        $gadget->relationships['list']   = ['a'];
        $gadget->relationships['list'][] = 'b';

        $this->assertSame(['a', 'b'], $gadget->relationships['list']);
        $this->assertSame($widgets, $gadget->relationships['widgets'], 'Lazy result survives a list append');
    }

    public function testWholeArrayWriteReplacesAllEntries()
    {
        $gadget = Model::factory('Gadget')->find_one(1);
        $gadget->widgets;
        $this->assertArrayHasKey('widgets', $gadget->relationships);

        $gadget->relationships = ['reset' => 1];

        $this->assertSame(['reset' => 1], $gadget->relationships);
    }

    public function testClearComputedValuesRecomputesLazyResult()
    {
        $gadget = Model::factory('Gadget')->find_one(1);
        $first  = $gadget->widgets;
        $this->assertSame($first, $gadget->widgets);

        $gadget->clear_computed_values();

        $this->assertArrayNotHasKey('widgets', $gadget->relationships);

        $second = $gadget->widgets;
        $this->assertNotSame($first, $second, 'The next read loads the relationship again');
        $this->assertCount(count($first), $second);
        $this->assertSame($first->first()->id, $second->first()->id);
        $this->assertSame($second, $gadget->relationships['widgets']);
    }

    public function testClearComputedValuesChainsIntoPropertyRead()
    {
        $gadget = Model::factory('Gadget')->find_one(1);
        $first  = $gadget->widgets;

        $second = $gadget->clear_computed_values()->widgets;

        $this->assertNotSame($first, $second, 'The chained read loads the relationship again');
        $this->assertCount(count($first), $second);
    }

    public function testClearComputedValuesKeepsEagerResults()
    {
        $gadget = Model::factory('Gadget')->with('widgets')->find_one(1);
        $eager  = $gadget->relationships['widgets'];
        $this->assertSame($eager, $gadget->widgets);

        $gadget->clear_computed_values();

        $this->assertArrayHasKey('widgets', $gadget->relationships, 'Eager results survive the clear');
        $this->assertSame($eager, $gadget->relationships['widgets']);
        $this->assertSame($eager, $gadget->widgets);
    }

    public function testClearComputedValuesRecomputesMissingonceValue()
    {
        $car = Model::factory('Car')->find_one(1);
        $this->assertSame('expensive value', $car->expensiveProperty);
        $this->assertSame(1, $car->missingonceCallCount);

        $car->clear_computed_values();

        $this->assertArrayNotHasKey('expensiveProperty', $car->relationships);
        $this->assertSame('expensive value', $car->expensiveProperty);
        $this->assertSame(2, $car->missingonceCallCount);
    }

    public function testClearComputedValuesKeepsSetValues()
    {
        $car = Model::factory('Car')->find_one(1);

        $car->manufactor                = 'test';
        $car->relationships['external'] = 'kept';

        $car->clear_computed_values();

        $this->assertSame('test', $car->relationships['manufactor'], 'Values routed by set() survive the clear');
        $this->assertSame('kept', $car->relationships['external'], 'Direct writes survive the clear');
    }

    public function testCloneGetsItsOwnStash()
    {
        $gadget = Model::factory('Gadget')->find_one(1);
        $gadget->widgets;
        $clone = clone $gadget;

        $clone->relationships['extra'] = 'value';

        $this->assertArrayNotHasKey('extra', $gadget->relationships);
        $this->assertSame($gadget->widgets, $gadget->relationships['widgets']);
    }
}
