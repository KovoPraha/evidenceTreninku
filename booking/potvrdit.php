<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
// Endpoint pro potvrzení/zamítnutí žluté rezervace z emailu trenéra
// GET ?token=xxx&akce=potvrdit|zamit
app_session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/waiting_list.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$token = trim($_GET['token'] ?? '');
$akce  = in_array($_GET['akce'] ?? '', ['potvrdit', 'zamit'], true) ? $_GET['akce'] : '';

$ok  = false;
$msg = '';

if ($token && $akce) {
    $st = $pdo->prepare("
        SELECT vr.*, il.nazev, il.datum, il.cas_od, il.cas_do, il.trener_id,
               vu.email, vu.jmeno
        FROM verejne_rezervace vr
        JOIN individualni_lekce il ON il.id = vr.lekce_id
        JOIN verejni_uzivatele vu   ON vu.id = vr.uzivatel_id
        WHERE vr.potvrzovaci_token = ? AND vr.stav = 'ceka'
    ");
    // (slot_cas_od / slot_cas_do jsou součástí vr.* výběru)
    $st->execute([$token]);
    $rez = $st->fetch(PDO::FETCH_ASSOC);

    if ($rez) {
        // Sestavení popisu termínu (slot nebo okno)
        $terminInfo = !empty($rez['slot_cas_od']) && !empty($rez['slot_cas_do'])
            ? "Datum: {$rez['datum']}, Slot: " . substr($rez['slot_cas_od'],0,5) . "–" . substr($rez['slot_cas_do'],0,5)
            : "Datum: {$rez['datum']}, " . substr($rez['cas_od'],0,5) . "–" . substr($rez['cas_do'],0,5);

        if ($akce === 'potvrdit') {
            $pdo->prepare("UPDATE verejne_rezervace SET stav='potvrzena', cas_potvrzeni=NOW() WHERE id=?")
                ->execute([$rez['id']]);
            $ok  = true;
            $msg = 'Rezervace potvrzena.';

            $subject = 'Rezervace potvrzena — ' . $rez['nazev'];
            $body = "Dobrý den {$rez['jmeno']},\n\nVaše rezervace byla potvrzena trenérem:\n"
                . "Lekce: {$rez['nazev']}\n"
                . "{$terminInfo}\n\n"
                . "Těšíme se na Vás!";
        } else {
            $pdo->prepare("UPDATE verejne_rezervace SET stav='zamitnuta' WHERE id=?")
                ->execute([$rez['id']]);

            // Uvolnil se slot — upozornit prvního na čekací listině
            notifyWaitingList($pdo, (int)$rez['lekce_id'], (string)($rez['slot_cas_od'] ?? ''));

            $ok  = true;
            $msg = 'Rezervace zamítnuta.';

            $subject = 'Rezervace zamítnuta — ' . $rez['nazev'];
            $body = "Dobrý den {$rez['jmeno']},\n\nBohužel Vaše žádost o rezervaci byla zamítnuta.\n"
                . "{$terminInfo}\n"
                . "V případě dotazů nás kontaktujte.";
        }

        @mail($rez['email'], $subject, $body,
            "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");
    } else {
        $msg = 'Token není platný nebo rezervace již byla zpracována.';
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Potvrzení rezervace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:480px">
    <div class="card shadow text-center p-4">
        <?php if ($ok): ?>
            <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
            <h5><?= h($msg) ?></h5>
            <p class="text-muted">Zákazník byl informován emailem.</p>
            <a href="../individualni_lekce_sprava.php" class="btn btn-primary mt-2">
                Přejít na správu lekcí
            </a>
        <?php else: ?>
            <i class="bi bi-exclamation-triangle-fill text-warning fs-1 mb-3"></i>
            <h5><?= h($msg ?: 'Neplatný požadavek') ?></h5>
            <a href="../individualni_lekce_sprava.php" class="btn btn-outline-secondary mt-2">
                Správa lekcí
            </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
