<?php

use Granada\ORM;
use Granada\Orm\ConnectionManager;

class ConnectionManagerTest extends \PHPUnit\Framework\TestCase
{
    public const ALTERNATE = 'alternate';

    protected function setUp(): void
    {
        ORM::set_connection_manager(new ConnectionManager());
    }

    public function testConstructorAcceptsConnectionStringShortcut()
    {
        $manager = new ConnectionManager('sqlite::memory:');

        $this->assertSame('sqlite::memory:', $manager->get_config('connection_string'));
    }

    public function testConstructorAcceptsArrayBatchShortcut()
    {
        $manager = new ConnectionManager([
            'connection_string' => 'sqlite::memory:',
            'username'          => 'fred',
        ]);

        $this->assertSame('sqlite::memory:', $manager->get_config('connection_string'));
        $this->assertSame('fred', $manager->get_config('username'));
    }

    public function testMisspelledSettingThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('loggin');

        (new ConnectionManager())->configure('loggin', true);
    }

    public function testMisspelledSettingInBatchThrows()
    {
        $manager = new ConnectionManager();

        try {
            $manager->configure([
                'logging'   => true,
                'passsword' => 'nope',
            ]);
            $this->fail('Expected InvalidArgumentException for unknown batch key');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('passsword', $e->getMessage());
        }
    }

    public function testEveryDocumentedSettingIsAccepted()
    {
        $manager = new ConnectionManager();

        $settings = [
            'connection_string'           => 'sqlite::memory:',
            'id_column'                   => 'pk',
            'id_column_overrides'         => ['widget' => 'widget_id'],
            'error_mode'                  => PDO::ERRMODE_SILENT,
            'username'                    => 'fred',
            'password'                    => 'secret',
            'driver_options'              => [PDO::ATTR_CASE => PDO::CASE_NATURAL],
            'identifier_quote_character'  => '"',
            'limit_clause_style'          => ORM::LIMIT_STYLE_TOP_N,
            'driver_name'                 => 'pgsql',
            'logging'                     => true,
            'logger'                      => fn(string $query) => null,
            'caching'                     => true,
            'return_result_sets'          => false,
            'find_many_primary_id_as_key' => false,
        ];

        foreach ($settings as $key => $value) {
            $manager->configure($key, $value);
        }

        foreach ($settings as $key => $value) {
            $this->assertSame($value, $manager->get_config($key), "Setting {$key} survived round trip");
        }
    }

    public function testMysqlDsnGetsSslDriverOptionsByDefault()
    {
        $manager = new ConnectionManager('mysql:host=localhost');

        $this->assertSame([
            PDO::MYSQL_ATTR_SSL_CA                 => true,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ], $manager->get_config('driver_options'));
    }

    public function testNonMysqlDsnGetsNoDriverOptions()
    {
        $manager = new ConnectionManager('sqlite::memory:');

        $this->assertNull($manager->get_config('driver_options'));
    }

    public function testExplicitDriverOptionsAreNotOverwritten()
    {
        $options = [PDO::ATTR_CASE => PDO::CASE_LOWER];
        $manager = new ConnectionManager();

        $manager->configure('mysql:host=localhost');
        $manager->configure('driver_options', $options);

        $this->assertSame($options, $manager->get_config('driver_options'));
    }

    public function testSetDbNullDropsHandleAndDerivesFactsOnReconnect()
    {
        $manager = new ConnectionManager('sqlite::memory:');
        $dropped = new MockPDO('sqlite::memory:');
        $manager->set_db($dropped);

        $manager->set_db(null);

        $this->assertFalse($manager->has_db());
        $this->assertContains(ORM::DEFAULT_CONNECTION, $manager->get_connection_names());

        $reconnected = $manager->get_db();
        $this->assertNotSame($dropped, $reconnected);
        $this->assertSame('sqlite', $manager->get_config('driver_name'));
        $this->assertSame('`', $manager->get_config('identifier_quote_character'));
    }

    public function testTwoManagersNeverShareHandlesLogsOrCaches()
    {
        $one = new ConnectionManager('sqlite::memory:');
        $two = new ConnectionManager('sqlite::memory:');

        $this->assertNotSame($one->get_db(), $two->get_db());

        $one->configure('logging', true);
        $one->set_db(new MockPDO('sqlite::memory:'));
        $one->execute('SELECT * FROM `widget`');

        $this->assertSame('SELECT * FROM `widget`', $one->get_last_query());
        $this->assertNull($two->get_last_query());
        $this->assertSame([], $two->get_query_log());

        $one->cache_query_result('cache_key', ['cached'], ORM::DEFAULT_CONNECTION);
        $this->assertFalse($two->check_query_cache('cache_key', ORM::DEFAULT_CONNECTION));

        $statement = $one->get_last_statement();
        $this->assertInstanceOf('PDOStatement', $statement);
        $this->assertNull($two->get_last_statement());
    }

    public function testQueryCacheStaysPerConnection()
    {
        $manager = new ConnectionManager();
        $manager->configure('logging', true);
        $manager->configure('caching', true);
        $manager->configure('logging', true, self::ALTERNATE);
        $manager->configure('caching', true, self::ALTERNATE);
        $manager->set_db(new MockPDO('sqlite::memory:'));
        $manager->set_db(new MockDifferentPDO('sqlite::memory:'), self::ALTERNATE);
        ORM::set_connection_manager($manager);

        $expected = "SELECT * FROM `widget` WHERE `name` = 'Fred' LIMIT 1";
        ORM::for_table('widget')->where('name', 'Fred')->find_one();

        $this->assertCount(0, $manager->get_query_log(self::ALTERNATE));

        ORM::for_table('widget', self::ALTERNATE)->where('name', 'Fred')->find_one();

        $this->assertSame(
            [$expected],
            $manager->get_query_log(self::ALTERNATE),
            'Identical SQL on another connection must execute, not serve the first connection\'s cache'
        );
    }
}
