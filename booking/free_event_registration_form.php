<?php
declare(strict_types=1);

/** @var array<string,mixed> $freeFormEvent */
/** @var list<array{id:int,name:string,disabled:bool}> $freeFormPeople */
/** @var string $freeFormAction */
if (!isset($freeFormEvent, $freeFormPeople, $freeFormAction)) {
    http_response_code(404);
    exit;
}
$freeFormH = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$freeFormEventId = (int)$freeFormEvent['id'];
?>
<form method="post" action="<?=$freeFormH($freeFormAction)?>" class="row g-2 mt-2">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="register">
    <input type="hidden" name="event_id" value="<?=$freeFormEventId?>">
    <input type="hidden" name="consent_version" value="<?=$freeFormH($freeFormEvent['terms_version'])?>">
    <div class="col-md-8">
        <label class="form-label" for="free-event-person-<?=$freeFormEventId?>">Účastník</label>
        <select class="form-select" id="free-event-person-<?=$freeFormEventId?>" name="sportovec_id" required>
            <option value="">Vyberte účastníka</option>
            <?php foreach ($freeFormPeople as $freeFormPerson): ?>
                <option value="<?=$freeFormPerson['id']?>" <?=$freeFormPerson['disabled']?'disabled':''?>><?=$freeFormH($freeFormPerson['name'])?><?=$freeFormPerson['disabled']?' — již přihlášeno':''?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 d-grid align-self-end"><button class="btn btn-primary"><?=(int)$freeFormEvent['remaining_capacity']>0?'Přihlásit':'Na čekací listinu'?></button></div>
    <div class="col-12"><div class="border rounded bg-light p-2 small">
        <strong>Souhlas s účastí</strong><br><?=nl2br($freeFormH($freeFormEvent['consent_text_plain']))?>
        <hr class="my-2"><strong>Bezplatné storno do <?=$freeFormH(uiFormatDateTime((string)$freeFormEvent['cancellation_deadline_at']))?></strong><br><?=nl2br($freeFormH($freeFormEvent['cancellation_policy_plain']))?>
    </div></div>
    <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="consented" value="1" id="free-event-consent-<?=$freeFormEventId?>" required><label class="form-check-label" for="free-event-consent-<?=$freeFormEventId?>">Potvrzuji souhlas a storno podmínky pro vybraného účastníka.</label></div>
</form>
