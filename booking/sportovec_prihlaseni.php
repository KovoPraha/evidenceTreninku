<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/auth_rate_limit.php';
require_once dirname(__DIR__) . '/includes/child_access.php';

function childLoginH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (isset($_SESSION['sportovec_pristup_id'])) {
    header('Location: muj_sport.php');
    exit;
}

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Neplatný bezpečnostní token.';
    } else {
        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['heslo'] ?? '');
        try {
            $scope = 'athlete_login';
            $ipAddress = auth_rate_limit_request_ip();
            $allowed = auth_rate_limit_reserve_attempt($pdo, $scope, $login, $ipAddress);
            $account = $allowed ? childAccessAuthenticate($pdo, $login, $password) : null;
            if ($account === null) {
                $errors[] = 'Nesprávné přihlašovací jméno nebo heslo.';
            } else {
                auth_rate_limit_record_success($pdo, $scope, $login, $ipAddress);
                childAccessRecordLogin($pdo, (int)$account['id']);

                // Athlete mode is deliberately exclusive. It must never inherit
                // parent, sibling or trainer privileges from an existing browser session.
                auth_session_clear_identity('trainer');
                auth_session_clear_identity('public');
                auth_session_clear_identity('child');
                app_session_mark_identity_changed();
                app_session_mark_authenticated();
                auth_session_bind_child((int)$account['id'], (int)$account['session_version']);
                $_SESSION['sportovec_pristup_jmeno'] = $account['jmeno'] . ' ' . $account['prijmeni'];
                header('Location: muj_sport.php', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Athlete login failed: ' . $exception->getMessage());
            $errors[] = 'Přihlášení nyní není dostupné. Zkuste to znovu.';
        }
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přihlášení sportovce — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-5" style="max-width:440px">
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <h1 class="h4 text-center mb-2">Přihlášení sportovce</h1>
        <p class="text-muted text-center small">Jen vlastní tréninky, soupisky, události a platby. Z tohoto režimu nelze spravovat rodinu ani objednávky.</p>
        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= childLoginH($error) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Přihlašovací jméno</label><input class="form-control" name="login" value="<?= childLoginH($_POST['login'] ?? '') ?>" autocomplete="username" required autofocus></div>
            <div class="mb-3"><label class="form-label">Heslo</label><input class="form-control" type="password" name="heslo" autocomplete="current-password" required></div>
            <button class="btn btn-primary w-100">Přihlásit se</button>
        </form>
        <p class="text-center small mt-3 mb-0"><a href="zapomenute_heslo.php">Zapomenuté heslo</a></p>
    </div></div>
</main>
<?php publicShellFooter(); ?>
</body>
</html>
