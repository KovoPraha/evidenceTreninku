<?php
declare(strict_types=1);

require_once __DIR__ . '/app_url.php';
require_once dirname(__DIR__) . '/csrf_helper.php';

function appUiBasePath(): string
{
    $path = (string)(parse_url(appCanonicalBaseUrl(), PHP_URL_PATH) ?: '');
    return rtrim('/' . ltrim($path, '/'), '/');
}

function appUiUrl(string $path): string
{
    return appUiBasePath() . '/' . ltrim($path, '/');
}

function appUiAssets(): void
{
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    $root = dirname(__DIR__);
    $bootstrapCssVersion = (string)(filemtime($root . '/assets/vendor/bootstrap/bootstrap.min.css') ?: '1');
    $bootstrapJsVersion = (string)(filemtime($root . '/assets/vendor/bootstrap/bootstrap.bundle.min.js') ?: '1');
    $iconsVersion = (string)(filemtime($root . '/assets/vendor/bootstrap-icons/font/bootstrap-icons.css') ?: '1');
    $cssVersion = (string)(filemtime($root . '/assets/app-ui.css') ?: '1');
    $jsVersion = (string)(filemtime($root . '/assets/app-ui.js') ?: '1');
    $bootstrapCss = htmlspecialchars(appUiUrl('assets/vendor/bootstrap/bootstrap.min.css') . '?v=' . rawurlencode($bootstrapCssVersion), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $bootstrapJs = htmlspecialchars(appUiUrl('assets/vendor/bootstrap/bootstrap.bundle.min.js') . '?v=' . rawurlencode($bootstrapJsVersion), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $icons = htmlspecialchars(appUiUrl('assets/vendor/bootstrap-icons/font/bootstrap-icons.css') . '?v=' . rawurlencode($iconsVersion), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $css = htmlspecialchars(appUiUrl('assets/app-ui.css') . '?v=' . rawurlencode($cssVersion), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $js = htmlspecialchars(appUiUrl('assets/app-ui.js') . '?v=' . rawurlencode($jsVersion), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<link href="' . $bootstrapCss . '" rel="stylesheet">';
    echo '<link href="' . $icons . '" rel="stylesheet">';
    echo '<link href="' . $css . '" rel="stylesheet">';
    echo '<script src="' . $bootstrapJs . '"></script>';
    echo '<script defer src="' . $js . '"></script>';
}

function publicShellH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function publicShellNav(string $active = ''): void
{
    $customer = isset($_SESSION['verejny_uzivatel_id']);
    $athlete = isset($_SESSION['sportovec_pristup_id']);
    $trainer = isset($_SESSION['trener_id']);
    $items = [
        'home' => ['Domů', 'index.php'],
        'programs' => ['Kroužky', 'booking/cyklisticke_krouzky.php'],
        'shop' => ['E-shop', 'booking/eshop.php'],
        'training' => ['Tréninky', 'booking/treninky.php'],
        'clubs' => ['Akce', 'booking/krouzky.php'],
        'calendar' => ['Kalendář klubových akcí', 'booking/klubovy_kalendar.php'],
        'lessons' => ['Individuální lekce', 'booking/kalendar.php'],
        'velodrome' => ['Velodrom', 'booking/velodrom.php'],
    ];
    ?>
    <nav class="app-public-nav navbar navbar-expand-xl bg-white border-bottom shadow-sm" aria-label="Klubový portál">
      <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= publicShellH(appUiUrl('index.php')) ?>"><i class="bi bi-bicycle me-2 text-primary"></i>Kovopraha</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appPublicNav"
                aria-controls="appPublicNav" aria-expanded="false" aria-label="Otevřít hlavní nabídku">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="appPublicNav">
          <ul class="navbar-nav me-auto mb-2 mb-xl-0">
            <?php foreach ($items as $key => [$label, $href]): ?>
              <li class="nav-item"><a class="nav-link<?= $active === $key ? ' active fw-semibold' : '' ?>" href="<?= publicShellH(appUiUrl($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= publicShellH($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
          <div class="d-flex flex-column flex-xl-row align-items-stretch align-items-xl-center gap-2 ms-xl-2 pb-2 pb-xl-0">
            <?php if ($customer): ?>
              <a class="btn btn-outline-primary btn-sm" href="<?= publicShellH(appUiUrl('booking/sportovni_prehled.php')) ?>"><i class="bi bi-person-heart me-1"></i>Můj přehled</a>
              <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-1"></i>Můj účet</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="<?= publicShellH(appUiUrl('booking/verejny_profil.php')) ?>">Profil</a></li>
                  <li><a class="dropdown-item" href="<?= publicShellH(appUiUrl('booking/moje_osoby.php')) ?>">Moje osoby</a></li>
                  <li><a class="dropdown-item" href="<?= publicShellH(appUiUrl('booking/moje_programy.php')) ?>">Moje kroužky</a></li>
                  <li><a class="dropdown-item" href="<?= publicShellH(appUiUrl('booking/moje_rezervace.php')) ?>">Rezervace</a></li>
                  <li><a class="dropdown-item" href="<?= publicShellH(appUiUrl('booking/moje_objednavky.php')) ?>">Objednávky</a></li>
                </ul>
              </div>
            <?php endif; ?>
            <?php if ($athlete): ?>
              <a class="btn btn-outline-primary btn-sm" href="<?= publicShellH(appUiUrl('booking/muj_sport.php')) ?>"><i class="bi bi-activity me-1"></i>Můj sport</a>
            <?php endif; ?>
            <?php if ($trainer): ?>
              <a class="btn btn-outline-primary btn-sm" href="<?= publicShellH(appUiUrl('index.php')) ?>"><i class="bi bi-speedometer2 me-1"></i>Evidence</a>
            <?php endif; ?>
            <?php if (!$customer && !$athlete && !$trainer): ?>
              <a class="btn btn-primary btn-sm" href="<?= publicShellH(appUiUrl('booking/prihlaseni.php')) ?>">Přihlásit se</a>
            <?php elseif ($athlete && !$customer && !$trainer): ?>
              <form method="post" action="<?= publicShellH(appUiUrl('booking/sportovec_odhlaseni.php')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm w-100">Odhlásit</button></form>
            <?php else: ?>
              <form method="post" action="<?= publicShellH(appUiUrl('booking/odhlaseni.php')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm w-100">Odhlásit</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </nav>
    <?php
}

function publicShellFooter(): void
{
    ?>
    <footer class="app-public-footer border-top mt-5 py-4">
      <div class="container d-flex flex-wrap justify-content-between gap-2 small text-muted">
        <span>Klubový portál Kovopraha</span>
        <span><a href="<?= publicShellH(appUiUrl('index.php')) ?>">Domů</a> · <a href="<?= publicShellH(appUiUrl('booking/cyklisticke_krouzky.php')) ?>">Kroužky</a> · <a href="<?= publicShellH(appUiUrl('booking/kalendar.php')) ?>">Individuální lekce</a> · <a href="<?= publicShellH(appUiUrl('booking/treninky.php')) ?>">Veřejné tréninky</a> · <a href="<?= publicShellH(appUiUrl('booking/eshop.php')) ?>">E-shop</a></span>
      </div>
    </footer>
    <?php
}
