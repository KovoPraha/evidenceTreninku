<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php', true, 303);
    exit;
}

// Tento historicky pojmenovany endpoint ve skutecnosti zakladal novy zavod
// vlastnim zapisovacim tokem. Zapis je trvale vypnuty; cteni pouze zachovava
// kompatibilni odkaz na kanonicky formular.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $zavodId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $target = is_int($zavodId) && $zavodId > 0
        ? 'edit_zavod_form.php?id=' . $zavodId
        : 'prehled_zavodu.php';
    header('Location: ' . $target, true, 308);
    exit;
}

header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');
http_response_code(410);
echo 'Tento starý zapisovací endpoint byl bezpečně vyřazen. Použijte formulář pro vytvoření nebo úpravu závodu.';
exit;
