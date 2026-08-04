<?php
require_once __DIR__ . '/includes/session_security.php';
// ajax_sportovci.php
// Našeptávač sportovců pro výběr více sportovců (JSON)
// Volání: ajax_sportovci.php?q=mar
// Vrací: [{id, label, jmeno, prijmeni, uciid, narozeni}...]

app_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Neautorizováno']);
    exit;
}

require_once __DIR__ . '/db.php';

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    out([]);
}
if (mb_strlen($q, 'UTF-8') < 2) {
    // nechceme pálit DB při 1 znaku
    out([]);
}

// normalizace + limit
$limit = 20;
if (isset($_GET['limit']) && ctype_digit($_GET['limit'])) {
    $limit = max(5, min(50, (int)$_GET['limit']));
}

// Únik wildcardů pro LIKE
$like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q);
$like = '%' . $like . '%';

try {
    // Pozn.: collation fix – explicitně převádíme na utf8mb4 a sjednotíme collation,
    // aby nepadalo na "Illegal mix of collations" (pokud je DB mixovaná).
    $sql = "
        SELECT
            s.id,
            s.jmeno,
            s.prijmeni,
            s.uci AS uciid,
            s.narozeni
        FROM sportovci s
        WHERE (
            CONVERT(s.jmeno USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :q
            OR CONVERT(s.prijmeni USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :q
            OR CONVERT(s.uci USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :q
        )
        AND s.id NOT IN (
            SELECT ss.sportovec_id FROM sportovec_skupina ss
            JOIN skupiny sk ON ss.skupina_id = sk.id
            WHERE LOWER(sk.nazev) = 'archiv'
        )
        ORDER BY s.prijmeni, s.jmeno
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $res = [];
    foreach ($rows as $r) {
        $labelParts = [];
        $labelParts[] = trim(($r['prijmeni'] ?? '') . ' ' . ($r['jmeno'] ?? ''));
        if (!empty($r['uciid'])) $labelParts[] = 'UCI: ' . $r['uciid'];
        if (!empty($r['narozeni'])) $labelParts[] = 'nar. ' . $r['narozeni'];

        $res[] = [
            'id'       => (int)$r['id'],
            'label'    => implode(' · ', $labelParts),
            'jmeno'    => (string)($r['jmeno'] ?? ''),
            'prijmeni' => (string)($r['prijmeni'] ?? ''),
            'uciid'    => (string)($r['uciid'] ?? ''),
            'narozeni' => (string)($r['narozeni'] ?? ''),
        ];
    }

    out($res);

} catch (Exception $e) {
    error_log('ajax_sportovci: '.$e->getMessage());
    out(['error' => 'Vyhledávání momentálně není dostupné.'], 500);
}
