<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/person_identity_review.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sprava_sportovcu')) { http_response_code(403); exit('Nemáte oprávnění ke správě osob.'); }
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
function pirH(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$error=''; $id=max(0,(int)($_GET['id']??0));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token']??''))) throw new RuntimeException('Formulář vypršel. Obnovte stránku.');
        if (($_POST['action']??'')==='create') {
            $id=personIdentityReviewCreate($pdo,$_POST,(int)$_SESSION['trener_id']);
        } elseif (($_POST['action']??'')==='decide') {
            $id=(int)($_POST['review_id']??0);
            personIdentityReviewDecide($pdo,$id,(int)($_POST['revision']??0),(string)($_POST['decision']??''),(int)($_POST['sportovec_id']??0),(string)($_POST['reason']??''),(string)($_POST['evidence_ref']??''),(int)$_SESSION['trener_id']);
        } else throw new InvalidArgumentException('Neznámá akce.');
        header('Location: identity_reviews_admin.php?id='.$id.'&saved=1'); exit;
    } catch (Throwable $e) { $error=$e instanceof PDOException?'Zápis se nezdařil. Obnovte stránku; žádná částečná změna nebyla uložena.':$e->getMessage(); }
}
$review=null; $candidates=[]; $events=[];
if ($id>0) {
    try {
        $review=personIdentityReviewGet($pdo,$id);
        $candidates=personMatchV1($pdo,['jmeno'=>$review['first_name'],'prijmeni'=>$review['last_name'],'narozeni'=>$review['birth_date']])['candidates'];
        $s=$pdo->prepare('SELECT e.*,t.jmeno AS actor_name FROM person_identity_review_events e LEFT JOIN treneri t ON t.id=e.actor_trainer_id WHERE e.review_id=? ORDER BY e.revision DESC');
        $s->execute([$id]); $events=$s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $error=$e instanceof PDOException?'Podklady se nepodařilo načíst.':$e->getMessage(); }
}
$status=(string)($_GET['status']??'review');
if (!in_array($status,['review','deferred','verified','excluded'],true)) $status='review';
$s=$pdo->prepare('SELECT * FROM person_identity_reviews WHERE status=? ORDER BY id DESC LIMIT 200'); $s->execute([$status]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
$labels=['review'=>'Čeká na ověření','deferred'=>'Odloženo k řešení','verified'=>'Ověřená vazba','excluded'=>'Bez převodu'];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ověření identity</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet"></head><body>
<?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><h1>Ověření identity a zdrojové vazby</h1>
<p>Každá osoba má stabilní interní ID. Jméno, e-mail, UCI ani variabilní symbol je nenahrazují. Tato evidence osobu neslučuje, neimportuje historii a nedává přístup k účtu.</p>
<?php if ($error): ?><div class="alert alert-danger"><?=pirH($error)?></div><?php endif; ?>
<?php if (isset($_GET['saved']) && !$error): ?><div class="alert alert-success">Případ byl uložen včetně historie rozhodnutí.</div><?php endif; ?>
<?php if ($review): ?>
<section class="card mb-4"><div class="card-body"><h2 class="h4">Případ #<?=$id?> · <?=pirH($labels[$review['status']]??$review['status'])?></h2>
<p><strong><?=pirH($review['first_name'].' '.$review['last_name'])?></strong> · <?=pirH($review['birth_date']??'Datum narození neznámé')?><br>Zdroj <?=pirH($review['source_namespace'])?> / <?=pirH($review['source_id'])?> · revize <?=(int)$review['revision']?></p>
<p><?=pirH($review['reason'])?> Podklad: <?=pirH($review['evidence_ref'])?><?php if ($review['sportovec_id']): ?> · cílová osoba #<?=(int)$review['sportovec_id']?><?php endif; ?></p>
<h3 class="h5">Aktuální kandidáti — shoda není automatické ověření</h3>
<?php if (!$candidates): ?><p>Žádný kandidát podle jména a data narození. Jinou doloženou osobu lze vybrat výslovným interním ID.</p><?php endif; ?>
<ul><?php foreach ($candidates as $candidate): ?><li>#<?=(int)$candidate['id']?> <?=pirH($candidate['jmeno'].' '.$candidate['prijmeni'])?> · <?=pirH($candidate['narozeni']??'narození neznámé')?> · <?=pirH(implode(', ',$candidate['rules']))?></li><?php endforeach; ?></ul>
<form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?=pirH(csrf_token())?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="review_id" value="<?=$id?>"><input type="hidden" name="revision" value="<?=(int)$review['revision']?>">
<div class="col-md-6"><label class="form-label">Rozhodnutí<select name="decision" class="form-select" required><option value="deferred">Odložit — identita není doložená</option><option value="verified">Ověřit vazbu na vybranou osobu</option><option value="excluded">Vyloučit z převodu</option></select></label></div>
<div class="col-md-6"><label class="form-label">Interní ID cílové osoby (jen pro ověření)<input type="number" name="sportovec_id" min="1" class="form-control"></label></div>
<div class="col-12"><label class="form-label w-100">Důvod rozhodnutí<textarea name="reason" required minlength="10" maxlength="2000" class="form-control"></textarea></label></div>
<div class="col-12"><label class="form-label w-100">Označení ověřeného podkladu, bez rodného čísla a dalších citlivých hodnot<input name="evidence_ref" required minlength="3" maxlength="255" class="form-control" value="<?=pirH($review['evidence_ref'])?>"></label></div>
<div><button class="btn btn-primary">Uložit výslovné rozhodnutí</button></div></form>
<details class="mt-3"><summary>Historie rozhodnutí</summary><?php foreach ($events as $event): $after=json_decode($event['after_json'],true,32,JSON_THROW_ON_ERROR); ?><p>Revize <?=(int)$event['revision']?> · <?=pirH($event['created_at'])?> · <?=pirH($event['actor_name'])?> (#<?=(int)$event['actor_trainer_id']?>): <?=pirH($labels[$after['status']]??$after['status'])?>, osoba <?=pirH($after['sportovec_id']??'—')?>. <?=pirH($after['reason']??'Založení případu')?> · <?=pirH($after['evidence_ref']??'')?></p><?php endforeach; ?></details>
</div></section><?php endif; ?>
<details class="mb-4"><summary>Založit případ ze zdroje</summary><form method="post" class="row g-3 mt-1"><input type="hidden" name="csrf_token" value="<?=pirH(csrf_token())?>"><input type="hidden" name="action" value="create">
<?php foreach (['source_namespace'=>'Zdrojový systém / jmenný prostor','source_id'=>'Stabilní ID ve zdroji','jmeno'=>'Jméno','prijmeni'=>'Příjmení','evidence_ref'=>'Označení podkladu (bez citlivých hodnot)'] as $name=>$label): ?><div class="col-md-6"><label class="form-label"><?=pirH($label)?><input name="<?=pirH($name)?>" class="form-control" required maxlength="<?= $name==='source_namespace'?80:($name==='source_id'||$name==='jmeno'?100:255) ?>"></label></div><?php endforeach; ?>
<div class="col-md-6"><label class="form-label">Datum narození — neznámé ponechte prázdné<input type="date" name="narozeni" class="form-control"></label></div><div><button class="btn btn-outline-primary">Založit případ bez přiřazení</button></div></form></details>
<nav class="d-flex gap-2 flex-wrap mb-3"><?php foreach ($labels as $key=>$label): ?><a class="btn btn-sm <?=$status===$key?'btn-primary':'btn-outline-primary'?>" href="?status=<?=pirH($key)?>"><?=pirH($label)?></a><?php endforeach; ?></nav>
<p class="small text-muted">Nejnovějších nejvýše 200 případů ve vybraném stavu.</p>
<table class="table"><thead><tr><th>Případ</th><th>Zdroj</th><th>Osoba</th><th>Poslední důvod</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><a href="?id=<?=(int)$row['id']?>&amp;status=<?=pirH($status)?>">#<?=(int)$row['id']?></a></td><td><?=pirH($row['source_namespace'].' / '.$row['source_id'])?></td><td><?=pirH($row['first_name'].' '.$row['last_name'])?></td><td><?=pirH($row['reason'])?></td></tr><?php endforeach; ?></tbody></table>
</main></body></html>
