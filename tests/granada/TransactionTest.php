<?php

use Granada\Model;
use Granada\ORM;

/**
 * A real SQLite handle that counts begin/commit/rollback calls,
 * so the tests can see how many of them reached the database.
 */
class CountingPDO extends PDO
{
    public int $begins    = 0;
    public int $commits   = 0;
    public int $rollbacks = 0;

    public function beginTransaction(): bool
    {
        $this->begins++;

        return parent::beginTransaction();
    }

    public function commit(): bool
    {
        $this->commits++;

        return parent::commit();
    }

    public function rollBack(): bool
    {
        $this->rollbacks++;

        return parent::rollBack();
    }
}

class TransactionTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // The transaction tests need a real database.
        // Fresh connections: no handle or setting leaks between tests
        ORM::reset_db();
        ORM::reset_config();

        // Set up SQLite in memory
        ORM::set_db(new CountingPDO('sqlite::memory:'));

        // Create schemas and populate with data
        ORM::get_db()->exec(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'));

        // Enable logging
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::reset_db();
    }

    public function testTransactionReturnsTheCallableValueAndCommits()
    {
        $db = ORM::get_db();

        $result = ORM::transaction(function () {
            Tag::create(['name' => 'red'])->save();

            return 'made';
        });

        $this->assertSame('made', $result);
        $this->assertSame(1, Tag::count());
        $this->assertSame(1, $db->begins);
        $this->assertSame(1, $db->commits);
        $this->assertSame(0, $db->rollbacks);
        $this->assertFalse($db->inTransaction());
    }

    public function testTransactionRollsBackAndRethrowsOnException()
    {
        $db = ORM::get_db();

        try {
            ORM::transaction(function () {
                Tag::create(['name' => 'red'])->save();

                throw new RuntimeException('no');
            });
            $this->fail('The exception was expected to rethrow');
        } catch (RuntimeException $e) {
            $this->assertSame('no', $e->getMessage());
        }

        $this->assertSame(0, Tag::count());
        $this->assertSame(1, $db->rollbacks);
        $this->assertSame(0, $db->commits);
        $this->assertFalse($db->inTransaction());
    }

    public function testFalseReturnCommitsTheWork()
    {
        $result = ORM::transaction(function () {
            Tag::create(['name' => 'red'])->save();

            return false;
        });

        $this->assertFalse($result);
        $this->assertSame(1, Tag::count());
    }

    public function testNestedTransactionsCollapseToTheOutermost()
    {
        $db = ORM::get_db();

        $result = ORM::transaction(function () {
            Tag::create(['name' => 'red'])->save();

            return ORM::transaction(function () {
                Tag::create(['name' => 'blue'])->save();

                return 'inner';
            });
        });

        $this->assertSame('inner', $result);
        $this->assertSame(2, Tag::count());
        $this->assertSame(1, $db->begins);
        $this->assertSame(1, $db->commits);
        $this->assertSame(0, $db->rollbacks);
    }

    public function testNestedFailureRollsBackTheWholeTransaction()
    {
        $db = ORM::get_db();

        try {
            ORM::transaction(function () {
                Tag::create(['name' => 'red'])->save();
                ORM::transaction(function () {
                    Tag::create(['name' => 'blue'])->save();

                    throw new RuntimeException('inner');
                });
            });
            $this->fail('The exception was expected to rethrow');
        } catch (RuntimeException $e) {
            $this->assertSame('inner', $e->getMessage());
        }

        $this->assertSame(0, Tag::count());
        $this->assertSame(1, $db->rollbacks);
        $this->assertSame(0, $db->commits);
        $this->assertFalse($db->inTransaction());
    }

    public function testManualTransactionCommits()
    {
        ORM::beginTransaction();
        Tag::create(['name' => 'red'])->save();
        ORM::commit();

        $this->assertSame(1, Tag::count());
        $this->assertFalse(ORM::get_db()->inTransaction());
    }

    public function testManualTransactionRollsBack()
    {
        ORM::beginTransaction();
        Tag::create(['name' => 'red'])->save();
        ORM::rollBack();

        $this->assertSame(0, Tag::count());
        $this->assertFalse(ORM::get_db()->inTransaction());
    }

    public function testManualNestedCallsTouchTheDatabaseOnce()
    {
        $db = ORM::get_db();

        ORM::beginTransaction();
        ORM::beginTransaction();
        Tag::create(['name' => 'red'])->save();
        ORM::commit();
        ORM::commit();

        $this->assertSame(1, $db->begins);
        $this->assertSame(1, $db->commits);
        $this->assertSame(1, Tag::count());
        $this->assertFalse($db->inTransaction());
    }

    public function testManualRollBackEndsTheWholeTransaction()
    {
        $db = ORM::get_db();

        ORM::beginTransaction();
        ORM::beginTransaction();
        Tag::create(['name' => 'red'])->save();
        ORM::rollBack();

        $this->assertSame(0, Tag::count());
        $this->assertSame(1, $db->rollbacks);
        $this->assertFalse($db->inTransaction());
    }

    public function testTransactionOnANamedConnection()
    {
        ORM::configure('connection_string', 'sqlite::memory:', 'alternate');
        ORM::get_db('alternate')->exec(
            'CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)'
        );

        ORM::transaction(function () {
            Model::factory('Tag', 'alternate')->create(['name' => 'red'])->save();
        }, 'alternate');

        $this->assertSame(1, Model::factory('Tag', 'alternate')->count());
        $this->assertSame(0, Tag::count());
    }

    public function testManualTransactionOnANamedConnection()
    {
        ORM::configure('connection_string', 'sqlite::memory:', 'alternate');
        ORM::get_db('alternate')->exec(
            'CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)'
        );

        ORM::beginTransaction('alternate');
        Model::factory('Tag', 'alternate')->create(['name' => 'red'])->save();
        ORM::commit('alternate');

        $this->assertSame(1, Model::factory('Tag', 'alternate')->count());
        $this->assertSame(0, Tag::count());
        $this->assertFalse(ORM::get_db('alternate')->inTransaction());
    }

    public function testManualRollBackOnANamedConnection()
    {
        ORM::configure('connection_string', 'sqlite::memory:', 'alternate');
        ORM::get_db('alternate')->exec(
            'CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)'
        );

        ORM::beginTransaction('alternate');
        Model::factory('Tag', 'alternate')->create(['name' => 'red'])->save();
        ORM::rollBack('alternate');

        $this->assertSame(0, Model::factory('Tag', 'alternate')->count());
        $this->assertFalse(ORM::get_db('alternate')->inTransaction());
    }

    public function testInsertCommitsEveryRow()
    {
        $db = ORM::get_db();

        $result = Tag::insert([
            ['name' => 'red'],
            ['name' => 'blue'],
        ]);

        $this->assertSame('2', $result);
        $this->assertSame(2, Tag::count());
        $this->assertSame(1, $db->begins);
        $this->assertSame(1, $db->commits);
    }

    public function testInsertRollsBackOnAMidLoopFailure()
    {
        $db = ORM::get_db();

        try {
            Tag::insert([
                ['name' => 'red'],
                ['name' => 'red'],
            ]);
            $this->fail('The duplicate name was expected to fail');
        } catch (PDOException $e) {
            // the second row broke the unique column
        }

        $this->assertSame(0, Tag::count());
        $this->assertSame(1, $db->begins);
        $this->assertSame(0, $db->commits);
        $this->assertSame(1, $db->rollbacks);
        $this->assertFalse($db->inTransaction());
    }
}
