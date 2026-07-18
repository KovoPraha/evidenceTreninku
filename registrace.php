<?php
session_start();
require_once __DIR__ . '/includes/funkce.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    http_response_code(403);
    exit('Pristup odepren.');
}

header('Location: sprava_treneru.php');
exit;
