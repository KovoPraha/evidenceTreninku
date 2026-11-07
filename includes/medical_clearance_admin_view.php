<?php

declare(strict_types=1);

if (!isset($medicalViewData) || !is_array($medicalViewData)) {
    throw new LogicException('Medical view requires its controller context.');
}
['error' => $error, 'selected' => $selected, 'id' => $id, 'records' => $records, 'events' => $events,
    'contextPerson' => $contextPerson, 'medicalList' => $medicalList, 'watches' => $watches, 'labels' => $labels,
    'personChoices' => $personChoices, 'personLookupContext' => $personLookupContext, 'people' => $people,
    'chosenPerson' => $chosenPerson, 'policies' => $policies] = $medicalViewData;
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Zdravotní prohlídky</title>
<?php appUiAssets();?>
</head>
<body>
<?php include dirname(__DIR__) . '/hlavicka.php';?>
<main class="container py-4">
<h1>Zdravotní prohlídky</h1>
<p>Evidujte platnost uvedenou v podkladu. Zátěžový test je samostatná sportovní agenda. Nahraný dokument čeká na výslovné ověření.</p>
<p>
<a href="provozni_prehled_admin.php">Případy a odpovědnosti — přiřadit vlastníka a další krok</a> · <a href="medical_clearance_admin.php">Všechny podklady</a>
</p>
<?php if ($error !== ''):?>
<p class="alert alert-danger" role="alert">
<?=medicalH($error)?>
</p>
<?php endif;?>
<?php if ($selected):?>
<section class="card card-body mb-4">
<h2 class="h4">
<?=medicalH($selected['purpose'])?> · osoba #<?=(int)$selected['sportovec_id']?>
</h2>
<p>
<?=medicalH($selected['activity_rule'])?>
</p>
<h3 class="h5">Doložit nový podklad</h3>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<input type="hidden" name="action" value="record">
<input type="hidden" name="watch_id" value="<?=$id?>">
<div class="row g-3">
<label class="col-md-3">Vystaveno<input class="form-control" type="date" name="issued_on" required>
</label>
<label class="col-md-3">Platnost do včetně<input class="form-control" type="date" name="valid_until" required>
</label>
<label class="col-md-6">Umístění papírového originálu<input class="form-control" name="original_location" maxlength="1000">
</label>
<label class="col-md-6">Soukromý podklad<input class="form-control" type="file" name="evidence" accept="application/pdf,image/jpeg,image/png">
</label>
<label class="col-md-6">Důvod zápisu<input class="form-control" name="reason" maxlength="1000" required>
</label>
</div>
<button class="btn btn-primary my-3">Uložit k ověření</button>
</form>
<?php foreach ($records as $r):?>
<article class="border rounded p-3 mb-3">
<h3 class="h5">Podklad #<?=(int)$r['id']?> — <?=medicalH($labels[$r['state']] ?? $r['state'])?>
</h3>
<p>
<?=medicalH($r['issued_on'])?> až <?=medicalH($r['valid_until'])?> · <?=medicalH($r['original_location'])?>
</p>
<?php if ($r['storage_key']):?>
<p>
<a href="medical_clearance_file.php?id=<?=(int)$r['id']?>">Stáhnout soukromý podklad</a>
</p>
<?php endif;?>
<?php if (in_array($r['state'], ['pending','verified','superseded'], true)):?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="decide">
<input type="hidden" name="watch_id" value="<?=$id?>">
<input type="hidden" name="record_id" value="<?=(int)$r['id']?>">
<input type="hidden" name="revision" value="<?=(int)$r['revision']?>">
<label>Důvod rozhodnutí<input class="form-control mb-2" name="reason" required maxlength="1000">
</label>
<?php if ($r['state'] === 'pending'):?>
<button class="btn btn-success" name="decision" value="verified">Potvrdit ověření a nahradit předchozí podklad</button>
<button class="btn btn-outline-secondary" name="decision" value="rejected">Odmítnout</button>
<?php endif;?> <button class="btn btn-outline-danger" name="decision" value="revoked">Odvolat</button>
</form>
<?php endif;?>
</article>
<?php endforeach;?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="contact">
<input type="hidden" name="watch_id" value="<?=$id?>">
<label>Osobní kontakt: komu, kdy a další postup<input class="form-control mb-2" name="reason" required maxlength="1000">
</label>
<button class="btn btn-outline-primary">Zaznamenat osobní vyřízení</button>
<p class="small">Kontakt neznamená ověření podkladu ani odeslaný e-mail.</p>
</form>
<details>
<summary>Historie</summary>
<ul>
<?php foreach ($events as $e):?>
<li>
<?=medicalH($e['created_at'].' · '.$e['jmeno'].' · '.($labels[$e['action']] ?? $e['action']).' · '.$e['reason'])?>
</li>
<?php endforeach;?>
</ul>
</details>
</section>
<?php endif;?>
<p>
<a href="medical_activity_admin.php">Podklady skupiny k datu aktivity</a>
</p>
<h2 class="h4">Sledované podklady</h2>
<p>Stav se vyhodnocuje k dnešnímu dni. Období filtruje termín další kontroly.</p>
<?php staffListControls($medicalList['filters'], $medicalList, 'Kontrola', $contextPerson ? ['person_id' => $contextPerson] : []);?>
<table class="table">
<thead>
<tr>
<th>Osoba</th>
<th>Účel</th>
<th>Stav dnes</th>
<th>Kontrola od</th>
</tr>
</thead>
<tbody>
<?php foreach ($watches as $w):$status = $w['evaluated_status'];?>
<tr>
<td>
<a href="?id=<?=(int)$w['id']?>">
<?=medicalH($w['prijmeni'].' '.$w['jmeno'])?>
</a>
</td>
<td>
<?=medicalH($w['purpose'])?>
</td>
<td>
<?=medicalH($labels[$status['status']])?>
</td>
<td>
<?=medicalH($w['next_review_on'])?>
</td>
</tr>
<?php endforeach;?>
</tbody>
</table>
<details class="mb-4" <?=($personChoices['query'] !== '' || $personChoices['page'] > 1) ? 'open' : ''?>>
<summary>Začít sledovat podklad osoby</summary>
<form method="get" class="my-3" aria-label="Vyhledání osoby pro zdravotní podklad">
<?php foreach ($personLookupContext as $key => $value):?>
<input type="hidden" name="<?=medicalH($key)?>" value="<?=medicalH($value)?>">
<?php endforeach;?>
<label>Hledat osobu jménem nebo číslem
<input class="form-control" name="medical_person_q" maxlength="100" value="<?=medicalH($personChoices['query'])?>">
</label>
<button class="btn btn-outline-primary mt-2">Vyhledat osobu</button>
</form>
<p role="status">Nalezeno osob: <?=(int)$personChoices['total']?> · strana <?=(int)$personChoices['page']?> z <?=(int)$personChoices['pages']?>
</p>
<?php foreach (['Předchozí osoby' => $personChoices['page'] - 1,'Další osoby' => $personChoices['page'] + 1] as $label => $page):?>
<?php if ($page >= 1 && $page <= $personChoices['pages']):?>
<a data-medical-person-page class="btn btn-outline-secondary btn-sm mb-3 me-2" href="?<?=medicalH(http_build_query($personLookupContext + [
 'medical_person_q' => $personChoices['query'],'medical_person_page' => $page]))?>">
<?=medicalH($label)?>
</a>
<?php endif;?>
<?php endforeach;?>
<p class="small">Osobu vyhledejte nebo přejděte na další stránku. Dříve vybraná osoba zůstává v nabídce i mimo výsledky hledání.</p>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="watch">
<label>Osoba
<select class="form-select" name="sportovec_id" data-medical-person-choice required>
<option value="">Vyberte osobu</option>
<?php foreach ($people as $p):?>
<option value="<?=(int)$p['id']?>" <?=$chosenPerson === (int)$p['id'] ? 'selected' : ''?>>
<?=medicalH($p['prijmeni'].' '.$p['jmeno'].' #'.$p['id'])?>
</option>
<?php endforeach;?>
</select>
</label>
<label>Potvrzená pravidla
<select class="form-select" name="policy_id" required>
<option value="">Vyberte účel</option>
<?php foreach ($policies as $p):?>
<option value="<?=(int)$p['id']?>">
<?=medicalH($p['purpose'].' #'.$p['id'])?>
</option>
<?php endforeach;?>
</select>
</label>
<label>Důvod
<input class="form-control" name="reason" required maxlength="1000">
</label>
<button class="btn btn-primary mt-2">Založit sledování</button>
</form>
</details>
<details>
<summary>Potvrdit pravidla nového účelu</summary>
<p>Pravidla stanoví pověřený pracovník oddílu. Záznam neurčuje zdravotní způsobilost ani automaticky neomezuje účast.</p>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="policy">
<?php foreach (['purpose' => 'Účel podkladu','legal_basis' => 'Potvrzený právní důvod zpracování',
    'retention_rule' => 'Pravidlo uchování a výmazu',
    'activity_rule' => 'Aktivity vyžadující podklad a odpovědná osoba při chybějící platnosti'] as $key => $label):?>
<label class="d-block mb-2">
<?=$label?>
<input class="form-control" name="<?=$key?>" required maxlength="<?=$key === 'purpose' ? 100 : 1000?>">
</label>
<?php endforeach;?>
<label>Upozornit dní před koncem<input class="form-control" type="number" name="reminder_days" min="0" max="365" required>
</label>
<label class="d-block my-2">
<input type="checkbox" name="confirmed" value="1" required> Potvrzuji pravidla pro tento účel</label>
<button class="btn btn-primary">Uložit pravidla</button>
</form>
</details>
</main>
<script nonce="<?=medicalH(app_csp_nonce())?>" src="<?=medicalH(appUiUrl('assets/js/medical-person-picker.js'))?>"></script>
</body>
</html>
