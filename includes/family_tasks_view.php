<section class="card card-body mb-4" id="family-tasks" aria-labelledby="family-tasks-title">
<h2 class="h5" id="family-tasks-title">Co zbývá vyřídit</h2>
<p class="small text-muted">Podklady a odpovědi k tréninkům v příštích 30 dnech. Osobní vyřízení s oddílem zůstává možné.</p>
<?php if($familyTasksError!==''):?><p class="alert alert-warning"><?=familyPageH($familyTasksError)?></p><?php else:?>
<?php if($familyTasks['unavailable']):?><p class="alert alert-warning">Nyní nelze ověřit: <?=familyPageH(implode(', ',$familyTasks['unavailable']))?>. Chybějící údaj neznamená vyřízený úkol.</p><?php endif;?>
<?php foreach(['family'=>'Na vás / domluvit s oddílem','club'=>'Čeká na vyřízení klubem'] as $kind=>$title):$items=array_values(array_filter($familyTasks['tasks'],static fn($task)=>$task['kind']===$kind));?>
<h3 class="h6"><?=$title?> (<?=count($items)?>)</h3><?php if(!$items):?><p class="text-muted">V dostupných údajích není další úkol této skupiny.</p><?php else:?><ul><?php foreach($items as $task):?><li><a href="<?=familyPageH($task['href'])?>"><?=familyPageH($task['title'])?></a><p><?=familyPageH($task['detail'])?></p></li><?php endforeach;?></ul><?php endif;?>
<?php endforeach;endif;?></section>
