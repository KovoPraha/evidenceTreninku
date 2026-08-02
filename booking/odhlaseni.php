<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/../csrf_helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Odhlášení vyžaduje odeslání formuláře.');
}
if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Neplatný CSRF token.');
}

app_session_logout_public_identity();
header('Location: kalendar.php', true, 303);
exit;
