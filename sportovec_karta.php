<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/sportovec_status_lib.php';
require_once __DIR__ . '/includes/sportovec_history_lib.php';

if (!isset($_SESSION['trener_id']) || !canAccess('sprava_sportovcu')) {
    header('Location: login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function d(?string $date): string {
    if (!$date || $date === '0000-00-00') return '—';
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : h($date);
}
function money($v): string { return number_format((float)$v, 2, ',', ' ') . ' Kč'; }

$sportovecId = (int)($_GET['sportovec_id'] ?? $_GET['id'] ?? 0);
if ($sportovecId <= 0) {
    header('Location: sprava_sportovcu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header('Location: sportovec_karta.php?sportovec_id=' . $sportovecId);
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'set_status') {
        $stav = $_POST['stav_clenstvi'] ?? 'cekajici';
        if (!in_array($stav, ['aktivni','cekajici','neaktivni','archiv'], true)) {
            $stav = 'cekajici';
        }
        $duvod = trim((string)($_POST['stav_duvod'] ?? ''));
        $oldStmt = $pdo->prepare('SELECT stav_clenstvi, stav_duvod, stav_manualni FROM sportovci WHERE id = ?');
        $oldStmt->execute([$sportovecId]);
        $old = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("
            UPDATE sportovci
            SET stav_clenstvi = :stav,
                stav_duvod = :duvod,
                stav_manualni = 1,
                stav_aktualizovan = CURRENT_TIMESTAMP
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':stav' => $stav, ':duvod' => $duvod ?: null, ':id' => $sportovecId]);
        sportovecLogEvent($pdo, $sportovecId, 'status_manual', 'Ruční změna stavu člena', $old, [
            'stav_clenstvi' => $stav,
            'stav_duvod' => $duvod,
            'stav_manualni' => 1,
        ], 'manual', (int)$_SESSION['trener_id']);
        $_SESSION['flash_success'] = 'Stav člena byl uložen.';
        header('Location: sportovec_karta.php?sportovec_id=' . $sportovecId);
        exit;
    }

    if ($action === 'clear_manual_status') {
        $oldStmt = $pdo->prepare('SELECT * FROM sportovci WHERE id = ?');
        $oldStmt->execute([$sportovecId]);
        $old = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $pdo->prepare("UPDATE sportovci SET stav_manualni = 0, stav_aktualizovan = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1")->execute([$sportovecId]);
        $freshStmt = $pdo->prepare('SELECT * FROM sportovci WHERE id = ?');
        $freshStmt->execute([$sportovecId]);
        $fresh = $freshStmt->fetch(PDO::FETCH_ASSOC);
        if ($fresh) sportovecStatusUpdate($pdo, $sportovecId, $fresh);
        sportovecLogEvent($pdo, $sportovecId, 'status_auto', 'Návrat na automatický stav', $old, [], 'manual', (int)$_SESSION['trener_id']);
        $_SESSION['flash_success'] = 'Ruční stav byl zrušen.';
        header('Location: sportovec_karta.php?sportovec_id=' . $sportovecId);
        exit;
    }

    if ($action === 'add_note') {
        $text = trim((string)($_POST['note'] ?? ''));
        if ($text !== '') {
            $stmt = $pdo->prepare("INSERT INTO sportovec_interni_poznamka (sportovec_id, trener_id, text) VALUES (?, ?, ?)");
            $stmt->execute([$sportovecId, (int)$_SESSION['trener_id'], $text]);
            sportovecLogEvent($pdo, $sportovecId, 'note', 'Interní poznámka', [], ['text' => $text], 'manual', (int)$_SESSION['trener_id'], $text);
            $_SESSION['flash_success'] = 'Poznámka byla přidána.';
        }
        header('Location: sportovec_karta.php?sportovec_id=' . $sportovecId . '#historie');
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT s.*,
           (SELECT GROUP_CONCAT(DISTINCT sk.nazev ORDER BY sk.nazev SEPARATOR ', ')
            FROM sportovec_skupina ss JOIN skupiny sk ON sk.id = ss.skupina_id
            WHERE ss.sportovec_id = s.id) AS skupiny,
           (SELECT GROUP_CONCAT(DISTINCT ps.nazev ORDER BY ps.nazev SEPARATOR ', ')
            FROM sportovec_podskupina sp JOIN podskupiny ps ON ps.id = sp.podskupina_id
            WHERE sp.sportovec_id = s.id) AS podskupiny,
           (SELECT COUNT(*) FROM trenink_sportovec ts WHERE ts.sportovec_id = s.id) AS pocet_treninku,
           (SELECT MAX(t.datum) FROM treninky t JOIN trenink_sportovec ts ON ts.trenink_id = t.id WHERE ts.sportovec_id = s.id) AS posledni_trenink
    FROM sportovci s
    WHERE s.id = ?
");
$stmt->execute([$sportovecId]);
$sportovec = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sportovec) {
    header('Location: sprava_sportovcu.php');
    exit;
}
$status = sportovecStatusCompute($sportovec);

$recentTreninky = $pdo->prepare("
    SELECT t.id, t.datum, t.napln, t.kategorie, t.delka
    FROM treninky t
    JOIN trenink_sportovec ts ON ts.trenink_id = t.id
    WHERE ts.sportovec_id = ?
    ORDER BY t.datum DESC, t.id DESC
    LIMIT 8
");
$recentTreninky->execute([$sportovecId]);
$treninky = $recentTreninky->fetchAll(PDO::FETCH_ASSOC);

$zavodyStmt = $pdo->prepare("
    SELECT z.id, z.datum, z.kategorie, z.popis, zs.poradi, zs.cas
    FROM zavod_sportovec zs
    JOIN zavody z ON z.id = zs.zavod_id
    WHERE zs.sportovec_id = ?
    ORDER BY z.datum DESC, z.id DESC
    LIMIT 8
");
$zavodyStmt->execute([$sportovecId]);
$zavody = $zavodyStmt->fetchAll(PDO::FETCH_ASSOC);

$testyStmt = $pdo->prepare("SELECT * FROM zatezove_testy WHERE sportovec_id = ? ORDER BY datum DESC, id DESC LIMIT 5");
$testyStmt->execute([$sportovecId]);
$testy = $testyStmt->fetchAll(PDO::FETCH_ASSOC);

$obdobiStmt = $pdo->prepare("SELECT * FROM sportovec_obdobi WHERE sportovec_id = ? ORDER BY datum_od DESC LIMIT 6");
$obdobiStmt->execute([$sportovecId]);
$obdobi = $obdobiStmt->fetchAll(PDO::FETCH_ASSOC);

$notesStmt = $pdo->prepare("
    SELECT p.*, t.jmeno AS trener_jmeno
    FROM sportovec_interni_poznamka p
    LEFT JOIN treneri t ON t.id = p.trener_id
    WHERE p.sportovec_id = ?
    ORDER BY p.datum DESC, p.id DESC
    LIMIT 8
");
$notesStmt->execute([$sportovecId]);
$notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);
$history = sportovecHistoryFetch($pdo, $sportovecId, 80);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin karta člena - <?= h($sportovec['prijmeni'] . ' ' . $sportovec['jmeno']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:#f0f2f5; }
        .section-card { border:0; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        .hero-card { border:0; border-radius:8px; background:#1f2937; color:#fff; }
        .metric { background:#fff; border-radius:8px; padding:1rem; box-shadow:0 2px 8px rgba(0,0,0,.06); }
        .metric .val { font-size:1.45rem; font-weight:700; }
        .timeline { border-left:3px solid #dee2e6; margin-left:.6rem; padding-left:1rem; }
        .timeline-item { position:relative; margin-bottom:1rem; }
        .timeline-item:before { content:""; position:absolute; left:-1.45rem; top:.2rem; width:.7rem; height:.7rem; border-radius:50%; background:#0d6efd; }
    </style>
</head>
<body>
<?php include __DIR__ . '/hlavicka.php'; ?>
<div class="container py-4" style="max-width:1280px;">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="card hero-card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between gap-3 align-items-center">
            <div>
                <div class="text-uppercase small opacity-75 mb-1">Administrační karta člena</div>
                <h1 class="h3 mb-1"><?= h($sportovec['prijmeni'] . ' ' . $sportovec['jmeno']) ?></h1>
                <div class="opacity-75">
                    narození <?= d($sportovec['narozeni'] ?? null) ?> · <?= h($sportovec['category'] ?? 'bez kategorie') ?>
                    <?= $sportovec['uciid'] ? ' · UCI ' . h($sportovec['uciid']) : '' ?>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge <?= h($status['class']) ?> fs-6"><?= h($status['label']) ?><?= $status['manual'] ? ' ručně' : '' ?></span>
                <a href="sprava_sportovcu.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Správa</a>
                <a href="sportovec_detail.php?id=<?= (int)$sportovecId ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-eye"></i> Starý detail</a>
                <?php if (!empty($sportovec['hash'])): ?>
                    <a href="sportovec_treninky.php?hash=<?= h($sportovec['hash']) ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Veřejná karta sportovce</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="metric"><div class="val"><?= (int)$sportovec['pocet_treninku'] ?></div><div class="text-muted small">Tréninků</div></div></div>
        <div class="col-6 col-lg-3"><div class="metric"><div class="val"><?= d($sportovec['posledni_trenink'] ?? null) ?></div><div class="text-muted small">Poslední trénink</div></div></div>
        <div class="col-6 col-lg-3"><div class="metric"><div class="val"><?= (float)($sportovec['kis_neuhrazeno'] ?? 0) > 0 ? money($sportovec['kis_neuhrazeno']) : '0 Kč' ?></div><div class="text-muted small">KIS neuhrazeno</div></div></div>
        <div class="col-6 col-lg-3"><div class="metric"><div class="val"><?= d($sportovec['kis_posledni_sync'] ?? null) ?></div><div class="text-muted small">Poslední KIS sync</div></div></div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#prehled" type="button">Přehled</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#kis" type="button">KIS a platby</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sport" type="button">Sport</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#poznamky" type="button">Poznámky</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#historie" type="button">Historie</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="prehled">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card section-card">
                        <div class="card-header bg-white fw-semibold">Kontaktní a členské údaje</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= $sportovec['email'] ? h($sportovec['email']) : '<span class="text-muted">—</span>' ?></dd>
                                <dt class="col-sm-3">Telefon</dt><dd class="col-sm-9"><?= $sportovec['telefon'] ? h($sportovec['telefon']) : '<span class="text-muted">—</span>' ?></dd>
                                <dt class="col-sm-3">Oddíl</dt><dd class="col-sm-9"><?= $sportovec['oddil'] ? h($sportovec['oddil']) : '<span class="text-muted">—</span>' ?></dd>
                                <dt class="col-sm-3">Skupiny</dt><dd class="col-sm-9"><?= $sportovec['skupiny'] ? h($sportovec['skupiny']) : '<span class="text-muted">bez skupiny</span>' ?></dd>
                                <dt class="col-sm-3">Podskupiny</dt><dd class="col-sm-9"><?= $sportovec['podskupiny'] ? h($sportovec['podskupiny']) : '<span class="text-muted">—</span>' ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card section-card">
                        <div class="card-header bg-white fw-semibold">Stav člena</div>
                        <div class="card-body">
                            <p class="mb-2"><?= sportovecStatusBadge($sportovec) ?></p>
                            <p class="text-muted small"><?= h($status['reason']) ?></p>
                            <form method="post" class="row g-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <div class="col-5">
                                    <select name="stav_clenstvi" class="form-select form-select-sm">
                                        <?php foreach (['aktivni'=>'Aktivní','cekajici'=>'Čekající','neaktivni'=>'Neaktivní','archiv'=>'Archiv'] as $k => $v): ?>
                                            <option value="<?= h($k) ?>" <?= ($sportovec['stav_clenstvi'] ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-5"><input name="stav_duvod" class="form-control form-control-sm" placeholder="Důvod" value="<?= h($sportovec['stav_duvod'] ?? '') ?>"></div>
                                <div class="col-2"><button class="btn btn-sm btn-primary w-100" title="Uložit"><i class="bi bi-check-lg"></i></button></div>
                            </form>
                            <?php if (!empty($sportovec['stav_manualni'])): ?>
                                <form method="post" class="mt-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="clear_manual_status">
                                    <button class="btn btn-sm btn-outline-secondary">Vrátit na automatický stav</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="kis">
            <div class="card section-card">
                <div class="card-header bg-white fw-semibold">KIS stav a soupisky</div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="badge <?= !empty($sportovec['kis_aktivni']) ? 'bg-success' : 'bg-secondary' ?>">KIS <?= !empty($sportovec['kis_aktivni']) ? 'aktivní' : 'neaktivní' ?></span>
                        <span class="badge <?= !empty($sportovec['kis_platebne_aktivni']) ? 'bg-success' : 'bg-warning text-dark' ?>">Platby <?= !empty($sportovec['kis_platebne_aktivni']) ? 'aktivní' : 'bez potvrzení' ?></span>
                        <?php if ((float)($sportovec['kis_neuhrazeno'] ?? 0) > 0): ?><span class="badge bg-danger"><?= money($sportovec['kis_neuhrazeno']) ?></span><?php endif; ?>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Soupisky</dt><dd class="col-sm-9"><?= $sportovec['kis_soupisky'] ? h($sportovec['kis_soupisky']) : '<span class="text-muted">—</span>' ?></dd>
                        <dt class="col-sm-3">Poslední úhrada</dt><dd class="col-sm-9"><?= d($sportovec['kis_posledni_uhrada'] ?? null) ?></dd>
                        <dt class="col-sm-3">Naposledy v importu</dt><dd class="col-sm-9"><?= d($sportovec['kis_last_seen_at'] ?? null) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="sport">
            <div class="row g-3">
                <div class="col-lg-6"><div class="card section-card"><div class="card-header bg-white fw-semibold">Poslední tréninky</div><div class="card-body p-0"><table class="table table-sm mb-0"><?php foreach ($treninky as $t): ?><tr><td><?= d($t['datum']) ?></td><td><?= h($t['kategorie'] ?: '—') ?></td><td><?= h(mb_strimwidth((string)$t['napln'], 0, 70, '…', 'UTF-8')) ?></td></tr><?php endforeach; if (!$treninky): ?><tr><td class="text-muted p-3">Žádné tréninky.</td></tr><?php endif; ?></table></div></div></div>
                <div class="col-lg-6"><div class="card section-card"><div class="card-header bg-white fw-semibold">Závody</div><div class="card-body p-0"><table class="table table-sm mb-0"><?php foreach ($zavody as $z): ?><tr><td><?= d($z['datum']) ?></td><td><?= h($z['kategorie']) ?></td><td><a href="zavod_detail.php?id=<?= (int)$z['id'] ?>"><?= h(mb_strimwidth((string)$z['popis'], 0, 60, '…', 'UTF-8')) ?></a></td><td><?= h($z['cas'] ?? '') ?></td></tr><?php endforeach; if (!$zavody): ?><tr><td class="text-muted p-3">Žádné závody.</td></tr><?php endif; ?></table></div></div></div>
                <div class="col-lg-6"><div class="card section-card"><div class="card-header bg-white fw-semibold">Kreditní období</div><div class="card-body p-0"><table class="table table-sm mb-0"><?php foreach ($obdobi as $o): ?><tr><td><?= d($o['datum_od']) ?> - <?= d($o['datum_do'] ?? null) ?></td><td><?= money($o['sazba_kc']) ?></td><td><?= !empty($o['vyplaceno']) ? '<span class="badge bg-success">vyplaceno</span>' : '<span class="badge bg-warning text-dark">otevřeno</span>' ?></td></tr><?php endforeach; if (!$obdobi): ?><tr><td class="text-muted p-3">Žádná období.</td></tr><?php endif; ?></table></div></div></div>
                <div class="col-lg-6"><div class="card section-card"><div class="card-header bg-white fw-semibold">Zátěžové testy</div><div class="card-body p-0"><table class="table table-sm mb-0"><?php foreach ($testy as $t): ?><tr><td><?= d($t['datum']) ?></td><td><?= $t['vaha_kg'] ? h($t['vaha_kg']) . ' kg' : '—' ?></td><td><?= $t['vyska_cm'] ? h($t['vyska_cm']) . ' cm' : '—' ?></td></tr><?php endforeach; if (!$testy): ?><tr><td class="text-muted p-3">Žádné testy.</td></tr><?php endif; ?></table></div></div></div>
            </div>
        </div>

        <div class="tab-pane fade" id="poznamky">
            <div class="card section-card mb-3">
                <div class="card-header bg-white fw-semibold">Přidat interní poznámku</div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_note">
                        <textarea name="note" class="form-control mb-2" rows="3" required></textarea>
                        <button class="btn btn-primary btn-sm">Uložit poznámku</button>
                    </form>
                </div>
            </div>
            <div class="card section-card"><div class="card-header bg-white fw-semibold">Poznámky</div><div class="card-body"><?php foreach ($notes as $n): ?><div class="border-bottom pb-2 mb-2"><div class="small text-muted"><?= d($n['datum']) ?> · <?= h($n['trener_jmeno'] ?? '') ?></div><?= nl2br(h($n['text'])) ?></div><?php endforeach; if (!$notes): ?><div class="text-muted">Žádné interní poznámky.</div><?php endif; ?></div></div>
        </div>

        <div class="tab-pane fade" id="historie">
            <div class="card section-card">
                <div class="card-header bg-white fw-semibold">Historie změn</div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($history as $ev): ?>
                            <div class="timeline-item">
                                <div class="fw-semibold"><?= h($ev['title']) ?></div>
                                <div class="small text-muted"><?= d($ev['created_at']) ?> · <?= h($ev['source']) ?><?= $ev['trener_jmeno'] ? ' · ' . h($ev['trener_jmeno']) : '' ?></div>
                                <?php if ($ev['detail']): ?><div class="small"><?= nl2br(h($ev['detail'])) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$history): ?><div class="text-muted">Historie zatím neobsahuje žádné události.</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
