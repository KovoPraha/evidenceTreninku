<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/password_reset.php';

$errors = [];
$success = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Neplatný bezpečnostní token.';
    } else {
        $password = (string)($_POST['heslo'] ?? '');
        $confirmation = (string)($_POST['heslo2'] ?? '');
        if ($password !== $confirmation) {
            $errors[] = 'Hesla se neshodují.';
        } else {
            try {
                $result = passwordResetConsume($pdo, (string)($_POST['token'] ?? ''), $password);
                if ($result === null) {
                    $errors[] = 'Odkaz není platný nebo už byl použit.';
                } else {
                    $success = true;
                    auth_session_clear_identity('public');
                    auth_session_clear_identity('child');
                    app_session_mark_identity_changed();
                }
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Password reset consume failed: ' . $exception->getMessage());
                $errors[] = 'Heslo nyní nelze změnit. Zkuste to znovu.';
            }
        }
    }
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nové heslo — Kovopraha</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav(); ?><main class="container py-5" style="max-width:480px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h4">Nastavení nového hesla</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success">Heslo bylo změněno. Všechna starší přihlášení byla odhlášena.</div><a class="btn btn-primary w-100" href="prihlaseni.php">Přejít na přihlášení</a>
<?php else: ?><form method="post" id="password-reset-form"><?= csrf_field() ?><input type="hidden" name="token" id="password-reset-token">
<div class="mb-3"><label class="form-label">Nové heslo</label><input class="form-control" type="password" name="heslo" minlength="12" maxlength="200" autocomplete="new-password" required></div>
<div class="mb-3"><label class="form-label">Nové heslo znovu</label><input class="form-control" type="password" name="heslo2" minlength="12" maxlength="200" autocomplete="new-password" required></div>
<button class="btn btn-primary w-100">Změnit heslo</button></form><?php endif; ?>
</div></div></main>
<?php if (!$success): ?><script>
const fragment = new URLSearchParams(window.location.hash.slice(1));
document.getElementById('password-reset-token').value = fragment.get('token') || '';
if (window.location.hash) history.replaceState(null, '', window.location.pathname);
</script><?php endif; ?><?php publicShellFooter(); ?></body></html>
