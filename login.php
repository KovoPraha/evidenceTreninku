<?php
/**
 * login.php
 * Přihlášení trenéra.
 * Podporuje postupnou migraci hesel z plaintext → bcrypt:
 *  - Pokud heslo v DB začíná "$2y$" → ověř pomocí password_verify()
 *  - Jinak porovnej plaintext; při shodě okamžitě přehashuj a ulož
 */
session_start();

// Pokud je již přihlášen, přesměruj rovnou
if (isset($_SESSION['trener_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['jmeno'] ?? '');
    $heslo = $_POST['heslo'] ?? '';

    if ($login === '' || $heslo === '') {
        $error = 'Vyplňte přihlašovací údaje i heslo.';
    } else {
        try {
            // Načteme uživatele podle jména NEBO emailu
            $stmt = $pdo->prepare("SELECT id, jmeno, heslo, role FROM treneri WHERE jmeno = ? OR email = ? LIMIT 1");
            $stmt->execute([$login, $login]);
            $uzivatel = $stmt->fetch(PDO::FETCH_ASSOC);

            $authenticated = false;

            if ($uzivatel) {
                $storedHash = (string)($uzivatel['heslo'] ?? '');

                if (str_starts_with($storedHash, '$2y$')) {
                    // Heslo je již zahashováno (bcrypt)
                    $authenticated = password_verify($heslo, $storedHash);
                } else {
                    // Plaintext heslo – staré heslo; porovnej a pokud sedí, přehashuj
                    if (hash_equals($storedHash, $heslo)) {
                        $authenticated = true;
                        // Automatická migrace na bcrypt
                        $newHash = password_hash($heslo, PASSWORD_BCRYPT);
                        $pdo->prepare("UPDATE treneri SET heslo = ? WHERE id = ? LIMIT 1")
                            ->execute([$newHash, $uzivatel['id']]);
                    }
                }
            }

            if ($authenticated && $uzivatel) {
                // Regenerace session ID – ochrana proti session fixation
                session_regenerate_id(true);

                $_SESSION['trener_id']   = $uzivatel['id'];
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
        } catch (PDOException $e) {
            // Nezobrazujeme detaily DB chyby uživateli
            error_log('Login PDO error: ' . $e->getMessage());
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
