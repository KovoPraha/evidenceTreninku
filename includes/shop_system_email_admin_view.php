<?php
declare(strict_types=1);
if (!isset($pdo)||!function_exists('statusAdminH')) {http_response_code(404);exit;}
?>
<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Stavy a e-maily">
    <a class="btn btn-outline-primary btn-sm" href="#system-statuses">Systémové stavy</a>
    <a class="btn btn-outline-primary btn-sm" href="#system-emails">Systémové e-maily</a>
    <a class="btn btn-outline-primary btn-sm" href="#customer-statuses">Vlastní zákaznické stavy</a>
</nav>
<section class="card border-0 shadow-sm mb-4" id="system-statuses">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Systémové stavy objednávky</h2></div>
    <div class="card-body"><p>Tyto stavy jsou součástí zpracování objednávky a nelze je smazat. Vznikají konkrétní akcí nebo ověřením úhrady; nepřepínají se pomocí vlastního zákaznického stavu.</p>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Stav</th><th>Co jej mění a co způsobí</th><th>E-mail</th></tr></thead><tbody>
    <?php foreach(shopOrderSystemStatusDefinitions() as $state):?><tr><th scope="row"><?=statusAdminH($state['label'])?></th><td><?=statusAdminH($state['effect'])?></td><td><?=statusAdminH($state['email'])?></td></tr><?php endforeach;?>
    </tbody></table></div>
    <p class="small mb-0">Platba se zobrazuje samostatně: čeká, zaplaceno, zrušeno, čeká na vrácení a vráceno. Vlastní stav „Vyřízeno“ není dokončení výdeje ani potvrzení úhrady.</p></div>
</section>
<section id="system-emails" class="mb-4">
    <h2 class="h5">Systémové e-maily</h2>
    <p class="text-muted">Zde změníte text existujících automatických zpráv. Náhled používá pouze ukázková data a nic neodesílá. Změny platí pro nově vytvářené zprávy.</p>
    <div class="alert alert-info small">Místo odběru, čas a kontakt doplňte přímo do textu zprávy „Připraveno k osobnímu odběru“. Interní poznámka k přípravě ani podací kód Z-BOXu se do zákaznických zpráv nepřenášejí.</div>
    <?php if (!shopSystemEmailTableExists($pdo,'shop_system_email_templates')):?>
        <div class="alert alert-warning">Editor vyžaduje aktualizaci databáze. Obraťte se na správce aplikace.</div>
    <?php else:?>
    <?php foreach(shopSystemEmailCatalog() as $key=>$definition):
        $template=shopSystemEmailCurrent($pdo,$key);
        $editing=$systemDraft!==null&&$systemDraft['template_key']===$key;
        $display=$editing?$systemDraft:$template;
        $q=$pdo->prepare('SELECT version,subject_plain,body_plain,created_by_trainer_id,reason,created_at FROM shop_system_email_versions WHERE template_key=? ORDER BY version DESC LIMIT 10');$q->execute([$key]);$history=$q->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <details class="card border-0 shadow-sm mb-3" id="email-<?=statusAdminH($key)?>" <?=(($editing||($_GET['email']??'')===$key)?'open':'')?>>
        <summary class="card-header bg-white p-3"><strong><?=statusAdminH($definition['label'])?></strong> <span class="small text-muted">· <?=((int)$template['version']===0?'Výchozí text':'Verze '.(int)$template['version'])?></span></summary>
        <div class="card-body">
            <p class="small"><?=statusAdminH($definition['trigger'])?></p>
            <form method="post" action="eshop_order_statuses_admin.php#email-<?=statusAdminH($key)?>" class="row g-3">
                <?=csrf_field()?><input type="hidden" name="template_key" value="<?=statusAdminH($key)?>"><input type="hidden" name="expected_version" value="<?=(int)$display['version']?>">
                <div class="col-12"><label for="subject-<?=statusAdminH($key)?>" class="form-label">Předmět</label><input class="form-control" id="subject-<?=statusAdminH($key)?>" name="subject_plain" maxlength="180" required value="<?=statusAdminH($display['subject_plain'])?>"></div>
                <div class="col-12"><label for="body-<?=statusAdminH($key)?>" class="form-label">Text e-mailu</label><textarea class="form-control" id="body-<?=statusAdminH($key)?>" name="body_plain" rows="10" maxlength="8000" required><?=statusAdminH($display['body_plain'])?></textarea></div>
                <div class="col-12 small"><strong>Proměnné:</strong> <?php foreach($definition['variables'] as $name=>$description):?><span class="d-inline-block me-3"><code>{<?=statusAdminH($name)?>}</code> <?=statusAdminH($description)?></span><?php endforeach;?><div class="mt-2">V textu musí zůstat: <?=statusAdminH(implode(', ',array_map(static fn($name)=>'{'.$name.'}',$definition['required'])))?>.</div></div>
                <div class="col-12"><button class="btn btn-outline-primary" type="submit" name="action" value="system_preview" formnovalidate>Náhled bez odeslání</button></div>
                <div class="col-md-8"><label for="reason-<?=statusAdminH($key)?>" class="form-label">Důvod změny</label><input class="form-control" name="reason" id="reason-<?=statusAdminH($key)?>" maxlength="1000" required value="<?=statusAdminH($editing?($_POST['reason']??''):'')?>"></div>
                <div class="col-md-4 d-flex align-items-end flex-wrap gap-2"><label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Potvrzuji změnu textu</label><button class="btn btn-success" type="submit" name="action" value="system_save">Uložit novou verzi</button></div>
            </form>
            <?php if($systemPreview!==null&&$systemPreview['template_key']===$key):?><section class="border rounded p-3 mt-3" aria-label="Náhled systémového e-mailu"><h3 class="h6">Náhled · pouze ukázka, nic nebylo odesláno</h3><strong><?=statusAdminH($systemPreview['subject'])?></strong><pre class="text-wrap mt-2 mb-0"><?=statusAdminH($systemPreview['body'])?></pre></section><?php endif;?>
            <details class="mt-3"><summary>Historie verzí<?=($history===[]?' · zatím beze změn':'')?></summary>
                <p class="small mt-2">Výchozí text má verzi 0. Posledních 10 uložených změn:</p>
                <?php foreach($history as $version):?><article class="border rounded p-2 mb-2 small"><strong>Verze <?=(int)$version['version']?></strong> · <?=statusAdminH($version['created_at'])?> · autor #<?=(int)$version['created_by_trainer_id']?><p class="my-1"><?=statusAdminH($version['reason'])?></p><details><summary>Zobrazit uložený text</summary><strong><?=statusAdminH($version['subject_plain'])?></strong><pre class="text-wrap mb-0"><?=statusAdminH($version['body_plain'])?></pre></details></article><?php endforeach;?>
                <details><summary>Výchozí text aplikace</summary><strong><?=statusAdminH($definition['subject'])?></strong><pre class="text-wrap"><?=statusAdminH($definition['body'])?></pre></details>
            </details>
        </div>
    </details>
    <?php endforeach;?>
    <?php endif;?>
</section>
<h2 id="customer-statuses" class="h5">Vlastní zákaznické stavy</h2>
<p class="text-muted">Doplňující informace k objednávce, například „Objednávka přijata“. Nemění systémový stav, platbu, sklad ani výdej.</p>
