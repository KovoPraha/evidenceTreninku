<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * login.php
 * Přihlášení trenéra.
 * Podporuje postupnou migraci hesel z plaintextu na PASSWORD_DEFAULT:
 *  - Moderní hash ověří pomocí password_verify().
 *  - Legacy plaintext dočasně porovná přesně a až po shodě přehashuje.
 */
app_session_start();

// Pokud je již přihlášen, přesměruj rovnou
if (isset($_SESSION['trener_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';
require_once __DIR__ . '/includes/password_security.php';
require_once __DIR__ . '/includes/auth_rate_limit.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['jmeno'] ?? '');
    $heslo = $_POST['heslo'] ?? '';

    if ($login === '' || $heslo === '') {
        $error = 'Vyplňte přihlašovací údaje i heslo.';
    } else {
        try {
            $rateScope = 'trainer_login';
            $clientIp = auth_rate_limit_request_ip();
            $rateAllowed = auth_rate_limit_reserve_attempt($pdo, $rateScope, $login, $clientIp);
            $uzivatel = false;

            if ($rateAllowed) {
                // Načteme uživatele podle jména NEBO emailu
                $stmt = $pdo->prepare(
                    "SELECT id, jmeno, heslo, role, session_version FROM treneri "
                    . "WHERE aktivni = 1 AND (jmeno = ? OR email = ?) LIMIT 1"
                );
                $stmt->execute([$login, $login]);
                $uzivatel = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $authenticated = false;

            if ($uzivatel) {
                $storedHash = (string)($uzivatel['heslo'] ?? '');

                $authenticated = trainer_password_verify($heslo, $storedHash);
                if ($authenticated && trainer_password_needs_rehash($storedHash)) {
                    $newHash = trainer_password_hash($heslo);
                    $pdo->prepare("UPDATE treneri SET heslo = ? WHERE id = ? AND heslo = ? LIMIT 1")
                        ->execute([$newHash, $uzivatel['id'], $storedHash]);
                }
            }

            if ($authenticated && $uzivatel) {
                auth_rate_limit_record_success($pdo, $rateScope, $login, $clientIp);
                // Nová autentizace rotuje session ID, CSRF token a nastaví časové limity.
                app_session_mark_authenticated();

                auth_session_bind_trainer(
                    (int)$uzivatel['id'],
                    (int)$uzivatel['session_version']
                );
                $_SESSION['trener_jmeno'] = $uzivatel['jmeno'];
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
                // Úmyslně neurčitá zpráva – neprozrazuje, zda přihlašovací údaj existuje
                $error = 'Neplatné přihlašovací jméno / email nebo heslo.';
            }
        } catch (Throwable $e) {
            // Nezobrazujeme detaily autentizační ani DB chyby uživateli.
            error_log('Login authentication error: ' . $e->getMessage());
            $error = 'Přihlášení momentálně není dostupné. Zkuste to znovu.';
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
