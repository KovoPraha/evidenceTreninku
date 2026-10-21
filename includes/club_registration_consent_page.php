<?php
declare(strict_types=1);

// Included only after the registrar route guard in registration_texts_admin.php.
registrationTextsRequireRegistrar();
function customConsentH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$id=(int)($_POST['custom_id']??$_GET['custom']??0);$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        $action=(string)($_POST['custom_action']??'');$draftId=0;
        if($action==='save') {
            $title=(string)($_POST['title']??'');$text=(string)($_POST['text']??'');$reason=(string)($_POST['reason']??'');
            $required=($_POST['custom_required']??'')==='1';$visible=($_POST['custom_visible']??'')==='1';
            if($id===0){$created=clubConsentCreate($pdo,$title,$text,$reason,$required,$visible);$id=$created['id'];$draftId=$created['draft_id'];}
            else $draftId=clubConsentSaveDraft($pdo,$id,(int)($_POST['base_term_id']??0),$title,$text,$reason,$required,$visible);
            $_SESSION['custom_consent_flash']='Koncept je uložen. Zkontrolujte název, text i nastavení a samostatně ho zveřejněte.';
        }elseif($action==='publish') {
            $draftId=(int)($_POST['draft_id']??0);clubConsentPublish($pdo,$id,$draftId,($_POST['confirmed']??'')==='1');
            $_SESSION['custom_consent_flash']='Nová verze souhlasu a jeho nastavení jsou zveřejněné.';
        }elseif($action==='hide') {
            clubConsentHide($pdo,$id,(int)($_POST['base_term_id']??0),($_POST['confirmed']??'')==='1');
            $_SESSION['custom_consent_flash']='Souhlas je skrytý pro nové přihlášky. Dřívější odpovědi a historie jsou zachované.';
        }else throw new InvalidArgumentException('Neznámá operace.');
        header('Location: registration_texts_admin.php?custom='.$id.($draftId>0?'&draft_id='.$draftId:''),true,303);exit;
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('Custom consent administration: '.get_class($e));$error='Operaci se nepodařilo dokončit. Zkontrolujte stav a zkuste ji znovu.';}
}
try{$current=$id>0?clubConsentTerm($pdo,$id):null;}catch(InvalidArgumentException){http_response_code(404);exit('Vlastní souhlas nebyl nalezen.');}
$selected=null;$drafts=[];$history=[];
if($id>0) {
    $q=$pdo->prepare('SELECT * FROM athlete_registration_text_drafts WHERE custom_consent_id=? ORDER BY id DESC');$q->execute([$id]);$drafts=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($drafts as $draft)if((int)$draft['id']===(int)($_POST['draft_id']??$_GET['draft_id']??0))$selected=$draft;
    $definition=clubConsentDefinition($pdo,$id);
    $q=$pdo->prepare("SELECT t.*,d.reason,(SELECT COUNT(*) FROM club_registration_consent_acceptances a WHERE a.term_version_id=t.id AND a.accepted=1) AS accepted_count,(SELECT COUNT(*) FROM club_registration_consent_acceptances a WHERE a.term_version_id=t.id AND a.accepted=0) AS declined_count FROM club_event_term_versions t LEFT JOIN athlete_registration_text_drafts d ON d.published_term_id=t.id WHERE t.scope_type='club_registration' AND t.scope_key='all-programs' AND t.consent_purpose=? ORDER BY t.id DESC");
    $q->execute([$definition['purpose']]);$history=$q->fetchAll(PDO::FETCH_ASSOC);
}
$editingPost=$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['custom_action']??'')==='save';
$source=$selected??$current??[];
$title=$editingPost?(string)($_POST['title']??''):(string)($source['title_plain']??'');
$text=$editingPost?(string)($_POST['text']??''):(string)($source['text_plain']??$source['consent_text_plain']??'');
$reason=$editingPost?(string)($_POST['reason']??''):(string)($selected['reason']??'');
$required=$editingPost?($_POST['custom_required']??'')==='1':(int)($source['custom_required']??0)===1;
$visible=$editingPost?($_POST['custom_visible']??'')==='1':(int)($source['custom_visible']??1)===1;
$names=$pdo->query('SELECT id,jmeno FROM treneri')->fetchAll(PDO::FETCH_KEY_PAIR);
$flash=(string)($_SESSION['custom_consent_flash']??'');unset($_SESSION['custom_consent_flash']);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vlastní souhlas ke kroužkům</title><?php appUiAssets();?></head><body><?php include dirname(__DIR__).'/hlavicka.php';?>
<main class="container py-4"><a href="registration_texts_admin.php">← Registrační texty a souhlasy</a>
<h1 class="h3 mt-3"><?=$id>0?'Vlastní souhlas ke kroužkům':'Přidat vlastní souhlas'?></h1>
<p>Vlastní souhlasy se používají při přihlášení do všech kroužků. Změnu názvu, textu, povinnosti i viditelnosti nejprve uložte jako koncept a poté zveřejněte.</p>
<?php if($error!==''):?><div class="alert alert-danger" role="alert"><?=customConsentH($error)?></div><?php endif;?>
<?php if($flash!==''):?><div class="alert alert-success" role="status"><?=customConsentH($flash)?></div><?php endif;?>
<?php if($current):?><section class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Aktuální nastavení</h2></div><div class="card-body"><h3 class="h5 text-break"><?=customConsentH($current['title_plain'])?></h3>
<p><strong><?=(int)$current['custom_visible']===1?'Viditelný v přihláškách':'Skrytý pro nové přihlášky'?></strong> · <?=(int)$current['custom_required']===1?'Povinný':'Volitelný'?> · <?=customConsentH($current['terms_version'])?></p>
<div class="text-break mb-3"><?=nl2br(customConsentH($current['consent_text_plain']))?></div>
<?php if((int)$current['custom_visible']===1):?><form method="post"><?=csrf_field()?><input type="hidden" name="custom_action" value="hide"><input type="hidden" name="custom_id" value="<?=$id?>"><input type="hidden" name="base_term_id" value="<?=(int)$current['id']?>">
<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="confirm-hide" required><label class="form-check-label" for="confirm-hide">Chci souhlas skrýt z nových přihlášek a ponechat jeho historii.</label></div><button class="btn btn-outline-danger">Skrýt souhlas</button></form><?php endif;?></div></section><?php endif;?>
<?php if($selected):?><section class="card border-primary mb-4"><div class="card-header"><h2 class="h5 mb-0">Náhled konceptu č. <?=(int)$selected['id']?></h2></div><div class="card-body"><h3 class="h5 text-break"><?=customConsentH($selected['title_plain'])?></h3>
<p><?=(int)$selected['custom_visible']===1?'Zobrazovat při přihlášení':'Skrýt z přihlášek'?> · <?=(int)$selected['custom_required']===1?'Povinný':'Volitelný'?> · <?=customConsentH($names[(int)$selected['created_by']]??'')?> · <?=customConsentH($selected['created_at'])?></p>
<div class="text-break mb-3"><?=nl2br(customConsentH($selected['text_plain']))?></div><p>Důvod: <?=customConsentH($selected['reason'])?></p>
<?php if($selected['published_term_id']!==null):?><div class="alert alert-success">Tato verze už byla zveřejněna.</div>
<?php elseif((int)$selected['base_term_id']!==(int)$current['id']):?><div class="alert alert-warning">Koncept vychází ze starší verze. Porovnejte aktuální nastavení a uložte nový koncept.</div>
<?php else:?><form method="post"><?=csrf_field()?><input type="hidden" name="custom_action" value="publish"><input type="hidden" name="custom_id" value="<?=$id?>"><input type="hidden" name="draft_id" value="<?=(int)$selected['id']?>"><div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="confirm-publish-custom" name="confirmed" value="1" required><label class="form-check-label" for="confirm-publish-custom">Klub schválil název, znění, povinnost a viditelnost. Chci nyní zveřejnit tuto verzi.</label></div><button class="btn btn-success">Zveřejnit tuto verzi</button></form><?php endif;?></div></section><?php endif;?>
<section class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Připravit koncept</h2></div><div class="card-body"><form method="post"><?=csrf_field()?><input type="hidden" name="custom_action" value="save"><input type="hidden" name="custom_id" value="<?=$id?>"><input type="hidden" name="base_term_id" value="<?=(int)($current['id']??0)?>">
<label class="form-label" for="custom-title">Název souhlasu</label><input type="text" class="form-control mb-3" id="custom-title" name="title" value="<?=customConsentH($title)?>" maxlength="200" required>
<label class="form-label" for="custom-text">Znění souhlasu</label><textarea class="form-control mb-3" id="custom-text" name="text" rows="7" maxlength="12000" required><?=customConsentH($text)?></textarea>
<div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="custom_required" id="custom-required" value="1" <?=$required?'checked':''?>><label class="form-check-label" for="custom-required">Povinný — bez potvrzení nepůjde přihlášku dokončit</label></div>
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="custom_visible" id="custom-visible" value="1" <?=$visible?'checked':''?>><label class="form-check-label" for="custom-visible">Zobrazovat při přihlášení do kroužku</label></div>
<label class="form-label" for="custom-reason">Důvod změny</label><textarea class="form-control mb-3" id="custom-reason" name="reason" rows="2" maxlength="1000" required><?=customConsentH($reason)?></textarea><button class="btn btn-primary">Uložit koncept a zobrazit náhled</button></form></div></section>
<?php if($drafts):?><section class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Uložené koncepty</h2></div><div class="card-body"><?php foreach($drafts as $draft):?><div class="mb-2"><a href="?custom=<?=$id?>&amp;draft_id=<?=(int)$draft['id']?>">Koncept č. <?=(int)$draft['id']?> — <?=customConsentH($draft['title_plain'])?></a> · <?=$draft['published_term_id']===null?'Koncept':'Zveřejněný'?></div><?php endforeach;?></div></section><?php endif;?>
<?php if($history):?><section class="card"><div class="card-header"><h2 class="h5 mb-0">Historie verzí a nastavení</h2></div><div class="card-body"><?php foreach($history as $term):?><details class="border rounded p-3 mb-2"><summary class="text-break"><?=customConsentH($term['title_plain'])?> · <?=(int)$term['custom_visible']===1?'Viditelný':'Skrytý'?> · <?=(int)$term['custom_required']===1?'Povinný':'Volitelný'?> · <?=customConsentH($term['created_at'])?></summary><div class="mt-3 text-break"><p><?=customConsentH($names[(int)$term['actor_id']]??'')?> · <?=customConsentH($term['terms_version'])?> · <?=customConsentH($term['reason']??'Vytvoření skrytého souhlasu')?></p><p>Potvrzeno: <?=(int)$term['accepted_count']?> · Nepotvrzeno: <?=(int)$term['declined_count']?></p><?=nl2br(customConsentH($term['consent_text_plain']))?></div></details><?php endforeach;?></div></section><?php endif;?>
</main></body></html>
