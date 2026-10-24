<?php // Included only by the authenticated operational overview.
if (!isset($cases,$pdo,$position)) { http_response_code(404);exit; }
?>
<?php if ($caseError!==''): ?><div class="alert alert-danger"><?=operationalPageH($caseError)?></div><?php endif; ?>
<?php if ($caseMessage!==''): ?><div class="alert alert-success"><?=operationalPageH($caseMessage)?></div><?php endif; ?>
<?php if ($cases['unavailable']): ?><div class="alert alert-warning">Nedostupné zdroje: <?=operationalPageH(implode(', ',$cases['unavailable']))?>. Chybějící údaje neznamenají vyřešené případy.</div><?php endif; ?>
<?php if (in_array($position,['registrar','order_operator','finance_manager','sports_lead'],true)): ?>
<section id="pripady" class="mb-4"><h2 class="h4">Případy k dořešení</h2>
<p class="text-muted">Nejvýše 30 položek z každého zdroje na stránku. Nepřidělené případy jsou uvedeny první.</p>
<p><a href="?closed=0#pripady">Otevřené případy</a> · <a href="?closed=1#pripady">Historie uzavřených případů</a></p>
<?php if (!$cases['items'] && !$cases['unavailable']): ?><p>V dostupných zdrojích není otevřený případ.</p><?php endif; ?>
<?php foreach($cases['items'] as $case): $key=$case['source_type'].'-'.$case['source_id']; ?>
<article class="card mb-3" id="case-<?=operationalPageH($key)?>"><div class="card-body">
<h3 class="h6"><?=operationalPageH($case['label'])?> #<?=(int)$case['source_id']?> <a href="<?=operationalPageH($case['href'])?>" class="btn btn-sm btn-outline-primary">Otevřít agendu</a></h3>
<?php if(isset($case['context_date'])):?><p><?=operationalPageH($case['context_date'].' · '.$case['context_label'])?></p><?php endif;?>
<?php if (!$case['owner_trainer_id']): ?><p class="text-danger">Chybí odpovědná osoba.</p>
<?php elseif (!(int)$case['owner_active'] || !in_array((int)$case['owner_trainer_id'],array_map('intval',array_column($owners,'id')),true)): ?><p class="text-danger">Odpovědná osoba již nemá oprávnění. Případ předejte.</p><?php endif; ?>
<?php if ($case['review_on'] && $case['review_on']<date('Y-m-d')): ?><p class="text-danger">Termín kontroly již uplynul.</p><?php endif; ?>
<?php if (!(int)$case['unresolved']): ?><p class="text-success">Zdroj je vyřešený; lze uzavřít úkol.</p><?php endif; ?>
<form method="post" class="row g-2"><?=csrf_field()?>
<input type="hidden" name="source_type" value="<?=operationalPageH($case['source_type'])?>"><input type="hidden" name="source_id" value="<?=(int)$case['source_id']?>"><input type="hidden" name="revision" value="<?=(int)$case['revision']?>">
<div class="col-md-4"><label class="form-label" for="owner-<?=$key?>">Odpovědná osoba</label><select class="form-select" name="owner_trainer_id" id="owner-<?=$key?>" required><option value="">Vyberte</option><?php foreach($owners as $owner): ?><option value="<?=(int)$owner['id']?>" <?=(int)$case['owner_trainer_id']===(int)$owner['id']?'selected':''?>><?=operationalPageH($owner['jmeno'])?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" for="date-<?=$key?>">Datum kontroly</label><input class="form-control" type="date" name="review_on" id="date-<?=$key?>" value="<?=operationalPageH($case['review_on']??date('Y-m-d'))?>" required></div>
<div class="col-md-4"><label class="form-label" for="state-<?=$key?>">Stav úkolu</label><select class="form-select" name="status" id="state-<?=$key?>"><option value="open">Otevřený</option><?php if (!(int)$case['unresolved']): ?><option value="closed">Uzavřít</option><?php endif; ?></select></div>
<div class="col-md-6"><label class="form-label" for="step-<?=$key?>">Další krok</label><input class="form-control" name="next_step" id="step-<?=$key?>" maxlength="1000" value="<?=operationalPageH($case['next_step'])?>" required></div>
<div class="col-md-6"><label class="form-label" for="reason-<?=$key?>">Důvod zápisu / předání</label><input class="form-control" name="reason" id="reason-<?=$key?>" maxlength="1000" required></div>
<div><button class="btn btn-primary">Uložit odpovědnost a krok</button></div></form>
<?php if ($case['case_id']): $history=$pdo->prepare('SELECT e.*,t.jmeno FROM operational_case_events e JOIN treneri t ON t.id=e.actor_trainer_id WHERE e.case_id=? ORDER BY e.id DESC LIMIT 30');$history->execute([(int)$case['case_id']]); ?>
<details class="mt-3"><summary>Historie předání a rozhodnutí (posledních 30)</summary><ul><?php foreach($history->fetchAll(PDO::FETCH_ASSOC) as $event): ?><li><?=operationalPageH($event['created_at'].' · '.$event['jmeno'].' · '.$event['reason'])?><pre class="small text-wrap"><?=operationalPageH($event['after_json'])?></pre></li><?php endforeach; ?></ul></details><?php endif; ?>
</div></article><?php endforeach; ?>
<nav><?php if ($cases['page']>1): ?><a class="btn btn-outline-secondary" href="?closed=<?=(int)$cases['closed']?>&amp;page=<?=$cases['page']-1?>#pripady">Předchozí</a><?php endif; ?><?php if ($cases['has_next']): ?> <a class="btn btn-outline-secondary" href="?closed=<?=(int)$cases['closed']?>&amp;page=<?=$cases['page']+1?>#pripady">Další stránka</a><?php endif; ?></nav></section>
<?php endif; ?>
<?php if ($jobs): ?>
<section class="card mb-4"><div class="card-body"><h2 class="h4">Automatické úlohy</h2>
<p>Registr úloh nenastavuje plánovač ani nezapíná odesílání. <a href="diagnostika_site_admin.php">Diagnostika a ruční postupy</a></p>
<div class="table-responsive"><table class="table"><thead><tr><th>Úloha / vlastník</th><th>Stav</th><th>Poslední úspěch (UTC)</th><th>Další krok</th></tr></thead><tbody>
<?php foreach($jobs as $job): ?><tr>
<td><?=operationalPageH($job['label'].' · '.$job['owner'])?><br><small>Interval <?=(int)$job['interval_seconds']?> s</small></td>
<td><?=operationalPageH($job['status_label'])?></td>
<td><?=operationalPageH($job['last_success_at']??'Dosud nedoložen')?><?php if($job['last_duration_seconds']!==null):?><br><small>Poslední běh: <?=(int)$job['last_duration_seconds']?> s</small><?php endif;?></td>
<td><?=operationalPageH($job['next_action'])?></td>
</tr><?php endforeach; ?></tbody></table></div></div></section><?php endif; ?>
