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
    exit('Původní správa struktur byla ukončena. Použijte Skupiny a soupisky.');
}
header('Location: kis_roster_hierarchy_admin.php', true, 302);
exit;
