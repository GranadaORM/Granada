<?php

/**
 * A real SQLite handle that counts the transactions and statements it runs.
 */
class CountingPDO extends PDO
{
    public int $begins     = 0;
    public int $commits    = 0;
    public int $rollbacks  = 0;
    public int $statements = 0;

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

    public function prepare(string $query, array $options = []): false|PDOStatement
    {
        $this->statements++;

        return parent::prepare($query, $options);
    }
}
