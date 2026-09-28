<?php

use Granada\ORM;
use Granada\Orm\Dialect;

class DialectTest extends \PHPUnit\Framework\TestCase
{
    public function driverFamilies()
    {
        return [
            'mysql'    => ['mysql', '`', ORM::LIMIT_STYLE_LIMIT, 'LIMIT', 'OFFSET'],
            'sqlite'   => ['sqlite', '`', ORM::LIMIT_STYLE_LIMIT, 'LIMIT', 'OFFSET'],
            'unknown'  => [null, '`', ORM::LIMIT_STYLE_LIMIT, 'LIMIT', 'OFFSET'],
            'pgsql'    => ['pgsql', '"', ORM::LIMIT_STYLE_LIMIT, 'LIMIT', 'OFFSET'],
            'sqlsrv'   => ['sqlsrv', '"', ORM::LIMIT_STYLE_TOP_N, '', 'OFFSET'],
            'dblib'    => ['dblib', '"', ORM::LIMIT_STYLE_TOP_N, '', 'OFFSET'],
            'mssql'    => ['mssql', '"', ORM::LIMIT_STYLE_TOP_N, '', 'OFFSET'],
            'sybase'   => ['sybase', '"', ORM::LIMIT_STYLE_TOP_N, '', 'OFFSET'],
            'firebird' => ['firebird', '"', ORM::LIMIT_STYLE_LIMIT, 'ROWS', 'TO'],
        ];
    }

    /**
     * @dataProvider driverFamilies
     */
    public function testFamilyFacts($driver_name, $quote_character, $limit_clause_style, $limit_keyword, $offset_keyword)
    {
        $dialect = Dialect::for_driver($driver_name);

        $this->assertSame($quote_character, $dialect->quote_character);
        $this->assertSame($limit_clause_style, $dialect->limit_clause_style);
        $this->assertSame($limit_keyword === '' ? '' : "{$limit_keyword} 5", $dialect->limit_fragment(5));
        $this->assertSame("{$offset_keyword} 10", $dialect->offset_fragment(10));
        $this->assertSame($limit_clause_style === ORM::LIMIT_STYLE_TOP_N ? 'TOP 5 ' : '', $dialect->select_top_fragment(5));
    }

    public function testNoLimitOrOffsetRendersEmpty()
    {
        $dialect = Dialect::for_driver('firebird');

        $this->assertSame('', $dialect->limit_fragment(null));
        $this->assertSame('', $dialect->offset_fragment(null));
        $this->assertSame('', $dialect->select_top_fragment(null));
    }

    public function testExplicitConfigOverridesAreHonored()
    {
        $dialect = Dialect::for_driver('mysql', '`', ORM::LIMIT_STYLE_TOP_N);

        $this->assertSame('TOP 5 ', $dialect->select_top_fragment(5));
        $this->assertSame('', $dialect->limit_fragment(5));
        $this->assertSame('OFFSET 10', $dialect->offset_fragment(10));

        $dialect = Dialect::for_driver('sqlsrv', '"', ORM::LIMIT_STYLE_LIMIT);

        $this->assertSame('LIMIT 5', $dialect->limit_fragment(5));
        $this->assertSame('', $dialect->select_top_fragment(5));
    }

    public function testInsertReportingPerFamily()
    {
        $this->assertSame('', Dialect::for_driver('mysql')->insert_returning_fragment('id'));
        $this->assertSame('RETURNING "id"', Dialect::for_driver('pgsql')->insert_returning_fragment('id'));
        $this->assertSame('', Dialect::for_driver('sqlsrv')->insert_returning_fragment('id'));
        $this->assertSame('', Dialect::for_driver('firebird')->insert_returning_fragment('id'));
    }

    public function testInsertUpdateAppliesToMysqlFamilyOnly()
    {
        $fields = ['`a`', '`b`'];

        $this->assertSame(
            ' ON DUPLICATE KEY UPDATE  `a` = ?, `b` = ? ',
            Dialect::for_driver('mysql')->insert_update_fragment($fields)
        );
        $this->assertSame('', Dialect::for_driver('pgsql')->insert_update_fragment($fields));
        $this->assertSame('', Dialect::for_driver('sqlsrv')->insert_update_fragment($fields));
        $this->assertSame('', Dialect::for_driver('firebird')->insert_update_fragment($fields));
    }

    public function testOrderByListUsesFieldOnMysqlFamily()
    {
        $this->assertSame(
            'FIELD(`x`,1,2)',
            Dialect::for_driver('mysql')->order_by_field_expression('`x`', [1, 2])
        );
    }

    public function testOrderByListEmulatesFieldSemanticsElsewhere()
    {
        $this->assertSame(
            'CASE "x" WHEN 1 THEN 1 WHEN 2 THEN 2 ELSE 0 END',
            Dialect::for_driver('pgsql')->order_by_field_expression('"x"', [1, 2])
        );
        $this->assertSame(
            'CASE "x" WHEN \'a\' THEN 1 WHEN \'b\' THEN 2 ELSE 0 END',
            Dialect::for_driver('firebird')->order_by_field_expression('"x"', ["'a'", "'b'"])
        );
    }

    public function testQuotingEscapesEmbeddedQuoteCharacters()
    {
        $this->assertSame('`we``ird`', Dialect::for_driver('mysql')->quote_identifier('we`ird'));
        $this->assertSame('"we""ird"', Dialect::for_driver('pgsql')->quote_identifier('we"ird'));
        $this->assertSame('`person`.*', Dialect::for_driver('mysql')->quote_identifier('person.*'));
    }
}
