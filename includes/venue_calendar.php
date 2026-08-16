<?php
declare(strict_types=1);

final class VenueCalendarException extends RuntimeException
{
}

/**
 * Vrátí plány, které se mají vykreslit samostatně vedle rezervací sportovišť.
 * Deduplikace pokrývá novou přímou vazbu i historické řádky propojené jen přes trenink_id.
 *
 * @return list<array<string,mixed>>
 */
function venueCalendarUnreservedPlans(PDO $pdo, string $from, string $to, ?int $venueId = null): array
{
    $where = [
        "pt.stav IN ('planovany','evidovany')",
        'pt.sportoviste_id IS NOT NULL',
        'pt.rezervace_id IS NULL',
        'pt.datum BETWEEN ? AND ?',
        'NOT EXISTS ('
            . 'SELECT 1 FROM rezervace_sportovist vr '
            . 'WHERE pt.trenink_id IS NOT NULL AND vr.trenink_id=pt.trenink_id'
        . ')',
    ];
    $parameters = [$from, $to];
    if ($venueId !== null) {
        $where[] = 'pt.sportoviste_id=?';
        $parameters[] = $venueId;
    }
    $statement = $pdo->prepare(
        'SELECT pt.*,sp.nazev AS sport_nazev,t.jmeno AS trener_jmeno,sk.nazev AS skupina_nazev '
        . 'FROM planovane_treninky pt '
        . 'LEFT JOIN sportovist sp ON sp.id=pt.sportoviste_id '
        . 'LEFT JOIN treneri t ON t.id=pt.trener_id '
        . 'LEFT JOIN skupiny sk ON sk.id=pt.skupina_id '
        . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY pt.datum,pt.cas_od,pt.id'
    );
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vytvoří rezervaci uvnitř transakce ukládání tréninku a případně ji propojí s plánem.
 */
function venueCalendarCreateTrainingReservation(
    PDO $pdo,
    int $venueId,
    int $trainerId,
    string $date,
    string $timeFrom,
    string $timeTo,
    int $capacity,
    int $trainingId,
    int $planId = 0
): int {
    if (!$pdo->inTransaction()) {
        throw new LogicException('Rezervace tréninku musí vzniknout uvnitř transakce.');
    }
    $capacity = max(1, min(5, $capacity));
    if ($venueId < 1 || $trainerId < 1 || $trainingId < 1 || $date === ''
        || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $timeFrom)
        || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $timeTo)
        || $timeFrom >= $timeTo
    ) {
        throw new VenueCalendarException('Rezervace sportoviště nemá platné místo, datum nebo čas.');
    }

    $venueSql = 'SELECT nazev,max_kapacita FROM sportovist WHERE id=? AND aktivni=1';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $venueSql .= ' FOR UPDATE';
    }
    $venueStatement = $pdo->prepare($venueSql);
    $venueStatement->execute([$venueId]);
    $venue = $venueStatement->fetch(PDO::FETCH_ASSOC);
    if (!$venue) {
        throw new VenueCalendarException('Vybrané sportoviště není aktivní.');
    }
    $maxCapacity = max(1, (int)$venue['max_kapacita']);
    $occupiedStatement = $pdo->prepare(
        'SELECT COALESCE(SUM(kapacita_dilu),0) FROM rezervace_sportovist '
        . 'WHERE sportoviste_id=? AND datum=? AND cas_od<? AND cas_do>? AND lekce_id IS NULL'
    );
    $occupiedStatement->execute([$venueId, $date, $timeTo, $timeFrom]);
    $occupied = (int)$occupiedStatement->fetchColumn();
    if ($occupied + $capacity > $maxCapacity) {
        throw new VenueCalendarException(sprintf(
            'Rezervaci sportoviště „%s“ nelze uložit: v čase %s–%s je obsazeno %d z %d dílů.',
            (string)$venue['nazev'],
            substr($timeFrom, 0, 5),
            substr($timeTo, 0, 5),
            $occupied,
            $maxCapacity
        ));
    }

    $insert = $pdo->prepare(
        'INSERT INTO rezervace_sportovist '
        . '(sportoviste_id,trener_id,datum,cas_od,cas_do,kapacita_dilu,trenink_id) '
        . 'VALUES (?,?,?,?,?,?,?)'
    );
    $insert->execute([$venueId, $trainerId, $date, $timeFrom, $timeTo, $capacity, $trainingId]);
    $reservationId = (int)$pdo->lastInsertId();
    if ($planId > 0) {
        $link = $pdo->prepare(
            "UPDATE planovane_treninky SET rezervace_id=? WHERE id=? AND stav='planovany' AND rezervace_id IS NULL"
        );
        $link->execute([$reservationId, $planId]);
        if ($link->rowCount() !== 1) {
            throw new VenueCalendarException('Rezervaci se nepodařilo propojit s plánovaným tréninkem. Obnovte plánovač.');
        }
    }
    return $reservationId;
}
