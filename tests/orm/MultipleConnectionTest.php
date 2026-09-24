<?php

use Granada\ORM;
use Granada\Orm\ConnectionManager;

class MultipleConnectionTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate'; // Used as name of alternate connection

    protected function setUp(): void
    {
        $manager = new ConnectionManager('sqlite::memory:');

        // Set up the dummy database connections
        $manager->set_db(new MockPDO('sqlite::memory:'));
        $manager->set_db(new MockDifferentPDO('sqlite::memory:'), self::ALTERNATE);

        // Enable logging
        $manager->configure('logging', true);
        $manager->configure('logging', true, self::ALTERNATE);

        ORM::set_connection_manager($manager);
    }

    public function testMultiplePdoConnections()
    {
        $this->assertInstanceOf('MockPDO', ORM::get_db());
        $this->assertInstanceOf('MockPDO', ORM::get_db(ORM::DEFAULT_CONNECTION));
        $this->assertInstanceOf('MockDifferentPDO', ORM::get_db(self::ALTERNATE));
    }

    public function testRawExecuteOverAlternateConnection()
    {
        $expected = 'SELECT * FROM `foo`';
        ORM::raw_execute('SELECT * FROM `foo`', [], self::ALTERNATE);

        $this->assertEquals($expected, ORM::get_last_query(self::ALTERNATE));
    }

    public function testFindOneOverDifferentConnections()
    {
        ORM::for_table('widget')->find_one();
        $statementOne = ORM::get_last_statement();
        $this->assertInstanceOf('MockPDOStatement', $statementOne);

        ORM::for_table('person', self::ALTERNATE)->find_one();
        $statementOne = ORM::get_last_statement(); // get_statement is *not* per connection
        $this->assertInstanceOf('MockDifferentPDOStatement', $statementOne);

        $expected = 'SELECT * FROM `widget` LIMIT 1';
        $this->assertNotEquals($expected, ORM::get_last_query()); // Because get_last_query() is across *all* connections
        $this->assertEquals($expected, ORM::get_last_query(ORM::DEFAULT_CONNECTION));

        $expectedToo = 'SELECT * FROM `person` LIMIT 1';
        $this->assertEquals($expectedToo, ORM::get_last_query(self::ALTERNATE));
    }
}
