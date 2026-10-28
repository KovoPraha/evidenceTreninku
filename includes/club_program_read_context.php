<?php

declare(strict_types=1);

/**
 * One public read projection only. Never retain this object across mutations,
 * requests or connections. Checkout and locking reads do not consume it.
 */
final class ClubProgramReadContext
{
    /** @var array<string,bool> */
    private array $tables = [];
    /** @var array<string,array<string,true>> */
    private array $columns = [];
    /** @var array<int,array<string,mixed>|false> */
    private array $offers = [];
    /** @var array<int,array{saleable:bool,reason:string,offer:array<string,mixed>|null}> */
    private array $states = [];
    /** @var array<string,array<string,mixed>|false> */
    private array $terms = [];
    private DateTimeImmutable $now;

    public function __construct(private PDO $pdo, ?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    }

    public function assertConnection(PDO $pdo): void
    {
        if ($pdo !== $this->pdo) {
            throw new LogicException('Program read context belongs to another PDO connection.');
        }
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        $this->assertConnection($pdo);
        return $this->tables[$table] ??= clubProgramRuntimeTableExists($pdo, $table);
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $this->assertConnection($pdo);
        if (!isset($this->columns[$table])) {
            if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
                return false;
            }
            if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $statement = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
                $statement->execute([$table]);
                $names = $statement->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $names = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
            }
            $this->columns[$table] = array_fill_keys(array_map('strval', $names), true);
        }
        return isset($this->columns[$table][$column]);
    }

    /** @return array<string,mixed>|false */
    public function offer(int $variantId): array|false
    {
        return $this->offers[$variantId] ??= clubProgramOfferForVariant($this->pdo, $variantId, $this->now, false, $this);
    }

    /** @return array{saleable:bool,reason:string,offer:array<string,mixed>|null} */
    public function saleState(int $variantId): array
    {
        return $this->states[$variantId] ??= clubProgramVariantSaleState($this->pdo, $variantId, $this->now, false, $this);
    }

    /** @return array<string,mixed>|false */
    public function term(string $targetType, int $targetId, string $purpose): array|false
    {
        $key = $targetType . ':' . $targetId . ':' . $purpose;
        return $this->terms[$key] ??= clubProgramTermsCurrent($this->pdo, $targetType, $targetId, $purpose, false, $this);
    }
}
