<?php
$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/app_url.php';
$is_logged_in = isset($_SESSION['trener_id']);
$is_customer  = isset($_SESSION['verejny_uzivatel_id']);
$is_athlete   = isset($_SESSION['sportovec_pristup_id']);
$customerName = trim((string)($_SESSION['verejny_uzivatel_jmeno'] ?? ''));
$athleteName  = trim((string)($_SESSION['sportovec_pristup_jmeno'] ?? ''));
$shopUrl      = 'booking/eshop.php';
$familyUrl    = $is_customer ? 'booking/moje_osoby.php' : 'booking/prihlaseni.php?redirect=moje_osoby.php';
$athleteUrl   = $is_athlete ? 'booking/muj_sport.php' : 'booking/sportovec_prihlaseni.php';
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
  <title><?= $is_logged_in ? 'Evidence tréninků' : 'Kovopraha – klubový portál' ?></title>
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
    .portal-hero { border: 0; border-radius: 20px; color: #fff; overflow: hidden; background: linear-gradient(135deg,#12372a 0%,#1f6f50 55%,#c46a2d 100%); box-shadow: 0 18px 45px rgba(18,55,42,.2); }
    .portal-hero .hero-kicker { text-transform: uppercase; letter-spacing: .12em; font-size: .75rem; font-weight: 700; opacity: .78; }
    .portal-card { border: 0; border-radius: 16px; box-shadow: 0 8px 26px rgba(18,55,42,.09); transition: transform .15s ease, box-shadow .15s ease; }
    .portal-card:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(18,55,42,.14); }
    .portal-icon { width: 52px; height: 52px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.45rem; }
    .portal-link { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .62rem 0; color: inherit; text-decoration: none; border-top: 1px solid rgba(0,0,0,.08); }
    .portal-link:hover { color: #146c43; }
    .portal-shortcut { display: block; height: 100%; padding: 1rem; border-radius: 14px; color: inherit; text-decoration: none; background: #fff; box-shadow: 0 3px 14px rgba(0,0,0,.07); }
    .portal-shortcut:hover { color: #0d6efd; box-shadow: 0 6px 20px rgba(0,0,0,.11); }
  </style>
</head>
<body>
  <?php include 'hlavicka.php'; ?>

  <div class="container py-4">

    <?php if (!$is_logged_in): ?>
      <section class="portal-hero card mb-4">
        <div class="card-body p-4 p-lg-5">
          <div class="row align-items-center g-4">
            <div class="col-lg-8">
              <div class="hero-kicker mb-2">TJ Kovo Praha · jeden klubový portál</div>
              <h1 class="display-6 fw-bold mb-3">Nakupujte, přihlašujte děti a sledujte sport na jednom místě.</h1>
              <p class="lead mb-4 opacity-75">E-shop, kroužky, klubové události, velodrom a sportovní přehled jsou propojené se stejnými osobami a objednávkami.</p>
              <div class="d-flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars($shopUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-light btn-lg"><i class="bi bi-bag me-2"></i>Nakoupit v e-shopu</a>
                <a href="booking/velodrom.php" class="btn btn-outline-light btn-lg"><i class="bi bi-bicycle me-2"></i>Rezervovat velodrom</a>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="p-3 p-lg-4 rounded-4" style="background:rgba(255,255,255,.13)">
                <?php if ($is_customer): ?>
                  <div class="small opacity-75">Přihlášený rodinný účet</div>
                  <div class="fw-bold fs-5 mb-3"><?= htmlspecialchars($customerName !== '' ? $customerName : 'Můj účet', ENT_QUOTES, 'UTF-8') ?></div>
                  <a href="booking/moje_objednavky.php" class="btn btn-light w-100 mb-2">Moje objednávky</a>
                  <a href="booking/sportovni_prehled.php" class="btn btn-outline-light w-100">Sportovní přehled</a>
                <?php elseif ($is_athlete): ?>
                  <div class="small opacity-75">Přihlášený sportovec</div>
                  <div class="fw-bold fs-5 mb-3"><?= htmlspecialchars($athleteName !== '' ? $athleteName : 'Můj sport', ENT_QUOTES, 'UTF-8') ?></div>
                  <a href="booking/muj_sport.php" class="btn btn-light w-100">Otevřít Můj sport</a>
                <?php else: ?>
                  <div class="fw-semibold mb-2">Už u nás máte účet?</div>
                  <a href="booking/prihlaseni.php" class="btn btn-light w-100 mb-2">Přihlášení rodiče / zákazníka</a>
                  <a href="booking/sportovec_prihlaseni.php" class="btn btn-outline-light w-100">Přihlášení sportovce</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section aria-labelledby="portal-options-title">
        <div class="d-flex justify-content-between align-items-end mb-3">
          <div><h2 id="portal-options-title" class="h3 mb-1">Co potřebujete zařídit?</h2><p class="text-muted mb-0">Vyberte nejbližší cestu; není potřeba znát strukturu systému.</p></div>
        </div>
        <div class="row g-3">
          <div class="col-lg-4">
            <article class="card portal-card h-100"><div class="card-body p-4">
              <span class="portal-icon bg-success-subtle text-success mb-3"><i class="bi bi-bag-check"></i></span>
              <h3 class="h5">E-shop a klubové služby</h3>
              <p class="text-muted">Oblečení, knihy, kroužky, kurzy, výjezdy a placené rezervace.</p>
              <a class="portal-link" href="<?= htmlspecialchars($shopUrl, ENT_QUOTES, 'UTF-8') ?>"><span>E-shop</span><i class="bi bi-arrow-right"></i></a>
              <a class="portal-link" href="booking/krouzky.php"><span>Kroužky a události</span><i class="bi bi-arrow-right"></i></a>
              <a class="portal-link" href="booking/velodrom.php"><span>Hodiny velodromu</span><i class="bi bi-arrow-right"></i></a>
              <a class="portal-link" href="booking/treninky.php"><span>Rozvrh tréninků</span><i class="bi bi-arrow-right"></i></a>
            </div></article>
          </div>
          <div class="col-lg-4">
            <article class="card portal-card h-100"><div class="card-body p-4">
              <span class="portal-icon bg-primary-subtle text-primary mb-3"><i class="bi bi-people"></i></span>
              <h3 class="h5">Rodina a sportovec</h3>
              <p class="text-muted">Jeden rodičovský účet pro děti, platby, přihlášky, soupisky a tréninky.</p>
              <a class="portal-link" href="<?= htmlspecialchars($familyUrl, ENT_QUOTES, 'UTF-8') ?>"><span><?= $is_customer ? 'Moje osoby' : 'Přihlášení rodiče' ?></span><i class="bi bi-arrow-right"></i></a>
              <a class="portal-link" href="<?= htmlspecialchars($athleteUrl, ENT_QUOTES, 'UTF-8') ?>"><span><?= $is_athlete ? 'Můj sport' : 'Přihlášení sportovce' ?></span><i class="bi bi-arrow-right"></i></a>
              <?php if (!$is_customer && !$is_athlete): ?><a class="portal-link" href="booking/registrace.php"><span>Vytvořit účet</span><i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </div></article>
          </div>
          <div class="col-lg-4">
            <article class="card portal-card h-100"><div class="card-body p-4">
              <span class="portal-icon bg-warning-subtle text-warning-emphasis mb-3"><i class="bi bi-clipboard-data"></i></span>
              <h3 class="h5">Trenéři a vedení klubu</h3>
              <p class="text-muted">Evidence tréninků, plánovač, KIS soupisky, závody, události a správa objednávek.</p>
              <a class="portal-link" href="login.php"><span>Vstup pro trenéry</span><i class="bi bi-arrow-right"></i></a>
              <div class="small text-muted mt-3"><i class="bi bi-shield-check me-1"></i>Jeden účet používá podle oprávnění e-shop i trenérskou Evidenci.</div>
            </div></article>
          </div>
        </div>
      </section>

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
            <h1 class="fw-semibold fs-5 mb-0">Ahoj, <?= htmlspecialchars($trenerJmeno) ?> 👋</h1>
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

      <section class="mb-4" aria-labelledby="staff-shortcuts-title">
        <h2 id="staff-shortcuts-title" class="h5 mb-3">Co chcete udělat?</h2>
        <div class="row g-3">
          <div class="col-6 col-xl-3"><a class="portal-shortcut" href="formular.php"><i class="bi bi-plus-square text-primary fs-4"></i><div class="fw-semibold mt-2">Zadat trénink</div><div class="small text-muted">Evidence a účast</div></a></div>
          <div class="col-6 col-xl-3"><a class="portal-shortcut" href="<?= $is_hlavni ? 'kis_rosters_admin.php' : 'moje_skupiny.php' ?>"><i class="bi bi-people-fill text-success fs-4"></i><div class="fw-semibold mt-2">KIS a soupisky</div><div class="small text-muted"><?= $is_hlavni ? 'Týmy, členství a události' : 'Moje skupiny' ?></div></a></div>
          <div class="col-6 col-xl-3"><a class="portal-shortcut" href="<?= $is_admin ? 'eshop_orders_admin.php' : 'booking/prihlaseni.php?redirect=eshop.php' ?>"><i class="bi bi-receipt text-warning fs-4"></i><div class="fw-semibold mt-2"><?= $is_admin ? 'Objednávky' : 'E-shop' ?></div><div class="small text-muted"><?= $is_admin ? 'Platby, výdej a storna' : 'Klubová nabídka' ?></div></a></div>
          <div class="col-6 col-xl-3"><a class="portal-shortcut" href="booking/eshop.php"><i class="bi bi-globe text-info fs-4"></i><div class="fw-semibold mt-2">Veřejný portál</div><div class="small text-muted">Pohled rodiče a zákazníka</div></a></div>
        </div>
      </section>

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

      <!-- Rychlý přístup — plný výpis funkcí je v menu nahoře (Vložit / Přehledy /
           Plánovač / Rezervace / Klub / Administrace), nástěnka už ho neduplikuje -->
      <?php $bookingUrl = appUrl('booking/kalendar.php'); ?>
      <section class="mb-4">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card section-card h-100">
              <div class="card-header bg-info text-white">
                <i class="bi bi-building me-2"></i>Odkaz pro zákazníky
              </div>
              <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <a href="booking/kalendar.php" target="_blank" class="small">
                    <i class="bi bi-globe me-1"></i>Veřejný kalendář rezervací</a>
                  <code id="bookingUrlCode" class="small text-break ms-2"><?= htmlspecialchars($bookingUrl) ?></code>
                  <button type="button" class="btn btn-outline-secondary btn-sm ms-auto py-0"
                          data-copy-text="<?= htmlspecialchars($bookingUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <i class="bi bi-clipboard" aria-hidden="true"></i> <span class="app-copy-label">Kopírovat</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <?php if ($is_hlavni): ?>
          <div class="col-6 col-lg-3">
            <a class="portal-shortcut" href="sprava_sportovcu.php">
              <i class="bi bi-diagram-3 text-primary fs-4"></i>
              <div class="fw-semibold mt-2">Otevřít Klub</div>
              <div class="small text-muted">Provoz, členové a KIS — i v menu nahoře</div>
            </a>
          </div>
          <?php endif; ?>
          <?php if ($is_admin): ?>
          <div class="col-6 col-lg-3">
            <a class="portal-shortcut" href="eshop_admin.php">
              <i class="bi bi-shield-lock text-danger fs-4"></i>
              <div class="fw-semibold mt-2">Otevřít Administraci</div>
              <div class="small text-muted">E-shop, nastavení — i v menu nahoře</div>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </section>

    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
