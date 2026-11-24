<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/auth_rate_limit.php';
require_once dirname(__DIR__) . '/includes/app_url.php';
require_once dirname(__DIR__) . '/includes/auth_mail_outbox.php';
require_once dirname(__DIR__) . '/includes/mail_request_audit.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Database bootstrap unavailable.');
}

function verifyResendH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$returnTo = bookingReturnDestination($_POST['redirect'] ?? $_GET['redirect'] ?? null, 'moje_osoby.php');
$submitted = false;
$errors = [];
$requestId = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $requestId = bin2hex(random_bytes(16));
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $auditReady = false;
    try {
        mailRequestAuditStart($pdo, $requestId, 'verify_resend', $email);
        $auditReady = true;
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            mailRequestAuditFinish($pdo, $requestId, 'rejected', 'csrf_invalid');
            $errors[] = 'Formulář vypršel. Obnovte stránku.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            mailRequestAuditFinish($pdo, $requestId, 'rejected', 'invalid_input');
            $submitted = true;
        } elseif (!auth_rate_limit_reserve_attempt($pdo, 'email_verification_resend', $email, auth_rate_limit_request_ip())) {
            mailRequestAuditFinish($pdo, $requestId, 'rejected', 'rate_limited');
            $submitted = true;
        } else {
            $outcome = authMailResendVerification($pdo, $email, appUrl('booking/overeni.php'), null, $returnTo, $requestId);
            if (!$outcome['queued']) {
                mailRequestAuditFinish($pdo, $requestId, 'rejected', 'not_eligible');
            }
            $submitted = true;
        }
    } catch (Throwable $exception) {
        if ($auditReady && !$pdo->inTransaction()) {
            try {
                mailRequestAuditFinish($pdo, $requestId, 'failed', 'queue_error');
            } catch (Throwable $auditError) {
                error_log('Verification resend audit completion failed: request_id=' . $requestId . ' class=' . get_class($auditError));
            }
        }
        error_log('Verification resend failed: request_id=' . $requestId . ' class=' . get_class($exception));
        $errors[] = 'Žádost se nyní nepodařilo bezpečně zařadit. Zkuste to později.';
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Nový ověřovací odkaz — KOVO Praha</title>
<?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-5" style="max-width:520px">
<div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h4">Nový ověřovací odkaz</h1>
<p class="text-muted">Pokud odkaz nedorazil nebo vypršel, zadejte e-mail použitý při registraci.</p>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=verifyResendH($error)?></div><?php endforeach; ?>
<?php if ($requestId !== null): ?>
<div class="alert alert-secondary small">Reference požadavku: <code><?=verifyResendH($requestId)?></code>. Pokud zpráva nepřijde, sdělte ji správci klubu.</div>
<?php endif; ?>
<?php if ($submitted): ?>
<div class="alert alert-success">Pokud účet čeká na ověření, zařadili jsme nový odkaz k odeslání. Zkontrolujte také spam.</div>
<?php else: ?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="redirect" value="<?=verifyResendH($returnTo)?>">
<label class="form-label" for="verify-email">E-mail</label>
<input class="form-control mb-3" type="email" name="email" id="verify-email" maxlength="254" required autofocus>
<button class="btn btn-primary w-100">Zařadit nový odkaz k odeslání</button>
</form>
<?php endif; ?>
<p class="mt-3 mb-0"><a href="prihlaseni.php?redirect=<?=rawurlencode($returnTo)?>">Zpět na přihlášení</a></p>
</div></div>
</main>
<?php publicShellFooter(); ?>
</body>
</html>
