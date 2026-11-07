<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
header('Cache-Control: no-store, private, max-age=0');
header('Referrer-Policy: no-referrer');
if (!isset($_SESSION['trener_id'])) {
    http_response_code(403);
    exit('Řešení je dostupné pouze registráři.');
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/external_sync_admin_read.php';
require_once __DIR__ . '/includes/external_sync_admin_view.php';
require_once __DIR__ . '/includes/external_sync_review_view.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(503);
    exit('Databázové připojení momentálně není dostupné.');
}
if (!staffActivePositionIs('registrar')) {
    http_response_code(403);
    exit('Přepněte do pracovního prostoru registráře.');
}
$context = null;
$error = '';
try {
    operationalCaseActor($pdo, (int)$_SESSION['trener_id'], 'registrar');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        externalSyncReject('method_not_allowed', 405);
    }
    $params = $method === 'POST' ? $_POST : $_GET;
    foreach ($params as $value) {
        if (!is_string($value) || strlen($value) > 4000) {
            externalSyncReject('invalid_input', 422);
        }
    }
    $runId = $params['run'] ?? '';
    if (!preg_match('/^[a-f0-9-]{36}$/D', $runId) || !preg_match('/^[1-9][0-9]{0,8}$/D', $params['ordinal'] ?? '')) {
        externalSyncReject('invalid_input', 422);
    }
    $ordinal = (int)$params['ordinal'];
    if ($method === 'POST') {
        if (!csrf_verify($params['csrf_token'] ?? '')) {
            externalSyncReject('invalid_csrf', 403);
        }
        if (!preg_match('/^(0|[1-9][0-9]{0,8})$/D', $params['revision'] ?? '')) {
            externalSyncReject('invalid_input', 422);
        }
        externalSyncReviewDecide(
            $pdo,
            (int)$_SESSION['trener_id'],
            $runId,
            $ordinal,
            (int)$params['revision'],
            $params['basis'] ?? '',
            $params['state'] ?? '',
            $params['reason'] ?? ''
        );
        header('Location: external_sync_review.php?' . http_build_query(['run' => $runId, 'ordinal' => $ordinal]), true, 303);
        exit;
    }
    if (isset($params['before']) && !preg_match('/^[1-9][0-9]{0,8}$/D', $params['before'])) {
        externalSyncReject('invalid_input', 422);
    }
    $context = externalSyncReviewGet($pdo, (int)$_SESSION['trener_id'], $runId, $ordinal, (int)($params['before'] ?? PHP_INT_MAX));
} catch (ExternalSyncException $exception) {
    http_response_code($exception->getCode());
    $error = match ($exception->getCode()) {
        409 => 'Údaje nebo rozhodnutí se změnily, případně párování stále není jednoznačné. Obnovte detail a znovu posuďte záznam.',
        403 => 'Neplatné ověření formuláře. Otevřete detail znovu.',
        422 => 'Vyberte platné řešení a uveďte důvod o délce 10 až 1000 znaků.',
        default => 'Vybraný záznam není dostupný.',
    };
} catch (DomainException) {
    http_response_code(403);
    $error = 'Nemáte aktuální oprávnění registráře.';
} catch (PDOException) {
    http_response_code(503);
    $error = 'Úložiště není připravené nebo je dočasně nedostupné. Změna nebyla uložena.';
}
?>
<!doctype html><html lang="cs"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Řešení záznamu ČSC / Sportsoft</title><link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
</head><body>
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4">
    <h1>Řešení záznamu ČSC / Sportsoft</h1>
    <p><a href="external_sync_admin.php">Přehled synchronizací</a></p>
    <?php if ($error !== '') : ?><div class="alert alert-danger" role="alert"><?= externalSyncAdminH($error) ?></div><?php endif; ?>
    <?php if ($context !== null) {
        externalSyncReviewRender($context);
    } ?>
</main></body></html>
