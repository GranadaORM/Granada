<?php

use Granada\Granada;
use Granada\ORM;

/**
 * Row writes and aggregates, against a real SQLite engine.
 */
class RowWriteTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('return_result_sets', false);
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::set_db(null);
    }

    private function widget(int $id): ?ORM
    {
        return Granada::for_table('widget')->find_one($id);
    }

    /**
     * A handle holding the shared schema that counts what reaches it.
     */
    private function counting_connection(): CountingPDO
    {
        $connection = new CountingPDO('sqlite::memory:');
        $connection->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::set_db($connection);

        return $connection;
    }

    public function testInsertWritesTheRowAndSetsItsId()
    {
        $widget          = ORM::_new_row('widget')->create();
        $widget->name    = 'Widget6';
        $widget->enabled = 0;
        $widget->save();

        $this->assertSame(6, (int) $widget->id);
        $stored = $this->widget(6);
        $this->assertSame('Widget6', $stored->name);
        $this->assertSame(0, (int) $stored->enabled);
    }

    public function testInsertWritesAnExpressionAsSql()
    {
        $widget            = ORM::_new_row('widget')->create();
        $widget->name      = 'Widget6';
        $widget->gadget_id = 1;
        $widget->set_expr('gadget_id', '3 + 1');
        $widget->save();

        $this->assertSame(4, (int) $this->widget(6)->gadget_id);
    }

    public function testInsertThroughArrayAccess()
    {
        $widget           = ORM::_new_row('widget')->create();
        $widget['name']   = 'Widget6';
        $widget['hidden'] = 1;
        $widget->save();

        $stored = $this->widget(6);
        $this->assertSame('Widget6', $stored->name);
        $this->assertSame(1, (int) $stored->hidden);
    }

    public function testInsertWritesNull()
    {
        $widget            = ORM::_new_row('widget')->create();
        $widget->name      = 'Widget6';
        $widget->gadget_id = null;
        $widget->save();

        $this->assertNull($this->widget(6)->gadget_id);
    }

    public function testUpdateWritesTheChangedFields()
    {
        $widget         = $this->widget(1);
        $widget->name   = 'Widget9';
        $widget->hidden = 1;
        $widget->save();

        $stored = $this->widget(1);
        $this->assertSame('Widget9', $stored->name);
        $this->assertSame(1, (int) $stored->hidden);
    }

    public function testUpdateLeavesAFieldItDidNotChangeAlone()
    {
        $widget = $this->widget(1);

        // Another writer changes hidden while the loaded row is held.
        Granada::for_table('widget')
            ->where_id_is(1)
            ->update_many(['hidden' => 1]);

        $widget->name = 'Widget9';
        $widget->save();

        $stored = $this->widget(1);
        $this->assertSame('Widget9', $stored->name);
        $this->assertSame(1, (int) $stored->hidden);
    }

    public function testUpdateWithNoChangesWritesNothing()
    {
        $connection = $this->counting_connection();

        $widget       = $this->widget(1);
        $widget->name = 'Widget1';
        $widget->save();

        $this->assertSame(1, $connection->statements);

        $this->assertSame('Widget1', $this->widget(1)->name);
    }

    public function testUpdateWritesAnExpressionAsSql()
    {
        $widget         = $this->widget(1);
        $widget->hidden = 0;
        $widget->set_expr('hidden', 'enabled + 1');
        $widget->save();

        $this->assertSame(2, (int) $this->widget(1)->hidden);
    }

    public function testUpdateWritesOnlyAnExpression()
    {
        $widget = $this->widget(1);
        $widget->set_expr('hidden', 'enabled + 1');
        $widget->save();

        $this->assertSame(2, (int) $this->widget(1)->hidden);
    }

    public function testUpdateWritesSeveralFieldsAtOnce()
    {
        $widget = $this->widget(1);
        $widget->set([
            'name'   => 'Widget9',
            'hidden' => 1,
        ]);
        $widget->save();

        $stored = $this->widget(1);
        $this->assertSame('Widget9', $stored->name);
        $this->assertSame(1, (int) $stored->hidden);
    }

    public function testUpdateWritesSeveralFieldsWithAnExpression()
    {
        $widget = $this->widget(1);
        $widget->set([
            'name'   => 'Widget9',
            'hidden' => 0,
        ]);
        $widget->set_expr([
            'hidden'    => 'enabled + 1',
            'gadget_id' => 'gadget_id',
        ]);
        $widget->save();

        $stored = $this->widget(1);
        $this->assertSame('Widget9', $stored->name);
        $this->assertSame(2, (int) $stored->hidden);
    }

    public function testAFieldSetBackToItsStoredValueIsStillWritten()
    {
        $widget = $this->widget(2);

        $widget->name = 'Other';
        $widget->name = 'Widget2';

        // Another writer changes the row after it was read.
        Granada::for_table('widget')
            ->where_id_is(2)
            ->update_many(['name' => 'Racer']);

        $widget->save();

        $this->assertSame('Widget2', $this->widget(2)->name);
    }

    public function testAPlainValueOverridesAPreviouslySetExpression()
    {
        $widget = $this->widget(1);
        $widget->set([
            'name'   => 'Widget9',
            'hidden' => 0,
        ]);
        $widget->set_expr([
            'hidden'    => 'enabled + 1',
            'gadget_id' => 'gadget_id',
        ]);
        $widget->hidden = 7;
        $widget->save();

        $this->assertSame(7, (int) $this->widget(1)->hidden);
    }

    public function testDeleteRemovesTheRow()
    {
        $widget = $this->widget(1);
        $widget->delete();

        $this->assertNull($this->widget(1));
    }

    public function testDeleteManyRemovesEveryMatchingRow()
    {
        Granada::for_table('widget')
            ->where_equal('gadget_id', 1)
            ->delete_many();

        $this->assertNull($this->widget(1));
        $this->assertNull($this->widget(2));
        $this->assertSame('Widget3', $this->widget(3)->name);
    }

    public function testFindOneReturnsOneRow()
    {
        $widget = Granada::for_table('widget')->find_one();

        $this->assertInstanceOf(ORM::class, $widget);
        $this->assertSame(1, (int) $widget->id);
    }

    public function testFindOneWithAnIdReturnsThatRow()
    {
        $widget = Granada::for_table('widget')->find_one(3);

        $this->assertSame('Widget3', $widget->name);
    }

    public function testCountCountsTheRows()
    {
        $this->assertSame(5, Granada::for_table('widget')->count());
    }

    public function testCountIgnoresAPreviousSelect()
    {
        $this->assertSame(5, Granada::for_table('widget')
            ->select('name')
            ->count());
    }

    public function testMaxReturnsTheLargestValue()
    {
        $this->assertSame(4, Granada::for_table('widget')->max('gadget_id'));
    }

    public function testMinReturnsTheSmallestValue()
    {
        $this->assertSame(1, Granada::for_table('widget')->min('gadget_id'));
    }

    public function testAvgReturnsTheMean()
    {
        // Five widgets hold gadget_id 1, 1, 2, null and 4, so the mean
        // skips the null row.
        $this->assertSame(2, Granada::for_table('widget')->avg('gadget_id'));
    }

    public function testSumAddsTheValues()
    {
        $this->assertSame(8, Granada::for_table('widget')->sum('gadget_id'));
    }

    public function testASubqueryInConditionRunsOnlyTheOuterQuery()
    {
        $logged   = count(ORM::get_query_log());
        $subquery = Granada::for_table('gadget')
            ->select('id')
            ->where('name', 'Gadget1');

        $widgets = Granada::for_table('widget')
            ->where_in('gadget_id', $subquery)
            ->find_many();

        $this->assertSame(['Widget1', 'Widget2'], array_column($widgets, 'name'));
        $this->assertCount($logged + 1, ORM::get_query_log());
    }

    public function testModelInsertWritesTheRow()
    {
        $part        = Part::create();
        $part->name  = 'Part6';
        $part->price = 1.5;
        $part->save();

        $stored = Part::q()->find_one(6);
        $this->assertSame('Part6', $stored->name);
        $this->assertSame(1.5, $stored->price);
    }

    public function testModelUpdateWritesTheChangedFields()
    {
        $part        = Part::q()->find_one(1);
        $part->name  = 'PartSix';
        $part->price = 6.5;
        $part->save();

        $stored = Part::q()->find_one(1);
        $this->assertSame('PartSix', $stored->name);
        $this->assertSame(6.5, $stored->price);
    }

    public function testModelUpdateWithNoChangesWritesNothing()
    {
        $connection = $this->counting_connection();

        $part       = Part::q()->find_one(1);
        $part->name = 'Part1';
        $part->save();

        $this->assertSame(1, $connection->statements);
    }

    public function testModelDeleteRemovesTheRow()
    {
        $part = Part::q()->find_one(1);
        $part->delete();

        $this->assertNull(Part::q()->find_one(1));
    }

    public function testHydrateChainsIntoSave()
    {
        Part::create()
            ->hydrate([
                'name'  => 'Part6',
                'price' => 1.5,
            ])->save();

        $this->assertSame('Part6', Part::q()
            ->find_one(6)
            ->name);
    }

    public function testSetExprChainsIntoSave()
    {
        $part        = Part::create();
        $part->name  = 'Part6';
        $part->price = 1.5;
        $part->set_expr('price', '3 * 2')->save();

        $this->assertSame(6.0, Part::q()
            ->find_one(6)
            ->price);
    }
}
