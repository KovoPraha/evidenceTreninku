<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/one_time_token.php';
require_once __DIR__ . '/../includes/public_profile.php';
require_once __DIR__ . '/../includes/app_url.php';
require_once __DIR__ . '/../includes/password_security.php';
require_once __DIR__ . '/../includes/auth_rate_limit.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function registrationSafeRedirect(mixed $value): string
{
    $redirect = trim((string)$value);
    return preg_match('~^[a-z0-9_]+\.php(\?[a-z0-9_=&%.-]*)?$~i', $redirect) === 1
        ? $redirect
        : 'kalendar.php';
}

if (isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: ' . registrationSafeRedirect($_GET['redirect'] ?? 'kalendar.php')); exit;
}

$errors  = [];
$success = false;
$existingAccount = false;
$purpose = (string)($_POST['ucel'] ?? $_GET['purpose'] ?? 'nakup');
$redirect = registrationSafeRedirect($_POST['redirect'] ?? $_GET['redirect'] ?? 'kalendar.php');
if (!in_array($purpose, ['nakup', 'sport'], true)) {
    $purpose = 'nakup';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $jmeno    = trim($_POST['jmeno']    ?? '');
        $prijmeni = trim($_POST['prijmeni'] ?? '');
        $email    = strtolower(trim($_POST['email']  ?? ''));
        $telefon  = trim($_POST['telefon']  ?? '');
        $purpose  = (string)($_POST['ucel'] ?? 'nakup');
        $narozeni = trim($_POST['narozeni'] ?? '');
        $heslo    = $_POST['heslo']         ?? '';
        $heslo2   = $_POST['heslo2']        ?? '';

        try {
            if (!auth_rate_limit_reserve_attempt(
                $pdo,
                'public_registration',
                $email,
                auth_rate_limit_request_ip()
            )) {
                $errors[] = 'Příliš mnoho pokusů. Zkuste registraci později.';
            }
        } catch (Throwable $exception) {
            error_log('booking/registrace.php rate limit: ' . $exception->getMessage());
            $errors[] = 'Registrace momentálně není dostupná. Zkuste to později.';
        }

        if (!$jmeno)    $errors[] = 'Zadejte jméno.';
        if (!$prijmeni) $errors[] = 'Zadejte příjmení.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Neplatná emailová adresa.';
        if (!in_array($purpose, ['nakup', 'sport'], true)) {
            $errors[] = 'Vyberte, k čemu budete účet používat.';
        }
        if ($purpose === 'sport') {
            $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $narozeni);
            if (!$birth || $birth->format('Y-m-d') !== $narozeni
                || $birth > new DateTimeImmutable('today') || $birth < new DateTimeImmutable('1900-01-01')) {
                $errors[] = 'Pro sportovní účet zadejte platné datum narození.';
            }
        }
        try {
            passwordPolicyValidate($heslo);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }
        if ($heslo !== $heslo2) $errors[] = 'Hesla se neshodují.';

        if (empty($errors)) {
            // Kontrola duplicity
            $st = $pdo->prepare(
                "SELECT id FROM verejni_uzivatele WHERE LOWER(email)=? "
                . "UNION SELECT id FROM treneri WHERE LOWER(email)=? LIMIT 1"
            );
            $st->execute([$email, $email]);
            $existingAccount = $st->fetchColumn() !== false;
        }

        if (empty($errors) && $existingAccount) {
            // Stejná odpověď jako u nové registrace brání zjišťování existence účtu.
            $success = true;
        }

        if (empty($errors) && !$existingAccount) {
            $verification = one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION, 86400);
            try {
                $pdo->beginTransaction();
                $pdo->prepare("
                    INSERT INTO verejni_uzivatele
                        (jmeno, prijmeni, email, heslo_hash, telefon, verifikacni_token,
                         verifikacni_token_expires_at)
                    VALUES (?,?,?,?,?,?,?)
                ")->execute([
                    $jmeno,
                    $prijmeni,
                    $email,
                    password_hash($heslo, PASSWORD_DEFAULT),
                    $telefon ?: null,
                    $verification['hash'],
                    $verification['expires_at'],
                ]);
                $accountId = (int)$pdo->lastInsertId();
                if ($purpose === 'sport') {
                    publicProfileSave(
                        $pdo,
                        $accountId,
                        $jmeno,
                        $prijmeni,
                        $narozeni,
                        $telefon
                    );
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('booking/registrace.php public profile: ' . $exception->getMessage());
                $errors[] = 'Účet se nepodařilo vytvořit bez částečného zápisu.';
            }

            // Verifikační email
            if (empty($errors)) {
                $link = appUrl('booking/overeni.php') . '#token=' . rawurlencode($verification['token'])
                    . '&redirect=' . rawurlencode($redirect);
                @mail($email, 'Ověření registrace — Kovopraha',
                    "Dobrý den {$jmeno},\n\nPro dokončení registrace klikněte na odkaz:\n{$link}\n\nOdkaz je platný 24 hodin.",
                    "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");

                $success = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrace — Rezervace Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>

<div class="container mt-5" style="max-width:500px">
    <div class="card shadow">
        <div class="card-body p-4">
            <h1 class="h4 mb-4 text-center"><i class="bi bi-person-plus me-2"></i>Registrace</h1>

            <?php if ($success): ?>
                <div class="alert alert-success text-center">
                    <i class="bi bi-envelope-check fs-2 d-block mb-2"></i>
                    Pokud lze účet s touto adresou vytvořit, poslali jsme na ni další postup.
                </div>
            <?php else: ?>

                <?php foreach ($errors as $e): ?>
                    <div class="alert alert-danger"><?= $e ?></div>
                <?php endforeach; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?=h($redirect)?>">
                    <fieldset class="mb-3">
                        <legend class="form-label mb-2">K čemu budete účet používat?</legend>
                        <div class="form-check border rounded p-3 ps-5 mb-2">
                            <input class="form-check-input" type="radio" name="ucel" id="registration-purpose-shop" value="nakup" <?= $purpose === 'nakup' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="registration-purpose-shop"><strong>Jen nákup a rezervace</strong><br><span class="text-muted small">Oblečení, veřejné akce a další nabídky. Datum narození není potřeba.</span></label>
                        </div>
                        <div class="form-check border rounded p-3 ps-5">
                            <input class="form-check-input" type="radio" name="ucel" id="registration-purpose-sport" value="sport" <?= $purpose === 'sport' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="registration-purpose-sport"><strong>Jsem také účastník nebo sportovec</strong><br><span class="text-muted small">Vytvoří se mi osobní sportovní profil. Dítě lze přidat samostatně po přihlášení.</span></label>
                        </div>
                    </fieldset>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="registration-first-name">Jméno</label>
                            <input type="text" name="jmeno" id="registration-first-name" class="form-control"
                                   value="<?= h($_POST['jmeno'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="registration-last-name">Příjmení</label>
                            <input type="text" name="prijmeni" id="registration-last-name" class="form-control"
                                   value="<?= h($_POST['prijmeni'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="registration-email">Email</label>
                        <input type="email" name="email" id="registration-email" class="form-control"
                               value="<?= h($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="registration-birth-date">Datum narození <span id="registration-birth-required" class="text-muted small">(jen pro sportovní profil)</span></label>
                        <input type="date" name="narozeni" id="registration-birth-date" class="form-control"
                               value="<?= h($_POST['narozeni'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="registration-phone">Telefon (nepovinný)</label>
                        <input type="tel" name="telefon" id="registration-phone" class="form-control"
                               value="<?= h($_POST['telefon'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="registration-password">Heslo <small class="text-muted">(12–200 znaků)</small></label>
                        <input type="password" name="heslo" id="registration-password" class="form-control" required minlength="12" maxlength="200">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="registration-password-confirmation">Heslo znovu</label>
                        <input type="password" name="heslo2" id="registration-password-confirmation" class="form-control" required minlength="12" maxlength="200">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Zaregistrovat se</button>
                </form>
                <p class="text-center text-muted small mt-3">
                    Již máte účet? <a href="prihlaseni.php?redirect=<?=rawurlencode($redirect)?>">Přihlaste se</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(() => {
    const birth = document.getElementById('registration-birth-date');
    const hint = document.getElementById('registration-birth-required');
    const sync = () => {
        const sport = document.getElementById('registration-purpose-sport').checked;
        birth.required = sport;
        hint.textContent = sport ? '(povinné)' : '(jen pro sportovní profil)';
    };
    document.querySelectorAll('input[name="ucel"]').forEach(input => input.addEventListener('change', sync));
    sync();
})();
</script>
<?php publicShellFooter(); ?>
</body>
</html>
