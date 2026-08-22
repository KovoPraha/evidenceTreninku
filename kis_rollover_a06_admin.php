<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/localhost_acceptance_hub.php';
if (!localhostAcceptanceRequestIsAllowed($_SERVER, getenv('APP_HOST'))) {
    http_response_code(404); header('Cache-Control: no-store'); exit('Nenalezeno.');
}
require_once __DIR__ . '/includes/init.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) { header('Location: login.php'); exit; }
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/kis_a06_acceptance.php';

function a06h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        if (($_POST['action'] ?? '') !== 'execute_a06') throw new InvalidArgumentException('Neplatná operace.');
        $result = kisA06Execute($pdo, (int)$_SESSION['trener_id'], (string)($_POST['reason'] ?? ''), ($_POST['confirm_a06'] ?? '') === '1', (string)($_POST['batch_fingerprint'] ?? ''), (array)($_POST['fingerprints'] ?? []));
        $_SESSION['flash_success'] = 'A06 dokončeno: ' . $result['moved_count'] . ' přesunuto, ' . $result['skipped_count'] . ' beze změny. Opakování nevytváří duplicitní členství.';
        header('Location: kis_rollover_a06_admin.php', true, 303); exit;
    } catch (InvalidArgumentException|KisRosterException|KisA06Exception $exception) {
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('kis_rollover_a06_admin.php: ' . $exception->getMessage());
        $errors[] = 'A06 se nepodařilo dokončit. Již dokončené kroky jsou auditované a průvodce je při opakování bezpečně přeskočí.';
    }
}
$success = (string)($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
try { $scenario = kisA06Scenario($pdo); } catch (Throwable $exception) { $scenario = null; $errors[] = $exception->getMessage(); }
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>A06 – roční obnova soupisek</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?><main class="container py-4" style="max-width:1100px">
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h1 class="h3 mb-1">A06 – roční obnova soupisek</h1><p class="text-muted mb-0">Jeden kontrolovaný průchod věkovým přesunem, přenosem disciplíny a individuální výjimkou.</p></div><a class="btn btn-outline-secondary" href="pracovni_pozice.php">Zpět na rozcestník</a></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=a06h($error)?></div><?php endforeach; ?><?php if ($success !== ''): ?><div class="alert alert-success"><?=a06h($success)?></div><?php endif; ?>
<?php if ($scenario): ?><div class="alert alert-info"><strong>Cílová sezona:</strong> <?=a06h($scenario['target_season']['name'])?>. Náhled nic nemění; zápis vyžaduje důvod a potvrzení.</div>
<div class="vstack gap-3"><?php foreach ($scenario['rows'] as $row): ?><section class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between gap-2"><div><h2 class="h5 mb-1"><?=a06h($row['label'])?></h2><div class="text-muted"><?=a06h($row['team']['name'])?> · politika <code><?=a06h($row['team']['rollover_policy'])?></code></div></div><?php if ($row['run']): ?><span class="badge text-bg-success align-self-start">Dokončeno</span><?php else: ?><span class="badge text-bg-warning align-self-start">Čeká</span><?php endif; ?></div>
<?php if ($row['run']): ?><div class="mt-3 small">Auditní běh #<?=(int)$row['run']['id']?>: <?=(int)$row['run']['moved_count']?> přesunuto, <?=(int)$row['run']['skipped_count']?> beze změny.</div><?php else: ?><div class="table-responsive mt-3"><table class="table table-sm mb-0"><thead><tr><th>Sportovec</th><th>Návrh</th><th>Cíl</th><th>Výjimka</th></tr></thead><tbody><?php foreach ($row['preview']['proposals'] as $proposal): ?><tr><td><?=a06h($proposal['name'])?></td><td><code><?=a06h($proposal['action'])?></code></td><td><?=a06h($proposal['target_team_id'] ?? '—')?></td><td><?=a06h($proposal['exception_reason'] ?? '—')?></td></tr><?php endforeach; ?></tbody></table></div><div class="small text-muted mt-2">Fingerprint: <code><?=a06h(substr((string)$row['preview']['fingerprint'],0,16))?>…</code></div><?php endif; ?></div></section><?php endforeach; ?></div>
<?php if ($scenario['complete']): ?><div class="alert alert-success mt-3"><strong>A06 je dokončeno.</strong> Všechny tři kroky mají auditní běh. Další otevření nic samo nemění.</div><?php else: ?><form method="post" class="card border-primary shadow-sm mt-3"><div class="card-body"><?=csrf_field()?><input type="hidden" name="action" value="execute_a06"><input type="hidden" name="batch_fingerprint" value="<?=a06h($scenario['batch_fingerprint'])?>"><?php foreach ($scenario['pending_fingerprints'] as $code=>$fingerprint): ?><input type="hidden" name="fingerprints[<?=a06h($code)?>]" value="<?=a06h($fingerprint)?>"><?php endforeach; ?><label class="form-label fw-semibold">Důvod provedení</label><input class="form-control" name="reason" value="Roční obnova testovacích soupisek pro scénář A06." maxlength="900" required><div class="form-check my-3"><input class="form-check-input" type="checkbox" id="confirmA06" name="confirm_a06" value="1" required><label class="form-check-label" for="confirmA06">Potvrzuji skutečný zápis pouze nad soupiskami LOCALHOST.</label></div><button class="btn btn-primary">Provést zbývající kroky A06</button></div></form><?php endif; ?><?php endif; ?>
</main></body></html>
