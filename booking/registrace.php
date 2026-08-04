<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/one_time_token.php';
require_once __DIR__ . '/../includes/public_profile.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: kalendar.php'); exit;
}

$errors  = [];
$success = false;
$existingAccount = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $jmeno    = trim($_POST['jmeno']    ?? '');
        $prijmeni = trim($_POST['prijmeni'] ?? '');
        $email    = strtolower(trim($_POST['email']  ?? ''));
        $telefon  = trim($_POST['telefon']  ?? '');
        $narozeni = trim($_POST['narozeni'] ?? '');
        $heslo    = $_POST['heslo']         ?? '';
        $heslo2   = $_POST['heslo2']        ?? '';

        if (!$jmeno)    $errors[] = 'Zadejte jméno.';
        if (!$prijmeni) $errors[] = 'Zadejte příjmení.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Neplatná emailová adresa.';
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $narozeni);
        if (!$birth || $birth->format('Y-m-d') !== $narozeni
            || $birth > new DateTimeImmutable('today') || $birth < new DateTimeImmutable('1900-01-01')) {
            $errors[] = 'Zadejte platné datum narození.';
        }
        if (strlen($heslo) < 8) $errors[] = 'Heslo musí mít alespoň 8 znaků.';
        if ($heslo !== $heslo2) $errors[] = 'Hesla se neshodují.';

        if (empty($errors)) {
            // Kontrola duplicity
            $st = $pdo->prepare("SELECT id FROM verejni_uzivatele WHERE email=?");
            $st->execute([$email]);
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
                publicProfileSave(
                    $pdo,
                    (int)$pdo->lastInsertId(),
                    $jmeno,
                    $prijmeni,
                    $narozeni,
                    $telefon
                );
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
                $link = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                    . '/evidence/booking/overeni.php#token=' . rawurlencode($verification['token']);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="kalendar.php">
            <i class="bi bi-bicycle me-2 text-primary"></i>Rezervace Kovopraha
        </a>
        <a href="prihlaseni.php" class="btn btn-outline-primary btn-sm">Přihlásit se</a>
    </div>
</nav>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<div class="container mt-5" style="max-width:500px">
    <div class="card shadow">
        <div class="card-body p-4">
            <h4 class="mb-4 text-center"><i class="bi bi-person-plus me-2"></i>Registrace</h4>

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
                    <div class="row g-3 mb-3">
                        <div class="col">
                            <label class="form-label">Jméno</label>
                            <input type="text" name="jmeno" class="form-control"
                                   value="<?= h($_POST['jmeno'] ?? '') ?>" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Příjmení</label>
                            <input type="text" name="prijmeni" class="form-control"
                                   value="<?= h($_POST['prijmeni'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= h($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum narození</label>
                        <input type="date" name="narozeni" class="form-control"
                               value="<?= h($_POST['narozeni'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefon (nepovinný)</label>
                        <input type="tel" name="telefon" class="form-control"
                               value="<?= h($_POST['telefon'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Heslo <small class="text-muted">(min. 8 znaků)</small></label>
                        <input type="password" name="heslo" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Heslo znovu</label>
                        <input type="password" name="heslo2" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Zaregistrovat se</button>
                </form>
                <p class="text-center text-muted small mt-3">
                    Již máte účet? <a href="prihlaseni.php">Přihlaste se</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
