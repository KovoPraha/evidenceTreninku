<?php
/**
 * AJAX endpoint – uložení poznámky sportovce k tréninku (public, auth via hash)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

$hash = trim($_POST['hash'] ?? '');
$tid  = (int)($_POST['trenink_id'] ?? 0);
$poz  = trim($_POST['poznamka'] ?? '');

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Neplatny CSRF token.']);
    exit;
}

if (!$hash || $tid <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatné parametry.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM sportovci WHERE hash = ?');
$stmt->execute([$hash]);
$spRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$spRow) {
    echo json_encode(['ok' => false, 'msg' => 'Neplatný přístup.']);
    exit;
}

$sportovec_id = (int)$spRow['id'];

try {
    $own = $pdo->prepare("
        SELECT 1
        FROM treninky t
        LEFT JOIN trenink_sportovec ts ON ts.trenink_id = t.id AND ts.sportovec_id = ?
        LEFT JOIN trenink_mereni tm ON tm.trenink_id = t.id
        LEFT JOIN mereni_zaznamy mz ON mz.id = tm.mereni_id AND mz.sportovec_id = ?
        WHERE t.id = ?
          AND (ts.sportovec_id IS NOT NULL OR mz.sportovec_id IS NOT NULL)
        LIMIT 1
    ");
    $own->execute([$sportovec_id, $sportovec_id, $tid]);
    if (!$own->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Trenink nepatri tomuto sportovci.']);
        exit;
    }

    $chk = $pdo->prepare('SELECT COUNT(*) FROM sportovec_poznamka WHERE sportovec_id = ? AND trenink_id = ?');
    $chk->execute([$sportovec_id, $tid]);

    if ((int)$chk->fetchColumn() > 0) {
        $pdo->prepare('UPDATE sportovec_poznamka SET poznamka = ? WHERE sportovec_id = ? AND trenink_id = ?')
            ->execute([$poz, $sportovec_id, $tid]);
    } else {
        $pdo->prepare('INSERT INTO sportovec_poznamka (sportovec_id, trenink_id, poznamka) VALUES (?, ?, ?)')
            ->execute([$sportovec_id, $tid, $poz]);
    }

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Chyba DB: ' . $e->getMessage()]);
}
