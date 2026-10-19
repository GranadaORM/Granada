<?php

use Granada\ORM;

/**
 * Children sync: sync through a has_many relation replaces the child set
 * in one call - a row with an id updates that child, a row without one
 * creates a child, and an unlisted child is deleted.
 */
class ChildrenSyncTest extends \PHPUnit\Framework\TestCase
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

    /** @return string[] the item names on the crate, in read order */
    private function itemNames(int $crate_id): array
    {
        $names = [];
        foreach (Crate::q()
            ->find_one($crate_id)
            ->items()
            ->find_many() as $item) {
            $names[] = $item->name;
        }

        return $names;
    }

    /** @return int[] the ids of the crate's items, in read order */
    private function itemIds(int $crate_id): array
    {
        $ids = [];
        foreach (Crate::q()
            ->find_one($crate_id)
            ->items()
            ->find_many() as $item) {
            $ids[] = (int) $item->id;
        }

        return $ids;
    }

    /** @return string[] the visible extra names on the crate, in read order */
    private function extraNames(int $crate_id): array
    {
        $names = [];
        foreach (Crate::q()
            ->find_one($crate_id)
            ->extras()
            ->find_many() as $extra) {
            $names[] = $extra->name;
        }

        return $names;
    }

    /** @return int[] every extra id on the crate, visible or flagged */
    private function extraIds(int $crate_id): array
    {
        $ids = [];
        foreach (CrateExtra::q()
            ->clear_where()
            ->where('crate_id', $crate_id)
            ->order_by_id()
            ->find_many() as $extra) {
            $ids[] = (int) $extra->id;
        }

        return $ids;
    }

    public function testSyncCreatesNewChildrenWithTheParentKey()
    {
        Crate::q()
            ->find_one(1)
            ->sync_items([
                ['name' => 'Item1'],
                ['name' => 'Item2'],
            ]);

        $this->assertSame(['Item1', 'Item2'], $this->itemNames(1));
        $this->assertSame(2, CrateItem::q()
            ->where('crate_id', 1)
            ->count());
    }

    public function testSyncUpdatesAChildGivenWithItsId()
    {
        Crate::q()
            ->find_one(1)
            ->sync_items([['name' => 'Item1']]);
        $id = $this->itemIds(1)[0];

        Crate::q()
            ->find_one(1)
            ->sync_items([
                [
                    'id'   => $id,
                    'name' => 'Renamed',
                ],
            ]);

        $this->assertSame(['Renamed'], $this->itemNames(1));
        $this->assertSame([$id], $this->itemIds(1));
    }

    public function testSyncDeletesUnlistedChildren()
    {
        $crate = Crate::q()->find_one(1);
        $crate->sync_items([
            ['name' => 'Item1'],
            ['name' => 'Item2'],
            ['name' => 'Item3'],
        ]);
        [$keep1, $keep2] = $this->itemIds(1);

        $crate->sync_items([
            [
                'id'   => $keep1,
                'name' => 'Item1',
            ],
            [
                'id'   => $keep2,
                'name' => 'Item2',
            ],
        ]);

        $this->assertSame(['Item1', 'Item2'], $this->itemNames(1));
        $this->assertSame(2, CrateItem::q()->count());
    }

    public function testSyncWithAnEmptyListRemovesEveryChild()
    {
        $crate = Crate::q()->find_one(1);
        $crate->sync_items([['name' => 'Item1'], ['name' => 'Item2']]);

        $crate->sync_items([]);

        $this->assertSame([], $this->itemNames(1));
        $this->assertSame(0, CrateItem::q()->count());
    }

    public function testSyncThroughTheChainForm()
    {
        Crate::q()
            ->find_one(1)
            ->items()
            ->sync([['name' => 'Item1']]);

        $this->assertSame(['Item1'], $this->itemNames(1));
    }

    public function testSyncThroughTheModelMethod()
    {
        Crate::q()
            ->find_one(1)
            ->sync_ordered_items([
                ['name' => 'Item1'],
                ['name' => 'Item2'],
            ]);

        $this->assertSame(['Item1', 'Item2'], $this->itemNames(1));
        $this->assertSame(1, (int) CrateItem::q()
            ->where('name', 'Item1')
            ->find_one()
            ->sort_order);
        $this->assertSame(2, (int) CrateItem::q()
            ->where('name', 'Item2')
            ->find_one()
            ->sort_order);
    }

    public function testSyncSkipsAnIdThatIsNotAChildOfThisParent()
    {
        Crate::q()
            ->find_one(2)
            ->sync_items([['name' => 'Other']]);
        $other_id = $this->itemIds(2)[0];

        Crate::q()
            ->find_one(1)
            ->sync_items([
                [
                    'id'   => $other_id,
                    'name' => 'Hijacked',
                ],
            ]);

        $this->assertSame([], $this->itemNames(1));
        $this->assertSame(['Other'], $this->itemNames(2));
    }

    public function testSyncScopesToTheRelationsOwnFilter()
    {
        $crate = Crate::q()->find_one(1);
        $crate->sync_items([['name' => 'Item1'], ['name' => 'Item2']]);
        [$item1, $item2] = $this->itemIds(1);

        // The relation reads only Item1, so sync sees that subset: Item1
        // is kept, and Item2 - outside the filter - is left untouched.
        $crate->sync_named_items([
            [
                'id'   => $item1,
                'name' => 'Item1',
            ],
        ]);

        $this->assertSame([$item1, $item2], $this->itemIds(1));
    }

    public function testSyncSoftDeletesUnlistedChildren()
    {
        $crate = Crate::q()->find_one(1);
        $crate->sync_extras([['name' => 'Extra1'], ['name' => 'Extra2']]);
        [$kept, $dropped] = $this->extraIds(1);

        $crate->sync_extras([
            [
                'id'   => $kept,
                'name' => 'Extra1',
            ],
        ]);

        // The unlisted extra keeps its row, flagged, and drops out of reads.
        $this->assertSame(2, CrateExtra::q()
            ->clear_where()
            ->count());
        $this->assertSame(['Extra1'], $this->extraNames(1));
        $this->assertSame(1, CrateExtra::q()
            ->clear_where()
            ->where('is_deleted', 1)
            ->count());
        $this->assertSame((int) $dropped, (int) CrateExtra::q()
            ->clear_where()
            ->where('is_deleted', 1)
            ->find_one()
            ->id);
    }

    public function testSyncRestoresARelistedSoftDeletedChild()
    {
        $crate = Crate::q()->find_one(1);
        $crate->sync_extras([['name' => 'Extra1'], ['name' => 'Extra2']]);
        [$kept, $flagged] = $this->extraIds(1);

        // Extra2 is unlisted, so it is flagged; only Extra1 stays visible.
        $crate->sync_extras([
            [
                'id'   => $kept,
                'name' => 'Extra1',
            ],
        ]);
        $this->assertSame(['Extra1'], $this->extraNames(1));

        // Listing the flagged id again matches the same row and clears
        // the flag rather than inserting a second Extra2.
        $crate->sync_extras([
            [
                'id'   => $kept,
                'name' => 'Extra1',
            ],
            [
                'id'   => $flagged,
                'name' => 'Extra2',
            ],
        ]);

        $this->assertSame(['Extra1', 'Extra2'], $this->extraNames(1));
        $this->assertSame(2, CrateExtra::q()
            ->clear_where()
            ->count());
    }

    public function testSyncOnAnOrderedRelationNumbersRowsInListOrder()
    {
        Crate::q()
            ->find_one(1)
            ->ordered_items()
            ->sync([
                ['name' => 'Item1'],
                ['name' => 'Item2'],
                ['name' => 'Item3'],
            ]);

        $this->assertSame(1, (int) CrateItem::q()
            ->where('name', 'Item1')
            ->find_one()
            ->sort_order);
        $this->assertSame(2, (int) CrateItem::q()
            ->where('name', 'Item2')
            ->find_one()
            ->sort_order);
        $this->assertSame(3, (int) CrateItem::q()
            ->where('name', 'Item3')
            ->find_one()
            ->sort_order);
    }

    public function testASkippedRowDoesNotConsumeAnOrderPosition()
    {
        Crate::q()
            ->find_one(2)
            ->sync_items([['name' => 'Other']]);
        $foreign = $this->itemIds(2)[0];

        Crate::q()
            ->find_one(1)
            ->ordered_items()
            ->sync([
                [
                    'id'   => $foreign,
                    'name' => 'Hijacked',
                ],
                ['name' => 'Item1'],
            ]);

        // The skipped foreign row is neither created nor kept, so Item1 is
        // the first kept row and takes position 1.
        $this->assertSame(1, (int) CrateItem::q()
            ->where('name', 'Item1')
            ->find_one()
            ->sort_order);
    }

    public function testReadsThroughAnOrderedHasManyOrderByTheOrderColumn()
    {
        Crate::q()
            ->find_one(1)
            ->ordered_items()
            ->sync([
                ['name' => 'Item1'],
                ['name' => 'Item2'],
                ['name' => 'Item3'],
            ]);

        // Reverse the set; the reads come back in the new order.
        $crate = Crate::q()->find_one(1);
        $ids   = $this->itemIds(1);
        $crate->ordered_items()->sync([
            [
                'id'   => $ids[2],
                'name' => 'Item3',
            ],
            [
                'id'   => $ids[1],
                'name' => 'Item2',
            ],
            [
                'id'   => $ids[0],
                'name' => 'Item1',
            ],
        ]);

        $names = [];
        foreach (Crate::q()
            ->find_one(1)
            ->ordered_items()
            ->find_many() as $item) {
            $names[] = $item->name;
        }

        $this->assertSame(['Item3', 'Item2', 'Item1'], $names);
    }

    public function testEachSyncRunsInsideOneTransaction()
    {
        $db    = ORM::get_db();
        $crate = Crate::q()->find_one(1);

        $crate->sync_items([['name' => 'Item1']]);
        $crate->sync_items([['name' => 'Item2']]);

        $this->assertSame(2, $db->begins);
        $this->assertSame(2, $db->commits);
        $this->assertSame(0, $db->rollbacks);
        $this->assertFalse($db->inTransaction());
    }

    public function testAFailedSyncRollsBackEveryWrite()
    {
        $crate = Crate::q()->find_one(1);

        try {
            $crate->sync_items([
                [
                    'name' => 'Item1',
                    'qty'  => 1,
                ],
                [
                    'name' => 'Item2',
                    'qty'  => -1,
                ],
            ]);
            $this->fail('The negative qty was expected to fail the check constraint');
        } catch (PDOException $e) {
            // the CHECK (qty >= 0) rejected the second row
        }

        $this->assertSame([], $this->itemNames(1));
        $this->assertSame(1, ORM::get_db()->rollbacks);
        $this->assertSame(0, ORM::get_db()->commits);
    }

    public function testASaveNeverWritesChildren()
    {
        $crate                         = Crate::q()->find_one(2);
        $crate->relationships['items'] = [['name' => 'Item1']];

        $crate->save();

        $this->assertSame([], $this->itemNames(2));
    }

    public function testAnUnsavedParentWritesNoChildren()
    {
        Crate::create(['name' => 'New'])
            ->sync_items([['name' => 'Item1']]);

        $this->assertSame(0, CrateItem::q()->count());
    }
}
