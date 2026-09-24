<?php

use Granada\ORM;
use Granada\Model;
use Granada\Orm\ConnectionManager;

class MultipleConnectionsTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate';

    protected function setUp(): void
    {
        $manager = new ConnectionManager('sqlite::memory:');

        // Set up the dummy database connection
        $manager->set_db(new MockPDO('sqlite::memory:'));
        $manager->set_db(new MockDifferentPDO('sqlite::memory:'), self::ALTERNATE);

        // Enable logging
        $manager->configure('logging', true);
        $manager->configure('logging', true, self::ALTERNATE);

        ORM::set_connection_manager($manager);
    }

    public function testMultipleConnections()
    {
        $simple    = Model::factory('Simple')->find_one(1);
        $statement = ORM::get_last_statement();
        $this->assertInstanceOf('MockPDOStatement', $statement);

        $simple = Model::factory('Simple', self::ALTERNATE); // Change the object's default connection
        $simple->find_one(1);
        $statement = ORM::get_last_statement();
        $this->assertInstanceOf('MockDifferentPDOStatement', $statement);

        $temp      = Model::factory('Simple', self::ALTERNATE)->find_one(1);
        $statement = ORM::get_last_statement();
        $this->assertInstanceOf('MockDifferentPDOStatement', $statement);
    }

    public function testCustomConnectionName()
    {
        $person3   = Model::factory('ModelWithCustomConnection')->find_one(1);
        $statement = ORM::get_last_statement();
        $this->assertInstanceOf('MockDifferentPDOStatement', $statement);
    }
}
