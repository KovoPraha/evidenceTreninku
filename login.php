<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * login.php
 * Přihlášení trenéra.
 * Přijímá pouze hesla uložená přes password_hash(). Legacy plaintext převádí
 * číslovaná bezpečnostní migrace před aktivací tohoto kódu.
 */
app_session_start();
app_session_send_auth_no_store_headers();

// Pokud je již přihlášen, přesměruj rovnou
if (isset($_SESSION['trener_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/password_security.php';
require_once __DIR__ . '/includes/auth_rate_limit.php';
require_once __DIR__ . '/includes/unified_account.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $error = 'Přihlašovací formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
    $login = trim($_POST['jmeno'] ?? '');
    $heslo = $_POST['heslo'] ?? '';

    if ($login === '' || $heslo === '') {
        $error = 'Vyplňte přihlašovací údaje i heslo.';
    } else {
        try {
            $rateScope = 'trainer_login';
            $clientIp = auth_rate_limit_request_ip();
            $rateAllowed = auth_rate_limit_reserve_attempt($pdo, $rateScope, $login, $clientIp);
            $uzivatele = [];

            if ($rateAllowed) {
                // Jméno ani e-mail nejsou v historických datech vždy unikátní.
                // Heslo proto ověříme proti všem přesným kandidátům a přijmeme
                // pouze právě jednu shodu; pořadí řádků nesmí rozhodovat o identitě.
                $stmt = $pdo->prepare(
                    "SELECT id, jmeno, email, heslo, role, session_version FROM treneri "
                    . "WHERE aktivni = 1 AND (jmeno = ? OR email = ?)"
                );
                $stmt->execute([$login, $login]);
                $uzivatele = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $uzivatel = trainer_password_unique_match($uzivatele, $heslo);
            $authenticated = $uzivatel !== null;
            if ($authenticated) {
                $storedHash = (string)($uzivatel['heslo'] ?? '');
                if (trainer_password_needs_rehash($storedHash)) {
                    $newHash = trainer_password_hash($heslo);
                    $pdo->prepare("UPDATE treneri SET heslo = ? WHERE id = ? AND heslo = ? LIMIT 1")
                        ->execute([$newHash, $uzivatel['id'], $storedHash]);
                    $uzivatel['heslo'] = $newHash;
                }
            }

            if ($authenticated) {
                auth_rate_limit_record_success($pdo, $rateScope, $login, $clientIp);
                $customerAccount = unifiedAccountEnsureTrainerCustomer($pdo, $uzivatel);
                // Nová autentizace rotuje session ID, CSRF token a nastaví časové limity.
                app_session_mark_authenticated();

                auth_session_bind_trainer(
                    (int)$uzivatel['id'],
                    (int)$uzivatel['session_version']
                );
                auth_session_bind_public_user(
                    (int)$customerAccount['id'],
                    (int)$customerAccount['session_version']
                );
                $_SESSION['trener_jmeno'] = $uzivatel['jmeno'];
                $_SESSION['verejny_uzivatel_jmeno'] = trim(
                    (string)$customerAccount['jmeno'] . ' ' . (string)$customerAccount['prijmeni']
                );
                $_SESSION['role']        = $uzivatel['role'];
                $_SESSION['login_time']  = time();

                // Načíst konfigurovatelná oprávnění do session
                try {
                    $permsStmt = $pdo->query("SELECT klic, min_role FROM opravneni");
                    $_SESSION['opravneni'] = $permsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                } catch (Exception $e) {
                    $_SESSION['opravneni'] = [];
                }

                header('Location: index.php');
                exit;
            } else {
                $error = $rateAllowed
                    // Úmyslně neurčitá zpráva – neprozrazuje, zda přihlašovací údaj existuje.
                    ? 'Neplatné přihlašovací jméno / email nebo heslo.'
                    : 'Příliš mnoho pokusů o přihlášení. Zkuste to prosím znovu za několik minut.';
            }
        } catch (Throwable $e) {
            // Nezobrazujeme detaily autentizační ani DB chyby uživateli.
            error_log('Login authentication error: ' . $e->getMessage());
            $error = 'Přihlášení momentálně není dostupné. Zkuste to znovu.';
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
    <title>Přihlášení – Evidence tréninků</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<?php appUiAssets(); ?>
    <style>
        body { background: linear-gradient(135deg, #1a56db 0%, #0e9f6e 100%); min-height: 100vh; }
        .login-card { max-width: 420px; border-radius: 1rem; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
<div class="container">
    <div class="login-card card shadow mx-auto p-4">
        <div class="text-center mb-4">
            <span style="font-size:2.5rem;">🚴</span>
            <h1 class="h4 mt-2 mb-0">Evidence tréninků</h1>
            <div class="text-muted small">Přihlášení trenéra</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="jmeno" class="form-label">Jméno nebo e-mail</label>
                <input type="text" name="jmeno" id="jmeno" class="form-control"
                       value="<?= h($_POST['jmeno'] ?? '') ?>"
                       autocomplete="username" required autofocus
                       placeholder="přihlašovací jméno nebo e-mail">
            </div>
            <div class="mb-4">
                <label for="heslo" class="form-label">Heslo</label>
                <input type="password" name="heslo" id="heslo" class="form-control"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Přihlásit se</button>
        </form>
    </div>
</div>
</body>
</html>
