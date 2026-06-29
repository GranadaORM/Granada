<?php

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
        $item = ORM::for_table('test')->create([
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
        $result_set = [
            'item1' => new stdClass(),
            'item2' => new stdClass(),
            'item3' => new stdClass(),
        ];
        $ResultSet = new ResultSet($result_set);
        foreach ($ResultSet as $key => $record) {
            $return_array[$key] = $record;
            if ($key == 'item1') {
                $this->assertSame(true, $record->_isFirstResult);
                $this->assertSame(false, $record->_isLastResult);
            }
            if ($key == 'item2') {
                $this->assertSame(false, $record->_isFirstResult);
                $this->assertSame(false, $record->_isLastResult);
            }
            if ($key == 'item3') {
                $this->assertSame(false, $record->_isFirstResult);
                $this->assertSame(true, $record->_isLastResult);
            }
        }
    }

    public function testCallingMethods()
    {
        $result_set = ['item' => ORM::for_table('test'), 'item2' => ORM::for_table('test')];
        $ResultSet  = new ResultSet($result_set);
        $ResultSet->set('field', 'value')->set('field2', 'value');

        foreach ($ResultSet as $record) {
            $this->assertTrue(isset($record->field));
            $this->assertSame($record->field, 'value');

            $this->assertTrue(isset($record->field2));
            $this->assertSame($record->field2, 'value');
        }
    }

    public function testAsJson()
    {
        $a       = ORM::for_table('test');
        $a->name = 'Test1';
        $b       = ORM::for_table('test');
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
        $ResultSet = new ResultSet(['a' => ORM::for_table('test'), 'b' => ORM::for_table('test')]);
        $this->assertSame(['a', 'b'], $ResultSet->keys());
    }

    public function testKeysNumeric()
    {
        $ResultSet = new ResultSet([ORM::for_table('test'), ORM::for_table('test')]);
        $this->assertSame([0, 1], $ResultSet->keys());
    }

    public function testFirst()
    {
        $a       = ORM::for_table('test');
        $a->name = 'First';
        $b       = ORM::for_table('test');
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
        $a       = ORM::for_table('test');
        $a->name = 'First';
        $b       = ORM::for_table('test');
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
        $item      = ORM::for_table('test');
        $ResultSet->add($item);
        $this->assertSame($item, $ResultSet[0]);
        $this->assertSame(1, $ResultSet->count());
    }

    public function testAddChaining()
    {
        $ResultSet = new ResultSet();
        $ResultSet
            ->add(ORM::for_table('test'))
            ->add(ORM::for_table('test'));
        $this->assertSame(2, $ResultSet->count());
    }

    public function testManualIteration()
    {
        $a       = ORM::for_table('test');
        $a->name = 'First';
        $b       = ORM::for_table('test');
        $b->name = 'Middle';
        $c       = ORM::for_table('test');
        $c->name = 'Last';

        $ResultSet = new ResultSet(['alpha' => $a, 'beta' => $b, 'gamma' => $c]);

        $this->assertSame('First', $ResultSet->first()->name);
        $this->assertSame('Last', $ResultSet->last()->name);

        $ResultSet->rewind();
        $this->assertSame('alpha', $ResultSet->key());
        $this->assertSame('First', $ResultSet->current()->name);

        $ResultSet->next();
        $this->assertSame('beta', $ResultSet->key());
        $this->assertSame('Middle', $ResultSet->current()->name);

        $ResultSet->next();
        $this->assertSame('gamma', $ResultSet->key());
        $this->assertSame('Last', $ResultSet->current()->name);

        $this->assertFalse($ResultSet->next());
    }

    public function testValidCallsIdWhichDoesNotExistOnResultSet()
    {
        $this->expectException(\TypeError::class);

        $ResultSet = new ResultSet([ORM::for_table('test')]);
        $ResultSet->rewind();
        $ResultSet->valid();
    }

    public function testArrayAccess()
    {
        $ResultSet = new ResultSet();
        $this->assertFalse($ResultSet->offsetExists('key'));
        $this->assertFalse($ResultSet->offsetExists('missing'));

        $a = ORM::for_table('test');
        $ResultSet->offsetSet('key', $a);
        $this->assertTrue($ResultSet->offsetExists('key'));
        $this->assertSame($a, $ResultSet->offsetGet('key'));

        $b = ORM::for_table('test');
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

        $ResultSet->add(ORM::for_table('test'));
        $this->assertTrue($ResultSet->has_results());
    }
}
