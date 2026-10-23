<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/paper_registration.php';
require_once __DIR__.'/includes/paper_registration_labels.php';
try { paperRegistrationRequireRegistrar(); }
catch (DomainException $e) { http_response_code(403); exit('Přístup má pouze Registrář členů a KIS.'); }
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
function paperH(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$error = ''; $id = (int)($_GET['id'] ?? 0); $personId = (int)($_POST['sportovec_id'] ?? $_GET['person_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.'); }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'record') {
            $input=$_POST;
            if (array_key_exists('scope_reference',$input)) {
                $ref=is_string($input['scope_reference'])?$input['scope_reference']:'';
                if ($ref==='') $input['scope_id']=0;
                else {
                    $parts=explode(':',$ref,2);
                    if (count($parts)!==2 || $parts[0]!==($input['scope_type']??'')) throw new InvalidArgumentException('Vybraný program nebo akce musí odpovídat účelu podkladu.');
                    $input['scope_id']=$parts[1];
                }
            }
            $id = paperRegistrationRecord($pdo,$input,$_FILES['evidence'] ?? null);
        }
        else {
            $id = (int)($_POST['id'] ?? 0);
            paperRegistrationDecide($pdo,$id,$action,(string)($_POST['reason'] ?? ''));
        }
        header('Location: paper_registrations_admin.php?id='.$id,true,303); exit;
    } catch (InvalidArgumentException|DomainException $e) { $error=$e->getMessage(); }
    catch (Throwable $e) { error_log('Paper registration operation failed: '.get_class($e)); $error='Podklad se nepodařilo uložit. Původní evidence zůstala zachována.'; }
}
$selected = null; $events = [];
if ($id > 0) {
    try {
        $selected = paperRegistrationGet($pdo,$id); $personId=(int)$selected['sportovec_id'];
        $q=$pdo->prepare('SELECT e.*,t.jmeno AS actor_name FROM club_paper_record_events e JOIN treneri t ON t.id=e.actor_id WHERE record_id=? ORDER BY e.id'); $q->execute([$id]); $events=$q->fetchAll(PDO::FETCH_ASSOC);
    } catch (DomainException $e) { $error=$e->getMessage(); }
}
$q=$pdo->prepare("SELECT p.*,s.jmeno,s.prijmeni,(SELECT e.event_type FROM club_paper_record_events e WHERE e.record_id=p.id AND e.event_type<>'file_viewed' ORDER BY e.id DESC LIMIT 1) AS state FROM club_paper_records p JOIN sportovci s ON s.id=p.sportovec_id ".($personId>0?'WHERE p.sportovec_id=? ':'').'ORDER BY p.id DESC LIMIT 100');
$q->execute($personId>0?[$personId]:[]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
$people=$pdo->query('SELECT id,jmeno,prijmeni,narozeni FROM sportovci ORDER BY prijmeni,jmeno,id')->fetchAll(PDO::FETCH_ASSOC);
$terms=$pdo->query('SELECT id,scope_type,scope_key,consent_purpose,terms_version,title_plain FROM club_event_term_versions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$programs=$pdo->query('SELECT id,name FROM club_programs ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);
$clubEvents=$pdo->query('SELECT id,name FROM club_events ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);
$states=['recorded'=>'Zaevidováno – k posouzení','accepted'=>'Podklad ověřen','rejected'=>'Odmítnuto','revoked'=>'Odvoláno','superseded'=>'Nahrazeno opravou','file_viewed'=>'Zobrazení důkazu'];
$correction=$selected!==null && ($_GET['correct']??'')==='1';
$form=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:($correction?$selected:[]);
$value=static fn(string $key,mixed $default=''):mixed=>$form[$key]??$default;
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Papírové přihlášky a souhlasy</title><?php appUiAssets(); ?></head><body><?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><h1 class="h3">Papírové přihlášky a souhlasy</h1>
<p>Zaevidujte skutečně podepsaný podklad. Ověření podkladu samo nezakládá členství, účast, účet ani oprávnění rodiče. Tyto kroky vyřídíte samostatně na kartě osoby.</p>
<p><a href="eshop_identity_admin.php">Účty a ověření rodinných vazeb</a> · <a href="sprava_sportovcu.php">Najít nebo založit osobu</a> · <a href="paper_registrations_admin.php">Všechny podklady</a></p>
<?php if($error!==''):?><div class="alert alert-danger" role="alert"><?=paperH($error)?></div><?php endif;?>
<?php if($selected!==null):?><section class="card mb-4"><div class="card-body"><h2 class="h5">Podklad #<?=$id?>: <?=paperH($selected['document_title'])?></h2>
<p><strong><?=paperH($states[$selected['state']]??$selected['state'])?></strong> · <?=paperH($selected['version_label'])?> · Osoba #<?=$personId?></p>
<p>Podepsal/a <?=paperH($selected['signer_name'])?> (<?=paperH($selected['signer_relation'])?>), dne <?=paperH($selected['signed_on'])?>. Zaevidováno <?=paperH($selected['recorded_at'])?>, pracovník #<?=(int)$selected['recorded_by']?>.</p>
<p>Originál: <?=paperH($selected['original_location']?:'uložená soukromá příloha')?>. <?php if($selected['storage_key']):?><a href="paper_registration_file.php?id=<?=$id?>">Stáhnout důkaz</a><?php endif;?></p>
<?php if($selected['text_snapshot']!==''):?><details><summary>Zmrazené znění dokumentu</summary><p class="text-break"><?=nl2br(paperH($selected['text_snapshot']))?></p></details><?php endif;?>
<?php if($selected['replaces_id']):?><p>Oprava podkladu <a href="?id=<?=(int)$selected['replaces_id']?>">#<?=(int)$selected['replaces_id']?></a>.</p><?php endif;?>
<?php if(in_array($selected['state'],['recorded','accepted'],true)):?><form method="post" class="my-3"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><label class="form-label" for="decision-reason">Důvod rozhodnutí</label><input id="decision-reason" name="reason" required maxlength="1000" class="form-control mb-2">
<?php if($selected['state']==='recorded'):?><button name="action" value="accepted" class="btn btn-primary">Potvrdit ověření podkladu</button> <button name="action" value="rejected" class="btn btn-outline-danger">Odmítnout</button><?php endif;?> <button name="action" value="revoked" class="btn btn-outline-danger">Zaznamenat odvolání</button></form><?php endif;?>
<?php if($selected['state']!=='superseded'):?><a class="btn btn-outline-secondary" href="?id=<?=$id?>&amp;correct=1#record">Opravit novou verzí</a><?php endif;?>
<details class="mt-3"><summary>Auditní historie</summary><ul><?php foreach($events as $event):?><li><?=paperH($event['created_at'])?> · <?=paperH($event['actor_name'])?> · <?=paperH($states[$event['event_type']]??$event['event_type'])?> · <?=paperH($event['note'])?></li><?php endforeach;?></ul></details></div></section><?php endif;?>
<section class="card mb-4" id="record"><div class="card-body"><h2 class="h5"><?=$correction?'Oprava novou verzí':'Nový papírový podklad'?></h2>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="record"><input type="hidden" name="replaces_id" value="<?=(int)($_SERVER['REQUEST_METHOD']==='POST'?($_POST['replaces_id']??0):($correction?$id:0))?>">
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="person">Osoba</label><select id="person" name="sportovec_id" required class="form-select"><option value="">Vyberte ověřenou osobu</option><?php foreach($people as $p):?><option value="<?=(int)$p['id']?>" <?=(int)$value('sportovec_id',$personId)===(int)$p['id']?'selected':''?>><?=paperH($p['prijmeni'].' '.$p['jmeno'].' · '.$p['narozeni'].' · #'.$p['id'])?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label" for="kind">Druh</label><select id="kind" name="kind" class="form-select"><?php foreach(['application'=>'Přihláška','consent'=>'Souhlas'] as $k=>$label):?><option value="<?=$k?>" <?=$value('kind')===$k?'selected':''?>><?=$label?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label" for="scope">Účel</label><select id="scope" name="scope_type" class="form-select"><?php foreach(['membership'=>'Členství','program'=>'Kurz / kroužek','event'=>'Jednotlivá akce'] as $k=>$label):?><option value="<?=$k?>" <?=$value('scope_type')===$k?'selected':''?>><?=$label?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label" for="scope-id">Konkrétní program / akce</label><select id="scope-id" name="scope_reference" class="form-select"><option value="">Členství bez programu / akce</option><?php foreach(['program'=>['Kurzy a kroužky',$programs],'event'=>['Jednotlivé akce',$clubEvents]] as $type=>[$label,$options]):?><optgroup label="<?=$label?>"><?php foreach($options as $option): $reference=$type.':'.$option['id'];?><option value="<?=$reference?>" <?=($value('scope_reference')===$reference||($value('scope_type')===$type&&(int)$value('scope_id')===(int)$option['id']))?'selected':''?>><?=paperH($label.': '.$option['name'])?></option><?php endforeach;?></optgroup><?php endforeach;?></select></div>
<div class="col-md-8"><label class="form-label" for="title">Název dokumentu</label><input id="title" name="document_title" required maxlength="200" value="<?=paperH($value('document_title'))?>" class="form-control"></div>
<div class="col-12"><label class="form-label" for="term">Verze doložená papírem</label><select id="term" name="term_version_id" class="form-select"><option value="0">Verze neověřena — starý papír bez dohledatelné verze</option><?php foreach($terms as $term):?><option value="<?=(int)$term['id']?>" <?=(int)$value('term_version_id')===(int)$term['id']?'selected':''?>><?=paperH(paperRegistrationTermLabel($term))?></option><?php endforeach;?></select><p class="small text-body-secondary">Vyberte jen skutečně doloženou verzi. Dnešní verze se starému podpisu nepřiřazuje automaticky.</p></div>
<?php foreach(['signer_name'=>'Jméno podepisujícího','original_location'=>'Umístění originálu (povinné bez přílohy)','reason'=>'Důvod zaevidování / opravy'] as $key=>$label):?><div class="col-md-6"><label class="form-label" for="<?=$key?>"><?=$label?></label><input id="<?=$key?>" name="<?=$key?>" <?=$key!=='original_location'?'required':''?> maxlength="<?=$key==='signer_name'?200:1000?>" value="<?=paperH($value($key))?>" class="form-control"></div><?php endforeach;?>
<div class="col-md-3"><label class="form-label" for="relation">Vztah podepisujícího</label><select id="relation" name="signer_relation" class="form-select"><?php foreach(['self'=>'Sám / sama','guardian'=>'Zákonný zástupce','other'=>'Jiný doložený vztah'] as $k=>$label):?><option value="<?=$k?>" <?=$value('signer_relation')===$k?'selected':''?>><?=$label?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label" for="signed">Datum skutečného podpisu</label><input id="signed" name="signed_on" type="date" required max="<?=date('Y-m-d')?>" value="<?=paperH($value('signed_on'))?>" class="form-control"></div>
<div class="col-12"><label class="form-label" for="evidence">Soukromá příloha JPG, PNG nebo PDF (nejvýše 10 MB)</label><input id="evidence" name="evidence" type="file" accept="image/jpeg,image/png,application/pdf" class="form-control"><p class="small text-body-secondary">Oprava zachová původní důkaz. Pro novou verzi přiložte nový důkaz nebo znovu uveďte umístění originálu.</p></div></div>
<button class="btn btn-primary mt-3">Zaevidovat podklad</button></form></div></section>
<h2 class="h5">Posledních 100 podkladů<?=$personId>0?' osoby #'.$personId:''?></h2><div class="table-responsive"><table class="table"><thead><tr><th>Podklad</th><th>Osoba</th><th>Verze</th><th>Stav</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><a href="?id=<?=(int)$row['id']?>">#<?=(int)$row['id']?> <?=paperH($row['document_title'])?></a></td><td><?=paperH($row['prijmeni'].' '.$row['jmeno'])?></td><td><?=paperH($row['version_label'])?></td><td><?=paperH($states[$row['state']]??$row['state'])?></td></tr><?php endforeach;?></tbody></table></div>
</main></body></html>
