<?php

declare(strict_types=1);

/**
 * Registrar read projection; caller must enforce the current route permission.
 * @param array<string,mixed> $input
 * @return array{q:string,stavFilter:string,kisFilter:string,sort:string,dir:string,perPage:int,page:int,
 *     pocetStranek:int,sportovciPage:list<array<string,mixed>>,totalCount:int,aktivniCount:int,kisAktivniCount:int,kisDluhCount:int}
 */
function sportovecDirectoryRead(PDO $pdo, array $input): array
{
    $q = trim(is_string($input['q'] ?? null) ? $input['q'] : '');
    $stavFilter = $input['stav'] ?? '';
    $kisFilter = $input['kis'] ?? '';
    if (!in_array($stavFilter, ['', 'aktivni', 'cekajici', 'neaktivni', 'archiv', 'manualni'], true)) {
        $stavFilter = '';
    }
    if (!in_array($kisFilter, ['', 'kis_aktivni', 'dluh', 'bez_skupiny', 'mimo_import'], true)) {
        $kisFilter = '';
    }
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(s.prijmeni LIKE ? OR s.jmeno LIKE ? OR s.uciid LIKE ? OR s.email LIKE ?)';
        $params = array_fill(0, 4, '%' . $q . '%');
    }
    if ($stavFilter === 'manualni') {
        $where[] = 'COALESCE(s.stav_manualni, 0) = 1';
    } elseif ($stavFilter !== '') {
        $where[] = "COALESCE(s.stav_clenstvi, 'cekajici') = ?";
        $params[] = $stavFilter;
    }
    if ($kisFilter === 'kis_aktivni') {
        $where[] = 'COALESCE(s.kis_aktivni, 0) = 1';
    } elseif ($kisFilter === 'dluh') {
        $where[] = 'COALESCE(s.kis_neuhrazeno, 0) > 0';
    } elseif ($kisFilter === 'bez_skupiny') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM sportovec_skupina ss2 WHERE ss2.sportovec_id = s.id)';
    } elseif ($kisFilter === 'mimo_import') {
        $where[] = '(s.kis_last_seen_at IS NULL OR s.kis_last_seen_at < DATE_SUB(NOW(), INTERVAL 90 DAY))';
    }
    $predicate = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    // Without text search the existing activity statistic is global, regardless of other filters.
    $activity = $q === ''
        ? '(SELECT COUNT(DISTINCT sportovec_id) FROM trenink_sportovec)'
        : 'COALESCE(SUM(EXISTS (SELECT 1 FROM trenink_sportovec ts WHERE ts.sportovec_id = s.id)), 0)';
    $statement = $pdo->prepare('SELECT COUNT(*) AS total_count, ' . $activity . ' AS active_count FROM sportovci s' . $predicate);
    $statement->execute($params);
    $counts = $statement->fetch(PDO::FETCH_ASSOC);
    $totalCount = (int) $counts['total_count'];
    $aktivniCount = (int) $counts['active_count'];
    $sortMap = [
        'prijmeni' => ['s.prijmeni', 's.jmeno'], 'jmeno' => ['s.jmeno', 's.prijmeni'],
        'narozeni' => ['s.narozeni'], 'category' => ['s.category'], 'uciid' => ['s.uciid'],
        'treninku' => ['pocet_treninku'], 'kis' => ['COALESCE(s.kis_neuhrazeno, 0)', 'COALESCE(s.kis_aktivni, 0)'],
    ];
    $requestedSort = is_string($input['sort'] ?? null) ? $input['sort'] : '';
    $sort = isset($sortMap[$requestedSort]) ? $requestedSort : 'prijmeni';
    $dir = ($input['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    $perPage = (int) ($input['pp'] ?? 100);
    if (!in_array($perPage, [50, 100, 500, 0], true)) {
        $perPage = 100;
    }
    $pocetStranek = $perPage > 0 ? max(1, (int) ceil($totalCount / $perPage)) : 1;
    $page = min($pocetStranek, max(1, (int) ($input['p'] ?? 1)));
    $columns = array_merge($sortMap[$sort], ['s.id']);
    $sql = 'SELECT s.*,'
        . ' (SELECT COUNT(*) FROM trenink_sportovec ts WHERE ts.sportovec_id = s.id) AS pocet_treninku,'
        . " (SELECT GROUP_CONCAT(sk.nazev ORDER BY sk.nazev SEPARATOR ', ')"
        . ' FROM sportovec_skupina ss JOIN skupiny sk ON sk.id = ss.skupina_id'
        . ' WHERE ss.sportovec_id = s.id) AS skupiny FROM sportovci s' . $predicate
        . ' ORDER BY ' . implode(', ', array_map(static fn (string $column): string => $column . ' ' . $dir, $columns));
    if ($perPage > 0) {
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $sportovciPage = $statement->fetchAll(PDO::FETCH_ASSOC);
    $kisAktivniCount = 0;
    $kisDluhCount = 0;
    try {
        $kisAktivniCount = (int) $pdo->query('SELECT COUNT(*) FROM sportovci WHERE kis_aktivni = 1')->fetchColumn();
        $kisDluhCount = (int) $pdo->query('SELECT COUNT(*) FROM sportovci WHERE kis_neuhrazeno > 0')->fetchColumn();
    } catch (Throwable) {
        // Preserve the legacy fallback for optional KIS columns.
    }
    return compact(
        'q',
        'stavFilter',
        'kisFilter',
        'sort',
        'dir',
        'perPage',
        'page',
        'pocetStranek',
        'sportovciPage',
        'totalCount',
        'aktivniCount',
        'kisAktivniCount',
        'kisDluhCount'
    );
}
