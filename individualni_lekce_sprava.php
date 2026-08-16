<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('individualni_lekce')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$trenerId = (int)$_SESSION['trener_id'];

// ── POST akce ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '')) {
    $action   = $_POST['action'] ?? '';
    $lekceId  = (int)($_POST['lekce_id'] ?? 0);
    $rezervId = (int)($_POST['rezervace_id'] ?? 0);

    if ($action === 'zrusit_lekci' && $lekceId) {
        // Ověř vlastnictví lekce
        $stOwn = $pdo->prepare("SELECT nazev, datum, cas_od, cas_do FROM individualni_lekce WHERE id=? AND trener_id=?");
        $stOwn->execute([$lekceId, $trenerId]);
        $lek = $stOwn->fetch(PDO::FETCH_ASSOC);
        if ($lek) {
            // Načti zákazníky s živými rezervacemi pro informační email
            $stCust = $pdo->prepare("
                SELECT vu.email, vu.jmeno
                FROM verejne_rezervace vr
                JOIN verejni_uzivatele vu ON vu.id = vr.uzivatel_id
                WHERE vr.lekce_id=? AND vr.stav IN ('ceka','potvrzena','cekaci_listina')
            ");
            $stCust->execute([$lekceId]);
            $dotceni = $stCust->fetchAll(PDO::FETCH_ASSOC);

            $pdo->prepare("UPDATE individualni_lekce SET stav='zrusena' WHERE id=?")->execute([$lekceId]);
            // Zruš i všechny živé rezervace zákazníků (jinak zůstanou viset na neexistující lekci)
            $pdo->prepare("UPDATE verejne_rezervace SET stav='zrusena' WHERE lekce_id=? AND stav IN ('ceka','potvrzena','cekaci_listina')")
                ->execute([$lekceId]);
            $pdo->prepare("DELETE FROM rezervace_sportovist WHERE lekce_id=?")->execute([$lekceId]);

            foreach ($dotceni as $d) {
                $subject = 'Lekce zrušena — ' . str_replace(["\r","\n"], ' ', (string)$lek['nazev']);
                $body = "Dobrý den {$d['jmeno']},\n\nLekce \"{$lek['nazev']}\" dne {$lek['datum']} ("
                    . substr($lek['cas_od'],0,5) . '–' . substr($lek['cas_do'],0,5)
                    . ") byla bohužel zrušena.\n\nOmlouváme se za komplikace.";
                @mail($d['email'], $subject, $body, "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");
            }
            $_SESSION['flash_success'] = 'Lekce zrušena, informováno ' . count($dotceni) . ' zákazníků.';
        }
    }

    if ($action === 'potvrdit' && $rezervId) {
        $st = $pdo->prepare("
            SELECT vr.*, vu.email, vu.jmeno, vu.prijmeni,
                   il.nazev, il.datum, il.cas_od, il.cas_do, il.trener_id
            FROM verejne_rezervace vr
            JOIN verejni_uzivatele vu ON vu.id = vr.uzivatel_id
            JOIN individualni_lekce il ON il.id = vr.lekce_id
            WHERE vr.id=?
        ");
        $st->execute([$rezervId]);
        $rez = $st->fetch(PDO::FETCH_ASSOC);
        if ($rez && (int)$rez['trener_id'] === $trenerId && in_array($rez['stav'], ['ceka','cekaci_listina'], true)) {
            $pdo->prepare("UPDATE verejne_rezervace SET stav='potvrzena', cas_potvrzeni=NOW() WHERE id=?")
                ->execute([$rezervId]);
            // Email zákazníkovi
            $subject = 'Rezervace potvrzena — ' . str_replace(["\r","\n"], ' ', (string)$rez['nazev']);
            $body = "Dobrý den {$rez['jmeno']},\n\nVaše rezervace byla potvrzena:\n"
                . "Lekce: {$rez['nazev']}\n"
                . "Datum: {$rez['datum']}\n"
                . "Čas: " . substr($rez['cas_od'], 0, 5) . "–" . substr($rez['cas_do'], 0, 5) . "\n\n"
                . "Těšíme se na Vás!";
            @mail($rez['email'], $subject, $body,
                "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");
            $_SESSION['flash_success'] = 'Rezervace potvrzena, zákazník informován.';
        }
    }

    if ($action === 'zamit' && $rezervId) {
        $st = $pdo->prepare("
            SELECT vr.*, vu.email, vu.jmeno, il.nazev, il.datum, il.cas_od, il.cas_do, il.trener_id
            FROM verejne_rezervace vr
            JOIN verejni_uzivatele vu ON vu.id = vr.uzivatel_id
            JOIN individualni_lekce il ON il.id = vr.lekce_id
            WHERE vr.id=?
        ");
        $st->execute([$rezervId]);
        $rez = $st->fetch(PDO::FETCH_ASSOC);
        if ($rez && (int)$rez['trener_id'] === $trenerId && in_array($rez['stav'], ['ceka','cekaci_listina'], true)) {
            $pdo->prepare("UPDATE verejne_rezervace SET stav='zamitnuta' WHERE id=?")
                ->execute([$rezervId]);
            // Upozornit dalšího na čekací listině
            require_once __DIR__ . '/booking/waiting_list.php';
            notifyWaitingList($pdo, (int)$rez['lekce_id'], (string)($rez['slot_cas_od'] ?? ''));
            $subject = 'Rezervace zamítnuta — ' . str_replace(["\r","\n"], ' ', (string)$rez['nazev']);
            $body = "Dobrý den {$rez['jmeno']},\n\nBohužel Vaše rezervace na {$rez['datum']} ({$rez['nazev']}) byla zamítnuta.\n\nV případě dotazů nás kontaktujte.";
            @mail($rez['email'], $subject, $body,
                "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");
            $_SESSION['flash_success'] = 'Rezervace zamítnuta.';
        }
    }

    if (($action === 'zaplatit' || $action === 'nezaplatit') && $rezervId) {
        // IDOR guard — jen rezervace na lekcích tohoto trenéra
        $stChk = $pdo->prepare("
            SELECT vr.id FROM verejne_rezervace vr
            JOIN individualni_lekce il ON il.id = vr.lekce_id
            WHERE vr.id=? AND il.trener_id=?
        ");
        $stChk->execute([$rezervId, $trenerId]);
        if ($stChk->fetch()) {
            $nova = $action === 'zaplatit' ? 1 : 0;
            $pdo->prepare("UPDATE verejne_rezervace SET zaplaceno=? WHERE id=?")->execute([$nova, $rezervId]);
            if ($nova) $_SESSION['flash_success'] = 'Označeno jako zaplaceno.';
        }
    }

    header('Location: individualni_lekce_sprava.php');
    exit;
}

// ── Filtr ─────────────────────────────────────────────────────────────────────
$zobrazitVse = roleAtLeast('hlavni') && isset($_GET['vse']);
$podminka = $zobrazitVse ? '' : ' AND il.trener_id = ' . $trenerId;

// ── Lekce s rezervacemi ───────────────────────────────────────────────────────
$lekce = $pdo->query("
    SELECT il.*,
           s.nazev AS sport_nazev,
           t.jmeno AS trener_jmeno,
           COUNT(vr.id) AS pocet_rezervaci,
           SUM(vr.stav='potvrzena') AS potvrzenych,
           SUM(vr.stav='ceka') AS cekajicich,
           SUM(vr.stav='cekaci_listina') AS na_listine,
           SUM(vr.zaplaceno=1 AND vr.stav='potvrzena') AS zaplaceno
    FROM individualni_lekce il
    JOIN sportovist s ON s.id = il.sportoviste_id
    JOIN treneri t    ON t.id = il.trener_id
    LEFT JOIN verejne_rezervace vr ON vr.lekce_id = il.id AND vr.stav NOT IN ('zamitnuta','zrusena','cekaci_listina')
    WHERE 1=1 {$podminka}
    GROUP BY il.id
    ORDER BY il.datum DESC, il.cas_od DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Rezervace pro každou lekci
$rezervaceByLekce = [];
if (!empty($lekce)) {
    $lekceIds = implode(',', array_column($lekce, 'id'));
    $rezervace = $pdo->query("
        SELECT vr.*, vu.jmeno, vu.prijmeni, vu.email, vu.telefon
        FROM verejne_rezervace vr
        JOIN verejni_uzivatele vu ON vu.id = vr.uzivatel_id
        WHERE vr.lekce_id IN ({$lekceIds})
        ORDER BY vr.cas_rezervace
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rezervace as $r) {
        $rezervaceByLekce[$r['lekce_id']][] = $r;
    }
}

$stavBadge = [
    'ceka'           => ['warning',  'Čeká na potvrzení'],
    'potvrzena'      => ['success',  'Potvrzena'],
    'zamitnuta'      => ['danger',   'Zamítnuta'],
    'zrusena'        => ['secondary','Zrušena'],
    'cekaci_listina' => ['warning',  'Čekací listina'],
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Individuální lekce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0"><i class="bi bi-person-circle me-2 text-success"></i>Individuální lekce</h1>
        <div class="d-flex gap-2">
            <?php if (roleAtLeast('hlavni')): ?>
                <a href="?<?= $zobrazitVse ? '' : 'vse' ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-people me-1"></i><?= $zobrazitVse ? 'Jen moje' : 'Všechny' ?>
                </a>
            <?php endif; ?>
            <a href="individualni_lekce_form.php" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nová lekce
            </a>
            <a href="kalendar_sportovist.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-calendar3 me-1"></i>Kalendář
            </a>
        </div>
    </div>

    <?php if (empty($lekce)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Žádné lekce. <a href="individualni_lekce_form.php">Vypište první lekci</a>.
        </div>
    <?php endif; ?>

    <?php foreach ($lekce as $l):
        $rezervace = $rezervaceByLekce[$l['id']] ?? [];
        $jeMinulost = $l['datum'] < date('Y-m-d');
        $barvaTyp = $l['typ'] === 'zelena' ? 'success' : 'warning';
        $labelTyp = $l['typ'] === 'zelena' ? 'Zelená' : 'Žlutá';
    ?>
    <div class="card shadow-sm mb-3 <?= $l['stav'] === 'zrusena' ? 'opacity-50' : '' ?>">
        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <span class="badge bg-<?= $barvaTyp ?> me-1"><?= $labelTyp ?></span>
                <strong><?= h($l['nazev']) ?></strong>
                <span class="text-muted ms-2 small"><?= h($l['sport_nazev']) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">
                    <i class="bi bi-calendar me-1"></i><?= h($l['datum']) ?>
                    <?= h(substr($l['cas_od'], 0, 5)) ?>–<?= h(substr($l['cas_do'], 0, 5)) ?>
                    <?php if (!empty($l['slot_delka_min'])): ?>
                        <span class="badge bg-light text-dark border ms-1"><?= (int)$l['slot_delka_min'] ?> min/slot</span>
                    <?php endif; ?>
                </span>
                <span class="fw-bold"><?= number_format($l['cena_kc'], 0) ?> Kč</span>
                <a href="individualni_lekce_form.php?kopie_id=<?= $l['id'] ?>"
                   class="btn btn-sm btn-outline-secondary" title="Duplikovat (+7 dní)">
                    <i class="bi bi-copy"></i>
                </a>
                <?php if ($l['stav'] === 'aktivni' && !$jeMinulost): ?>
                    <form method="post" class="d-inline"
                          data-confirm="Zrušit lekci a všechny rezervace?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="zrusit_lekci">
                        <input type="hidden" name="lekce_id" value="<?= $l['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Zrušit lekci <?= h($l['nazev']) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($l['stav'] === 'zrusena'): ?>
                    <span class="badge bg-secondary">Zrušena</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body pb-1">
            <!-- Stats -->
            <div class="d-flex gap-3 mb-2 small text-muted">
                <span><i class="bi bi-people me-1"></i>Rezervací: <?= $l['pocet_rezervaci'] ?>/<?= $l['max_osob'] ?></span>
                <span class="text-success"><i class="bi bi-check-circle me-1"></i>Potvrzeno: <?= $l['potvrzenych'] ?></span>
                <?php if ($l['cekajicich'] > 0): ?>
                    <span class="text-warning fw-semibold"><i class="bi bi-clock me-1"></i>Čeká: <?= $l['cekajicich'] ?></span>
                <?php endif; ?>
                <?php if ($l['potvrzenych'] > 0): ?>
                    <span class="text-<?= $l['zaplaceno'] >= $l['potvrzenych'] ? 'success' : 'danger' ?>">
                        <i class="bi bi-cash me-1"></i>Zaplaceno: <?= $l['zaplaceno'] ?>/<?= $l['potvrzenych'] ?>
                    </span>
                <?php endif; ?>
                <?php if ($zobrazitVse): ?>
                    <span class="ms-auto">
                        <i class="bi bi-person me-1"></i><?= h($l['trener_jmeno']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Rezervace -->
            <?php if (!empty($rezervace)): ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Zákazník</th>
                        <th>Slot</th>
                        <th>Kontakt</th>
                        <th>Stav</th>
                        <th>Platba</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rezervace as $r):
                    [$bColor, $bLabel] = $stavBadge[$r['stav']] ?? ['secondary', $r['stav']];
                    $slotCas = (!empty($r['slot_cas_od']) && !empty($r['slot_cas_do']))
                        ? substr($r['slot_cas_od'],0,5) . '–' . substr($r['slot_cas_do'],0,5)
                        : '—';
                ?>
                    <tr>
                        <td><?= h($r['jmeno'] . ' ' . $r['prijmeni']) ?></td>
                        <td class="text-muted small"><?= h($slotCas) ?></td>
                        <td>
                            <a href="mailto:<?= h($r['email']) ?>" class="text-muted small"><?= h($r['email']) ?></a>
                            <?php if ($r['telefon']): ?>
                                <br><small><?= h($r['telefon']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $bColor ?>"><?= $bLabel ?></span></td>
                        <td>
                            <?php if ($r['stav'] === 'potvrzena'): ?>
                                <?php if ($r['zaplaceno']): ?>
                                    <span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Zaplaceno</span>
                                <?php else: ?>
                                    <span class="text-danger small"><i class="bi bi-x-circle me-1"></i>Nezaplaceno</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($r['stav'] === 'ceka' && $l['trener_id'] == $trenerId): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="potvrdit">
                                    <input type="hidden" name="rezervace_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i> Potvrdit
                                    </button>
                                </form>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="zamit">
                                    <input type="hidden" name="rezervace_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Zamítnout rezervaci <?= h(trim($r['jmeno'] . ' ' . $r['prijmeni'])) ?>">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            <?php elseif ($r['stav'] === 'potvrzena'): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action"
                                           value="<?= $r['zaplaceno'] ? 'nezaplatit' : 'zaplatit' ?>">
                                    <input type="hidden" name="rezervace_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $r['zaplaceno'] ? 'secondary' : 'success' ?>">
                                        <i class="bi bi-cash me-1"></i><?= $r['zaplaceno'] ? 'Odznačit' : 'Zaplaceno' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php elseif ($l['stav'] === 'aktivni'): ?>
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>Zatím žádné rezervace.
                    <?php if ($l['je_verejne'] ?? false): ?>
                        Zákazníci mohou rezervovat na <a href="booking/kalendar.php" target="_blank">veřejném kalendáři</a>.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</body>
</html>
