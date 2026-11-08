<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

final class TrainingHistoryFixture
{
    public static function seed(PDO $pdo, int $size = 150): void
    {
        $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY,datum DATE,napln TEXT,poznamka TEXT,obrazky TEXT,kategorie VARCHAR(30),delka DECIMAL(10,2));
            CREATE TABLE treneri(id INT PRIMARY KEY,jmeno VARCHAR(100));
            CREATE TABLE trenink_trener(trenink_id INT,trener_id INT);
            CREATE TABLE trenink_skupina(trenink_id INT,skupina_id INT);
            CREATE TABLE trenink_podskupina(trenink_id INT,podskupina_id INT);
            CREATE TABLE sportovci(id INT PRIMARY KEY,prijmeni VARCHAR(100),jmeno VARCHAR(100));
            CREATE TABLE trenink_sportovec(trenink_id INT,sportovec_id INT);
            CREATE TABLE training_roster_links(id INT PRIMARY KEY,trenink_id INT,team_id INT,team_name_snapshot VARCHAR(100));
            CREATE TABLE club_team_edges(parent_team_id INT,child_team_id INT,valid_from DATE,valid_to DATE);
            CREATE INDEX history_trainer ON trenink_trener(trenink_id,trener_id);
            CREATE INDEX history_group ON trenink_skupina(trenink_id);
            CREATE INDEX history_sub ON trenink_podskupina(trenink_id);
            CREATE INDEX history_person ON trenink_sportovec(trenink_id);
            CREATE INDEX history_roster ON training_roster_links(trenink_id)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'Alpha'),(2,'Beta');
            INSERT INTO club_team_edges VALUES(1,2,'2020-01-01','2026-01-31'),(1,3,'2026-02-01',NULL),(3,1,'2026-03-01','2026-03-31')");
        $pdo->beginTransaction();
        $training = $pdo->prepare('INSERT INTO treninky VALUES(?,?,?,?,?,?,?)');
        $link = $pdo->prepare('INSERT INTO training_roster_links VALUES(?,?,?,?)');
        $participant = $pdo->prepare('INSERT INTO trenink_sportovec VALUES(?,?)');
        for ($n = 1; $n <= 12; $n++) {
            $pdo->prepare('INSERT INTO sportovci VALUES(?,?,?)')->execute([$n, 'Synthetic ' . $n, 'Local']);
        }
        for ($id = 1; $id <= $size; $id++) {
            $date = ['2026-01-31', '2026-02-01', '2026-03-31'][$id % 3];
            $training->execute([$id, $date, 'Synthetic session ' . $id, str_repeat('Synthetic note ', 100), '', $id % 2 ? 'mtb' : 'silnice', $id % 4 + .25]);
            $pdo->exec('INSERT INTO trenink_trener VALUES(' . $id . ',1),(' . $id . ',2)');
            $pdo->exec('INSERT INTO trenink_skupina VALUES(' . $id . ',1),(' . $id . ',2)');
            $pdo->exec('INSERT INTO trenink_podskupina VALUES(' . $id . ',1),(' . $id . ',2)');
            $link->execute([$id, $id, $id % 4 + 1, 'Synthetic team']);
            for ($person = 1; $person <= 12; $person++) {
                $participant->execute([$id, $person]);
            }
        }
        $pdo->commit();
    }

    /** Original two route projections at cc805561; explicit tie order is the new pagination contract. */
    public static function sql(PDO $pdo, bool $rich, string $where = '1=1'): string
    {
        $coaches = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ')" : "REPLACE(GROUP_CONCAT(DISTINCT tr.jmeno),',',', ')";
        return 'SELECT t.id,t.datum,t.napln,t.poznamka,t.obrazky,'
            . ($rich ? 't.kategorie,t.delka,' : '') . $coaches . ' AS trenere FROM treninky t
            JOIN trenink_trener tt ON t.id=tt.trenink_id JOIN treneri tr ON tt.trener_id=tr.id
            LEFT JOIN trenink_skupina tg ON t.id=tg.trenink_id
            LEFT JOIN trenink_podskupina tp ON t.id=tp.trenink_id WHERE ' . $where
            . ' GROUP BY t.id ORDER BY t.datum DESC';
    }

    /** Preserve rendered name order within each training; ignore unrendered cross-training interleaving. */
    public static function participantGroups(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => $a['trenink_id'] <=> $b['trenink_id']);
        return $rows;
    }

    public static function participants(PDO $pdo, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "CONCAT(sp.prijmeni, ' ', sp.jmeno)" : "sp.prijmeni || ' ' || sp.jmeno";
        $q = $pdo->prepare('SELECT ts.trenink_id,' . $name . ' AS jmeno_cele FROM trenink_sportovec ts
            JOIN sportovci sp ON sp.id=ts.sportovec_id WHERE ts.trenink_id IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY sp.prijmeni,sp.jmeno');
        $q->execute($ids);
        try {
            return $q->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $q->closeCursor();
        }
    }
}
