<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/sportovec_status_lib.php';
require_once __DIR__ . '/includes/person_match.php';

if (!isset($_SESSION['trener_id']) || !canAccess('sprava_sportovcu')) {
    header("Location: login.php");
    exit;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$createPreview = null;
$createInput = ['jmeno' => '', 'prijmeni' => '', 'narozeni' => '', 'email' => ''];
$createError = '';

// ── POST: uložení změn nebo založení sportovce ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header("Location: sprava_sportovcu.php");
        exit;
    }

    $akce = $_POST['akce'] ?? '';

    if ($akce === 'create') {
        if (!roleAtLeast('admin')) {
            http_response_code(403);
            exit('Tuto akci smí provést pouze administrátor.');
        }
        $createInput = [
            'jmeno' => trim((string)($_POST['jmeno'] ?? '')),
            'prijmeni' => trim((string)($_POST['prijmeni'] ?? '')),
            'narozeni' => trim((string)($_POST['narozeni'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
        ];
        $confirmation = (string)($_POST['create_confirmation'] ?? '');
        $overrideReason = trim((string)($_POST['override_reason'] ?? ''));
        try {
            $birthDate = personMatchV1Date($createInput['narozeni']);
            if ($createInput['jmeno'] === '' || $createInput['prijmeni'] === '' || $birthDate === null) {
                throw new InvalidArgumentException('Jméno, příjmení a platné datum narození jsou povinné.');
            }
            if (new DateTimeImmutable($birthDate) > new DateTimeImmutable('today')) {
                throw new InvalidArgumentException('Datum narození nesmí být v budoucnosti.');
            }
            $match = personMatchV1($pdo, $createInput);
            $createPreview = $match;
            if ($match['level'] === PERSON_MATCH_EXACT && $confirmation !== 'exact_override') {
                personMatchV1Audit(
                    $pdo,
                    (int)$_SESSION['trener_id'],
                    'exact_discovery',
                    $match,
                    null,
                    '',
                    ['source' => 'sprava_sportovcu']
                );
                $createError = 'Nalezena přesná shoda. Použijte existující osobu, nebo zdůvodněte výjimku.';
            } elseif ($match['level'] === PERSON_MATCH_EXACT && mb_strlen($overrideReason, 'UTF-8') < 10) {
                $createError = 'Důvod výjimky musí mít alespoň 10 znaků.';
            } elseif ($match['level'] === PERSON_MATCH_SIMILARITY && $confirmation !== 'similarity') {
                personMatchV1Audit(
                    $pdo,
                    (int)$_SESSION['trener_id'],
                    'similarity_discovery',
                    $match,
                    null,
                    '',
                    ['source' => 'sprava_sportovcu']
                );
                $createError = 'Nalezeny podobné osoby. Zkontrolujte je a založení výslovně potvrďte.';
            } else {
                $pdo->beginTransaction();
                $newPersonId = personMatchV1CreateManual($pdo, $createInput);
                $auditAction = $match['level'] === PERSON_MATCH_EXACT
                    ? 'override_create'
                    : ($match['level'] === PERSON_MATCH_SIMILARITY ? 'similarity_create' : 'create');
                personMatchV1Audit(
                    $pdo,
                    (int)$_SESSION['trener_id'],
                    $auditAction,
                    $match,
                    $newPersonId,
                    $overrideReason,
                    ['source' => 'sprava_sportovcu']
                );
                $pdo->commit();
                $_SESSION['flash_success'] = 'Osoba ' . $createInput['prijmeni'] . ' ' . $createInput['jmeno'] . ' byla založena jako čekající.';
                header('Location: sprava_sportovcu.php?q=' . rawurlencode($createInput['prijmeni']));
                exit;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof InvalidArgumentException) {
                $createError = $exception->getMessage();
            } else {
                error_log('sprava_sportovcu create: ' . $exception->getMessage());
                $createError = 'Osobu se nepodařilo bezpečně založit.';
            }
        }
    } elseif ($akce === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $jmeno    = trim($_POST['jmeno'] ?? '');
        $prijmeni = trim($_POST['prijmeni'] ?? '');
        $narozeni = trim($_POST['narozeni'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $uciid    = trim($_POST['uciid'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $oddil    = trim($_POST['oddil'] ?? '');

        if ($id <= 0 || !$jmeno || !$prijmeni) {
            $_SESSION['flash_error'] = 'Jméno a příjmení jsou povinné.';
            header("Location: sprava_sportovcu.php");
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE sportovci
                SET jmeno    = :jmeno,
                    prijmeni = :prijmeni,
                    narozeni = :narozeni,
                    category = :category,
                    uciid    = :uciid,
                    email    = :email,
                    oddil    = :oddil
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([
                ':jmeno'    => $jmeno,
                ':prijmeni' => $prijmeni,
                ':narozeni' => $narozeni ?: null,
                ':category' => $category,
                ':uciid'    => $uciid,
                ':email'    => $email,
                ':oddil'    => $oddil,
                ':id'       => $id,
            ]);
            $_SESSION['flash_success'] = 'Sportovec ' . $prijmeni . ' ' . $jmeno . ' aktualizován.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Chyba při ukládání: ' . $e->getMessage();
        }
        header("Location: sprava_sportovcu.php");
        exit;
    }
}

// ── Filtr ────────────────────────────────────────────────────────────────────
$q = trim($_GET['q'] ?? '');
$stavFilter = $_GET['stav'] ?? '';
$kisFilter = $_GET['kis'] ?? '';
if (!in_array($stavFilter, ['', 'aktivni', 'cekajici', 'neaktivni', 'archiv', 'manualni'], true)) {
    $stavFilter = '';
}
if (!in_array($kisFilter, ['', 'kis_aktivni', 'dluh', 'bez_skupiny', 'mimo_import'], true)) {
    $kisFilter = '';
}

$sql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM trenink_sportovec ts WHERE ts.sportovec_id = s.id) AS pocet_treninku,
           (SELECT GROUP_CONCAT(sk.nazev ORDER BY sk.nazev SEPARATOR ', ')
            FROM sportovec_skupina ss
            JOIN skupiny sk ON sk.id = ss.skupina_id
            WHERE ss.sportovec_id = s.id) AS skupiny
    FROM sportovci s
";
$params = [];
$where = [];

if ($q !== '') {
    $where[] = "(s.prijmeni LIKE :q OR s.jmeno LIKE :q OR s.uciid LIKE :q OR s.email LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($stavFilter === 'manualni') {
    $where[] = "COALESCE(s.stav_manualni, 0) = 1";
} elseif ($stavFilter !== '') {
    $where[] = "COALESCE(s.stav_clenstvi, 'cekajici') = :stav";
    $params[':stav'] = $stavFilter;
}
if ($kisFilter === 'kis_aktivni') {
    $where[] = "COALESCE(s.kis_aktivni, 0) = 1";
} elseif ($kisFilter === 'dluh') {
    $where[] = "COALESCE(s.kis_neuhrazeno, 0) > 0";
} elseif ($kisFilter === 'bez_skupiny') {
    $where[] = "NOT EXISTS (SELECT 1 FROM sportovec_skupina ss2 WHERE ss2.sportovec_id = s.id)";
} elseif ($kisFilter === 'mimo_import') {
    $where[] = "(s.kis_last_seen_at IS NULL OR s.kis_last_seen_at < DATE_SUB(NOW(), INTERVAL 90 DAY))";
}
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

// ── Řazení (klik na hlavičku sloupce) ────────────────────────────────────────
$sortMap = [
    'prijmeni' => ['s.prijmeni', 's.jmeno'],
    'jmeno'    => ['s.jmeno', 's.prijmeni'],
    'narozeni' => ['s.narozeni'],
    'category' => ['s.category'],
    'uciid'    => ['s.uciid'],
    'treninku' => ['pocet_treninku'],
    'kis'      => ['COALESCE(s.kis_neuhrazeno, 0)', 'COALESCE(s.kis_aktivni, 0)'],
];
$sort = isset($sortMap[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'prijmeni';
$dir  = (($_GET['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
$sql .= " ORDER BY " . implode(', ', array_map(fn($c) => "$c $dir", $sortMap[$sort]));

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Stránkování ──────────────────────────────────────────────────────────────
$perPage = (int)($_GET['pp'] ?? 100);
if (!in_array($perPage, [50, 100, 500, 0], true)) $perPage = 100;  // 0 = všichni
$page = max(1, (int)($_GET['p'] ?? 1));
$pocetStranek = $perPage > 0 ? max(1, (int)ceil(count($sportovci) / $perPage)) : 1;
if ($page > $pocetStranek) $page = $pocetStranek;
$sportovciPage = $perPage > 0 ? array_slice($sportovci, ($page - 1) * $perPage, $perPage) : $sportovci;

/** URL aktuální stránky se změněnými parametry (zachová filtr, řazení, stránku) */
function spUrl(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'sprava_sportovcu.php' . ($params ? '?' . http_build_query($params) : '');
}

/** Klikatelná hlavička sloupce se šipkou směru řazení */
function sortLink(string $key, string $label): string {
    global $sort, $dir;
    $nextDir = ($sort === $key && $dir === 'ASC') ? 'desc' : 'asc';
    $icon = $sort === $key
        ? ($dir === 'ASC' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>')
        : '';
    return '<a href="' . h(spUrl(['sort' => $key, 'dir' => $nextDir, 'p' => 1]))
        . '" class="text-white text-decoration-none">' . h($label) . $icon . '</a>';
}

// Statistiky
$totalCount = count($sportovci);
if ($q === '') {
    $stQ = $pdo->query("SELECT COUNT(DISTINCT sportovec_id) FROM trenink_sportovec");
    $aktivniCount = (int)$stQ->fetchColumn();
} else {
    $aktivniCount = 0;
    foreach ($sportovci as $s) {
        if ((int)$s['pocet_treninku'] > 0) $aktivniCount++;
    }
}
$kisAktivniCount = 0;
$kisDluhCount = 0;
try {
    $kisAktivniCount = (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE kis_aktivni = 1")->fetchColumn();
    $kisDluhCount = (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE kis_neuhrazeno > 0")->fetchColumn();
} catch (Throwable $e) {
    // KIS sloupce vznikaji auto-migraci; pri prvnim nacteni bez migrace jen nezobrazime statistiky.
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa sportovců – Evidence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .hero-card {
            border: none; border-radius: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
        }
        .section-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .section-card .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600; font-size: .92rem;
            padding: .65rem 1.1rem;
        }
        .stat-card {
            border: none; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            text-align: center; padding: 1.1rem;
        }
        .stat-card .stat-val { font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
        .stat-card .stat-label { font-size: .82rem; color: #6c757d; margin-top: .3rem; }
        .table-wrap { max-height: 520px; overflow-y: auto; }
        .sticky-head thead th { position: sticky; top: 0; z-index: 5; }
    </style>
</head>
<body>
<?php include __DIR__ . '/hlavicka.php'; ?>

<div class="container py-4" style="max-width: 1200px;">

    <!-- Hero -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="fw-semibold fs-5 mb-0">
                    <i class="bi bi-person-lines-fill me-2 opacity-75"></i>Správa sportovců
                </h1>
                <div class="opacity-75 small">Editace údajů sportovců — jméno, příjmení, kategorie, kontakt.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house me-1"></i>Rozcestník
                </a>
            </div>
        </div>
    </div>

    <!-- Flash zprávy -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($_SESSION['flash_success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($_SESSION['flash_error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <!-- Statistiky -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-val text-primary"><?= $totalCount ?></div>
                <div class="stat-label"><i class="bi bi-people me-1"></i><?= $q !== '' ? 'Nalezeno' : 'Celkem sportovců' ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-val text-success"><?= $aktivniCount ?></div>
                <div class="stat-label"><i class="bi bi-activity me-1"></i><?= $q !== '' ? 'S tréninky' : 'S alespoň 1 tréninkem' ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-val text-info"><?= $kisAktivniCount ?></div>
                <div class="stat-label"><i class="bi bi-person-check me-1"></i>KIS aktivni</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-val text-warning"><?= $kisDluhCount ?></div>
                <div class="stat-label"><i class="bi bi-cash-coin me-1"></i>KIS dluh</div>
            </div>
        </div>
    </div>

    <?php if (roleAtLeast('admin')): ?>
    <div class="card section-card mb-4" id="create-card">
        <div class="card-header bg-success text-white">
            <i class="bi bi-person-plus-fill me-1"></i>Založit novou osobu
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Ruční osoba vznikne ve stavu „čekající“. Před uložením proběhne závazná kontrola shod person-match-v1.</p>
            <?php if ($createError !== ''): ?><div class="alert alert-warning"><?= h($createError) ?></div><?php endif; ?>
            <?php if (is_array($createPreview) && $createPreview['candidates'] !== []): ?>
                <div class="alert <?= $createPreview['level'] === PERSON_MATCH_EXACT ? 'alert-danger' : 'alert-warning' ?>">
                    <div class="fw-semibold mb-2">
                        <?= $createPreview['level'] === PERSON_MATCH_EXACT ? 'Přesná shoda – založení je zablokováno' : 'Nalezeny podobné osoby' ?>
                    </div>
                    <div class="list-group mb-2">
                    <?php foreach ($createPreview['candidates'] as $candidate): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span>
                                <strong><?= h($candidate['prijmeni'] . ' ' . $candidate['jmeno']) ?></strong>
                                · nar. <?= h($candidate['narozeni'] ?? 'neuvedeno') ?>
                                · pravidlo <?= h(implode(', ', $candidate['rules'])) ?>
                                <?php if ($candidate['kis_external_id']): ?><span class="badge bg-info text-dark">KIS</span><?php endif; ?>
                            </span>
                            <a class="btn btn-sm btn-outline-primary" href="sportovec_karta.php?sportovec_id=<?= (int)$candidate['id'] ?>">
                                Připojit k této osobě / otevřít kartu
                            </a>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <span class="small">Zobrazeny jsou všechny nalezené shody a podobnosti.</span>
                </div>
            <?php endif; ?>
            <form method="post" class="row g-3" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="akce" value="create">
                <?php if (is_array($createPreview)): ?>
                    <input type="hidden" name="create_confirmation" value="<?= $createPreview['level'] === PERSON_MATCH_EXACT ? 'exact_override' : 'similarity' ?>">
                <?php endif; ?>
                <div class="col-md-3"><label class="form-label req">Příjmení</label><input class="form-control" name="prijmeni" maxlength="100" required value="<?= h($createInput['prijmeni']) ?>"></div>
                <div class="col-md-3"><label class="form-label req">Jméno</label><input class="form-control" name="jmeno" maxlength="100" required value="<?= h($createInput['jmeno']) ?>"></div>
                <div class="col-md-3"><label class="form-label req">Datum narození</label><input class="form-control" type="date" name="narozeni" required value="<?= h($createInput['narozeni']) ?>"></div>
                <div class="col-md-3"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" value="<?= h($createInput['email']) ?>"></div>
                <?php if (is_array($createPreview) && $createPreview['level'] === PERSON_MATCH_EXACT): ?>
                    <div class="col-12"><label class="form-label req">Důvod, proč jde o jinou osobu</label><textarea class="form-control" name="override_reason" minlength="10" maxlength="1000" required placeholder="Alespoň 10 znaků; důvod se uloží do auditu."></textarea></div>
                <?php endif; ?>
                <div class="col-12">
                    <button class="btn <?= is_array($createPreview) && $createPreview['level'] === PERSON_MATCH_EXACT ? 'btn-danger' : 'btn-success' ?>">
                        <i class="bi bi-person-plus me-1"></i>
                        <?= is_array($createPreview) && $createPreview['level'] === PERSON_MATCH_EXACT
                            ? 'Přesto založit jako novou osobu'
                            : (is_array($createPreview) && $createPreview['level'] === PERSON_MATCH_SIMILARITY
                                ? 'Potvrzuji kontrolu a zakládám osobu'
                                : 'Zkontrolovat shody a založit') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtr -->
    <div class="card section-card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <i class="bi bi-search text-muted"></i>
                <input type="text" name="q" class="form-control form-control-sm" style="max-width: 320px;" aria-label="Hledat sportovce"
                       placeholder="Hledat příjmení, jméno, UCI ID, email…" value="<?= h($q) ?>">
                <select name="stav" class="form-select form-select-sm" style="max-width: 190px;" aria-label="Filtrovat podle stavu">
                    <option value="">Vsechny stavy</option>
                    <option value="aktivni" <?= $stavFilter === 'aktivni' ? 'selected' : '' ?>>Aktivni</option>
                    <option value="cekajici" <?= $stavFilter === 'cekajici' ? 'selected' : '' ?>>Cekajici</option>
                    <option value="neaktivni" <?= $stavFilter === 'neaktivni' ? 'selected' : '' ?>>Neaktivni</option>
                    <option value="archiv" <?= $stavFilter === 'archiv' ? 'selected' : '' ?>>Archiv</option>
                    <option value="manualni" <?= $stavFilter === 'manualni' ? 'selected' : '' ?>>Rucni stav</option>
                </select>
                <select name="kis" class="form-select form-select-sm" style="max-width: 210px;" aria-label="Filtrovat podle KIS">
                    <option value="">KIS filtr</option>
                    <option value="kis_aktivni" <?= $kisFilter === 'kis_aktivni' ? 'selected' : '' ?>>KIS aktivni</option>
                    <option value="dluh" <?= $kisFilter === 'dluh' ? 'selected' : '' ?>>Dluh &gt; 0</option>
                    <option value="bez_skupiny" <?= $kisFilter === 'bez_skupiny' ? 'selected' : '' ?>>Bez skupiny</option>
                    <option value="mimo_import" <?= $kisFilter === 'mimo_import' ? 'selected' : '' ?>>Mimo import</option>
                </select>
                <select name="pp" class="form-select form-select-sm" style="max-width: 130px;" title="Počet na stránku">
                    <option value="50"  <?= $perPage === 50  ? 'selected' : '' ?>>50 / stránka</option>
                    <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 / stránka</option>
                    <option value="500" <?= $perPage === 500 ? 'selected' : '' ?>>500 / stránka</option>
                    <option value="0"   <?= $perPage === 0   ? 'selected' : '' ?>>Všichni</option>
                </select>
                <?php if (($sort ?? 'prijmeni') !== 'prijmeni'): ?><input type="hidden" name="sort" value="<?= h($sort) ?>"><?php endif; ?>
                <?php if (($dir ?? 'ASC') === 'DESC'): ?><input type="hidden" name="dir" value="desc"><?php endif; ?>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search me-1"></i>Hledat
                </button>
                <?php if ($q !== '' || $stavFilter !== '' || $kisFilter !== ''): ?>
                <a href="sprava_sportovcu.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Zrušit filtr
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabulka sportovců -->
    <div class="card section-card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people-fill me-1"></i>Seznam sportovců <span class="badge bg-light text-primary ms-1"><?= $totalCount ?></span></span>
        </div>
        <div class="card-body p-0">
        <?php if (empty($sportovci)): ?>
            <div class="p-3 text-muted">
                <i class="bi bi-info-circle me-2"></i>Žádní sportovci nenalezeni.
            </div>
        <?php else: ?>
            <form method="post" action="sportovci_hromadne.php">
            <?= csrf_field() ?>
            <div class="p-2 border-bottom bg-light d-flex gap-2 align-items-center flex-wrap">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check2-square me-1"></i>Hromadne akce
                </button>
                <span class="text-muted small">Vyberte cleny v tabulce a pokracujte na preview hromadne akce.</span>
            </div>
            <div class="table-wrap">
            <table class="table table-sm table-hover align-middle mb-0 sticky-head">
                <thead class="table-dark">
                    <tr>
                        <th>Vyber</th>
                        <th><?= sortLink('prijmeni', 'Příjmení') ?></th>
                        <th><?= sortLink('jmeno', 'Jméno') ?></th>
                        <th>Stav</th>
                        <th><?= sortLink('narozeni', 'Nar.') ?></th>
                        <th><?= sortLink('category', 'Kategorie') ?></th>
                        <th><?= sortLink('uciid', 'UCI ID') ?></th>
                        <th>Skupiny</th>
                        <th><?= sortLink('kis', 'KIS') ?></th>
                        <th class="text-end"><?= sortLink('treninku', 'Tréninků') ?></th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sportovciPage as $s): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input row-check" name="sportovec_ids[]" value="<?= (int)$s['id'] ?>" aria-label="Vybrat <?= h(trim($s['prijmeni'] . ' ' . $s['jmeno'])) ?>"></td>
                        <td class="fw-semibold"><a href="sportovec_karta.php?sportovec_id=<?= (int)$s['id'] ?>" title="Administrační karta člena"><?= h($s['prijmeni']) ?></a></td>
                        <td><?= h($s['jmeno']) ?></td>
                        <td><?= sportovecStatusBadge($s) ?></td>
                        <td class="text-nowrap"><?= $s['narozeni'] ? h($s['narozeni']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?php if ($s['category']): ?>
                            <span class="badge bg-info text-dark"><?= h($s['category']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $s['uciid'] ? h($s['uciid']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="small"><?= $s['skupiny'] ? h($s['skupiny']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="small">
                            <?php if (!empty($s['kis_aktivni'])): ?>
                                <span class="badge bg-success">aktivni</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">neaktivni</span>
                            <?php endif; ?>
                            <?php if ((float)($s['kis_neuhrazeno'] ?? 0) > 0): ?>
                                <span class="badge bg-warning text-dark"><?= number_format((float)$s['kis_neuhrazeno'], 0, ',', ' ') ?> Kc</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= (int)$s['pocet_treninku'] ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary edit-btn"
                                    data-id="<?= (int)$s['id'] ?>"
                                    data-jmeno="<?= h($s['jmeno']) ?>"
                                    data-prijmeni="<?= h($s['prijmeni']) ?>"
                                    data-narozeni="<?= h($s['narozeni'] ?? '') ?>"
                                    data-category="<?= h($s['category'] ?? '') ?>"
                                    data-uciid="<?= h($s['uciid'] ?? '') ?>"
                                    data-email="<?= h($s['email'] ?? '') ?>"
                                    data-oddil="<?= h($s['oddil'] ?? '') ?>"
                                    title="Upravit" aria-label="Upravit sportovce">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="sportovec_karta.php?sportovec_id=<?= (int)$s['id'] ?>"
                               class="btn btn-sm btn-outline-success" title="Administrační karta člena" aria-label="Administrační karta člena">
                                <i class="bi bi-person-vcard"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            </form>
            <?php if ($pocetStranek > 1): ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-2 border-top">
                <span class="text-muted small">
                    Zobrazeno <?= count($sportovciPage) ?> z <?= $totalCount ?>
                    (stránka <?= $page ?> / <?= $pocetStranek ?>)
                </span>
                <nav aria-label="Stránkování">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= h(spUrl(['p' => $page - 1])) ?>" aria-label="Předchozí stránka">&laquo;</a>
                        </li>
                        <?php
                        $od = max(1, $page - 3);
                        $do = min($pocetStranek, $page + 3);
                        for ($i = $od; $i <= $do; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= h(spUrl(['p' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $pocetStranek ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= h(spUrl(['p' => $page + 1])) ?>" aria-label="Další stránka">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Formulář pro editaci -->
    <div class="card section-card" id="edit-card">
        <div class="card-header bg-warning text-dark">
            <i class="bi bi-pencil-square me-1"></i><span id="form-title">Vyberte sportovce k úpravě</span>
        </div>
        <div class="card-body">
            <form method="POST" id="sportovec-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="akce" value="save">
                <input type="hidden" name="id" id="sp-id" value="0">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="sp-prijmeni" class="form-label req">Příjmení</label>
                        <input type="text" name="prijmeni" id="sp-prijmeni" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="sp-jmeno" class="form-label req">Jméno</label>
                        <input type="text" name="jmeno" id="sp-jmeno" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="sp-narozeni" class="form-label">Datum narození</label>
                        <input type="date" name="narozeni" id="sp-narozeni" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="sp-category" class="form-label">Kategorie</label>
                        <input type="text" name="category" id="sp-category" class="form-control"
                               placeholder="např. Elite, U23, masters…">
                    </div>
                    <div class="col-md-3">
                        <label for="sp-uciid" class="form-label">UCI ID</label>
                        <input type="text" name="uciid" id="sp-uciid" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="sp-email" class="form-label">Email</label>
                        <input type="email" name="email" id="sp-email" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="sp-oddil" class="form-label">Oddíl</label>
                        <input type="text" name="oddil" id="sp-oddil" class="form-control">
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary" id="btn-save" disabled>
                        <i class="bi bi-check-lg me-1"></i>Uložit změny
                    </button>
                    <button type="button" id="form-reset" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Zrušit
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>


<script>
// ── Edit tlačítko → vyplnit formulář ────────────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('form-title').innerText = 'Upravit: ' + btn.dataset.prijmeni + ' ' + btn.dataset.jmeno;
        document.getElementById('sp-id').value        = btn.dataset.id;
        document.getElementById('sp-prijmeni').value  = btn.dataset.prijmeni;
        document.getElementById('sp-jmeno').value     = btn.dataset.jmeno;
        document.getElementById('sp-narozeni').value   = btn.dataset.narozeni;
        document.getElementById('sp-category').value   = btn.dataset.category;
        document.getElementById('sp-uciid').value      = btn.dataset.uciid;
        document.getElementById('sp-email').value      = btn.dataset.email;
        document.getElementById('sp-oddil').value      = btn.dataset.oddil;
        document.getElementById('btn-save').disabled   = false;
        document.getElementById('edit-card').scrollIntoView({ behavior: 'smooth' });
    });
});

// ── Reset formuláře ─────────────────────────────────────────────────────────
document.getElementById('form-reset').addEventListener('click', () => {
    document.getElementById('form-title').innerText   = 'Vyberte sportovce k úpravě';
    document.getElementById('sp-id').value            = 0;
    document.getElementById('sp-prijmeni').value      = '';
    document.getElementById('sp-jmeno').value         = '';
    document.getElementById('sp-narozeni').value       = '';
    document.getElementById('sp-category').value       = '';
    document.getElementById('sp-uciid').value          = '';
    document.getElementById('sp-email').value          = '';
    document.getElementById('sp-oddil').value          = '';
    document.getElementById('btn-save').disabled       = true;
    document.getElementById('sportovec-form').classList.remove('was-validated');
});

// ── Validace při submitu ────────────────────────────────────────────────────
document.getElementById('sportovec-form').addEventListener('submit', function(e) {
    this.classList.add('was-validated');
    if (!this.checkValidity() || document.getElementById('sp-id').value === '0') {
        e.preventDefault();
        e.stopPropagation();
    }
});
const checkAll = document.getElementById('check-all');
if (checkAll) {
    checkAll.addEventListener('change', () => {
        document.querySelectorAll('.row-check').forEach(cb => { cb.checked = checkAll.checked; });
    });
}
</script>
</body>
</html>
