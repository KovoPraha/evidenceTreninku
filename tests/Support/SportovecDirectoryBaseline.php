<?php

declare(strict_types=1);
// Frozen a3fae123 directory read; only tie order (ID) is specified for comparison.
function w75DirectoryBaseline(PDO $pdo, array $input): array
{
    $q = trim($input['q'] ?? '');
    $stavFilter = $input['stav'] ?? '';
    $kisFilter = $input['kis'] ?? '';
    if (!in_array($stavFilter, ['', 'aktivni', 'cekajici', 'neaktivni', 'archiv', 'manualni'], true)) {
        $stavFilter = '';
    }
    if (!in_array($kisFilter, ['', 'kis_aktivni', 'dluh', 'bez_skupiny', 'mimo_import'], true)) {
        $kisFilter = '';
    }

    $sql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM trenink_sportovec ts WHERE ts.sportovec_id = s.id) AS pocet_treninku,
           (SELECT GROUP_CONCAT(sk.nazev ORDER BY sk.nazev SEPARATOR ', ')
            FROM sportovec_skupina ss
            JOIN skupiny sk ON sk.id = ss.skupina_id
            WHERE ss.sportovec_id = s.id) AS skupiny
    FROM sportovci s
";
    $params = [];
    $where = [];

    if ($q !== '') {
        $where[] = "(s.prijmeni LIKE :q OR s.jmeno LIKE :q OR s.uciid LIKE :q OR s.email LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    if ($stavFilter === 'manualni') {
        $where[] = "COALESCE(s.stav_manualni, 0) = 1";
    } elseif ($stavFilter !== '') {
        $where[] = "COALESCE(s.stav_clenstvi, 'cekajici') = :stav";
        $params[':stav'] = $stavFilter;
    }
    if ($kisFilter === 'kis_aktivni') {
        $where[] = "COALESCE(s.kis_aktivni, 0) = 1";
    } elseif ($kisFilter === 'dluh') {
        $where[] = "COALESCE(s.kis_neuhrazeno, 0) > 0";
    } elseif ($kisFilter === 'bez_skupiny') {
        $where[] = "NOT EXISTS (SELECT 1 FROM sportovec_skupina ss2 WHERE ss2.sportovec_id = s.id)";
    } elseif ($kisFilter === 'mimo_import') {
        $where[] = "(s.kis_last_seen_at IS NULL OR s.kis_last_seen_at < DATE_SUB(NOW(), INTERVAL 90 DAY))";
    }
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    // ── Řazení (klik na hlavičku sloupce) ────────────────────────────────────────
    $sortMap = [
        'prijmeni' => ['s.prijmeni', 's.jmeno'],
        'jmeno'    => ['s.jmeno', 's.prijmeni'],
        'narozeni' => ['s.narozeni'],
        'category' => ['s.category'],
        'uciid'    => ['s.uciid'],
        'treninku' => ['pocet_treninku'],
        'kis'      => ['COALESCE(s.kis_neuhrazeno, 0)', 'COALESCE(s.kis_aktivni, 0)'],
    ];
    $sort = isset($sortMap[$input['sort'] ?? '']) ? $input['sort'] : 'prijmeni';
    $dir  = (($input['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
    $sql .= " ORDER BY " . implode(', ', array_map(fn ($c) => "$c $dir", $sortMap[$sort]));

    // Only tie order is specified for stable pagination comparisons.
    $sql .= ", s.id $dir";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Stránkování ──────────────────────────────────────────────────────────────
    $perPage = (int)($input['pp'] ?? 100);
    if (!in_array($perPage, [50, 100, 500, 0], true)) {
        $perPage = 100;
    }  // 0 = všichni
    $page = max(1, (int)($input['p'] ?? 1));
    $pocetStranek = $perPage > 0 ? max(1, (int)ceil(count($sportovci) / $perPage)) : 1;
    if ($page > $pocetStranek) {
        $page = $pocetStranek;
    }
    $sportovciPage = $perPage > 0 ? array_slice($sportovci, ($page - 1) * $perPage, $perPage) : $sportovci;

    // Statistiky
    $totalCount = count($sportovci);
    if ($q === '') {
        $stQ = $pdo->query("SELECT COUNT(DISTINCT sportovec_id) FROM trenink_sportovec");
        $aktivniCount = (int)$stQ->fetchColumn();
    } else {
        $aktivniCount = 0;
        foreach ($sportovci as $s) {
            if ((int)$s['pocet_treninku'] > 0) {
                $aktivniCount++;
            }
        }
    }
    $kisAktivniCount = 0;
    $kisDluhCount = 0;
    try {
        $kisAktivniCount = (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE kis_aktivni = 1")->fetchColumn();
        $kisDluhCount = (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE kis_neuhrazeno > 0")->fetchColumn();
    } catch (Throwable $e) {
        // KIS sloupce vznikaji auto-migraci; pri prvnim nacteni bez migrace jen nezobrazime statistiky.
    }
    return compact('q', 'stavFilter', 'kisFilter', 'sort', 'dir', 'perPage', 'page', 'pocetStranek', 'sportovciPage', 'totalCount', 'aktivniCount', 'kisAktivniCount', 'kisDluhCount');
}
