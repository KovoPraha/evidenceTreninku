<?php
declare(strict_types=1);

require_once __DIR__ . '/funkce.php';

/** Existing role/workspace policies remain authoritative; no new role model. */
function athleteRewardRequireWriteAccess(): void
{
    if (empty($_SESSION['trener_id']) || !canAccess('kreditni_obdobi')) {
        throw new DomainException('Nemáte oprávnění ke změně kreditních období. Obraťte se na hospodáře.');
    }
}

function athleteRewardIsProtected(array $period): bool
{
    return (int)($period['vyplaceno'] ?? 0) !== 0
        || !in_array($period['datum_do'] ?? null, [null, '', '0000-00-00'], true);
}

function athleteRewardHasSnapshot(array $period): bool
{
    return isset($period['pocet_treninku'], $period['castka_celkem']);
}

function athleteRewardRequireOpen(array $period): void
{
    if (athleteRewardIsProtected($period)) {
        throw new DomainException('Období #' . (int)$period['id']
            . ' je uzavřené nebo vyplacené. Změna nebyla provedena; opravu předejte hospodáři.');
    }
}

function athleteRewardValidateDates(string $start, ?string $end): void
{
    foreach ([$start, $end] as $date) {
        if ($date === null) continue;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new DomainException('Zadejte platné datum období.');
        }
    }
    if ($end !== null && $end < $start) {
        throw new DomainException('Konec období nesmí předcházet jeho začátku.');
    }
}

function athleteRewardValidateRate(float $rate): void
{
    if (!is_finite($rate) || $rate < 0 || $rate > 99999999.99) {
        throw new DomainException('Sazba musí být nezáporná částka nejvýše 99 999 999,99 Kč.');
    }
}

function athleteRewardLockSuffix(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
}

/** Serialize create, single edits and bulk edits in the same parent/row order. */
function athleteRewardLockPerson(PDO $pdo, int $personId): void
{
    $stmt = $pdo->prepare('SELECT id FROM sportovci WHERE id = ?' . athleteRewardLockSuffix($pdo));
    $stmt->execute([$personId]);
    if (!$stmt->fetchColumn()) throw new DomainException('Sportovec nebyl nalezen.');
}

function athleteRewardLockPeriod(PDO $pdo, int $periodId, int $personId): array
{
    $stmt = $pdo->prepare('SELECT * FROM sportovec_obdobi WHERE id = ? AND sportovec_id = ?'
        . athleteRewardLockSuffix($pdo));
    $stmt->execute([$periodId, $personId]);
    $period = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$period) throw new DomainException('Období #' . $periodId . ' pro vybraného sportovce nebylo nalezeno.');
    return $period;
}

function athleteRewardStats(PDO $pdo, int $personId, string $start, ?string $end, float $rate): array
{
    $sql = 'SELECT COUNT(DISTINCT t.id) FROM treninky t JOIN trenink_sportovec ts ON ts.trenink_id = t.id'
        . ' WHERE ts.sportovec_id = ? AND t.datum >= ?';
    $params = [$personId, $start];
    if ($end !== null) { $sql .= ' AND t.datum <= ?'; $params[] = $end; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = (int)$stmt->fetchColumn();
    return ['pocet' => $count, 'kredit' => round($count * $rate, 2)];
}

/** One atomic transaction, including audit. A failed audit must roll back the change. */
function athleteRewardTransaction(PDO $pdo, callable $operation): mixed
{
    athleteRewardRequireWriteAccess();
    if ($pdo->inTransaction()) throw new LogicException('Reward mutation requires its own transaction.');
    $pdo->beginTransaction();
    try {
        $result = $operation();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function athleteRewardAudit(PDO $pdo, string $action, int $id, ?array $before, array $after): void
{
    zapisAuditLog($pdo, (int)$_SESSION['trener_id'], $action, 'sportovec_obdobi', $id,
        ['before' => $before, 'after' => $after]);
}

function athleteRewardSave(PDO $pdo, int $personId, int $periodId, float $rate, string $start, ?string $end): int
{
    athleteRewardValidateRate($rate);
    athleteRewardValidateDates($start, $end);
    return athleteRewardTransaction($pdo, function () use ($pdo, $personId, $periodId, $rate, $start, $end): int {
        athleteRewardLockPerson($pdo, $personId);
        $before = $periodId > 0 ? athleteRewardLockPeriod($pdo, $periodId, $personId) : null;
        if ($before !== null) athleteRewardRequireOpen($before);
        // Providing an end date also closes the period; never create a new missing snapshot.
        $stats = $end !== null ? athleteRewardStats($pdo, $personId, $start, $end, $rate) : null;
        if ($before !== null) {
            $stmt = $pdo->prepare('UPDATE sportovec_obdobi SET sazba_kc = ?, datum_od = ?, datum_do = ?, pocet_treninku = ?, castka_celkem = ? WHERE id = ? AND sportovec_id = ?');
            $stmt->execute([$rate, $start, $end, $stats['pocet'] ?? null, $stats['kredit'] ?? null, $periodId, $personId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO sportovec_obdobi (sportovec_id, sazba_kc, datum_od, datum_do, pocet_treninku, castka_celkem) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$personId, $rate, $start, $end, $stats['pocet'] ?? null, $stats['kredit'] ?? null]);
            $periodId = (int)$pdo->lastInsertId();
        }
        athleteRewardAudit($pdo, $before === null ? 'reward_period_create' : 'reward_period_update', $periodId,
            $before, athleteRewardLockPeriod($pdo, $periodId, $personId));
        return $periodId;
    });
}

function athleteRewardClose(PDO $pdo, int $personId, int $periodId, string $end): array
{
    return athleteRewardTransaction($pdo, function () use ($pdo, $personId, $periodId, $end): array {
        athleteRewardLockPerson($pdo, $personId);
        $before = athleteRewardLockPeriod($pdo, $periodId, $personId);
        // Repeated close is a visible rejection, including historical rows missing a snapshot.
        athleteRewardRequireOpen($before);
        athleteRewardValidateDates($before['datum_od'], $end);
        $stats = athleteRewardStats($pdo, $personId, $before['datum_od'], $end, (float)$before['sazba_kc']);
        $stmt = $pdo->prepare('UPDATE sportovec_obdobi SET datum_do = ?, pocet_treninku = ?, castka_celkem = ? WHERE id = ? AND sportovec_id = ?');
        $stmt->execute([$end, $stats['pocet'], $stats['kredit'], $periodId, $personId]);
        athleteRewardAudit($pdo, 'reward_period_close', $periodId, $before, athleteRewardLockPeriod($pdo, $periodId, $personId));
        return $stats;
    });
}

/** Existing paid marker only: this does not execute or prove a bank/cash payout. */
function athleteRewardMarkPaid(PDO $pdo, int $personId, int $periodId): void
{
    athleteRewardTransaction($pdo, function () use ($pdo, $personId, $periodId): void {
        athleteRewardLockPerson($pdo, $personId);
        $before = athleteRewardLockPeriod($pdo, $periodId, $personId);
        if ((int)$before['vyplaceno'] !== 0) athleteRewardRequireOpen($before);
        if (!athleteRewardIsProtected($before) || !athleteRewardHasSnapshot($before)) {
            throw new DomainException('Období #' . $periodId . ' nemá uzavřený souhrn. Označení výplaty nebylo provedeno; případ předejte hospodáři.');
        }
        $stmt = $pdo->prepare('UPDATE sportovec_obdobi SET vyplaceno = 1 WHERE id = ? AND sportovec_id = ?');
        $stmt->execute([$periodId, $personId]);
        athleteRewardAudit($pdo, 'reward_period_mark_paid', $periodId, $before, athleteRewardLockPeriod($pdo, $periodId, $personId));
    });
}

/** $rates must already be restricted to the selected group's allowed person IDs. */
function athleteRewardBulkRates(PDO $pdo, array $rates, string $today): array
{
    athleteRewardValidateDates($today, null);
    ksort($rates, SORT_NUMERIC);
    foreach ($rates as $rate) athleteRewardValidateRate((float)$rate);
    return athleteRewardTransaction($pdo, function () use ($pdo, $rates, $today): array {
        $counts = ['updated' => 0, 'inserted' => 0];
        foreach ($rates as $personId => $rate) {
            $personId = (int)$personId;
            athleteRewardLockPerson($pdo, $personId);
            $stmt = $pdo->prepare("SELECT * FROM sportovec_obdobi WHERE sportovec_id = ? AND (datum_do IS NULL OR datum_do = '0000-00-00') ORDER BY datum_od DESC, id DESC"
                . athleteRewardLockSuffix($pdo));
            $stmt->execute([$personId]);
            $open = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($open as $period) athleteRewardRequireOpen($period); // Paid but still open is a conflict.
            if (count($open) > 1) {
                throw new DomainException('Sportovec #' . $personId . ' má více otevřených období. Hromadná změna nebyla provedena; případ předejte hospodáři.');
            }
            $before = $open[0] ?? null;
            if ($before !== null) {
                $id = (int)$before['id'];
                $stmt = $pdo->prepare('UPDATE sportovec_obdobi SET sazba_kc = ? WHERE id = ?');
                $stmt->execute([$rate, $id]);
                $counts['updated']++;
            } else {
                $stmt = $pdo->prepare('INSERT INTO sportovec_obdobi (sportovec_id, datum_od, sazba_kc) VALUES (?, ?, ?)');
                $stmt->execute([$personId, $today, $rate]);
                $id = (int)$pdo->lastInsertId();
                $counts['inserted']++;
            }
            athleteRewardAudit($pdo, 'reward_period_bulk_rate', $id, $before, athleteRewardLockPeriod($pdo, $id, $personId));
        }
        return $counts;
    });
}
