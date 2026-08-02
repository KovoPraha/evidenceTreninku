<?php
require_once __DIR__ . '/includes/session_security.php';
if (session_status() === PHP_SESSION_NONE) app_session_start();
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/includes/funkce.php')) {
    require_once __DIR__ . '/includes/funkce.php';
}
if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/csrf_helper.php';
}

$is_logged_in = isset($_SESSION['trener_id']);
$is_hlavni    = $is_logged_in && roleAtLeast('hlavni');  // správce nebo admin
$is_admin     = $is_logged_in && roleAtLeast('admin');   // pouze admin

// Active page detection for navbar highlighting
$_currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
function _navActive(string ...$pages): string {
    global $_currentPage;
    return in_array($_currentPage, $pages, true) ? ' active' : '';
}
function _dropActive(string ...$pages): string {
    global $_currentPage;
    return in_array($_currentPage, $pages, true) ? ' active fw-semibold' : '';
}

$trener_jmeno = '';
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT jmeno FROM treneri WHERE id = ?");
    $stmt->execute([$_SESSION['trener_id']]);
    $trener_jmeno = (string)$stmt->fetchColumn();
}
?>
<!-- Dark mode: nastav téma co nejdříve, aby nedošlo k bliknutí -->
<script>
(function(){
    var t = localStorage.getItem('bs-theme')
         || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .navbar .nav-link.active { color: #fff !important; border-bottom: 2px solid #0d6efd; }
    .dropdown-item.active { background-color: #e8f0fe !important; color: #0d6efd !important; }

    /* Povinná pole — vizuální indikátory */
    .form-label.req::after { content: " *"; color: #dc3545; font-weight: 700; }
    input[required], select[required], textarea[required] {
        border-left: 3px solid #dc3545 !important;
    }
    .was-validated input:valid,
    .was-validated select:valid,
    .was-validated textarea:valid { border-left: 3px solid #198754 !important; }
    .was-validated input:invalid,
    .was-validated select:invalid,
    .was-validated textarea:invalid { border-left: 3px solid #dc3545 !important; }

    /* Mobilní použitelnost — globální */
    @media (max-width: 991.98px) {
        /* Větší touch targets na malých obrazovkách */
        .btn { min-height: 44px; }
        .btn-sm { min-height: 38px; }
        .nav-link { min-height: 44px; display: flex; align-items: center; }
        .form-control, .form-select { min-height: 44px; font-size: 16px; } /* 16px zabrání zoom na iOS */
        .form-control-sm, .form-select-sm { min-height: 38px; font-size: 15px; }
        .form-check-input { width: 1.25em; height: 1.25em; }
        .accordion-button { min-height: 56px; }
        /* Tabulky — vodorovný posun */
        table:not(.no-responsive) { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    }

    /* Toast notifikace */
    .toast-container-global {
        position: fixed;
        top: 70px;
        right: 1rem;
        z-index: 1080;
        max-width: 400px;
    }
    .toast-container-global .toast {
        min-width: 280px;
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
    }
    .toast-container-global .toast-body {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .9rem;
    }

    /* ── Sticky záhlaví tabulek ──────────────────────────────────────────── */
    .table-responsive .table thead th,
    .table-sticky thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        box-shadow: 0 1px 0 rgba(0,0,0,.1);
    }

    /* ── Prázdné stavy ───────────────────────────────────────────────────── */
    .empty-state {
        text-align: center;
        padding: 3.5rem 1rem;
        color: #6c757d;
    }
    .empty-state .empty-icon {
        font-size: 3.5rem;
        opacity: .25;
        display: block;
        margin-bottom: .75rem;
        line-height: 1;
    }
    .empty-state h5 { color: #495057; margin-bottom: .4rem; }
    .empty-state p  { font-size: .9rem; margin-bottom: 1.25rem; }
    [data-bs-theme="dark"] .empty-state h5 { color: #ced4da; }

    /* ── Dark mode — doplňující přepisy ──────────────────────────────────── */
    [data-bs-theme="dark"] .hero-card {
        background: linear-gradient(135deg, #1e3a8a 0%, #065f46 100%) !important;
    }
    [data-bs-theme="dark"] .plan-card  { border-left-color: #60a5fa; }
    [data-bs-theme="dark"] .badge-orange { background-color: #c2600a !important; }
    [data-bs-theme="dark"] .badge-teal   { background-color: #0d9488 !important; }

    /* ── Klávesové zkratky — help modal ─────────────────────────────────── */
    #shortcutsModal kbd {
        display: inline-block;
        padding: .15rem .4rem;
        font-size: .8rem;
        background: var(--bs-secondary-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: .25rem;
    }

    /* ── Globální hledání */
    #globalSearchWrap { position: relative; }
    #globalSearchInput {
        background: rgba(255,255,255,.12);
        border-color: rgba(255,255,255,.2);
        color: #fff;
        min-width: 220px;
        transition: background .2s;
    }
    #globalSearchInput::placeholder { color: rgba(255,255,255,.55); }
    #globalSearchInput:focus {
        background: rgba(255,255,255,.95);
        color: #212529;
        border-color: #86b7fe;
        box-shadow: 0 0 0 .2rem rgba(13,110,253,.25);
    }
    #globalSearchInput:focus::placeholder { color: #6c757d; }
    #globalSearchDropdown {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        min-width: 340px;
        max-height: 420px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid rgba(0,0,0,.15);
        border-radius: .5rem;
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.2);
        z-index: 1055;
        display: none;
    }
    #globalSearchDropdown.show { display: block; }
    .gs-group-header {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6c757d;
        padding: .4rem .85rem .2rem;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }
    .gs-group-header:first-child { border-top: none; }
    .gs-item {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .45rem .85rem;
        font-size: .875rem;
        color: #212529;
        text-decoration: none;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }
    .gs-item:hover, .gs-item.gs-focus {
        background: #e8f0fe;
        color: #0d6efd;
    }
    .gs-item i { font-size: 1rem; flex-shrink: 0; }
    .gs-empty { padding: .6rem .85rem; color: #6c757d; font-size: .85rem; }
    .gs-loading { padding: .6rem .85rem; color: #6c757d; font-size: .85rem; }
</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="index.php">
            <i class="bi bi-bicycle me-1"></i>Tréninková evidence
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Přepnout navigaci">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?= _navActive('index.php') ?>" href="index.php"><i class="bi bi-house me-1"></i>Domů</a>
                </li>

                <?php if ($is_logged_in): ?>

                    <!-- Vkládání dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= _navActive('formular.php','nova_cinnost.php','zatezovy_test_form.php','duplikovat_trenink.php') ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-plus-square me-1"></i>Vložit
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item<?= _dropActive('formular.php') ?>" href="formular.php">
                                <i class="bi bi-calendar-plus me-2 text-primary"></i>Nový trénink</a></li>
                            <li><a class="dropdown-item<?= _dropActive('nova_cinnost.php') ?>" href="nova_cinnost.php">
                                <i class="bi bi-journal-plus me-2 text-secondary"></i>Další činnost</a></li>
                            <li><a class="dropdown-item<?= _dropActive('zatezovy_test_form.php') ?>" href="zatezovy_test_form.php">
                                <i class="bi bi-heart-pulse me-2 text-danger"></i>Zátěžový test</a></li>
                        </ul>
                    </li>

                    <!-- Přehledy dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= _navActive('moje_treninky.php','moje_skupiny.php','prehled_trenera.php','prehled_sportovcu.php','prehled_treninku_skupiny_kalendar.php','vypis_vykazu.php','prehled_popisu.php','prehled_zavodu.php','zavod_detail.php') ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bar-chart me-1"></i>Přehledy
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item<?= _dropActive('moje_treninky.php') ?>" href="moje_treninky.php">
                                <i class="bi bi-list-ul me-2 text-success"></i>Moje tréninky</a></li>
                            <li><a class="dropdown-item<?= _dropActive('moje_skupiny.php') ?>" href="moje_skupiny.php">
                                <i class="bi bi-people me-2 text-success"></i>Moje skupiny</a></li>
                            <li><a class="dropdown-item<?= _dropActive('prehled_trenera.php') ?>" href="prehled_trenera.php">
                                <i class="bi bi-calendar-range me-2 text-success"></i>Přehled tréninků</a></li>
                            <li><a class="dropdown-item<?= _dropActive('prehled_sportovcu.php') ?>" href="prehled_sportovcu.php">
                                <i class="bi bi-person-lines-fill me-2 text-success"></i>Přehled sportovců</a></li>
                            <li><a class="dropdown-item<?= _dropActive('prehled_treninku_skupiny_kalendar.php') ?>" href="prehled_treninku_skupiny_kalendar.php">
                                <i class="bi bi-calendar3 me-2 text-success"></i>Kalendář tréninků</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?= _dropActive('vypis_vykazu.php') ?>" href="vypis_vykazu.php">
                                <i class="bi bi-file-earmark-bar-graph me-2 text-secondary"></i>Výkaz činností</a></li>
                            <li><a class="dropdown-item<?= _dropActive('prehled_popisu.php') ?>" href="prehled_popisu.php">
                                <i class="bi bi-journal-text me-2 text-success"></i>Přehled popisů</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?= _dropActive('prehled_zavodu.php') ?>" href="prehled_zavodu.php">
                                <i class="bi bi-trophy me-2 text-warning"></i>Přehled závodů</a></li>
                        </ul>
                    </li>

                    <!-- Plánovač -->
                    <?php if (canAccess('planovac')): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= _navActive('planovac.php','planovany_trenink_form.php') ?>" href="planovac.php">
                            <i class="bi bi-calendar3-week me-1"></i>Plánovač
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Rezervace dropdown -->
                    <?php if (canAccess('kalendar_sportovist') || canAccess('individualni_lekce')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= _navActive('kalendar_sportovist.php','rezervovat_sportoviste.php','individualni_lekce_form.php','individualni_lekce_sprava.php') ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-building me-1"></i>Rezervace
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (canAccess('kalendar_sportovist')): ?>
                            <li><a class="dropdown-item<?= _dropActive('kalendar_sportovist.php') ?>" href="kalendar_sportovist.php">
                                <i class="bi bi-calendar3 me-2 text-primary"></i>Kalendář sportovišť</a></li>
                            <?php endif; ?>
                            <?php if (canAccess('rezervace_sportovist')): ?>
                            <li><a class="dropdown-item<?= _dropActive('rezervovat_sportoviste.php') ?>" href="rezervovat_sportoviste.php">
                                <i class="bi bi-calendar-plus me-2 text-success"></i>Nová rezervace</a></li>
                            <?php endif; ?>
                            <?php if (canAccess('individualni_lekce')): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?= _dropActive('individualni_lekce_sprava.php') ?>" href="individualni_lekce_sprava.php">
                                <i class="bi bi-person-circle me-2 text-success"></i>Individuální lekce</a></li>
                            <li><a class="dropdown-item<?= _dropActive('individualni_lekce_form.php') ?>" href="individualni_lekce_form.php">
                                <i class="bi bi-person-plus me-2 text-success"></i>Nová lekce</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="booking/kalendar.php" target="_blank">
                                <i class="bi bi-globe me-2 text-info"></i>Veřejný kalendář</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if ($is_hlavni): ?>
                    <!-- Správa dropdown – správce + admin -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-shield-lock me-1"></i>Správa
                        </a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Správa dat</h6></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_sportovcu.php') ?>" href="sprava_sportovcu.php">
                                <i class="bi bi-person-lines-fill me-2"></i>Správa sportovců</a></li>
                            <li><a class="dropdown-item<?= _dropActive('admin_dashboard.php') ?>" href="admin_dashboard.php">
                                <i class="bi bi-speedometer2 me-2 text-primary"></i>Admin dashboard</a></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_vsech_treninku.php') ?>" href="sprava_vsech_treninku.php">
                                <i class="bi bi-clipboard-data me-2"></i>Správa tréninků</a></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_skupin.php') ?>" href="sprava_skupin.php">
                                <i class="bi bi-diagram-2 me-2"></i>Správa skupin</a></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_podskupin.php') ?>" href="sprava_podskupin.php">
                                <i class="bi bi-diagram-3 me-2"></i>Správa podskupin</a></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_zavodu.php') ?>" href="sprava_zavodu.php">
                                <i class="bi bi-flag me-2"></i>Správa závodů</a></li>
                            <li><a class="dropdown-item<?= _dropActive('formular_zavod.php') ?>" href="formular_zavod.php">
                                <i class="bi bi-plus-circle me-2 text-success"></i>Nový závod</a></li>
                            <li><a class="dropdown-item<?= _dropActive('edit_zavod_form.php') ?>" href="prehled_zavodu.php">
                                <i class="bi bi-trophy me-2 text-warning"></i>Přehled závodů</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?= _dropActive('verejny_prehled.php') ?>" href="verejny_prehled.php">
                                <i class="bi bi-globe me-2"></i>Veřejný přehled</a></li>
                            <li><a class="dropdown-item<?= _dropActive('prehled_vsech_vykazu.php') ?>" href="prehled_vsech_vykazu.php">
                                <i class="bi bi-graph-up me-2"></i>Všechny výkazy</a></li>
                            <li><a class="dropdown-item<?= _dropActive('sync_evidence.php') ?>" href="sync_evidence.php">
                                <i class="bi bi-arrow-repeat me-2 text-primary"></i>Synchronizace evidence</a></li>
                            <li><a class="dropdown-item<?= _dropActive('kis_sync_center.php') ?>" href="kis_sync_center.php">
                                <i class="bi bi-diagram-3 me-2 text-primary"></i>KIS centrum</a></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_segmentu.php') ?>" href="sprava_segmentu.php">
                                <i class="bi bi-signpost-split me-2"></i>Správa segmentů</a></li>

                            <?php if ($is_admin): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Administrace</h6></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_sportovist.php') ?>" href="sprava_sportovist.php">
                                <i class="bi bi-building-gear me-2"></i>Správa sportovišť</a></li>
                            <li><a class="dropdown-item<?= _dropActive('sprava_treneru.php') ?>" href="sprava_treneru.php">
                                <i class="bi bi-person-gear me-2"></i>Správa trenérů</a></li>
                            <li><a class="dropdown-item<?= _dropActive('nastaveni_opravneni.php') ?>" href="nastaveni_opravneni.php">
                                <i class="bi bi-sliders me-2 text-danger"></i>Nastavení oprávnění</a></li>
                            <li><a class="dropdown-item<?= _dropActive('nastaveni_zadavani.php') ?>" href="nastaveni_zadavani.php">
                                <i class="bi bi-calendar-lock me-2"></i>Okno pro zadávání</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Firemní evidence</h6></li>
                            <li><a class="dropdown-item" href="vozidla/seznam.php">
                                <i class="bi bi-car-front me-2"></i>Vozidla</a></li>
                            <li><a class="dropdown-item" href="uctenky/seznam.php">
                                <i class="bi bi-receipt me-2"></i>Účtenky</a></li>
                            <li><a class="dropdown-item" href="udalosti/seznam.php">
                                <i class="bi bi-calendar-event me-2"></i>Události</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                <?php endif; ?>
            </ul>

            <?php if ($is_logged_in): ?>
            <!-- Globální hledání -->
            <div id="globalSearchWrap" class="mx-lg-3 my-2 my-lg-0" role="search">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-end-0" style="border-color:rgba(255,255,255,.2);">
                        <i class="bi bi-search text-white-50"></i>
                    </span>
                    <input type="text" id="globalSearchInput"
                           class="form-control border-start-0"
                           placeholder="Hledat…"
                           autocomplete="off"
                           aria-label="Globální hledání"
                           aria-expanded="false"
                           aria-controls="globalSearchDropdown">
                </div>
                <div id="globalSearchDropdown" role="listbox" aria-label="Výsledky hledání"></div>
            </div>
            <?php endif; ?>

            <ul class="navbar-nav align-items-lg-center gap-1">
                <!-- Tmavý režim + zkratky -->
                <li class="nav-item d-flex align-items-center gap-1">
                    <?php if ($is_logged_in): ?>
                    <button id="pushToggleBtn" class="btn btn-sm btn-outline-secondary border-0 text-white-50"
                            style="display:none;padding:4px 8px" title="Push notifikace">
                        <i class="bi bi-bell-slash"></i>
                    </button>
                    <?php endif; ?>
                    <button id="themeToggle" class="btn btn-sm btn-outline-secondary border-0 text-white-50"
                            onclick="toggleTheme()" title="Přepnout tmavý/světlý režim" style="padding:4px 8px">
                        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary border-0 text-white-50"
                            data-bs-toggle="modal" data-bs-target="#shortcutsModal"
                            title="Klávesové zkratky (?)" style="padding:4px 8px">
                        <i class="bi bi-keyboard"></i>
                    </button>
                </li>
                <?php if ($is_logged_in): ?>
                    <li class="nav-item">
                        <span class="navbar-text text-light small">
                            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($trener_jmeno) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="logout.php" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="nav-link text-danger border-0 bg-transparent">
                                <i class="bi bi-box-arrow-right me-1"></i>Odhlásit
                            </button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Přihlásit se
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Toast kontejner -->
<div class="toast-container-global" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<!-- Modal: klávesové zkratky -->
<div class="modal fade" id="shortcutsModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-keyboard me-2"></i>Klávesové zkratky</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td><kbd>/</kbd></td><td class="text-muted small">Aktivovat hledání</td></tr>
                        <tr><td><kbd>N</kbd></td><td class="text-muted small">Nový trénink</td></tr>
                        <tr><td><kbd>P</kbd></td><td class="text-muted small">Plánovač</td></tr>
                        <tr><td><kbd>K</kbd></td><td class="text-muted small">Kalendář sportovišť</td></tr>
                        <tr><td><kbd>?</kbd></td><td class="text-muted small">Tato nápověda</td></tr>
                        <tr><td><kbd>Esc</kbd></td><td class="text-muted small">Zavřít / zrušit</td></tr>
                        <tr><td><kbd>D</kbd></td><td class="text-muted small">Přepnout dark/light</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Globální potvrzovací modal — náhrada za nativní confirm() (data-confirm atribut) -->
<div class="modal fade" id="appConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Potvrzení akce</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
            </div>
            <div class="modal-body" id="appConfirmModalText" style="white-space: pre-line;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-danger" id="appConfirmModalOk">
                    <i class="bi bi-check-lg me-1"></i>Potvrdit
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Flash zprávy ze session → automatický toast
$_flashToasts = [];
if (!empty($_SESSION['flash_success'])) {
    $_flashToasts[] = ['message' => $_SESSION['flash_success'], 'type' => 'success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $_flashToasts[] = ['message' => $_SESSION['flash_error'], 'type' => 'danger'];
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_warning'])) {
    $_flashToasts[] = ['message' => $_SESSION['flash_warning'], 'type' => 'warning'];
    unset($_SESSION['flash_warning']);
}
if (!empty($_SESSION['flash_info'])) {
    $_flashToasts[] = ['message' => $_SESSION['flash_info'], 'type' => 'info'];
    unset($_SESSION['flash_info']);
}
?>
<script>
// ── Toast notifikace — globální funkce ──────────────────────────────────────
window.showToast = function(message, type) {
    type = type || 'success';
    var icons = {
        success: 'bi-check-circle-fill text-success',
        danger:  'bi-exclamation-triangle-fill text-danger',
        warning: 'bi-exclamation-circle-fill text-warning',
        info:    'bi-info-circle-fill text-info'
    };
    var icon = icons[type] || icons.info;

    var container = document.getElementById('toastContainer');
    if (!container) return;

    var id = 'toast_' + Date.now();
    var html = '<div id="' + id + '" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">' +
        '<div class="toast-body">' +
            '<i class="bi ' + icon + ' fs-5"></i>' +
            '<span>' + message + '</span>' +
            '<button type="button" class="btn-close btn-close-sm ms-auto" data-bs-dismiss="toast" aria-label="Zavřít"></button>' +
        '</div>' +
    '</div>';

    container.insertAdjacentHTML('beforeend', html);

    var toastEl = document.getElementById(id);
    if (toastEl && typeof bootstrap !== 'undefined') {
        var bsToast = new bootstrap.Toast(toastEl, { delay: 4000, autohide: true });
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', function() { toastEl.remove(); });
    }
};

// ── Tmavý režim ─────────────────────────────────────────────────────────────
window.toggleTheme = function() {
    var html = document.documentElement;
    var current = html.getAttribute('data-bs-theme') || 'light';
    var next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('bs-theme', next);
    updateThemeIcon(next);
};
function updateThemeIcon(theme) {
    var icon = document.getElementById('themeIcon');
    if (!icon) return;
    icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}
document.addEventListener('DOMContentLoaded', function() {
    updateThemeIcon(document.documentElement.getAttribute('data-bs-theme') || 'light');
});

// ── Klávesové zkratky ────────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    // Ignorovat zkratky v input/textarea/select
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
    // Ignorovat Ctrl/Alt/Meta kombinace
    if (e.ctrlKey || e.altKey || e.metaKey) return;

    switch (e.key) {
        case '/':
            e.preventDefault();
            var si = document.getElementById('globalSearchInput');
            if (si) { si.focus(); si.select(); }
            break;
        case 'n': case 'N':
            if (!e.shiftKey) window.location.href = 'formular.php';
            break;
        case 'p': case 'P':
            window.location.href = 'planovac.php';
            break;
        case 'k': case 'K':
            window.location.href = 'kalendar_sportovist.php';
            break;
        case '?':
            e.preventDefault();
            var m = document.getElementById('shortcutsModal');
            if (m && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(m).show();
            break;
        case 'd': case 'D':
            toggleTheme();
            break;
        case 'Escape':
            // Zavřít otevřené Bootstrap modaly
            var openModal = document.querySelector('.modal.show');
            if (openModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(openModal)?.hide();
            }
            break;
    }
});

// ── Auto-zobrazení flash zpráv ze session
<?php if (!empty($_flashToasts)): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($_flashToasts as $t): ?>
    showToast(<?= json_encode(htmlspecialchars($t['message'], ENT_QUOTES, 'UTF-8')) ?>, <?= json_encode($t['type']) ?>);
    <?php endforeach; ?>
});
<?php endif; ?>
</script>

<?php
// Absolutní URL-cesta k search endpointu — funguje z kořene i podadresářů
$_gsDocRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_gsHlavickaDir = rtrim(str_replace('\\', '/', __DIR__), '/');
$_gsAppBase = '/' . ltrim(str_replace($_gsDocRoot, '', $_gsHlavickaDir), '/');
$_gsSearchUrl = $_gsAppBase . '/ajax_global_search.php';
?>
<?php if ($is_logged_in): ?>
<script>
// ── Web Push — registrace Service Workeru a subscribe ───────────────────────
(function() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    const PUSH_PUBLIC_KEY = <?= json_encode(
        $pdo->query("SELECT hodnota FROM nastaveni WHERE klic='push_vapid_public'")->fetchColumn() ?: ''
    ) ?>;
    if (!PUSH_PUBLIC_KEY) return; // VAPID klíče ještě nejsou nastaveny

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw     = window.atob(base64);
        return new Uint8Array([...raw].map(c => c.charCodeAt(0)));
    }

    navigator.serviceWorker.register('/evidence/sw.js', { scope: '/evidence/' })
        .then(function(reg) {
            // Zkontrolovat stav subscripce
            return reg.pushManager.getSubscription().then(function(sub) {
                const btn = document.getElementById('pushToggleBtn');
                if (!btn) return;
                if (sub) {
                    btn.innerHTML = '<i class="bi bi-bell-fill"></i>';
                    btn.title = 'Push notifikace aktivní — klik pro vypnutí';
                    btn.dataset.subscribed = '1';
                } else {
                    btn.innerHTML = '<i class="bi bi-bell-slash"></i>';
                    btn.title = 'Zapnout push notifikace';
                    btn.dataset.subscribed = '0';
                }
                btn.style.display = 'inline-block';
            });
        })
        .catch(err => console.warn('SW registration failed:', err));

    // Toggle subscribe/unsubscribe
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#pushToggleBtn');
        if (!btn) return;

        navigator.serviceWorker.ready.then(function(reg) {
            if (btn.dataset.subscribed === '1') {
                // Odhlásit
                reg.pushManager.getSubscription().then(sub => {
                    if (!sub) return;
                    sub.unsubscribe().then(() => {
                        fetch('push_subscribe.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'unsubscribe', csrf_token: <?= json_encode(csrf_token()) ?>, subscription: { endpoint: sub.endpoint } })
                        });
                        btn.innerHTML = '<i class="bi bi-bell-slash"></i>';
                        btn.dataset.subscribed = '0';
                        btn.title = 'Zapnout push notifikace';
                        if (window.showToast) showToast('Push notifikace vypnuty', 'info');
                    });
                });
            } else {
                // Přihlásit
                Notification.requestPermission().then(function(perm) {
                    if (perm !== 'granted') {
                        if (window.showToast) showToast('Notifikace nebyly povoleny prohlížečem.', 'warning');
                        return;
                    }
                    reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(PUSH_PUBLIC_KEY)
                    }).then(sub => {
                        fetch('push_subscribe.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'subscribe', csrf_token: <?= json_encode(csrf_token()) ?>, subscription: sub.toJSON() })
                        });
                        btn.innerHTML = '<i class="bi bi-bell-fill"></i>';
                        btn.dataset.subscribed = '1';
                        btn.title = 'Push notifikace aktivní — klik pro vypnutí';
                        if (window.showToast) showToast('Push notifikace aktivovány! 🔔', 'success');
                    }).catch(err => {
                        console.error('Push subscribe failed:', err);
                        if (window.showToast) showToast('Aktivace notifikací selhala.', 'danger');
                    });
                });
            }
        });
    });
})();

// ── Globální vyhledávání ─────────────────────────────────────────────────────
(function () {
    const input    = document.getElementById('globalSearchInput');
    const dropdown = document.getElementById('globalSearchDropdown');
    if (!input || !dropdown) return;

    const SEARCH_URL = <?= json_encode($_gsSearchUrl) ?>;

    let controller = null;
    let timer      = null;
    let focusIdx   = -1;

    function getItems() {
        return Array.from(dropdown.querySelectorAll('.gs-item'));
    }

    function setFocus(idx) {
        const items = getItems();
        items.forEach((el, i) => el.classList.toggle('gs-focus', i === idx));
        focusIdx = idx;
    }

    function showDropdown(html) {
        dropdown.innerHTML = html;
        dropdown.classList.add('show');
        input.setAttribute('aria-expanded', 'true');
        focusIdx = -1;
    }

    function hideDropdown() {
        dropdown.classList.remove('show');
        dropdown.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        focusIdx = -1;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function buildHtml(data) {
        const groups = [
            { key: 'sportovci', label: 'Sportovci' },
            { key: 'treninky',  label: 'Tréninky' },
            { key: 'zavody',    label: 'Závody' },
        ];
        let html = '';
        let total = 0;
        groups.forEach(g => {
            const rows = data[g.key] || [];
            if (!rows.length) return;
            total += rows.length;
            html += `<div class="gs-group-header">${esc(g.label)}</div>`;
            rows.forEach(r => {
                html += `<a class="gs-item" href="${esc(r.url)}" role="option">
                    <i class="bi ${esc(r.icon)} text-primary"></i>
                    <span>${esc(r.label)}</span>
                </a>`;
            });
        });
        if (total === 0) {
            html = '<div class="gs-empty"><i class="bi bi-search me-1"></i>Nic nenalezeno.</div>';
        }
        return html;
    }

    function doSearch() {
        const q = input.value.trim();
        if (q.length < 2) { hideDropdown(); return; }

        if (controller) controller.abort();
        controller = new AbortController();

        showDropdown('<div class="gs-loading"><span class="spinner-border spinner-border-sm me-2"></span>Hledám…</div>');

        const url = new URL(SEARCH_URL, window.location.origin);
        url.searchParams.set('q', q);

        fetch(url.toString(), { signal: controller.signal })
            .then(r => r.json())
            .then(data => {
                showDropdown(buildHtml(data));
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    showDropdown('<div class="gs-empty text-danger">Chyba při hledání.</div>');
                }
            });
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { hideDropdown(); return; }
        timer = setTimeout(doSearch, 300);
    });

    input.addEventListener('keydown', e => {
        const items = getItems();
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setFocus(Math.min(focusIdx + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setFocus(Math.max(focusIdx - 1, 0));
        } else if (e.key === 'Enter') {
            if (focusIdx >= 0 && items[focusIdx]) {
                e.preventDefault();
                items[focusIdx].click();
            }
        } else if (e.key === 'Escape') {
            hideDropdown();
            input.blur();
        }
    });

    // Klik mimo → zavřít
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            hideDropdown();
        }
    });

    // Focus → znovu otevřít pokud je co
    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2 && !dropdown.classList.contains('show')) {
            doSearch();
        }
    });
})();

// ── Potvrzovací modal — window.appConfirm(message) → Promise<bool> ──────────
window.appConfirm = function (message) {
    return new Promise(function (resolve) {
        var modalEl = document.getElementById('appConfirmModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            resolve(window.confirm(message));
            return;
        }
        document.getElementById('appConfirmModalText').textContent = message;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var okBtn = document.getElementById('appConfirmModalOk');
        var done = false;
        function onOk() { done = true; modal.hide(); }
        function onHide() {
            okBtn.removeEventListener('click', onOk);
            modalEl.removeEventListener('hidden.bs.modal', onHide);
            resolve(done);
        }
        okBtn.addEventListener('click', onOk);
        modalEl.addEventListener('hidden.bs.modal', onHide);
        modal.show();
    });
};

// ── data-confirm — formuláře a odkazy potvrzované modalem ───────────────────
(function () {
    // capture fáze: běží dřív než zámek proti dvojímu odeslání
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.dataset.confirm || form.dataset.confirmed === '1') return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter || null;
        window.appConfirm(form.dataset.confirm).then(function (ok) {
            if (!ok) return;
            form.dataset.confirmed = '1';
            if (form.requestSubmit) form.requestSubmit(submitter || undefined);
            else form.submit();
            form.dataset.confirmed = '';
        });
    }, true);

    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;
        // odkazy
        var a = e.target.closest('a[data-confirm]');
        if (a) {
            e.preventDefault();
            window.appConfirm(a.dataset.confirm).then(function (ok) {
                if (ok) window.location.href = a.href;
            });
            return;
        }
        // submit tlačítka s vlastním potvrzením
        var btn = e.target.closest('button[data-confirm], input[type="submit"][data-confirm]');
        if (btn && btn.form) {
            e.preventDefault();
            window.appConfirm(btn.dataset.confirm).then(function (ok) {
                if (!ok) return;
                btn.form.dataset.confirmed = '1';
                if (btn.form.requestSubmit) btn.form.requestSubmit(btn);
                else btn.form.submit();
                btn.form.dataset.confirmed = '';
            });
        }
    }, true);
})();

// ── Zámek proti dvojímu odeslání formulářů ──────────────────────────────────
// Vyjmutí: <form data-no-lock> (např. formuláře, které neopouštějí stránku).
// Po 10 s se tlačítka automaticky odemknou (exporty stahující soubor).
(function () {
    function unlock(form) {
        form.querySelectorAll('button[disabled][data-orig-html]').forEach(function (btn) {
            btn.innerHTML = btn.dataset.origHtml;
            btn.disabled = false;
            delete btn.dataset.origHtml;
        });
        form.querySelectorAll('input[type="submit"][disabled][data-locked]').forEach(function (btn) {
            btn.disabled = false;
            delete btn.dataset.locked;
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.noLock !== undefined) return;
        if (form.target && form.target === '_blank') return;

        var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])');
        // odloženě — hodnota kliknutého tlačítka se musí stihnout serializovat.
        // Kontrola defaultPrevented AŽ tady: AJAX handlery na stránce volají
        // preventDefault v témže dispatchi, ale po tomto (dříve registrovaném) listeneru.
        // Kdyby byla kontrola nahoře, byl by defaultPrevented ještě false a lock by
        // zamkl i AJAX formuláře, které nenavigují → zůstaly by zamčené 10 s.
        setTimeout(function () {
            if (e.defaultPrevented) return;
            buttons.forEach(function (btn) {
                if (btn.disabled) return;
                btn.disabled = true;
                if (btn.tagName === 'BUTTON') {
                    btn.dataset.origHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + btn.innerHTML;
                } else {
                    btn.dataset.locked = '1';
                }
            });
        }, 0);
        setTimeout(function () { unlock(form); }, 10000);
    });

    // Návrat tlačítkem Zpět z bfcache → odemknout vše
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('form').forEach(unlock);
    });
})();

// ── PWA — manifest + service worker (instalovatelná aplikace) ───────────────
(function () {
    var base = <?= json_encode(rtrim(str_replace('\\', '/', substr(__DIR__, strlen((string)($_SERVER['DOCUMENT_ROOT'] ?? '')))), '/') . '/') ?>;
    var link = document.createElement('link');
    link.rel = 'manifest';
    link.href = base + 'manifest.json';
    document.head.appendChild(link);
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () {});
    }
})();
</script>
<?php endif; ?>
