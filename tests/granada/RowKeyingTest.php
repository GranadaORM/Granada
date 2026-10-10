<?php

use Granada\ORM;
use Granada\Granada;
use Granada\ResultSet;
use Granada\Orm\ConnectionManager;

/**
 * Tests for which array key each fetched row gets in find_many
 * results, and what find_one hands back.
 *
 * The seeded table covers the edges: a duplicated id, a null id and
 * a zero id, in row order.
 */
class RowKeyingTest extends \PHPUnit\Framework\TestCase
{
    protected CountingConnectionManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CountingConnectionManager();
        ORM::set_connection_manager($this->manager);
        ORM::configure('connection_string', 'sqlite::memory:');
        ORM::configure('return_result_sets', false);
        ORM::configure('find_many_primary_id_as_key', true);

        ORM::get_db()->exec(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'));

        $this->manager->get_db_calls = 0;
    }

    protected function tearDown(): void
    {
        ORM::set_connection_manager(new ConnectionManager());
    }

    public function testFindManyKeysByRowIdWhenAssociative()
    {
        $results = KeyedRow::find_many();

        $this->assertSame([1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testDuplicateIdsLastRowWins()
    {
        $this->seed([[5, 'first'], [5, 'second'], [5, 'third']]);

        $results = KeyedRow::find_many();

        $this->assertSame([5], array_keys($results));
        $this->assertSame(['third'], $this->names($results));
    }

    public function testNullIdRowFallsBackToPositionalKey()
    {
        $this->seed([[1, 'a'], [null, 'orphan'], [2, 'b']]);

        $results = KeyedRow::find_many();

        // The positional key 1 collides with the id 1 and overwrites it.
        $this->assertSame([1, 2], array_keys($results));
        $this->assertSame(['orphan', 'b'], $this->names($results));
    }

    public function testZeroIdRowFallsBackToPositionalKey()
    {
        $this->seed([[7, 'seven'], [0, 'zero']]);

        $results = KeyedRow::find_many();

        $this->assertSame([7, 1], array_keys($results));
        $this->assertSame(['seven', 'zero'], $this->names($results));
    }

    public function testRowsWithoutTheIdColumnSelectedAreNumberedPositionally()
    {
        $results = KeyedRow::select('name')->find_many();

        $this->assertSame([0, 1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a', 'a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testFindManyPrimaryIdAsKeyOffNumbersRowsPositionally()
    {
        ORM::configure('find_many_primary_id_as_key', false);

        $results = KeyedRow::find_many();

        $this->assertSame([0, 1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a', 'a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testNonAssociativeNumbersRowsPositionally()
    {
        $results = KeyedRow::non_associative()->find_many();

        $this->assertSame([0, 1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a', 'a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testAssociativeKeysRowsByRowIdWhenConfigIsOn()
    {
        $results = KeyedRow::associative()->find_many();

        $this->assertSame([1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testAssociativeKeysRowsByRowIdWhenConfigIsOff()
    {
        ORM::configure('find_many_primary_id_as_key', false);

        $results = KeyedRow::associative()->find_many();

        $this->assertSame([1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testNonAssociativeNumbersRowsPositionallyWhenConfigIsOff()
    {
        ORM::configure('find_many_primary_id_as_key', false);

        $results = KeyedRow::non_associative()->find_many();

        $this->assertSame([0, 1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a', 'a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testTheLastToggleCallBeforeTheFetchWins()
    {
        ORM::configure('find_many_primary_id_as_key', false);

        $positional = KeyedRow::associative()->non_associative()->find_many();

        $this->assertSame([0, 1, 2, 3, 4], array_keys($positional));
        $this->assertSame(['a', 'a-dup', 'null-id', 'c', 'zero'], $this->names($positional));

        $keyed = KeyedRow::non_associative()->associative()->find_many();

        $this->assertSame([1, 2, 3, 4], array_keys($keyed));
        $this->assertSame(['a-dup', 'null-id', 'c', 'zero'], $this->names($keyed));
    }

    public function testResetAssociativeRestoresKeyingByIdWhenConfigIsOn()
    {
        $results = KeyedRow::non_associative()->reset_associative()->find_many();

        $this->assertSame([1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testResetAssociativeRestoresPositionalKeyingWhenConfigIsOff()
    {
        ORM::configure('find_many_primary_id_as_key', false);

        $results = KeyedRow::associative()->reset_associative()->find_many();

        $this->assertSame([0, 1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a', 'a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testCustomIdColumnKeysRows()
    {
        $results = KeyedRowCustomId::find_many();

        $this->assertSame([7], array_keys($results));
        $this->assertSame(['seven'], $this->names($results));
    }

    public function testPlainOrmFindManyNumbersRowsWithoutAnIdColumn()
    {
        $results = Granada::for_table('keyed_row')->find_many();

        $this->assertSame([0, 1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a', 'a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testPlainOrmFindManyKeysByRowIdWithUseIdColumn()
    {
        $results = Granada::for_table('keyed_row')->use_id_column('id')->find_many();

        $this->assertSame([1, 2, 3, 4], array_keys($results));
        $this->assertSame(['a-dup', 'null-id', 'c', 'zero'], $this->names($results));
    }

    public function testIdKeyedResultSetWhenResultSetsAreOn()
    {
        ORM::configure('return_result_sets', true);

        $results = KeyedRow::find_many();

        $this->assertInstanceOf(ResultSet::class, $results);
        $this->assertSame([1, 2, 3, 4], array_keys($results->as_array()));
    }

    public function testFindManyOnAnEmptyTableReturnsAnEmptyArray()
    {
        $results = KeyedRow::where('id', 99999)->find_many();

        $this->assertSame([], $results);
    }

    public function testFindOneReturnsTheModel()
    {
        $result = KeyedRow::find_one(3);

        $this->assertInstanceOf(KeyedRow::class, $result);
        $this->assertSame(3, $result->id());
        $this->assertSame('c', $result->name);
    }

    public function testFindOneWithACustomIdColumn()
    {
        $result = KeyedRowCustomId::find_one(7);

        $this->assertInstanceOf(KeyedRowCustomId::class, $result);
        $this->assertSame(7, $result->id());
    }

    public function testFindOneWithoutAMatchReturnsNull()
    {
        $this->assertNull(KeyedRow::find_one(999));
    }

    public function testFindOneAttachesEagerLoadedRelationshipsWhenNonAssociative()
    {
        $car = Car::non_associative()->with('manufactor')->find_one(1);

        $this->assertInstanceOf(Manufactor::class, $car->manufactor);
        $this->assertSame(1, $car->manufactor->id());
        $this->assertSame('Manufactor1', $car->manufactor->name);
    }

    public function testFindOneAttachesEagerLoadedRelationshipsWhenAssociative()
    {
        $car = Car::associative()->with('manufactor')->find_one(1);

        $this->assertInstanceOf(Manufactor::class, $car->manufactor);
        $this->assertSame(1, $car->manufactor->id());
        $this->assertSame('Manufactor1', $car->manufactor->name);
    }

    public function testHydratingManyRowsResolvesTheConnectionOnce()
    {
        KeyedRow::find_many();

        // One resolution each for building the query, running the
        // SELECT and hydrating the whole result, whatever the row count.
        $this->assertSame(3, $this->manager->get_db_calls);
    }

    public function testHydratingOneRowResolvesTheConnectionOnce()
    {
        KeyedRow::where('id', 3)->find_many();

        $this->assertSame(3, $this->manager->get_db_calls);
    }

    public function testFindMapKeepsTheKeyingFindManyWouldUse()
    {
        ORM::configure('return_result_sets', true);

        $keyed = KeyedRow::find_map(fn($row) => $row->name);

        $this->assertSame([1 => 'a-dup', 2 => 'null-id', 3 => 'c', 4 => 'zero'], $keyed);

        $positional = KeyedRow::non_associative()->find_map(fn($row) => $row->name);

        $this->assertSame([0 => 'a', 1 => 'a-dup', 2 => 'null-id', 3 => 'c', 4 => 'zero'], $positional);
    }

    public function testFindPairsOutputDoesNotDependOnTheToggle()
    {
        $pairs = KeyedRow::find_pairs('id', 'name');

        $this->assertSame([1 => 'a-dup', 3 => 'c', 0 => 'zero'], $pairs);
        $this->assertSame($pairs, KeyedRow::associative()->find_pairs('id', 'name'));
        $this->assertSame($pairs, KeyedRow::non_associative()->find_pairs('id', 'name'));
    }

    /**
     * @param array<int, mixed> $results
     * @return string[]
     */
    private function names(array $results): array
    {
        return array_values(array_map(fn($result) => $result->name, $results));
    }

    /**
     * @param array<int, array{0: int|null, 1: string}> $rows
     */
    private function seed(array $rows): void
    {
        $db = ORM::get_db();
        $db->exec('DELETE FROM keyed_row');
        foreach ($rows as [$id, $name]) {
            $id = ($id === null) ? 'NULL' : $db->quote((string) $id);
            $db->exec("INSERT INTO keyed_row (id, name) VALUES ({$id}, {$db->quote($name)})");
        }
    }
}

/**
 * Connection manager that counts how often the ORM asks it for a
 * connection handle.
 */
class CountingConnectionManager extends ConnectionManager
{
    public int $get_db_calls = 0;

    public function get_db(string $connection_name = self::DEFAULT_CONNECTION): PDO
    {
        $this->get_db_calls++;

        return parent::get_db($connection_name);
    }
}
