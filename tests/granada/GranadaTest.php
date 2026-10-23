<?php

use Granada\ORM;

class GranadaTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate';

    protected function setUp(): void
    {
        // Set up the dummy database connection
        ORM::set_db(new MockPDO('sqlite::memory:'));

        // Enable logging
        ORM::configure('logging', true);
    }

    protected function tearDown(): void
    {
        ORM::configure('logging', false);
        ORM::set_db(null);
    }

    public function testSimpleAutoTableName()
    {
        Simple::q()->find_many();
        $expected = 'SELECT * FROM `simple`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testComplexModelClassName()
    {
        ComplexModelClassName::q()->find_many();
        $expected = 'SELECT * FROM `complex_model_class_name`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testModelWithCustomTable()
    {
        ModelWithCustomTable::q()->find_many();
        $expected = 'SELECT * FROM `custom_table`';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testCustomIDColumn()
    {
        ModelWithCustomTableAndCustomIdColumn::q()->find_one(5);
        $expected = "SELECT * FROM `custom_table` WHERE `custom_table`.`custom_id_column` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertData()
    {
        $widget       = Simple::create();
        $widget->name = 'Fred';
        $widget->age  = 10;
        $widget->save();
        $expected = "INSERT INTO `simple` (`name`, `age`) VALUES ('Fred', '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFindOneCamelCase()
    {
        $widget   = Simple::q()->findOne();
        $expected = 'SELECT * FROM `simple` LIMIT 1';
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testFindOneCamelCaseWithId()
    {
        $widget   = Simple::q()->findOne(1);
        $expected = "SELECT * FROM `simple` WHERE `simple`.`id` = '1' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateData()
    {
        $widget       = Simple::q()->find_one(1);
        $widget->name = 'Bob';
        $widget->age  = 12;
        $widget->save();
        $expected = "UPDATE `simple` SET `name` = 'Bob', `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateDataOnlyAge()
    {
        $widget       = Simple::q()->find_one(1);
        $widget->name = 'Fred'; // Was Fred before
        $widget->age  = 12;
        $widget->save();
        $expected = "UPDATE `simple` SET `age` = '12' WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testUpdateDataNoChange()
    {
        $widget       = Simple::q()->find_one(1);
        $widget->name = 'Fred';
        $widget->age  = 10;
        $widget->save();
        $expected = "SELECT * FROM `simple` WHERE `simple`.`id` = '1' LIMIT 1"; // Nothing changed, do not update database
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testDeleteData()
    {
        $widget = Simple::q()->find_one(1);
        $widget->delete();
        $expected = "DELETE FROM `simple` WHERE `id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testInsertingDataContainingAnExpression()
    {
        $widget       = Simple::create();
        $widget->name = 'Fred';
        $widget->age  = 10;
        $widget->set_expr('added', 'NOW()');
        $widget->save();
        $expected = "INSERT INTO `simple` (`name`, `age`, `added`) VALUES ('Fred', '10', NOW())";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHydrateChainsIntoSave()
    {
        $widget = Simple::create();
        $widget->hydrate([
            'name' => 'Fred',
            'age'  => 10,
        ])->save();
        $expected = "INSERT INTO `simple` (`name`, `age`) VALUES ('Fred', '10')";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSetExprChainsIntoSave()
    {
        $widget       = Simple::create();
        $widget->name = 'Fred';
        $widget->age  = 10;
        $widget->set_expr('added', 'NOW()')->save();
        $expected = "INSERT INTO `simple` (`name`, `age`, `added`) VALUES ('Fred', '10', NOW())";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testSetClassNameChainsIntoFind()
    {
        $widget = Simple::q()
            ->set_class_name('Simple')
            ->find_one(1);
        $this->assertInstanceOf('Simple', $widget);
    }

    public function testRelationshipQueryAsSubquery()
    {
        $gadget = Gadget::q()->find_one(1);

        $widget_ids = $gadget->widgets()->select('id');

        $this->assertSame(
            "SELECT `id` FROM `widget` WHERE `widget`.`gadget_id` = '1' AND `widget`.`enabled` = '1' AND `widget`.`hidden` = '0'",
            $widget_ids->get_select_query()
        );

        Widget::q()
            ->where_id_in($widget_ids)
            ->find_many();
        $this->assertSame(
            "SELECT * FROM `widget` WHERE `widget`.`enabled` = '1' AND `widget`.`hidden` = '0' AND `widget`.`id` IN (SELECT `id` FROM `widget` WHERE `widget`.`gadget_id` = '1' AND `widget`.`enabled` = '1' AND `widget`.`hidden` = '0')",
            ORM::get_last_query()
        );
    }

    public function testHasOneRelation()
    {
        $user     = User::q()->find_one(1);
        $profile  = $user->profile()->find_one();
        $expected = "SELECT * FROM `profile` WHERE `profile`.`user_id` = '1' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHasOneWithCustomForeignKeyName()
    {
        $user2    = UserTwo::q()->find_one(1);
        $profile  = $user2->profile()->find_one();
        $expected = "SELECT * FROM `profile` WHERE `profile`.`my_custom_fk_column` = '1' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testBelongsToRelation()
    {
        $user2            = UserTwo::q()->find_one(1);
        $profile          = $user2->profile()->find_one();
        $profile->user_id = 1;
        $user3            = $profile->user()->find_one();
        $expected         = "SELECT * FROM `user` WHERE `user`.`id` = '1' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testBelongsToRelationWithCustomForeignKeyName()
    {
        $profile2                        = ProfileTwo::q()->find_one(1);
        $profile2->custom_user_fk_column = 5;
        $user4                           = $profile2->user()->find_one();
        $expected                        = "SELECT * FROM `user` WHERE `user`.`id` = '5' LIMIT 1";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHasManyRelation()
    {
        $user4    = UserThree::q()->find_one(1);
        $posts    = $user4->posts()->find_many();
        $expected = "SELECT * FROM `post` WHERE `post`.`user_three_id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHasManyRelationWithCustomForeignKeyName()
    {
        $user5    = UserFour::q()->find_one(1);
        $posts    = $user5->posts()->find_many();
        $expected = "SELECT * FROM `post` WHERE `post`.`my_custom_fk_column` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHasManyThroughRelation()
    {
        $book     = Book::q()->find_one(1);
        $authors  = $book->authors()->find_many();
        $expected = "SELECT `author`.* FROM `author` JOIN `author_book` ON `author`.`id` = `author_book`.`author_id` WHERE `author_book`.`book_id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHasManyThroughRelationWithCustomIntermediateModelAndKeyNames()
    {
        $book2    = BookTwo::q()->find_one(1);
        $authors2 = $book2->authors()->find_many();
        $expected = "SELECT `author`.* FROM `author` JOIN `author_book` ON `author`.`id` = `author_book`.`custom_author_id` WHERE `author_book`.`custom_book_id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }

    public function testHasManyThroughWithCustomIdColumns()
    {
        $book     = BookThree::q()->find_one(1);
        $authors  = $book->authors()->find_many();
        $expected = "SELECT `author`.* FROM `author` JOIN `author_book_three` ON `author`.`id` = `author_book_three`.`author_id` WHERE `author_book_three`.`book_three_id` = '1'";
        $this->assertEquals($expected, ORM::get_last_query());
    }
}
