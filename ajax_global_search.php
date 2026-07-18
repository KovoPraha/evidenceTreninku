<?php
/**
 * AJAX endpoint: globální vyhledávání
 * GET ?q=... — vrací JSON s výsledky pro sportovce, tréninky, závody
 */
session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Nepřihlášen']);
    exit;
}
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['sportovci' => [], 'treninky' => [], 'zavody' => []]);
    exit;
}

$like  = '%' . $q . '%';
$uid   = (int)$_SESSION['trener_id'];
$isAdmin = canAccess('sprava_treninku');

$results = ['sportovci' => [], 'treninky' => [], 'zavody' => []];

// ── Sportovci ────────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "SELECT s.id, s.jmeno, s.prijmeni, s.hash
         FROM sportovci s
         WHERE s.prijmeni LIKE :q OR s.jmeno LIKE :q OR CONCAT(s.prijmeni,' ',s.jmeno) LIKE :q OR CONCAT(s.jmeno,' ',s.prijmeni) LIKE :q
         ORDER BY s.prijmeni, s.jmeno
         LIMIT 6"
    );
    $stmt->execute([':q' => $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results['sportovci'][] = [
            'label' => $row['prijmeni'] . ' ' . $row['jmeno'],
            'url'   => 'sportovec_detail.php?id=' . (int)$row['id'],
            'icon'  => 'bi-person',
        ];
    }
} catch (PDOException $e) {
    error_log('ajax_global_search sportovci: ' . $e->getMessage());
}

// ── Tréninky (jen trenérovy) ─────────────────────────────────────────────────
try {
    $sql = "SELECT t.id, t.datum, t.napln
            FROM treninky t
            JOIN trenink_trener tt ON tt.trenink_id = t.id
            WHERE tt.trener_id = :uid
              AND (t.napln LIKE :q OR t.poznamka LIKE :q)
            ORDER BY t.datum DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $uid, ':q' => $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $napln = mb_substr(strip_tags((string)$row['napln']), 0, 60);
        if (mb_strlen((string)$row['napln']) > 60) $napln .= '…';
        $results['treninky'][] = [
            'label' => $row['datum'] . ($napln !== '' ? ' – ' . $napln : ''),
            'url'   => 'edit_trenink.php?id=' . (int)$row['id'],
            'icon'  => 'bi-calendar-event',
        ];
    }
} catch (PDOException $e) {
    error_log('ajax_global_search treninky: ' . $e->getMessage());
}

// ── Závody ───────────────────────────────────────────────────────────────────
try {
    $sql = "SELECT z.id, z.datum, z.popis FROM zavody z WHERE z.popis LIKE :q ORDER BY z.datum DESC LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $label = ($row['datum'] ? $row['datum'] . ' – ' : '') . mb_substr((string)$row['popis'], 0, 60);
        $results['zavody'][] = [
            'label' => $label,
            'url'   => 'prehled_zavodu.php',
            'icon'  => 'bi-trophy',
        ];
    }
} catch (PDOException $e) {
    error_log('ajax_global_search zavody: ' . $e->getMessage());
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
