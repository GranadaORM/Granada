<?php

use Granada\Granada;
use Granada\ORM;

/**
 * Existence filters: where_has() and where_not_has() keep parents by
 * their related rows, one parent row per match.
 */
class ExistenceFilterTest extends \PHPUnit\Framework\TestCase
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
     * @return string[]
     */
    private function names(mixed $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $names[] = $row->name;
        }

        return $names;
    }

    public function testWhereHasCompilesToExists()
    {
        $query = Gadget::q()->where_has('widgets');

        $this->assertSame(
            'SELECT * FROM `gadget` WHERE `gadget`.`hidden` = \'0\' AND `enabled` = \'1\''
            . ' AND EXISTS ( SELECT 1 FROM `widget` WHERE `enabled` = \'1\' AND `hidden` = \'0\''
            . ' AND `widget`.`gadget_id` = `gadget`.`id` )',
            $query->get_select_query()
        );
    }

    public function testWhereHasKeepsParentsWithARelatedRow()
    {
        $gadgets = Gadget::q()
            ->where_has('widgets')
            ->find_many();

        $this->assertSame(['Gadget1', 'Gadget4'], $this->names($gadgets));
    }

    public function testWhereHasStaysOneRowPerParent()
    {
        // Gadget1 has two matching widgets; it is still one row.
        $query = Gadget::q()->where_has('widgets');

        $this->assertSame(2, $query->count());
    }

    public function testWhereHasArrayNarrowsTheRelatedRows()
    {
        $gadgets = Gadget::q()
            ->where_has('widgets', ['name' => 'Widget1'])
            ->find_many();

        $this->assertSame(['Gadget1'], $this->names($gadgets));
    }

    public function testWhereHasArrayNullValueMeansIsNull()
    {
        // No widget has a null name.
        $gadgets = Gadget::q()
            ->where_has('widgets', ['name' => null])
            ->find_many();

        $this->assertSame([], $this->names($gadgets));
    }

    public function testWhereHasClosureReceivesTheRelatedQuery()
    {
        $gadgets = Gadget::q()
            ->where_has('widgets', function ($query): void {
                $query->where('name', 'Widget2');
            })
            ->find_many();

        $this->assertSame(['Gadget1'], $this->names($gadgets));
    }

    public function testWhereHasAtLeastNCompilesToACount()
    {
        $query = Gadget::q()->where_has('widgets', null, 2);

        $this->assertSame(
            'SELECT * FROM `gadget` WHERE `gadget`.`hidden` = \'0\' AND `enabled` = \'1\''
            . ' AND ( SELECT COUNT(*) FROM `widget` WHERE `enabled` = \'1\' AND `hidden` = \'0\''
            . ' AND `widget`.`gadget_id` = `gadget`.`id` ) >= 2',
            $query->get_select_query()
        );
    }

    public function testWhereHasAtLeastNKeepsOnlyParentsWithEnoughRows()
    {
        $gadgets = Gadget::q()
            ->where_has('widgets', null, 2)
            ->find_many();

        $this->assertSame(['Gadget1'], $this->names($gadgets));
    }

    public function testWhereHasAtLeastNIgnoresTheRelationsOwnSelect()
    {
        // picked_widgets() selects widget.id; the count subquery must
        // select only COUNT(*).
        $query = Gadget::q()->where_has('picked_widgets', null, 2);

        $this->assertSame(
            'SELECT * FROM `gadget` WHERE `gadget`.`hidden` = \'0\' AND `enabled` = \'1\''
            . ' AND ( SELECT COUNT(*) FROM `widget` WHERE `enabled` = \'1\' AND `hidden` = \'0\''
            . ' AND `widget`.`gadget_id` = `gadget`.`id` ) >= 2',
            $query->get_select_query()
        );

        $this->assertSame(['Gadget1'], $this->names($query->find_many()));
    }

    public function testWhereNotHasCompilesToNotExists()
    {
        $empty = Gadget::create([
            'name'    => 'Empty',
            'enabled' => 1,
            'hidden'  => 0,
        ]);
        $empty->save();

        $gadgets = Gadget::q()
            ->where_not_has('widgets')
            ->find_many();

        $this->assertSame(['Empty'], $this->names($gadgets));
    }

    public function testWhereHasBelongsToCorrelatesTheForeignKey()
    {
        $query = Car::q()->where_has('manufactor', ['name' => 'Manufactor2']);

        $this->assertSame(
            'SELECT * FROM `car` WHERE `car`.`is_deleted` = \'0\''
            . ' AND EXISTS ( SELECT 1 FROM `manufactor` WHERE `enabled` = \'1\''
            . ' AND `manufactor`.`id` = `car`.`manufactor_id` AND `name` = \'Manufactor2\' )',
            $query->get_select_query()
        );

        $this->assertSame(['Car3', 'Car4', 'Car6'], $this->names($query->find_many()));
    }

    public function testWhereHasHonoursTheRelationsOwnFilter()
    {
        // Manufactor::cars() applies enabled = 1, so Car6 (disabled)
        // does not match its manufactor.
        $manufactors = Manufactor::q()
            ->where_has('cars', ['name' => 'Car6'])
            ->find_many();

        $this->assertSame([], $this->names($manufactors));
    }

    public function testWhereHasRelationSugarMatchesTheNamedMethod()
    {
        $named  = Gadget::q()->where_has('widgets');
        $sugary = Gadget::q()->where_has_widgets();

        $this->assertSame($named->get_select_query(), $sugary->get_select_query());

        $gadgets = Gadget::q()
            ->where_has_widgets(['name' => 'Widget1'])
            ->find_many();

        $this->assertSame(['Gadget1'], $this->names($gadgets));
    }

    public function testWhereNotHasRelationSugarMatchesTheNamedMethod()
    {
        $named  = Gadget::q()->where_not_has('widgets');
        $sugary = Gadget::q()->where_not_has_widgets();

        $this->assertSame($named->get_select_query(), $sugary->get_select_query());
    }

    public function testWhereHasOnAThroughRelationshipThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Cannot where_has(): the kits relationship is a has_many_through;'
            . ' existence filters and related counts do not support it'
        );
        Widget::q()->where_has('kits');
    }

    public function testWhereHasOnAModelLessQueryThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Cannot where_has(): the query has no model to read the manufactor relationship from'
        );
        Granada::for_table('car')->where_has('manufactor');
    }

    public function testWhereHasOnAnUnknownRelationshipThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot where_has(): Car has no nonsense relationship');
        Car::q()->where_has('nonsense');
    }
}
