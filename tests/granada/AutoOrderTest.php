<?php

use Granada\ORM;

/**
 * Auto order: a save that writes 0 to the model's order column stores
 * the next number in the table instead.
 */
class AutoOrderTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_db();
        ORM::reset_config();
        ORM::set_db(new PDO('sqlite::memory:'));
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

    public function testAZeroOnANewRowStoresTheNextNumber()
    {
        Ordered::create([
            'name'     => 'a',
            'position' => 1,
        ])->save();
        Ordered::create([
            'name'     => 'b',
            'position' => 2,
        ])->save();

        $row = Ordered::create([
            'name'     => 'c',
            'position' => 0,
        ]);
        $row->save();

        $this->assertSame(3, (int) $this->raw('ordered', $row->id)['position']);
    }

    public function testTheFirstZeroOnAnEmptyTableStoresOne()
    {
        $row = Ordered::create([
            'name'     => 'a',
            'position' => 0,
        ]);
        $row->save();

        $this->assertSame(1, (int) $this->raw('ordered', $row->id)['position']);
    }

    public function testAnUpdateWritingZeroStoresTheNextNumber()
    {
        Ordered::create([
            'name'     => 'a',
            'position' => 1,
        ])->save();
        $b = Ordered::create([
            'name'     => 'b',
            'position' => 2,
        ]);
        $b->save();

        $row           = Ordered::q()->find_one($b->id);
        $row->position = 0;
        $row->save();

        $this->assertSame(3, (int) $this->raw('ordered', $b->id)['position']);
        $this->assertSame(1, (int) $this->raw('ordered', 1)['position']);
    }

    public function testAZeroWriteStoresZeroWhenTheModelDeclaresNoColumn()
    {
        StampedChild::create([
            'name'       => 'a',
            'sort_order' => 5,
        ])->save();

        $row = StampedChild::create([
            'name'       => 'b',
            'sort_order' => 0,
        ]);
        $row->save();

        $this->assertSame(0, (int) $this->raw('stamped_child', $row->id)['sort_order']);
    }

    public function testAZeroWriteStoresZeroWhenTheModelDeclaresAnEmptyColumn()
    {
        OrderedOff::create([
            'name'     => 'a',
            'position' => 5,
        ])->save();

        $row = OrderedOff::create([
            'name'     => 'b',
            'position' => 0,
        ]);
        $row->save();

        $this->assertSame(0, (int) $this->raw('ordered_off', $row->id)['position']);
    }

    public function testANonZeroValueStoresUnchanged()
    {
        Ordered::create([
            'name'     => 'a',
            'position' => 1,
        ])->save();
        Ordered::create([
            'name'     => 'b',
            'position' => 2,
        ])->save();

        $row = Ordered::create([
            'name'     => 'c',
            'position' => 7,
        ]);
        $row->save();

        $half = Ordered::create([
            'name'     => 'd',
            'position' => 0.5,
        ]);
        $half->save();

        $this->assertSame(7, (int) $this->raw('ordered', $row->id)['position']);
        $this->assertSame(0.5, $this->raw('ordered', $half->id)['position']);
    }

    public function testTheAssignedNumberDoesNotWriteUpdatedAt()
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

        $row           = Stamped::q()->find_one($row->id);
        $row->position = 0;
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame(2, (int) $raw['position']);
        $this->assertSame('2001-02-03 04:05:06', $raw['updated_at']);
    }

    public function testABulkInsertWithZerosNumbersConsecutively()
    {
        Ordered::q()->insert([
            [
                'name'     => 'x',
                'position' => 0,
            ],
            [
                'name'     => 'y',
                'position' => 0,
            ],
        ]);

        $rows = \Granada\Granada::for_table('ordered')
            ->order_by_asc('id')
            ->find_array();
        $this->assertCount(2, $rows);
        $this->assertSame(1, (int) $rows[0]['position']);
        $this->assertSame(2, (int) $rows[1]['position']);
    }
}
