<?php if(staffActivePositionIs('registrar')):
require_once __DIR__.'/person_workflow.php';
$personSteps=personWorkflowSteps($pdo,$editPerson,$editContext);
?>
<section class="border rounded p-3 mb-4" aria-labelledby="person-workflow-title">
<h2 class="h5" id="person-workflow-title">Co u osoby zbývá vyřídit</h2>
<p class="small text-muted">Přehled propojuje existující agendy. Nevytváří další registraci ani automaticky nepotvrzuje členství, souhlasy nebo rodinný přístup.</p>
<div class="list-group list-group-flush"><?php foreach($personSteps as $step):?><div class="list-group-item px-0">
<div class="d-flex justify-content-between gap-3"><strong><?=h($step['title'])?></strong><a href="<?=h($step['href'])?>">Otevřít</a></div>
<p class="mb-0 <?=$step['attention']?'text-danger':'text-muted'?>"><?=h($step['detail'])?></p>
</div><?php endforeach;?></div></section>
<?php endif;?>
