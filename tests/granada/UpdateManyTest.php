<?php

use Granada\Granada;
use Granada\ORM;
use Granada\Orm\Expression;

/**
 * update_many(): one UPDATE over every row the query matches, with no
 * rows loaded.
 */
class UpdateManyTest extends \PHPUnit\Framework\TestCase
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

    private function raw(string $table, int $id): array
    {
        return Granada::for_table($table)
            ->where('id', $id)
            ->find_array()[0];
    }

    private function isUtcNow(string $value): bool
    {
        return abs(strtotime($value . ' UTC') - time()) < 5;
    }

    public function testUpdateManyWritesAPlainValueToEveryMatchingRow()
    {
        $result = Part::q()
            ->where_lt('price', 12)
            ->update_many(['name' => 'Cheap']);

        $this->assertTrue($result);
        $this->assertSame('Cheap', $this->raw('part', 1)['name']);
        $this->assertSame('Cheap', $this->raw('part', 3)['name']);
        $this->assertSame('Part2', $this->raw('part', 2)['name']);
    }

    public function testUpdateManyWritesSeveralColumns()
    {
        $result = Part::q()
            ->where_lt('price', 12)
            ->update_many([
                'name'  => 'Cheap',
                'price' => 1.5,
            ]);

        $this->assertTrue($result);
        $first = $this->raw('part', 1);
        $this->assertSame('Cheap', $first['name']);
        $this->assertSame(1.5, (float) $first['price']);
        $this->assertSame(1.5, (float) $this->raw('part', 3)['price']);
        $this->assertSame(15.0, (float) $this->raw('part', 2)['price']);
    }

    public function testUpdateManyRunsAnExpressionInTheSQL()
    {
        $result = Part::q()
            ->where_lt('price', 12)
            ->update_many(['price' => new Expression('price + 1')]);

        $this->assertTrue($result);
        $this->assertSame(6.01, (float) $this->raw('part', 1)['price']);
        $this->assertSame(11.95, (float) $this->raw('part', 3)['price']);
        // The row the query did not match stays as it was
        $this->assertSame(15.0, (float) $this->raw('part', 2)['price']);
    }

    public function testUpdateManyConvertsADateThroughTheModelSetter()
    {
        $result = Car::q()
            ->where_lt('id', 3)
            ->update_many(['manufacture_date' => new DateTime('2026-03-15')]);

        $this->assertTrue($result);
        $this->assertSame('2026-03-15', $this->raw('car', 1)['manufacture_date']);
        $this->assertSame('2026-03-15', $this->raw('car', 2)['manufacture_date']);
        $this->assertSame('2023-10-05', $this->raw('car', 3)['manufacture_date']);
    }

    public function testUpdateManyConvertsAMoneyObjectThroughTheModelSetter()
    {
        $result = Part::q()
            ->where_lt('price', 12)
            ->update_many(['price' => new Money(9.99)]);

        $this->assertTrue($result);
        $this->assertSame(9.99, (float) $this->raw('part', 1)['price']);
        $this->assertSame(9.99, (float) $this->raw('part', 3)['price']);
        $this->assertSame(15.0, (float) $this->raw('part', 2)['price']);
    }

    public function testUpdateManyLeavesAnExpressionUnconverted()
    {
        $result = Part::q()
            ->where_lt('price', 12)
            ->update_many(['price' => new Expression('price / 2')]);

        $this->assertTrue($result);
        $this->assertSame(2.505, (float) $this->raw('part', 1)['price']);
        $this->assertSame(5.475, (float) $this->raw('part', 3)['price']);
    }

    public function testUpdateManyWithNoValuesChangesNothingAndReturnsTrue()
    {
        Part::q()->find_one();
        $expected = ORM::get_last_query();

        $result = Part::q()
            ->where('id', 1)
            ->update_many([]);

        $this->assertTrue($result);
        $this->assertSame($expected, ORM::get_last_query());
        $this->assertSame('Part1', $this->raw('part', 1)['name']);
    }

    public function testUpdateManyWithoutConditionsUpdatesEveryRow()
    {
        $result = Part::q()
            ->update_many(['name' => 'Bulk']);

        $this->assertTrue($result);
        $this->assertSame('Bulk', $this->raw('part', 1)['name']);
        $this->assertSame('Bulk', $this->raw('part', 2)['name']);
        $this->assertSame('Bulk', $this->raw('part', 3)['name']);
        $this->assertSame('Bulk', $this->raw('part', 4)['name']);
        $this->assertSame('Bulk', $this->raw('part', 5)['name']);
    }

    public function testUpdateManyThrowsOnAJoinedQuery()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('update_many() does not support joined queries');

        Part::q()
            ->join('car_part', ['car_part.part_id', '=', 'part.id'])
            ->update_many(['name' => 'Cheap']);
    }

    public function testUpdateManyKeepsSoftDeletedRowsHidden()
    {
        $crate = Crate::create(['name' => 'Crate1']);
        $crate->save();
        $kept = CrateExtra::create([
            'crate_id' => $crate->id,
            'name'     => 'Kept',
        ]);
        $kept->save();
        $dropped = CrateExtra::create([
            'crate_id' => $crate->id,
            'name'     => 'Dropped',
        ]);
        $dropped->save();
        $dropped->delete();

        $result = CrateExtra::q()
            ->update_many(['name' => 'Renamed']);

        $this->assertTrue($result);
        $this->assertSame('Renamed', $this->raw('crate_extra', $kept->id)['name']);
        $this->assertSame('Dropped', $this->raw('crate_extra', $dropped->id)['name']);

        // clear_where() lifts the hiding
        CrateExtra::q()
            ->clear_where()
            ->update_many(['name' => 'Renamed']);
        $this->assertSame('Renamed', $this->raw('crate_extra', $dropped->id)['name']);
    }

    public function testUpdateManyNarrowsToASubquerysRows()
    {
        $result = Car::q()
            ->where_id_in(CarPart::q()
                ->where('part_id', 1)
                ->select('car_id'))
            ->update_many(['manufacture_date' => '2026-03-15']);

        $this->assertTrue($result);
        $this->assertSame('2026-03-15', $this->raw('car', 1)['manufacture_date']);
        $this->assertSame('2026-03-15', $this->raw('car', 4)['manufacture_date']);
        $this->assertSame('2025-05-10', $this->raw('car', 5)['manufacture_date']);
        $this->assertSame('2021-12-10', $this->raw('car', 6)['manufacture_date']);
    }

    public function testTableFirstQueryWritesTheValuesAsGiven()
    {
        $result = Granada::for_table('car')
            ->where('id', 1)
            ->update_many(['manufacture_date' => 'Mar 15, 2026']);

        $this->assertTrue($result);
        // No model, so set_manufacture_date() does not reformat the value
        $this->assertSame('Mar 15, 2026', $this->raw('car', 1)['manufacture_date']);
        $this->assertSame('2020-02-01', $this->raw('car', 2)['manufacture_date']);
    }

    public function testUpdateManyReturnsFalseWhenTheWriteFails()
    {
        $db = ORM::get_db();
        Tag::create(['name' => 'one'])->save();
        Tag::create(['name' => 'two'])->save();
        // The write must report false instead of throwing
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $result = Tag::q()
            ->where('name', 'two')
            ->update_many(['name' => 'one']);

        $this->assertFalse($result);
        $this->assertSame('two', $this->raw('tag', 2)['name']);
    }

    public function testUpdateManyWritesTheUpdatedTimestamp()
    {
        $row = Stamped::create([
            'name'       => 'a',
            'updated_at' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        Stamped::q()
            ->where('id', $row->id)
            ->update_many(['views' => 5]);

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame(5, (int) $raw['views']);
        $this->assertTrue($this->isUtcNow($raw['updated_at']), "updated_at was {$raw['updated_at']}");
    }

    public function testAnUpdatedAtInTheValuesStaysAsGiven()
    {
        $row = Stamped::create(['name' => 'a']);
        $row->save();

        Stamped::q()
            ->where('id', $row->id)
            ->update_many([
                'views'      => 5,
                'updated_at' => '2001-02-03 04:05:06',
            ]);

        $this->assertSame('2001-02-03 04:05:06', $this->raw('stamped', $row->id)['updated_at']);
    }

    public function testUpdatingOnlyIgnoredColumnsWritesNoTimestamp()
    {
        $row = StampedChild::create([
            'name'       => 'a',
            'changed_on' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        StampedChild::q()
            ->where('id', $row->id)
            ->update_many(['sort_order' => 3]);

        $raw = $this->raw('stamped_child', $row->id);
        $this->assertSame(3, (int) $raw['sort_order']);
        $this->assertSame('2001-02-03 04:05:06', $raw['changed_on']);

        // A non-ignored column brings the timestamp back
        StampedChild::q()
            ->where('id', $row->id)
            ->update_many([
                'sort_order' => 4,
                'name'       => 'b',
            ]);
        $raw = $this->raw('stamped_child', $row->id);
        $this->assertTrue($this->isUtcNow($raw['changed_on']), "changed_on was {$raw['changed_on']}");
    }
}
