<?php

use Granada\ORM;
use Granada\Orm\Dialect;
use PHPUnit\Framework\Attributes\DataProvider;

class DialectTest extends \PHPUnit\Framework\TestCase
{
    public static function driverFamilies()
    {
        return [
            'mysql'    => ['mysql', '`', ORM::LIMIT_STYLE_LIMIT],
            'sqlite'   => ['sqlite', '`', ORM::LIMIT_STYLE_LIMIT],
            'unknown'  => [null, '`', ORM::LIMIT_STYLE_LIMIT],
            'pgsql'    => ['pgsql', '"', ORM::LIMIT_STYLE_LIMIT],
            'sqlsrv'   => ['sqlsrv', '"', ORM::LIMIT_STYLE_TOP_N],
            'dblib'    => ['dblib', '"', ORM::LIMIT_STYLE_TOP_N],
            'mssql'    => ['mssql', '"', ORM::LIMIT_STYLE_TOP_N],
            'sybase'   => ['sybase', '"', ORM::LIMIT_STYLE_TOP_N],
            'firebird' => ['firebird', '"', ORM::LIMIT_STYLE_LIMIT],
        ];
    }

    #[DataProvider('driverFamilies')]
    public function testFamilyFacts($driver_name, $quote_character, $limit_clause_style)
    {
        $dialect = Dialect::for_driver($driver_name);

        $this->assertSame($quote_character, $dialect->quote_character);
        $this->assertSame($limit_clause_style, $dialect->limit_clause_style);
    }

    /**
     * The pagination fragment for a limit alone, an offset alone,
     * and both, with the caller's ordering present.
     */
    public static function paginationShapes()
    {
        return [
            // The LIMIT dialects render LIMIT and OFFSET as two fragments.
            'mysql'   => ['mysql', 'LIMIT 5', 'OFFSET 10', 'LIMIT 5 OFFSET 10'],
            'sqlite'  => ['sqlite', 'LIMIT 5', 'OFFSET 10', 'LIMIT 5 OFFSET 10'],
            'unknown' => [null, 'LIMIT 5', 'OFFSET 10', 'LIMIT 5 OFFSET 10'],
            'pgsql'   => ['pgsql', 'LIMIT 5', 'OFFSET 10', 'LIMIT 5 OFFSET 10'],
            // SQL Server reserves a limit's rows with TOP, so a
            // limit alone renders no fragment and a limit with an
            // offset renders the OFFSET and FETCH.
            'sqlsrv' => ['sqlsrv', '', 'OFFSET 10 ROWS', 'OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY'],
            'dblib'  => ['dblib', '', 'OFFSET 10 ROWS', 'OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY'],
            'mssql'  => ['mssql', '', 'OFFSET 10 ROWS', 'OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY'],
            'sybase' => ['sybase', '', 'OFFSET 10 ROWS', 'OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY'],
            // Firebird renders a one-based ROWS range: a limit of 5
            // with an offset of 10 is rows 11 through 15, and an
            // offset alone ends the range at PHP_INT_MAX.
            'firebird' => ['firebird', 'ROWS 5', 'ROWS 11 TO 9223372036854775807', 'ROWS 11 TO 15'],
        ];
    }

    #[DataProvider('paginationShapes')]
    public function testPaginationFragmentShapes($driver_name, $limit_only, $offset_only, $limit_and_offset)
    {
        $dialect = Dialect::for_driver($driver_name);

        $this->assertSame($limit_only, $dialect->pagination_fragment(5, null, true));
        $this->assertSame($offset_only, $dialect->pagination_fragment(null, 10, true));
        $this->assertSame($limit_and_offset, $dialect->pagination_fragment(5, 10, true));
    }

    public function testNoLimitOrOffsetRendersEmpty()
    {
        $dialect = Dialect::for_driver('firebird');

        $this->assertSame('', $dialect->pagination_fragment(null, null, false));
        $this->assertSame('', $dialect->select_top_fragment(null));
    }

    public function testExplicitConfigOverridesAreHonored()
    {
        $dialect = Dialect::for_driver('mysql', '`', ORM::LIMIT_STYLE_TOP_N);

        $this->assertSame('TOP 5 ', $dialect->select_top_fragment(5));
        $this->assertSame('', $dialect->pagination_fragment(5, null, true));
        $this->assertSame('OFFSET 10', $dialect->pagination_fragment(5, 10, true));

        $dialect = Dialect::for_driver('sqlsrv', '"', ORM::LIMIT_STYLE_LIMIT);

        $this->assertSame('LIMIT 5', $dialect->pagination_fragment(5, null, true));
        $this->assertSame('LIMIT 5 OFFSET 10', $dialect->pagination_fragment(5, 10, true));
        $this->assertSame('', $dialect->select_top_fragment(5));
    }

    public function testSqlsrvSuppliesOrderingForPaginationWithoutOne()
    {
        $dialect = Dialect::for_driver('sqlsrv');

        $this->assertSame(
            'ORDER BY (SELECT NULL) OFFSET 10 ROWS',
            $dialect->pagination_fragment(null, 10, false)
        );
        $this->assertSame(
            'ORDER BY (SELECT NULL) OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY',
            $dialect->pagination_fragment(5, 10, false)
        );
    }

    public function testTopStyleDropsTopWhenAnOffsetIsSet()
    {
        $dialect = Dialect::for_driver('sqlsrv');

        $this->assertSame('TOP 5 ', $dialect->select_top_fragment(5));
        $this->assertSame('', $dialect->select_top_fragment(5, 10));
    }

    public function testInsertReportingPerFamily()
    {
        $this->assertSame('', Dialect::for_driver('mysql')->insert_returning_fragment('id'));
        $this->assertSame('RETURNING "id"', Dialect::for_driver('pgsql')->insert_returning_fragment('id'));
        $this->assertSame('', Dialect::for_driver('sqlsrv')->insert_returning_fragment('id'));
        $this->assertSame('', Dialect::for_driver('firebird')->insert_returning_fragment('id'));
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

    public function testOrderByListEmulatesFieldSemanticsOnSqlite()
    {
        $this->assertSame(
            'CASE `x` WHEN 1 THEN 1 WHEN 2 THEN 2 ELSE 0 END',
            Dialect::for_driver('sqlite')->order_by_field_expression('`x`', [1, 2])
        );
    }

    public function testOrderByRandUsesRandomOnSqlite()
    {
        $this->assertSame(
            'RANDOM()',
            Dialect::for_driver('sqlite')->order_by_random_expression()
        );
    }

    public function testOrderByRandKeepsRandElsewhere()
    {
        $this->assertSame('RAND()', Dialect::for_driver('mysql')->order_by_random_expression());
        $this->assertSame('RAND()', Dialect::for_driver('pgsql')->order_by_random_expression());
        $this->assertSame('RAND()', Dialect::for_driver('sqlsrv')->order_by_random_expression());
        $this->assertSame('RAND()', Dialect::for_driver('firebird')->order_by_random_expression());
    }

    public function testQuotingEscapesEmbeddedQuoteCharacters()
    {
        $this->assertSame('`we``ird`', Dialect::for_driver('mysql')->quote_identifier('we`ird'));
        $this->assertSame('"we""ird"', Dialect::for_driver('pgsql')->quote_identifier('we"ird'));
        $this->assertSame('`person`.*', Dialect::for_driver('mysql')->quote_identifier('person.*'));
    }
}
