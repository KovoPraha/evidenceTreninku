<?php

declare(strict_types=1);

require_once __DIR__ . '/TrainingHistoryFixture.php';

/** Original copy-list SQL at 7d452b30, independent of the new candidate projection. */
function trainingCopyFixtureSql(string $scope, bool $groupFiltered, string $period): string
{
    $sql = "SELECT t.id,t.datum,t.napln,t.delka,t.kategorie,
        GROUP_CONCAT(DISTINCT s.nazev ORDER BY s.nazev SEPARATOR ', ') AS skupiny_nazev,
        GROUP_CONCAT(DISTINCT ps.nazev ORDER BY ps.nazev SEPARATOR ', ') AS podskupiny_nazev,
        COUNT(DISTINCT ts.sportovec_id) AS pocet_ucastniku
        FROM treninky t
        LEFT JOIN trenink_skupina tsk ON tsk.trenink_id = t.id
        LEFT JOIN skupiny s ON s.id = tsk.skupina_id
        LEFT JOIN trenink_podskupina tp ON tp.trenink_id = t.id
        LEFT JOIN podskupiny ps ON ps.id = tp.podskupina_id
        LEFT JOIN trenink_sportovec ts ON ts.trenink_id = t.id
        WHERE 1=1 AND " . $scope;
    if ($groupFiltered) {
        $sql .= ' AND tsk.skupina_id = ?';
    }
    if ($period !== 'all') {
        $sql .= ' AND t.datum >= DATE_SUB(CURRENT_DATE, INTERVAL ' . (int)$period . ' DAY)';
    }
    return $sql;
}

function trainingCopyBaseline(PDO $pdo, string $sql, array $params, ?int $team): array
{
    $query = $pdo->prepare($sql . ' GROUP BY t.id ORDER BY t.datum DESC' . ($team === null ? ' LIMIT 100' : ''));
    $query->execute($params);
    try {
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    } finally {
        $query->closeCursor();
    }
    return $team === null ? $rows : array_slice(trainingRosterBridgeFilterTrainingRows($pdo, $rows, $team), 0, 100);
}

function trainingCopyFixture(PDO $pdo, int $size): void
{
    Tests\Support\TrainingHistoryFixture::seed($pdo, $size);
    $pdo->exec("CREATE TABLE skupiny(id INT PRIMARY KEY,nazev VARCHAR(100));
        CREATE TABLE podskupiny(id INT PRIMARY KEY,nazev VARCHAR(100));
        INSERT INTO skupiny VALUES(1,'Alpha group'),(2,'Beta group');
        INSERT INTO podskupiny VALUES(1,'Alpha subgroup'),(2,'Beta subgroup')");
    // Keep dated ties, orphaned group assignments, duplicate names and missing links.
    $pdo->exec("UPDATE treninky SET datum=DATE_SUB(CURRENT_DATE, INTERVAL (id % 400) DAY)");
    $pdo->exec('INSERT INTO trenink_skupina VALUES(1,99);
        DELETE FROM training_roster_links WHERE trenink_id % 13=0;
        DELETE FROM trenink_sportovec WHERE trenink_id % 17=0');
}
