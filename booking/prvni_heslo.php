<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/quick_account_password.php';

$accountId = (int)($_SESSION['verejny_uzivatel_id'] ?? 0);
$sessionVersion = (int)($_SESSION['verejny_uzivatel_session_version'] ?? 0);
$grant = is_array($_SESSION['quick_account_password_setup'] ?? null) ? $_SESSION['quick_account_password_setup'] : [];
$returnTo = quickAccountPasswordSetupReturn($grant['return'] ?? 'moje_osoby.php');
$available = quickAccountPasswordSetupValid($pdo, $grant, $accountId, $sessionVersion);
$errors = [];
$success = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } elseif (!$available) {
        $errors[] = 'Možnost nastavení hesla vypršela. Použijte obnovu hesla.';
    } elseif ((string)($_POST['heslo'] ?? '') !== (string)($_POST['heslo2'] ?? '')) {
        $errors[] = 'Hesla se neshodují.';
    } else {
        try {
            $newVersion = quickAccountPasswordSetupConsume($pdo, $grant, $accountId, $sessionVersion, (string)($_POST['heslo'] ?? ''));
            if ($newVersion === null) {
                $available = false;
                $errors[] = 'Možnost nastavení hesla vypršela. Použijte obnovu hesla.';
            } else {
                unset($_SESSION['quick_account_password_setup']);
                app_session_mark_authenticated();
                auth_session_bind_public_user($accountId, $newVersion);
                $success = true;
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Quick account password setup failed: ' . get_class($exception));
            $errors[] = 'Heslo nyní nelze uložit. Zkuste to znovu.';
        }
    }
}
function firstPasswordH(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Heslo k rodičovskému účtu — Kovopraha</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav(); ?><main class="container py-5" style="max-width:520px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h4">Heslo k rodičovskému účtu</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= firstPasswordH($error) ?></div><?php endforeach; ?>
<?php if ($success): ?>
<div class="alert alert-success">Heslo je uložené. Příště se přihlásíte svým e-mailem a tímto heslem. Starší přihlášení byla odhlášena.</div>
<a class="btn btn-primary" href="<?= firstPasswordH($returnTo) ?>">Pokračovat k přihlášce</a>
<?php elseif ($available): ?>
<p>E-mail máte ověřený. Při rychlé přihlášce jste si heslo ještě nevolili. Nastavte si ho pro další přihlášení a správu dítěte.</p>
<form method="post"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="first-password">Heslo (12–200 znaků)</label><input id="first-password" class="form-control" type="password" name="heslo" minlength="12" maxlength="200" autocomplete="new-password" required><div class="form-text">Použijte snadno zapamatovatelnou větu nebo několik slov; mezery jsou povolené.</div></div>
<div class="mb-3"><label class="form-label" for="first-password-confirmation">Heslo znovu</label><input id="first-password-confirmation" class="form-control" type="password" name="heslo2" minlength="12" maxlength="200" autocomplete="new-password" required></div>
<button class="btn btn-primary" type="submit">Uložit heslo</button>
</form>
<?php else: ?>
<p>Nastavení hesla po ověření e-mailu už není dostupné. Odkaz pro nové heslo si můžete poslat na ověřený e-mail.</p>
<a class="btn btn-primary" href="zapomenute_heslo.php">Obnovit heslo</a>
<a class="btn btn-outline-secondary" href="prihlaseni.php">Přihlásit se</a>
<?php endif; ?>
</div></div></main><?php publicShellFooter(); ?></body></html>
