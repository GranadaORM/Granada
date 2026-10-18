<?php

use Granada\ORM;

/**
 * Junction writes on a link table: the machine_bolt table joins one
 * machine to one bolt and has a qty and a sort_order of its own.
 */
class JunctionWriteTest extends \PHPUnit\Framework\TestCase
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
    }

    /** @return int[] the bolt ids the machine is linked to, in read order */
    private function linkedBoltIds(int $machine_id): array
    {
        $ids = [];
        foreach (Machine::find_one($machine_id)
            ->bolts()
            ->find_many() as $bolt) {
            $ids[] = (int) $bolt->id;
        }

        return $ids;
    }

    public function testAttachCreatesTheLinkRow()
    {
        Machine::find_one(1)->attach_bolts(1);

        $this->assertSame([1], $this->linkedBoltIds(1));
    }

    public function testAttachIsIdempotent()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolts(1);
        $machine->attach_bolts(1);

        $this->assertSame([1], $this->linkedBoltIds(1));
    }

    public function testAttachWithDataUpdatesAnExistingLink()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolts(1, ['qty' => 2]);
        $machine->attach_bolts(1, ['qty' => 7]);

        $link = MachineBolt::where_bolt_id(1)
            ->where_machine_id(1)
            ->find_one();

        $this->assertSame(7, (int) $link->qty);
        $this->assertSame([1], $this->linkedBoltIds(1));
    }

    public function testAttachWritesExtraLinkColumns()
    {
        Machine::find_one(1)->attach_bolts(1, ['qty' => 4]);

        $link = MachineBolt::where_machine_id(1)
            ->where_bolt_id(1)
            ->find_one();

        $this->assertSame(4, (int) $link->qty);
    }

    public function testDetachRemovesThatLink()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolts(1);
        $machine->attach_bolts(2);

        $machine->detach_bolts(1);

        $this->assertSame([2], $this->linkedBoltIds(1));
    }

    public function testDetachWithNoArgumentRemovesEveryLink()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolts(1);
        $machine->attach_bolts(2);

        $machine->detach_bolts();

        $this->assertSame([], $this->linkedBoltIds(1));
    }

    public function testSyncCreatesDeletesAndKeeps()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolts(1);
        $machine->attach_bolts(2);

        $machine->sync_bolts([2, 3]);

        $this->assertSame([2, 3], $this->linkedBoltIds(1));
    }

    public function testSyncUpdatesDataForAKeptId()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolts(1, ['qty' => 9]);

        $machine->sync_bolts([1 => ['qty' => 2]]);

        $link = MachineBolt::where_machine_id(1)
            ->where_bolt_id(1)
            ->find_one();

        $this->assertSame(2, (int) $link->qty);
    }

    public function testSyncWithAnEmptyListRemovesEveryLink()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolts(1);
        $machine->attach_bolts(2);

        $machine->sync_bolts([]);

        $this->assertSame([], $this->linkedBoltIds(1));
    }

    public function testAttachOnAnOrderedRelationAppendsMaxPlusOne()
    {
        $machine = Machine::find_one(1);
        $machine->ordered_bolts()->attach(1);
        $machine->ordered_bolts()->attach(2);

        $this->assertSame(1, (int) MachineBolt::where_bolt_id(1)
            ->find_one()
            ->sort_order);
        $this->assertSame(2, (int) MachineBolt::where_bolt_id(2)
            ->find_one()
            ->sort_order);
    }

    public function testSyncOnAnOrderedRelationNumbersCreatedAndKeptRowsInListOrder()
    {
        $machine = Machine::find_one(1);
        $machine->ordered_bolts()->attach(1);

        $machine->ordered_bolts()->sync([3, 1, 2]);

        $this->assertSame(1, (int) MachineBolt::where_bolt_id(3)
            ->find_one()
            ->sort_order);
        $this->assertSame(2, (int) MachineBolt::where_bolt_id(1)
            ->find_one()
            ->sort_order);
        $this->assertSame(3, (int) MachineBolt::where_bolt_id(2)
            ->find_one()
            ->sort_order);
    }

    public function testReadsThroughAnOrderedRelationOrderByTheOrderColumn()
    {
        $machine = Machine::find_one(1);
        $machine->ordered_bolts()->sync([3, 1, 2]);

        $names = [];
        foreach (Machine::find_one(1)
            ->ordered_bolts()
            ->find_many() as $bolt) {
            $names[] = $bolt->name;
        }

        $this->assertSame(['Bolt3', 'Bolt1', 'Bolt2'], $names);
    }

    public function testEachWriteRunsInsideOneTransaction()
    {
        $db = ORM::get_db();

        Machine::find_one(1)->attach_bolts(1);
        Machine::find_one(1)->sync_bolts([1, 2]);
        Machine::find_one(1)->detach_bolts(1);

        $this->assertSame(3, $db->begins);
        $this->assertSame(3, $db->commits);
        $this->assertSame(0, $db->rollbacks);
        $this->assertFalse($db->inTransaction());
    }

    public function testAFailedSyncRollsBackEveryWrite()
    {
        $machine = Machine::find_one(1);

        try {
            $machine->sync_bolts([
                1 => ['qty' => 1],
                2 => ['qty' => -1],
            ]);
            $this->fail('The negative qty was expected to fail the check constraint');
        } catch (PDOException $e) {
            // the CHECK (qty >= 0) rejected the second row
        }

        $this->assertSame([], $this->linkedBoltIds(1));
        $this->assertSame(1, ORM::get_db()->rollbacks);
        $this->assertSame(0, ORM::get_db()->commits);
    }

    public function testTheChainFormWritesThroughTheRelation()
    {
        $machine = Machine::find_one(1);

        $machine->bolts()->attach(1, ['qty' => 2]);

        $this->assertSame([1], $this->linkedBoltIds(1));

        $link = MachineBolt::where_machine_id(1)
            ->where_bolt_id(1)
            ->find_one();

        $this->assertSame(2, (int) $link->qty);
    }

    public function testTheRelationOnTheOtherSideWritesToo()
    {
        Bolt::find_one(1)->attach_machines(2);

        $ids = [];
        foreach (Machine::find_one(2)
            ->bolts()
            ->find_many() as $bolt) {
            $ids[] = (int) $bolt->id;
        }

        $this->assertSame([1], $ids);
    }

    public function testASaveNeverWritesLinkRows()
    {
        $machine                         = Machine::find_one(2);
        $machine->relationships['bolts'] = [1, 2];

        $machine->save();

        $this->assertSame([], $this->linkedBoltIds(2));
    }

    public function testAnUnsavedParentWritesNoLink()
    {
        Machine::create(['name' => 'New'])->attach_bolts(1);

        $this->assertSame(0, MachineBolt::count());
    }

    public function testAJunctionWriteOnAPlainHasManyThrows()
    {
        $machine = Machine::find_one(1);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot attach: the has_many relationship has no link table');

        $machine->attach_direct_parts(1);
    }

    public function testSyncMatchesStringKeysThroughANonIdTableKey()
    {
        $machine = Machine::find_one(1);
        $machine->attach_bolt_codes('AAA');
        $machine->attach_bolt_codes('BBB');

        $machine->sync_bolt_codes(['BBB', 'CCC']);

        $codes = [];
        foreach (MachineBoltCode::order_by_id()->find_many() as $link) {
            $codes[] = $link->bolt_code;
        }

        $this->assertSame(['BBB', 'CCC'], $codes);
    }
}
