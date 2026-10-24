<?php

use Granada\Granada;
use Granada\ORM;

class CacheTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate'; // Used as name of alternate connection

    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        ORM::set_db(new PDO('sqlite::memory:'), self::ALTERNATE);
        ORM::get_db(self::ALTERNATE)->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));

        ORM::configure('logging', true);
        ORM::configure('logging', true, self::ALTERNATE);
        ORM::configure('caching', true);
        ORM::configure('caching', true, self::ALTERNATE);
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testQueryGenerationOnlyOccursOnce()
    {
        Granada::for_table('widget')
            ->where('name', 'Widget1')
            ->where('gadget_id', 1)
            ->find_one();
        Granada::for_table('widget')
            ->where('name', 'Widget2')
            ->where('gadget_id', 1)
            ->find_one();

        $statements = count(ORM::get_query_log());

        Granada::for_table('widget')
            ->where('name', 'Widget1')
            ->where('gadget_id', 1)
            ->find_one(); // this shouldn't run a query!

        $this->assertCount($statements, ORM::get_query_log());
    }

    public function testQueryGenerationOnlyOccursOnceWithMultipleConnections()
    {
        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget3')
            ->where('gadget_id', 2)
            ->find_one();
        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget4')
            ->where('gadget_id', 4)
            ->find_one();

        $statements = count(ORM::get_query_log(self::ALTERNATE));

        Granada::for_table('widget', self::ALTERNATE)
            ->where('name', 'Widget3')
            ->where('gadget_id', 2)
            ->find_one(); // this shouldn't run a query!

        $this->assertCount($statements, ORM::get_query_log(self::ALTERNATE));
    }
}
