<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/imported_account_activation.php';

header('Referrer-Policy: no-referrer');
$errors = [];
$success = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } elseif ((string)($_POST['heslo'] ?? '') !== (string)($_POST['heslo2'] ?? '')) {
        $errors[] = 'Hesla se neshodují.';
    } else {
        try {
            $success = importedAccountActivationConsume($pdo, (string)($_POST['token'] ?? ''), (string)($_POST['heslo'] ?? ''));
            if ($success) {
                auth_session_clear_identity('public');
                auth_session_clear_identity('child');
                app_session_mark_identity_changed();
            } else {
                $errors[] = 'Odkaz není platný nebo už byl použit.';
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Imported account activation: ' . get_class($exception));
            $errors[] = 'Aktivaci se nyní nepodařilo dokončit. Zkuste to později.';
        }
    }
}
function activationH(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow">
<title>Aktivace přístupu do KIS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav(); ?><main class="container py-5" style="max-width:520px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h4">Aktivace přístupu do KIS</h1><p>Potvrďte přístup nastavením vlastního hesla. Odkaz platí 60 minut a lze jej použít jednou.</p>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= activationH($error) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success">E-mail a přístup byly ověřeny. Heslo je nastaveno.</div><a class="btn btn-primary" href="prihlaseni.php">Přihlásit se</a>
<?php else: ?><form method="post" id="activation-form"><?= csrf_field() ?><input type="hidden" name="token" id="activation-token">
<label class="form-label" for="activation-password">Nové heslo (12–200 znaků)</label><input class="form-control mb-3" id="activation-password" type="password" name="heslo" minlength="12" maxlength="200" autocomplete="new-password" required>
<label class="form-label" for="activation-confirmation">Heslo znovu</label><input class="form-control mb-3" id="activation-confirmation" type="password" name="heslo2" minlength="12" maxlength="200" autocomplete="new-password" required>
<button class="btn btn-primary w-100">Aktivovat účet a nastavit heslo</button></form><?php endif; ?>
<p class="mt-3 mb-0">Pokud odkaz vypršel, požádejte klub o nové pozvání.</p></div></div></main><?php publicShellFooter(); ?>
<?php if (!$success): ?><script nonce="<?= activationH(app_csp_nonce()) ?>">const p=new URLSearchParams(window.location.hash.slice(1));document.getElementById('activation-token').value=p.get('token')||'';if(window.location.hash)history.replaceState(null,'',window.location.pathname);</script><?php endif; ?></body></html>
