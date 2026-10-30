<?php

declare(strict_types=1);

/**
 * Metadata only, during one explicit family GET projection. No DDL or USE inside
 * the scope. Never store authority, rows or a PDO here beyond the caller's finally.
 * Outside a scope every helper keeps its original fresh read and error behavior.
 */
final class FamilySchemaReadScope
{
    /** @var WeakMap<PDO,self>|null */
    private static ?WeakMap $active = null;
    /** @var array<string,bool> */
    private array $values = [];
    private bool $closed = false;

    /** @param WeakReference<PDO> $connection */
    private function __construct(private WeakReference $connection, private ?self $previous)
    {
    }

    public static function begin(PDO $pdo): self
    {
        self::$active ??= new WeakMap();
        $scope = new self(WeakReference::create($pdo), self::$active[$pdo] ?? null);
        self::$active[$pdo] = $scope;
        return $scope;
    }

    /** @param callable():bool $read */
    public static function remember(PDO $pdo, string $key, callable $read): bool
    {
        $scope = self::$active[$pdo] ?? null;
        if ($scope === null) {
            return $read();
        }
        // Assignment occurs only after a successful read; failures must retry.
        return $scope->values[$key] ??= $read();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $pdo = $this->connection->get();
        if ($pdo !== null) {
            if ((self::$active[$pdo] ?? null) !== $this) {
                throw new LogicException('Family schema scopes must close in reverse order.');
            }
            if ($this->previous === null) {
                unset(self::$active[$pdo]);
            } else {
                self::$active[$pdo] = $this->previous;
            }
        }
        $this->values = [];
        $this->previous = null;
        $this->closed = true;
    }
}
