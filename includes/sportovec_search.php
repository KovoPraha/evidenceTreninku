<?php

declare(strict_types=1);
require_once __DIR__ . '/coach_scope.php';

function sportovecSearchLabel(array $person): string
{
    $parts = [trim(($person['prijmeni'] ?? '') . ' ' . ($person['jmeno'] ?? ''))];
    if (!empty($person['uciid'])) {
        $parts[] = 'UCI: ' . $person['uciid'];
    }
    if (!empty($person['narozeni'])) {
        $parts[] = 'nar. ' . $person['narozeni'];
    }
    $parts[] = '#' . (int)$person['id'];
    return implode(' · ', $parts);
}

/** Bounded search within the current workspace; an empty query is opt-in. */
function sportovecSearch(PDO $pdo, string $query, int $limit = 20, bool $browse = false, bool $training = false): array
{
    $query = trim($query);
    if (($query === '' && !$browse) || ($query !== '' && mb_strlen($query, 'UTF-8') < 2)) {
        return [];
    }
    $limit = max(5, min(50, $limit));
    $scope = $training ? coachTrainingPersonPredicate($pdo) : coachScopePersonPredicate($pdo);
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $name = $mysql
        ? "CONVERT(CONCAT(COALESCE(s.jmeno, ''), ' ', COALESCE(s.prijmeni, '')) USING utf8mb4) COLLATE utf8mb4_general_ci"
        : "(COALESCE(s.jmeno, '') || ' ' || COALESCE(s.prijmeni, ''))";
    $uci = $mysql ? 'CONVERT(s.uci USING utf8mb4) COLLATE utf8mb4_general_ci' : 's.uci';
    $conditions = [];
    $params = [];
    foreach (preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
        $conditions[] = "($name LIKE ? ESCAPE '!' OR $uci LIKE ? ESCAPE '!')";
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%';
        array_push($params, $like, $like);
    }
    $filter = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
    $preferred = $training ? 'CASE WHEN (' . coachTrainingPreferredPersonPredicate($pdo) . ') THEN 0 ELSE 1 END,' : '';
    $stmt = $pdo->prepare("SELECT s.id, s.jmeno, s.prijmeni, s.uci AS uciid, s.narozeni
        FROM sportovci s WHERE ($scope) AND NOT EXISTS (
            SELECT 1 FROM sportovec_skupina ss JOIN skupiny sk ON sk.id = ss.skupina_id
            WHERE ss.sportovec_id = s.id AND LOWER(sk.nazev) = 'archiv'
        ) $filter ORDER BY $preferred s.prijmeni, s.jmeno, s.id LIMIT $limit");
    $stmt->execute($params);
    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['id'] = (int)$row['id'];
        foreach (['jmeno', 'prijmeni', 'uciid', 'narozeni'] as $field) {
            $row[$field] = (string)($row[$field] ?? '');
        }
        $row['label'] = sportovecSearchLabel($row);
        $results[] = $row;
    }
    return $results;
}
