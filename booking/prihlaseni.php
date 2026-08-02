<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/auth_rate_limit.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: kalendar.php'); exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný token.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $heslo = $_POST['heslo'] ?? '';

        try {
            $rateScope = 'public_login';
            $clientIp = auth_rate_limit_request_ip();
            $rateAllowed = auth_rate_limit_reserve_attempt($pdo, $rateScope, $email, $clientIp);
            $uzivatel = false;

            if ($rateAllowed) {
                $st = $pdo->prepare("SELECT * FROM verejni_uzivatele WHERE email=? AND aktivni=1");
                $st->execute([$email]);
                $uzivatel = $st->fetch(PDO::FETCH_ASSOC);
            }

            if (!$rateAllowed || !$uzivatel || !password_verify($heslo, $uzivatel['heslo_hash'])) {
                $errors[] = 'Nesprávný email nebo heslo.';
            } elseif (!$uzivatel['email_overeno']) {
                auth_rate_limit_record_success($pdo, $rateScope, $email, $clientIp);
                $errors[] = 'Email není ověřen. Zkontrolujte svou schránku.';
            } else {
                auth_rate_limit_record_success($pdo, $rateScope, $email, $clientIp);
                app_session_mark_authenticated();
                auth_session_bind_public_user(
                    (int)$uzivatel['id'],
                    (int)$uzivatel['session_version']
                );
                $_SESSION['verejny_uzivatel_jmeno'] = $uzivatel['jmeno'] . ' ' . $uzivatel['prijmeni'];
                // Jen interní relativní cíl (žádné //host, http://, zpětná lomítka) — prevence open redirect
                $redirect = $_GET['redirect'] ?? 'kalendar.php';
                if (!preg_match('~^[a-z0-9_]+\.php(\?[^\r\n]*)?$~i', $redirect)) {
                    $redirect = 'kalendar.php';
                }
                header('Location: ' . $redirect);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Public login authentication error: ' . $exception->getMessage());
            $errors[] = 'Přihlášení momentálně není dostupné. Zkuste to znovu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přihlášení — Rezervace Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="kalendar.php">
            <i class="bi bi-bicycle me-2 text-primary"></i>Rezervace Kovopraha
        </a>
        <a href="registrace.php" class="btn btn-outline-primary btn-sm">Registrace</a>
    </div>
</nav>

<div class="container mt-5" style="max-width:420px">
    <div class="card shadow">
        <div class="card-body p-4">
            <h4 class="mb-4 text-center"><i class="bi bi-box-arrow-in-right me-2"></i>Přihlášení</h4>

            <?php foreach ($errors as $e): ?>
                <div class="alert alert-danger"><?= $e ?></div>
            <?php endforeach; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Heslo</label>
                    <input type="password" name="heslo" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Přihlásit se</button>
            </form>
            <p class="text-center text-muted small mt-3">
                Nemáte účet? <a href="registrace.php">Zaregistrujte se</a>
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
