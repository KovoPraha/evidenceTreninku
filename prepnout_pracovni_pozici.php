<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/staff_workspaces.php';

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Přepnutí pracovní pozice vyžaduje potvrzený formulář.');
}
if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    exit('Formulář vypršel. Obnovte stránku.');
}

try {
    $targetPosition = (string)($_POST['position'] ?? '');
    staffSwitchPosition(
        $pdo,
        (int)$_SESSION['trener_id'],
        $targetPosition,
        (string)($_POST['reason'] ?? '')
    );
    $_SESSION['flash_success'] = 'Aktivní pracovní pozice byla přepnuta.';
    $next = staffNormalizeRoute((string)($_POST['next'] ?? ''));
    if ($next !== '' && staffRouteOwner($next) === $targetPosition) {
        $orderCode = (string)($_POST['payment_order_code'] ?? '');
        if ($next === 'eshop_payments_admin.php' && preg_match('/^[A-Z0-9-]{1,64}$/D', $orderCode) === 1) {
            $next .= '?q=' . rawurlencode($orderCode);
        }
        header('Location: ' . $next, true, 303);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    $_SESSION['flash_error'] = $exception->getMessage();
}

header('Location: pracovni_pozice.php', true, 303);
exit;
