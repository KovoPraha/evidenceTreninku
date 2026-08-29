<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php', true, 303);
    exit;
}

// Legacy import mazal vsechny vysledky pred validaci celeho souboru a osoby
// paroval jen podle jmena. Dokud nebude k dispozici staging + jednoznacne ID,
// musi endpoint selhat bez jakekoli zmeny dat.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: prehled_zavodu.php', true, 308);
    exit;
}

header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');
http_response_code(410);
echo 'Starý import výsledků byl bezpečně vyřazen. Data závodu upravte v kanonickém formuláři.';
exit;
