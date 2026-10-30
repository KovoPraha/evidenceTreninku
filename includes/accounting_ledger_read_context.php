<?php

declare(strict_types=1);

/** Metadata for one pending-source projection. No rows, amounts, authority or write path cache. */
final class AccountingLedgerReadContext
{
    /** @var array<string,bool> */
    private array $tables = [];

    public function __construct(private PDO $connection)
    {
    }

    public function assertConnection(PDO $connection): void
    {
        if ($connection !== $this->connection) {
            throw new LogicException('Accounting read context belongs to another connection.');
        }
    }

    public function tableExists(PDO $connection, string $table): bool
    {
        $this->assertConnection($connection);
        return $this->tables[$table] ??= accountingLedgerTable($connection, $table);
    }
}
