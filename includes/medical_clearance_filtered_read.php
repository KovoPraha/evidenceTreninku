<?php

declare(strict_types=1);

/** Reuse a bounded candidate set; broad state queries never repeat the person's search in history. */
final class MedicalClearanceFilteredRead
{
    public static function page(PDO $pdo, string $base, array $params, array $filters, string $on): array
    {
        $query = $pdo->prepare('SELECT w.id ' . $base . ' LIMIT 257');
        try {
            $query->execute($params);
            $ids = $query->fetchAll(PDO::FETCH_COLUMN);
        } finally {
            $query->closeCursor();
        }
        if ($ids === []) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }
        if (count($ids) <= 256) {
            // Recheck the original predicates on these IDs, without another full candidate scan.
            $base .= ' AND w.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = [...$params, ...$ids];
            if ($filters['state'] !== '') {
                return medicalClearanceStatePage($pdo, $base, $params, $filters, $on);
            }
        } elseif ($filters['state'] !== '') {
            return MedicalClearanceStateRead::filteredPage($pdo, $base, $params, $filters, $on);
        }
        $page = staffListPage($pdo, 'w.*,s.jmeno,s.prijmeni', $base, 'w.next_review_on,w.id', $params, $filters['page']);
        $page['rows'] = medicalClearanceListStatuses($pdo, $page['rows'], $on);
        return $page;
    }
}
