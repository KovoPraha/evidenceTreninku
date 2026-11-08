<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

require_once __DIR__ . '/TrainingHistoryFixture.php';
require_once dirname(__DIR__, 2) . '/includes/coach_history_read.php';

final class CoachHistoryFixture
{
    public static function seed(PDO $pdo, int $size = 151): void
    {
        // db.php uses PDO MySQL's default emulated prepares, including repeated :q.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        }
        TrainingHistoryFixture::seed($pdo, $size);
        $pdo->exec('ALTER TABLE treninky ADD mereni TEXT;
            CREATE TABLE skupiny(id INT PRIMARY KEY,nazev VARCHAR(100));
            CREATE TABLE podskupiny(id INT PRIMARY KEY,nazev VARCHAR(100));
            CREATE TABLE tagy(id INT PRIMARY KEY,nazev VARCHAR(100));
            CREATE TABLE trenink_tag(trenink_id INT,tag_id INT);
            CREATE INDEX coach_history_tags ON trenink_tag(trenink_id)');
        $pdo->exec("INSERT INTO skupiny VALUES(1,'First'),(2,'Second');
            INSERT INTO podskupiny VALUES(1,'Child one'),(2,'Child two');
            INSERT INTO tagy VALUES(1,'Bike'),(2,'Road')");
        $pdo->prepare('UPDATE treninky SET mereni=?')->execute([str_repeat('Synthetic measurement ', 30)]);
        $pdo->beginTransaction();
        for ($id = 1; $id <= $size; $id++) {
            $pdo->exec('INSERT INTO trenink_tag VALUES(' . $id . ',1),(' . $id . ',2)');
        }
        $pdo->commit();
    }

    public static function cases(PDO $pdo): array
    {
        $native = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $year = $native ? 'YEAR(t.datum)' : "CAST(strftime('%Y',t.datum) AS INTEGER)";
        $month = $native ? 'MONTH(t.datum)' : "CAST(strftime('%m',t.datum) AS INTEGER)";
        $plain = 'FROM trenink_trener tt JOIN treninky t ON tt.trenink_id=t.id';
        $scope = ' WHERE tt.trener_id=:uid AND EXISTS(SELECT 1 FROM trenink_trener ctt WHERE ctt.trenink_id=t.id AND ctt.trener_id=:uid)';
        return [
            'all' => [$plain . $scope, [':uid' => 1]],
            'other_coach' => [$plain . $scope, [':uid' => 2]],
            'group' => [$plain . ' JOIN trenink_skupina ts2 ON ts2.trenink_id=t.id' . $scope
                . ' AND ts2.skupina_id=:sid', [':uid' => 1, ':sid' => 2]],
            'year' => [$plain . $scope . ' AND ' . $year . '=:rok', [':uid' => 1, ':rok' => 2026]],
            'month_without_year' => [$plain . $scope . ' AND ' . $month . '=:mesic', [':uid' => 1, ':mesic' => 3]],
            'year_month' => [$plain . $scope . ' AND ' . $year . '=:rok AND ' . $month . '=:mesic',
                [':uid' => 1, ':rok' => 2026, ':mesic' => 2]],
            'search' => [$plain . $scope . ' AND (t.napln LIKE :q OR t.poznamka LIKE :q)', [':uid' => 1, ':q' => '%session 12%']],
            'wildcard' => [$plain . $scope . ' AND (t.napln LIKE :q OR t.poznamka LIKE :q)', [':uid' => 1, ':q' => '%_%']],
            'rare' => [$plain . $scope . ' AND t.id=:last_id', [':uid' => 1, ':last_id' => 1]],
            'empty' => [$plain . $scope, [':uid' => 99]],
            'scope' => [$plain . $scope . ' AND t.id<=:scope_id', [':uid' => 1, ':scope_id' => 30]],
        ];
    }

    public static function original(PDO $pdo, string $from, array $params, int $team): array
    {
        $statement = $pdo->prepare('SELECT t.id,t.datum,t.delka,t.napln,t.poznamka,t.obrazky,t.mereni '
            . $from . ' ORDER BY t.datum DESC,t.id DESC');
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if ($team > 0) {
            $rows = \trainingRosterBridgeFilterTrainingRows($pdo, $rows, $team);
        }
        return self::related($pdo, $rows);
    }

    public static function related(PDO $pdo, array $rows): array
    {
        $result = ['rows' => $rows, 'labels' => \trainingRosterBridgeTrainingLabels($pdo, $rows)];
        $ids = array_column($rows, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $fullName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "CONCAT(sp.prijmeni, ' ', sp.jmeno)" : "sp.prijmeni || ' ' || sp.jmeno";
        $queries = [
            'groups' => "SELECT ts.trenink_id,s.nazev FROM trenink_skupina ts JOIN skupiny s ON s.id=ts.skupina_id WHERE ts.trenink_id IN ($marks) ORDER BY s.nazev",
            'subgroups' => "SELECT tp.trenink_id,p.nazev FROM trenink_podskupina tp JOIN podskupiny p ON p.id=tp.podskupina_id WHERE tp.trenink_id IN ($marks) ORDER BY p.nazev",
            'coaches' => "SELECT tt.trenink_id,tr.jmeno FROM trenink_trener tt JOIN treneri tr ON tr.id=tt.trener_id WHERE tt.trenink_id IN ($marks) ORDER BY tr.jmeno",
            'participants' => "SELECT ts.trenink_id,$fullName AS jmeno FROM trenink_sportovec ts JOIN sportovci sp ON sp.id=ts.sportovec_id WHERE ts.trenink_id IN ($marks) ORDER BY sp.prijmeni",
            'tags' => "SELECT ttg.trenink_id,tg.nazev FROM trenink_tag ttg JOIN tagy tg ON tg.id=ttg.tag_id WHERE ttg.trenink_id IN ($marks) ORDER BY tg.nazev",
        ];
        foreach ($queries as $key => $sql) {
            $result[$key] = [];
            if ($ids === []) {
                continue;
            }
            $statement = $pdo->prepare($sql);
            $statement->execute($ids);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $result[$key][(int)$r['trenink_id']][] = $r['nazev'] ?? $r['jmeno'];
            }
            $statement->closeCursor();
        }
        return $result;
    }

    /** Lookup-map insertion order is not rendered. Call only outside the measured interval. */
    public static function normalize(array $value): array
    {
        foreach ($value as $key => &$map) {
            if ($key !== 'rows') {
                ksort($map, SORT_NUMERIC);
            }
        }
        return $value;
    }

    public static function subset(array $value, array $rows): array
    {
        $ids = array_flip(array_column($rows, 'id'));
        foreach ($value as $key => &$map) {
            $map = $key === 'rows' ? $rows : array_intersect_key($map, $ids);
        }
        return self::normalize($value);
    }
}
