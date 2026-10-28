<?php

use Granada\Relationship;

/**
 * For each relationship kind, correlated_columns() returns the
 * column on the associated model and the column on the parent.
 */
class RelationshipMatchColumnsTest extends \PHPUnit\Framework\TestCase
{
    public function testHasOneMatchesTheChildForeignKeyToTheParentId()
    {
        $this->assertSame(
            ['owner_id', 'id'],
            Owner::create()
                ->car()
                ->correlated_columns()
        );
    }

    public function testHasManyMatchesTheChildForeignKeyToTheParentId()
    {
        $this->assertSame(
            ['crate_id', 'id'],
            Crate::create()
                ->items()
                ->correlated_columns()
        );
    }

    public function testBelongsToMatchesTheRelatedIdColumnToTheForeignKey()
    {
        $this->assertSame(
            ['id', 'owner_id'],
            Car::create()
                ->owner()
                ->correlated_columns()
        );
    }

    public function testBelongsToWithoutARelatedKeyFallsBackToTheRelatedIdColumn()
    {
        $relationship = new Relationship(Car::create(), 'belongs_to', 'Manufactor', 'manufactor_id');

        $this->assertSame(['id', 'manufactor_id'], $relationship->correlated_columns());
    }

    public function testBelongsToWithAnExplicitRelatedKeyPassesItThrough()
    {
        $relationship = new Relationship(Car::create(), 'belongs_to', 'Manufactor', 'manufactor_id', related_key: 'name');

        $this->assertSame(['name', 'manufactor_id'], $relationship->correlated_columns());
    }

    public function testHasManyThroughReturnsTheLinkColumnAndTheParentId()
    {
        $this->assertSame(
            ['car_id', 'id'],
            Car::create()
                ->parts()
                ->correlated_columns()
        );
    }
}
