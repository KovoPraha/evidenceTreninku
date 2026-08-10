<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/auth_rate_limit.php';
require_once dirname(__DIR__) . '/includes/password_reset.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

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
                passwordResetRequest(
                    $pdo,
                    $identifier,
                    static function (string $email, string $token) use (&$localResetLink): bool {
                        $link = appUrl('booking/nove_heslo.php') . '#token=' . rawurlencode($token);
                        if (app_session_request_is_local()) {
                            $localResetLink = 'nove_heslo.php#token=' . rawurlencode($token);
                            return true;
                        }
                        return @mail(
                            $email,
                            'Obnova hesla — Kovopraha',
                            "Pro nastavení nového hesla otevřete odkaz:\n{$link}\n\nOdkaz je platný 60 minut.",
                            "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8"
                        );
                    }
                );
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
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Obnova hesla — Kovopraha</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><?php appUiAssets(); ?></head>
<body class="bg-light"><main class="container py-5" style="max-width:480px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h4">Obnova hesla</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endforeach; ?>
<?php if ($submitted): ?><div class="alert alert-success">Pokud účet existuje a lze jej bezpečně ověřit, poslali jsme další postup na příslušný e-mail.</div>
<?php if ($localResetLink !== null): ?><div class="alert alert-warning"><strong>Jen localhost:</strong> e-mail se zde neposílá. <a class="alert-link" href="<?= htmlspecialchars($localResetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Otevřít testovací odkaz pro obnovu</a>.</div><?php endif; ?>
<?php else: ?><p class="text-muted">Zadejte e-mail rodičovského účtu nebo přihlašovací jméno sportovce.</p><form method="post"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="password-reset-identifier">E-mail nebo přihlašovací jméno</label><input class="form-control" id="password-reset-identifier" name="identifier" autocomplete="username" required autofocus></div>
<button class="btn btn-primary w-100">Poslat odkaz pro obnovu</button></form><?php endif; ?>
<p class="mt-3 mb-0 text-center"><a href="prihlaseni.php">Zpět na přihlášení</a></p>
</div></div></main></body></html>
