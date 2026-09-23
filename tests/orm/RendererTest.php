<?php

use Granada\ORM;
use Granada\Orm\Aggregate;
use Granada\Orm\BulkDeleteSpec;
use Granada\Orm\Condition;
use Granada\Orm\Dialect;
use Granada\Orm\JoinSource;
use Granada\Orm\Renderer;
use Granada\Orm\SelectSpec;
use Granada\Orm\Term;
use Granada\Orm\WriteSpec;

class RendererTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    private function selectSpec(array $overrides = []): SelectSpec
    {
        $defaults = [
            'table_name' => 'person',
            'dialect'    => Dialect::forDriver(null),
        ];

        return new SelectSpec(...array_merge($defaults, $overrides));
    }

    private function writeSpec(array $overrides = []): WriteSpec
    {
        $defaults = [
            'table_name' => 'person',
            'id_column'  => 'id',
            'dialect'    => Dialect::forDriver(null),
        ];

        return new WriteSpec(...array_merge($defaults, $overrides));
    }

    private function bulkDeleteSpec(array $overrides = []): BulkDeleteSpec
    {
        $defaults = [
            'table_name' => 'person',
            'target'     => '',
            'dialect'    => Dialect::forDriver(null),
        ];

        return new BulkDeleteSpec(...array_merge($defaults, $overrides));
    }

    public function testSelectAssemblesAllClausesInOrder()
    {
        $spec = $this->selectSpec([
            'result_columns'   => ['`name`', 'COUNT(*) AS `n`', new Aggregate('MAX', 'age', 'oldest')],
            'table_alias'      => 'p',
            'distinct'         => true,
            'join_sources'     => [new JoinSource('LEFT', 'address', null, ['p.id', '=', 'address.person_id'])],
            'where_conditions' => [
                Condition::raw('`p`.`age` > ?', [17]),
                Condition::compare('p.name', '=', 'fred'),
                Condition::isNull('p.deleted'),
            ],
            'group_by'          => [Term::column('p.name')],
            'having_conditions' => [Condition::raw('COUNT(*) > ?', [1])],
            'order_by'          => [Term::column('p.name', 'ASC')],
            'limit'             => 5,
            'offset'            => 10,
        ]);

        $statement = Renderer::select($spec);

        $this->assertSame(
            'SELECT DISTINCT `name`, COUNT(*) AS `n`, MAX(`age`) AS `oldest` FROM `person` `p`'
            . ' LEFT JOIN `address` ON `p`.`id` = `address`.`person_id`'
            . ' WHERE `p`.`age` > ? AND `p`.`name` = ? AND `p`.`deleted` IS NULL'
            . ' GROUP BY `p`.`name` HAVING COUNT(*) > ?'
            . ' ORDER BY `p`.`name` ASC LIMIT 5 OFFSET 10',
            $statement->query
        );
        $this->assertSame([17, 'fred', 1], $statement->values);
    }

    public function testSelectRendersAnyIsOrNullAndInConditions()
    {
        $spec = $this->selectSpec([
            'where_conditions' => [
                Condition::anyIs([
                    [Condition::compare('name', '=', 'Joe'), Condition::in('age', [18, 19])],
                    [Condition::isNotNull('name'), Condition::notInSubquery('id', 'SELECT id FROM other')],
                ]),
                Condition::orNull('score', '>=', 5),
                Condition::notInOrNull('tag', ['a', 'b']),
            ],
            'order_by' => [Term::natural('name', 'DESC'), Term::byFieldList('id', ['3', '1'])],
            'group_by' => [Term::expression('`age`')],
        ]);

        $statement = Renderer::select($spec);

        $this->assertSame(
            'SELECT * FROM `person`'
            . ' WHERE (( `name` = ? AND `age` IN (?, ?) ) OR ( `name` IS NOT NULL AND `id` NOT IN (SELECT id FROM other) ))'
            . ' AND ( `score` >= ? OR `score` IS NULL )'
            . ' AND ( `tag` NOT IN (?, ?) OR `tag` IS NULL )'
            . ' GROUP BY `age`'
            . ' ORDER BY LENGTH(`name`), `name` DESC, FIELD(`id`,3,1)',
            $statement->query
        );
        $this->assertSame(['Joe', 18, 19, 5, 'a', 'b'], $statement->values);
    }

    public function testDoubleQuoteIdentifiersWhenConfigured()
    {
        $statement = Renderer::select($this->selectSpec([
            'dialect'        => Dialect::forDriver('pgsql'),
            'result_columns' => ['p.*'],
            'table_alias'    => 'p x',
        ]));

        $this->assertSame('SELECT p.* FROM "person" "p x"', $statement->query);
    }

    public function testTopStyleLimitPlacesTopAfterSelect()
    {
        $statement = Renderer::select($this->selectSpec([
            'dialect' => Dialect::forDriver('sqlsrv'),
            'limit'   => 5,
        ]));

        $this->assertSame('SELECT TOP 5 * FROM "person"', $statement->query);
    }

    public function testFirebirdUsesRowsAndTo()
    {
        $statement = Renderer::select($this->selectSpec([
            'dialect' => Dialect::forDriver('firebird'),
            'limit'   => 5,
            'offset'  => 10,
        ]));

        $this->assertSame('SELECT * FROM "person" ROWS 5 TO 10', $statement->query);
    }

    public function testInsertReturningIdForPgsql()
    {
        $statement = Renderer::insert($this->writeSpec([
            'dialect'      => Dialect::forDriver('pgsql'),
            'dirty_fields' => ['name' => 'fred', 'age' => 17],
            'expr_fields'  => ['age' => true],
            'id_column'    => 'person_id',
        ]));

        $this->assertSame(
            'INSERT INTO "person" ("name", "age") VALUES (?, 17) RETURNING "person_id"',
            $statement->query
        );
        $this->assertSame(['fred'], $statement->values);
    }

    public function testInsertUpdateDuplicatesValues()
    {
        $statement = Renderer::insertUpdate($this->writeSpec([
            'dirty_fields' => ['name' => 'fred'],
        ]));

        $this->assertSame(
            'INSERT INTO `person` (`name`) VALUES (?)  ON DUPLICATE KEY UPDATE  `name` = ? ',
            $statement->query
        );
        $this->assertSame(['fred', 'fred'], $statement->values);
    }

    public function testInsertUpdateRendersPlainInsertWithoutUpsertSupport()
    {
        $statement = Renderer::insertUpdate($this->writeSpec([
            'dialect'      => Dialect::forDriver('pgsql'),
            'dirty_fields' => ['name' => 'fred'],
        ]));

        $this->assertSame('INSERT INTO "person" ("name") VALUES (?)', $statement->query);
        $this->assertSame(['fred'], $statement->values);
    }

    public function testUpdateAppendsIdValueLast()
    {
        $statement = Renderer::update($this->writeSpec([
            'dirty_fields' => ['name' => 'fred', 'age' => 17],
            'expr_fields'  => ['age' => true],
            'id_column'    => 'person_id',
            'id_value'     => 3,
        ]));

        $this->assertSame(
            'UPDATE `person` SET `name` = ?, `age` = 17 WHERE `person_id` = ?',
            $statement->query
        );
        $this->assertSame(['fred', 3], $statement->values);
    }

    public function testDeleteByPrimaryKey()
    {
        $statement = Renderer::delete($this->writeSpec([
            'id_column' => 'person_id',
            'id_value'  => 3,
        ]));

        $this->assertSame('DELETE FROM `person` WHERE `person_id` = ?', $statement->query);
        $this->assertSame([3], $statement->values);
    }

    public function testDeleteManyUsesWhereConditions()
    {
        $statement = Renderer::deleteMany($this->bulkDeleteSpec([
            'where_conditions' => [Condition::raw('`age` > ?', [17])],
            'join_sources'     => ['INNER JOIN `address` ON 1'],
            'target'           => '`a`',
        ]));

        $this->assertSame(
            'DELETE `a` FROM `person` INNER JOIN `address` ON 1 WHERE `age` > ?',
            $statement->query
        );
        $this->assertSame([17], $statement->values);
    }

    public function testDeleteManyWithoutJoinsOmitsJoinClause()
    {
        $statement = Renderer::deleteMany($this->bulkDeleteSpec([
            'where_conditions' => [Condition::raw('`age` > ?', [17])],
        ]));

        $this->assertSame('DELETE FROM `person` WHERE `age` > ?', $statement->query);
        $this->assertSame([17], $statement->values);
    }

    public function testQuotingEscapesEmbeddedQuoteCharacters()
    {
        $this->assertSame('`we``ird`', Dialect::forDriver(null)->quoteIdentifier('we`ird'));
        $this->assertSame('"we""ird"', Dialect::forDriver('pgsql')->quoteIdentifier('we"ird'));
    }

    public function testBuildSelectWithoutConnection()
    {
        $orm = ORM::for_table('person');
        $orm->select('name');
        $orm->where('age', 17);
        $orm->limit(5);

        // Rendering must not require the connection to still exist
        ORM::reset_db();

        $statement = Renderer::select($orm->_select_spec());

        $this->assertSame(
            'SELECT `name` FROM `person` WHERE `age` = ? LIMIT 5',
            $statement->query
        );
        $this->assertSame([17], $statement->values);
    }

    public function testBuildWriteStatementsWithoutConnection()
    {
        ORM::configure('driver_name', 'pgsql');
        ORM::configure('identifier_quote_character', '"');

        $orm = ORM::for_table('person');
        $orm->use_id_column('person_id');
        $orm->set('name', 'fred');

        ORM::reset_db();

        $this->assertSame(
            'INSERT INTO "person" ("name") VALUES (?) RETURNING "person_id"',
            $orm->_build_insert()
        );
        $this->assertSame(
            'UPDATE "person" SET "name" = ? WHERE "person_id" = ?',
            $orm->_build_update()
        );
    }

    public function testGetSelectQueryWithoutConnectionAndNoValues()
    {
        $orm = ORM::for_table('person');

        $this->assertSame('SELECT * FROM `person`', $orm->get_select_query());
    }

    public function testGetSelectQueryWithoutConnectionWithBoundValuesThrows()
    {
        $orm = ORM::for_table('person');
        $orm->where('name', "O'Brien");

        ORM::reset_db();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('without a database connection');
        $orm->get_select_query();
    }

    public function testInterpolateLeavesQuotedStringsAlone()
    {
        $this->assertSame(
            "SELECT 'a?b' FROM `t` WHERE `c` = 'x'",
            Renderer::interpolate("SELECT 'a?b' FROM `t` WHERE `c` = ?", ['x'], fn($p) => "'$p'")
        );
    }

    public function testInterpolateRendersNullAndPreservesPercentSigns()
    {
        $this->assertSame(
            "SELECT * FROM `t` WHERE `a` = NULL AND `b` = '100%'",
            Renderer::interpolate('SELECT * FROM `t` WHERE `a` = ? AND `b` = ?', [null, '100%'], fn($p) => "'$p'")
        );
    }
}
