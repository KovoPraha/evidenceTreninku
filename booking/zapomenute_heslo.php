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
require_once dirname(__DIR__) . '/includes/password_reset_request_audit.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$trainerReset = ($_GET['pro'] ?? '') === 'trener';
$loginUrl = $trainerReset ? '../login.php' : 'prihlaseni.php';

$submitted = false;
$errors = [];
$localResetLink = null;
$requestId = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfInput = $_POST['csrf_token'] ?? null;
    $identifierInput = $_POST['identifier'] ?? null;
    $csrfValid = is_string($csrfInput) && csrf_verify($csrfInput);
    $result = passwordResetRequestHandle(
        $pdo,
        $trainerReset,
        is_string($identifierInput) ? $identifierInput : '',
        $csrfValid,
        auth_rate_limit_request_ip(),
        appUrl('booking/nove_heslo.php') . ($trainerReset ? '?pro=trener' : '')
    );
    $requestId = $result['request_id'];
    if (!$csrfValid) {
        $errors[] = 'Neplatný bezpečnostní token.';
    } else {
        if (app_session_request_is_local() && is_string($result['token'])) {
            $localResetLink = $trainerReset
                ? 'nove_heslo.php?pro=trener#token=' . rawurlencode($result['token'])
                : 'nove_heslo.php#token=' . rawurlencode($result['token']);
        }
        $submitted = true;
    }
}
?>
<!doctype html><html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Obnova hesla — Kovopraha</title>
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
<?php if ($requestId !== null): ?>
<div class="alert alert-secondary small">Reference požadavku: <code><?= htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') ?></code>. Pokud zpráva nepřijde, sdělte tuto referenci správci klubu.</div>
<?php endif; ?>
<?php if ($submitted): ?>
<div class="alert alert-info">Pokud lze žádost zpracovat, pošleme odkaz na příslušný e-mail. Zkontrolujte také spam.</div>
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
Zadejte e-mail rodičovského účtu, e-mail sportovce nebo přihlašovací jméno sportovce. U sdíleného e-mailu použijte přihlašovací jméno konkrétního sportovce; sdělí vám je klub.
<?php endif; ?>
</p>
<form method="post">
<?= csrf_field() ?>
<div class="mb-3">
<label class="form-label" for="password-reset-identifier">
<?= $trainerReset ? 'E-mail trenérského účtu' : 'E-mail nebo přihlašovací jméno' ?>
</label>
<input class="form-control" id="password-reset-identifier" name="identifier"
 type="<?= $trainerReset ? 'email' : 'text' ?>" maxlength="<?= $trainerReset ? 254 : 160 ?>" autocomplete="username" required autofocus>
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
