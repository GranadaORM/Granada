<?php

use Granada\ORM;

/**
 * Junction filters declared on the query class: QueryCar::where_part_in()
 * and QueryCar::where_part_not_in() find cars by their parts.
 */
class JunctionFilterTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        ORM::set_db(new PDO('sqlite::memory:'));
        ORM::get_db()->exec(file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models.sql'
        ));
    }

    protected function tearDown(): void
    {
        ORM::set_db(null);
    }

    /**
     * @return string[]
     */
    private function names(mixed $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $names[] = $row->name;
        }

        return $names;
    }

    public function testWherePartInKeepsCarsLinkedToAnyListedPart()
    {
        // Car1 links parts 1, 2; Car2 links parts 1, 3.
        $cars = Car::q()
            ->where_part_in([2, 3])
            ->find_many();

        $this->assertSame(['Car1', 'Car2'], $this->names($cars));
    }

    public function testWherePartInAcceptsASingleId()
    {
        $cars = Car::q()
            ->where_part_in(2)
            ->find_many();

        $this->assertSame(['Car1'], $this->names($cars));
    }

    public function testWherePartInAcceptsASubquery()
    {
        // Parts priced 15 and up are Part2 and Part4; Car1 links Part2,
        // Car3 links Part4.
        $cars = Car::q()
            ->where_part_in(Part::q()
                ->where_price_gte(15)
                ->select('id'))
            ->find_many();

        $this->assertSame(['Car1', 'Car3'], $this->names($cars));
    }

    public function testWherePartNotInKeepsCarsLinkedToNoneListed()
    {
        // Car3 links Part4 and Car4 links Part5, so both drop out.
        // Car6 has no links at all and stays.
        $cars = Car::q()
            ->where_part_not_in([4, 5])
            ->find_many();

        $this->assertSame(['Car1', 'Car2', 'Car6'], $this->names($cars));
    }

    public function testWherePartNotInAcceptsASubquery()
    {
        // Parts priced 15 and up are Part2 and Part4; Car1 links Part2
        // and Car3 links Part4, so both drop out.
        $cars = Car::q()
            ->where_part_not_in(Part::q()
                ->where_price_gte(15)
                ->select('id'))
            ->find_many();

        $this->assertSame(['Car2', 'Car4', 'Car6'], $this->names($cars));
    }
}
