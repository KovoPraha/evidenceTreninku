<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/kis_hobby_transition.php';

if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!roleAtLeast('admin')) { http_response_code(403); exit('Přechod může provést pouze administrátor.'); }
function kth(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$errors = [];
$actorId = (int)$_SESSION['trener_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a vytvořte nový náhled.';
    } else {
        try {
            $result = kisHobbyTransitionExecute(
                $pdo,
                (int)($_POST['source_member_id'] ?? 0),
                (int)($_POST['target_team_id'] ?? 0),
                (string)($_POST['transition_on'] ?? ''),
                ($_POST['end_hobby'] ?? '') === '1',
                $actorId,
                (string)($_POST['reason'] ?? ''),
                ($_POST['confirm_transition'] ?? '') === '1',
                (string)($_POST['preview_fingerprint'] ?? '')
            );
            $_SESSION['flash_success'] = $result['idempotent']
                ? 'Tento přesný náhled už byl proveden; nevznikl další zápis.'
                : 'Přechod byl proveden nad stejnou identitou sportovce a auditován.';
            header('Location: kis_transition_admin.php'); exit;
        } catch (InvalidArgumentException | KisRosterException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('kis_transition_admin.php: ' . $e->getMessage());
            $errors[] = 'Přechod selhal bez částečného zápisu.';
        }
    }
}

$success = (string)($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
$sources = kisHobbyTransitionSources($pdo);
$targets = kisHobbyTransitionTargets($pdo);
$sourceMemberId = max(0, (int)($_GET['source_member_id'] ?? 0));
$targetTeamId = max(0, (int)($_GET['target_team_id'] ?? 0));
$transitionOn = (string)($_GET['transition_on'] ?? (new DateTimeImmutable('today'))->format('Y-m-d'));
$endHobby = ($_GET['end_hobby'] ?? '') === '1';
$preview = null;
if ($sourceMemberId > 0 && $targetTeamId > 0) {
    try { $preview = kisHobbyTransitionPreview($pdo, $sourceMemberId, $targetTeamId, $transitionOn, $endHobby); }
    catch (InvalidArgumentException | KisRosterException $e) { $errors[] = $e->getMessage(); }
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>A05 – přechod do závodního týmu</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1100px"><div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="h4 mb-1">A05 – kroužek → závodní tým</h1><p class="text-muted mb-0">Nejprve vznikne pouze náhled. Sportovec zůstává stejnou osobou; mění se jen jeho členství v soupiskách.</p></div><a class="btn btn-outline-secondary btn-sm" href="kis_rosters_admin.php">Soupisky</a></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=kth($error)?></div><?php endforeach; ?><?php if ($success !== ''): ?><div class="alert alert-success"><?=kth($success)?></div><?php endif; ?>
<section class="card mb-3"><div class="card-header fw-semibold">1. Připravit read-only náhled</div><div class="card-body"><form method="get" class="row g-3"><div class="col-lg-6"><label class="form-label">Aktivní kroužkové členství</label><select class="form-select" name="source_member_id" required><option value="">Vyberte sportovce a kroužek</option><?php foreach ($sources as $row): ?><option value="<?=(int)$row['member_id']?>" <?=$sourceMemberId===(int)$row['member_id']?'selected':''?>><?=kth($row['prijmeni'].' '.$row['jmeno'].' — '.$row['team_name'].' / '.$row['season_name'])?></option><?php endforeach; ?></select></div><div class="col-lg-6"><label class="form-label">Cílová věková soupiska</label><select class="form-select" name="target_team_id" required><option value="">Vyberte závodní tým</option><?php foreach ($targets as $row): ?><option value="<?=(int)$row['team_id']?>" <?=$targetTeamId===(int)$row['team_id']?'selected':''?>><?=kth($row['team_name'].' — '.$row['season_name'].' / '.$row['series_name'])?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Přechod od</label><input type="date" class="form-control" name="transition_on" value="<?=kth($transitionOn)?>" required></div><div class="col-md-8 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="end_hobby" value="1" id="end-hobby" <?=$endHobby?'checked':''?>><label class="form-check-label" for="end-hobby">Současně ukončit vybrané kroužkové členství k tomuto dni</label></div></div><div><button class="btn btn-primary">Zobrazit náhled – nic neukládat</button></div></form></div></section>
<?php if ($preview): $source=$preview['source']; $target=$preview['target']; ?>
<section class="card border-primary"><div class="card-header fw-semibold">2. Zkontrolovat a explicitně provést</div><div class="card-body"><div class="alert alert-info"><strong><?=kth($source['prijmeni'].' '.$source['jmeno'])?></strong>, sportovec ID <code><?=(int)$source['sportovec_id']?></code>, bude od <?=kth($preview['transition_on'])?> aktivní v <strong><?=kth($target['name'])?></strong>. Věk pro cílový rok: <strong><?=(int)$preview['category_age']?></strong>. Kroužek <strong><?=kth($source['source_team_name'])?></strong> bude <?=$preview['end_hobby']?'<strong>ukončen</strong>':'<strong>ponechán aktivní</strong>'?>.</div>
<?php if ($preview['target_member']): ?><p class="small text-muted">Cílové členství už v databázi existuje; provedení jej podle potřeby bezpečně aktivuje, ale nevytvoří duplicitu.</p><?php endif; ?>
<p class="small text-muted">Fingerprint náhledu: <code><?=kth(substr($preview['fingerprint'],0,16))?>…</code>. Změní-li se členství, potvrzení bude odmítnuto.</p>
<form method="post" class="border rounded p-3"><input type="hidden" name="csrf_token" value="<?=kth(csrf_token())?>"><input type="hidden" name="source_member_id" value="<?=$sourceMemberId?>"><input type="hidden" name="target_team_id" value="<?=$targetTeamId?>"><input type="hidden" name="transition_on" value="<?=kth($preview['transition_on'])?>"><input type="hidden" name="end_hobby" value="<?=$preview['end_hobby']?'1':'0'?>"><input type="hidden" name="preview_fingerprint" value="<?=kth($preview['fingerprint'])?>"><label class="form-label">Povinný důvod rozhodnutí</label><textarea class="form-control mb-3" name="reason" maxlength="1000" required></textarea><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_transition" value="1" id="confirm-transition" required><label class="form-check-label" for="confirm-transition">Potvrzuji tento konkrétní náhled a rozumím změnám obou soupisek.</label></div><button class="btn btn-danger">Provést auditovaný přechod</button></form></div></section>
<?php endif; ?></main></body></html>
