<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
if (!isset($_SESSION['trener_id'])) {
    http_response_code(403);
    exit('Přehled je dostupný pouze registráři.');
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/external_sync_admin_read.php';
require_once __DIR__ . '/includes/external_sync_admin_view.php';
if (!staffActivePositionIs('registrar')) {
    http_response_code(403);
    exit('Přepněte do pracovního prostoru registráře.');
}
try {
    operationalCaseActor($pdo, (int)$_SESSION['trener_id'], 'registrar');
} catch (DomainException) {
    http_response_code(403);
    exit('Nemáte aktuální oprávnění registráře.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Přehled podporuje pouze čtení.');
}
$input = externalSyncAdminInput([]);
$overview = ['rows' => [], 'has_next' => false];
$detail = null;
$error = '';
try {
    $input = externalSyncAdminInput($_GET);
    $overview = externalSyncAdminOverview($pdo, (int)$_SESSION['trener_id'], $input['connections_page']);
    if ($input['connection'] !== '') {
        $detail = externalSyncAdminDetail($pdo, (int)$_SESSION['trener_id'], $input);
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    $error = $exception->getMessage();
} catch (ExternalSyncException $exception) {
    http_response_code($exception->getCode());
    $error = 'Vybraná data nejsou dostupná. Vraťte se do přehledu zdrojů.';
} catch (PDOException) {
    http_response_code(503);
    $error = 'Úložiště synchronizací není připravené nebo je dočasně nedostupné. Zobrazení nic nevytváří.';
} catch (DomainException) {
    http_response_code(403);
    $error = 'Nemáte aktuální oprávnění registráře.';
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Synchronizace ČSC a Sportsoftu</title>
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php
include __DIR__ . '/hlavicka.php';
externalSyncAdminRender($overview, $detail, $input, defined('KIS_EXTERNAL_SYNC_ENABLED') && KIS_EXTERNAL_SYNC_ENABLED === true, $error);
?>
</body>
</html>
