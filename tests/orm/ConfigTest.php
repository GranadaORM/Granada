<?php

use Granada\Granada;
use Granada\ORM;
use Granada\Orm\ConnectionManager;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $manager = new ConnectionManager('sqlite::memory:');
        $manager->set_db(new PDO('sqlite::memory:'));
        $manager->configure('logging', true);
        $manager->configure('id_column', 'primary_key');

        ORM::set_connection_manager($manager);
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testSettingIdColumn()
    {
        $statement = Granada::for_table('widget')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`primary_key` = ? LIMIT 1', $statement->query);
        $this->assertSame([5], $statement->values);
    }

    public function testSettingIdColumnOverridesOne()
    {
        ORM::configure('id_column_overrides', [
            'widget' => 'widget_id',
        ]);

        $statement = Granada::for_table('widget')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`widget_id` = ? LIMIT 1', $statement->query);
    }

    public function testSettingIdColumnOverridesTwo()
    {
        ORM::configure('id_column_overrides', [
            'widget_handle' => 'widget_handle_id',
        ]);

        $statement = Granada::for_table('widget_handle')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame(
            'SELECT * FROM `widget_handle` WHERE `widget_handle`.`widget_handle_id` = ? LIMIT 1',
            $statement->query
        );
    }

    public function testSettingIdColumnOverridesThree()
    {
        ORM::configure('id_column_overrides', [
            'widget'        => 'widget_id',
            'widget_handle' => 'widget_handle_id',
        ]);

        $statement = Granada::for_table('widget_nozzle')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame(
            'SELECT * FROM `widget_nozzle` WHERE `widget_nozzle`.`primary_key` = ? LIMIT 1',
            $statement->query
        );
    }

    public function testInstanceIdColumnOne()
    {
        $statement = Granada::for_table('widget')
            ->use_id_column('new_id')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `widget` WHERE `widget`.`new_id` = ? LIMIT 1', $statement->query);
    }

    public function testInstanceIdColumnTwo()
    {
        ORM::configure('id_column_overrides', [
            'widget_handle' => 'widget_handle_id',
        ]);

        $statement = Granada::for_table('widget_handle')
            ->use_id_column('new_id')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame(
            'SELECT * FROM `widget_handle` WHERE `widget_handle`.`new_id` = ? LIMIT 1',
            $statement->query
        );
    }

    public function testInstanceIdColumnThree()
    {
        $statement = Granada::for_table('widget_nozzle')
            ->use_id_column('new_id')
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame(
            'SELECT * FROM `widget_nozzle` WHERE `widget_nozzle`.`new_id` = ? LIMIT 1',
            $statement->query
        );
    }

    public function testGetConfig()
    {
        $this->assertTrue(ORM::get_config('logging'));
        ORM::configure('logging', false);
        $this->assertFalse(ORM::get_config('logging'));
        ORM::configure('logging', true);
    }

    public function testGetConfigArray()
    {
        $expected = [
            'connection_string'           => 'sqlite::memory:',
            'id_column'                   => 'primary_key',
            'id_column_overrides'         => [],
            'error_mode'                  => PDO::ERRMODE_EXCEPTION,
            'username'                    => null,
            'password'                    => null,
            'driver_options'              => null,
            'identifier_quote_character'  => '`',
            'logging'                     => true,
            'logger'                      => null,
            'caching'                     => false,
            'return_result_sets'          => true, // true by default in Granada
            'limit_clause_style'          => 'limit',
            'driver_name'                 => 'sqlite',
            'find_many_primary_id_as_key' => true,
        ];
        $this->assertEquals($expected, ORM::get_config());
    }
}
