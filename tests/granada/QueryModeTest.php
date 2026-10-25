<?php

use Granada\Granada;
use Granada\ORM;
use Granada\Query;

/**
 * The query/data split: q() and the other entry points return a
 * Query, the per-model query class resolves and applies its
 * defaultFilter(), and a query refuses row work.
 */
class QueryModeTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('return_result_sets', true);
    }

    protected function tearDown(): void
    {
        ORM::set_db(null);
    }

    public function testQReturnsTheModelsQuery()
    {
        $this->assertSame(
            "SELECT * FROM `car` WHERE `car`.`is_deleted` = '0'",
            Car::q()->get_select_query()
        );
    }

    public function testQOnAnInstanceIsTableWide()
    {
        $car = Car::q()->find_one(1);
        $this->assertInstanceOf(Query::class, $car->q());

        // The count covers the whole table: five rows, not the found row.
        $this->assertSame(5, $car->q()->count());
    }

    public function testForTableIsModelLess()
    {
        $query = Granada::for_table('car');
        $this->assertInstanceOf(Query::class, $query);

        $row = $query->find_one(1);
        $this->assertInstanceOf(ORM::class, $row);
        $this->assertSame('Car1', $row->name);

        // No model class, so no default filter
        $this->assertSame(
            'SELECT * FROM `car`',
            Granada::for_table('car')->get_select_query()
        );
    }

    public function testSetClassNameHydratesIntoThatClass()
    {
        $row = Granada::for_table('car')
            ->set_class_name(Car::class)
            ->find_one(1);

        $this->assertInstanceOf(Car::class, $row);
        $this->assertSame('Car1', $row->name);
    }

    public function testQueryClassResolvesQueryThenBaseQueryThenQuery()
    {
        $this->assertSame(QueryResolutionBoth::class, ResolutionBoth::q()::class);
        $this->assertSame(BaseQueryResolutionBaseOnly::class, ResolutionBaseOnly::q()::class);
        $this->assertSame(Query::class, ResolutionNone::q()::class);
    }

    public function testResolutionAppliesToRelationshipAndEagerQueries()
    {
        $gadget = Gadget::q()->find_one(1);

        // A chained relation method resolves the query class, and the
        // eager load hydrates its rows into that model.
        $query = $gadget->base_resolutions()
            ->where('name', 'Res1');
        $this->assertSame(BaseQueryResolutionBaseOnly::class, $query::class);

        $loaded = Gadget::q()
            ->with('base_resolutions')
            ->find_one(1)
            ->base_resolutions;
        $this->assertInstanceOf(ResolutionBaseOnly::class, $loaded->first());
        $this->assertSame(['Res1', 'Res2'], array_values(array_map(
            fn($row) => $row->name,
            $loaded->as_array()
        )));
    }

    public function testDefaultFilterAppliesAtConstruction()
    {
        // Gadget's defaultFilter() adds enabled = 1 beside the
        // soft-delete hiding core Query applies.
        $this->assertSame(
            "SELECT * FROM `gadget` WHERE `gadget`.`hidden` = '0' AND `gadget`.`enabled` = '1'",
            Gadget::q()->get_select_query()
        );
        $gadgets = Gadget::q()
            ->find_many()
            ->as_array();
        $this->assertSame(['Gadget1', 'Gadget4'], array_values(array_map(
            fn($gadget) => $gadget->name,
            $gadgets
        )));
    }

    public function testClearWhereClearsTheDefaultFilterAndTheHiding()
    {
        $this->assertSame(
            'SELECT * FROM `gadget`',
            Gadget::q()
                ->clear_where()
                ->get_select_query()
        );
    }

    public function testDefaultFilterAppliesToRelationshipQueries()
    {
        $gadget = Gadget::q()->find_one(1);

        $this->assertSame(
            ['Widget1', 'Widget2'],
            array_values(array_map(fn($widget) => $widget->name, $gadget->widgets->as_array()))
        );
    }

    public function testAQueryRefusesRowWrites()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method ' . QueryCar::class . '::save()');
        Car::q()->save();
    }

    public function testAQueryHoldsNoRowData()
    {
        // The warning names the class and property; no row data comes out.
        $reported = null;
        set_error_handler(function (int $number, string $message) use (&$reported): bool {
            $reported = $message;

            return true;
        });
        $value = Car::q()->name;
        restore_error_handler();

        $this->assertSame('Undefined property: ' . QueryCar::class . '::$name', $reported);
        $this->assertNull($value);
    }

    public function testAQueryRefusesSettingARow()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method ' . QueryCar::class . '::set()');
        Car::q()->set('name', 'x');
    }

    public function testCreateReturnsAnUnsavedSeededRow()
    {
        $car = Car::create(['name' => 'NewCar']);
        $this->assertInstanceOf(Car::class, $car);
        $this->assertTrue($car->is_new());
        $this->assertNull($car->id());

        // The name went through the model's set_name hook at seeding
        $this->assertSame('test', $car->name);

        $car->save();
        // The new row has no soft-delete flag yet, so the model's
        // filter hides it; model-less finds see it.
        $this->assertSame(5, Car::q()->count());
        $this->assertSame(7, Granada::for_table('car')->count());
    }

    public function testForTableRefusesRowCreation()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method ' . Query::class . '::create()');
        Granada::for_table('car')->create();
    }
}
