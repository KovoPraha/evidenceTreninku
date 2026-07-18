<?php
session_start();
require_once __DIR__ . '/../db.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$token = trim($_GET['token'] ?? '');
$ok = false;

if ($token !== '') {
    $st = $pdo->prepare("SELECT id FROM verejni_uzivatele WHERE verifikacni_token=? AND email_overeno=0");
    $st->execute([$token]);
    $uid = $st->fetchColumn();
    if ($uid) {
        $pdo->prepare("UPDATE verejni_uzivatele SET email_overeno=1, verifikacni_token=NULL WHERE id=?")
            ->execute([$uid]);
        session_regenerate_id(true);   // prevence session fixation po autentizaci
        $_SESSION['verejny_uzivatel_id'] = $uid;
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ověření emailu — Rezervace Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php if ($ok): ?>
        <meta http-equiv="refresh" content="3;url=kalendar.php">
    <?php endif; ?>
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:500px">
    <div class="card shadow text-center p-4">
        <?php if ($ok): ?>
            <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
            <h5>Email ověřen!</h5>
            <p class="text-muted">Přesměrováváme vás na kalendář…</p>
        <?php else: ?>
            <i class="bi bi-x-circle-fill text-danger fs-1 mb-3"></i>
            <h5>Neplatný nebo expirovaný odkaz</h5>
            <p class="text-muted">Zkuste se <a href="registrace.php">zaregistrovat znovu</a>.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
