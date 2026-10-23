<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/coach_scope.php';
staffRequireActivePosition('sports_lead');
header('Cache-Control: no-store, private');
function csH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new RuntimeException('Formulář vypršel. Obnovte stránku.');
        $actor=(int)$_SESSION['trener_id'];
        if(($_POST['action']??'')==='grant'){
            $to=trim((string)($_POST['valid_to']??''));
            coachScopeGrant($pdo,(int)($_POST['trainer_id']??0),(string)($_POST['scope_kind']??''),(int)($_POST['scope_id']??0),str_replace('T',' ',(string)($_POST['valid_from']??'')).':00',$to===''?null:str_replace('T',' ',$to).':00',(string)($_POST['reason']??''),$actor);
        }elseif(($_POST['action']??'')==='revoke')coachScopeRevoke($pdo,(int)($_POST['grant_id']??0),(string)($_POST['reason']??''),$actor);
        else throw new InvalidArgumentException('Neznámá akce.');
        header('Location: coach_scope_admin.php?saved=1');exit;
    }catch(Throwable $e){$error=$e instanceof PDOException?'Zápis se nezdařil; žádná částečná změna nebyla uložena.':$e->getMessage();}
}
$trainers=$pdo->query("SELECT t.id,t.jmeno FROM treneri t JOIN staff_user_positions p ON p.trainer_id=t.id AND p.position_code='coach' WHERE t.aktivni=1 ORDER BY t.jmeno")->fetchAll(PDO::FETCH_ASSOC);
$teams=$pdo->query('SELECT id,name AS name FROM club_teams ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$subgroups=$pdo->query('SELECT id,nazev AS name FROM podskupiny ORDER BY nazev')->fetchAll(PDO::FETCH_ASSOC);
$rows=$pdo->query("SELECT g.*,t.jmeno,COALESCE(ct.name,p.nazev,'Nedostupný rozsah') AS scope_name FROM coach_scope_grants g JOIN treneri t ON t.id=g.trainer_id LEFT JOIN club_teams ct ON g.scope_kind='team' AND ct.id=g.scope_id LEFT JOIN podskupiny p ON g.scope_kind='subgroup' AND p.id=g.scope_id ORDER BY g.id DESC LIMIT 250")->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Svěřenci trenérů</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body>
<?php include __DIR__.'/hlavicka.php';?><main class="container py-4"><h1>Svěřenci trenérů</h1>
<p>Trenér vidí sportovní údaje osob v přiděleném týmu nebo podskupině po dobu platnosti. Historicky vedený trénink nepřiděluje přístup. Týmové přidělení zahrnuje aktuální přímé členy vybrané soupisky; další týmy se přidělují výslovně.</p>
<?php if($error):?><div class="alert alert-danger"><?=csH($error)?></div><?php endif;?><?php if(isset($_GET['saved'])&&!$error):?><div class="alert alert-success">Rozsah byl uložen s auditní historií.</div><?php endif;?>
<?php foreach(['team'=>['Tým / soupiska',$teams],'subgroup'=>['Původní podskupina',$subgroups]] as $kind=>[$label,$scopes]):?>
<details class="mb-3"><summary>Přidělit: <?=csH($label)?></summary><form method="post" class="row g-2 mt-2"><input type="hidden" name="csrf_token" value="<?=csH(csrf_token())?>"><input type="hidden" name="action" value="grant"><input type="hidden" name="scope_kind" value="<?=csH($kind)?>">
<div class="col-md-6"><label>Trenér<select name="trainer_id" class="form-select" required><option value="">Vyberte</option><?php foreach($trainers as $trainer):?><option value="<?=(int)$trainer['id']?>"><?=csH($trainer['jmeno'])?></option><?php endforeach;?></select></label></div>
<div class="col-md-6"><label><?=csH($label)?><select name="scope_id" class="form-select" required><option value="">Vyberte</option><?php foreach($scopes as $scope):?><option value="<?=(int)$scope['id']?>"><?=csH($scope['name'])?> #<?=(int)$scope['id']?></option><?php endforeach;?></select></label></div>
<div class="col-md-6"><label>Od<input type="datetime-local" name="valid_from" required value="<?=csH(date('Y-m-d\TH:i'))?>" class="form-control"></label></div><div class="col-md-6"><label>Do (prázdné = bez plánovaného konce)<input type="datetime-local" name="valid_to" class="form-control"></label></div>
<div class="col-12"><label class="w-100">Důvod<input name="reason" minlength="10" maxlength="1000" required class="form-control"></label></div><div><button class="btn btn-primary">Přidělit rozsah</button></div></form></details><?php endforeach;?>
<p class="small text-muted">Nejnovějších nejvýše 250 přidělení. Ukončení působí ihned, historie se nemaže.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Trenér</th><th>Rozsah</th><th>Platnost</th><th>Důvod / autor</th><th>Ukončení</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=csH($row['jmeno'])?></td><td><?=csH($row['scope_name'])?> (#<?=(int)$row['scope_id']?>)</td><td><?=csH($row['valid_from'])?> – <?=csH($row['valid_to']??'bez konce')?></td><td><?=csH($row['reason'])?> · pracovník #<?=(int)$row['created_by_trainer_id']?></td><td><?php if($row['revoked_at']):?><?=csH($row['revoked_at'].' · '.$row['revoke_reason'])?> · #<?=(int)$row['revoked_by_trainer_id']?><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=csH(csrf_token())?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="grant_id" value="<?=(int)$row['id']?>"><input name="reason" required minlength="10" maxlength="1000" class="form-control form-control-sm" placeholder="Důvod ukončení"><button class="btn btn-sm btn-outline-danger mt-1">Ukončit přístup</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
</main></body></html>
