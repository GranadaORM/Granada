<?php

use Granada\Granada;
use Granada\ORM;
use Granada\ResultSet;

class IdiormResultSetTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new PDO('sqlite::memory:');
        ORM::set_db($db);
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testGet()
    {
        $ResultSet = new ResultSet();
        $this->assertTrue(is_array($ResultSet->get_results()));
    }

    public function testConstructor()
    {
        $result_set = ['item' => new stdClass()];
        $ResultSet  = new ResultSet($result_set);
        $this->assertSame($ResultSet->get_results(), $result_set);
    }

    public function testSetResultsAndGetResults()
    {
        $result_set = ['item' => new stdClass()];
        $ResultSet  = new ResultSet();
        $ResultSet->set_results($result_set);
        $this->assertSame($ResultSet->get_results(), $result_set);
    }

    public function testAsArray()
    {
        $result_set = ['item' => new stdClass()];
        $ResultSet  = new ResultSet();
        $ResultSet->set_results($result_set);
        $this->assertSame($ResultSet->as_array(), $result_set);
    }

    public function testAsArrayArgs()
    {
        $item = ORM::_new_row('test')->create([
            'name'  => 'Test1',
            'phone' => '012345678',
            'email' => 'test@gmail.com',
        ]);

        $this->assertSame([
            'name'  => 'Test1',
            'phone' => '012345678',
            'email' => 'test@gmail.com',
        ], $item->as_array());

        $this->assertSame([
            'name'  => 'Test1',
            'phone' => '012345678',
        ], $item->as_array('name', 'phone'));
    }

    public function testCount()
    {
        $result_set = ['item' => new stdClass()];
        $ResultSet  = new ResultSet($result_set);
        $this->assertSame($ResultSet->count(), 1);
        $this->assertSame(count($ResultSet), 1);
    }

    public function testGetIterator()
    {
        $result_set = ['item' => new stdClass()];
        $ResultSet  = new ResultSet($result_set);
        $this->assertTrue(is_a($ResultSet->getIterator(), 'ArrayIterator'));
    }

    public function testForeach()
    {
        $result_set   = ['item' => new stdClass()];
        $ResultSet    = new ResultSet($result_set);
        $return_array = [];
        foreach ($ResultSet as $key => $record) {
            $return_array[$key] = $record;
        }
        $this->assertSame($result_set, $return_array);
    }

    public function testIsFirstAndLast()
    {
        ORM::get_db()->exec(file_get_contents(__DIR__ . '/../models.sql'));

        $result_set = [
            'item1' => Car::create(['name' => 'Car1']),
            'item2' => Car::create(['name' => 'Car2']),
            'item3' => Car::create(['name' => 'Car3']),
        ];
        foreach ($result_set as $car) {
            $car->save();
        }
        $ResultSet = new ResultSet($result_set);
        foreach ($ResultSet as $key => $record) {
            if ($key == 'item1') {
                $this->assertSame(true, $record->isFirstResult());
                $this->assertSame(false, $record->isLastResult());
            }
            if ($key == 'item2') {
                $this->assertSame(false, $record->isFirstResult());
                $this->assertSame(false, $record->isLastResult());
            }
            if ($key == 'item3') {
                $this->assertSame(false, $record->isFirstResult());
                $this->assertSame(true, $record->isLastResult());
            }
        }
    }

    public function testCallingMethods()
    {
        $result_set = [
            'item'  => ORM::_new_row('test'),
            'item2' => ORM::_new_row('test'),
        ];
        $ResultSet = new ResultSet($result_set);
        $ResultSet->set('field', 'value')->set('field2', 'value');

        foreach ($ResultSet as $record) {
            $this->assertTrue(isset($record->field));
            $this->assertSame($record->field, 'value');

            $this->assertTrue(isset($record->field2));
            $this->assertSame($record->field2, 'value');
        }
    }

    public function testCallingMethodsReturnsResultSet()
    {
        $result_set = [
            'item'  => ORM::_new_row('test'),
            'item2' => ORM::_new_row('test'),
        ];
        $ResultSet = new ResultSet($result_set);
        $this->assertSame($ResultSet, $ResultSet->set('field', 'value'));
    }

    public function testCallingMethodReturningNullReturnsTheResultSet()
    {
        $result_set = [
            'item'  => ORM::_new_row('test'),
            'item2' => ORM::_new_row('test'),
        ];
        $ResultSet = new ResultSet($result_set);
        $this->assertSame($ResultSet, $ResultSet->id());
        $this->assertNull($result_set['item']->id());
    }

    public function testCallingSaveThatFailsThrows()
    {
        ORM::get_db()->exec('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE)');
        // The write must report false instead of throwing
        ORM::get_db()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        ORM::_new_row('test')
            ->create(['name' => 'one'])
            ->save();

        $ResultSet = new ResultSet([
            'item'  => ORM::_new_row('test')->create(['name' => 'one']),
            'item2' => ORM::_new_row('test')->create(['name' => 'two']),
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('save on Granada\ORM id NULL failed');
        $ResultSet->save();
    }

    public function testCallingSaveThatFailsNamesTheRowId()
    {
        ORM::get_db()->exec('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE)');
        // The write must report false instead of throwing
        ORM::get_db()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $row = ORM::_new_row('test')
            ->create(['name' => 'one']);
        $row->save();

        ORM::_new_row('test')
            ->create(['name' => 'two'])
            ->save();

        $ResultSet = new ResultSet(['item' => $row]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('save on Granada\ORM id \'1\' failed');
        $ResultSet->set('name', 'two')->save();
    }

    public function testFailedBatchWritesNothing()
    {
        ORM::get_db()->exec('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)');
        // The write must report false instead of throwing
        ORM::get_db()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        ORM::_new_row('test')
            ->create(['name' => 'one'])
            ->save();
        ORM::_new_row('test')
            ->create(['name' => 'blue'])
            ->save();

        try {
            Granada::for_table('test')
                ->find_many()
                ->set('name', 'crimson')
                ->save();
            $this->fail('The failed save should abort the batch');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('save on Granada\ORM id', $e->getMessage());
        }

        $first = Granada::for_table('test')->find_one(1);
        $this->assertSame('one', $first->name);
        $second = Granada::for_table('test')->find_one(2);
        $this->assertSame('blue', $second->name);
    }

    public function testFailedBatchInCallerTransactionRollsBackOuterWork()
    {
        ORM::get_db()->exec('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)');
        // The write must report false instead of throwing
        ORM::get_db()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        ORM::_new_row('test')
            ->create(['name' => 'one'])
            ->save();
        ORM::_new_row('test')
            ->create(['name' => 'blue'])
            ->save();

        try {
            ORM::transaction(function (): void {
                ORM::_new_row('test')
                    ->create(['name' => 'outer'])
                    ->save();
                Granada::for_table('test')
                    ->find_many()
                    ->set('name', 'two')
                    ->save();
            });
            $this->fail('The failed save should abort the transaction');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('save on Granada\ORM id', $e->getMessage());
        }

        $this->assertSame(2, Granada::for_table('test')->count());
        $first = Granada::for_table('test')->find_one(1);
        $this->assertSame('one', $first->name);
        $second = Granada::for_table('test')->find_one(2);
        $this->assertSame('blue', $second->name);
    }

    public function testCallingMethodExceptionPropagates()
    {
        $ResultSet = new ResultSet([
            'item' => ORM::_new_row('test')->create(),
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot save a new record with no fields set');
        $ResultSet->save();
    }

    public function testAsJson()
    {
        $a       = ORM::_new_row('test');
        $a->name = 'Test1';
        $b       = ORM::_new_row('test');
        $b->name = 'Test2';

        $ResultSet = new ResultSet([$a, $b]);
        $json      = $ResultSet->as_json();

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertEquals('Test1', $decoded[0]['name']);
        $this->assertEquals('Test2', $decoded[1]['name']);
    }

    public function testKeys()
    {
        $ResultSet = new ResultSet([
            'a' => ORM::_new_row('test'),
            'b' => ORM::_new_row('test'),
        ]);
        $this->assertSame(['a', 'b'], $ResultSet->keys());
    }

    public function testKeysNumeric()
    {
        $ResultSet = new ResultSet([ORM::_new_row('test'), ORM::_new_row('test')]);
        $this->assertSame([0, 1], $ResultSet->keys());
    }

    public function testFirst()
    {
        $a       = ORM::_new_row('test');
        $a->name = 'First';
        $b       = ORM::_new_row('test');
        $b->name = 'Second';

        $ResultSet = new ResultSet([$a, $b]);
        $first     = $ResultSet->first();
        $this->assertSame('First', $first->name);
    }

    public function testFirstEmpty()
    {
        $ResultSet = new ResultSet();
        $this->assertFalse($ResultSet->first());
    }

    public function testLast()
    {
        $a       = ORM::_new_row('test');
        $a->name = 'First';
        $b       = ORM::_new_row('test');
        $b->name = 'Last';

        $ResultSet = new ResultSet([$a, $b]);
        $last      = $ResultSet->last();
        $this->assertSame('Last', $last->name);
    }

    public function testLastEmpty()
    {
        $ResultSet = new ResultSet();
        $this->assertFalse($ResultSet->last());
    }

    public function testAdd()
    {
        $ResultSet = new ResultSet();
        $item      = ORM::_new_row('test');
        $ResultSet->add($item);
        $this->assertSame($item, $ResultSet[0]);
        $this->assertSame(1, $ResultSet->count());
    }

    public function testAddChaining()
    {
        $ResultSet = new ResultSet();
        $ResultSet
            ->add(ORM::_new_row('test'))
            ->add(ORM::_new_row('test'));
        $this->assertSame(2, $ResultSet->count());
    }

    public function testFirstAndLastWithStringKeys()
    {
        $a       = ORM::_new_row('test');
        $a->name = 'First';
        $b       = ORM::_new_row('test');
        $b->name = 'Middle';
        $c       = ORM::_new_row('test');
        $c->name = 'Last';

        $ResultSet = new ResultSet([
            'alpha' => $a,
            'beta'  => $b,
            'gamma' => $c,
        ]);

        $this->assertSame('First', $ResultSet->first()->name);
        $this->assertSame('Last', $ResultSet->last()->name);
    }

    public function testArrayAccess()
    {
        $ResultSet = new ResultSet();
        $this->assertFalse($ResultSet->offsetExists('key'));
        $this->assertFalse($ResultSet->offsetExists('missing'));

        $a = ORM::_new_row('test');
        $ResultSet->offsetSet('key', $a);
        $this->assertTrue($ResultSet->offsetExists('key'));
        $this->assertSame($a, $ResultSet->offsetGet('key'));

        $b = ORM::_new_row('test');
        $ResultSet->offsetSet(null, $b);
        $this->assertTrue($ResultSet->offsetExists(0));
        $this->assertSame($b, $ResultSet[0]);

        $this->assertSame(2, $ResultSet->count());
        $ResultSet->offsetUnset('key');
        $this->assertSame(1, $ResultSet->count());
        $this->assertFalse($ResultSet->offsetExists('key'));
    }

    public function testHasResults()
    {
        $ResultSet = new ResultSet();
        $this->assertFalse($ResultSet->has_results());

        $ResultSet->add(ORM::_new_row('test'));
        $this->assertTrue($ResultSet->has_results());
    }
}
