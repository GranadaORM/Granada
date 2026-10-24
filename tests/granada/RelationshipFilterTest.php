<?php

use Granada\Model;
use Granada\ORM;
use Granada\Orm\ConnectionManager;

/**
 * Relationships whose associated model has a default filter adding two
 * where conditions must load correctly, both lazily and eagerly.
 */
class RelationshipFilterTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_connection_manager(new ConnectionManager('sqlite::memory:'));
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
        $widget = Widget::q()
            ->with('gadget')
            ->find_one(1);

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `widget`.`enabled` = '1' AND `widget`.`hidden` = '0' AND `widget`.`id` = '1' LIMIT 1";
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `gadget`.`hidden` = '0' AND `gadget`.`enabled` = '1' AND `gadget`.`id` IN ('1')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertSame('Gadget1', $widget->gadget->name);
    }

    public function testEagerBelongsToFilteredParentGivesNull()
    {
        $widget = Widget::q()
            ->with('gadget')
            ->find_one(3);

        $this->assertNull($widget->relationships['gadget']);
    }

    public function testEagerHasMany()
    {
        $gadgets = Gadget::q()
            ->with('widgets')
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `gadget`.`hidden` = '0' AND `gadget`.`enabled` = '1'";
        $expectedSql[] = "SELECT * FROM `widget` WHERE `widget`.`enabled` = '1' AND `widget`.`hidden` = '0' AND `widget`.`gadget_id` IN ('1', '4')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertCount(2, $gadgets->first()->relationships['widgets']);
        $this->assertCount(1, $gadgets->last()->relationships['widgets']);
    }

    public function testEagerHasManyWithChainedWhere()
    {
        $gadgets = Gadget::q()
            ->with('named_widgets')
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `gadget`.`hidden` = '0' AND `gadget`.`enabled` = '1'";
        $expectedSql[] = "SELECT * FROM `widget` WHERE `widget`.`enabled` = '1' AND `widget`.`hidden` = '0' AND `widget`.`name` = 'Widget1' AND `widget`.`gadget_id` IN ('1', '4')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertCount(1, $gadgets->first()->relationships['named_widgets']);
        $this->assertCount(0, $gadgets->last()->relationships['named_widgets']);
    }

    public function testEagerHasOne()
    {
        $gadgets = Gadget::q()
            ->with('featured')
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `gadget` WHERE `gadget`.`hidden` = '0' AND `gadget`.`enabled` = '1'";
        $expectedSql[] = "SELECT * FROM `widget` WHERE `widget`.`enabled` = '1' AND `widget`.`hidden` = '0' AND `widget`.`gadget_id` IN ('1', '4')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertSame('Widget1', $gadgets->first()
            ->relationships['featured']
            ->name);
        $this->assertSame('Widget5', $gadgets->last()
            ->relationships['featured']
            ->name);
    }

    public function testEagerHasManyThrough()
    {
        $widgets = Widget::q()
            ->with('kits')
            ->find_many();

        $expectedSql   = [];
        $expectedSql[] = "SELECT * FROM `widget` WHERE `widget`.`enabled` = '1' AND `widget`.`hidden` = '0'";
        $expectedSql[] = 'SELECT `kit`.*, `kit_widget`.`widget_id` FROM `kit` JOIN `kit_widget` ON `kit`.`id` = `kit_widget`.`kit_id`'
            . " WHERE `kit`.`enabled` = '1' AND `kit`.`hidden` = '0' AND `kit_widget`.`widget_id` IN ('1', '2', '3', '4', '5')";

        $this->assertEquals($expectedSql, ORM::get_query_log());
        $this->assertCount(1, $widgets->first()->relationships['kits']);
        $this->assertSame('Kit1', $widgets->first()
            ->relationships['kits'][0]
            ->name);
        $this->assertCount(1, $widgets->as_array()[3]->relationships['kits']);
        $this->assertSame('Kit2', $widgets->as_array()[3]
            ->relationships['kits'][0]
            ->name);
    }

    public function testLazyBelongsTo()
    {
        $widget = Widget::q()->find_one(1);
        $widget->gadget;

        $statement = $widget->gadget()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `gadget` WHERE `gadget`.`id` = ? AND `gadget`.`hidden` = ? AND `gadget`.`enabled` = ? LIMIT 1', $statement->query);
        $this->assertSame([1, 0, 1], $statement->values);
        $this->assertSame('Gadget1', $widget->gadget->name);
    }

    public function testLazyBelongsToFilteredParentGivesNull()
    {
        $widget = Widget::q()->find_one(3);

        $this->assertNull($widget->gadget);
    }

    public function testLazyBelongsToNullFkGivesNull()
    {
        $widget = Widget::q()->find_one(4);

        $this->assertNull($widget->gadget);
    }

    public function testLazyHasMany()
    {
        $gadget = Gadget::q()->find_one(1);
        $gadget->widgets;

        $statement = $gadget->widgets()->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`gadget_id` = ? AND `widget`.`enabled` = ? AND `widget`.`hidden` = ?', $statement->query);
        $this->assertSame([1, 1, 0], $statement->values);
        $this->assertCount(2, $gadget->widgets);
    }

    public function testLazyHasManyWithChainedWhere()
    {
        $gadget = Gadget::q()->find_one(1);
        $gadget->named_widgets;

        $statement = $gadget->named_widgets()->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`gadget_id` = ? AND `widget`.`enabled` = ? AND `widget`.`hidden` = ? AND `widget`.`name` = ?', $statement->query);
        $this->assertSame([1, 1, 0, 'Widget1'], $statement->values);
        $this->assertCount(1, $gadget->named_widgets);
    }

    public function testLazyHasOne()
    {
        $gadget = Gadget::q()->find_one(1);
        $gadget->featured;

        $statement = $gadget->featured()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`gadget_id` = ? AND `widget`.`enabled` = ? AND `widget`.`hidden` = ? LIMIT 1', $statement->query);
        $this->assertSame([1, 1, 0], $statement->values);
        $this->assertSame('Widget1', $gadget->featured->name);
    }

    public function testLazyHasManyThrough()
    {
        $widget = Widget::q()->find_one(1);
        $widget->kits;

        $statement = $widget->kits()->to_statement();

        $this->assertSame('SELECT `kit`.* FROM `kit` JOIN `kit_widget` ON `kit`.`id` = `kit_widget`.`kit_id` WHERE `kit_widget`.`widget_id` = ? AND `kit`.`enabled` = ? AND `kit`.`hidden` = ?', $statement->query);
        $this->assertSame([1, 1, 0], $statement->values);
        $this->assertCount(1, $widget->kits);
    }

    public function testDirectFindOneExecutes()
    {
        $widget = Widget::q()->find_one(1);
        $gadget = $widget->gadget()->find_one();

        $statement = $widget->gadget()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `gadget` WHERE `gadget`.`id` = ? AND `gadget`.`hidden` = ? AND `gadget`.`enabled` = ? LIMIT 1', $statement->query);
        $this->assertSame([1, 0, 1], $statement->values);
        $this->assertInstanceOf(Gadget::class, $gadget);
        $this->assertSame('Gadget1', $gadget->name);
    }

    public function testDirectFindManyExecutes()
    {
        $gadget  = Gadget::q()->find_one(1);
        $widgets = $gadget->widgets()->find_many();

        $this->assertCount(2, $widgets);
        $this->assertSame('Widget1', $widgets->first()->name);
    }

    public function testDirectBuildingCallThenFindMany()
    {
        $gadget  = Gadget::q()->find_one(1);
        $widgets = $gadget->widgets()
            ->where('name', 'Widget1')
            ->find_many();

        $statement = $gadget->widgets()
            ->where('name', 'Widget1')
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`gadget_id` = ? AND `widget`.`enabled` = ? AND `widget`.`hidden` = ? AND `widget`.`name` = ?', $statement->query);
        $this->assertSame([1, 1, 0, 'Widget1'], $statement->values);
        $this->assertCount(1, $widgets);
    }

    public function testDirectCountExecutes()
    {
        $gadget = Gadget::q()->find_one(1);

        $this->assertSame(2, $gadget->widgets()->count());
    }

    public function testDirectPluckExecutes()
    {
        $gadget = Gadget::q()->find_one(1);

        $this->assertSame('Widget1', $gadget->widgets()->pluck('name'));
    }

    public function testDirectDeleteManyExecutes()
    {
        $gadget = Gadget::q()->find_one(1);
        $gadget->widgets()->delete_many();

        $this->assertSame(3, Widget::q()->count());
    }

    public function testRelationshipQueryAsSubquery()
    {
        $gadget = Gadget::q()->find_one(1);

        $this->assertCount(2, Widget::q()
            ->where_id_in($gadget->widgets()->select('id'))
            ->find_many());
    }
}
