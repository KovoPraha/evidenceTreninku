<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

require_once dirname(__DIR__, 2) . '/includes/athlete_profile_read.php';

final class AthleteProfileFixture
{
    public static function seed(PDO $pdo, int $size = 151): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('YEAR', static fn ($v) => $v === null ? null : (int) substr($v, 0, 4));
        } else {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        }
        $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY,datum DATE,delka VARCHAR(30),napln TEXT,obrazky TEXT);
            CREATE TABLE trenink_sportovec(trenink_id INT,sportovec_id INT);
            CREATE INDEX athlete_attendance ON trenink_sportovec(sportovec_id,trenink_id);
            CREATE TABLE trenink_mereni(trenink_id INT,mereni_id INT,poradi INT);
            CREATE INDEX athlete_links ON trenink_mereni(trenink_id,mereni_id);
            CREATE TABLE mereni_zaznamy(id INT PRIMARY KEY,sportovec_id INT,typ VARCHAR(50),
                vzdalenost VARCHAR(30),distance_unit VARCHAR(10),cas VARCHAR(50),prevod VARCHAR(20),
                cvik_id INT,segment_id INT,vaha VARCHAR(30),opakovani INT,rpe VARCHAR(30),poznamka TEXT);
            CREATE INDEX athlete_measurements ON mereni_zaznamy(sportovec_id);
            CREATE TABLE cviky(id INT PRIMARY KEY,nazev VARCHAR(100));
            CREATE TABLE segmenty(id INT PRIMARY KEY,nazev VARCHAR(100));
            CREATE TABLE sportovec_poznamka(trenink_id INT,sportovec_id INT,poznamka TEXT);
            CREATE INDEX athlete_notes ON sportovec_poznamka(sportovec_id,trenink_id)');
        $pdo->exec("INSERT INTO cviky VALUES(1,'Synthetic <exercise>'); INSERT INTO segmenty VALUES(1,'Synthetic & segment')");
        $training = $pdo->prepare('INSERT INTO treninky VALUES(?,?,?,?,?)');
        $attendance = $pdo->prepare('INSERT INTO trenink_sportovec VALUES(?,?)');
        $measurement = $pdo->prepare('INSERT INTO mereni_zaznamy VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $link = $pdo->prepare('INSERT INTO trenink_mereni VALUES(?,?,?)');
        $note = $pdo->prepare('INSERT INTO sportovec_poznamka VALUES(?,?,?)');
        $types = ['kolo','beh','posilovna','kolo_krouzek','kolo_silnice','kolo_mtb','KOLO','kolo ','unknown',''];
        $pdo->beginTransaction();
        for ($i = 1; $i <= $size; $i++) {
            $training->execute([$i, sprintf('%d-%02d-%02d', 2024 + $i % 3, 1 + $i % 12, 1 + $i % 27),
                $i % 7 === 0 ? '1,25' : ($i % 9 === 0 ? null : '0.5'), str_repeat('Synthetic <training> & content ', 80), '']);
            if ($i % 5 !== 0) {
                $attendance->execute([$i, 1]);
                if ($i % 13 === 0) {
                    $attendance->execute([$i, 1]);
                }
            }
            $attendance->execute([$i, 2]);
            $note->execute([$i, 1, 'Synthetic own note ' . $i]);
            $note->execute([$i, 2, 'Foreign hidden note ' . $i]);
            foreach (range(0, 9) as $n) {
                $id = $i * 10 + $n;
                $measurement->execute([$id, $n === 9 ? 2 : 1, $types[($i + $n) % count($types)],
                    $n % 2 ? '10.25' : '0', $n % 2 ? 'km' : null, '00:30:00', '50/14',
                    $n % 2 ? 1 : 999, $n % 2 ? 1 : null, '20', 12, '7', str_repeat('Synthetic measurement <note> ', 8)]);
                $link->execute([$i, $id, $n]);
                if ($i % 11 === 0 && $n === 0) {
                    $link->execute([$i, $id, $n]);
                }
            }
        }
        // Broken links and an unlinked own measurement must not add trainings or measurement rows.
        $link->execute([$size + 999, 10, 0]);
        $link->execute([1, 99999999, 0]);
        $pdo->commit();
    }

    /** Frozen original LEFT JOIN/DISTINCT projection from 69b69fa4, independent of the new helper. */
    public static function originalHistory(PDO $pdo, int $sid, int $year): array
    {
        $sql = 'SELECT DISTINCT t.id,t.datum,t.delka,t.napln,t.obrazky FROM treninky t
            LEFT JOIN trenink_sportovec ts ON ts.trenink_id=t.id AND ts.sportovec_id=:sid
            LEFT JOIN trenink_mereni tm ON tm.trenink_id=t.id
            LEFT JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id AND mz.sportovec_id=:sid
            WHERE (ts.sportovec_id IS NOT NULL OR mz.sportovec_id IS NOT NULL)';
        $params = [':sid' => $sid];
        if ($year > 0) {
            $sql .= ' AND YEAR(t.datum)=:year';
            $params[':year'] = $year;
        }
        $rows = self::read($pdo, $sql . ' ORDER BY t.datum DESC,t.id DESC', $params);
        $notes = [];
        foreach (self::read($pdo, 'SELECT trenink_id,poznamka FROM sportovec_poznamka WHERE sportovec_id=?', [$sid]) as $row) {
            $notes[(int) $row['trenink_id']] = $row['poznamka'];
        }
        $measurements = [];
        $ids = array_column($rows, 'id');
        if ($ids !== []) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            foreach (self::read($pdo, 'SELECT tm.trenink_id,tm.poradi,mz.id AS mereni_id,mz.typ,
                mz.vzdalenost,mz.distance_unit,mz.cas,mz.prevod,mz.cvik_id,mz.segment_id,
                mz.vaha,mz.opakovani,mz.rpe,mz.poznamka AS mereni_poznamka,
                c.nazev AS cvik_nazev,seg.nazev AS segment_nazev
                FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                LEFT JOIN cviky c ON c.id=mz.cvik_id LEFT JOIN segmenty seg ON seg.id=mz.segment_id
                WHERE tm.trenink_id IN (' . $marks . ') AND mz.sportovec_id=?
                ORDER BY tm.trenink_id ASC,tm.poradi ASC,mz.id ASC', [...$ids, $sid]) as $row) {
                $measurements[(int) $row['trenink_id']][] = $row;
            }
        }
        $minutes = 0;
        foreach ($rows as $row) {
            if (!empty($row['delka'])) {
                $minutes += (float) str_replace(',', '.', $row['delka']) * 60;
            }
        }
        return ['rows' => $rows, 'notes' => $notes, 'measurements' => $measurements, 'minutes' => $minutes, 'total' => count($rows)];
    }

    public static function originalMeasurements(PDO $pdo, int $sid): array
    {
        return self::read($pdo, 'SELECT tm.trenink_id,tm.poradi,mz.id AS mereni_id,mz.typ,
            mz.vzdalenost,mz.distance_unit,mz.cas,mz.prevod,mz.cvik_id,mz.segment_id,
            mz.vaha,mz.opakovani,mz.rpe,mz.poznamka AS mereni_poznamka,
            c.nazev AS cvik_nazev,seg.nazev AS segment_nazev,t.datum AS trenink_datum
            FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
            JOIN treninky t ON t.id=tm.trenink_id
            LEFT JOIN cviky c ON c.id=mz.cvik_id LEFT JOIN segmenty seg ON seg.id=mz.segment_id
            WHERE mz.sportovec_id=? ORDER BY t.datum DESC,t.id DESC,tm.poradi ASC,mz.id ASC', [$sid]);
    }

    public static function subset(array $reference, array $rows): array
    {
        $ids = array_flip(array_column($rows, 'id'));
        return ['rows' => $rows, 'notes' => array_intersect_key($reference['notes'], $ids),
            'measurements' => array_intersect_key($reference['measurements'], $ids),
            'minutes' => $reference['minutes'], 'total' => $reference['total']];
    }

    public static function comparable(array $value): array
    {
        unset($value['page'], $value['pages']);
        ksort($value);
        ksort($value['notes']);
        ksort($value['measurements']);
        return $value;
    }

    private static function read(PDO $pdo, string $sql, array $params): array
    {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        $s->closeCursor();
        return $rows;
    }
}
