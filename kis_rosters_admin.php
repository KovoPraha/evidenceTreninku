<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
staffRequireActivePosition('registrar');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(410);
    exit('Původní správa byla ukončena. Použijte Skupiny a soupisky.');
}
$teamId = max(0, (int) ($_GET['team_id'] ?? 0));
header('Location: kis_roster_hierarchy_admin.php' . ($teamId ? '?team_id=' . $teamId : ''), true, 302);
exit;
