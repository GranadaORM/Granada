<?php

use Granada\ORM;

/**
 * Tests for lazy finds: find_many_lazy() gives one model at a time.
 */
class LazyFindTest extends \PHPUnit\Framework\TestCase
{
    /** How many queries were logged before this test started. */
    protected int $log_offset = 0;

    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));

        ORM::get_db()->exec(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'));

        ORM::configure('return_result_sets', false);
        ORM::configure('find_many_primary_id_as_key', true);
        ORM::configure('logging', true);

        // The query log accumulates across tests; remember where this
        // test's queries start.
        $this->log_offset = count(ORM::get_query_log());
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
    }

    public function testLazyFindReturnsAGenerator()
    {
        $results = Car::find_many_lazy();

        $this->assertInstanceOf(Generator::class, $results);
    }

    public function testYieldsTheModelsFindManyWouldReturnInTheSameOrder()
    {
        $expected = Car::order_by_asc('name')->find_many();
        $walked   = Car::order_by_asc('name')->find_many_lazy();

        $this->assertSame($this->pairs($expected), $this->pairs($walked));
    }

    public function testKeysAreRowIdsByDefault()
    {
        $walked = Car::find_many_lazy();

        $this->assertSame([1, 2, 3, 4, 6], array_keys(iterator_to_array($walked)));
    }

    public function testPositionalKeysRunContinuouslyAcrossChunksWhenConfigIsOff()
    {
        ORM::configure('find_many_primary_id_as_key', false);

        // Five cars over chunks of two: the keys must not restart per chunk.
        $walked = Car::find_many_lazy(2);

        $this->assertSame([0, 1, 2, 3, 4], array_keys(iterator_to_array($walked)));
    }

    public function testPlainOrmWithoutAnIdColumnNumbersRowsPositionally()
    {
        $walked = ORM::for_table('car')->find_many_lazy();

        $this->assertSame([0, 1, 2, 3, 4, 5], array_keys(iterator_to_array($walked)));
    }

    public function testPlainOrmWithAnIdColumnKeysRowsById()
    {
        $walked = ORM::for_table('car')->use_id_column('id')->find_many_lazy();

        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys(iterator_to_array($walked)));
    }

    public function testOneIdQueryThenOneRowQueryPerChunk()
    {
        iterator_to_array(Car::find_many_lazy(2));

        $log = $this->log();

        // Five cars over chunks of two: one id pass, three row passes.
        $this->assertSame("SELECT `id` FROM `car` WHERE `car`.`is_deleted` = '0'", $log[0]);
        $this->assertCount(4, $log);
        $this->assertSame(3, $this->queriesContaining($log, 'IN ('));
    }

    public function testChangingTheChunkSizeChangesOnlyTheQueryCount()
    {
        $expected = $this->pairs(Car::order_by_asc('name')->find_many());

        foreach ([1, 2, 1000] as $chunk_size) {
            $walked = Car::order_by_asc('name')->find_many_lazy($chunk_size);

            $this->assertSame($expected, $this->pairs($walked), "chunk size {$chunk_size}");
        }
    }

    public function testEagerLoadsRunOncePerChunkAgainstThatChunksParents()
    {
        $walked  = Car::with('parts')->find_many_lazy(2);
        $results = iterator_to_array($walked);

        $log = $this->log();

        // Three chunks, so three part queries, not one per car.
        $this->assertSame(3, $this->queriesContaining($log, 'FROM `part`'));

        $expected = Car::with('parts')->find_many();
        foreach ($expected as $id => $car) {
            $this->assertCount(count($car->parts), $results[$id]->parts, "car {$id}");
        }
    }

    public function testEveryYieldedModelCarriesItsEagerLoads()
    {
        $walked = Car::with('manufactor')->find_many_lazy(2);

        foreach ($walked as $car) {
            $this->assertInstanceOf(Manufactor::class, $car->manufactor);
            $this->assertSame($car->manufactor_id, $car->manufactor->id());
        }
    }

    public function testLazyRelationshipAccessMidWalkCostsOneQueryPerTouch()
    {
        $owners = [];
        $walked = Car::order_by_asc('id')->find_many_lazy(2);
        foreach ($walked as $car) {
            $owners[] = $car->owner->name;
        }

        // Five touches; the Owner4 touch repeats, and LazyItemCache
        // answers the repeat, so four queries in all. No chunk
        // prefetches owners.
        $this->assertSame(4, $this->queriesContaining($this->log(), 'FROM `owner`'));
        $this->assertSame(['Owner1', 'Owner2', 'Owner3', 'Owner4', 'Owner4'], $owners);
    }

    public function testLimitAndOffsetMatchFindMany()
    {
        $expected = Car::order_by_asc('id')->limit(3)->offset(1)->find_many();
        $walked   = Car::order_by_asc('id')->limit(3)->offset(1)->find_many_lazy();

        $this->assertSame($this->pairs($expected), $this->pairs($walked));
    }

    public function testSelectMatchesFindMany()
    {
        $expected = Car::select('name')->order_by_asc('id')->find_many();
        $walked   = Car::select('name')->order_by_asc('id')->find_many_lazy();

        $this->assertSame(
            array_map(fn($car) => $car->as_array(), $expected),
            array_map(fn($car) => $car->as_array(), iterator_to_array($walked))
        );
    }

    public function testAJoinedQueryWithTheDefaultSelectYieldsTheBaseRowsInIdPassOrder()
    {
        $walked = Car::join('manufactor', ['car.manufactor_id', '=', 'manufactor.id'])
            ->where('manufactor.name', 'Manufactor2')
            ->order_by_asc('car.name')
            ->find_many_lazy();

        $results = iterator_to_array($walked);

        // The join narrows and orders the id pass; the row pass
        // returns clean car rows, whatever the join matched.
        $this->assertSame([3, 4, 6], array_keys($results));
        $this->assertSame(['Car3', 'Car4', 'Car6'], $this->names($results));
    }

    public function testAJoinedQueryWithACustomSelectThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        Car::join('manufactor', ['car.manufactor_id', '=', 'manufactor.id'])
            ->select('manufactor.name')
            ->find_many_lazy();
    }

    public function testGroupByThrowsBeforeAnyQueryRuns()
    {
        $query = Car::group_by('manufactor_id');

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

        Car::raw_query('SELECT * FROM car')->find_many_lazy();
    }

    public function testAChunkSizeBelowOneThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        Car::find_many_lazy(0);
    }

    public function testAnEmptyResultYieldsNothingBeyondTheIdPass()
    {
        $walked = Car::where('id', 99999)->find_many_lazy();

        $this->assertSame([], iterator_to_array($walked));
        $this->assertCount(1, $this->log());
    }

    public function testTheWalkIsSingleUse()
    {
        $walked = Car::find_many_lazy();
        $walked->current();
        $walked->next();

        $this->expectException(Exception::class);
        $walked->rewind();
    }

    public function testReturnResultSetsDoesNotApply()
    {
        ORM::configure('return_result_sets', true);

        $this->assertInstanceOf(Generator::class, Car::find_many_lazy());
    }

    public function testRowsWithANullIdAreSkipped()
    {
        // keyed_row holds a duplicated id, a null id and a zero id.
        // The null id has no row to fetch by, so that row is
        // skipped; the duplicated id comes back once per
        // occurrence, in row order.
        $walked = KeyedRow::find_many_lazy();

        $this->assertSame(['a', 'a-dup', 'c', 'zero'], $this->names($walked));
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
        return array_slice(ORM::get_query_log(), $this->log_offset);
    }

    /**
     * @param string[] $log
     */
    private function queriesContaining(array $log, string $fragment): int
    {
        return count(array_filter($log, fn($query) => str_contains($query, $fragment)));
    }
}
