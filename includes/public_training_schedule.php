<?php
declare(strict_types=1);

/**
 * @return list<array<string,mixed>>
 */
function publicTrainingSchedule(PDO $pdo, string $from, string $to): array
{
    $fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if (!$fromDate || !$toDate || $fromDate->format('Y-m-d') !== $from
        || $toDate->format('Y-m-d') !== $to || $toDate < $fromDate
    ) {
        throw new InvalidArgumentException('Neplatné období veřejného rozvrhu.');
    }

    // Do public outputu záměrně nevybíráme sportovce, docházku, trenérské poznámky ani popis.
    $statement = $pdo->prepare(
        'SELECT p.id,p.datum,p.cas_od,p.cas_do,p.nazev,p.kategorie,'
        . 's.nazev AS sportoviste,g.nazev AS skupina '
        . 'FROM planovane_treninky p '
        . 'LEFT JOIN sportovist s ON s.id=p.sportoviste_id '
        . 'LEFT JOIN skupiny g ON g.id=p.skupina_id '
        . "WHERE p.je_verejny=1 AND p.stav='planovany' AND p.datum BETWEEN ? AND ? "
        . 'ORDER BY p.datum,p.cas_od,p.id'
    );
    $statement->execute([$from, $to]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
