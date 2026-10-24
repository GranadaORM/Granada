<?php

use Granada\Granada;
use Granada\Orm\ConnectionManager;
use Granada\ORM;

class MultipleConnectionTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate'; // Used as name of alternate connection

    protected function setUp(): void
    {
        $manager = new ConnectionManager('sqlite::memory:');

        // Two real databases with the same schema. Every widget in the
        // alternate is renamed, so a result says which one ran the query.
        $default   = $this->seeded_connection();
        $alternate = $this->seeded_connection();
        $alternate->exec("UPDATE widget SET name = 'Alternate1'");

        $manager->set_db($default);
        $manager->set_db($alternate, self::ALTERNATE);

        ORM::set_connection_manager($manager);
    }

    /**
     * A handle holding the shared schema and its rows.
     */
    private function seeded_connection(): PDO
    {
        $connection = new PDO('sqlite::memory:');
        $connection->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        return $connection;
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testEachConnectionKeepsItsOwnHandle()
    {
        $this->assertNotSame(ORM::get_db(), ORM::get_db(self::ALTERNATE));
        $this->assertSame(ORM::get_db(), ORM::get_db(ORM::DEFAULT_CONNECTION));
    }

    public function testARowComesFromTheConnectionTheQueryNames()
    {
        $this->assertSame('Widget1', Granada::for_table('widget')
            ->find_one(1)
            ->name);
        $this->assertSame('Alternate1', Granada::for_table('widget', self::ALTERNATE)
            ->find_one(1)
            ->name);
    }

    public function testGetLastStatementIsTheStatementThatJustRan()
    {
        Granada::for_table('widget')->find_one(1);

        $this->assertInstanceOf(PDOStatement::class, ORM::get_last_statement());
    }
}
