<?php

use Granada\Model;
use Granada\ORM;

/**
 * Relationships whose associated model has a default filter adding two
 * where conditions must load correctly, both lazily and eagerly.
 */
class RelationshipFilterTest extends \PHPUnit\Framework\TestCase
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

    public function testEagerBelongsTo()
    {
        $widget = Widget::with('gadget')->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `enabled` = '1' AND `hidden` = '0' AND `id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `enabled` = '1' AND `hidden` = '0' AND `id` IN ('1')";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertSame('Gadget1', $widget->gadget->name);
    }

    public function testEagerBelongsToFilteredParentGivesNull()
    {
        $widget = Widget::with('gadget')->find_one(3);

        $this->assertNull($widget->relationships['gadget']);
    }

    public function testEagerHasMany()
    {
        $gadgets = Gadget::with('widgets')->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `enabled` = '1' AND `hidden` = '0'";
        $expectedSql[] = "SELECT * FROM `widget` WHERE `enabled` = '1' AND `hidden` = '0' AND `gadget_id` IN ('1', '4')";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertCount(2, $gadgets->first()->relationships['widgets']);
        $this->assertCount(1, $gadgets->last()->relationships['widgets']);
    }

    public function testEagerHasManyWithChainedWhere()
    {
        $gadgets = Gadget::with('named_widgets')->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `enabled` = '1' AND `hidden` = '0'";
        $expectedSql[] = "SELECT * FROM `widget` WHERE `enabled` = '1' AND `hidden` = '0' AND `name` = 'Widget1' AND `gadget_id` IN ('1', '4')";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertCount(1, $gadgets->first()->relationships['named_widgets']);
        $this->assertCount(0, $gadgets->last()->relationships['named_widgets']);
    }

    public function testEagerHasOne()
    {
        $gadgets = Gadget::with('featured')->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `enabled` = '1' AND `hidden` = '0'";
        $expectedSql[] = "SELECT * FROM `widget` WHERE `enabled` = '1' AND `hidden` = '0' AND `gadget_id` IN ('1', '4')";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertSame('Widget1', $gadgets->first()->relationships['featured']->name);
        $this->assertSame('Widget5', $gadgets->last()->relationships['featured']->name);
    }

    public function testEagerHasManyThrough()
    {
        $widgets = Widget::with('kits')->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `enabled` = '1' AND `hidden` = '0'";
        $expectedSql[] = 'SELECT `kit`.*, `kit_widget`.`widget_id` FROM `kit` JOIN `kit_widget` ON `kit`.`id` = `kit_widget`.`kit_id`'
            . " WHERE `enabled` = '1' AND `hidden` = '0' AND `kit_widget`.`widget_id` IN ('1', '2', '3', '4', '5')";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 2);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertCount(1, $widgets->first()->relationships['kits']);
        $this->assertSame('Kit1', $widgets->first()->relationships['kits'][0]->name);
        $this->assertCount(1, $widgets->as_array()[3]->relationships['kits']);
        $this->assertSame('Kit2', $widgets->as_array()[3]->relationships['kits'][0]->name);
    }

    public function testLazyBelongsTo()
    {
        $widget = Widget::find_one(1);
        $widget->gadget;

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `id` = '1' AND `enabled` = '1' AND `hidden` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertSame('Gadget1', $widget->gadget->name);
    }

    public function testLazyBelongsToFilteredParentGivesNull()
    {
        $widget = Widget::find_one(3);

        $this->assertNull($widget->gadget);
    }

    public function testLazyBelongsToNullFkGivesNull()
    {
        $widget = Widget::find_one(4);

        $this->assertNull($widget->gadget);
    }

    public function testLazyHasMany()
    {
        $gadget = Gadget::find_one(1);
        $gadget->widgets;

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `gadget_id` = '1' AND `enabled` = '1' AND `hidden` = '0'";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertCount(2, $gadget->widgets);
    }

    public function testLazyHasManyWithChainedWhere()
    {
        $gadget = Gadget::find_one(1);
        $gadget->named_widgets;

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `gadget_id` = '1' AND `enabled` = '1' AND `hidden` = '0' AND `name` = 'Widget1'";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertCount(1, $gadget->named_widgets);
    }

    public function testLazyHasOne()
    {
        $gadget = Gadget::find_one(1);
        $gadget->featured;

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `gadget_id` = '1' AND `enabled` = '1' AND `hidden` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertSame('Widget1', $gadget->featured->name);
    }

    public function testLazyHasManyThrough()
    {
        $widget = Widget::find_one(1);
        $widget->kits;

        $expectedSql   = [];
        $expectedSql[] = 'SELECT `kit`.* FROM `kit` JOIN `kit_widget` ON `kit`.`id` = `kit_widget`.`kit_id`'
            . " WHERE `kit_widget`.`widget_id` = '1' AND `enabled` = '1' AND `hidden` = '0'";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertCount(1, $widget->kits);
    }

    public function testDirectFindOneExecutes()
    {
        $widget = Widget::find_one(1);
        $gadget = $widget->gadget()->find_one();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `id` = '1' AND `enabled` = '1' AND `hidden` = '0' LIMIT 1";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertInstanceOf(Gadget::class, $gadget);
        $this->assertSame('Gadget1', $gadget->name);
    }

    public function testDirectFindManyExecutes()
    {
        $gadget  = Gadget::find_one(1);
        $widgets = $gadget->widgets()->find_many();

        $this->assertCount(2, $widgets);
        $this->assertSame('Widget1', $widgets->first()->name);
    }

    public function testDirectBuildingCallThenFindMany()
    {
        $gadget  = Gadget::find_one(1);
        $widgets = $gadget->widgets()->where('name', 'Widget1')->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `gadget_id` = '1' AND `enabled` = '1' AND `hidden` = '0' AND `name` = 'Widget1'";

        $fullQueryLog = ORM::get_query_log();
        $actualSql    = array_slice($fullQueryLog, count($fullQueryLog) - 1);

        $this->assertEquals($expectedSql, $actualSql);
        $this->assertCount(1, $widgets);
    }

    public function testDirectCountExecutes()
    {
        $gadget = Gadget::find_one(1);

        $this->assertSame(2, $gadget->widgets()->count());
    }

    public function testDirectPluckExecutes()
    {
        $gadget = Gadget::find_one(1);

        $this->assertSame('Widget1', $gadget->widgets()->pluck('name'));
    }

    public function testDirectDeleteManyExecutes()
    {
        $gadget = Gadget::find_one(1);
        $gadget->widgets()->delete_many();

        $this->assertSame(3, Widget::count());
    }

    public function testRelationshipQueryAsSubquery()
    {
        $gadget = Gadget::find_one(1);

        $this->assertCount(2, Widget::where_id_in($gadget->widgets()->select('id'))->find_many());
    }

    public function testDeclaringRelationshipsStillSetsLegacyProperties()
    {
        $gadget = Gadget::find_one(1);
        $gadget->widgets;
        $this->assertSame('has_many', $gadget->relating);
        $this->assertSame('gadget_id', $gadget->relating_key);
        $this->assertNull($gadget->relating_class);
        $this->assertNull($gadget->relating_table);

        $widget = Widget::find_one(1);
        $widget->gadget;
        $this->assertSame('belongs_to', $widget->relating);
        $this->assertSame('gadget_id', $widget->relating_key);
        $this->assertSame('Gadget', $widget->relating_class);
        $this->assertNull($widget->relating_table);

        $gadget->featured;
        $this->assertSame('has_one', $gadget->relating);
        $this->assertSame('gadget_id', $gadget->relating_key);
        $this->assertSame('Widget', $gadget->relating_class);

        $widget->kits;
        $this->assertSame('has_many_through', $widget->relating);
        $this->assertSame(['widget_id', 'kit_id'], $widget->relating_key);
        $this->assertSame('kit_widget', $widget->relating_table);
    }

    public function testDeprecatedWhereJugglingMethodsStillWork()
    {
        $query = Model::factory('Part')->where('name', 'Part1')->where('price', 5);
        $this->assertSame($query, $query->reset_relation());
        $this->assertSame(
            "SELECT * FROM `part` WHERE `price` = '5'",
            $query->get_select_query()
        );

        $query = Model::factory('Part')->where('name', 'Part1');
        $this->assertSame($query, $query->stash_where());
        $this->assertSame($query, $query->where('price', 5));
        $this->assertSame($query, $query->pop_where());
        $this->assertSame(
            "SELECT * FROM `part` WHERE `price` = '5' AND `name` = 'Part1'",
            $query->get_select_query()
        );
    }
}
