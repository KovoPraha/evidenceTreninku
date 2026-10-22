<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/shop_number_series.php';
staffRequireActivePosition('finance_manager');

function seriesH(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function seriesCategoryLabel(array $c): string {return $c['group_code'].'/'.$c['category_code'].' · '.$c['group_name'].' → '.$c['name'].(!(int)$c['active']?' (neaktivní)':'');}
function seriesCategoryOptions(array $categories, int $selected=0, bool $mixed=false): void {
    foreach ($categories as $c) if ((int)$c['active']&&($mixed||(int)$c['group_code']!==9)) echo '<option value="'.(int)$c['id'].'"'.((int)$c['id']===$selected?' selected':'').'>'.seriesH(seriesCategoryLabel($c)).'</option>';
}
function seriesFormEnd(int $revision, string $suffix): void { ?>
<input type="hidden" name="revision" value="<?=$revision?>">
<label class="form-label mt-3" for="reason-<?=seriesH($suffix)?>">Důvod změny</label><input class="form-control" id="reason-<?=seriesH($suffix)?>" name="reason" minlength="3" maxlength="1000" required>
<div class="form-check my-3"><input class="form-check-input" type="checkbox" id="confirm-<?=seriesH($suffix)?>" name="confirm_action" value="1" required><label class="form-check-label" for="confirm-<?=seriesH($suffix)?>">Potvrzuji změnu pro budoucí objednávky</label></div>
<button class="btn btn-success" type="submit">Uložit</button></form>
<?php }

$errors=[];
$available=shopNumberSeriesTableExists($pdo,'shop_number_series_settings');
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) $errors[]='Formulář vypršel. Obnovte stránku.';
    elseif (!$available) $errors[]='Správa řad vyžaduje dokončenou databázovou migraci.';
    else try {
        shopNumberSeriesSave($pdo,(int)$_SESSION['trener_id'],$_POST,(string)($_POST['reason']??''),($_POST['confirm_action']??'')==='1');
        $_SESSION['series_flash']='Změna byla uložena s historií. Existující objednávky ani příspěvky se neměnily.';
        header('Location: eshop_number_series_admin.php',true,303);exit;
    } catch (InvalidArgumentException|ShopNumberSeriesException $e) {$errors[]=$e->getMessage();}
    catch (Throwable $e) {error_log('shop_number_series admin: '.get_class($e));$errors[]='Uložení selhalo. Ověřte, zda kód již nepoužívá jiná kategorie; nic se neuložilo.';}
}
$flash=(string)($_SESSION['series_flash']??'');unset($_SESSION['series_flash']);
$categories=$available?shopNumberSeriesCategories($pdo):[];
$groups=$available?$pdo->query('SELECT * FROM shop_accounting_groups ORDER BY code')->fetchAll(PDO::FETCH_ASSOC):[];
$revision=$available?(int)$pdo->query('SELECT revision FROM shop_number_series_settings WHERE id=1')->fetchColumn():0;
$mappings=$available?$pdo->query('SELECT m.*,c.name,c.group_code,c.category_code FROM shop_accounting_mappings m JOIN shop_accounting_categories c ON c.id=m.category_id ORDER BY m.mapping_type,m.mapping_key')->fetchAll(PDO::FETCH_ASSOC):[];
$events=$available?$pdo->query('SELECT * FROM shop_number_series_events ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC):[];
$issued=$available?$pdo->query('SELECT a.*,o.public_code FROM shop_order_number_assignments a JOIN shop_orders o ON o.id=a.order_id ORDER BY a.order_id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC):[];
$year=(int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->format('Y');
$viewYear=filter_var($_GET['year']??$year,FILTER_VALIDATE_INT);if ($viewYear===false||$viewYear<2000||$viewYear>2099) $viewYear=$year;
$counters=[];if ($available) {$s=$pdo->prepare('SELECT category_id,next_number FROM shop_number_series_counters WHERE series_year=?');$s->execute([$viewYear]);$counters=$s->fetchAll(PDO::FETCH_KEY_PAIR);}
$editId=(int)($_GET['category_id']??0);$edit=null;foreach($categories as $c) if ((int)$c['id']===$editId&&(int)$c['group_code']!==9) $edit=$c;
$catalog=$available?shopCategoryTreeOrder(shopCategoryNodes($pdo)):[];
$products=$available?$pdo->query('SELECT id,name FROM shop_products ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC):[];
$productNames=array_column($products,'name','id');
$kinds=['goods'=>'Zboží bez mapování','program'=>'Kroužky bez mapování','event'=>'Klubové akce','velodrome'=>'Velodrom'];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Číselné řady a účetní kategorie</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head>
<body class="bg-light"><?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4">
<h1 class="h3">Číselné řady a účetní kategorie</h1>
<p>Nové nákupy používají variabilní symbol <strong>RR N KK XXXXX</strong>: rok, nadkategorie, kategorie a pořadí. Stálé VS příspěvků a již vydané platební údaje se zachovávají.</p>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=seriesH($error)?></div><?php endforeach;?>
<?php if($flash!==''):?><div class="alert alert-success"><?=seriesH($flash)?></div><?php endif;?>
<?php if(!$available):?><div class="alert alert-warning">Číselné řady zatím nejsou připravené v databázi.</div><?php else:?>
<div class="alert alert-info">Účetní zařazení se ukládá při nákupu. Smíšené objednávky mají řadu <strong>9/00</strong>; jednotlivé položky si ponechají své kategorie. Řada <strong>8/00 Nezařazeno</strong> označuje položky bez mapování. Čitelné číslo je číslem objednávky, nikoli fakturou.</div>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Řady a náhled</h2>
<form method="get" class="d-flex gap-2 mb-3"><label class="form-label align-self-center mb-0" for="view-year">Rok</label><input class="form-control" style="max-width:130px" id="view-year" name="year" type="number" min="2000" max="2099" value="<?=$viewYear?>" required><button class="btn btn-outline-primary">Zobrazit</button></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Kód</th><th>Účetní kategorie</th><th>Následující pořadí</th><th>Ukázka VS / čísla</th><th></th></tr></thead><tbody>
<?php foreach($categories as $c):$next=(int)($counters[$c['id']]??1);$preview=$next<=99999?shopNumberSeriesFormat($viewYear,(int)$c['group_code'],(string)$c['category_code'],$next):null;?>
<tr><td><?=seriesH($c['group_code'].'/'.$c['category_code'])?></td><td><?=seriesH($c['group_name'].' → '.$c['name'])?><?php if(!(int)$c['active']):?> <span class="badge text-bg-secondary">Neaktivní</span><?php endif;?></td><td><?=$next?></td><td><?php if($preview):?><code><?=seriesH($preview['variable_symbol'])?></code><br><small><?=seriesH($preview['number_snapshot'])?></small><?php else:?><strong class="text-danger">Řada je vyčerpaná</strong><?php endif;?></td><td><?php if((int)$c['group_code']!==9):?><a class="btn btn-sm btn-outline-primary" href="?category_id=<?=(int)$c['id']?>#category-form">Upravit</a><?php endif;?></td></tr>
<?php endforeach;?></tbody></table></div><p class="small text-muted mb-0">Náhled číslo nerezervuje. Pokud je VS již použitý, nákup přeskočí kolidující číslo. Každý rok začíná samostatně od 1, pokud neurčíte vyšší počáteční číslo. Po stornu se číslo znovu nepoužije.</p></div></section>
<div class="row g-4 mb-4"><section class="col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Nadkategorie</h2><p class="small"><?php foreach($groups as $g):?><?=seriesH($g['code'].' = '.$g['name'])?><br><?php endforeach;?></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="group"><label class="form-label" for="group-code">Kód nadkategorie</label><input class="form-control" id="group-code" name="group_code" type="number" min="1" max="8" required><label class="form-label mt-2" for="group-name">Název</label><input class="form-control" id="group-name" name="name" maxlength="120" required><div class="form-text">Existující kód přejmenuje nadkategorii pro budoucí nákupy. Kód 9 je vyhrazen smíšeným objednávkám.</div><?php seriesFormEnd($revision,'group');?></div></div></section>
<section class="col-lg-6"><div class="card h-100" id="category-form"><div class="card-body"><h2 class="h5"><?=$edit?'Upravit účetní kategorii':'Nová účetní kategorie'?></h2>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="category"><input type="hidden" name="category_id" value="<?=(int)($edit['id']??0)?>"><label class="form-label" for="category-group">Nadkategorie</label><select class="form-select" id="category-group" name="group_code" required><?php foreach($groups as $g):if((int)$g['code']===9)continue;?><option value="<?=(int)$g['code']?>" <?=(int)($edit['group_code']??0)===(int)$g['code']?'selected':''?>><?=seriesH($g['code'].' · '.$g['name'])?></option><?php endforeach;?></select>
<label class="form-label mt-2" for="category-code">Kód kategorie (00–99)</label><input class="form-control" id="category-code" name="category_code" pattern="[0-9]{2}" maxlength="2" value="<?=seriesH($edit['category_code']??'')?>" required><label class="form-label mt-2" for="category-name">Název kategorie</label><input class="form-control" id="category-name" name="name" maxlength="120" value="<?=seriesH($edit['name']??'')?>" required><div class="form-check mt-2"><input class="form-check-input" id="category-active" type="checkbox" name="active" value="1" <?=!$edit||(int)$edit['active']?'checked':''?>><label class="form-check-label" for="category-active">Aktivní</label></div><div class="form-text">Kódy uložené kategorie jsou neměnné. Pro jiný kód vytvořte novou kategorii.</div><?php seriesFormEnd($revision,'category');?><?php if($edit):?><a class="d-inline-block mt-2" href="eshop_number_series_admin.php#category-form">Vytvořit novou kategorii</a><?php endif;?></div></div></section></div>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Přiřazení účetních kategorií</h2><p>Platí přednost: konkrétní produkt → výchozí kategorie katalogu → nejbližší nastavená nadkategorie katalogu → výchozí druh nabídky.</p>
<div class="row g-4">
<?php foreach(['catalog'=>'Kategorie a nadkategorie katalogu','product'=>'Konkrétní produkt','kind'=>'Výchozí druh nabídky'] as $type=>$title):
    $sourceKey=$type==='kind'?'goods':($type==='product'?(string)($products[0]['id']??''):(string)($catalog[0]['category_path']??''));
    if (($_GET['load_mapping']??'')===$type) $sourceKey=(string)($_GET['mapping_key']??$sourceKey);
    $selectedCategory=shopNumberSeriesMapping($pdo,$type,$sourceKey)??0;
?><div class="col-lg-4"><h3 class="h6"><?=seriesH($title)?></h3><form method="get"><input type="hidden" name="load_mapping" value="<?=seriesH($type)?>"><label class="form-label" for="key-<?=seriesH($type)?>">Zdroj</label><select class="form-select" id="key-<?=seriesH($type)?>" name="mapping_key" required>
<?php if($type==='catalog'):foreach($catalog as $row):?><option value="<?=seriesH($row['category_path'])?>" <?=$sourceKey===$row['category_path']?'selected':''?>><?=seriesH(str_repeat('↳ ',(int)$row['depth']).$row['category_path'])?></option><?php endforeach;elseif($type==='product'):foreach($products as $p):?><option value="<?=(int)$p['id']?>" <?=$sourceKey===(string)$p['id']?'selected':''?>><?=seriesH($p['name'].' (#'.$p['id'].')')?></option><?php endforeach;else:foreach($kinds as $key=>$label):?><option value="<?=seriesH($key)?>" <?=$sourceKey===$key?'selected':''?>><?=seriesH($label)?></option><?php endforeach;endif;?></select><button class="btn btn-sm btn-outline-secondary mt-2" type="submit">Načíst přiřazení</button></form><div class="form-text mb-2">Po změně zdroje načtěte jeho uložené přiřazení.</div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mapping"><input type="hidden" name="mapping_type" value="<?=seriesH($type)?>"><input type="hidden" name="mapping_key" value="<?=seriesH($sourceKey)?>"><p class="small">Upravujete přiřazení: <strong><?=seriesH($type==='kind'?($kinds[$sourceKey]??$sourceKey):($type==='product'?($productNames[$sourceKey]??$sourceKey):$sourceKey))?></strong></p>
<label class="form-label mt-2" for="mapping-<?=seriesH($type)?>">Účetní kategorie</label><select class="form-select" id="mapping-<?=seriesH($type)?>" name="category_id" required><?php if($type!=='kind'):?><option value="0">Převzít z nadřazeného nastavení</option><?php endif;seriesCategoryOptions($categories,$selectedCategory);?></select><?php seriesFormEnd($revision,$type);?></div><?php endforeach;?></div>
<h3 class="h6 mt-4">Uložená přiřazení</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Zdroj</th><th>Hodnota</th><th>Účetní řada</th></tr></thead><tbody><?php foreach($mappings as $m):?><tr><td><?=seriesH(['kind'=>'Druh nabídky','product'=>'Produkt','catalog'=>'Kategorie katalogu'][$m['mapping_type']]??$m['mapping_type'])?></td><td><?=seriesH($m['mapping_type']==='product'?($productNames[$m['mapping_key']]??$m['mapping_key']):($kinds[$m['mapping_key']]??$m['mapping_key']))?></td><td><?=seriesH($m['group_code'].'/'.$m['category_code'].' · '.$m['name'])?></td></tr><?php endforeach;?></tbody></table></div></div></section>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Počáteční nebo následující číslo</h2><p>Čítač lze pouze zvýšit. Změna se projeví až při novém nákupu.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="counter"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="counter-category">Řada</label><select class="form-select" id="counter-category" name="category_id" required><?php seriesCategoryOptions($categories,0,true);?></select></div><div class="col-md-3"><label class="form-label" for="counter-year">Rok</label><input class="form-control" id="counter-year" name="series_year" type="number" min="2000" max="2099" value="<?=$viewYear?>" required></div><div class="col-md-3"><label class="form-label" for="counter-next">Následující číslo</label><input class="form-control" id="counter-next" name="next_number" type="number" min="1" max="99999" required></div></div><?php seriesFormEnd($revision,'counter');?></div></section>
<section class="card"><div class="card-body"><h2 class="h5">Historie posledních 50 změn</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Čas / pracovník</th><th>Změna</th><th>Předtím → potom</th><th>Důvod</th></tr></thead><tbody><?php foreach($events as $e):?><tr><td><?=seriesH($e['created_at'])?><br>#<?=(int)$e['actor_id']?></td><td><?=seriesH(['group'=>'Nadkategorie','category'=>'Účetní kategorie','mapping'=>'Přiřazení','counter'=>'Čítač'][$e['action']]??$e['action'])?></td><td><details><summary>Zobrazit hodnoty</summary><pre class="small text-wrap"><?=seriesH($e['before_json'])?> → <?=seriesH($e['after_json'])?></pre></details></td><td><?=seriesH($e['reason'])?></td></tr><?php endforeach;?></tbody></table></div></div></section>
<section class="card mt-4"><div class="card-body"><h2 class="h5">Posledních 30 přidělených čísel</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Objednávka</th><th>Čitelné číslo</th><th>VS</th><th>Účetní zařazení při nákupu</th></tr></thead><tbody><?php foreach($issued as $a):?><tr><td><?=seriesH($a['public_code'])?></td><td><?=seriesH($a['number_snapshot'])?></td><td><code><?=seriesH($a['variable_symbol'])?></code></td><td><?=seriesH($a['group_name_snapshot'].' → '.$a['category_name_snapshot'])?></td></tr><?php endforeach;?><?php if($issued===[]):?><tr><td colspan="4">Zatím nebylo přiděleno žádné nové číslo. Historické objednávky zachovávají původní VS.</td></tr><?php endif;?></tbody></table></div></div></section>
<?php endif;?></main></body></html>
