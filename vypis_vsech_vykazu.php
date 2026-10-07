<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('vsechny_vykazy')) { header('Location: login.php'); exit; }
$query = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: prehled_vsech_vykazu.php'.($query !== '' ? '?'.$query : ''), true, 302);
exit;
