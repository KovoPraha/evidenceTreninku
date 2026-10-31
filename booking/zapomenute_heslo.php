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
require_once dirname(__DIR__) . '/includes/auth_rate_limit.php';
require_once dirname(__DIR__) . '/includes/password_reset.php';
require_once dirname(__DIR__) . '/includes/app_url.php';
require_once dirname(__DIR__) . '/includes/trainer_password_reset.php';

$trainerReset = ($_GET['pro'] ?? '') === 'trener';
$loginUrl = $trainerReset ? '../login.php' : 'prihlaseni.php';

$submitted = false;
$errors = [];
$localResetLink = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Neplatný bezpečnostní token.';
    } else {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        try {
            $ip = auth_rate_limit_request_ip();
            if (auth_rate_limit_reserve_attempt($pdo, 'password_reset', $identifier, $ip)) {
                $result = $trainerReset
                    ? trainerPasswordResetQueueRequest($pdo, $identifier, appUrl('booking/nove_heslo.php') . '?pro=trener')
                    : passwordResetQueueRequest($pdo, $identifier, appUrl('booking/nove_heslo.php'));
                if (app_session_request_is_local() && is_string($result['token'])) {
                    $localResetLink = $trainerReset
                        ? 'nove_heslo.php?pro=trener#token=' . rawurlencode($result['token'])
                        : 'nove_heslo.php#token=' . rawurlencode($result['token']);
                }
            }
            $submitted = true;
        } catch (Throwable $exception) {
            error_log('Password reset request failed: ' . $exception->getMessage());
            // Fail closed without revealing whether the identifier exists.
            $submitted = true;
        }
    }
}
?>
<!doctype html><html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Obnova hesla — Kovopraha</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
 integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-5" style="max-width:480px">
<div class="card shadow-sm">
<div class="card-body p-4">
<h1 class="h4">
<?= $trainerReset ? 'Obnova trenérského hesla' : 'Obnova hesla' ?>
</h1>
<?php foreach ($errors as $error): ?>
<div class="alert alert-danger">
<?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
</div>
<?php endforeach; ?>
<?php if ($submitted): ?>
<div class="alert alert-success">Pokud účet existuje a lze jej bezpečně ověřit, zařadili jsme další postup k odeslání na příslušný e-mail.</div>
<?php if ($localResetLink !== null): ?>
<div class="alert alert-warning">
<strong>Jen localhost:</strong> <a class="alert-link" href="<?= htmlspecialchars($localResetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Otevřít testovací odkaz pro obnovu</a>.</div>
<?php endif; ?>
<?php else: ?>
<p class="text-muted">
<?php if ($trainerReset): ?>
Zadejte e-mail uložený u vašeho trenérského účtu.
Pokud k němu nemáte přístup nebo zpráva nedorazí ani do spamu, obraťte se na správce klubu.
<?php else: ?>
Zadejte e-mail rodičovského účtu nebo přihlašovací jméno sportovce.
<?php endif; ?>
</p>
<form method="post">
<?= csrf_field() ?>
<div class="mb-3">
<label class="form-label" for="password-reset-identifier">
<?= $trainerReset ? 'E-mail trenérského účtu' : 'E-mail nebo přihlašovací jméno' ?>
</label>
<input class="form-control" id="password-reset-identifier" name="identifier"
 type="<?= $trainerReset ? 'email' : 'text' ?>" maxlength="254" autocomplete="username" required autofocus>
</div>
<button class="btn btn-primary w-100">Poslat odkaz pro obnovu</button>
</form>
<?php endif; ?>
<p class="mt-3 mb-0 text-center">
<a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Zpět na přihlášení</a>
</p>
</div>
</div>
</main>
<?php publicShellFooter(); ?>
</body>
</html>
