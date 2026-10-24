<?php

use Granada\Granada;
use Granada\Orm\ConnectionManager;
use Granada\ORM;

class MultipleConnectionsTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate';

    protected function setUp(): void
    {
        $manager = new ConnectionManager('sqlite::memory:');

        // Two real databases with the same schema. The rows in each are
        // renamed, so a result says which handle the query reached.
        $default   = $this->seeded_connection('Default');
        $alternate = $this->seeded_connection('Alternate');

        $manager->set_db($default);
        $manager->set_db($alternate, self::ALTERNATE);

        ORM::set_connection_manager($manager);
    }

    /**
     * A handle holding the shared schema, with its rows renamed.
     */
    private function seeded_connection(string $prefix): PDO
    {
        $connection = new PDO('sqlite::memory:');
        $connection->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
        $connection->exec("UPDATE simple SET name = '{$prefix}1'");
        $connection->exec("UPDATE model_with_custom_connection SET name = '{$prefix}Custom1'");

        return $connection;
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testAModelQueryUsesTheModelsConnection()
    {
        $row = Simple::q()->find_one(1);

        $this->assertSame('Default1', $row->name);
    }

    public function testTheTableFirstDoorTakesAConnectionName()
    {
        $row = Granada::for_table('simple', self::ALTERNATE)->find_one(1);

        $this->assertSame('Alternate1', $row->name);
    }

    public function testAModelCanNameItsOwnConnection()
    {
        $row = ModelWithCustomConnection::q()->find_one(1);

        $this->assertSame('AlternateCustom1', $row->name);
    }
}
