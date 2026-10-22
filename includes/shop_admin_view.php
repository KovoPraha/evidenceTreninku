<?php
declare(strict_types=1);

function shopAdminH(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function shopAdminMoney(mixed $value): string {return $value===null?'':number_format((int)$value/100,2,',','');}
function shopAdminUrl(int $id,string $tab='variants'): string {return 'eshop_produkt_admin.php?'.http_build_query(['id'=>$id,'tab'=>$tab]);}
function shopAdminOfferLabel(string $type): string {return ['goods'=>'Zboží','program'=>'Kroužek','club_event'=>'Klubová akce','camp'=>'Tábor','rental'=>'Pronájem','bookable_service'=>'Rezervační služba','bookable_rental'=>'Rezervační pronájem'][$type]??$type;}
function shopAdminVariantCount(int $count): string {return $count.' '.($count===1?'varianta':($count>=2&&$count<=4?'varianty':'variant'));}
function shopAdminBadge(array $product): string
{
    $status=(string)($product['catalog_status']??'draft');
    $published=($product['publication_status']??null)==='active'&&$status==='active';
    $label=$published?'Zveřejněný':($status==='draft'?'Koncept':($status==='inactive'?'Stažený':'Nezveřejněný'));
    return '<span class="badge '.($published?'text-bg-success':'text-bg-secondary').'">'.$label.'</span>';
}
function shopAdminStock(array $variant): string
{
    $stock=$variant['stock_quantity_decimal']??null;
    if(isset($variant['inventory_balance'])){$b=$variant['inventory_balance'];return '<span class="app-stock-count">'.(int)$b['available_quantity'].' ks dostupné</span><small>Fyzicky '.(int)$b['physical_quantity'].' · rezervováno '.(int)$b['reserved_quantity'].' · blokováno '.(int)$b['blocked_quantity'].'</small>';}
    if (($variant['offer_type']??'')==='program') return '<span class="small text-muted">Kapacita termínu</span>';
    if ($stock===null) return '<span class="small text-muted">Sklad se nesleduje</span>';
    $class=(float)$stock<=0?' text-danger':'';
    $unit=(string)($variant['unit_code']??'');
    $label=[''=>'ks','piece'=>'ks','set'=>'sada','other'=>'jedn.','person'=>'os.'][$unit]??$unit;
    return '<span class="app-stock-count'.$class.'">'.shopAdminH(shopAdminStockText($stock)).' '.shopAdminH($label).'</span>';
}
function shopAdminDecision(string $button,string $reason=''): void
{
    static $counter=0;$id='decision-reason-'.(++$counter);
    ?><div class="app-form-actions"><div class="flex-grow-1"><label class="form-label" for="<?=$id?>">Důvod změny</label><input class="form-control" id="<?=$id?>" name="reason" maxlength="1000" required value="<?=shopAdminH($_POST['reason']??$reason)?>"></div><label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="confirm_action" value="1" required<?=($_POST['confirm_action']??'')==='1'?' checked':''?>> Potvrzuji změnu</label><button class="btn btn-success"><?=shopAdminH($button)?></button></div><?php
}
function shopAdminHidden(string $action,int $productId=0,string $tab='variants'): void
{
    echo csrf_field().'<input type="hidden" name="action" value="'.shopAdminH($action).'">';
    if ($productId>0) echo '<input type="hidden" name="product_id" value="'.$productId.'">';
    echo '<input type="hidden" name="tab" value="'.shopAdminH($tab).'">';
}
function shopAdminAttributes(array $definitions,string $json): void
{
    try {$values=json_decode($json,true,64,JSON_THROW_ON_ERROR);} catch (JsonException) {$values=[];}
    if (!is_array($values)) $values=[];
    ?>
    <div data-shop-attribute-editor>
      <datalist id="shop-attribute-keys"><?php foreach ($definitions as $definition): ?><option value="<?=shopAdminH($definition['attribute_key'])?>"><?=shopAdminH($definition['display_name'])?></option><?php endforeach; ?></datalist>
      <div data-shop-attribute-rows><?php foreach ($values as $key=>$value): ?>
        <div class="app-attribute-row"><input class="form-control" name="attribute_keys[]" list="shop-attribute-keys" aria-label="Název parametru" value="<?=shopAdminH($key)?>"><input class="form-control" name="attribute_values[]" aria-label="Hodnota parametru" value="<?=shopAdminH(shopAttributeValueText($value))?>"><button class="btn btn-outline-danger" type="button" data-shop-remove-attribute aria-label="Odebrat parametr">×</button></div>
      <?php endforeach; ?></div>
      <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-shop-add-attribute>Přidat parametr</button>
      <template><div class="app-attribute-row"><input class="form-control" name="attribute_keys[]" list="shop-attribute-keys" aria-label="Název parametru" placeholder="Např. Velikost"><input class="form-control" name="attribute_values[]" aria-label="Hodnota parametru" placeholder="Např. XL"><button class="btn btn-outline-danger" type="button" data-shop-remove-attribute aria-label="Odebrat parametr">×</button></div></template>
    </div>
    <?php
}
function shopAdminFilters(bool $variants=false): void
{
    ?><form method="get" class="row g-2 align-items-end mb-4">
      <div class="col-sm-5"><label class="form-label" for="shop-search">Název nebo kód</label><input class="form-control" id="shop-search" name="q" value="<?=shopAdminH($_GET['q']??'')?>" placeholder="Hledat produkt nebo variantu"></div>
      <div class="col-sm"><label class="form-label" for="shop-status">Stav</label><select class="form-select" id="shop-status" name="status"><?php foreach (['current'=>'Běžný katalog','all'=>'Včetně stažených','active'=>'Aktivní','draft'=>'Koncepty','inactive'=>'Stažené'] as $key=>$label): ?><option value="<?=$key?>"<?=($_GET['status']??'current')===$key?' selected':''?>><?=$label?></option><?php endforeach; ?></select></div>
      <?php if ($variants): ?><div class="col-sm"><label class="form-label" for="shop-stock-filter">Sklad</label><select class="form-select" id="shop-stock-filter" name="stock"><?php foreach ([''=>'Všechny varianty','empty'=>'Vyprodané a záporné','unmanaged'=>'Nesledovaný sklad'] as $key=>$label): ?><option value="<?=$key?>"<?=($_GET['stock']??'')===$key?' selected':''?>><?=$label?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-sm-auto"><button class="btn btn-outline-primary">Hledat</button></div>
    </form><?php
}
function shopAdminPagination(array $page): void
{
    ?><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3"><span class="small text-muted"><?=shopAdminH($page['total'])?> položek · strana <?=$page['page']?> / <?=$page['pages']?></span><nav aria-label="Stránky katalogu" class="d-flex gap-2"><?php foreach ([-1=>'Předchozí',1=>'Další'] as $offset=>$label): $number=$page['page']+$offset;if ($number<1||$number>$page['pages'])continue;$params=$_GET;$params['page']=$number; ?><a class="btn btn-sm btn-outline-secondary" href="?<?=shopAdminH(http_build_query($params))?>"><?=$label?></a><?php endforeach; ?></nav></div><?php
}

function shopAdminVariantFields(array $variant,array $definitions,bool $editing=false,bool $program=false): void
{
    $value=static fn(string $key,mixed $fallback=''):mixed=>$_POST[$key]??$fallback;
    ?><section class="app-form-section"><h2>Parametry varianty</h2><div class="app-form-row"><label>Rozlišení varianty</label><div><?php shopAdminAttributes($definitions,(string)($variant['attributes_json']??'{}')); ?></div></div></section>
    <section class="app-form-section"><h2>Cena a identifikace</h2>
      <div class="app-form-row"><label for="variant-price">Cena v Kč</label><input class="form-control" id="variant-price" name="amount" inputmode="decimal" required value="<?=shopAdminH($value('amount',shopAdminMoney($variant['amount_minor']??null)))?>"></div>
      <div class="app-form-row"><label for="variant-compare">Původní cena v Kč</label><div><input class="form-control" id="variant-compare" name="compare_at_amount" inputmode="decimal" value="<?=shopAdminH($value('compare_at_amount',shopAdminMoney($variant['compare_at_amount_minor']??null)))?>"><div class="form-text">Nepovinná přeškrtnutá cena.</div></div></div>
      <div class="app-form-row"><label for="variant-sku">Kód varianty</label><div><input class="form-control" id="variant-sku" name="sku" maxlength="64" value="<?=shopAdminH($value('sku',$variant['sku']??''))?>"<?=($variant['origin']??'')==='import'?' readonly':''?>><div class="form-text"><?=$editing?'Původní importní kód zůstává zachovaný. Nové ruční kódy používají prefix '.shopAdminH(shopCatalogManualSkuPrefix()).'.':'Nevyplněný kód se vytvoří automaticky.'?></div></div></div>
      <div class="app-form-row"><label for="variant-unit">Jednotka</label><input class="form-control" id="variant-unit" name="unit_code" value="<?=shopAdminH($value('unit_code',$variant['unit_code']??($program?'person':'ks')))?>"<?=$program?' readonly':''?>></div>
      <?php if (!$editing&&!$program): ?><div class="app-form-row"><label for="variant-stock">Počáteční sklad</label><div><input class="form-control" id="variant-stock" name="stock_quantity_decimal" inputmode="decimal" value="<?=shopAdminH($value('stock_quantity_decimal','0'))?>"><div class="form-text">Prázdné pole znamená, že se sklad nesleduje.</div></div></div><?php endif; ?>
      <div class="app-form-row"><label for="variant-visible">Viditelnost</label><label class="form-check text-start"><input class="form-check-input" id="variant-visible" name="visible" type="checkbox" value="1"<?=isset($_POST['action'])?(isset($_POST['visible'])?' checked':''):((!isset($variant['visible'])||(int)$variant['visible']===1)?' checked':'')?>>Nabízet tuto variantu</label></div>
    </section>
    <details class="mb-4"><summary class="text-primary">Další údaje (EAN a DPH)</summary><div class="row g-3 mt-1"><div class="col-sm-4"><label class="form-label" for="variant-ean">EAN</label><input class="form-control" name="ean" id="variant-ean" value="<?=shopAdminH($value('ean',$variant['ean']??''))?>"></div><div class="col-sm-4"><label class="form-label" for="variant-vat">DPH zahrnuta</label><select class="form-select" name="includes_vat" id="variant-vat"><?php foreach ([''=>'Neurčeno','1'=>'Ano','0'=>'Ne'] as $key=>$label): ?><option value="<?=$key?>"<?=(string)$value('includes_vat',$variant['includes_vat']??'')===(string)$key?' selected':''?>><?=$label?></option><?php endforeach; ?></select></div><div class="col-sm-4"><label class="form-label" for="variant-rate">DPH (%)</label><input class="form-control" name="vat_percent" id="variant-rate" inputmode="decimal" value="<?=shopAdminH($value('vat_percent',isset($variant['vat_rate_basis_points'])?number_format((int)$variant['vat_rate_basis_points']/100,2,',',''):''))?>"></div></div></details>
    <?php
}
