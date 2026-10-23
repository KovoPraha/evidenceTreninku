<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/medical_clearance.php';
header('Cache-Control: no-store, private');
$date=(string)($_GET['on']??date('Y-m-d'));$person=(int)($_GET['person_id']??0);
try{$rows=medicalClearanceView($pdo,$person,$date);}catch(DomainException|InvalidArgumentException){http_response_code(403);exit('Podklad není ve vašem rozsahu nebo je neplatné datum.');}
$labels=['missing'=>'Nedoloženo','pending'=>'Čeká na ověření','valid'=>'Platné','expired'=>'Platnost skončila','not_yet_valid'=>'Dosud neplatné','revoked'=>'Odvoláno'];
function medicalStatusH(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Platnost podkladů</title><?php appUiAssets();?></head><body><?php include __DIR__.'/hlavicka.php';?><main class="container py-4"><h1>Platnost podkladů osoby #<?=$person?></h1><form method="get"><input type="hidden" name="person_id" value="<?=$person?>"><label>Den aktivity <input type="date" name="on" value="<?=medicalStatusH($date)?>" required></label><button class="btn btn-primary">Zobrazit stav</button></form><?php if(!$rows):?><p>Pro tuto osobu zatím nebyl určen sledovaný podklad. Ověřte požadavek s registrářem.</p><?php endif;?><?php foreach($rows as$r):?><section class="my-3"><h2 class="h5"><?=medicalStatusH($r['purpose'])?></h2><p><?=medicalStatusH($labels[$r['status']])?><?= $r['valid_until']?' do '.medicalStatusH($r['valid_until']):''?>. <?=medicalStatusH($r['next_step'])?></p></section><?php endforeach;?></main></body></html>
