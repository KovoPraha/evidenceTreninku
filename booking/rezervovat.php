<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit;
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/one_time_token.php';
require_once __DIR__ . '/../includes/app_url.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$uzivatelId = (int)$_SESSION['verejny_uzivatel_id'];
$lekceId    = (int)($_GET['lekce_id'] ?? $_POST['lekce_id'] ?? 0);
$slotOd     = trim($_GET['slot_od'] ?? $_POST['slot_od'] ?? '');
$slotDo     = trim($_GET['slot_do'] ?? $_POST['slot_do'] ?? '');
$waitlist   = !empty($_GET['waitlist']) || !empty($_POST['waitlist']);
$errors     = [];

// Načtení lekce
$stLekce = $pdo->prepare("
    SELECT il.*, s.nazev AS sport_nazev, t.jmeno AS trener_jmeno, t.email AS trener_email
    FROM individualni_lekce il
    JOIN sportovist s ON s.id = il.sportoviste_id
    JOIN treneri t    ON t.id = il.trener_id
    WHERE il.id=? AND il.stav='aktivni' AND s.je_verejne=1
");
$stLekce->execute([$lekceId]);
$lekce = $stLekce->fetch(PDO::FETCH_ASSOC);

if (!$lekce) {
    header('Location: kalendar.php'); exit;
}

// Lekci v minulosti nelze rezervovat nikdy (ani s výjimkou 3 dnů)
$dnes = (new DateTime())->format('Y-m-d');
if ($lekce['datum'] < $dnes) {
    header('Location: kalendar.php'); exit;
}
// Minimální datum (3 dny předem) — lze přeskočit příznakem vyjimka_3_dny
$minDatum = (new DateTime())->modify('+3 days')->format('Y-m-d');
if ($lekce['datum'] < $minDatum && !$lekce['vyjimka_3_dny']) {
    header('Location: kalendar.php'); exit;
}

// Validace formátu slotu — musí být HH:MM a uvnitř okna
$reTime = '/^\d{2}:\d{2}$/';
if (!preg_match($reTime, $slotOd) || !preg_match($reTime, $slotDo)) {
    header('Location: kalendar.php'); exit;
}
$winOd = substr($lekce['cas_od'], 0, 5);
$winDo = substr($lekce['cas_do'], 0, 5);
if ($slotOd < $winOd || $slotDo > $winDo || $slotOd >= $slotDo) {
    header('Location: kalendar.php'); exit;
}

// Kontrola slot-specifické kapacity
$stKap = $pdo->prepare("
    SELECT COUNT(*) FROM verejne_rezervace
    WHERE lekce_id=? AND slot_cas_od=? AND stav IN ('ceka','potvrzena')
");
$stKap->execute([$lekceId, $slotOd]);
$obsazenoSlot = (int)$stKap->fetchColumn();
$slotPlny     = $obsazenoSlot >= (int)$lekce['max_osob'];

// Pokud je slot plný a nejsme v waitlist módu → přesměrovat na čekací listinu
if ($slotPlny && !$waitlist) {
    header('Location: rezervovat.php?lekce_id=' . $lekceId
        . '&slot_od=' . urlencode($slotOd)
        . '&slot_do=' . urlencode($slotDo)
        . '&waitlist=1');
    exit;
}
// Pokud je slot volný a jsme v waitlist módu → přesměrovat na normální rezervaci
if (!$slotPlny && $waitlist) {
    header('Location: rezervovat.php?lekce_id=' . $lekceId
        . '&slot_od=' . urlencode($slotOd)
        . '&slot_do=' . urlencode($slotDo));
    exit;
}

// Kontrola, zda uživatel již má aktivní rezervaci nebo je na čekací listině
$stDup = $pdo->prepare("
    SELECT id, stav FROM verejne_rezervace
    WHERE lekce_id=? AND uzivatel_id=? AND slot_cas_od=? AND stav IN ('ceka','potvrzena','cekaci_listina')
");
$stDup->execute([$lekceId, $uzivatelId, $slotOd]);
if ($stDup->fetchColumn()) {
    header('Location: moje_rezervace.php'); exit;
}

// Čekací listina — počet čekajících (pro zobrazení pozice)
$stWait = $pdo->prepare("SELECT COUNT(*) FROM verejne_rezervace WHERE lekce_id=? AND slot_cas_od=? AND stav='cekaci_listina'");
$stWait->execute([$lekceId, $slotOd]);
$naListine = (int)$stWait->fetchColumn();

// ── POST: provést rezervaci ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný token.';
    } else {
        $poznamka = trim($_POST['poznamka_klienta'] ?? '');
        $stavNovy = $waitlist ? 'cekaci_listina'
                    : ($lekce['typ'] === 'zelena' ? 'potvrzena' : 'ceka');
        $approval = (!$waitlist && $lekce['typ'] === 'zluta')
            ? one_time_token_issue(ONE_TIME_TOKEN_BOOKING_APPROVAL, 172800)
            : null;

        // Serializuj souběžné rezervace téhož slotu — jinak dva zákazníci obsadí
        // poslední místo současně (kontrola kapacity výše je mimo transakci).
        $lockName = 'rez_' . $lekceId . '_' . preg_replace('/[^0-9]/', '', $slotOd);
        $lockStatement = $pdo->prepare("SELECT GET_LOCK(?, 5)");
        $lockStatement->execute([$lockName]);
        $lockAcquired = (int)$lockStatement->fetchColumn() === 1;
        if (!$lockAcquired) {
            $errors[] = 'Rezervaci se nyní nepodařilo bezpečně uzamknout. Zkuste to prosím znovu.';
        } else {
          try {
            // Re-check kapacity pod zámkem
            $stKap2 = $pdo->prepare("SELECT COUNT(*) FROM verejne_rezervace WHERE lekce_id=? AND slot_cas_od=? AND stav IN ('ceka','potvrzena')");
            $stKap2->execute([$lekceId, $slotOd]);
            if (!$waitlist && (int)$stKap2->fetchColumn() >= (int)$lekce['max_osob']) {
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
                header('Location: rezervovat.php?lekce_id=' . $lekceId
                    . '&slot_od=' . urlencode($slotOd) . '&slot_do=' . urlencode($slotDo) . '&waitlist=1');
                exit;
            }
            // Re-check duplicity pod zámkem
            $stDup2 = $pdo->prepare("SELECT id FROM verejne_rezervace WHERE lekce_id=? AND uzivatel_id=? AND slot_cas_od=? AND stav IN ('ceka','potvrzena','cekaci_listina')");
            $stDup2->execute([$lekceId, $uzivatelId, $slotOd]);
            if ($stDup2->fetchColumn()) {
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
                header('Location: moje_rezervace.php'); exit;
            }

            $pdo->prepare("
                INSERT INTO verejne_rezervace
                    (lekce_id, uzivatel_id, stav, poznamka_klienta, potvrzovaci_token,
                     potvrzovaci_token_expires_at, slot_cas_od, slot_cas_do)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([
                $lekceId,
                $uzivatelId,
                $stavNovy,
                $poznamka ?: null,
                $approval['hash'] ?? null,
                $approval['expires_at'] ?? null,
                $slotOd,
                $slotDo,
            ]);
          } finally {
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
          }

        // Načtení jména uživatele pro email
        $stUz = $pdo->prepare("SELECT jmeno, prijmeni FROM verejni_uzivatele WHERE id=?");
        $stUz->execute([$uzivatelId]);
        $uzivatel = $stUz->fetch(PDO::FETCH_ASSOC);
        $uzJmeno  = $uzivatel['jmeno'] . ' ' . $uzivatel['prijmeni'];
        $slotInfo = "Slot: {$slotOd}–{$slotDo}";

        // Push notifikace trenérovi (pokud má aktivní subscripci)
        if (!$waitlist) {
            try {
                require_once __DIR__ . '/../includes/push_helper.php';
                $slotStr = substr($slotOd, 0, 5) . '–' . substr($slotDo, 0, 5);
                sendPushNotification($pdo, [
                    'title' => 'Nová rezervace',
                    'body'  => "{$uzJmeno}: {$lekce['nazev']} ({$lekce['datum']}, {$slotStr})",
                    'url'   => 'https://data.kovopraha.cz/evidence/individualni_lekce_sprava.php',
                    'tag'   => 'rezervace-' . $lekceId,
                ], [(int)$lekce['trener_id']]);
            } catch (Throwable $ex) { /* push je nepovinný */ }
        }

        if ($waitlist) {
            // Čekací listina — jen potvrzení zákazníkovi
            $_SESSION['flash_booking_success'] = 'Byli jste zařazeni na čekací listinu. Jakmile se slot uvolní, dostanete email.';
        } elseif ($lekce['typ'] === 'zelena') {
            // Trenér dostane info
            $subject = "Nová rezervace: {$lekce['nazev']}";
            $body = "Zákazník {$uzJmeno} si zarezervoval lekci:\n"
                . "Lekce: {$lekce['nazev']}\n"
                . "Sportoviště: {$lekce['sport_nazev']}\n"
                . "Datum: {$lekce['datum']}, {$slotInfo}\n\n"
                . "Přehled lekcí: " . appUrl('individualni_lekce_sprava.php');
            @mail($lekce['trener_email'], $subject, $body,
                "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");

            $_SESSION['flash_booking_success'] = 'Rezervace potvrzena! Uvidíte ji v přehledu svých rezervací.';
        } else {
            // Trenér musí potvrdit
            $host = appUrl('booking/potvrdit.php');
            $rawApprovalToken = (string)($approval['token'] ?? '');
            $linkPotvrdit = $host . '#token=' . rawurlencode($rawApprovalToken) . '&akce=potvrdit';
            $linkZamit    = $host . '#token=' . rawurlencode($rawApprovalToken) . '&akce=zamit';

            $subject = "Žádost o rezervaci (Žlutá): {$lekce['nazev']}";
            $body = "Zákazník {$uzJmeno} žádá o rezervaci:\n"
                . "Lekce: {$lekce['nazev']}\n"
                . "Datum: {$lekce['datum']}, {$slotInfo}\n\n"
                . "✓ POTVRDIT: {$linkPotvrdit}\n"
                . "✗ ZAMÍTNOUT: {$linkZamit}";
            @mail($lekce['trener_email'], $subject, $body,
                "From: evidence@kovopraha.cz\r\nContent-Type: text/plain; charset=utf-8");

            $_SESSION['flash_booking_success'] = 'Žádost o rezervaci odeslána — trenér vás brzy kontaktuje.';
        }

        header('Location: moje_rezervace.php');
        exit;
        }
    }
}

// Počet volných míst pro zobrazení
$volnoSlot = max(0, (int)$lekce['max_osob'] - $obsazenoSlot);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Potvrdit rezervaci — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav('velodrome'); ?>
<div class="container mt-5" style="max-width:540px">
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="card shadow">
        <div class="card-body p-4">
            <h5 class="mb-3">
                <?php if ($waitlist): ?>
                    <i class="bi bi-hourglass-split me-2 text-warning"></i>Přidat na čekací listinu
                <?php else: ?>
                    <i class="bi bi-calendar-check me-2 text-primary"></i>Potvrdit rezervaci
                <?php endif; ?>
            </h5>
            <?php if ($waitlist): ?>
            <div class="alert alert-warning py-2 mb-3">
                <i class="bi bi-people-fill me-2"></i>
                Tento slot je <strong>plně obsazen</strong>.
                Zařadíte se na čekací listinu — aktuálně čeká <strong><?= $naListine ?></strong> osob.
                Jakmile se místo uvolní, dostanete automaticky email.
            </div>
            <?php endif; ?>

            <!-- Souhrn lekce a slotu -->
            <div class="alert alert-light border mb-4">
                <div class="fw-semibold mb-2"><?= h($lekce['nazev']) ?></div>
                <div class="text-muted small">
                    <i class="bi bi-building me-1"></i><?= h($lekce['sport_nazev']) ?><br>
                    <i class="bi bi-calendar me-1"></i><?= h($lekce['datum']) ?><br>
                    <i class="bi bi-clock-fill me-1 text-primary"></i>
                    <strong class="text-dark"><?= h($slotOd) ?>–<?= h($slotDo) ?></strong>
                    <span class="text-muted">(okno <?= h($winOd) ?>–<?= h($winDo) ?>)</span><br>
                    <i class="bi bi-person me-1"></i>Trenér: <?= h($lekce['trener_jmeno']) ?>
                </div>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <span class="fw-bold text-primary fs-5"><?= number_format($lekce['cena_kc'], 0) ?> Kč</span>
                    <span class="text-muted small">
                        <i class="bi bi-people me-1"></i>Volná místa: <?= $volnoSlot ?>/<?= (int)$lekce['max_osob'] ?>
                    </span>
                </div>
                <?php if ($lekce['typ'] === 'zluta'): ?>
                    <div class="mt-2 small text-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Tato lekce vyžaduje potvrzení trenéra. Po odeslání žádosti vás budeme informovat emailem.
                    </div>
                <?php endif; ?>
            </div>

            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="lekce_id" value="<?= (int)$lekce['id'] ?>">
                <input type="hidden" name="slot_od"  value="<?= h($slotOd) ?>">
                <input type="hidden" name="slot_do"  value="<?= h($slotDo) ?>">
                <?php if ($waitlist): ?><input type="hidden" name="waitlist" value="1"><?php endif; ?>
                <div class="mb-4">
                    <label class="form-label">Poznámka pro trenéra (nepovinná)</label>
                    <textarea name="poznamka_klienta" class="form-control" rows="3"
                              placeholder="Úroveň jízdy, speciální požadavky…"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-<?= $waitlist ? 'warning' : 'primary' ?> flex-fill">
                        <i class="bi bi-<?= $waitlist ? 'hourglass' : 'check-lg' ?> me-1"></i>
                        <?= $waitlist ? 'Zařadit na čekací listinu' : ($lekce['typ'] === 'zelena' ? 'Rezervovat' : 'Odeslat žádost') ?>
                    </button>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">Zrušit</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
