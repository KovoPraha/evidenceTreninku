<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/db.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT jmeno, prijmeni, YEAR(narozeni) AS narozeni_rok
      FROM sportovci
     WHERE jmeno LIKE CONCAT(:q, '%') OR prijmeni LIKE CONCAT(:q, '%')
     ORDER BY jmeno, prijmeni
     LIMIT 10
");
$stmt->execute([':q' => $q]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
