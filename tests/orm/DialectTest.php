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
        $dialect = Dialect::forDriver($driver_name);

        $this->assertSame($quote_character, $dialect->quote_character);
        $this->assertSame($limit_clause_style, $dialect->limit_clause_style);
        $this->assertSame($limit_keyword === '' ? '' : "{$limit_keyword} 5", $dialect->limitFragment(5));
        $this->assertSame("{$offset_keyword} 10", $dialect->offsetFragment(10));
        $this->assertSame($limit_clause_style === ORM::LIMIT_STYLE_TOP_N ? 'TOP 5 ' : '', $dialect->selectTopFragment(5));
    }

    public function testNoLimitOrOffsetRendersEmpty()
    {
        $dialect = Dialect::forDriver('firebird');

        $this->assertSame('', $dialect->limitFragment(null));
        $this->assertSame('', $dialect->offsetFragment(null));
        $this->assertSame('', $dialect->selectTopFragment(null));
    }

    public function testExplicitConfigOverridesAreHonored()
    {
        $dialect = Dialect::forDriver('mysql', '`', ORM::LIMIT_STYLE_TOP_N);

        $this->assertSame('TOP 5 ', $dialect->selectTopFragment(5));
        $this->assertSame('', $dialect->limitFragment(5));
        $this->assertSame('OFFSET 10', $dialect->offsetFragment(10));

        $dialect = Dialect::forDriver('sqlsrv', '"', ORM::LIMIT_STYLE_LIMIT);

        $this->assertSame('LIMIT 5', $dialect->limitFragment(5));
        $this->assertSame('', $dialect->selectTopFragment(5));
    }

    public function testInsertReportingPerFamily()
    {
        $this->assertSame('', Dialect::forDriver('mysql')->insertReturningFragment('id'));
        $this->assertSame('RETURNING "id"', Dialect::forDriver('pgsql')->insertReturningFragment('id'));
        $this->assertSame('', Dialect::forDriver('sqlsrv')->insertReturningFragment('id'));
        $this->assertSame('', Dialect::forDriver('firebird')->insertReturningFragment('id'));
    }

    public function testInsertUpdateAppliesToMysqlFamilyOnly()
    {
        $fields = ['`a`', '`b`'];

        $this->assertSame(
            ' ON DUPLICATE KEY UPDATE  `a` = ?, `b` = ? ',
            Dialect::forDriver('mysql')->insertUpdateFragment($fields)
        );
        $this->assertSame('', Dialect::forDriver('pgsql')->insertUpdateFragment($fields));
        $this->assertSame('', Dialect::forDriver('sqlsrv')->insertUpdateFragment($fields));
        $this->assertSame('', Dialect::forDriver('firebird')->insertUpdateFragment($fields));
    }

    public function testOrderByListUsesFieldOnMysqlFamily()
    {
        $this->assertSame(
            'FIELD(`x`,1,2)',
            Dialect::forDriver('mysql')->orderByFieldExpression('`x`', [1, 2])
        );
    }

    public function testOrderByListEmulatesFieldSemanticsElsewhere()
    {
        $this->assertSame(
            'CASE "x" WHEN 1 THEN 1 WHEN 2 THEN 2 ELSE 0 END',
            Dialect::forDriver('pgsql')->orderByFieldExpression('"x"', [1, 2])
        );
        $this->assertSame(
            'CASE "x" WHEN \'a\' THEN 1 WHEN \'b\' THEN 2 ELSE 0 END',
            Dialect::forDriver('firebird')->orderByFieldExpression('"x"', ["'a'", "'b'"])
        );
    }

    public function testQuotingEscapesEmbeddedQuoteCharacters()
    {
        $this->assertSame('`we``ird`', Dialect::forDriver('mysql')->quoteIdentifier('we`ird'));
        $this->assertSame('"we""ird"', Dialect::forDriver('pgsql')->quoteIdentifier('we"ird'));
        $this->assertSame('`person`.*', Dialect::forDriver('mysql')->quoteIdentifier('person.*'));
    }
}
