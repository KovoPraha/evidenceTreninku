<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/shop_checkout.php';
if(!isset($_SESSION['trener_id'])||!roleAtLeast('admin')){header('Location: login.php');exit;}

function pplAdminH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$errors=[];$saved=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        $price=(string)($_POST['price']??'');
        if(!preg_match('/^\d{1,5}(?:[,.]\d{1,2})?$/D',$price))throw new InvalidArgumentException('Zadejte cenu dopravy v Kč.');
        $parts=explode('.',str_replace(',','.',$price),2);
        $minor=(int)$parts[0]*100+(int)str_pad($parts[1]??'0',2,'0');
        shopPplSaveSettings($pdo,(int)$_SESSION['trener_id'],$minor,(string)($_POST['widget_api_key']??''),($_POST['enabled']??'')==='1',(string)($_POST['reason']??''),($_POST['confirmed']??'')==='1');
        header('Location: eshop_ppl_admin.php?saved=1',true,303);exit;
    }catch(InvalidArgumentException|ShopPplException $error){$errors[]=$error->getMessage();}
    catch(PDOException $error){error_log('eshop_ppl_admin: '.get_class($error));$errors[]='Nastavení se nepodařilo uložit.';}
}
$settings=shopPplSettings($pdo);
$ready=shopPplAvailable(['enabled'=>true,'price_minor'=>max(1,$settings['price_minor']),'widget_api_key'=>$settings['widget_api_key']]);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Doprava PPL v e-shopu</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php include __DIR__.'/hlavicka.php';?>
<main class="container py-4" style="max-width:900px"><a href="eshop_orders_admin.php" class="btn btn-sm btn-outline-secondary mb-3">← Objednávky</a><h1 class="h3">Doručení do PPL Parcelboxu</h1>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=pplAdminH($error)?></div><?php endforeach;?><?php if(($_GET['saved']??'')==='1'):?><div class="alert alert-success">Nastavení bylo uloženo a auditováno.</div><?php endif;?>
<div class="alert alert-<?=$ready?'info':'warning'?>">Serverové připojení PPL: <strong><?=$ready?'připraveno podle konfigurace':'není kompletní'?></strong>. Pro spuštění potřebujete PPL widget klíč a serverové proměnné <code>PPL_API_MODE</code>, <code>PPL_CLIENT_ID</code>, <code>PPL_CLIENT_SECRET</code>. Tajný klíč API sem nevkládejte. Reálnou funkčnost ještě ověřte v PPL sandboxu. Volba dopravy se ukáže jen u produktů označených pro zasílání.</div>
<form method="post" class="card card-body shadow-sm border-0"><?=csrf_field()?><div class="mb-3"><label for="ppl-price" class="form-label fw-semibold">Cena dopravy do boxu v Kč</label><input id="ppl-price" class="form-control" name="price" inputmode="decimal" required value="<?=pplAdminH(number_format($settings['price_minor']/100,2,',',''))?>"><div class="form-text">Jedna cena za celou objednávku. Bude zahrnuta do částky platby i potvrzení.</div></div>
<div class="mb-3"><label for="ppl-widget-key" class="form-label fw-semibold">Veřejný klíč PPL widgetu</label><input id="ppl-widget-key" class="form-control" name="widget_api_key" maxlength="255" value="<?=pplAdminH($settings['widget_api_key'])?>"><div class="form-text">Klíč svázaný s doménou e-shopu, přidělený PPL. Není to serverový Client Secret.</div></div>
<div class="form-check mb-3"><input id="ppl-enabled" class="form-check-input" type="checkbox" name="enabled" value="1" <?=$settings['enabled']?'checked':''?>><label for="ppl-enabled" class="form-check-label">Nabízet zákazníkům doručení PPL Parcelbox</label></div>
<div class="mb-3"><label for="ppl-reason" class="form-label fw-semibold">Důvod změny</label><input id="ppl-reason" class="form-control" name="reason" minlength="5" maxlength="1000" required placeholder="Např. aktivace po sandboxovém testu"></div><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required><span class="form-check-label">Potvrzuji změnu nabídky a ceny dopravy.</span></label><button class="btn btn-success">Uložit nastavení</button></form>
<div class="card card-body border-0 shadow-sm mt-3 small"><strong>Provozní postup</strong><p class="mb-0">Po zaplacení a přípravě objednávky vytvořte zásilku v Klient PPL se zobrazeným kódem boxu. Teprve po skutečném předání dopravci zadejte číslo zásilky v administraci objednávky. V této fázi se štítky nevytvářejí automaticky.</p></div></main></body></html>
