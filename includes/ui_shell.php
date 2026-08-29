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
        'shop' => ['E-shop', 'eshop.php'],
        'training' => ['Tréninky', 'treninky.php'],
        'clubs' => ['Kroužky a akce', 'krouzky.php'],
        'calendar' => ['Klubový kalendář', 'klubovy_kalendar.php'],
        'velodrome' => ['Velodrom', 'velodrom.php'],
    ];
    ?>
    <nav class="app-public-nav bg-white border-bottom shadow-sm" aria-label="Klubový portál">
      <div class="container d-flex flex-wrap align-items-center gap-3 py-2">
        <a class="navbar-brand fw-semibold" href="../index.php"><i class="bi bi-bicycle me-2 text-primary"></i>Kovopraha</a>
        <div class="d-flex flex-wrap align-items-center gap-1 flex-grow-1">
          <ul class="navbar-nav flex-row flex-wrap me-auto gap-1">
            <?php foreach ($items as $key => [$label, $href]): ?>
              <li class="nav-item"><a class="nav-link<?= $active === $key ? ' active fw-semibold' : '' ?>" href="<?= publicShellH($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= publicShellH($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
          <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
            <?php if ($customer): ?>
              <a class="btn btn-outline-primary btn-sm" href="sportovni_prehled.php"><i class="bi bi-person-heart me-1"></i>Můj přehled</a>
              <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-1"></i>Můj účet</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="verejny_profil.php">Profil</a></li>
                  <li><a class="dropdown-item" href="moje_osoby.php">Moje osoby</a></li>
                  <li><a class="dropdown-item" href="moje_programy.php">Moje kroužky</a></li>
                  <li><a class="dropdown-item" href="moje_rezervace.php">Rezervace</a></li>
                  <li><a class="dropdown-item" href="moje_objednavky.php">Objednávky</a></li>
                </ul>
              </div>
            <?php endif; ?>
            <?php if ($athlete): ?>
              <a class="btn btn-outline-primary btn-sm" href="muj_sport.php"><i class="bi bi-activity me-1"></i>Můj sport</a>
            <?php endif; ?>
            <?php if ($trainer): ?>
              <a class="btn btn-outline-primary btn-sm" href="../index.php"><i class="bi bi-speedometer2 me-1"></i>Evidence</a>
            <?php endif; ?>
            <?php if (!$customer && !$athlete && !$trainer): ?>
              <a class="btn btn-primary btn-sm" href="prihlaseni.php">Přihlásit se</a>
            <?php elseif ($athlete && !$customer && !$trainer): ?>
              <form method="post" action="sportovec_odhlaseni.php" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Odhlásit</button></form>
            <?php else: ?>
              <form method="post" action="odhlaseni.php" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Odhlásit</button></form>
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
        <span><a href="../index.php">Domů</a> · <a href="treninky.php">Veřejné tréninky</a> · <a href="eshop.php">E-shop</a></span>
      </div>
    </footer>
    <?php
}
