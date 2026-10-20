<?php
declare(strict_types=1);

require_once __DIR__.'/ui_shell.php';

/** The same role-filtered menu is used by every staff page. */
function appAdminSidebar(string $position,string $route,bool $isLocal): void
{
    $groups=staffPositionMenuGroups($position,$isLocal);
    $primary=array_fill_keys(staffPositionPrimaryRoutes()[$position]??[],true);
    $shopPage=str_starts_with($route,'eshop_') && $position==='catalog_manager';
    $current=$route;
    if (in_array($route,['eshop_produkt_novy_admin.php','eshop_varianta_admin.php','eshop_produkt_stahnout_admin.php'],true)) $current='eshop_produkt_admin.php';
    ?>
    <script nonce="<?=publicShellH(app_csp_nonce())?>">document.body.classList.add('app-admin-shell');</script>
    <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appAdminSidebar" aria-label="Pracovní nabídka">
      <div class="offcanvas-header"><strong>Pracovní nabídka</strong><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appAdminSidebar" aria-label="Zavřít nabídku"></button></div>
      <div class="offcanvas-body">
        <a class="app-sidebar-home<?= $route==='pracovni_pozice.php'?' active':'' ?>" href="<?=publicShellH(appUiUrl('pracovni_pozice.php'))?>"><i class="bi bi-grid" aria-hidden="true"></i> Pracovní rozcestník</a>
        <?php foreach ($groups as $group): $main=[];$advanced=[];
          foreach ($group['items'] as $item) {if(isset($primary[$item['route']]))$main[]=$item;else $advanced[]=$item;}
          if ($main===[] && $advanced===[]) continue;
        ?>
        <section class="app-sidebar-group">
          <h2><?=publicShellH($group['label'])?></h2>
          <?php foreach ($main as $item): appAdminSidebarLink($item,$current); endforeach; ?>
          <?php if ($advanced!==[]): $open=in_array($current,array_column($advanced,'route'),true); ?>
          <details<?=$open?' open':''?>><summary>Další nástroje</summary><?php foreach ($advanced as $item): appAdminSidebarLink($item,$current); endforeach; ?></details>
          <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <?php if ($shopPage): ?><a class="btn btn-primary w-100 mt-3" href="<?=publicShellH(appUiUrl('eshop_produkt_novy_admin.php'))?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Přidat produkt</a><?php endif; ?>
        <div class="app-sidebar-footer">KOVO Praha · KIS</div>
      </div>
    </aside>
    <?php
}

/** @param array<string,mixed> $item */
function appAdminSidebarLink(array $item,string $route): void
{
    $active=$route===$item['route'];
    echo '<a class="app-sidebar-link'.($active?' active':'').'" href="'.publicShellH(appUiUrl((string)$item['route'])).'"'.($active?' aria-current="page"':'').'>';
    echo '<i class="bi bi-'.publicShellH($item['icon']??'circle').'" aria-hidden="true"></i><span>'.publicShellH($item['label']).'</span></a>';
}
