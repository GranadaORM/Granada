<?php

/**
 * Mock version of the PDOStatement class.
 */
class MockPDOStatement extends PDOStatement
{
    private $current_row = 0;

    public function __construct() {}

    public function execute($params = null)
    {
        return true;
    }

    /**
     * Return some dummy data
     */
    public function fetch($fetch_style = PDO::FETCH_BOTH, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        if ($this->current_row == 5) {
            return false;
        }

            return [
                'name' => 'Fred',
                'age'  => 10,
                'id'   => ++$this->current_row,
            ];
    }
}

/**
 * Another mock PDOStatement class, used for testing multiple connections
 */
class MockDifferentPDOStatement extends MockPDOStatement {}

/**
 * Mock database class implementing a subset
 * of the PDO API.
 */
class MockPDO extends PDO
{
    public $last_query;

    /**
     * Return a dummy PDO statement
     */
    public function prepare($statement, $driver_options = [])
    {
        $this->last_query = new MockPDOStatement($statement);

        return $this->last_query;
    }
}

/**
 * A different mock database class, for testing multiple connections
 * Mock database class implementing a subset of the PDO API.
 */
class MockDifferentPDO extends MockPDO
{
    /**
     * Return a dummy PDO statement
     */
    public function prepare($statement, $driver_options = [])
    {
        $this->last_query = new MockDifferentPDOStatement($statement);

        return $this->last_query;
    }
}

class MockMsSqlPDO extends MockPDO
{
    public $fake_driver = 'mssql';

    /**
     * If we are asking for the name of the driver, check if a fake one
     * has been set.
     */
    public function getAttribute($attribute)
    {
        if ($attribute == self::ATTR_DRIVER_NAME) {
            if (!is_null($this->fake_driver)) {
                return $this->fake_driver;
            }
        }

        return parent::getAttribute($attribute);
    }
}

/**
 * A real SQLite handle that counts begin/commit/rollback calls, so a
 * test can see how many of them reached the database.
 */
class CountingPDO extends PDO
{
    public int $begins    = 0;
    public int $commits   = 0;
    public int $rollbacks = 0;

    public function beginTransaction(): bool
    {
        $this->begins++;

        return parent::beginTransaction();
    }

    public function commit(): bool
    {
        $this->commits++;

        return parent::commit();
    }

    public function rollBack(): bool
    {
        $this->rollbacks++;

        return parent::rollBack();
    }
}
