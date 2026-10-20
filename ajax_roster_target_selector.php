<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/roster_target_selector.php';

$date = (string)($_GET['date'] ?? '');
$selected = $_GET['selected'] ?? [];
if (!is_array($selected)) $selected = [];
$selected = array_values(array_unique(array_filter(array_map('intval', $selected), static fn(int $id): bool => $id > 0)));
if (count($selected) > 100) {
    http_response_code(400);
    exit('Příliš mnoho cílů.');
}
header('Content-Type: text/html; charset=utf-8');
echo rosterTargetSelectorHtml($pdo, $date, $selected);
