<?php
declare(strict_types=1);
require_once __DIR__.'/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__.'/db.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/coach_attendance.php';
require_once __DIR__.'/includes/ui_shell.php';
/** @var PDO $pdo */

$actor = (int)$_SESSION['trener_id'];
$planId = (int)($_POST['plan_id'] ?? $_GET['plan_id'] ?? 0);
$error = '';
if ($planId < 1) {
    http_response_code(400);
    exit('Vyberte termín tréninku.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Neplatný bezpečnostní token.');
    }
    try {
        if (!is_array($_POST['attendance'] ?? null)) {
            throw new TrainingClosureException('Neplatný formulář docházky.');
        }
        $result = coachAttendanceSave(
            $pdo,
            $planId,
            $actor,
            (int)($_POST['version'] ?? 0),
            $_POST['attendance'],
            is_array($_POST['excuse_reason'] ?? null) ? $_POST['excuse_reason'] : []
        );
        $_SESSION['coach_attendance_success'] = $result['status'] === 'draft'
            ? 'Rozpracovaná docházka byla uložena. Nevyplněné děti zůstávají bez stavu.'
            : 'Docházka byla uložena a uzavřena.';
        header('Location: coach_attendance.php?plan_id='.$planId, true, 303);
        exit;
    } catch (TrainingClosureException | RuntimeException $e) {
        http_response_code(422);
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('coach_attendance.php: '.$e->getMessage());
        http_response_code(500);
        $error = 'Docházku se nepodařilo uložit.';
    }
}
try {
    $view = coachAttendanceLoad($pdo, $planId, $actor);
} catch (RuntimeException $e) {
    http_response_code(403);
    exit($e->getMessage());
}
$success = (string)($_SESSION['coach_attendance_success'] ?? '');
unset($_SESSION['coach_attendance_success']);
$plan = $view['plan'];
$rosterLabels = trainingRosterBridgePlanLabels($pdo, [$plan]);
$current = $view['current'];
$people = $view['people'];
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
$lessonStart = trainingClosureDateTime((string)($current['actual_started_at'] ?? ($plan['datum'].' '.substr((string)$plan['cas_od'], 0, 5).':00')));
$lessonEnd = trainingClosureDateTime((string)($current['actual_ended_at'] ?? ($plan['datum'].' '.substr((string)$plan['cas_do'], 0, 5).':00')));
$editDeadline = $lessonEnd->setTimezone(new DateTimeZone('UTC'))->add(new DateInterval('PT72H'));
$canSave = $people !== [] && $plan['stav'] !== 'zruseny' && $now >= $lessonStart
    && (staffActivePositionIs('sports_lead') || $now <= $editDeadline)
    && (($current['status'] ?? '') !== 'void' || staffActivePositionIs('sports_lead'));
$history = [];
if ($view['training_id']) {
    $q = $pdo->prepare('SELECT r.version,r.status,r.closed_at,r.closed_by,r.reason,r.snapshot_json,t.jmeno AS actor_name '
        . 'FROM training_closure_revisions r LEFT JOIN treneri t ON t.id=r.closed_by '
        . 'WHERE r.training_id=? ORDER BY r.version DESC');
    $q->execute([$view['training_id']]);
    $history = $q->fetchAll(PDO::FETCH_ASSOC);
}
function caH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Docházka · <?=caH($plan['nazev'])?></title><?php appUiAssets(); ?>
<style nonce="<?=caH(app_csp_nonce())?>">
.attendance-row{border:1px solid var(--bs-border-color);border-radius:.75rem;padding:1rem;margin-bottom:.75rem}
.attendance-choices{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.6rem}
.attendance-choices label{border:1px solid var(--bs-border-color);border-radius:.5rem;padding:.55rem .7rem;min-height:44px;cursor:pointer}
.attendance-choices label:has(input:checked){border-color:var(--bs-primary);background:var(--bs-primary-bg-subtle);font-weight:700}
.attendance-choices input{margin-right:.3rem}
</style></head><body><?php require __DIR__.'/hlavicka.php'; ?>
<main class="container py-4" style="max-width:960px">
<p><a href="planovac.php?datum=<?=caH($plan['datum'])?>">← Rozvrh</a></p>
<h1 class="h2">Docházka · <?=caH($plan['nazev'])?></h1>
<p><?=caH($plan['datum'])?> · <?=caH(substr((string)$plan['cas_od'], 0, 5))?>–<?=caH(substr((string)$plan['cas_do'], 0, 5))?></p>
<p>Soupiska: <strong><?=caH($rosterLabels[$planId] ?? 'Bez přiřazené soupisky')?></strong></p>
<?php if ($error):?><div class="alert alert-danger" role="alert"><?=caH($error)?></div><?php endif;?>
<?php if ($success):?><div class="alert alert-success" role="status"><?=caH($success)?></div><?php endif;?>
<?php if ($plan['stav'] === 'zruseny'):?><div class="alert alert-warning">Termín je zrušený. Historie zůstává k nahlédnutí.</div><?php endif;?>
<?php if ($now < $lessonStart):?><div class="alert alert-info">Docházku lze začít zapisovat od začátku lekce.</div><?php endif;?>
<?php if (!$canSave && $now > $editDeadline && staffActivePositionIs('coach')):?><div class="alert alert-warning">Lhůta trenéra uplynula. Opravu provádí vedoucí sportu.</div><?php endif;?>
<p>Stav záznamu: <strong><?=!$current ? 'Nezapsáno' : ($current['status'] === 'draft'
    ? 'Rozpracováno · verze '.(int)$current['version'] : 'Uzavřeno · verze '.(int)$current['version'])?></strong>.
 Omluva od rodiče je předvyplněná. Nevyplněný stav není absence.</p>
<div class="alert alert-info">Trenér může docházku opravit do 72 hodin po konci lekce. Potom opravu provádí vedoucí sportu.</div>
<form method="post" id="attendance-form">
<input type="hidden" name="csrf_token" value="<?=caH(csrf_token())?>">
<input type="hidden" name="plan_id" value="<?=$planId?>">
<input type="hidden" name="version" value="<?=(int)($current['version'] ?? 0)?>">
<div class="card card-body mb-3" aria-live="polite" id="attendance-summary"></div>
<?php foreach ($people as $person): $id = (int)$person['id'];
    $selected = $error && isset($_POST['attendance'][$id]) ? (string)$_POST['attendance'][$id] : $person['state']; ?>
<div class="attendance-row">
<div class="d-flex justify-content-between align-items-center gap-2"><strong><?=caH($person['label'])?></strong>
<?php if ($person['makeup']):?><span class="badge text-bg-info">Náhradník · rezervace #<?=(int)$person['reservation_id']?></span><?php endif;?></div>
<?php if ($person['excuse'] !== null && !$person['manual_excuse']):?><div class="small text-muted mt-1">Evidovaná omluva: <?=caH($person['excuse'])?></div><?php endif;?>
<div class="attendance-choices" role="group" aria-label="Docházka: <?=caH($person['label'])?>">
<?php foreach (['' => 'Nevyplněno','present' => 'Přítomen','absent' => 'Nepřítomen','excused' => 'Omluven'] as $value => $label): if ($person['excuse'] !== null && $value === 'absent') {
    continue;
} ?>
<label><input type="radio" name="attendance[<?=$id?>]" value="<?=caH($value)?>" <?=$selected === $value ? 'checked' : ''?>><?=caH($label)?></label>
<?php endforeach;?></div>
<?php if ($person['excuse'] === null || $person['manual_excuse']):
    $reason = $error && isset($_POST['excuse_reason'][$id]) ? (string)$_POST['excuse_reason'][$id]
        : (string)($person['manual_excuse'] ? $person['excuse'] : ''); ?>
<label class="d-block small mt-2" for="excuse-reason-<?=$id?>">Důvod omluvy při volbě „Omluven“ (minimálně 10 znaků)</label>
<input class="form-control" id="excuse-reason-<?=$id?>" name="excuse_reason[<?=$id?>]" value="<?=caH($reason)?>" maxlength="1000" <?=$canSave ? '' : 'disabled'?>>
<?php endif;?></div>
<?php endforeach;?>
<?php if (!$people):?><p class="alert alert-warning">K tomuto termínu není přiřazena platná soupiska ani rezervovaný náhradník.</p><?php endif;?>
<button class="btn btn-primary btn-lg" type="submit" <?=$canSave ? '' : 'disabled'?>>Uložit docházku</button>
<span class="small text-muted ms-2">Úplná docházka se uzavře po skončení lekce. Dříve se uloží jako rozpracovaná.</span>
</form>
<?php if ($history):?><section class="mt-4"><h2 class="h4">Historie změn</h2><ol>
<?php foreach ($history as $item): $snapshot = json_decode((string)$item['snapshot_json'], true) ?: []; ?>
<li><details><summary>Verze <?=(int)$item['version']?> · <?=caH($item['closed_at'])?>
 · <?=caH(['closed' => 'Uzavřeno','draft' => 'Rozpracováno','void' => 'Zneplatněno'][$item['status']] ?? $item['status'])?>
 · <?=caH($item['actor_name'] ?: ('Pracovník #'.(int)$item['closed_by']))?> · <?=caH($item['reason'])?></summary>
<?php if (isset($snapshot['attendance_states'])): ?><ul><?php foreach ($snapshot['attendance_states'] as $personId => $state): ?>
<li><?=caH($people[(int)$personId]['label'] ?? ('Sportovec #'.(int)$personId))?>:
 <?=caH(['present' => 'Přítomen','absent' => 'Nepřítomen','excused' => 'Omluven'][$state] ?? $state)?>
<?php if ($state === 'excused' && isset($snapshot['attendance_excuse_reasons'][$personId])): ?>
 · <?=caH($snapshot['attendance_excuse_reasons'][$personId])?><?php endif;?></li>
<?php endforeach; ?></ul><?php else: ?><p>Původní evidence uvádí pouze přítomné sportovce.</p><?php endif; ?></details></li>
<?php endforeach;?></ol></section><?php endif;?>
</main>
<script nonce="<?=caH(app_csp_nonce())?>">
const attendanceForm=document.getElementById('attendance-form');
const attendanceSummary=document.getElementById('attendance-summary');
function updateAttendanceSummary(){
 const counts={present:0,absent:0,excused:0,pending:0,makeup:document.querySelectorAll('.attendance-row .badge').length};
 attendanceForm.querySelectorAll('.attendance-row').forEach(row=>{const state=row.querySelector('input:checked')?.value||''; counts[state||'pending']++;});
 attendanceSummary.textContent=`Celkem ${counts.present+counts.absent+counts.excused+counts.pending}`+
  ` · Přítomni ${counts.present} · Nepřítomni ${counts.absent} · Omluveni ${counts.excused}`+
  ` · Nevyplněno ${counts.pending} · Náhradníci ${counts.makeup}`;
}
attendanceForm.addEventListener('change',updateAttendanceSummary);updateAttendanceSummary();
</script></body></html>
