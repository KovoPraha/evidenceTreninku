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
    /** @var array<int,list<int>> */
    private array $physicalTeams = [];
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

    /** @param list<int> $variantIds */
    public function warmOffersForVariants(array $variantIds): void
    {
        $pending = array_values(array_filter(array_unique($variantIds), fn (int $id): bool => !array_key_exists($id, $this->offers)));
        foreach (array_chunk($pending, 400) as $batch) {
            $marks = implode(',', array_fill(0, count($batch), '?'));
            $statement = $this->pdo->prepare(clubProgramOfferSelectSql($this->pdo, $this)
                . "WHERE o.variant_id IN ($marks) AND o.status='active' AND p.status='active'");
            $statement->execute($batch);
            $byVariant = [];
            $programIds = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $byVariant[(int) $row['variant_id']][] = $row;
                if ((int) ($row['team_id'] ?? 0) > 0 && (int) $row['program_id'] > 0) {
                    $programIds[] = (int) $row['program_id'];
                }
            }
            $this->warmPhysicalTeams($programIds);
            foreach ($batch as $id) {
                $rows = $byVariant[$id] ?? [];
                if (count($rows) > 1) {
                    // Legacy duplicate links keep the canonical unordered fetch.
                    $this->offer($id);
                } elseif ($rows === []) {
                    $this->offers[$id] = false;
                } else {
                    $this->offers[$id] = array_merge($rows[0], clubProgramOfferCapacityState($this->pdo, $rows[0], $this->now, false, 0, $this));
                }
            }
        }
    }

    /** @return list<int> */
    public function physicalTeamIds(int $programId): array
    {
        $this->warmPhysicalTeams([$programId]);
        return $this->physicalTeams[$programId] ?? [];
    }

    /** @param list<int> $programIds */
    private function warmPhysicalTeams(array $programIds): void
    {
        $pending = array_values(array_filter(array_unique($programIds), fn (int $id): bool => !array_key_exists($id, $this->physicalTeams)));
        if ($pending === [] || !$this->tableExists($this->pdo, 'club_program_physical_teams')) {
            return;
        }
        foreach (array_chunk($pending, 400) as $batch) {
            $marks = implode(',', array_fill(0, count($batch), '?'));
            $statement = $this->pdo->prepare("SELECT program_id,team_id FROM club_program_physical_teams WHERE program_id IN ($marks) ORDER BY team_id");
            $statement->execute($batch);
            foreach ($batch as $id) {
                $this->physicalTeams[$id] = [];
            }
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->physicalTeams[(int) $row['program_id']][] = (int) $row['team_id'];
            }
        }
    }

    /** @return array{saleable:bool,reason:string,offer:array<string,mixed>|null} */
    public function saleState(int $variantId): array
    {
        return $this->states[$variantId] ??= clubProgramVariantSaleState($this->pdo, $variantId, $this->now, false, $this);
    }

    /** Warm published documents for one public projection, never a locking read.
     * @param list<int> $variantIds
     */
    public function warmTermsForVariants(array $variantIds): void
    {
        $this->warmOffersForVariants($variantIds);
        $targets = ['offer' => [], 'program' => []];
        foreach (array_unique($variantIds) as $variantId) {
            $offer = $this->offer($variantId);
            if ($offer && clubProgramOfferSaleState($offer, $this->now)['saleable']) {
                $targets['offer'][] = (int) $offer['id'];
                $targets['program'][] = (int) $offer['program_id'];
            }
        }
        if ($targets['offer'] === [] || !clubProgramTermsRegistryAvailable($this->pdo, $this)) {
            return;
        }
        foreach ($targets as $type => $ids) {
            $this->warmTermsForTargets($type, array_values(array_unique($ids)));
        }
    }

    /** @param list<int> $targetIds */
    private function warmTermsForTargets(string $targetType, array $targetIds): void
    {
        // At most 160 SELECT arms / 800 parameters: below SQLite's older limits.
        foreach (array_chunk($targetIds, 80) as $batch) {
            $selects = [];
            $parameters = [];
            foreach ($batch as $targetId) {
                [$scopeType, $scopeKey] = clubProgramTermsScope($targetType, $targetId);
                foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
                    $key = $targetType . ':' . $targetId . ':' . $purpose;
                    if (array_key_exists($key, $this->terms)) {
                        continue;
                    }
                    // Bound predicates retain the canonical database collation.
                    // MAX(id) selects the same last active row as ORDER BY id DESC.
                    $selects[] = 'SELECT ? AS read_target_id,? AS read_purpose,(SELECT MAX(id) '
                        . 'FROM club_event_term_versions WHERE scope_type=? AND scope_key=? '
                        . "AND consent_purpose=? AND status='active') AS term_id";
                    array_push($parameters, $targetId, $purpose, $scopeType, $scopeKey, $purpose);
                }
            }
            if ($selects === []) {
                continue;
            }
            $sql = 'SELECT requested.read_target_id AS _read_target_id,requested.read_purpose AS _read_purpose,t.* '
                . 'FROM (' . implode(' UNION ALL ', $selects) . ') requested '
                . 'LEFT JOIN club_event_term_versions t ON t.id=requested.term_id';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = $targetType . ':' . (int) $row['_read_target_id'] . ':' . $row['_read_purpose'];
                unset($row['_read_target_id'], $row['_read_purpose']);
                $this->terms[$key] = $row['id'] === null ? false : $row;
            }
        }
    }

    /** @return array<string,mixed>|false */
    public function term(string $targetType, int $targetId, string $purpose): array|false
    {
        $key = $targetType . ':' . $targetId . ':' . $purpose;
        return $this->terms[$key] ??= clubProgramTermsCurrent($this->pdo, $targetType, $targetId, $purpose, false, $this);
    }
}
