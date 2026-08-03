<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/csrf_helper.php';
$is_logged_in = isset($_SESSION['trener_id']);
if ($is_logged_in && file_exists(__DIR__ . '/includes/funkce.php')) {
    require_once __DIR__ . '/includes/funkce.php';
}
$is_hlavni    = $is_logged_in && function_exists('roleAtLeast') && roleAtLeast('hlavni');
$is_admin     = $is_logged_in && function_exists('roleAtLeast') && roleAtLeast('admin');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Evidence tréninků</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; }
    .section-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .section-card .card-header {
      border-radius: 12px 12px 0 0 !important;
      font-weight: 600;
      font-size: .95rem;
      letter-spacing: .02em;
      padding: .65rem 1rem;
    }
    .section-card .list-group-item {
      border-left: none;
      border-right: none;
      padding: .55rem 1rem;
      font-size: .92rem;
      display: flex;
      align-items: center;
      gap: .5rem;
      transition: background .12s;
    }
    .section-card .list-group-item:last-child { border-radius: 0 0 12px 12px; border-bottom: none; }
    .section-card .list-group-item:first-child { border-top: none; }
    .section-card .list-group-item i { font-size: 1.1rem; width: 1.4rem; text-align: center; flex-shrink: 0; }
    .section-card .list-group-item:hover { background: #f8f9fa; }
    .role-badge { font-size: .6rem; padding: .15rem .4rem; border-radius: 4px; font-weight: 500; letter-spacing: .02em; margin-left: auto; flex-shrink: 0; }
    .role-all   { background: #d1e7dd; color: #0f5132; }
    .role-mgr   { background: #cfe2ff; color: #084298; }
    .role-admin { background: #f8d7da; color: #842029; }
    .welcome-card { border: none; border-radius: 12px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%); color: #fff; }
    .welcome-card .badge-date { background: rgba(255,255,255,.15); font-weight: 400; font-size: .85rem; }
  </style>
</head>
<body>
  <?php include 'hlavicka.php'; ?>

  <div class="container py-4">

    <?php if (!$is_logged_in): ?>
      <!-- Nepřihlášený uživatel -->
      <div class="row justify-content-center mt-5">
        <div class="col-md-5">
          <div class="card section-card text-center p-4">
            <i class="bi bi-shield-lock fs-1 text-secondary mb-3"></i>
            <h4>Evidence tréninků</h4>
            <p class="text-muted">Pro přístup do aplikace se přihlaste.</p>
            <a href="login.php" class="btn btn-primary mt-2">
              <i class="bi bi-box-arrow-in-right me-1"></i>Přihlásit se
            </a>
          </div>
        </div>
      </div>

    <?php else: ?>

      <?php
        require_once 'db.php';
        $stmt = $pdo->prepare("SELECT jmeno FROM treneri WHERE id = ?");
        $stmt->execute([$_SESSION['trener_id']]);
        $trenerJmeno = (string)$stmt->fetchColumn();
        $dnes = new DateTime();
        $czDays = ['Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
                   'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'];
        $dayName = $czDays[$dnes->format('l')] ?? $dnes->format('l');

        // ── Dashboard statistiky ──
        $trenerId = (int)$_SESSION['trener_id'];
        $mesicStart = $dnes->format('Y-m-01');
        $mesicEnd   = $dnes->format('Y-m-t');

        try {
            // Tréninky tento měsíc
            $stQ = $pdo->prepare("
                SELECT COUNT(DISTINCT t.id) AS cnt, COALESCE(SUM(t.delka),0) AS hodiny
                FROM trenink_trener tt
                JOIN treninky t ON tt.trenink_id = t.id
                WHERE tt.trener_id = ? AND t.datum BETWEEN ? AND ?
            ");
            $stQ->execute([$trenerId, $mesicStart, $mesicEnd]);
            $statMesic = $stQ->fetch(PDO::FETCH_ASSOC);

            // Počet aktivních sportovců (s alespoň 1 tréninkem tento měsíc)
            $stQ2 = $pdo->prepare("
                SELECT COUNT(DISTINCT ts.sportovec_id) AS cnt
                FROM trenink_trener tt
                JOIN trenink_sportovec ts ON ts.trenink_id = tt.trenink_id
                JOIN treninky t ON t.id = tt.trenink_id
                WHERE tt.trener_id = ? AND t.datum BETWEEN ? AND ?
            ");
            $stQ2->execute([$trenerId, $mesicStart, $mesicEnd]);
            $statSportovci = (int)$stQ2->fetchColumn();

            // Poslední 3 tréninky
            $stQ3 = $pdo->prepare("
                SELECT t.id, t.datum, t.delka,
                       GROUP_CONCAT(DISTINCT sk.nazev ORDER BY sk.nazev SEPARATOR ', ') AS skupiny
                FROM trenink_trener tt
                JOIN treninky t ON tt.trenink_id = t.id
                LEFT JOIN trenink_skupina tsk ON tsk.trenink_id = t.id
                LEFT JOIN skupiny sk ON sk.id = tsk.skupina_id
                WHERE tt.trener_id = ?
                GROUP BY t.id
                ORDER BY t.datum DESC
                LIMIT 3
            ");
            $stQ3->execute([$trenerId]);
            $posledniTreninky = $stQ3->fetchAll(PDO::FETCH_ASSOC);

            // Plánované tréninky čekající na evidenci (moje nebo přístupné)
            try {
                $stQPlan = $pdo->prepare("
                    SELECT COUNT(*) FROM planovane_treninky
                    WHERE stav = 'planovany' AND datum >= CURDATE() AND trener_id = ?
                ");
                $stQPlan->execute([$trenerId]);
                $statPendingPlans = (int)$stQPlan->fetchColumn();
            } catch (Throwable $e) {
                $statPendingPlans = 0;
            }
        } catch (Throwable $e) {
            $statMesic = ['cnt' => 0, 'hodiny' => 0];
            $statSportovci = 0;
            $posledniTreninky = [];
            $statPendingPlans = 0;
        }

        // ── Vyžaduje pozornost ──
        $pozornost = [];
        try {
            // Nezaevidované plány z minulosti (posledních 14 dní — stejné okno jako upomínky)
            $stA = $pdo->prepare("
                SELECT COUNT(*) FROM planovane_treninky
                WHERE stav = 'planovany' AND trener_id = ?
                  AND datum < CURDATE() AND datum >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            ");
            $stA->execute([$trenerId]);
            $cntNezaevidovane = (int)$stA->fetchColumn();
            if ($cntNezaevidovane > 0) {
                $pozornost[] = [
                    'url'   => 'planovac.php',
                    'icon'  => 'bi-exclamation-triangle-fill',
                    'color' => '#dc3545',
                    'text'  => $cntNezaevidovane . ' ' . ($cntNezaevidovane === 1 ? 'proběhlý trénink není zaevidován' : ($cntNezaevidovane < 5 ? 'proběhlé tréninky nejsou zaevidovány' : 'proběhlých tréninků není zaevidováno')),
                    'sub'   => 'Otevřít plánovač a doplnit evidenci',
                ];
            }
        } catch (Throwable $e) { /* tabulka nemusí existovat */ }
        try {
            // Žluté rezervace čekající na potvrzení trenérem
            $stB = $pdo->prepare("
                SELECT COUNT(*) FROM verejne_rezervace vr
                JOIN individualni_lekce il ON il.id = vr.lekce_id
                WHERE vr.stav = 'ceka' AND il.trener_id = ?
            ");
            $stB->execute([$trenerId]);
            $cntCekajici = (int)$stB->fetchColumn();
            if ($cntCekajici > 0) {
                $pozornost[] = [
                    'url'   => 'individualni_lekce_sprava.php',
                    'icon'  => 'bi-hourglass-split',
                    'color' => '#fd7e14',
                    'text'  => $cntCekajici . ' ' . ($cntCekajici === 1 ? 'rezervace čeká na potvrzení' : ($cntCekajici < 5 ? 'rezervace čekají na potvrzení' : 'rezervací čeká na potvrzení')),
                    'sub'   => 'Potvrdit nebo zamítnout ve správě lekcí',
                ];
            }
        } catch (Throwable $e) { /* modul nemusí být aktivní */ }
        if ($is_hlavni) {
            try {
                $cntDluh = (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE kis_neuhrazeno > 0")->fetchColumn();
                if ($cntDluh > 0) {
                    $pozornost[] = [
                        'url'   => 'sprava_sportovcu.php?kis=dluh',
                        'icon'  => 'bi-cash-coin',
                        'color' => '#6f42c1',
                        'text'  => $cntDluh . ' ' . ($cntDluh === 1 ? 'člen má neuhrazenou platbu v KIS' : ($cntDluh < 5 ? 'členové mají neuhrazené platby v KIS' : 'členů má neuhrazené platby v KIS')),
                        'sub'   => 'Zobrazit dlužníky ve správě sportovců',
                    ];
                }
            } catch (Throwable $e) { /* kis sloupce nemusí existovat */ }
        }
      ?>

      <!-- Welcome banner -->
      <div class="welcome-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <div class="fw-semibold fs-5">Ahoj, <?= htmlspecialchars($trenerJmeno) ?> 👋</div>
            <div class="opacity-75 small"><?= $dayName ?>, <?= $dnes->format('j. n. Y') ?></div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="formular.php" class="btn btn-light btn-sm">
              <i class="bi bi-plus-lg me-1"></i>Nový trénink
            </a>
            <a href="planovac.php" class="btn btn-outline-light btn-sm">
              <i class="bi bi-calendar3-week me-1"></i>Plánovač
            </a>
            <a href="kalendar_sportovist.php" class="btn btn-outline-light btn-sm">
              <i class="bi bi-building me-1"></i>Kalendář
            </a>
            <a href="moje_treninky.php" class="btn btn-outline-light btn-sm">
              <i class="bi bi-list-ul me-1"></i>Moje tréninky
            </a>
          </div>
        </div>
      </div>

      <!-- Statistiky měsíce -->
      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="card section-card h-100">
            <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:42px;height:42px;background:#e8f0fe;">
                <i class="bi bi-calendar-check text-primary fs-5"></i>
              </div>
              <div>
                <div class="fw-bold fs-5 lh-1"><?= (int)($statMesic['cnt'] ?? 0) ?></div>
                <div class="text-muted" style="font-size:.78rem;">Tréninků tento měsíc</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card section-card h-100">
            <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:42px;height:42px;background:#e8f5e9;">
                <i class="bi bi-clock-history text-success fs-5"></i>
              </div>
              <div>
                <div class="fw-bold fs-5 lh-1"><?= number_format((float)($statMesic['hodiny'] ?? 0), 1, ',', '') ?></div>
                <div class="text-muted" style="font-size:.78rem;">Hodin tento měsíc</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card section-card h-100">
            <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:42px;height:42px;background:#fff3e0;">
                <i class="bi bi-people text-warning fs-5"></i>
              </div>
              <div>
                <div class="fw-bold fs-5 lh-1"><?= $statSportovci ?></div>
                <div class="text-muted" style="font-size:.78rem;">Aktivních sportovců</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card section-card h-100">
            <div class="card-body py-2 px-3">
              <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-clock text-secondary"></i>
                <span class="text-muted" style="font-size:.78rem;">Poslední tréninky</span>
              </div>
              <?php if (empty($posledniTreninky)): ?>
                <div class="text-muted small">Žádné tréninky</div>
              <?php else: ?>
                <?php foreach ($posledniTreninky as $pt):
                  $ptDt = new DateTime($pt['datum']);
                ?>
                <a href="edit_trenink.php?id=<?= (int)$pt['id'] ?>" class="d-flex justify-content-between text-decoration-none text-dark" style="font-size:.82rem;line-height:1.6;">
                  <span><?= htmlspecialchars($ptDt->format('j.n.')) ?> <span class="text-muted"><?= htmlspecialchars($pt['skupiny'] ?: '—') ?></span></span>
                  <span class="text-muted"><?= htmlspecialchars($pt['delka'] ?? '') ?>h</span>
                </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Vyžaduje pozornost -->
      <?php if (!empty($pozornost)): ?>
      <div class="mb-3">
        <?php foreach ($pozornost as $poz): ?>
        <a href="<?= htmlspecialchars($poz['url']) ?>" class="text-decoration-none d-block mb-2">
          <div class="d-flex align-items-center gap-3 px-4 py-2 rounded-3 shadow-sm bg-white border">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:34px;height:34px;background:<?= $poz['color'] ?>;">
              <i class="bi <?= $poz['icon'] ?> text-white" style="font-size:.95rem;"></i>
            </div>
            <div class="flex-grow-1">
              <span class="fw-semibold text-dark"><?= htmlspecialchars($poz['text']) ?></span>
              <span class="text-muted small ms-2 d-none d-md-inline"><?= htmlspecialchars($poz['sub']) ?></span>
            </div>
            <i class="bi bi-arrow-right-circle text-muted flex-shrink-0"></i>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Plánovač – alert banner (jen když jsou čekající plány) -->
      <?php if (function_exists('canAccess') && canAccess('planovac') && ($statPendingPlans ?? 0) > 0): ?>
      <a href="planovac.php" class="text-decoration-none d-block mb-3">
        <div class="d-flex align-items-center gap-3 px-4 py-3 rounded-3 shadow-sm"
             style="background:linear-gradient(90deg,#fffbeb,#fef3c7);border:1px solid #fde68a;">
          <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:38px;height:38px;background:#f59e0b;">
            <i class="bi bi-calendar3-week text-white" style="font-size:1.1rem;"></i>
          </div>
          <div class="flex-grow-1">
            <span class="fw-semibold text-dark">
              <?= $statPendingPlans ?> <?= $statPendingPlans === 1 ? 'plánovaný trénink čeká' : ($statPendingPlans < 5 ? 'plánované tréninky čekají' : 'plánovaných tréninků čeká') ?> na zadání evidence
            </span>
            <span class="text-muted small ms-2">Klikněte pro otevření plánovače</span>
          </div>
          <i class="bi bi-arrow-right-circle text-warning fs-5 flex-shrink-0"></i>
        </div>
      </a>
      <?php endif; ?>

      <!-- Legenda rolí -->
      <?php if ($is_hlavni): ?>
      <div class="d-flex gap-3 mb-3 flex-wrap align-items-center">
        <span class="small text-muted fw-semibold">Přístup:</span>
        <span class="role-badge role-all">všichni</span>
        <span class="role-badge role-mgr">správce</span>
        <?php if ($is_admin): ?>
        <span class="role-badge role-admin">admin</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="row g-3">

        <!-- LEVÝ SLOUPEC -->
        <div class="col-lg-4 col-md-6">

          <!-- Vkládání -->
          <div class="card section-card mb-3">
            <div class="card-header bg-primary text-white">
              <i class="bi bi-plus-square me-2"></i>Vkládání
            </div>
            <div class="list-group list-group-flush">
              <a href="formular.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar-plus text-primary"></i>Nový trénink
                <?= roleBadge('formular') ?></a>
              <a href="duplikovat_trenink.php" class="list-group-item list-group-item-action">
                <i class="bi bi-copy text-primary"></i>Duplikovat trénink
                <?= roleBadge('duplikovat_trenink') ?></a>
              <a href="nova_cinnost.php" class="list-group-item list-group-item-action">
                <i class="bi bi-journal-plus text-secondary"></i>Další činnost
                <?= roleBadge('nova_cinnost') ?></a>
              <?php if (canAccess('formular_zavod')): ?>
              <a href="formular_zavod.php" class="list-group-item list-group-item-action">
                <i class="bi bi-trophy text-warning"></i>Nový závod
                <?= roleBadge('formular_zavod') ?></a>
              <?php endif; ?>
              <a href="zatezovy_test_form.php" class="list-group-item list-group-item-action">
                <i class="bi bi-heart-pulse text-danger"></i>Nový zátěžový test
                <?= roleBadge('zatezovy_test') ?></a>
              <?php if (canAccess('planovac')): ?>
              <a href="planovac.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar3-week text-info"></i>Plánovač tréninků
                <?php if (($statPendingPlans ?? 0) > 0): ?>
                  <span class="badge bg-warning text-dark ms-auto"><?= $statPendingPlans ?> bez evidence</span>
                <?php else: ?>
                  <?= roleBadge('planovac') ?>
                <?php endif; ?>
              </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Přehledy -->
          <div class="card section-card">
            <div class="card-header bg-success text-white">
              <i class="bi bi-bar-chart me-2"></i>Přehledy
            </div>
            <div class="list-group list-group-flush">
              <a href="moje_treninky.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-ul text-success"></i>Moje tréninky
                <?= roleBadge('moje_treninky') ?></a>
              <a href="moje_skupiny.php" class="list-group-item list-group-item-action">
                <i class="bi bi-people text-success"></i>Moje skupiny
                <?= roleBadge('moje_skupiny') ?></a>
              <a href="prehled_trenera.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar-range text-success"></i>Přehled tréninků
                <?= roleBadge('prehled_trenera') ?></a>
              <a href="prehled_sportovcu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-person-lines-fill text-success"></i>Přehled sportovců
                <?= roleBadge('prehled_sportovcu') ?></a>
              <a href="prehled_treninku_skupiny_kalendar.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar3 text-success"></i>Kalendář tréninků
                <?= roleBadge('kalendar') ?></a>
              <a href="vypis_vykazu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-file-earmark-bar-graph text-secondary"></i>Výkaz činností
                <?= roleBadge('vypis_vykazu') ?></a>
              <a href="prehled_popisu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-journal-text text-success"></i>Přehled popisů tréninků
                <?= roleBadge('prehled_popisu') ?></a>
            </div>
          </div>

        </div>

        <!-- STŘEDNÍ SLOUPEC -->
        <div class="col-lg-4 col-md-6">

          <!-- Závodní sekce -->
          <div class="card section-card mb-3">
            <div class="card-header bg-warning text-dark">
              <i class="bi bi-trophy me-2"></i>Závodní sekce
            </div>
            <div class="list-group list-group-flush">
              <a href="prehled_zavodu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-trophy text-warning"></i>Přehled závodů
                <?= roleBadge('prehled_zavodu') ?></a>
              <?php if (canAccess('formular_zavod')): ?>
              <a href="formular_zavod.php" class="list-group-item list-group-item-action">
                <i class="bi bi-plus-circle text-success"></i>Nový závod
                <?= roleBadge('formular_zavod') ?></a>
              <?php endif; ?>
              <?php if (canAccess('sprava_zavodu')): ?>
              <a href="sprava_zavodu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-gear text-warning"></i>Správa závodů
                <?= roleBadge('sprava_zavodu') ?></a>
              <?php endif; ?>
              <a href="hromadne_podskupiny.php" class="list-group-item list-group-item-action">
                <i class="bi bi-diagram-3 text-warning"></i>Skupiny a podskupiny
                <?= roleBadge('skupiny_podskupiny') ?></a>
              <a href="export_draha.php" class="list-group-item list-group-item-action">
                <i class="bi bi-file-earmark-excel text-success"></i>Export – dráha
                <?= roleBadge('exporty') ?></a>
              <a href="export_uci.php" class="list-group-item list-group-item-action">
                <i class="bi bi-file-earmark-person text-primary"></i>Export – UCI přihláška
                <?= roleBadge('exporty') ?></a>
              <a href="export_seznam.php" class="list-group-item list-group-item-action">
                <i class="bi bi-file-earmark-spreadsheet text-success"></i>Export – Seznam sportovců
                <?= roleBadge('exporty') ?></a>
              <a href="google_sheets_linky.php" class="list-group-item list-group-item-action">
                <i class="bi bi-table text-success"></i>Google Sheets evidence
                <?= roleBadge('google_sheets') ?></a>
              <a href="oznameni.php" class="list-group-item list-group-item-action">
                <i class="bi bi-bell text-warning"></i>Oznámení a zprávy
                <?= roleBadge('oznameni') ?></a>
            </div>
          </div>

          <!-- Rezervace sportovišť — vždy viditelné (veřejný kalendář sdílí každý trenér) -->
          <?php
            $bookingUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                          . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/booking/kalendar.php';
          ?>
          <div class="card section-card mb-3">
            <div class="card-header bg-info text-white">
              <i class="bi bi-building me-2"></i>Rezervace sportovišť
            </div>
            <div class="list-group list-group-flush">
              <?php if (canAccess('kalendar_sportovist')): ?>
              <a href="kalendar_sportovist.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar3 text-info"></i>Kalendář sportovišť
                <?= roleBadge('kalendar_sportovist') ?></a>
              <?php endif; ?>
              <?php if (canAccess('rezervace_sportovist')): ?>
              <a href="rezervovat_sportoviste.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar-plus text-success"></i>Nová rezervace
                <?= roleBadge('rezervace_sportovist') ?></a>
              <?php endif; ?>
              <?php if (canAccess('individualni_lekce')): ?>
              <a href="individualni_lekce_sprava.php" class="list-group-item list-group-item-action">
                <i class="bi bi-person-circle text-success"></i>Individuální lekce
                <?= roleBadge('individualni_lekce') ?></a>
              <a href="individualni_lekce_form.php" class="list-group-item list-group-item-action">
                <i class="bi bi-person-plus text-success"></i>Nová lekce
                <?= roleBadge('individualni_lekce') ?></a>
              <?php endif; ?>
              <a href="booking/kalendar.php" class="list-group-item list-group-item-action" target="_blank">
                <i class="bi bi-globe text-primary"></i>Veřejný kalendář rezervací
                <span class="badge bg-primary ms-auto">veřejné</span>
              </a>
              <div class="list-group-item py-2 px-3" style="background:#f0f9ff;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <i class="bi bi-link-45deg text-info" style="font-size:.95rem;"></i>
                  <span class="text-muted" style="font-size:.75rem;">Odkaz pro zákazníky:</span>
                  <code id="bookingUrlCode" class="small text-break" style="font-size:.73rem;"><?= htmlspecialchars($bookingUrl) ?></code>
                  <button type="button" class="btn btn-outline-secondary btn-sm ms-auto py-0"
                          style="font-size:.7rem;"
                          onclick="navigator.clipboard.writeText(<?= json_encode($bookingUrl) ?>).then(()=>{this.innerHTML='<i class=\'bi bi-check-lg\'></i> Zkopírováno';setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i> Kopírovat';},2000)})">
                    <i class="bi bi-clipboard"></i> Kopírovat
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Nastavení -->
          <div class="card section-card">
            <div class="card-header bg-secondary text-white">
              <i class="bi bi-gear me-2"></i>Nastavení
            </div>
            <div class="list-group list-group-flush">
              <a href="cviky.php" class="list-group-item list-group-item-action">
                <i class="bi bi-activity text-secondary"></i>Cviky v posilovně
                <?= roleBadge('cviky') ?></a>
              <a href="sprava_segmentu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-signpost-split text-secondary"></i>Segmenty
                <?= roleBadge('segmenty') ?></a>
              <a href="hromadne_odmeny.php" class="list-group-item list-group-item-action">
                <i class="bi bi-star text-warning"></i>Nastavení odměn
                <?= roleBadge('odmeny') ?></a>
            </div>
          </div>

        </div>

        <!-- PRAVÝ SLOUPEC -->
        <div class="col-lg-4 col-md-12">

          <?php if ($is_hlavni): ?>
          <!-- Správa dat (správce + admin) -->
          <div class="card section-card mb-3">
            <div class="card-header bg-primary text-white">
              <i class="bi bi-key me-2"></i>Správa dat
            </div>
            <div class="list-group list-group-flush">
              <a href="sprava_sportovcu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-person-lines-fill text-primary"></i>Správa sportovců
                <?= roleBadge('sprava_sportovcu') ?></a>
              <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                <i class="bi bi-speedometer2 text-primary"></i>Admin dashboard
                <?= roleBadge('sprava_sportovcu') ?></a>
              <a href="sprava_vsech_treninku.php" class="list-group-item list-group-item-action">
                <i class="bi bi-clipboard-data text-primary"></i>Správa tréninků
                <?= roleBadge('sprava_treninku') ?></a>
              <a href="sprava_skupin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-diagram-2 text-primary"></i>Správa skupin
                <?= roleBadge('sprava_skupin') ?></a>
              <a href="sprava_podskupin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-diagram-3 text-primary"></i>Správa podskupin
                <?= roleBadge('sprava_podskupin') ?></a>
              <a href="verejny_prehled.php" class="list-group-item list-group-item-action">
                <i class="bi bi-globe text-primary"></i>Veřejný přehled
                <?= roleBadge('verejny_prehled') ?></a>
              <a href="prehled_vsech_vykazu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-graph-up text-secondary"></i>Všechny výkazy
                <?= roleBadge('vsechny_vykazy') ?></a>
              <a href="odeslat_emaily.php" class="list-group-item list-group-item-action">
                <i class="bi bi-envelope-fill text-primary"></i>Odeslat emaily
                <?= roleBadge('odeslat_emaily') ?></a>
              <a href="prehled_kreditu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-wallet2 text-success"></i>Přehled kreditů
                <?= roleBadge('prehled_kreditu') ?></a>
              <a href="sprava_sportovec_obdobi.php" class="list-group-item list-group-item-action">
                <i class="bi bi-cash-coin text-success"></i>Kreditní období
                <?= roleBadge('kreditni_obdobi') ?></a>
              <a href="sync_evidence.php" class="list-group-item list-group-item-action">
                <i class="bi bi-arrow-repeat text-primary"></i>Synchronizace evidence
                <?= roleBadge('sync_evidence') ?></a>
              <a href="kis_sync_center.php" class="list-group-item list-group-item-action">
                <i class="bi bi-diagram-3 text-primary"></i>KIS centrum
                <?= roleBadge('sync_evidence') ?></a>
              <a href="club_programs_admin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar-range text-primary"></i>Kroužkové programy
                <?= roleBadge('sync_evidence') ?></a>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($is_admin): ?>
          <!-- Administrace (pouze admin) -->
          <div class="card section-card mb-3">
            <div class="card-header bg-danger text-white">
              <i class="bi bi-shield-lock me-2"></i>Administrace
            </div>
            <div class="list-group list-group-flush">
              <a href="eshop_admin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-shop text-primary"></i>Administrace e-shopu
                <span class="role-badge role-admin">admin</span></a>
              <a href="eshop_catalog_publication_admin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-eye text-success"></i>Aktivace katalogu
                <span class="role-badge role-admin">admin</span></a>
              <a href="eshop_events_admin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar-event text-primary"></i>Klubové akce a soupisky
                <span class="role-badge role-admin">admin</span></a>
              <a href="eshop_identity_admin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-people text-primary"></i>Účty, rodiče a sportovci
                <span class="role-badge role-admin">admin</span></a>
              <a href="sprava_sportovist.php" class="list-group-item list-group-item-action">
                <i class="bi bi-building-gear text-danger"></i>Správa sportovišť
                <?= roleBadge('sprava_sportovist') ?></a>
              <a href="sprava_treneru.php" class="list-group-item list-group-item-action">
                <i class="bi bi-person-gear text-danger"></i>Správa trenérů
                <span class="role-badge role-admin">admin</span></a>
              <a href="nastaveni_opravneni.php" class="list-group-item list-group-item-action">
                <i class="bi bi-sliders text-danger"></i>Nastavení oprávnění
                <span class="role-badge role-admin">admin</span></a>
              <a href="nastaveni_zadavani.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar-lock text-warning"></i>Okno pro zadávání
                <?= roleBadge('nastaveni_zadavani') ?></a>
              <a href="vozidla/seznam.php" class="list-group-item list-group-item-action">
                <i class="bi bi-car-front text-secondary"></i>Vozidla
                <?= roleBadge('vozidla') ?></a>
              <a href="uctenky/seznam.php" class="list-group-item list-group-item-action">
                <i class="bi bi-receipt text-secondary"></i>Účtenky
                <?= roleBadge('uctenky') ?></a>
              <a href="udalosti/seznam.php" class="list-group-item list-group-item-action">
                <i class="bi bi-calendar-event text-secondary"></i>Události
                <?= roleBadge('udalosti') ?></a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Odhlášení -->
          <div class="card section-card">
            <div class="list-group list-group-flush">
              <form method="post" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="list-group-item list-group-item-action text-danger border-0 text-start w-100">
                  <i class="bi bi-box-arrow-right text-danger"></i>Odhlásit se
                </button>
              </form>
            </div>
          </div>

        </div>
      </div><!-- /row -->

    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
