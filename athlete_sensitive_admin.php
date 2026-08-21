<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/person_sensitive.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, ['ok' => false, 'message' => 'Operace není povolena.']);
}
if (!isset($_SESSION['trener_id']) || !staffActivePositionIs('registrar')) {
    $respond(403, ['ok' => false, 'message' => 'Přístup odepřen.']);
}
if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
    $respond(419, ['ok' => false, 'message' => 'Formulář vypršel. Obnovte stránku.']);
}
$recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$action = (string)($_POST['action'] ?? '');
if ($recordId === false || !in_array($action, ['mask', 'reveal'], true)) {
    $respond(422, ['ok' => false, 'message' => 'Požadavek není platný.']);
}

try {
    $value = $action === 'reveal'
        ? personSensitiveAdminReveal(
            $pdo,
            (int)$recordId,
            (string)($_POST['reason'] ?? ''),
            null,
            (string)($_SERVER['REMOTE_ADDR'] ?? '')
        )
        : personSensitiveAdminMaskedView(
            $pdo,
            (int)$recordId,
            null,
            (string)($_SERVER['REMOTE_ADDR'] ?? '')
        );
    $respond(200, ['ok' => true, 'value' => $value]);
} catch (InvalidArgumentException $exception) {
    $respond(422, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (Throwable) {
    $respond(503, ['ok' => false, 'message' => 'Citlivý údaj nyní nelze bezpečně zobrazit.']);
}
