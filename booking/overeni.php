<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/one_time_token.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/quick_account_password.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function verificationSafeRedirect(mixed $value): string
{
    return bookingReturnDestination($value, 'moje_osoby.php');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$token = $method === 'POST' ? trim((string)($_POST['token'] ?? '')) : '';
$legacyToken = $method === 'GET' ? trim((string)($_GET['token'] ?? '')) : '';
$legacyToken = one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, $legacyToken) !== ''
    ? $legacyToken
    : '';
$ok = false;
$attempted = $method === 'POST';
$redirect = verificationSafeRedirect($_POST['redirect'] ?? $_GET['redirect'] ?? null);

if ($attempted && csrf_verify((string)($_POST['csrf_token'] ?? '')) && $token !== '') {
    $uzivatel = one_time_email_verification_consume($pdo, $token);
    if ($uzivatel) {
        app_session_mark_authenticated();
        auth_session_bind_public_user(
            (int)$uzivatel['id'],
            (int)$uzivatel['session_version']
        );
        unset($_SESSION['quick_account_password_setup']);
        try {
            $setup = quickAccountPasswordSetupIssue($pdo, (int)$uzivatel['id'], (int)$uzivatel['session_version']);
            if ($setup !== null) {
                $_SESSION['quick_account_password_setup'] = $setup + ['return' => $redirect];
                $redirect = 'prvni_heslo.php';
            }
        } catch (Throwable $exception) {
            error_log('Quick account onboarding unavailable: ' . get_class($exception));
            // Verification remains successful; the ordinary reset flow is available.
            $redirect = 'prvni_heslo.php';
        }
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ověření emailu — Rezervace Kovopraha</title>
    <meta name="referrer" content="no-referrer">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php appUiAssets(); ?>
    <?php if ($ok): ?>
        <meta http-equiv="refresh" content="3;url=<?=h($redirect)?>">
    <?php endif; ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<div class="container mt-5" style="max-width:500px">
    <div class="card shadow text-center p-4">
        <?php if ($ok): ?>
            <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
            <h5>Email ověřen!</h5>
            <p class="text-muted">Přesměrováváme vás na další krok…</p>
            <a class="btn btn-primary" href="<?=h($redirect)?>">Pokračovat</a>
        <?php elseif ($attempted): ?>
            <i class="bi bi-x-circle-fill text-danger fs-1 mb-3"></i>
            <h5>Neplatný nebo expirovaný odkaz</h5>
            <p class="text-muted">Požádejte o <a href="znovu_overit_email.php?redirect=<?=rawurlencode($redirect)?>">nový ověřovací odkaz</a>.</p>
        <?php else: ?>
            <i class="bi bi-envelope-check text-primary fs-1 mb-3"></i>
            <h5>Dokončit ověření e-mailu</h5>
            <p class="text-muted" id="verification-help">Ověření dokončíte výslovným kliknutím na tlačítko.</p>
            <form method="post" id="verification-form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" id="verification-token" value="<?= h($legacyToken) ?>">
                <input type="hidden" name="redirect" id="verification-redirect" value="<?=h($redirect)?>">
                <button type="submit" id="verification-submit" class="btn btn-primary" <?= $legacyToken === '' ? 'disabled' : '' ?>>
                    Ověřit e-mail
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if (!$attempted): ?>
<script nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>">
(() => {
    const params = new URLSearchParams(window.location.hash.slice(1));
    const token = params.get('token') || '';
    history.replaceState(null, '', window.location.pathname);
    const submit = document.getElementById('verification-submit');
    if (!/^[a-f0-9]{48,128}$/.test(token)) {
        if (!document.getElementById('verification-token').value) {
            document.getElementById('verification-help').textContent = 'Odkaz není platný nebo je neúplný.';
        }
        return;
    }
    document.getElementById('verification-token').value = token;
    const redirect = params.get('redirect');
    // Only carry form data here; the shared server allowlist decides the destination.
    if (redirect && redirect.length <= 1500) {
        document.getElementById('verification-redirect').value = redirect;
    }
    submit.disabled = false;
})();
</script>
<?php endif; ?>
<?php publicShellFooter(); ?>
</body>
</html>
