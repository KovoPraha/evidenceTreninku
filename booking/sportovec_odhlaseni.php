<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/child_access.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Odhlášení vyžaduje odeslání formuláře.');
}
if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Neplatný CSRF token.');
}

$accessAccountId = filter_var($_SESSION['sportovec_pristup_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($accessAccountId !== false && childAccessIdentity($pdo, (int)$accessAccountId) !== null) {
    try {
        childAccessEvent(
            $pdo,
            (int)$accessAccountId,
            'athlete',
            (int)$accessAccountId,
            'logout',
            'Odhlášení sportovce.'
        );
    } catch (Throwable $exception) {
        // Audit outage must never trap an authenticated user in their session.
        error_log('Athlete logout audit failed: ' . $exception->getMessage());
    }
}
app_session_logout_child_identity();
header('Location: sportovec_prihlaseni.php', true, 303);
exit;
