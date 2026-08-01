<?php
require_once __DIR__ . '/includes/session_security.php';
// 1) Hlavička JSON
header('Content-Type: application/json; charset=UTF-8');

// Přístup jen pro přihlášené trenéry
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Neautorizováno']);
    exit;
}

// 2) Připojení k DB (absolutní cesta)
require_once __DIR__ . '/db.php';

// 3) Získej a validuj parametr
$skupina_id = filter_input(INPUT_GET, 'skupina_id', FILTER_VALIDATE_INT);
if (!$skupina_id) {
    echo json_encode([]);
    exit;
}

try {
    // 4) Dotaz
    $stmt = $pdo->prepare("
        SELECT id, nazev
          FROM podskupiny
         WHERE skupina_id = ?
         ORDER BY poradi, nazev
    ");
    $stmt->execute([$skupina_id]);
    $podskupiny = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5) Výstup jako JSON
    echo json_encode($podskupiny);

} catch (PDOException $e) {
    // 6) Chybový JSON — detail jen do logu, ne do odpovědi
    error_log('nacti_podskupiny: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Chyba databáze.']);
}
