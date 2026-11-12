<?php

namespace Granada\Benchmark;

use Car;
use CarPart;
use Granada\ORM;
use Granada\Orm\ConnectionManager;
use Manufactor;
use Part;
use PhpBench\Attributes as Bench;

/**
 * Read paths over a 2,000-car fixture.
 *
 * The fixture is a file: PHPBench runs the class hooks in a process
 * separate from the iterations, so a seed in class scope does not reach
 * the benchmark. Each iteration opens the file again.
 */
#[Bench\BeforeClassMethods('setUpBeforeClass')]
#[Bench\AfterClassMethods('tearDownAfterClass')]
class CarBench
{
    private const ROWS = 2000;

    public static function setUpBeforeClass(): void
    {
        @unlink(self::db_path());

        ORM::set_connection_manager(new ConnectionManager('sqlite:' . self::db_path()));
        ORM::set_db(new \PDO('sqlite:' . self::db_path()));
        // The fixture is rebuilt every run, so durability buys nothing.
        ORM::get_db()->exec('PRAGMA journal_mode = MEMORY');
        ORM::get_db()->exec('PRAGMA synchronous = OFF');
        ORM::get_db()->exec(file_get_contents(dirname(__DIR__) . '/tests/models.sql'));

        ORM::get_db()->beginTransaction();

        for ($i = 1; $i <= 20; $i++) {
            Manufactor::create([
                'name'    => 'M' . $i,
                'enabled' => 1,
            ])->save();
        }

        for ($i = 1; $i <= 50; $i++) {
            Part::create([
                'name'  => 'P' . $i,
                'price' => $i,
            ])->save();
        }

        for ($i = 1; $i <= self::ROWS; $i++) {
            Car::create([
                'name'          => 'C' . $i,
                'manufactor_id' => ($i % 20) + 1,
                'enabled'       => 1,
                'is_deleted'    => 0,
            ])->save();
        }

        for ($i = 1; $i <= self::ROWS; $i++) {
            CarPart::create([
                'car_id'  => $i,
                'part_id' => ($i % 50) + 1,
            ])->save();
            CarPart::create([
                'car_id'  => $i,
                'part_id' => (($i + 1) % 50) + 1,
            ])->save();
        }

        ORM::get_db()->commit();
    }

    public static function tearDownAfterClass(): void
    {
        ORM::set_db(null);
        @unlink(self::db_path());
    }

    public function setUp(): void
    {
        ORM::set_connection_manager(new ConnectionManager('sqlite:' . self::db_path()));
        ORM::set_db(new \PDO('sqlite:' . self::db_path()));
    }

    #[Bench\BeforeMethods('setUp')]
    #[Bench\Assert('mode(variant.time.avg) < 20 milliseconds')]
    public function benchFindMany(): void
    {
        Car::q()->find_many();
    }

    #[Bench\BeforeMethods('setUp')]
    #[Bench\Assert('mode(variant.time.avg) < 40 milliseconds')]
    public function benchFindManyLazy(): void
    {
        foreach (Car::q()->find_many_lazy() as $car) {
            $car->id();
        }
    }

    #[Bench\BeforeMethods('setUp')]
    #[Bench\Assert('mode(variant.time.avg) < 35 milliseconds')]
    public function benchEagerManufactor(): void
    {
        Car::q()
            ->with('manufactor')
            ->find_many();
    }

    #[Bench\BeforeMethods('setUp')]
    #[Bench\Assert('mode(variant.time.avg) < 120 milliseconds')]
    public function benchLazyManufactor(): void
    {
        foreach (Car::q()->find_many() as $car) {
            $car->manufactor();
        }
    }

    #[Bench\BeforeMethods('setUp')]
    #[Bench\Assert('mode(variant.time.avg) < 85 milliseconds')]
    public function benchEagerParts(): void
    {
        Car::q()
            ->with('parts')
            ->find_many();
    }

    #[Bench\BeforeMethods('setUp')]
    #[Bench\Assert('mode(variant.time.avg) < 270 milliseconds')]
    public function benchFindOneById(): void
    {
        for ($i = 1; $i <= self::ROWS; $i++) {
            Car::q()->find_one($i);
        }
    }

    #[Bench\BeforeMethods('setUp')]
    #[Bench\Assert('mode(variant.time.avg) < 2 milliseconds')]
    public function benchCount(): void
    {
        Car::q()->count();
    }

    private static function db_path(): string
    {
        return sys_get_temp_dir() . '/granada-bench.sqlite';
    }
}
