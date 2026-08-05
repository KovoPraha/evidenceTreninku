<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/app_url.php';
require_once __DIR__ . '/includes/html_sanitizer.php';

// Jen hlavní trenér
$is_hlavni = canAccess('odeslat_emaily');
if (!$is_hlavni) {
    header('Location: index.php');
    exit;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Vytvoř tabulku email_log pokud neexistuje ─────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        sportovec_id INT NOT NULL,
        email        VARCHAR(255),
        predmet      VARCHAR(500),
        stav         ENUM('odeslano','chyba','bez_emailu') NOT NULL,
        odeslano_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        trener_id    INT,
        INDEX (odeslano_at),
        INDEX (sportovec_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log('email_log create: ' . $e->getMessage());
}

// ── Pomocné funkce ────────────────────────────────────────────────────────

function buildProfilUrl(string $hash): string {
    return appUrl('sportovec_treninky.php') . '?hash=' . urlencode($hash);
}

function applyTemplate(string $tpl, array $sp, string $odkaz): string {
    return str_replace(
        ['{jmeno}', '{prijmeni}', '{odkaz}', '{email}'],
        [
            htmlspecialchars($sp['jmeno']    ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sp['prijmeni'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($odkaz,              ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sp['email']    ?? '', ENT_QUOTES, 'UTF-8'),
        ],
        $tpl
    );
}

function applySubject(string $subj, array $sp): string {
    return str_replace(["\r", "\n"], '', str_replace(
        ['{jmeno}', '{prijmeni}'],
        [$sp['jmeno'] ?? '', $sp['prijmeni'] ?? ''],
        $subj
    ));
}

// ── Načtení uložené šablony ───────────────────────────────────────────────
$defaultPredmet = 'Tvůj profil v evidenci tréninků';
$defaultTelo    = '<p>Ahoj {jmeno},</p>
<p>přikládáme odkaz na tvůj osobní profil v evidenci tréninků, kde najdeš přehled svých tréninků, výsledků měření a kreditů:</p>
<p><a href="{odkaz}">{odkaz}</a></p>
<p>S pozdravem,<br>trenérský tým</p>';

try {
    $stN = $pdo->prepare("SELECT klic, hodnota FROM nastaveni WHERE klic IN ('email_predmet_last','email_telo_last')");
    $stN->execute();
    foreach ($stN->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['klic'] === 'email_predmet_last') $defaultPredmet = $row['hodnota'];
        if ($row['klic'] === 'email_telo_last')    $defaultTelo    = $row['hodnota'];
    }
} catch (PDOException $e) {
    // nastaveni tabulka nemusí mít email klíče
}

// ── Skupiny + podskupiny pro filtr ────────────────────────────────────────
$skupiny = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);

$filterSkupinaId    = $_GET['skupina_id'] ?? ($_POST['skupina_id'] ?? '');
$filterPodskupinaId = $_GET['podskupina_id'] ?? ($_POST['podskupina_id'] ?? '');

if ($filterSkupinaId    !== '' && !ctype_digit((string)$filterSkupinaId))    $filterSkupinaId = '';
if ($filterPodskupinaId !== '' && !ctype_digit((string)$filterPodskupinaId)) $filterPodskupinaId = '';

$podskupiny = [];
if ($filterSkupinaId !== '') {
    $stPs = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev");
    $stPs->execute([(int)$filterSkupinaId]);
    $podskupiny = $stPs->fetchAll(PDO::FETCH_ASSOC);
    if ($filterPodskupinaId !== '') {
        $allowed = array_map(fn($r) => (int)$r['id'], $podskupiny);
        if (!in_array((int)$filterPodskupinaId, $allowed, true)) $filterPodskupinaId = '';
    }
}

// ── Načtení sportovců dle filtru ──────────────────────────────────────────
$vsichniSportovci = [];
$nacistVsechny    = isset($_GET['vsichni']) || isset($_POST['vsichni']);

if ($nacistVsechny) {
    $stVs = $pdo->query("SELECT id, jmeno, prijmeni, email, hash FROM sportovci ORDER BY prijmeni, jmeno");
    $vsichniSportovci = $stVs->fetchAll(PDO::FETCH_ASSOC);
} elseif ($filterSkupinaId !== '') {
    $params = [':gid' => (int)$filterSkupinaId];
    $sqlSp  = "
        SELECT DISTINCT s.id, s.jmeno, s.prijmeni, s.email, s.hash
        FROM sportovci s
        JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
        WHERE sg.skupina_id = :gid
    ";
    if ($filterPodskupinaId !== '') {
        $sqlSp .= " AND EXISTS (SELECT 1 FROM sportovec_podskupina sp
                    WHERE sp.sportovec_id = s.id AND sp.podskupina_id = :pid)";
        $params[':pid'] = (int)$filterPodskupinaId;
    }
    $sqlSp .= " ORDER BY s.prijmeni, s.jmeno";
    $stSp = $pdo->prepare($sqlSp);
    $stSp->execute($params);
    $vsichniSportovci = $stSp->fetchAll(PDO::FETCH_ASSOC);
}

// ── Zpracování POST akcí ──────────────────────────────────────────────────
$previewData  = null;   // pro zobrazení náhledu
$sendResults  = null;   // výsledky po odeslání
$flashError   = null;
$predmetVal   = $defaultPredmet;
$teloVal      = $defaultTelo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Neplatny bezpecnostni token.');
    }
    $action    = $_POST['action'] ?? '';
    $predmetIn = $_POST['predmet'] ?? $defaultPredmet;
    $teloIn    = safeEmailHtml((string)($_POST['telo'] ?? $defaultTelo));
    $predmetVal = $predmetIn;
    $teloVal    = $teloIn;

    $vybraniIds = array_map('intval', $_POST['sportovci'] ?? []);

    if (!in_array($action, ['preview', 'send'], true)) {
        $flashError = 'Neznámá akce.';
    } elseif (empty($vybraniIds)) {
        $flashError = 'Vyber alespoň jednoho sportovce.';
    } elseif (trim($predmetIn) === '') {
        $flashError = 'Předmět emailu nesmí být prázdný.';
    } elseif (trim(strip_tags($teloIn)) === '') {
        $flashError = 'Tělo emailu nesmí být prázdné.';
    } else {
        // Načíst jen vybrané sportovce z DB (bezpečnostní kontrola)
        $placeholders = implode(',', array_fill(0, count($vybraniIds), '?'));
        $stSel = $pdo->prepare("SELECT id, jmeno, prijmeni, email, hash FROM sportovci WHERE id IN ($placeholders) ORDER BY prijmeni, jmeno");
        $stSel->execute($vybraniIds);
        $vybraniSportovci = $stSel->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'preview') {
            // Náhled prvních 3 emailů
            $preview3 = array_slice($vybraniSportovci, 0, 3);
            $previewData = [];
            foreach ($preview3 as $sp) {
                $odkaz   = buildProfilUrl($sp['hash']);
                $subjekt = applySubject($predmetIn, $sp);
                $telo    = applyTemplate($teloIn, $sp, $odkaz);
                $previewData[] = [
                    'jmeno'  => $sp['jmeno'] . ' ' . $sp['prijmeni'],
                    'email'  => $sp['email']  ?? '',
                    'subjekt'=> $subjekt,
                    'telo'   => $telo,
                    'odkaz'  => $odkaz,
                ];
            }

            // Uložit šablonu do nastaveni pro příště
            try {
                $stUps = $pdo->prepare("INSERT INTO nastaveni (klic, hodnota) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE hodnota=VALUES(hodnota), upraveno=CURRENT_TIMESTAMP");
                $stUps->execute(['email_predmet_last', $predmetIn]);
                $stUps->execute(['email_telo_last',    $teloIn]);
            } catch (PDOException $e) { /* ignore */ }

        } elseif ($action === 'send') {
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                $flashError = 'Neplatný CSRF token.';
            } else {
                // Odeslat emaily
                $cntOk    = 0;
                $cntFail  = 0;
                $cntNoMail= 0;
                $log      = [];
                $trenerId = (int)$_SESSION['trener_id'];

                foreach ($vybraniSportovci as $sp) {
                    $email = trim((string)($sp['email'] ?? ''));
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $log[] = ['sp' => $sp, 'stav' => 'bez_emailu', 'msg' => 'Chybí e-mail'];
                        $cntNoMail++;
                        try {
                            $stLog = $pdo->prepare("INSERT INTO email_log (sportovec_id, email, predmet, stav, trener_id) VALUES (?, ?, ?, 'bez_emailu', ?)");
                            $stLog->execute([$sp['id'], $email ?: null, mb_substr($predmetIn, 0, 500), $trenerId]);
                        } catch (PDOException $e) { /* ignore log error */ }
                        continue;
                    }

                    $odkaz   = buildProfilUrl($sp['hash']);
                    $subjekt = applySubject($predmetIn, $sp);
                    $telo    = applyTemplate($teloIn, $sp, $odkaz);

                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: Evidence tréninků <evidence@kovopraha.cz>\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion();

                    $ok = @mail($email, $subjekt, $telo, $headers);
                    $stav = $ok ? 'odeslano' : 'chyba';
                    if ($ok) $cntOk++; else $cntFail++;

                    $log[] = [
                        'sp'   => $sp,
                        'stav' => $stav,
                        'msg'  => $ok ? 'Odesláno' : 'Chyba při odesílání',
                    ];

                    try {
                        $stLog = $pdo->prepare("INSERT INTO email_log (sportovec_id, email, predmet, stav, trener_id) VALUES (?, ?, ?, ?, ?)");
                        $stLog->execute([$sp['id'], $email, mb_substr($predmetIn, 0, 500), $stav, $trenerId]);
                    } catch (PDOException $e) { /* ignore */ }
                }

                $sendResults = compact('cntOk', 'cntFail', 'cntNoMail', 'log');

                // Uložit šablonu po odeslání
                try {
                    $stUps = $pdo->prepare("INSERT INTO nastaveni (klic, hodnota) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE hodnota=VALUES(hodnota), upraveno=CURRENT_TIMESTAMP");
                    $stUps->execute(['email_predmet_last', $predmetIn]);
                    $stUps->execute(['email_telo_last',    $teloIn]);
                } catch (PDOException $e) { /* ignore */ }
            }
        }
    }
}

// Počet s / bez emailu
$pocetSEmailem   = count(array_filter($vsichniSportovci, fn($s) => !empty(trim($s['email'] ?? ''))));
$pocetBezEmailu  = count($vsichniSportovci) - $pocetSEmailem;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odeslat emaily sportovcům</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .section-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .section-card .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600; font-size: .92rem;
            padding: .65rem 1.1rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .hero-card {
            border: none; border-radius: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
        }
        .mini-muted { font-size: .84rem; color: #6c757d; }
        .sp-check-row { padding: .3rem .5rem; border-radius: 6px; }
        .sp-check-row:hover { background: #f8f9fa; }
        .sp-check-row.no-email { opacity: .6; }
        .placeholder-tag {
            display: inline-block;
            background: #e9ecef; border: 1px solid #ced4da;
            border-radius: 4px; padding: 1px 7px;
            font-family: monospace; font-size: .85rem;
            cursor: pointer; user-select: all;
        }
        .placeholder-tag:hover { background: #dee2e6; }
        .email-preview-card {
            border: 1px solid #dee2e6; border-radius: 8px;
            background: #fff; padding: 1rem;
            font-size: .9rem;
        }
        .email-preview-card .subject { font-weight: 600; border-bottom: 1px solid #f0f0f0; padding-bottom: .5rem; margin-bottom: .75rem; }
        #sp-list { max-height: 380px; overflow-y: auto; }
    </style>
</head>
<body>
<?php include 'hlavicka.php'; ?>

<div class="container py-4" style="max-width: 1100px;">

    <!-- Hero -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold fs-5">
                    <i class="bi bi-envelope-fill me-2 opacity-75"></i>Odeslat emaily sportovcům
                </div>
                <div class="opacity-75 small">Hromadné individualizované emaily s unikátním odkazem na profil každého sportovce.</div>
            </div>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house me-1"></i>Rozcestník
            </a>
        </div>
    </div>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($sendResults !== null): ?>
    <!-- ── Výsledky odeslání ──────────────────────────────────────── -->
    <div class="card section-card mb-4">
        <div class="card-header bg-<?= $sendResults['cntFail'] > 0 ? 'warning text-dark' : 'success text-white' ?>">
            <i class="bi bi-send-check"></i>Výsledky odesílání
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-sm-4 text-center">
                    <div class="fs-3 fw-bold text-success"><?= $sendResults['cntOk'] ?></div>
                    <div class="mini-muted">úspěšně odesláno</div>
                </div>
                <div class="col-sm-4 text-center">
                    <div class="fs-3 fw-bold text-danger"><?= $sendResults['cntFail'] ?></div>
                    <div class="mini-muted">chyba při odesílání</div>
                </div>
                <div class="col-sm-4 text-center">
                    <div class="fs-3 fw-bold text-warning"><?= $sendResults['cntNoMail'] ?></div>
                    <div class="mini-muted">přeskočeno (bez emailu)</div>
                </div>
            </div>
            <?php if ($sendResults['cntFail'] > 0): ?>
            <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.87rem;">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Část emailů se nepodařilo odeslat. Ověřte konfiguraci PHP <code>mail()</code>
                (XAMPP: <code>php.ini</code> → <code>sendmail_path</code>).
            </div>
            <?php endif; ?>
            <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Sportovec</th><th>Email</th><th>Stav</th></tr>
                </thead>
                <tbody>
                <?php foreach ($sendResults['log'] as $entry): ?>
                    <tr>
                        <td><?= h(($entry['sp']['prijmeni'] ?? '') . ' ' . ($entry['sp']['jmeno'] ?? '')) ?></td>
                        <td><?= h($entry['sp']['email'] ?? '—') ?></td>
                        <td>
                            <?php if ($entry['stav'] === 'odeslano'): ?>
                                <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Odesláno</span>
                            <?php elseif ($entry['stav'] === 'chyba'): ?>
                                <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Chyba</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-dash me-1"></i>Bez emailu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="mt-3">
                <a href="odeslat_emaily.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Odeslat další email
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>

    <?php if ($previewData !== null): ?>
    <!-- ── Náhled emailů ──────────────────────────────────────────── -->
    <div class="card section-card mb-4">
        <div class="card-header bg-info text-white">
            <i class="bi bi-eye"></i>Náhled — prvních <?= count($previewData) ?> email(y)
        </div>
        <div class="card-body">
            <div class="row g-3">
            <?php foreach ($previewData as $pv): ?>
                <div class="col-md-<?= count($previewData) === 1 ? '12' : (count($previewData) === 2 ? '6' : '4') ?>">
                    <div class="email-preview-card h-100">
                        <div class="mini-muted mb-1">
                            <i class="bi bi-person me-1"></i><?= h($pv['jmeno']) ?>
                            &nbsp;·&nbsp;
                            <i class="bi bi-envelope me-1"></i><?= h($pv['email'] ?: '(bez emailu)') ?>
                        </div>
                        <div class="subject">
                            <i class="bi bi-chat-right-text text-primary me-1"></i><?= h($pv['subjekt']) ?>
                        </div>
                        <div style="max-height:250px;overflow-y:auto;">
                            <?= $pv['telo'] /* HTML – admin-only, sanitized at input */ ?>
                        </div>
                        <div class="mini-muted mt-2">
                            <i class="bi bi-link-45deg me-1"></i>
                            <a href="<?= h($pv['odkaz']) ?>" target="_blank" class="text-break" style="font-size:.78rem;">
                                <?= h($pv['odkaz']) ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <!-- Tlačítko pro potvrzení odeslání -->
            <form method="post" class="mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="skupina_id" value="<?= h($filterSkupinaId) ?>">
                <input type="hidden" name="podskupina_id" value="<?= h($filterPodskupinaId) ?>">
                <?php if ($nacistVsechny): ?>
                    <input type="hidden" name="vsichni" value="1">
                <?php endif; ?>
                <input type="hidden" name="predmet" value="<?= h($predmetVal) ?>">
                <input type="hidden" name="telo" id="telo_confirm" value="<?= h($teloVal) ?>">
                <?php
                    // Znovu pošleme seznam vybraných IDs
                    $vybraniConfirm = array_map('intval', $_POST['sportovci'] ?? []);
                    foreach ($vybraniConfirm as $cid): ?>
                    <input type="hidden" name="sportovci[]" value="<?= (int)$cid ?>">
                <?php endforeach; ?>
                <div class="d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-send-fill me-2"></i>Odeslat <?= count($vybraniConfirm) ?> email(ů)
                    </button>
                    <a href="odeslat_emaily.php<?= $filterSkupinaId ? '?skupina_id=' . (int)$filterSkupinaId . ($filterPodskupinaId ? '&podskupina_id=' . (int)$filterPodskupinaId : '') : '' ?>"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Zpět na úpravu
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>

    <!-- ── HLAVNÍ FORMULÁŘ ─────────────────────────────────────────── -->
    <form method="post" id="mainForm">
        <input type="hidden" name="action" value="preview">
        <?= csrf_field() ?>
        <input type="hidden" name="skupina_id" value="<?= h($filterSkupinaId) ?>">
        <input type="hidden" name="podskupina_id" value="<?= h($filterPodskupinaId) ?>">
        <?php if ($nacistVsechny): ?>
            <input type="hidden" name="vsichni" value="1">
        <?php endif; ?>

        <div class="row g-3">

            <!-- LEVÝ SLOUPEC: výběr příjemců -->
            <div class="col-lg-5">

                <!-- Filtr skupiny -->
                <div class="card section-card mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-funnel"></i>Výběr příjemců
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label fw-semibold small mb-1">Skupina</label>
                            <select class="form-select form-select-sm" id="skupina_sel" name="_skupina_id">
                                <option value="">-- všechny skupiny --</option>
                                <?php foreach ($skupiny as $sk): ?>
                                    <option value="<?= (int)$sk['id'] ?>"
                                        <?= ((string)$filterSkupinaId === (string)$sk['id']) ? 'selected' : '' ?>>
                                        <?= h($sk['nazev']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!empty($podskupiny)): ?>
                        <div class="mb-2">
                            <label class="form-label fw-semibold small mb-1">Podskupina (volitelné)</label>
                            <select class="form-select form-select-sm" name="_podskupina_id">
                                <option value="">-- všechny podskupiny --</option>
                                <?php foreach ($podskupiny as $ps): ?>
                                    <option value="<?= (int)$ps['id'] ?>"
                                        <?= ((string)$filterPodskupinaId === (string)$ps['id']) ? 'selected' : '' ?>>
                                        <?= h($ps['nazev']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" id="btnFilter" class="btn btn-sm btn-primary">
                                <i class="bi bi-search me-1"></i>Načíst skupinu
                            </button>
                            <button type="button" id="btnVsichni" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people me-1"></i>Všichni sportovci
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Checkboxlist sportovců -->
                <?php if (!empty($vsichniSportovci)): ?>
                <div class="card section-card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-check me-1"></i>Příjemci</span>
                        <span class="badge bg-light text-success fw-normal">
                            <?= $pocetSEmailem ?> s emailem
                            <?php if ($pocetBezEmailu > 0): ?>
                                &nbsp;·&nbsp;<span class="text-warning"><?= $pocetBezEmailu ?> bez emailu</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="card-body pt-2 pb-2">
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="selAll">
                                <i class="bi bi-check-all me-1"></i>Vybrat vše s emailem
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="selNone">
                                <i class="bi bi-x-lg me-1"></i>Zrušit vše
                            </button>
                        </div>
                        <div id="sp-list">
                        <?php foreach ($vsichniSportovci as $sp):
                            $hasEmail = !empty(trim($sp['email'] ?? ''));
                        ?>
                            <div class="sp-check-row d-flex align-items-center gap-2 <?= $hasEmail ? '' : 'no-email' ?>">
                                <input type="checkbox"
                                       class="form-check-input sp-cb"
                                       name="sportovci[]"
                                       value="<?= (int)$sp['id'] ?>"
                                       id="sp_<?= (int)$sp['id'] ?>"
                                       <?= $hasEmail ? 'checked' : 'disabled title="Sportovec nemá e-mail"' ?>>
                                <label class="form-check-label flex-grow-1" for="sp_<?= (int)$sp['id'] ?>">
                                    <?= h($sp['prijmeni'] . ' ' . $sp['jmeno']) ?>
                                </label>
                                <?php if ($hasEmail): ?>
                                    <span class="mini-muted text-truncate" style="max-width:140px;" title="<?= h($sp['email']) ?>">
                                        <i class="bi bi-envelope text-success"></i>
                                        <?= h($sp['email']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="mini-muted"><i class="bi bi-exclamation-triangle text-warning" title="Chybí e-mail"></i></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-arrow-right-circle me-2"></i>Načti skupinu nebo všechny sportovce.
                </div>
                <?php endif; ?>

            </div><!-- /col -->

            <!-- PRAVÝ SLOUPEC: šablona emailu -->
            <div class="col-lg-7">

                <div class="card section-card mb-3">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-pencil-square"></i>Šablona emailu
                    </div>
                    <div class="card-body">

                        <!-- Nápověda k placeholderům -->
                        <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:.85rem;">
                            <strong><i class="bi bi-braces me-1 text-primary"></i>Dostupné proměnné</strong>
                            (klikni pro zkopírování):<br class="mb-1">
                            <span class="placeholder-tag" onclick="copyTag(this)">{jmeno}</span> křestní jméno &nbsp;
                            <span class="placeholder-tag" onclick="copyTag(this)">{prijmeni}</span> příjmení &nbsp;
                            <span class="placeholder-tag" onclick="copyTag(this)">{odkaz}</span> URL veřejného profilu &nbsp;
                            <span class="placeholder-tag" onclick="copyTag(this)">{email}</span> e-mail sportovce
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="predmet">Předmět</label>
                            <input type="text" name="predmet" id="predmet" class="form-control"
                                   value="<?= h($predmetVal) ?>" required
                                   placeholder="např. Tvůj profil tréninků – {jmeno} {prijmeni}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="telo">Tělo emailu (HTML)</label>
                            <textarea name="telo" id="telo" rows="10" class="form-control"
                                      style="display:none;"><?= h($teloVal) ?></textarea>
                            <div id="telo-editor" style="min-height:300px;"></div>
                        </div>

                    </div>
                </div>

                <!-- Odeslat tlačítko -->
                <div class="d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-primary btn-lg" id="btnPreview">
                        <i class="bi bi-eye me-2"></i>Zobrazit náhled &amp; pokračovat
                    </button>
                </div>

            </div><!-- /col -->

        </div><!-- /row -->
    </form>

    <?php endif; /* previewData */ ?>
    <?php endif; /* sendResults */ ?>

</div><!-- /container -->

<!-- TinyMCE -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#telo',
    menubar: false,
    plugins: ['link', 'lists', 'code'],
    toolbar: 'undo redo | bold italic underline | forecolor | link | bullist numlist | code',
    height: 320,
    skin: 'oxide',
    content_css: 'default',
    branding: false,
    setup: function(editor) {
        editor.on('change', function() {
            editor.save(); // sync to textarea
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filtr skupiny – redirect
document.getElementById('btnFilter')?.addEventListener('click', function() {
    const skId = document.getElementById('skupina_sel').value;
    const psEl = document.querySelector('[name="_podskupina_id"]');
    const psId = psEl ? psEl.value : '';
    let url = 'odeslat_emaily.php';
    const params = [];
    if (skId) params.push('skupina_id=' + encodeURIComponent(skId));
    if (psId) params.push('podskupina_id=' + encodeURIComponent(psId));
    if (params.length) url += '?' + params.join('&');
    location.href = url;
});

// Všichni sportovci
document.getElementById('btnVsichni')?.addEventListener('click', function() {
    location.href = 'odeslat_emaily.php?vsichni=1';
});

// Vybrat vše / zrušit vše
document.getElementById('selAll')?.addEventListener('click', function() {
    document.querySelectorAll('.sp-cb:not([disabled])').forEach(cb => cb.checked = true);
});
document.getElementById('selNone')?.addEventListener('click', function() {
    document.querySelectorAll('.sp-cb').forEach(cb => cb.checked = false);
});

// Kliknutí na placeholder tag = kopírování do schránky
function copyTag(el) {
    const txt = el.textContent;
    navigator.clipboard?.writeText(txt).then(() => {
        const orig = el.style.background;
        el.style.background = '#b6d4fe';
        setTimeout(() => el.style.background = orig, 600);
    });
}

// Před odesláním formuláře – sync TinyMCE
document.getElementById('mainForm')?.addEventListener('submit', function(e) {
    if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
    const checked = document.querySelectorAll('.sp-cb:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Vyber alespoň jednoho sportovce se zadaným e-mailem.');
    }
});
</script>
</body>
</html>
