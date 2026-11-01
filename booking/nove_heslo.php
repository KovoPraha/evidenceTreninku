<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
require_once dirname(__DIR__) . '/db.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Database bootstrap unavailable.');
}
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/password_reset_form.php';

$trainerReset = ($_GET['pro'] ?? '') === 'trener';
$loginUrl = $trainerReset ? '../login.php' : 'prihlaseni.php';
$requestUrl = $trainerReset ? 'zapomenute_heslo.php?pro=trener' : 'zapomenute_heslo.php';

$errors = [];
$success = false;
$showForm = false;
$token = '';
$checkLink = ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST';
if (!$checkLink) {
    ['errors' => $errors, 'success' => $success, 'showForm' => $showForm, 'token' => $token] = passwordResetFormSubmit(
        $pdo,
        $trainerReset,
        $_POST,
        csrf_verify((string)($_POST['csrf_token'] ?? ''))
    );
    if ($success) {
        auth_session_clear_identity('public');
        auth_session_clear_identity('child');
        auth_session_clear_identity('trainer');
        app_session_mark_identity_changed();
    }
}
?>
<!doctype html><html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nové heslo — Kovopraha</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
 integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-5" style="max-width:480px">
<div class="card shadow-sm">
<div class="card-body p-4">
<h1 class="h4">Nastavení nového hesla</h1>
<?php foreach ($errors as $error): ?>
<div class="alert alert-danger">
<?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
</div>
<?php endforeach; ?>
<?php if ($success): ?>
<div class="alert alert-success">Heslo bylo změněno. Všechna starší přihlášení byla odhlášena.</div>
<a class="btn btn-primary w-100" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Přejít na přihlášení</a>
<?php elseif ($showForm): ?>
<form method="post" id="password-reset-form">
<?= csrf_field() ?>
<input type="hidden" name="action" value="consume">
<input type="hidden" name="token" id="password-reset-token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<div class="mb-3">
<label class="form-label" for="reset-password">Nové heslo <small class="text-muted">(12–200 znaků)</small>
</label>
<div class="input-group">
<input id="reset-password" class="form-control" type="password" name="heslo"
 minlength="12" maxlength="200" autocomplete="new-password"
 aria-describedby="reset-password-help" required>
<button class="btn btn-outline-secondary" type="button" data-password-toggle="reset-password" aria-controls="reset-password" aria-pressed="false">Zobrazit</button>
</div>
<div id="reset-password-help" class="form-text">Použijte snadno zapamatovatelnou větu nebo několik slov; mezery jsou povolené.</div>
</div>
<div class="mb-3">
<label class="form-label" for="reset-password-confirmation">Nové heslo znovu</label>
<div class="input-group">
<input id="reset-password-confirmation" class="form-control" type="password" name="heslo2"
 minlength="12" maxlength="200" autocomplete="new-password" required>
<button class="btn btn-outline-secondary" type="button" data-password-toggle="reset-password-confirmation" aria-controls="reset-password-confirmation" aria-pressed="false">Zobrazit</button>
</div>
</div>
<button class="btn btn-primary w-100">Změnit heslo</button>
</form>
<?php elseif ($checkLink): ?>
<p id="password-reset-check-status" role="status">Ověřujeme platnost odkazu…</p>
<form method="post" id="password-reset-check-form" hidden>
<?= csrf_field() ?>
<input type="hidden" name="action" value="check">
<input type="hidden" name="token" id="password-reset-token">
</form>
<noscript><p>Pro bezpečné ověření odkazu zapněte JavaScript a znovu otevřete odkaz z e-mailu.</p></noscript>
<?php endif; ?>
<?php if (!$success && !$showForm): ?>
<a href="<?= htmlspecialchars($requestUrl, ENT_QUOTES, 'UTF-8') ?>">Vyžádat nový odkaz pro obnovu</a>
<?php endif; ?>
</div>
</div>
</main>
<?php if ($checkLink || $showForm): ?>
<script nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>">
<?php if ($checkLink): ?>
const fragment = new URLSearchParams(window.location.hash.slice(1));
const token = fragment.get('token') || '';
if (window.location.hash) history.replaceState(null, '', window.location.pathname + window.location.search);
if (token) {
    document.getElementById('password-reset-token').value = token;
    document.getElementById('password-reset-check-form').submit();
} else {
    document.getElementById('password-reset-check-status').textContent = 'Odkaz není platný nebo už byl použit.';
}
<?php endif; ?>
document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Skrýt' : 'Zobrazit';
    button.setAttribute('aria-pressed', show ? 'true' : 'false');
}));
</script>
<?php endif; ?>
<?php publicShellFooter(); ?>
</body>
</html>
