<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/auth_rate_limit.php';
require_once __DIR__ . '/../includes/unified_account.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: kalendar.php'); exit;
}

$errors = isset($_GET['session']) && $_GET['session'] === 'expired'
    ? ['Přihlášení již není platné. Přihlaste se znovu.']
    : [];

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
            $identity = null;

            if ($rateAllowed) {
                $identity = unifiedAccountAuthenticate($pdo, $email, $heslo);
            }

            if (!$rateAllowed || $identity === null) {
                $errors[] = $rateAllowed
                    ? 'Nesprávný email nebo heslo.'
                    : 'Příliš mnoho pokusů o přihlášení. Zkuste to prosím znovu za několik minut.';
            } elseif (!$identity['public']['email_overeno']) {
                auth_rate_limit_record_success($pdo, $rateScope, $email, $clientIp);
                $errors[] = 'Email není ověřen. Zkontrolujte svou schránku.';
            } else {
                auth_rate_limit_record_success($pdo, $rateScope, $email, $clientIp);
                app_session_mark_authenticated();
                auth_session_bind_public_user(
                    (int)$identity['public']['id'],
                    (int)$identity['public']['session_version']
                );
                $_SESSION['verejny_uzivatel_jmeno'] = trim(
                    (string)$identity['public']['jmeno'] . ' ' . (string)$identity['public']['prijmeni']
                );
                if ($identity['trainer'] !== null) {
                    auth_session_bind_trainer(
                        (int)$identity['trainer']['id'],
                        (int)$identity['trainer']['session_version']
                    );
                    $_SESSION['trener_jmeno'] = (string)$identity['trainer']['jmeno'];
                    $_SESSION['login_time'] = time();
                    auth_session_refresh_trainer_authorization($pdo, (int)$identity['trainer']['id']);
                }
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
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>

<div class="container mt-5" style="max-width:420px">
    <div class="card shadow">
        <div class="card-body p-4">
            <h1 class="h4 mb-2 text-center"><i class="bi bi-box-arrow-in-right me-2"></i>Přihlášení</h1>
            <p class="text-center text-muted small mb-4">Jeden účet platí pro e-shop, rezervace i trenérskou Evidenci.</p>

            <?php foreach ($errors as $e): ?>
                <div class="alert alert-danger"><?= $e ?></div>
            <?php endforeach; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="login-email">Email</label>
                    <input type="email" name="email" id="login-email" class="form-control"
                           value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="login-password">Heslo</label>
                    <input type="password" name="heslo" id="login-password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Přihlásit se</button>
            </form>
            <p class="text-center text-muted small mt-3">
                Nemáte účet? <a href="registrace.php">Zaregistrujte se</a>
            </p>
            <p class="text-center small"><a href="zapomenute_heslo.php">Zapomenuté heslo</a></p>
            <p class="text-center small mb-0">
                Jste sportovec? <a href="sportovec_prihlaseni.php">Přihlaste se omezeným sportovním účtem</a>
            </p>
        </div>
    </div>
</div>

<?php publicShellFooter(); ?>
</body>
</html>
