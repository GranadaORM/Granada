<?php

use Granada\ORM;

/**
 * set_order_many(): numbers the listed rows 1..n on the model's
 * auto-order column, in chunked CASE updates inside one transaction.
 */
class SetOrderManyTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_db();
        ORM::reset_config();
        ORM::set_db(new CountingPDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::reset_db();
        \Granada\LazyItemCache::clear();
    }

    private function raw(string $table, int $id): array
    {
        return \Granada\Granada::for_table($table)
            ->where('id', $id)
            ->find_array()[0];
    }

    private function ordered_row(string $name, int $position): Ordered
    {
        $row = Ordered::create([
            'name'     => $name,
            'position' => $position,
        ]);
        $row->save();

        return $row;
    }

    public function testSetOrderManyNumbersTheListFromOne()
    {
        $a = $this->ordered_row('a', 7);
        $b = $this->ordered_row('b', 8);
        $c = $this->ordered_row('c', 9);

        $result = Ordered::q()->set_order_many([$c->id, $a->id, $b->id]);

        $this->assertTrue($result);
        $this->assertSame(1, (int) $this->raw('ordered', $c->id)['position']);
        $this->assertSame(2, (int) $this->raw('ordered', $a->id)['position']);
        $this->assertSame(3, (int) $this->raw('ordered', $b->id)['position']);
    }

    public function testRowsOutsideTheListKeepTheirPosition()
    {
        $a = $this->ordered_row('a', 5);
        $b = $this->ordered_row('b', 6);
        $c = $this->ordered_row('c', 7);

        Ordered::q()->set_order_many([$c->id]);

        $this->assertSame(1, (int) $this->raw('ordered', $c->id)['position']);
        $this->assertSame(5, (int) $this->raw('ordered', $a->id)['position']);
        $this->assertSame(6, (int) $this->raw('ordered', $b->id)['position']);
    }

    public function testAnUnknownIdIsIgnored()
    {
        $a = $this->ordered_row('a', 5);

        $result = Ordered::q()->set_order_many([$a->id, 99999]);

        $this->assertTrue($result);
        $this->assertSame(1, (int) $this->raw('ordered', $a->id)['position']);
    }

    public function testADuplicateIdKeepsItsLastPosition()
    {
        $a = $this->ordered_row('a', 5);
        $b = $this->ordered_row('b', 6);

        Ordered::q()->set_order_many([$a->id, $b->id, $a->id]);

        $this->assertSame(2, (int) $this->raw('ordered', $b->id)['position']);
        $this->assertSame(3, (int) $this->raw('ordered', $a->id)['position']);
    }

    public function testTheReorderDoesNotChangeUpdatedAt()
    {
        $row = Stamped::create([
            'name'     => 'a',
            'position' => 1,
        ]);
        $row->save();

        // Put the stored updated_at into the past, so the write shows.
        Stamped::q()
            ->where('id', $row->id)
            ->find_one()
            ->set('updated_at', '2001-02-03 04:05:06')
            ->save();

        Stamped::q()->set_order_many([$row->id]);

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame(1, (int) $raw['position']);
        $this->assertSame('2001-02-03 04:05:06', $raw['updated_at']);
    }

    public function testAnEmptyListChangesNothingAndReturnsTrue()
    {
        Ordered::q()->find_one();
        $expected = ORM::get_last_query();

        $result = Ordered::q()
            ->where('id', 1)
            ->set_order_many([]);

        $this->assertTrue($result);
        $this->assertSame($expected, ORM::get_last_query());
    }

    public function testAModelWithNoAutoOrderColumnThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('set_order_many() needs a model that declares $_auto_order_column');

        Part::q()->set_order_many([1]);
    }

    public function testAModelWithAnEmptyColumnNameThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('set_order_many() needs a model that declares $_auto_order_column');

        OrderedOff::q()->set_order_many([1]);
    }

    public function testWhereConditionsNarrowTheWrite()
    {
        $a = $this->ordered_row('a', 5);
        $b = $this->ordered_row('b', 7);

        Ordered::q()
            ->where('name', 'a')
            ->set_order_many([$b->id, $a->id]);

        // Row b is listed first but the where leaves it out
        $this->assertSame(7, (int) $this->raw('ordered', $b->id)['position']);
        $this->assertSame(2, (int) $this->raw('ordered', $a->id)['position']);
    }

    public function testAJoinedQueryThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('set_order_many() does not support joined queries');

        Part::q()
            ->join('car_part', ['car_part.part_id', '=', 'part.id'])
            ->set_order_many([1]);
    }

    public function testALongListNumbersTheSameAsOneChunk()
    {
        $rows = [];
        for ($i = 1; $i <= 600; $i++) {
            $rows[] = [
                'name'     => 'row' . $i,
                'position' => $i,
            ];
        }
        Ordered::q()->insert($rows);
        $ids = array_column(Ordered::q()
            ->order_by_asc('id')
            ->find_array(), 'id');

        Ordered::q()->set_order_many(array_reverse($ids));

        // The list ran in reverse id order, so id order counts down from 600
        $rows = Ordered::q()
            ->order_by_asc('id')
            ->find_array();
        foreach ($rows as $index => $row) {
            $this->assertSame(600 - $index, (int) $row['position']);
        }
    }

    public function testTheReorderRunsChunkedInsideOneTransaction()
    {
        $a          = $this->ordered_row('a', 5);
        $b          = $this->ordered_row('b', 6);
        $c          = $this->ordered_row('c', 7);
        $db         = ORM::get_db();
        $statements = $db->statements;
        $begins     = $db->begins;
        $commits    = $db->commits;

        Ordered::q()->set_order_many([$c->id, $a->id, $b->id]);

        $this->assertSame(1, $db->statements - $statements);
        $this->assertSame(1, $db->begins - $begins);
        $this->assertSame(1, $db->commits - $commits);
    }

    public function testAReorderInsideAnOpenTransactionJoinsIt()
    {
        $a  = $this->ordered_row('a', 5);
        $db = ORM::get_db();

        ORM::beginTransaction();
        $begins = $db->begins;

        Ordered::q()->set_order_many([$a->id]);
        $this->assertTrue($db->inTransaction());
        ORM::commit();

        $this->assertSame(0, $db->begins - $begins);
        $this->assertSame(1, (int) $this->raw('ordered', $a->id)['position']);
    }

    public function testTheStatementBindsItsValues()
    {
        $a = $this->ordered_row('a', 5);
        $b = $this->ordered_row('b', 6);

        Ordered::q()->set_order_many([$b->id, $a->id]);

        $this->assertSame(
            'UPDATE `ordered` SET `position` = CASE `id` WHEN ? THEN ? WHEN ? THEN ?'
            . ' ELSE `position` END WHERE `id` IN (?, ?)',
            ORM::get_last_statement()->queryString,
        );
    }
}
