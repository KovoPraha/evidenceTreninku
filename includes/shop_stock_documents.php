<?php
declare(strict_types=1);
require_once __DIR__.'/shop_inventory.php';
require_once __DIR__.'/private_storage.php';

function shopStockDocumentActor(PDO $pdo,int $actor):void
{
 $q=$pdo->prepare("SELECT t.id FROM treneri t WHERE t.id=? AND t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code IN ('catalog_manager','order_operator','finance_manager')) OR EXISTS(SELECT 1 FROM staff_superadmins s WHERE s.trainer_id=t.id))");$q->execute([$actor]);if(!$q->fetchColumn())throw new DomainException('Skladový doklad vyžaduje aktuální skladové nebo finanční oprávnění.');
}
function shopStockDocumentHtml(array $m):string
{
 $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $kinds=['opening'=>'Počáteční stav','receipt'=>'Příjemka','issue'=>'Výdejka','return'=>'Vratka zboží','count_adjustment'=>'Inventurní rozdíl','writeoff'=>'Vyřazení','supplier_return'=>'Vrácení dodavateli','internal_issue'=>'Výdej pro klub','reserve'=>'Rezervace','release'=>'Uvolnění rezervace','block'=>'Blokace','unblock'=>'Uvolnění blokace'];
 $html='<html><head><meta charset="UTF-8"><style nonce="">body{font-family:DejaVu Sans;font-size:10pt;color:#233347}h1{font-size:22pt}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #ccd5dd;text-align:left}p{line-height:1.5}.muted{color:#566779}</style></head><body><h1>'.$e($kinds[$m['kind']]??$m['kind']).' SK-'.$e($m['id']).'</h1><p class="muted">Skladový doklad - není potvrzením úhrady ani novým peněžním výdajem.</p>';
 $meta=json_decode($m['meta_json'],true,512,JSON_THROW_ON_ERROR);
 foreach(['Datum pohybu'=>$m['effective_on'],'Zapsáno'=>$m['recorded_at'],'Varianta #'=>$m['variant_id'],'Objednávka #'=>$m['order_id']??'bez objednávky','Položka #'=>$m['order_item_id']??'bez položky','Pracovník #'=>$m['actor_trainer_id']??'systém','Důvod'=>$m['reason']] as $label=>$value)$html.='<p><strong>'.$e($label).':</strong> '.$e($value).'</p>';
 $html.='<table><tr><th></th><th>Změna kusů</th><th>Stav po zápisu</th></tr>';
 foreach(['physical'=>'Fyzicky','reserved'=>'Rezervováno','blocked'=>'Blokováno'] as $key=>$label)$html.='<tr><td>'.$label.'</td><td>'.$e($m[$key.'_delta']).'</td><td>'.$e($m[$key.'_after']).'</td></tr>';
 $html.='</table><p>Fyzický skladový doklad bez pořizovacích cen. Ocenění a jeho opravy jsou v účetních podkladech hospodáře.</p><h2>Podklady a původní údaje</h2>';
 foreach(['name'=>'Zboží','sku'=>'SKU','unit_code'=>'Jednotka'] as $key=>$label)if(isset($meta['variant_snapshot'][$key]))$html.='<p><strong>'.$label.':</strong> '.$e($meta['variant_snapshot'][$key]==='piece'?'kus':$meta['variant_snapshot'][$key]).'</p>';
 foreach(['warehouse_snapshot'=>'Sklad','supplier'=>'Dodavatel','supplier_reference'=>'Dodací list / doklad dodavatele','recipient'=>'Příjemce','effective_on'=>'Datum předání','count_id'=>'Inventurní soupis','return_case_id'=>'Vratkový případ','exchange_case_id'=>'Výměna'] as $key=>$label)if(isset($meta[$key])&&is_scalar($meta[$key]))$html.='<p><strong>'.$label.':</strong> '.$e($meta[$key]).'</p>';
 return $html.'</body></html>';
}
function shopStockDocumentCreate(PDO $pdo,int $id,int $actor,string $reason,bool $confirmed):array
{
 $reason=shopInventoryDecision($actor,$reason,$confirmed);if($pdo->inTransaction())throw new LogicException('Doklad vyžaduje vlastní transakci.');$pdo->beginTransaction();$stored=null;
 try{shopStockDocumentActor($pdo,$actor);$q=$pdo->prepare('SELECT * FROM shop_stock_movements WHERE id=?'.shopInventoryLock($pdo));$q->execute([$id]);$m=$q->fetch(PDO::FETCH_ASSOC);if(!$m)throw new DomainException('Skladový pohyb neexistuje.');
  $q=$pdo->prepare('SELECT * FROM shop_stock_documents WHERE movement_id=?');$q->execute([$id]);if($old=$q->fetch(PDO::FETCH_ASSOC)){$pdo->commit();return $old;}
  $json=json_encode($m,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);require_once dirname(__DIR__).'/vendor/autoload.php';$options=new Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('isJavascriptEnabled',false);$options->set('defaultFont','DejaVu Sans');$options->set('chroot',dirname(__DIR__).'/vendor/dompdf');$pdf=new Dompdf\Dompdf($options);$pdf->loadHtml(shopStockDocumentHtml($m),'UTF-8');$pdf->setPaper('A4');$pdf->render();$pdf->getCanvas()->page_text(38,818,'SK-'.$id.' | {PAGE_NUM} / {PAGE_COUNT}',$pdf->getFontMetrics()->getFont('DejaVu Sans','normal'),8);$bytes=$pdf->output();
  $dir=privateStorageEnsureDirectory(PRIVATE_STORAGE_STOCK_DOCUMENTS);$temp=tempnam($dir,'render-');if($temp===false||file_put_contents($temp,$bytes)!==strlen($bytes))throw new RuntimeException('Skladový doklad nelze uložit.');
  try{$stored=privateStorageStore($temp,PRIVATE_STORAGE_STOCK_DOCUMENTS,false);}finally{if(is_file($temp))unlink($temp);}
  $pdo->prepare('INSERT INTO shop_stock_documents(movement_id,snapshot_json,snapshot_sha256,storage_key,pdf_sha256,actor_trainer_id,reason) VALUES(?,?,?,?,?,?,?)')->execute([$id,$json,hash('sha256',$json),$stored,hash('sha256',$bytes),$actor,$reason]);$pdo->commit();$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC);
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($stored!==null){$path=privateStorageResolve($stored);if($path!==null&&is_file($path))unlink($path);}throw $e;}
}
function shopStockDocumentDownload(PDO $pdo,int $id,int $actor):string
{
 shopStockDocumentActor($pdo,$actor);$q=$pdo->prepare('SELECT * FROM shop_stock_documents WHERE movement_id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Doklad ještě nebyl archivován.');$path=privateStorageResolve($r['storage_key']);if(!$path||!hash_equals($r['pdf_sha256'],hash_file('sha256',$path))||!hash_equals($r['snapshot_sha256'],hash('sha256',$r['snapshot_json'])))throw new DomainException('Otisk archivovaného dokladu nesouhlasí.');return $path;
}
