<?php
declare(strict_types=1);

if ($screen==='new'):
?>
<form method="post"><?php shopAdminHidden('create'); ?>
  <section class="app-form-section"><h2>Základní údaje</h2>
    <div class="app-form-row"><label for="new-name">Název produktu</label><input class="form-control" name="name" id="new-name" required maxlength="255" autofocus value="<?=shopAdminH($_POST['name']??'')?>"></div>
    <div class="app-form-row"><label for="new-description">Krátký popis</label><textarea class="form-control" name="short_description" id="new-description" rows="3" maxlength="4000"><?=shopAdminH($_POST['short_description']??'')?></textarea></div>
    <div class="app-form-row"><label for="new-type">Druh nabídky</label><select class="form-select" name="offer_type" id="new-type"><?php foreach(['goods'=>'Zboží','club_event'=>'Klubová akce','camp'=>'Tábor / soustředění'] as $key=>$label): ?><option value="<?=$key?>"<?=($_POST['offer_type']??'goods')===$key?' selected':''?>><?=$label?></option><?php endforeach; ?></select></div>
    <div class="app-form-row"><label for="new-delivery">Předání</label><select class="form-select" name="delivery_mode" id="new-delivery"><option value="pickup_only">Pouze osobní odběr na velodromu</option><option value="shipping_eligible"<?=($_POST['delivery_mode']??'')==='shipping_eligible'?' selected':''?>>Osobní odběr i zaslání (pouze zboží)</option></select></div>
  </section>
  <?php shopAdminVariantFields([], $definitions); shopAdminDecision('Vytvořit produkt','Založení produktu do katalogu.'); ?>
</form>
<?php elseif ($screen==='withdraw'&&$product): ?>
<div class="alert alert-warning"><strong><?=$product['catalog_status']==='draft'?'Koncept se přesune mezi stažené produkty.':'Produkt se přestane nabízet v e-shopu.'?></strong><div>Existující objednávky, skladové pohyby a historie zůstanou zachované.</div></div>
<p><?=shopAdminH($product['name'])?></p>
<form method="post"><?php shopAdminHidden('unpublish',$productId); shopAdminDecision($product['catalog_status']==='draft'?'Archivovat koncept':'Potvrdit stažení'); ?></form>
<?php elseif ($screen==='variant'&&$product): ?>
<p class="text-muted"><?=shopAdminH($product['name'])?></p>
<?php if ($product['offer_type']==='program'&&!$variant): ?>
<div class="alert alert-info">Varianty kroužku spravujte přes <a href="club_program_offers_admin.php">nabídky a termíny</a>, které řídí kapacitu a zápis do soupisky.</div>
<?php else: ?>
<form method="post" data-shop-variant-form><?php shopAdminHidden($variant?'update_variant':'add_variants',$productId); ?>
  <?php if ($variant): ?><input type="hidden" name="variant_id" value="<?=$variantId?>">
  <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2"><span>Aktuální sklad: <?=shopAdminStock(array_merge($variant,['offer_type'=>$product['offer_type']]))?></span><?php if ($product['offer_type']!=='program'): ?><a href="<?=shopAdminH(shopAdminUrl($productId,'inventory').'&stock_variant='.$variantId)?>">Upravit skladový stav</a><?php endif; ?></div>
  <?php else: ?>
  <section class="app-form-section"><h2>Přidat jednu nebo více variant</h2><div class="app-form-row"><label for="new-colors">Barvy</label><div><input class="form-control" id="new-colors" name="new_colors" maxlength="2000" data-shop-colors placeholder="Např. Modrá, Růžová" value="<?=shopAdminH($_POST['new_colors']??'')?>"><div class="form-text">Více hodnot oddělte čárkou. Pro variantu bez barvy ponechte prázdné.</div></div></div><div class="app-form-row"><label for="new-sizes">Velikosti</label><div><input class="form-control" id="new-sizes" name="new_sizes" maxlength="2000" data-shop-sizes placeholder="Např. 128, 134, 140" value="<?=shopAdminH($_POST['new_sizes']??'')?>"><div class="form-text">Vznikne každá kombinace barvy a velikosti. Nejvýše 64 variant najednou.</div></div></div><div class="app-form-row"><label>Náhled kombinací</label><div data-shop-combination-preview aria-live="polite"></div></div></section>
  <?php endif; ?>
  <?php
    $displayVariant=$variant??[];
    if(isset($_POST['attribute_keys'])) $displayVariant['attributes_json']=shopAdminPostedAttributes($_POST);
    shopAdminVariantFields($displayVariant,$definitions,$variant!==null,$product['offer_type']==='program');
    if(!$variant): ?><div class="form-text">Cena a počáteční sklad platí pro každou novou kombinaci. Existující varianta se stejnými parametry zablokuje celý zápis.</div><?php endif;
    shopAdminDecision($variant?'Uložit variantu':'Přidat varianty',$variant?'Úprava varianty produktu.':'Přidání variant produktu.');
  ?>
</form>
<?php endif; ?>
<?php elseif (in_array($screen,['prices','stock'],true)):
    shopAdminFilters(true);
    if ($screen==='prices'): ?><form method="post"><?php shopAdminHidden('save_prices'); endif; ?>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Produkt / varianta</th><th>Kód</th><th class="text-end">Skladem</th><th><?=$screen==='prices'?'Cena v Kč':'Dostupnost'?></th><th></th></tr></thead><tbody>
<?php foreach($page['rows'] as $row): ?>
<tr><td><a href="<?=shopAdminH(shopAdminUrl((int)$row['product_id']))?>"><?=shopAdminH($row['product_name'])?></a><small><?=shopAdminH(shopAdminVariantName($row))?></small></td><td><?=shopAdminH($row['sku'])?></td><td class="text-end"><?=shopAdminStock($row)?></td>
  <?php if($screen==='prices'): ?><td><input class="form-control app-price-input" name="prices[<?=(int)$row['id']?>]" aria-label="Cena <?=shopAdminH($row['sku'])?>" inputmode="decimal" required value="<?=shopAdminH($_POST['prices'][$row['id']]??shopAdminMoney($row['amount_minor']))?>"><input type="hidden" name="expected_prices[<?=(int)$row['id']?>]" value="<?=shopAdminH($row['amount_minor'])?>"></td><td><a href="eshop_varianta_admin.php?id=<?=(int)$row['product_id']?>&amp;variant_id=<?=(int)$row['id']?>">Upravit</a></td>
  <?php else: ?><td><?=shopAdminAvailability($row)?></td><td><?php if($row['offer_type']!=='program'): ?><a class="btn btn-sm btn-outline-primary" href="<?=shopAdminH(shopAdminUrl((int)$row['product_id'],'inventory').'&stock_variant='.(int)$row['id'])?>">Upravit stav</a><?php endif; ?></td><?php endif; ?>
</tr><?php endforeach; ?>
<?php if($page['rows']===[]): ?><tr><td colspan="5" class="text-muted text-center py-4">Žádná varianta neodpovídá zvoleným filtrům.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if($screen==='prices'): if($page['rows']!==[])shopAdminDecision('Uložit ceny','Úprava cen variant v ceníku.'); ?></form><?php endif; shopAdminPagination($page); ?>
<?php elseif($product===null):
    shopAdminFilters();
?>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th></th><th>Název</th><th class="text-end">Cena od</th><th class="text-end">Skladem</th><th>Stav</th></tr></thead><tbody>
<?php foreach($page['rows'] as $row): $image=shopStorefrontSafeImageUrl((string)($row['image_url']??'')); ?>
  <tr><td><?php if($image!==null): ?><img class="app-product-thumb" src="<?=shopAdminH($image)?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-box-seam text-muted fs-4" aria-hidden="true"></i><?php endif; ?></td><td><a href="<?=shopAdminH(shopAdminUrl((int)$row['id']))?>"><?=shopAdminH($row['name'])?></a><small><?=shopAdminH(shopAdminOfferLabel($row['offer_type']).' · '.shopAdminVariantCount((int)$row['variant_count']))?></small></td><td class="text-end text-nowrap"><?=shopAdminH(shopAdminMoney($row['min_amount_minor']))?> Kč</td><td class="text-end"><?php if($row['offer_type']==='program'): ?><small>Kapacita termínu</small><?php elseif($row['stock_total']===null): ?><small>Sklad se nesleduje</small><?php else: ?><span class="app-stock-count <?=(float)$row['stock_total']<=0?'text-danger':''?>"><?=shopAdminH(shopAdminStockText($row['stock_total']))?></span><small><?=(int)$row['unmanaged_count']>0?'část variant bez sledování':'součet variant'?></small><?php endif; ?></td><td><?=shopAdminBadge($row)?></td></tr>
<?php endforeach; ?>
<?php if($page['rows']===[]): ?><tr><td colspan="5" class="text-center text-muted py-4">Žádný produkt neodpovídá hledání.</td></tr><?php endif; ?>
</tbody></table></div>
<?php shopAdminPagination($page); ?>
<?php else: ?>
<nav class="nav nav-tabs app-product-tabs" aria-label="Detail produktu">
<?php foreach(['main'=>'Hlavní údaje','variants'=>'Varianty a ceny','inventory'=>'Sklad','images'=>'Obrázky','category'=>'Kategorie','history'=>'Historie'] as $key=>$label): ?><a class="nav-link<?=$tab===$key?' active':''?>" href="<?=shopAdminH(shopAdminUrl($productId,$key))?>"<?=$tab===$key?' aria-current="page"':''?>><?=$label?></a><?php endforeach; ?>
</nav>
<?php if($tab==='variants'): ?>
<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3"><h2 class="h5 mb-0 text-primary">Varianty produktu <span class="small text-muted">(<?=count($detail['variants'])?>)</span></h2><a class="btn btn-primary" href="<?=$product['offer_type']==='program'?'club_program_offers_admin.php':'eshop_varianta_admin.php?id='.$productId?>"><?=$product['offer_type']==='program'?'Spravovat nabídky kroužku':'+ Přidat varianty'?></a></div>
<form method="post"><?php shopAdminHidden('save_prices',$productId); ?>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Varianta / kód</th><th class="text-end">Cena v Kč</th><th class="text-end">Skladem</th><th>Dostupnost</th><th></th></tr></thead><tbody>
<?php foreach($detail['variants'] as $row): $row['offer_type']=$product['offer_type']; ?>
<tr><td><a href="eshop_varianta_admin.php?id=<?=$productId?>&amp;variant_id=<?=(int)$row['id']?>"><?=shopAdminH(shopAdminVariantName($row))?></a><small><?=shopAdminH($row['sku'])?></small></td><td class="text-end"><input class="form-control app-price-input ms-auto" name="prices[<?=(int)$row['id']?>]" aria-label="Cena <?=shopAdminH($row['sku'])?>" inputmode="decimal" required value="<?=shopAdminH($_POST['prices'][$row['id']]??shopAdminMoney($row['amount_minor']))?>"><input type="hidden" name="expected_prices[<?=(int)$row['id']?>]" value="<?=shopAdminH($row['amount_minor'])?>"></td><td class="text-end"><?=shopAdminStock($row)?></td><td><?=shopAdminAvailability($row)?></td><td><a href="eshop_varianta_admin.php?id=<?=$productId?>&amp;variant_id=<?=(int)$row['id']?>">Upravit</a></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if($detail['variants']!==[])shopAdminDecision('Uložit ceny','Úprava cen variant produktu.'); ?></form>
<?php elseif($tab==='inventory'):
    $stockId=max(0,(int)($_GET['stock_variant']??$_POST['stock_variant_id']??0));$stockVariant=null;
    foreach($detail['variants'] as $row)if((int)$row['id']===$stockId)$stockVariant=$row;
?>
<h2 class="h5 text-primary mb-3">Skladové zásoby</h2>
<?php if($product['offer_type']==='program'): ?><div class="alert alert-info">Kroužek nemá kusový sklad. Dostupnost řídí kapacita <a href="club_program_offers_admin.php">nabídek a termínů</a>.</div>
<?php else: ?>
<?php if($stockVariant): ?><section class="border p-3 mb-4"><h3 class="h6">Upravit stav: <?=shopAdminH(shopAdminVariantName($stockVariant))?></h3><form method="post" data-shop-stock-form><?php shopAdminHidden('adjust_stock',$productId,'inventory'); ?><input type="hidden" name="stock_variant_id" value="<?=$stockId?>"><input type="hidden" name="expected_stock" value="<?=shopAdminH($stockVariant['stock_quantity_decimal']??'__NULL__')?>"><div class="app-form-row"><label>Aktuální stav</label><div class="pt-2"><?=shopAdminStock($stockVariant)?></div></div><div class="app-form-row"><label for="stock-target">Nový počet</label><input class="form-control" name="stock_quantity_decimal" id="stock-target" inputmode="decimal" required value="<?=shopAdminH($_POST['stock_quantity_decimal']??max(0,(float)($stockVariant['stock_quantity_decimal']??0)))?>"></div><?php shopAdminDecision('Potvrdit skladovou změnu'); ?></form></section><?php endif; ?>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Varianta / kód</th><th class="text-end">Skladem</th><th>Dostupnost</th><th></th></tr></thead><tbody><?php foreach($detail['variants'] as $row): ?><tr><td><?=shopAdminH(shopAdminVariantName($row))?><small><?=shopAdminH($row['sku'])?></small></td><td class="text-end"><?=shopAdminStock($row)?></td><td><?=shopAdminAvailability($row)?></td><td><a class="btn btn-outline-primary btn-sm" href="<?=shopAdminH(shopAdminUrl($productId,'inventory').'&stock_variant='.(int)$row['id'])?>">Upravit stav</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php elseif($tab==='main'): ?>
<form method="post"><?php shopAdminHidden('save_product',$productId,'main'); ?>
<section class="app-form-section"><h2>Základní údaje</h2>
  <div class="app-form-row"><label for="product-name">Název produktu</label><input class="form-control" id="product-name" name="name" maxlength="255" required value="<?=shopAdminH($_POST['name']??$product['name'])?>"></div>
  <div class="app-form-row"><label for="product-description">Krátký popis</label><textarea class="form-control" id="product-description" name="short_description" maxlength="4000" rows="4"><?=shopAdminH($_POST['short_description']??html_entity_decode(strip_tags((string)$product['short_description']),ENT_QUOTES,'UTF-8'))?></textarea></div>
  <input type="hidden" name="offer_type" value="<?=shopAdminH($product['offer_type'])?>"><input type="hidden" name="item_type" value="<?=shopAdminH($product['item_type']?:'product')?>"><input type="hidden" name="visibility" value="visible">
  <?php if($product['offer_type']==='goods'): ?><div class="app-form-row"><label for="product-delivery">Předání</label><select class="form-select" id="product-delivery" name="delivery_mode"><option value="pickup_only">Pouze osobní odběr na velodromu</option><option value="shipping_eligible"<?=($_POST['delivery_mode']??$product['delivery_mode']??'')==='shipping_eligible'?' selected':''?>>Osobní odběr i zaslání</option></select></div><?php endif; ?>
</section><?php shopAdminDecision('Uložit údaje','Úprava údajů produktu.'); ?></form>
<section class="app-form-section mt-5"><h2>Zobrazení v e-shopu</h2>
<?php $readiness=shopCatalogPublicationReadiness($pdo,$productId);if($readiness['ready']): ?>
<form method="post"><?php shopAdminHidden('publish',$productId,'main'); ?><div class="app-form-row"><label for="public-name">Název pro zákazníky</label><input class="form-control" id="public-name" name="public_name" maxlength="255" required value="<?=shopAdminH($_POST['public_name']??($publication['public_name']??$product['name']))?>"></div><div class="app-form-row"><label for="public-summary">Popis pro zákazníky</label><textarea class="form-control" id="public-summary" name="public_summary" maxlength="1000" rows="3"><?=shopAdminH($_POST['public_summary']??($publication['public_summary']??html_entity_decode(strip_tags((string)$product['short_description']),ENT_QUOTES,'UTF-8')))?></textarea></div><?php shopAdminDecision(($publication['status']??'')==='active'?'Uložit zobrazení':'Zveřejnit produkt','Uložení zobrazení produktu v e-shopu.'); ?></form>
<?php else: ?><div class="alert alert-warning"><strong>Ještě nelze zveřejnit.</strong><ul class="mb-0 mt-2"><?php foreach($readiness['blockers'] as $blocker): ?><li><?=shopAdminH($blocker)?></li><?php endforeach; ?></ul></div><?php endif; ?></section>
<?php elseif($tab==='images'): ?>
<h2 class="h5 text-primary mb-3">Obrázky produktu</h2>
<div class="app-image-grid mb-4"><?php foreach($detail['images'] as $image): $url=shopStorefrontSafeImageUrl((string)$image['image_url']); ?>
<article class="app-gallery-item"><?php if($url!==null): ?><img src="<?=shopAdminH($url)?>" alt="<?=shopAdminH($product['name'])?>"><?php endif; ?>
<details class="mt-3"><summary>Pořadí a odebrání</summary><form method="post" class="mt-2"><?php shopAdminHidden('reorder_image',$productId,'images'); ?><input type="hidden" name="image_id" value="<?=(int)$image['id']?>"><label class="form-label">Pořadí<input class="form-control" type="number" name="sort_order" min="-100000" max="100000" value="<?=(int)$image['sort_order']?>"></label><?php shopAdminDecision('Uložit pořadí','Změna pořadí obrázku.'); ?></form><form method="post" class="mt-3"><?php shopAdminHidden('remove_image',$productId,'images'); ?><input type="hidden" name="image_id" value="<?=(int)$image['id']?>"><?php shopAdminDecision('Odebrat obrázek'); ?></form></details></article>
<?php endforeach; ?></div>
<form method="post" enctype="multipart/form-data"><?php shopAdminHidden('add_image',$productId,'images'); ?><section class="app-form-section"><h2>Přidat obrázek</h2><div class="app-form-row"><label for="product-image">JPG nebo PNG</label><div><input class="form-control" type="file" id="product-image" name="product_image" accept="image/jpeg,image/png" required><div class="form-text">Nejvýše 5 MB a 6000 × 6000 px. Metadata se odstraní.</div></div></div><input type="hidden" name="sort_order" value="<?=count($detail['images'])?>"></section><?php shopAdminDecision('Nahrát obrázek','Přidání obrázku produktu.'); ?></form>
<?php elseif($tab==='category'):
    $nodes=shopCategoryNodes($pdo);$query=$pdo->prepare('SELECT category_path,is_default FROM shop_product_categories WHERE product_id=? ORDER BY is_default DESC,sort_order,id');$query->execute([$productId]);$assigned=$query->fetchAll(PDO::FETCH_ASSOC);$paths=$_POST['category_paths']??array_column($assigned,'category_path');$default=$_POST['default_category']??($assigned[0]['category_path']??'');
?>
<form method="post"><?php shopAdminHidden('categories',$productId,'category'); ?><section class="app-form-section"><h2>Zařazení produktu</h2><div class="app-form-row"><label for="default-category">Výchozí kategorie</label><select class="form-select" id="default-category" name="default_category"><option value="">Bez kategorie</option><?php foreach($nodes as $path=>$node): ?><option value="<?=shopAdminH($path)?>"<?=$default===$path?' selected':''?>><?=shopAdminH($path)?></option><?php endforeach; ?></select></div><div class="app-form-row"><label>Zařadit do kategorií</label><div><?php foreach($nodes as $path=>$node): ?><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="category_paths[]" value="<?=shopAdminH($path)?>"<?=in_array($path,(array)$paths,true)?' checked':''?>><?=shopAdminH($path)?></label><?php endforeach; ?></div></div></section><?php shopAdminDecision('Uložit kategorie','Úprava kategorií produktu.'); ?></form>
<?php elseif($tab==='history'): ?>
<h2 class="h5 text-primary mb-3">Historie úprav</h2><div class="table-responsive"><table class="table"><thead><tr><th>Čas</th><th>Akce</th><th>Varianta</th><th>Správce</th><th>Důvod</th></tr></thead><tbody><?php foreach($detail['events'] as $event): ?><tr><td><?=shopAdminH($event['created_at'])?></td><td><?=shopAdminH(shopAdminEventLabel($event['action']))?></td><td><?=shopAdminH($event['variant_id']??'Produkt')?></td><td><?=shopAdminH($event['actor_name']?:'#'.$event['actor_id'])?></td><td><?=shopAdminH($event['reason'])?></td></tr><?php endforeach; ?><?php if($detail['events']===[]): ?><tr><td colspan="5" class="text-muted">Zatím bez úprav.</td></tr><?php endif; ?></tbody></table></div>
<?php $query=$pdo->prepare('SELECT m.*,v.sku FROM shop_inventory_movements m JOIN shop_variants v ON v.id=m.variant_id WHERE v.product_id=? ORDER BY m.id DESC LIMIT 100');$query->execute([$productId]);$movements=$query->fetchAll(PDO::FETCH_ASSOC); ?>
<h2 class="h5 text-primary mt-4 mb-3">Skladové pohyby</h2><div class="table-responsive"><table class="table"><thead><tr><th>Čas</th><th>Varianta</th><th>Změna</th><th>Nový stav</th><th>Důvod</th></tr></thead><tbody><?php foreach($movements as $move): ?><tr><td><?=shopAdminH($move['created_at'])?></td><td><?=shopAdminH($move['sku'])?></td><td><?=shopAdminH(shopAdminStockText($move['quantity_delta_decimal']))?></td><td><?=shopAdminH(shopAdminStockText($move['stock_after_decimal']))?></td><td><?=shopAdminH($move['reason']??shopAdminEventLabel($move['movement_type']))?></td></tr><?php endforeach; ?><?php if($movements===[]): ?><tr><td colspan="5" class="text-muted">Zatím bez skladových pohybů.</td></tr><?php endif; ?></tbody></table></div>
<?php endif; endif; ?>
<?php
function shopAdminAvailability(array $variant): string
{
    if(array_key_exists('visible',$variant)&&$variant['visible']!==null&&(int)$variant['visible']===0)return '<span class="badge text-bg-secondary">Skrytá varianta</span>';
    if(($variant['catalog_status']??'')!=='active')return '<span class="badge text-bg-secondary">'.(($variant['catalog_status']??'')==='draft'?'Koncept':'Neaktivní').'</span>';
    if(($variant['offer_type']??'')==='program')return '<span class="small text-muted">Podle termínu</span>';
    if(($variant['stock_quantity_decimal']??null)===null)return '<span class="small text-muted">Bez sledování skladu</span>';
    $stock=(float)$variant['stock_quantity_decimal'];
    return '<span class="badge '.($stock>0?'text-bg-success':'text-bg-danger').'">'.($stock>0?'Skladem':($stock<0?'Záporný stav':'Vyprodáno')).'</span>';
}
function shopAdminPostedAttributes(array $source): string
{
    $values=[];foreach((array)($source['attribute_keys']??[]) as $index=>$key)if(trim((string)$key)!=='')$values[(string)$key]=(string)($source['attribute_values'][$index]??'');
    return json_encode((object)$values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}
function shopAdminEventLabel(string $action): string
{
    return ['create_product'=>'Založení produktu','update_product'=>'Úprava produktu','add_variant'=>'Přidání varianty','update_variant'=>'Úprava varianty','update_price'=>'Změna ceny','adjust_stock'=>'Korekce skladu','manual_adjustment'=>'Korekce skladu','assign_categories'=>'Změna kategorií','archive_product'=>'Archivace','checkout'=>'Objednávka','cancel'=>'Storno'][$action]??$action;
}
