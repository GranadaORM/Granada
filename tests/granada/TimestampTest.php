<?php

use Granada\ORM;

/**
 * Automatic timestamps: a model that turns them on has created_at and
 * updated_at written in UTC on every write path.
 */
class TimestampTest extends \PHPUnit\Framework\TestCase
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

    private function isUtcNow(string $value): bool
    {
        return abs(strtotime($value . ' UTC') - time()) < 5;
    }

    public function testModelWithoutTheSwitchWritesNoTimestamps()
    {
        $row = Unstamped::create(['name' => 'a']);
        $row->save();

        $raw = $this->raw('unstamped', $row->id);
        $this->assertNull($raw['created_at']);
        $this->assertNull($raw['updated_at']);
    }

    public function testInsertWritesBothColumns()
    {
        $row = Stamped::create(['name' => 'a']);
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertTrue($this->isUtcNow($raw['created_at']), "created_at was {$raw['created_at']}");
        $this->assertTrue($this->isUtcNow($raw['updated_at']), "updated_at was {$raw['updated_at']}");
    }

    public function testInsertWritesACallerSuppliedCreatedAtInstead()
    {
        $row = Stamped::create([
            'name'       => 'a',
            'created_at' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame('2001-02-03 04:05:06', $raw['created_at']);
        // updated_at is written when the caller did not set it
        $this->assertTrue($this->isUtcNow($raw['updated_at']));
    }

    public function testInsertWritesTheTimestampWhenTheCallerPassedNull()
    {
        $row = Stamped::create([
            'name'       => 'a',
            'created_at' => null,
        ]);
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertTrue($this->isUtcNow($raw['created_at']), "created_at was {$raw['created_at']}");
    }

    public function testInsertWritesACallerSuppliedUpdatedAtInstead()
    {
        $row = Stamped::create([
            'name'       => 'a',
            'updated_at' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame('2001-02-03 04:05:06', $raw['updated_at']);
        $this->assertTrue($this->isUtcNow($raw['created_at']));
    }

    public function testUpdateWritesUpdatedAtAndNotCreatedAt()
    {
        $row = Stamped::create([
            'name'       => 'a',
            'created_at' => '2001-02-03 04:05:06',
            'updated_at' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $row       = Stamped::q()->find_one($row->id);
        $row->name = 'b';
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame('2001-02-03 04:05:06', $raw['created_at']);
        $this->assertTrue($this->isUtcNow($raw['updated_at']));
    }

    public function testUpdateWritesACallerSuppliedUpdatedAtInstead()
    {
        $row = Stamped::create([
            'name'       => 'a',
            'updated_at' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $row             = Stamped::q()->find_one($row->id);
        $row->name       = 'b';
        $row->updated_at = '2002-03-04 05:06:07';
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame('2002-03-04 05:06:07', $raw['updated_at']);
    }

    public function testUpdateWritesACallerSuppliedCreatedAtInstead()
    {
        $row = Stamped::create([
            'name'       => 'a',
            'created_at' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $row             = Stamped::q()->find_one($row->id);
        $row->name       = 'b';
        $row->created_at = '2002-03-04 05:06:07';
        $row->save();

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame('2002-03-04 05:06:07', $raw['created_at']);
    }

    public function testAChangedFieldOutsideTheIgnoreListWritesUpdatedAt()
    {
        $row = StampedChild::create([
            'name'       => 'a',
            'changed_on' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $row             = StampedChild::q()->find_one($row->id);
        $row->last_login = '2001-02-03 04:05:06';
        $row->save();

        $raw = $this->raw('stamped_child', $row->id);
        $this->assertTrue($this->isUtcNow($raw['changed_on']));
    }

    public function testOnlyIgnoredFieldsChangingDoesNotWriteUpdatedAt()
    {
        $row = StampedChild::create([
            'name'       => 'a',
            'changed_on' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $row             = StampedChild::q()->find_one($row->id);
        $row->sort_order = 5;
        $row->save();

        $raw = $this->raw('stamped_child', $row->id);
        $this->assertSame(5, (int) $raw['sort_order']);
        $this->assertSame('2001-02-03 04:05:06', $raw['changed_on']);
    }

    public function testCustomColumnNames()
    {
        $row = StampedChild::create([
            'name'    => 'a',
            'made_on' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        $raw = $this->raw('stamped_child', $row->id);
        $this->assertSame('2001-02-03 04:05:06', $raw['made_on']);
        $this->assertTrue($this->isUtcNow($raw['changed_on']));
    }

    public function testASubclassReplacesAnInheritedIgnoreList()
    {
        $row = StampedChildOverride::create([
            'name'       => 'a',
            'changed_on' => '2001-02-03 04:05:06',
        ]);
        $row->save();

        // The subclass's list names last_login, so sort_order is no
        // longer ignored, and changing it writes changed_on.
        $row             = StampedChildOverride::q()->find_one($row->id);
        $row->sort_order = 9;
        $row->save();

        $raw = $this->raw('stamped_override', $row->id);
        $this->assertTrue($this->isUtcNow($raw['changed_on']));
    }

    public function testNoChangeSaveWritesNothing()
    {
        $row = Stamped::create(['name' => 'a']);
        $row->save();

        $row    = Stamped::q()->find_one($row->id);
        $before = $this->raw('stamped', $row->id);

        $this->assertTrue($row->save());

        $after = $this->raw('stamped', $row->id);
        $this->assertSame($before, $after);
    }

    public function testIncrementWritesUpdatedAt()
    {
        $row = Stamped::create([
            'name'  => 'a',
            'views' => 1,
        ]);
        $row->save();

        // Put the stored updated_at into the past, so the write shows.
        \Granada\Granada::for_table('stamped')
            ->where('id', $row->id)
            ->find_one()
            ->set('updated_at', '2001-02-03 04:05:06')
            ->save();

        $row = Stamped::q()->find_one($row->id);
        $this->assertSame(2, $row->increment('views'));

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame(2, (int) $raw['views']);
        $this->assertTrue($this->isUtcNow($raw['updated_at']), "updated_at was {$raw['updated_at']}");
    }

    public function testDecrementWritesUpdatedAt()
    {
        $row = Stamped::create([
            'name'  => 'a',
            'views' => 5,
        ]);
        $row->save();

        \Granada\Granada::for_table('stamped')
            ->where('id', $row->id)
            ->find_one()
            ->set('updated_at', '2001-02-03 04:05:06')
            ->save();

        $row = Stamped::q()->find_one($row->id);
        $this->assertSame(3, $row->decrement('views', 2));

        $raw = $this->raw('stamped', $row->id);
        $this->assertSame(3, (int) $raw['views']);
        $this->assertTrue($this->isUtcNow($raw['updated_at']), "updated_at was {$raw['updated_at']}");
    }

    public function testSoftDeleteManyWritesUpdatedAt()
    {
        $a = StampedSoft::create([
            'name'       => 'a',
            'updated_at' => '2001-02-03 04:05:06',
        ]);
        $a->save();

        StampedSoft::create([
            'name'       => 'b',
            'updated_at' => '2001-02-03 04:05:06',
        ])->save();

        StampedSoft::q()
            ->where('name', 'a')
            ->delete_many();

        $raw = $this->raw('stamped_soft', $a->id);
        $this->assertSame(1, (int) $raw['is_deleted']);
        $this->assertTrue($this->isUtcNow($raw['updated_at']), "updated_at was {$raw['updated_at']}");
    }

    public function testASoftDeleteThatIgnoresTheFlagWritesNoUpdatedAt()
    {
        $a = StampedSoftIgnored::create([
            'name'       => 'a',
            'updated_at' => '2001-02-03 04:05:06',
        ]);
        $a->save();

        $a->delete();
        $raw = $this->raw('stamped_soft_ignored', $a->id);
        $this->assertSame(1, (int) $raw['is_deleted']);
        $this->assertSame('2001-02-03 04:05:06', $raw['updated_at']);

        $a->restore();
        $raw = $this->raw('stamped_soft_ignored', $a->id);
        $this->assertSame(0, (int) $raw['is_deleted']);
        $this->assertSame('2001-02-03 04:05:06', $raw['updated_at']);

        StampedSoftIgnored::q()
            ->where('id', $a->id)
            ->delete_many();
        $raw = $this->raw('stamped_soft_ignored', $a->id);
        $this->assertSame(1, (int) $raw['is_deleted']);
        $this->assertSame('2001-02-03 04:05:06', $raw['updated_at']);
    }

    public function testBulkInsertWritesTimestamps()
    {
        Stamped::q()->insert([
            ['name' => 'x'],
            ['name' => 'y'],
        ]);

        $rows = \Granada\Granada::for_table('stamped')
            ->order_by_asc('id')
            ->find_array();
        $this->assertCount(2, $rows);
        foreach ($rows as $raw) {
            $this->assertTrue($this->isUtcNow($raw['created_at']));
            $this->assertTrue($this->isUtcNow($raw['updated_at']));
        }
    }

    public function testModelLessQueryWritesNoTimestamps()
    {
        ORM::_new_row('stamped')
            ->create(['name' => 'a'])
            ->save();

        $raw = \Granada\Granada::for_table('stamped')->find_array()[0];
        $this->assertNull($raw['created_at']);
    }
}
