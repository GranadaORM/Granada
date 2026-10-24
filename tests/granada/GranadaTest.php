<?php

use Granada\ORM;

class GranadaTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::reset_db();
    }

    protected function tearDown(): void
    {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testSimpleAutoTableName()
    {
        $statement = Simple::q()->to_statement();

        $this->assertSame('SELECT * FROM `simple`', $statement->query);
    }

    public function testComplexModelClassName()
    {
        $statement = ComplexModelClassName::q()->to_statement();

        $this->assertSame('SELECT * FROM `complex_model_class_name`', $statement->query);
    }

    public function testModelWithCustomTable()
    {
        $statement = ModelWithCustomTable::q()->to_statement();

        $this->assertSame('SELECT * FROM `custom_table`', $statement->query);
    }

    public function testCustomIDColumn()
    {
        $statement = ModelWithCustomTableAndCustomIdColumn::q()
            ->where_id_is(5)
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `custom_table` WHERE `custom_table`.`custom_id_column` = ? LIMIT 1', $statement->query);
        $this->assertSame([5], $statement->values);
    }

    public function testARelationshipQueryHoldsItsOwnConditions()
    {
        $gadget = Gadget::create([
            'id'      => 1,
            'enabled' => 1,
            'hidden'  => 0,
        ]);

        $statement = $gadget->widgets()
            ->select('id')
            ->to_statement();

        $this->assertSame(
            'SELECT `id` FROM `widget` WHERE `widget`.`gadget_id` = ? AND `widget`.`enabled` = ? AND `widget`.`hidden` = ?',
            $statement->query
        );
        $this->assertSame([1, 1, 0], $statement->values);
    }

    public function testHasOneRelation()
    {
        $user = User::create(['id' => 1]);

        $statement = $user->profile()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `profile` WHERE `profile`.`user_id` = ? LIMIT 1', $statement->query);
        $this->assertSame([1], $statement->values);
    }

    public function testHasOneWithCustomForeignKeyName()
    {
        $user = UserTwo::create(['id' => 1]);

        $statement = $user->profile()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `profile` WHERE `profile`.`my_custom_fk_column` = ? LIMIT 1', $statement->query);
        $this->assertSame([1], $statement->values);
    }

    public function testBelongsToRelation()
    {
        $profile = Profile::create([
            'id'      => 1,
            'user_id' => 1,
        ]);

        $statement = $profile->user()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `user` WHERE `user`.`id` = ? LIMIT 1', $statement->query);
        $this->assertSame([1], $statement->values);
    }

    public function testBelongsToRelationWithCustomForeignKeyName()
    {
        $profile = ProfileTwo::create([
            'id'                    => 1,
            'custom_user_fk_column' => 5,
        ]);

        $statement = $profile->user()
            ->limit(1)
            ->to_statement();

        $this->assertSame('SELECT * FROM `user` WHERE `user`.`id` = ? LIMIT 1', $statement->query);
        $this->assertSame([5], $statement->values);
    }

    public function testHasManyRelation()
    {
        $user = UserThree::create(['id' => 1]);

        $statement = $user->posts()->to_statement();

        $this->assertSame('SELECT * FROM `post` WHERE `post`.`user_three_id` = ?', $statement->query);
        $this->assertSame([1], $statement->values);
    }

    public function testHasManyRelationWithCustomForeignKeyName()
    {
        $user = UserFour::create(['id' => 1]);

        $statement = $user->posts()->to_statement();

        $this->assertSame('SELECT * FROM `post` WHERE `post`.`my_custom_fk_column` = ?', $statement->query);
        $this->assertSame([1], $statement->values);
    }

    public function testHasManyThroughRelation()
    {
        $book = Book::create(['id' => 1]);

        $statement = $book->authors()->to_statement();

        $this->assertSame(
            'SELECT `author`.* FROM `author` JOIN `author_book` ON `author`.`id` = `author_book`.`author_id`'
            . ' WHERE `author_book`.`book_id` = ?',
            $statement->query
        );
        $this->assertSame([1], $statement->values);
    }

    public function testHasManyThroughRelationWithCustomIntermediateModelAndKeyNames()
    {
        $book = BookTwo::create(['id' => 1]);

        $statement = $book->authors()->to_statement();

        $this->assertSame(
            'SELECT `author`.* FROM `author` JOIN `author_book` ON `author`.`id` = `author_book`.`custom_author_id`'
            . ' WHERE `author_book`.`custom_book_id` = ?',
            $statement->query
        );
        $this->assertSame([1], $statement->values);
    }

    public function testHasManyThroughWithCustomIdColumns()
    {
        $book = BookThree::create(['id' => 1]);

        $statement = $book->authors()->to_statement();

        $this->assertSame(
            'SELECT `author`.* FROM `author` JOIN `author_book_three` ON `author`.`id` = `author_book_three`.`author_id`'
            . ' WHERE `author_book_three`.`book_three_id` = ?',
            $statement->query
        );
        $this->assertSame([1], $statement->values);
    }
}
