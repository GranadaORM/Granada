<?php

use Granada\Granada;
use Granada\ORM;
use Granada\Orm\ConnectionManager;

/**
 * Tests for lazy finds: find_many_lazy() gives one model at a time.
 */
class LazyFindTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_connection_manager(new ConnectionManager('sqlite::memory:'));
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        ORM::configure('return_result_sets', false);
        ORM::configure('find_many_primary_id_as_key', true);
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
    }

    public function testLazyFindReturnsAGenerator()
    {
        $results = Car::q()->find_many_lazy();

        $this->assertInstanceOf(Generator::class, $results);
    }

    public function testYieldsTheModelsFindManyWouldReturnInTheSameOrder()
    {
        $expected = Car::q()
            ->order_by_asc('name')
            ->find_many();
        $lazy = Car::q()
            ->order_by_asc('name')
            ->find_many_lazy();

        $this->assertSame($this->pairs($expected), $this->pairs($lazy));
    }

    public function testKeysAreRowIdsByDefault()
    {
        $lazy = Car::q()->find_many_lazy();

        $this->assertSame([1, 2, 3, 4, 6], array_keys(iterator_to_array($lazy)));
    }

    public function testPositionalKeysRunContinuouslyAcrossChunksWhenConfigIsOff()
    {
        ORM::configure('find_many_primary_id_as_key', false);

        // Five cars over chunks of two: the keys must not restart per chunk.
        $lazy = Car::q()->find_many_lazy(2);

        $this->assertSame([0, 1, 2, 3, 4], array_keys(iterator_to_array($lazy)));
    }

    public function testAModelLessQueryWithoutAnIdColumnNumbersRowsPositionally()
    {
        $lazy = Granada::for_table('car')->find_many_lazy();

        $this->assertSame([0, 1, 2, 3, 4, 5], array_keys(iterator_to_array($lazy)));
    }

    public function testAModelLessQueryWithAnIdColumnKeysRowsById()
    {
        $lazy = Granada::for_table('car')
            ->use_id_column('id')
            ->find_many_lazy();

        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys(iterator_to_array($lazy)));
    }

    public function testOneIdQueryThenOneRowQueryPerChunk()
    {
        iterator_to_array(Car::q()->find_many_lazy(2));

        $log = $this->log();

        // Five cars over chunks of two: one id pass, three row passes.
        $this->assertSame("SELECT `id` FROM `car` WHERE `car`.`is_deleted` = '0'", $log[0]);
        $this->assertCount(4, $log);
        $this->assertSame(3, $this->queriesContaining($log, 'IN ('));
    }

    public function testChangingTheChunkSizeChangesOnlyTheQueryCount()
    {
        $expected = $this->pairs(Car::q()
            ->order_by_asc('name')
            ->find_many());

        foreach ([1, 2, 1000] as $chunk_size) {
            $lazy = Car::q()
                ->order_by_asc('name')
                ->find_many_lazy($chunk_size);

            $this->assertSame($expected, $this->pairs($lazy), "chunk size {$chunk_size}");
        }
    }

    public function testEagerLoadsRunOncePerChunkAgainstThatChunksParents()
    {
        $lazy = Car::q()
            ->with('parts')
            ->find_many_lazy(2);
        $results = iterator_to_array($lazy);

        $log = $this->log();

        // Three chunks, so three part queries, not one per car.
        $this->assertSame(3, $this->queriesContaining($log, 'FROM `part`'));

        $expected = Car::q()
            ->with('parts')
            ->find_many();
        foreach ($expected as $id => $car) {
            $this->assertCount(count($car->parts), $results[$id]->parts, "car {$id}");
        }
    }

    public function testEveryYieldedModelHasItsEagerLoads()
    {
        $lazy = Car::q()
            ->with('manufactor')
            ->find_many_lazy(2);

        foreach ($lazy as $car) {
            $this->assertInstanceOf(Manufactor::class, $car->manufactor);
            $this->assertSame($car->manufactor_id, $car->manufactor->id());
        }
    }

    public function testLazyRelationshipAccessDuringFindRunsOneQueryPerRead()
    {
        $owners = [];
        $lazy   = Car::q()
            ->order_by_asc('id')
            ->find_many_lazy(2);
        foreach ($lazy as $car) {
            $owners[] = $car->owner->name;
        }

        // Five reads; the Owner4 read repeats, and LazyItemCache returns
        // the cached value, so four queries in all. No chunk prefetches
        // owners.
        $this->assertSame(4, $this->queriesContaining($this->log(), 'FROM `owner`'));
        $this->assertSame(['Owner1', 'Owner2', 'Owner3', 'Owner4', 'Owner4'], $owners);
    }

    public function testLimitAndOffsetMatchFindMany()
    {
        $expected = Car::q()
            ->order_by_asc('id')
            ->limit(3)
            ->offset(1)
            ->find_many();
        $lazy = Car::q()
            ->order_by_asc('id')
            ->limit(3)
            ->offset(1)
            ->find_many_lazy();

        $this->assertSame($this->pairs($expected), $this->pairs($lazy));
    }

    public function testSelectMatchesFindMany()
    {
        $expected = Car::q()
            ->select('name')
            ->order_by_asc('id')
            ->find_many();
        $lazy = Car::q()
            ->select('name')
            ->order_by_asc('id')
            ->find_many_lazy();

        $this->assertSame(
            array_map(fn($car) => $car->as_array(), $expected),
            array_map(fn($car) => $car->as_array(), iterator_to_array($lazy))
        );
    }

    public function testAJoinedQueryWithTheDefaultSelectYieldsTheBaseRowsInIdPassOrder()
    {
        $lazy = Car::q()
            ->join('manufactor', ['car.manufactor_id', '=', 'manufactor.id'])
            ->where('manufactor.name', 'Manufactor2')
            ->order_by_asc('car.name')
            ->find_many_lazy();

        $results = iterator_to_array($lazy);

        // The join narrows and orders the id pass; the row pass
        // returns unmodified car rows, whatever the join matched.
        $this->assertSame([3, 4, 6], array_keys($results));
        $this->assertSame(['Car3', 'Car4', 'Car6'], $this->names($results));
    }

    public function testAJoinedQueryWithACustomSelectThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        Car::q()
            ->join('manufactor', ['car.manufactor_id', '=', 'manufactor.id'])
            ->select('manufactor.name')
            ->find_many_lazy();
    }

    public function testGroupByThrowsBeforeAnyQueryRuns()
    {
        $query = Car::q()->group_by('manufactor_id');

        try {
            $query->find_many_lazy();
            $this->fail('A grouped query must throw');
        } catch (InvalidArgumentException $e) {
            $this->assertSame([], $this->log());
        }
    }

    public function testARawQueryThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        Car::q()
            ->raw_query('SELECT * FROM car')
            ->find_many_lazy();
    }

    public function testAChunkSizeBelowOneThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        Car::q()->find_many_lazy(0);
    }

    public function testAnEmptyResultYieldsNothingBeyondTheIdPass()
    {
        $lazy = Car::q()
            ->where('id', 99999)
            ->find_many_lazy();

        $this->assertSame([], iterator_to_array($lazy));
        $this->assertCount(1, $this->log());
    }

    public function testTheLazyFindIsSingleUse()
    {
        $lazy = Car::q()->find_many_lazy();
        $lazy->current();
        $lazy->next();

        $this->expectException(Exception::class);
        $lazy->rewind();
    }

    public function testReturnResultSetsDoesNotApply()
    {
        ORM::configure('return_result_sets', true);

        $this->assertInstanceOf(Generator::class, Car::q()->find_many_lazy());
    }

    public function testRowsWithANullIdAreSkipped()
    {
        // keyed_row contains a duplicated id, a null id and a zero id.
        // The null id has no row to fetch by, so that row is
        // skipped; the duplicated id appears once per
        // occurrence, in row order.
        $lazy = KeyedRow::q()->find_many_lazy();

        $this->assertSame(['a', 'a-dup', 'c', 'zero'], $this->names($lazy));
    }

    /**
     * @param iterable $results
     * @return array<int|string, string>
     */
    private function pairs(iterable $results): array
    {
        $pairs = [];
        foreach ($results as $key => $result) {
            $pairs[$key] = $result->name;
        }

        return $pairs;
    }

    /**
     * @param iterable $results
     * @return string[]
     */
    private function names(iterable $results): array
    {
        $names = [];
        foreach ($results as $result) {
            $names[] = $result->name;
        }

        return $names;
    }

    /**
     * The queries this test has logged so far.
     * @return string[]
     */
    private function log(): array
    {
        return ORM::get_query_log();
    }

    /**
     * @param string[] $log
     */
    private function queriesContaining(array $log, string $fragment): int
    {
        return count(array_filter($log, fn($query) => str_contains($query, $fragment)));
    }
}
