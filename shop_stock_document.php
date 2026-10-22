<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/shop_stock_documents.php';
if(!staffActivePositionIs('catalog_manager')&&!staffActivePositionIs('order_operator')&&!staffActivePositionIs('finance_manager')){http_response_code(403);exit('Vyberte skladovou, objednávkovou nebo finanční pozici.');}
$actor=(int)($_SESSION['trener_id']??0);$id=(int)($_GET['id']??$_POST['id']??0);$error='';
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
try{shopStockDocumentActor($pdo,$actor);}catch(DomainException){http_response_code(403);exit('Oprávnění již není platné.');}
if($_SERVER['REQUEST_METHOD']==='POST')try{
 if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new DomainException('Formulář vypršel.');
 shopStockDocumentCreate($pdo,$id,$actor,(string)($_POST['reason']??''),($_POST['confirmed']??'')==='1');header('Location: shop_stock_document.php?id='.$id.'&download=1',true,303);exit;
}catch(Throwable $e){http_response_code(422);$error=$e instanceof DomainException||$e instanceof ShopInventoryException?$e->getMessage():'Doklad se nepodařilo archivovat. Opakujte stejný pohyb.';}
if(isset($_GET['download']))try{$path=shopStockDocumentDownload($pdo,$id,$actor);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="sklad-SK-'.$id.'.pdf"');header('Content-Length: '.filesize($path));readfile($path);exit;}catch(DomainException $e){http_response_code(404);exit('Archivovaný doklad není dostupný nebo nesouhlasí otisk.');}
$q=$pdo->prepare('SELECT * FROM shop_stock_movements WHERE id=?');$q->execute([$id]);$m=$q->fetch(PDO::FETCH_ASSOC);if(!$m){http_response_code(404);exit('Pohyb nebyl nalezen.');}
$q=$pdo->prepare('SELECT movement_id FROM shop_stock_documents WHERE movement_id=?');$q->execute([$id]);$exists=(bool)$q->fetchColumn();
function stockDocH(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
require_once __DIR__.'/includes/ui_shell.php';
?><!doctype html><html lang="cs"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Skladový doklad</title><?php appUiAssets();?></head><body><?php require __DIR__.'/hlavicka.php';?><main class="container py-4"><h1>Skladový doklad SK-<?=stockDocH($id)?></h1><p>Archivuje původní fyzický pohyb bez pořizovacích cen. Nezaznamenává úhradu.</p><?php if($error!==''):?><p class="alert alert-danger"><?=stockDocH($error)?></p><?php endif;?><dl><?php foreach(['effective_on'=>'Datum','kind'=>'Druh','variant_id'=>'Varianta','physical_delta'=>'Změna fyzických kusů','reserved_delta'=>'Změna rezervace','blocked_delta'=>'Změna blokace','reason'=>'Podklad'] as $key=>$label):?><dt><?=stockDocH($label)?></dt><dd><?=stockDocH($m[$key])?></dd><?php endforeach;?></dl><?php if($exists):?><a class="btn btn-primary" href="?id=<?=$id?>&amp;download=1">Stáhnout archivované PDF</a><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=stockDocH(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><label class="d-block">Důvod archivace<input class="form-control" name="reason" minlength="10" maxlength="1000" required></label><label class="d-block my-3"><input type="checkbox" name="confirmed" value="1" required> Potvrzuji archivaci tohoto skladového dokladu.</label><button class="btn btn-primary">Archivovat a stáhnout PDF</button></form><?php endif;?></main></body></html>
