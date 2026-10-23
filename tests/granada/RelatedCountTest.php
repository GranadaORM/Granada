<?php

use Granada\Granada;
use Granada\ORM;

/**
 * Related counts: with_count() and with_exists() put {relation}_count
 * and {relation}_exists on each parent without loading the children.
 */
class RelatedCountTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
    }

    protected function tearDown(): void
    {
        ORM::set_db(null);
    }

    /**
     * The {relation}_count value of each found row, keyed by name.
     *
     * @return array<string, mixed>
     */
    private function counts(mixed $rows, string $alias): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->name] = $row->$alias;
        }

        return $counts;
    }

    public function testWithCountSelectsACorrelatedCount()
    {
        $query = Gadget::q()->with_count('widgets');

        $this->assertSame(
            'SELECT *, ( SELECT COUNT(*) FROM `widget` WHERE `widget`.`enabled` = \'1\' AND `widget`.`hidden` = \'0\''
            . ' AND `widget`.`gadget_id` = `gadget`.`id` ) AS `widgets_count`'
            . ' FROM `gadget` WHERE `gadget`.`hidden` = \'0\' AND `gadget`.`enabled` = \'1\'',
            $query->get_select_query()
        );
    }

    public function testWithCountIsStoredOnEachParent()
    {
        $gadgets = Gadget::q()
            ->with_count('widgets')
            ->find_many();

        $this->assertSame(
            [
                'Gadget1' => 2,
                'Gadget4' => 1,
            ],
            $this->counts($gadgets, 'widgets_count')
        );
    }

    public function testWithCountIsAWholeNumber()
    {
        $gadget = Gadget::q()
            ->with_count('widgets')
            ->find_one(1);

        $this->assertSame(2, $gadget->widgets_count);
    }

    public function testWithCountStoresASetValueNotAColumn()
    {
        $gadget = Gadget::q()
            ->with_count('widgets')
            ->find_one(1);

        $this->assertArrayNotHasKey('widgets_count', $gadget->as_array());
        $this->assertArrayHasKey('widgets_count', $gadget->relationships);

        // Set values survive clearing computed values.
        $gadget->clear_computed_values();
        $this->assertSame(2, $gadget->widgets_count);
    }

    public function testWithCountHonoursAClosureConstraint()
    {
        $gadgets = Gadget::q()
            ->with_count('widgets', function ($query): void {
                $query->where('name', 'Widget1');
            })
            ->find_many();

        $this->assertSame(
            [
                'Gadget1' => 1,
                'Gadget4' => 0,
            ],
            $this->counts($gadgets, 'widgets_count')
        );
    }

    public function testWithCountOnANilRelationCountsZero()
    {
        $empty = Gadget::create([
            'name'    => 'Empty',
            'enabled' => 1,
            'hidden'  => 0,
        ]);
        $empty->save();

        $gadgets = Gadget::q()
            ->with_count('widgets')
            ->find_many();

        $counts = $this->counts($gadgets, 'widgets_count');
        $this->assertSame(0, $counts['Empty']);
    }

    public function testWithCountThroughAHasMany()
    {
        $query = Manufactor::q()->with_count('cars');

        $this->assertSame(
            'SELECT *, ( SELECT COUNT(*) FROM `car` WHERE `car`.`is_deleted` = \'0\''
            . ' AND `car`.`enabled` = \'1\' AND `car`.`manufactor_id` = `manufactor`.`id` ) AS `cars_count`'
            . ' FROM `manufactor`',
            $query->get_select_query()
        );

        // Car5 is soft-deleted and Car6 is disabled, so only Car3 and
        // Car4 count under the relation's own enabled = 1 filter.
        $this->assertSame(
            [
                'Manufactor1' => 2,
                'Manufactor2' => 2,
            ],
            $this->counts($query->find_many(), 'cars_count')
        );
    }

    public function testWithExistsIsStoredAsABoolean()
    {
        $empty = Gadget::create([
            'name'    => 'Empty',
            'enabled' => 1,
            'hidden'  => 0,
        ]);
        $empty->save();

        $gadgets = Gadget::q()
            ->with_exists('widgets')
            ->find_many();

        $exists = $this->counts($gadgets, 'widgets_exists');
        $this->assertSame(
            [
                'Gadget1' => true,
                'Gadget4' => true,
                'Empty'   => false,
            ],
            $exists
        );
        $this->assertIsBool($exists['Empty']);
    }

    public function testWithExistsSelectsAnExistsExpression()
    {
        $query = Gadget::q()->with_exists('widgets');

        $this->assertSame(
            'SELECT *, EXISTS ( SELECT 1 FROM `widget` WHERE `widget`.`enabled` = \'1\' AND `widget`.`hidden` = \'0\''
            . ' AND `widget`.`gadget_id` = `gadget`.`id` ) AS `widgets_exists`'
            . ' FROM `gadget` WHERE `gadget`.`hidden` = \'0\' AND `gadget`.`enabled` = \'1\'',
            $query->get_select_query()
        );
    }

    public function testWithCountSurvivesALazyFind()
    {
        $gadgets = Gadget::q()
            ->with_count('widgets')
            ->find_many_lazy();

        $this->assertSame(
            [
                'Gadget1' => 2,
                'Gadget4' => 1,
            ],
            $this->counts($gadgets, 'widgets_count')
        );
    }

    public function testWithCountOpensNoSecondQuery()
    {
        ORM::configure('logging', true);
        $before = count(ORM::get_query_log());

        Gadget::q()
            ->with_count('widgets')
            ->find_many();

        $after = count(ORM::get_query_log());
        ORM::configure('logging', false);

        $this->assertSame(1, $after - $before);
    }

    public function testWithCountIgnoresTheRelationsOwnSelect()
    {
        // picked_widgets() selects widget.id. The count subquery must
        // select only its own expression, not that column too.
        $query = Gadget::q()->with_count('picked_widgets');

        $this->assertSame(
            'SELECT *, ( SELECT COUNT(*) FROM `widget` WHERE `widget`.`enabled` = \'1\' AND `widget`.`hidden` = \'0\''
            . ' AND `widget`.`gadget_id` = `gadget`.`id` ) AS `picked_widgets_count`'
            . ' FROM `gadget` WHERE `gadget`.`hidden` = \'0\' AND `gadget`.`enabled` = \'1\'',
            $query->get_select_query()
        );

        $this->assertSame(
            [
                'Gadget1' => 2,
                'Gadget4' => 1,
            ],
            $this->counts($query->find_many(), 'picked_widgets_count')
        );
    }

    public function testWithCountOnAThroughRelationshipThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Cannot with_count(): the kits relationship is a has_many_through;'
            . ' existence filters and related counts do not support it'
        );
        Widget::q()->with_count('kits');
    }

    public function testWithExistsOnAModelLessQueryThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Cannot with_exists(): the query has no model to read the widgets relationship from'
        );
        Granada::for_table('gadget')->with_exists('widgets');
    }
}
