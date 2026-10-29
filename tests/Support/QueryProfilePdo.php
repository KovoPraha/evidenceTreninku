<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;

/** Opt-in instrumentation for synthetic tests only; never load this with production credentials. */
final class QueryProfilePdo extends PDO
{
    public bool $profiling = false;
    public array $profile = [];

    public function __construct(string $dsn, ?string $user = null, ?string $password = null)
    {
        parent::__construct($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [QueryProfileStatement::class, [$this]]);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $statement = $this->prepare($query);
        if ($fetchMode !== null) {
            if ($fetchMode === PDO::FETCH_COLUMN && $args === []) {
                $args = [0];
            }
            $statement->setFetchMode($fetchMode, ...$args);
        }
        $statement->execute();
        return $statement;
    }
}

final class QueryProfileStatement extends PDOStatement
{
    private ?int $profileIndex = null;

    protected function __construct(private QueryProfilePdo $connection)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->profileIndex = null;
        $start = hrtime(true);
        $ok = parent::execute($params);
        if ($this->connection->profiling) {
            $this->profileIndex = count($this->connection->profile);
            $this->connection->profile[] = ['sql' => $this->queryString, 'parameters' => $params ?? [],
                'execute_ms' => (hrtime(true) - $start) / 1e6, 'fetch_ms' => 0.0, 'fetched_rows' => 0];
        }
        return $ok;
    }

    private function fetched(int $rows, int $start): void
    {
        if ($this->profileIndex !== null) {
            $this->connection->profile[$this->profileIndex]['fetched_rows'] += $rows;
            $this->connection->profile[$this->profileIndex]['fetch_ms'] += (hrtime(true) - $start) / 1e6;
        }
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $start = hrtime(true);
        $rows = parent::fetchAll($mode, ...$args);
        $this->fetched(count($rows), $start);
        return $rows;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $start = hrtime(true);
        $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);
        $this->fetched($row === false ? 0 : 1, $start);
        return $row;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $start = hrtime(true);
        $value = parent::fetchColumn($column);
        $this->fetched($value === false ? 0 : 1, $start);
        return $value;
    }
}
