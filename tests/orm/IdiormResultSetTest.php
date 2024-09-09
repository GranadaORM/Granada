<?php

use Granada\ORM;
use Granada\ResultSet;

class ResultSetTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @before
     */
    protected function beforeTest()
    {
        // Enable logging
        ORM::configure('logging', true);

        // Set up the dummy database connection
        $db = new PDO('sqlite::memory:');
        ORM::set_db($db);
    }

    /**
     * @after
     */
    protected function afterTest()
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
}
