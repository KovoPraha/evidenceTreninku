<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php'); exit;
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf_helper.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$uzivatelId = (int)$_SESSION['verejny_uzivatel_id'];
$success = $_SESSION['flash_booking_success'] ?? '';
unset($_SESSION['flash_booking_success']);

// ── Storno ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '')) {
    $rezervId = (int)($_POST['rezervace_id'] ?? 0);
    if ($rezervId) {
        $st = $pdo->prepare("
            SELECT vr.*, il.datum, il.cas_od FROM verejne_rezervace vr
            JOIN individualni_lekce il ON il.id = vr.lekce_id
            WHERE vr.id=? AND vr.uzivatel_id=?
        ");
        $st->execute([$rezervId, $uzivatelId]);
        $rez = $st->fetch(PDO::FETCH_ASSOC);
        if ($rez) {
            $minDatum = (new DateTime())->modify('+3 days')->format('Y-m-d');
            $isCekaci = $rez['stav'] === 'cekaci_listina';
            $canCancel = $isCekaci // z čekací listiny lze vždy odstoupit
                || ($rez['datum'] >= $minDatum && in_array($rez['stav'], ['ceka', 'potvrzena'], true));
            if ($canCancel) {
                $pdo->prepare("UPDATE verejne_rezervace SET stav='zrusena' WHERE id=?")
                    ->execute([$rezervId]);
                // Pokud to byla aktivní rezervace, upozorníme dalšího čekajícího
                if (!$isCekaci) {
                    require_once __DIR__ . '/waiting_list.php';
                    notifyWaitingList($pdo, (int)$rez['lekce_id'], (string)$rez['slot_cas_od']);
                }
                $success = $isCekaci ? 'Odhlásili jste se z čekací listiny.' : 'Rezervace zrušena.';
            }
        }
    }
}

// ── Načtení rezervací ─────────────────────────────────────────────────────────
$rezervace = $pdo->prepare("
    SELECT vr.*, il.nazev, il.datum, il.cas_od, il.cas_do, il.cena_kc, il.typ,
           s.nazev AS sport_nazev, t.jmeno AS trener_jmeno
    FROM verejne_rezervace vr
    JOIN individualni_lekce il ON il.id = vr.lekce_id
    JOIN sportovist s ON s.id = il.sportoviste_id
    JOIN treneri t    ON t.id = il.trener_id
    WHERE vr.uzivatel_id=?
    ORDER BY il.datum DESC, vr.slot_cas_od DESC
");
$rezervace->execute([$uzivatelId]);
$rezervace = $rezervace->fetchAll(PDO::FETCH_ASSOC);

$minDatum3 = (new DateTime())->modify('+3 days')->format('Y-m-d');

$stavBadge = [
    'ceka'            => ['warning',  'Čeká na potvrzení'],
    'potvrzena'       => ['success',  'Potvrzena'],
    'zamitnuta'       => ['danger',   'Zamítnuta'],
    'zrusena'         => ['secondary','Zrušena'],
    'cekaci_listina'  => ['warning',  'Na čekací listině'],
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje rezervace — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="kalendar.php">
            <i class="bi bi-bicycle me-2 text-primary"></i>Rezervace Kovopraha
        </a>
        <div class="d-flex gap-2">
            <a href="kalendar.php" class="btn btn-outline-primary btn-sm">Kalendář</a>
            <a href="odhlaseni.php" class="btn btn-outline-danger btn-sm">Odhlásit</a>
        </div>
    </div>
</nav>
<div class="container mt-4" style="max-width:700px">
    <h5 class="mb-3"><i class="bi bi-calendar-check me-2"></i>Moje rezervace</h5>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if (empty($rezervace)): ?>
        <div class="alert alert-info">
            Žádné rezervace. <a href="kalendar.php">Prohlédněte dostupné termíny</a>.
        </div>
    <?php endif; ?>

    <?php foreach ($rezervace as $r):
        [$bColor, $bLabel] = $stavBadge[$r['stav']] ?? ['secondary', $r['stav']];
        $isCekaci       = $r['stav'] === 'cekaci_listina';
        $muzeStoornovat = $isCekaci // z čekací listiny vždy
            || ($r['datum'] >= $minDatum3 && in_array($r['stav'], ['ceka', 'potvrzena'], true));
    ?>
    <div class="card shadow-sm mb-3 <?= in_array($r['stav'], ['zamitnuta','zrusena']) ? 'opacity-60' : '' ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <strong><?= h($r['nazev']) ?></strong>
                    <span class="badge bg-<?= $bColor ?> ms-2"><?= $bLabel ?></span>
                    <?php if ($r['zaplaceno'] && $r['stav'] === 'potvrzena'): ?>
                        <span class="badge bg-success ms-1"><i class="bi bi-check-circle me-1"></i>Zaplaceno</span>
                    <?php elseif ($r['stav'] === 'potvrzena'): ?>
                        <span class="badge bg-warning text-dark ms-1">Nezaplaceno</span>
                    <?php endif; ?>
                </div>
                <span class="fw-bold text-primary"><?= number_format($r['cena_kc'], 0) ?> Kč</span>
            </div>
            <div class="text-muted small mt-2">
                <i class="bi bi-building me-1"></i><?= h($r['sport_nazev']) ?> &nbsp;
                <i class="bi bi-calendar me-1"></i><?= h($r['datum']) ?> &nbsp;
                <?php if (!empty($r['slot_cas_od']) && !empty($r['slot_cas_do'])): ?>
                    <i class="bi bi-clock-fill me-1 text-primary"></i>
                    <strong class="text-dark"><?= h(substr($r['slot_cas_od'],0,5)) ?>–<?= h(substr($r['slot_cas_do'],0,5)) ?></strong>
                <?php else: ?>
                    <i class="bi bi-clock me-1"></i><?= h(substr($r['cas_od'],0,5)) ?>–<?= h(substr($r['cas_do'],0,5)) ?>
                <?php endif; ?>
                &nbsp;<i class="bi bi-person me-1"></i><?= h($r['trener_jmeno']) ?>
            </div>
            <?php if ($muzeStoornovat): ?>
                <form method="post" class="mt-2"
                      onsubmit="return confirm('Opravdu zrušit tuto rezervaci?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="rezervace_id" value="<?= $r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i>Zrušit rezervaci
                    </button>
                </form>
            <?php elseif ($r['datum'] < $minDatum3 && in_array($r['stav'], ['ceka','potvrzena'])): ?>
                <div class="text-muted small mt-2">
                    <i class="bi bi-lock me-1"></i>Storno již není možné (méně než 3 dny do lekce)
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
