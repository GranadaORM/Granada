<?php

use Granada\Granada;
use Granada\ORM;

class QueryBuilderMssqlTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_db();
        ORM::configure('driver_name', 'sqlsrv');
        ORM::configure('identifier_quote_character', '"');
        ORM::configure('limit_clause_style', ORM::LIMIT_STYLE_TOP_N);
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testLimit()
    {
        $statement = Granada::for_table('widget')
            ->limit(5)
            ->to_statement();

        $this->assertSame('SELECT TOP 5 * FROM "widget"', $statement->query);
    }

    public function testLimitOne()
    {
        $statement = Granada::for_table('widget')
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT TOP 1 * FROM "widget"', $statement->query);
    }

    public function testGetSelectQuery()
    {
        $query = Granada::for_table('widget')->limit(5);

        $this->assertSame('SELECT TOP 5 * FROM "widget"', $query->get_select_query());
    }
}
