<?php
require_once __DIR__ . '/includes/session_security.php';
if (session_status() === PHP_SESSION_NONE) {
    app_session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/ui_shell.php';
require_once __DIR__ . '/includes/admin_shell.php';
if (file_exists(__DIR__ . '/includes/funkce.php')) {
    require_once __DIR__ . '/includes/funkce.php';
}
if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/csrf_helper.php';
}

$is_logged_in = isset($_SESSION['trener_id']);
$is_customer  = isset($_SESSION['verejny_uzivatel_id']);
$is_athlete   = isset($_SESSION['sportovec_pristup_id']);
$is_hlavni    = $is_logged_in && roleAtLeast('hlavni');  // správce nebo admin
$is_admin     = $is_logged_in && roleAtLeast('admin');   // pouze admin
$staff_definitions = $is_logged_in ? staffPositionDefinitions() : [];
$staff_active_code = $is_logged_in ? staffActivePosition() : '';
$staff_active = $is_logged_in ? ($staff_definitions[$staff_active_code] ?? null) : null;
$staff_available = $is_logged_in ? staffAvailablePositions() : [];
$staff_menu_groups = $is_logged_in && is_array($staff_active)
    ? staffPositionPrimaryMenuGroups($staff_active_code, defined('JE_LOKALNE') && JE_LOKALNE === true)
    : [];

// Active page detection for navbar highlighting
$_currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
function _navActive(string ...$pages): string
{
    global $_currentPage;
    return in_array($_currentPage, $pages, true) ? ' active' : '';
}
function _dropActive(string ...$pages): string
{
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
<script nonce="<?=
htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')
?>">
(function(){
    var t = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
<?php
appUiAssets();
?>
<style nonce="<?=
htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')
?>">
    .navbar .nav-link { border-bottom: 2px solid transparent; }
    .navbar .nav-link.active { color: #fff !important; border-bottom-color: var(--app-blue); }
    .dropdown-item.active { background-color: #e7f0fb !important; color: var(--app-navy) !important; }

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
    @media (max-width: 1399.98px) {
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
        background: linear-gradient(135deg, #1767c9 0%, #18386f 100%) !important;
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
        border-color: var(--app-blue);
        box-shadow: var(--app-focus-ring);
    }
    #globalSearchInput:focus::placeholder { color: #6c757d; }
    @media (min-width: 1400px) and (max-width: 1439.98px) {
        #globalSearchInput { min-width: 170px; width: 170px; }
    }
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
        background: #e7f0fb;
        color: var(--app-navy);
    }
    .gs-item i { font-size: 1rem; flex-shrink: 0; }
    .gs-empty { padding: .6rem .85rem; color: #6c757d; font-size: .85rem; }
    .gs-loading { padding: .6rem .85rem; color: #6c757d; font-size: .85rem; }
</style>
<nav class="navbar navbar-expand-xxl navbar-dark bg-dark app-staff-topbar">
    <div class="container-fluid">
        <a class="navbar-brand app-brand-link" href="<?=
 htmlspecialchars(appUiUrl($is_logged_in ? 'pracovni_pozice.php' :
    'index.php'))
?>" aria-label="KOVO Praha – <?=
 $is_logged_in ? 'pracovní rozcestník' : 'úvodní stránka'
?>">
            <span class="app-brand-logo" aria-hidden="true"><img src="<?=
 htmlspecialchars(appUiUrl('assets/kovo-praha-logo.png'))

?>" alt="" width="500" height="167"></span>
        </a>
        <?php
if ($is_logged_in && is_array($staff_active)):
    ?>
<?php
    echo('        <button class="navbar-toggler app-sidebar-toggler" type="button" data-bs-toggle="offcanvas" ' .

    'data-bs-target="#appAdminSidebar" aria-controls="appAdminSidebar" aria-label="Otevřít pracovní nabídku"><span ' .

    'class="navbar-toggler-icon"></span></button>
');
    ?>        <?php
endif;
?>
        <?php if ($is_logged_in): require __DIR__ . '/includes/staff_notification_bell.php'; endif; ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Přepnout navigaci">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <?php
if ($is_logged_in && is_array($staff_active)):
    ?>
            <ul class="navbar-nav me-auto mb-2 mb-xxl-0">
                <li class="nav-item"><a class="nav-link<?=
     _navActive('pracovni_pozice.php')
    ?>" href="<?=
     htmlspecialchars(appUiUrl('pracovni_pozice.php'))

    ?>"><i class="bi bi-grid me-1"></i>Rozcestník</a></li>
                <?php
foreach ($staff_menu_groups as $staff_group):
    ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-<?=

                    htmlspecialchars((string)$staff_group['icon'])
    ?> me-1"></i><?=
     htmlspecialchars((string)$staff_group['label'])
    ?></a>
                    <ul class="dropdown-menu">
                        <?php
foreach ($staff_group['items'] as $staff_item):
    ?>
                        <li><a class="dropdown-item<?=
     _dropActive(basename((string)$staff_item['route']))
    ?>" href="<?=
     htmlspecialchars(appUiUrl((string)$staff_item['route']))

    ?>"><i class="bi bi-<?=
     htmlspecialchars((string)$staff_item['icon'])
    ?> me-2 text-primary"></i><?=
     htmlspecialchars((string)$staff_item['label'])

    ?></a></li>
                        <?php
endforeach;
    ?>
                    </ul>
                </li>
                <?php
endforeach;
    ?>
            </ul>
            <?php else:
                ?>
            <ul class="navbar-nav me-auto mb-2 mb-xxl-0">
                <li class="nav-item">
                    <a class="nav-link<?=
                 _navActive('index.php')
                ?>" href="index.php"><i class="bi bi-house me-1"></i>Domů</a>
                </li>

                <?php
if ($is_logged_in):
    ?>

                    <!-- Vkládání dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?=
     _navActive(
         'formular.php',
         'nova_cinnost.php',
         'zatezovy_test_form.php',
         'duplikovat_trenink.php',
         'formular_zavod.php'
     )
    ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-plus-square me-1"></i>Vložit
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item<?=
     _dropActive('formular.php')
    ?>" href="formular.php">
                                <i class="bi bi-calendar-plus me-2 text-primary"></i>Nový trénink</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('duplikovat_trenink.php')
    ?>" href="duplikovat_trenink.php">
                                <i class="bi bi-copy me-2 text-primary"></i>Duplikovat trénink</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('nova_cinnost.php')
    ?>" href="nova_cinnost.php">
                                <i class="bi bi-journal-plus me-2 text-secondary"></i>Další činnost</a></li>
                            <?php
if (canAccess('formular_zavod')):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('formular_zavod.php')
    ?>" href="formular_zavod.php">
                                <i class="bi bi-trophy me-2 text-warning"></i>Nový závod</a></li>
                            <?php
endif;
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('zatezovy_test_form.php')
    ?>" href="zatezovy_test_form.php">
                                <i class="bi bi-heart-pulse me-2 text-danger"></i>Zátěžový test</a></li>
                        </ul>
                    </li>

                    <!-- Přehledy dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?=
     _navActive(
         'moje_treninky.php',
         'moje_skupiny.php',
         'prehled_trenera.php',
         'prehled_sportovcu.php',
         'prehled_treninku_skupiny_kalendar.php',
         'vypis_vykazu.php',
         'prehled_popisu.php',
         'prehled_zavodu.php',
         'zavod_detail.php',
         'sprava_zavodu.php',
         'hromadne_podskupiny.php',
         'oznameni.php',
         'export_draha.php',
         'export_seznam.php',
         'export_uci.php',
         'google_sheets_linky.php',
         'cviky.php',
         'sprava_segmentu.php',
         'hromadne_odmeny.php'
     )
    ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bar-chart me-1"></i>Přehledy
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item<?=
     _dropActive('moje_treninky.php')
    ?>" href="moje_treninky.php">
                                <i class="bi bi-list-ul me-2 text-success"></i>Moje tréninky</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('moje_skupiny.php')
    ?>" href="moje_skupiny.php">
                                <i class="bi bi-people me-2 text-success"></i>Moje skupiny</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_trenera.php')
    ?>" href="prehled_trenera.php">
                                <i class="bi bi-calendar-range me-2 text-success"></i>Přehled tréninků</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_sportovcu.php')
    ?>" href="prehled_sportovcu.php">
                                <i class="bi bi-person-lines-fill me-2 text-success"></i>Přehled sportovců</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_treninku_skupiny_kalendar.php')
    ?>" href="prehled_treninku_skupiny_kalendar.php">
                                <i class="bi bi-calendar3 me-2 text-success"></i>Kalendář tréninků</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?=
     _dropActive('vypis_vykazu.php')
    ?>" href="vypis_vykazu.php">
                                <i class="bi bi-file-earmark-bar-graph me-2 text-secondary"></i>Výkaz činností</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_popisu.php')
    ?>" href="prehled_popisu.php">
                                <i class="bi bi-journal-text me-2 text-success"></i>Přehled popisů</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_zavodu.php')
    ?>" href="prehled_zavodu.php">
                                <i class="bi bi-trophy me-2 text-warning"></i>Přehled závodů</a></li>
                            <?php
if (canAccess('sprava_zavodu')):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_zavodu.php')
    ?>" href="sprava_zavodu.php">
                                <i class="bi bi-gear me-2 text-warning"></i>Správa závodů</a></li>
                            <?php
endif;
    ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Exporty</h6></li>
                            <li><a class="dropdown-item<?=
     _dropActive('export_draha.php')
    ?>" href="export_draha.php">
                                <i class="bi bi-file-earmark-excel me-2 text-success"></i>Export – dráha</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('export_uci.php')
    ?>" href="export_uci.php">
                                <i class="bi bi-file-earmark-person me-2 text-primary"></i>Export – UCI přihláška</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('export_seznam.php')
    ?>" href="export_seznam.php">
                                <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Export – Seznam sportovců</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('google_sheets_linky.php')
    ?>" href="google_sheets_linky.php">
                                <i class="bi bi-table me-2 text-success"></i>Google Sheets evidence</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Nástroje</h6></li>
                            <?php
if (canAccess('sprava_sportovcu')):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('hromadne_podskupiny.php')
    ?>" href="hromadne_podskupiny.php">
                                <i class="bi bi-diagram-3 me-2 text-warning"></i>Skupiny a podskupiny</a></li>
                            <?php
endif;
    ?>
                            <?php
if (canAccess('oznameni')):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('oznameni.php')
    ?>" href="oznameni.php">
                                <i class="bi bi-bell me-2 text-warning"></i>Oznámení a zprávy</a></li>
                            <?php
endif;
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('cviky.php')
    ?>" href="cviky.php">
                                <i class="bi bi-activity me-2 text-secondary"></i>Cviky v posilovně</a></li>
                            <?php
if (canAccess('segmenty')):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_segmentu.php')
    ?>" href="sprava_segmentu.php">
                                <i class="bi bi-signpost-split me-2 text-secondary"></i>Segmenty</a></li>
                            <?php
endif;
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('hromadne_odmeny.php')
    ?>" href="hromadne_odmeny.php">
                                <i class="bi bi-star me-2 text-warning"></i>Nastavení odměn</a></li>
                        </ul>
                    </li>

                    <!-- Plánovač -->
                    <?php
if (canAccess('planovac')):
    ?>
                    <li class="nav-item">
                        <a class="nav-link<?=
     _navActive('planovac.php', 'planovany_trenink_form.php')
    ?>" href="planovac.php">
                            <i class="bi bi-calendar3-week me-1"></i>Plánovač
                        </a>
                    </li>
                    <?php
endif;
    ?>

                    <!-- Rezervace dropdown -->
                    <?php
if (canAccess('kalendar_sportovist') || canAccess('individualni_lekce')):
    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?=
     _navActive(
         'kalendar_sportovist.php',
         'rezervovat_sportoviste.php',
         'individualni_lekce_form.php',
         'individualni_lekce_sprava.php'
     )
    ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-building me-1"></i>Rezervace
                        </a>
                        <ul class="dropdown-menu">
                            <?php
if (canAccess('kalendar_sportovist')):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('kalendar_sportovist.php')
    ?>" href="kalendar_sportovist.php">
                                <i class="bi bi-calendar3 me-2 text-primary"></i>Kalendář sportovišť</a></li>
                            <?php
endif;
    ?>
                            <?php
if (canAccess('rezervace_sportovist')):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('rezervovat_sportoviste.php')
    ?>" href="rezervovat_sportoviste.php">
                                <i class="bi bi-calendar-plus me-2 text-success"></i>Nová rezervace</a></li>
                            <?php
endif;
    ?>
                            <?php
if (canAccess('individualni_lekce')):
    ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?=
     _dropActive('individualni_lekce_sprava.php')
    ?>" href="individualni_lekce_sprava.php">
                                <i class="bi bi-person-circle me-2 text-success"></i>Individuální lekce</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('individualni_lekce_form.php')
    ?>" href="individualni_lekce_form.php">
                                <i class="bi bi-person-plus me-2 text-success"></i>Nová lekce</a></li>
                            <?php
endif;
    ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="booking/kalendar.php" target="_blank">
                                <i class="bi bi-globe me-2 text-info"></i>Veřejný kalendář</a></li>
                        </ul>
                    </li>
                    <?php
endif;
    ?>

                    <?php
if ($is_hlavni):
    ?>
                    <!-- Klub dropdown – správce + admin (cílová struktura: bývalá "Správa dat") -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-diagram-3 me-1"></i>Klub
                        </a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Provoz</h6></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_sportovcu.php')
    ?>" href="sprava_sportovcu.php">
                                <i class="bi bi-person-lines-fill me-2"></i>Správa sportovců</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('admin_dashboard.php')
    ?>" href="admin_dashboard.php">
                                <i class="bi bi-speedometer2 me-2 text-primary"></i>Admin dashboard</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_vsech_treninku.php')
    ?>" href="sprava_vsech_treninku.php">
                                <i class="bi bi-clipboard-data me-2"></i>Správa tréninků</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_skupin.php')
    ?>" href="sprava_skupin.php">
                                <i class="bi bi-diagram-2 me-2"></i>Správa skupin</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_podskupin.php')
    ?>" href="sprava_podskupin.php">
                                <i class="bi bi-diagram-3 me-2"></i>Správa podskupin</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_zavodu.php')
    ?>" href="sprava_zavodu.php">
                                <i class="bi bi-flag me-2"></i>Správa závodů</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('formular_zavod.php')
    ?>" href="formular_zavod.php">
                                <i class="bi bi-plus-circle me-2 text-success"></i>Nový závod</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_zavodu.php')
    ?>" href="prehled_zavodu.php">
                                <i class="bi bi-trophy me-2 text-warning"></i>Přehled závodů</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('verejny_prehled.php')
    ?>" href="verejny_prehled.php">
                                <i class="bi bi-globe me-2"></i>Veřejný přehled</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_vsech_vykazu.php')
    ?>" href="prehled_vsech_vykazu.php">
                                <i class="bi bi-graph-up me-2"></i>Všechny výkazy</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('club_programs_admin.php')
    ?>" href="club_programs_admin.php">
                                <i class="bi bi-calendar-range me-2 text-primary"></i>Kroužkové programy</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('club_program_offers_admin.php')
    ?>" href="club_program_offers_admin.php">
                                <i class="bi bi-calendar2-check me-2 text-primary"></i>Nabídky kroužků</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('club_program_storefront_admin.php')
    ?>" href="club_program_storefront_admin.php">
                                <i class="bi bi-images me-2 text-primary"></i>Veřejný rozcestník kroužků</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('club_program_wizard_admin.php')
    ?>" href="club_program_wizard_admin.php">
                                <i class="bi bi-magic me-2 text-success"></i>Vypsat kroužek</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('verejny_velodrom_admin.php')
    ?>" href="verejny_velodrom_admin.php">
                                <i class="bi bi-bicycle me-2 text-primary"></i>Veřejné hodiny velodromu</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_segmentu.php')
    ?>" href="sprava_segmentu.php">
                                <i class="bi bi-signpost-split me-2"></i>Správa segmentů</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('odeslat_emaily.php')
    ?>" href="odeslat_emaily.php">
                                <i class="bi bi-envelope-fill me-2 text-primary"></i>Odeslat emaily</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('prehled_kreditu.php')
    ?>" href="prehled_kreditu.php">
                                <i class="bi bi-wallet2 me-2 text-success"></i>Přehled kreditů</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_sportovec_obdobi.php')
    ?>" href="sprava_sportovec_obdobi.php">
                                <i class="bi bi-cash-coin me-2 text-success"></i>Kreditní období</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Členové a KIS</h6></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sync_evidence.php')
    ?>" href="sync_evidence.php">
                                <i class="bi bi-arrow-repeat me-2 text-primary"></i>Synchronizace evidence</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('kis_sync_center.php')
    ?>" href="kis_sync_center.php">
                                <i class="bi bi-diagram-3 me-2 text-primary"></i>KIS centrum</a></li>
                            <?php
if ($is_admin):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('kis_roster_hierarchy_admin.php')
    ?>" href="kis_roster_hierarchy_admin.php">
                                <i class="bi bi-diagram-3 me-2 text-primary"></i>Skupiny a soupisky</a></li>
                            <?php
endif;
    ?>
                            <?php
if (defined('JE_LOKALNE') && JE_LOKALNE):
    ?>
                            <?php
endif;
    ?>
                        </ul>
                    </li>
                    <?php
endif;
    ?>

                    <?php
if ($is_admin):
    ?>
                    <!-- Administrace dropdown – pouze admin (cílová struktura: samostatné top-level menu) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-shield-lock me-1"></i>Administrace
                        </a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">E-shop</h6></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_admin.php')
    ?>" href="eshop_admin.php">
                                <i class="bi bi-shop me-2 text-primary"></i>Administrace e-shopu</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_orders_admin.php')
    ?>" href="eshop_orders_admin.php">
                                <i class="bi bi-receipt me-2 text-success"></i>Objednávky K4</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_bank_admin.php')
    ?>" href="eshop_bank_admin.php">
                                <i class="bi bi-bank me-2 text-dark"></i>Bankovní účet e-shopu</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_catalog_publication_admin.php')
    ?>" href="eshop_catalog_publication_admin.php">
                                <i class="bi bi-eye me-2 text-success"></i>Aktivace katalogu</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_categories_admin.php')
    ?>" href="eshop_categories_admin.php">
                                <i class="bi bi-diagram-3 me-2 text-primary"></i>Kategorie e-shopu</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_catalog_admin.php')
    ?>" href="eshop_catalog_admin.php">
                                <i class="bi bi-boxes me-2 text-dark"></i>Správa katalogu</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_attributes_admin.php')
    ?>" href="eshop_attributes_admin.php">
                                <i class="bi bi-sliders me-2 text-primary"></i>Parametry produktů</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_member_prices_admin.php')
    ?>" href="eshop_member_prices_admin.php">
                                <i class="bi bi-tags me-2 text-success"></i>Klubové ceny</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_events_admin.php')
    ?>" href="eshop_events_admin.php">
                                <i class="bi bi-calendar-event me-2 text-primary"></i>Klubové akce a soupisky</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_notifications_admin.php')
    ?>" href="eshop_notifications_admin.php">
                                <i class="bi bi-envelope-exclamation me-2 text-warning"></i>Fronta e-mailů K3</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Členové a KIS</h6></li>
                            <li><a class="dropdown-item<?=
     _dropActive('member_charge_reminders_admin.php')
    ?>" href="member_charge_reminders_admin.php">
                                <i class="bi bi-bell me-2 text-warning"></i>Připomínky plateb</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('member_charges_admin.php')
    ?>" href="member_charges_admin.php">
                                <i class="bi bi-cash-stack me-2 text-warning"></i>Členské předpisy</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('member_fee_plans_admin.php')
    ?>" href="member_fee_plans_admin.php">
                                <i class="bi bi-calendar-month me-2 text-warning"></i>Měsíční plány příspěvků</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('member_fee_annual_admin.php')
    ?>" href="member_fee_annual_admin.php">
                                <i class="bi bi-calendar-check me-2 text-warning"></i>Roční hromadná úhrada</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('club_finance_admin.php')
    ?>" href="club_finance_admin.php">
                                <i class="bi bi-sliders me-2 text-warning"></i>Finanční pravidla přihlášek</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('family_weekly_summaries_admin.php')
    ?>" href="family_weekly_summaries_admin.php">
                                <i class="bi bi-envelope-paper me-2 text-info"></i>Týdenní souhrny</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_identity_admin.php')
    ?>" href="eshop_identity_admin.php">
                                <i class="bi bi-people me-2 text-primary"></i>Účty, rodiče a sportovci</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('kis_person_import_admin.php')
    ?>" href="kis_person_import_admin.php">
                                <i class="bi bi-person-lines-fill me-2 text-primary"></i>Osoby a kontakty ze starého KIS</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('kis_child_access_admin.php')
    ?>" href="kis_child_access_admin.php">
                                <i class="bi bi-person-badge me-2 text-primary"></i>Přístupy sportovců</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('person_audit_admin.php')
    ?>" href="person_audit_admin.php">
                                <i class="bi bi-clock-history me-2 text-primary"></i>Auditní osa osoby</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Provoz</h6></li>
                            <li><a class="dropdown-item<?=
     _dropActive('provozni_prehled_admin.php')
    ?>" href="provozni_prehled_admin.php">
                                <i class="bi bi-speedometer2 me-2 text-primary"></i>Provozní přehled</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sports_data_quality_admin.php')
    ?>" href="sports_data_quality_admin.php">
                                <i class="bi bi-clipboard-data me-2 text-primary"></i>Kvalita sportovních dat</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sports_import_review_admin.php')
    ?>" href="sports_import_review_admin.php">
                                <i class="bi bi-clipboard-check me-2 text-primary"></i>Příprava importu měření</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('eshop_order_expiry_admin.php')
    ?>" href="eshop_order_expiry_admin.php">
                                <i class="bi bi-hourglass-split me-2 text-warning"></i>Expirace nezaplacených objednávek</a></li>
                            <?php
if (defined('JE_LOKALNE') && JE_LOKALNE):
    ?>
                            <li><a class="dropdown-item<?=
     _dropActive('testovaci_scenare.php')
    ?>" href="testovaci_scenare.php">
                                <i class="bi bi-check2-square me-2 text-warning"></i>Finalizace M2</a></li>
                            <?php
endif;
    ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Nastavení a firemní evidence</h6></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_sportovist.php')
    ?>" href="sprava_sportovist.php">
                                <i class="bi bi-building-gear me-2"></i>Správa sportovišť</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('sprava_treneru.php')
    ?>" href="sprava_treneru.php">
                                <i class="bi bi-person-gear me-2"></i>Správa trenérů</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('nastaveni_opravneni.php')
    ?>" href="nastaveni_opravneni.php">
                                <i class="bi bi-sliders me-2 text-danger"></i>Nastavení oprávnění</a></li>
                            <li><a class="dropdown-item<?=
     _dropActive('nastaveni_zadavani.php')
    ?>" href="nastaveni_zadavani.php">
                                <i class="bi bi-calendar-lock me-2"></i>Okno pro zadávání</a></li>
                            <?php
if (canAccess('auditlog')):
    ?>
                            <li><a class="dropdown-item" href="auditlog/seznam.php">
                                <i class="bi bi-journal-text me-2"></i>Audit log</a></li>
                            <?php
endif;
    ?>
                            <li><a class="dropdown-item" href="vozidla/seznam.php">
                                <i class="bi bi-car-front me-2"></i>Vozidla</a></li>
                            <li><a class="dropdown-item" href="uctenky/seznam.php">
                                <i class="bi bi-receipt me-2"></i>Účtenky</a></li>
                            <li><a class="dropdown-item" href="udalosti/seznam.php">
                                <i class="bi bi-calendar-event me-2"></i>Události</a></li>
                        </ul>
                    </li>
                    <?php
endif;
    ?>

                <?php else:
                    ?>
                    <li class="nav-item"><a class="nav-link" href="booking/eshop.php"><i class="bi bi-bag me-1"></i>E-shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="booking/krouzky.php"><i class="bi bi-calendar-event me-1"></i>Akce</a></li>
                    <li class="nav-item"><a class="nav-link" href="booking/velodrom.php"><i class="bi bi-bicycle me-1"></i>Velodrom</a></li>
                    <li class="nav-item"><a class="nav-link" href="booking/treninky.php"><i class="bi bi-calendar3 me-1"></i>Tréninky</a></li>
                <?php
                endif;
?>
            </ul>
            <?php
endif;
?>

            <?php
if (false && $is_logged_in):
    ?>
            <!-- Globální hledání -->
            <div id="globalSearchWrap" class="mx-xxl-3 my-2 my-xxl-0" role="search">
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
            <?php
endif;
?>

            <ul class="navbar-nav align-items-xxl-center gap-1">
                <!-- Tmavý režim + zkratky -->
                <li class="nav-item d-flex align-items-center gap-1">
                    <?php
if ($is_logged_in):
    ?>
                    <button id="pushToggleBtn" class="btn btn-sm btn-outline-secondary border-0 text-white-50"
                            style="display:none;padding:4px 8px" title="Push notifikace">
                        <i class="bi bi-bell-slash"></i>
                    </button>
                    <?php
endif;
?>
                    <button id="themeToggle" class="btn btn-sm btn-outline-secondary border-0 text-white-50"
                            data-app-csp-handler="<?=
htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')
?>" onclick="toggleTheme()" title="Přepnout tmavý/světlý režim" style="padding:4px 8px">
                        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary border-0 text-white-50"
                            data-bs-toggle="modal" data-bs-target="#shortcutsModal"
                            title="Klávesové zkratky (?)" style="padding:4px 8px">
                        <i class="bi bi-keyboard"></i>
                    </button>
                    <?php
if ($is_logged_in):
    ?>
                    <a href="<?=
     publicShellH(appUiUrl('booking/eshop.php'))
    ?>" target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-secondary border-0 text-white-50"
                       title="Otevřít veřejný portál v nové záložce" style="padding:4px 8px">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <?php
endif;
?>
                </li>
                <?php
if ($is_logged_in):
    ?>
                    <?php
if (count($staff_available) > 1):
    ?>
                    <li class="nav-item dropdown">
                        <button class="btn btn-sm btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-<?=
     htmlspecialchars((string)($staff_active['icon'] ?? 'person-workspace'))
    ?> me-1"></i><?=

        staffIsSuperadmin() ? 'Superadmin · ' : ''
    ?><?=
     htmlspecialchars((string)($staff_active['short_label'] ?? 'Pozice'))

    ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width:270px">
                            <li><h6 class="dropdown-header">Přepnout pracovní pozici</h6></li>
                            <?php
foreach ($staff_available as $staff_code): $staff_position = $staff_definitions[$staff_code];

    ?>
                            <li>
                                <form method="post" action="<?=
     publicShellH(appUiUrl('prepnout_pracovni_pozici.php'))
    ?>">
                                    <?=
     csrf_field()
    ?>
                                    <input type="hidden" name="position" value="<?=
     htmlspecialchars($staff_code)
    ?>">
                                    <button class="dropdown-item<?=
     $staff_code === $staff_active_code ? ' active' : ''
    ?>" <?=

                                    $staff_code === $staff_active_code ? 'disabled' : ''
    ?>><i class="bi bi-<?=
     htmlspecialchars((string)$staff_position['icon'])

    ?> me-2"></i><?=
     htmlspecialchars((string)$staff_position['label'])
    ?></button>
                                </form>
                            </li>
                            <?php
endforeach;
    ?>
                        </ul>
                    </li>
                    <?php else:
                        ?>
                    <li class="nav-item"><span class="badge text-bg-secondary"><i class="bi bi-<?=
                         htmlspecialchars((string)($staff_active['icon']
                                                        ?? 'person-workspace'))
                        ?> me-1"></i><?=
                         htmlspecialchars((string)($staff_active['short_label'] ?? 'Pozice'))
                        ?></span></li>
                    <?php
                    endif;
?>
                    <li class="nav-item">
                        <span class="navbar-text text-light small">
                            <i class="bi bi-person-circle me-1"></i><?=
 htmlspecialchars($trener_jmeno)
?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="<?=
 publicShellH(appUiUrl('logout.php'))
?>" class="d-inline">
                            <?=
 csrf_field()
?>
                            <button type="submit" class="nav-link text-light border-0 bg-transparent">
                                <i class="bi bi-box-arrow-right me-1"></i>Odhlásit
                            </button>
                        </form>
                    </li>
                <?php else:
                    ?>
                    <?php
if ($is_customer):
    ?>
                      <li class="nav-item"><a class="nav-link" href="booking/moje_osoby.php"><i class="bi bi-person-circle me-1"></i>Můj účet</a></li>
                    <?php elseif ($is_athlete):
                        ?>
                      <li class="nav-item"><a class="nav-link" href="booking/muj_sport.php"><i class="bi bi-person-circle me-1"></i>Můj sport</a></li>
                    <?php else:
                        ?>
                      <li class="nav-item"><a class="nav-link" href="booking/prihlaseni.php"><i class="bi bi-person me-1"></i>Rodič / zákazník</a></li>
                    <?php
                    endif;
                    ?>
                    <li class="nav-item"><a class="nav-link" href="login.php"><i class="bi bi-shield-lock me-1"></i>Trenér</a></li>
                <?php
                endif;
?>
            </ul>
        </div>
    </div>
</nav>
<?php
if ($is_logged_in && is_array($staff_active)) {
    appAdminSidebar($staff_active_code, staffCurrentRoute(__DIR__) ?? $_currentPage, defined('JE_LOKALNE')
    && JE_LOKALNE === true);
}
?>

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
                        <tr><td><kbd>N</kbd></td><td class="text-muted small">Nový trénink</td></tr>
                        <tr><td><kbd>P</kbd></td><td class="text-muted small">Plánovač</td></tr>
                        <tr><td><kbd>K</kbd></td><td class="text-muted small">Kalendář sportovišť</td></tr>
                        <tr><td><kbd>?</kbd></td><td class="text-muted small">Tato nápověda</td></tr>
                        <tr><td><kbd>Esc</kbd></td><td class="text-muted small">Zavřít / zrušit</td></tr>
                        <tr><td><kbd>D</kbd></td><td class="text-muted small">Přepnout světlý / tmavý režim</td></tr>
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
<script nonce="<?=
htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')
?>">
// ── Toast notifikace — globální funkce ──────────────────────────────────────
// Bezpečné společné toast notifikace poskytuje assets/app-ui.js.

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
            if (!e.shiftKey) window.location.href = <?=
 json_encode(appUiUrl('formular.php'))
?>;
            break;
        case 'p': case 'P':
            window.location.href = <?=
 json_encode(appUiUrl('planovac.php'))
?>;
            break;
        case 'k': case 'K':
            window.location.href = <?=
 json_encode(appUiUrl('kalendar_sportovist.php'))
?>;
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
<?php
if (!empty($_flashToasts)):
    ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php
foreach ($_flashToasts as $t):
    ?>
    showToast(<?=
     json_encode(htmlspecialchars($t['message'], ENT_QUOTES, 'UTF-8'))
    ?>, <?=
     json_encode($t['type'])
    ?>);
    <?php
endforeach;
    ?>
});
<?php
endif;
?>
</script>

<?php
// Absolutní URL-cesta k search endpointu — funguje z kořene i podadresářů
$_gsSearchUrl = appUiUrl('ajax_global_search.php');

?>
<?php
if ($is_logged_in):
    ?>
<script type="application/json" id="appStaffHeaderConfig" nonce="<?= htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8') ?>">
<?= json_encode([
    'pushPublicKey' => $pdo->query("SELECT hodnota FROM nastaveni WHERE klic='push_vapid_public'")->fetchColumn() ?: '',
    'serviceWorkerUrl' => appUiUrl('sw.js'),
    'appBaseUrl' => appUiUrl(''),
    'pushEndpoint' => appUiUrl('push_subscribe.php'),
    'csrfToken' => csrf_token(),
    'searchUrl' => $_gsSearchUrl,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<script nonce="<?= htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8') ?>" src="<?=
htmlspecialchars(
    appUiUrl('assets/staff-header.222b64217870ab726e3ac50483b00fd54e4e0d79251917dfb5162b5de275c8ee.js'),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
)
    ?>" integrity="sha384-l6R7kkGPXehg6IQcj844whVo0+4vy/vxYBcsa5h4bdRDGk7PHltoFyg3iYLlkyfv" crossorigin="anonymous"></script>
<?php
endif;
?>
