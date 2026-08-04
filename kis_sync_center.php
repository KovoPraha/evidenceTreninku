<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/kis_import_run_lib.php';
require_once __DIR__ . '/includes/kis_import_sandbox_promotion.php';

if (!isset($_SESSION['trener_id']) || !canAccess('sync_evidence')) {
    header('Location: login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function dt(?string $date): string {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('d.m.Y H:i', $ts) : h($date);
}

$runId = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;
$sandboxAllowed = defined('JE_LOKALNE') && JE_LOKALNE === true && roleAtLeast('admin');
$messages = [];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$sandboxAllowed) {
            throw new KisImportSandboxException('Sandbox akce je povolena pouze administrátorovi na localhostu.');
        }
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            throw new InvalidArgumentException('Neplatný CSRF token.');
        }
        $fingerprint = trim((string)($_POST['preview_fingerprint'] ?? ''));
        $reason = (string)($_POST['reason'] ?? '');
        $confirmed = ($_POST['confirm_action'] ?? '') === '1';
        $actorId = (int)$_SESSION['trener_id'];
        if (($_POST['action'] ?? '') === 'sandbox_promote') {
            $result = kisImportSandboxPromote($pdo, $runId, $fingerprint, $actorId, $reason, $confirmed, true);
            $messages[] = !empty($result['idempotent'])
                ? 'Sandbox promote už byl aplikován; žádný další zápis nevznikl.'
                : (!empty($result['reapplied']) ? 'Sandbox promote byl bezpečně znovu aplikován.' : 'Sandbox promote byl aplikován.');
        } elseif (($_POST['action'] ?? '') === 'sandbox_rollback') {
            $result = kisImportSandboxRollback($pdo, $runId, $fingerprint, $actorId, $reason, $confirmed, true);
            $messages[] = !empty($result['idempotent'])
                ? 'Sandbox rollback už byl proveden; žádný další zápis nevznikl.'
                : 'Sandbox rollback byl proveden.';
        } else {
            throw new InvalidArgumentException('Neznámá sandbox akce.');
        }
    } catch (Throwable $exception) {
        $errors[] = $exception instanceof InvalidArgumentException || $exception instanceof KisImportSandboxException
            ? $exception->getMessage()
            : 'Sandbox akci se nepodařilo provést bez částečného zápisu.';
        if (!$exception instanceof InvalidArgumentException && !$exception instanceof KisImportSandboxException) {
            error_log('kis_sync_center sandbox: ' . $exception->getMessage());
        }
    }
}
$detail = $runId > 0 ? kisImportRunDetail($pdo, $runId) : null;
$previewReport = $detail && $detail['run'] ? kisImportStoredPreviewReport($detail['run']) : null;
$sandboxPromotion = $sandboxAllowed && $runId > 0 ? kisImportSandboxPromotionForRun($pdo, $runId) : [];
if (($_GET['preview_report'] ?? '') === 'json' && $previewReport !== null) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="kis-preview-run-' . $runId . '.json"');
    header('Cache-Control: no-store');
    echo json_encode($previewReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
$runs = kisImportLatestRuns($pdo, 30);
$attention = [
    'unmapped' => (int)$pdo->query("SELECT COUNT(*) FROM soupiska_mapping WHERE skupina_id IS NULL AND podskupina_id IS NULL")->fetchColumn(),
    'debt' => (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE COALESCE(kis_neuhrazeno,0) > 0")->fetchColumn(),
    'without_group' => (int)$pdo->query("SELECT COUNT(*) FROM sportovci s WHERE NOT EXISTS (SELECT 1 FROM sportovec_skupina ss WHERE ss.sportovec_id = s.id)")->fetchColumn(),
    'manual_seen' => (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE COALESCE(stav_manualni,0)=1 AND COALESCE(kis_aktivni,0)=1")->fetchColumn(),
];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KIS synchronizační centrum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<div class="container py-4" style="max-width:1280px;">
    <?php foreach ($messages as $message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">KIS synchronizační centrum</h1>
            <div class="text-muted small">Importy, preview, konflikty a provozní kontrola členské evidence.</div>
        </div>
        <div class="d-flex gap-2"><a href="kis_rosters_admin.php" class="btn btn-outline-primary btn-sm">Týmy a soupisky</a><a href="sync_evidence.php" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Nový import</a></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="h4 mb-0"><?= $attention['unmapped'] ?></div><div class="small text-muted">Nepřiřazené soupisky</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="h4 mb-0"><?= $attention['debt'] ?></div><div class="small text-muted">Členové s dluhem</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="h4 mb-0"><?= $attention['without_group'] ?></div><div class="small text-muted">Bez skupiny</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="h4 mb-0"><?= $attention['manual_seen'] ?></div><div class="small text-muted">Ruční stav + aktivní KIS</div></div></div></div>
    </div>

    <?php if ($detail && $detail['run']): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-semibold">Detail importu #<?= (int)$detail['run']['id'] ?> · <?= h($detail['run']['status']) ?></span>
                <a href="kis_sync_center.php" class="btn btn-sm btn-outline-secondary">Zavřít detail</a>
            </div>
            <div class="card-body border-bottom">
                <?php if ($previewReport !== null): ?>
                    <?php $previewReady = $previewReport['status'] === 'ready_for_test_review'; ?>
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="fw-semibold">Integrita náhledu
                                <span class="badge <?= $previewReady ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $previewReady ? 'připraveno ke kontrole' : 'blokováno' ?>
                                </span>
                            </div>
                            <div class="small text-muted mt-1">
                                Klasifikováno <?= (int)$previewReport['summary']['classified_rows'] ?>/<?= (int)$previewReport['summary']['total_rows'] ?>,
                                blokátory <?= (int)$previewReport['summary']['blocker_rows'] ?>.
                                Tento náhled nic nezapisuje do profilů sportovců.
                            </div>
                            <code class="small text-break"><?= h($previewReport['fingerprint']) ?></code>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary" href="kis_sync_center.php?run_id=<?= $runId ?>&amp;preview_report=json">Stáhnout bezpečný JSON report</a>
                    </div>
                    <?php if ($sandboxAllowed): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <div class="fw-semibold">M2.3c testovací sandbox</div>
                            <div class="small mb-2">Akce zapisuje pouze anonymní položky do oddělených sandbox tabulek. Tabulky sportovců, soupisek, plateb a objednávek se nemění.</div>
                            <?php if ($previewReady): ?>
                                <?php $sandboxApplied = ($sandboxPromotion['status'] ?? '') === 'applied'; ?>
                                <?php $sandboxRolledBack = ($sandboxPromotion['status'] ?? '') === 'rolled_back'; ?>
                                <?php if ($sandboxPromotion): ?>
                                    <div class="small mb-2">
                                        Stav: <strong><?= $sandboxApplied ? 'aplikováno v sandboxu' : 'vráceno rollbackem' ?></strong>,
                                        aktivní položky <?= (int)($sandboxPromotion['active_items'] ?? 0) ?>/<?= (int)($sandboxPromotion['item_count'] ?? 0) ?>,
                                        auditní události <?= (int)($sandboxPromotion['event_count'] ?? 0) ?>.
                                    </div>
                                <?php endif; ?>
                                <form method="post" action="kis_sync_center.php?run_id=<?= $runId ?>" class="row g-2 align-items-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="<?= $sandboxApplied ? 'sandbox_rollback' : 'sandbox_promote' ?>">
                                    <input type="hidden" name="preview_fingerprint" value="<?= h($previewReport['fingerprint']) ?>">
                                    <div class="col-lg-7">
                                        <label class="form-label small" for="sandbox-reason">Auditní důvod</label>
                                        <input id="sandbox-reason" class="form-control form-control-sm" name="reason" minlength="5" maxlength="500" required placeholder="Např. Ověření M2.3c na localhostu">
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" name="confirm_action" value="1" required>
                                            <span class="form-check-label small">Potvrzuji pouze sandbox akci</span>
                                        </label>
                                    </div>
                                    <div class="col-lg-2 d-grid">
                                        <button class="btn btn-sm <?= $sandboxApplied ? 'btn-outline-danger' : 'btn-primary' ?>" type="submit">
                                            <?= $sandboxApplied ? 'Vrátit sandbox' : ($sandboxRolledBack ? 'Znovu aplikovat' : 'Aplikovat do sandboxu') ?>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="small text-danger">Blokovaný nebo neúplný náhled nelze aplikovat ani do sandboxu.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        Starší náhled nemá uzamčený M2.3b report. Nelze jej použít jako podklad pro testovací promote.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-dark"><tr><th>Import</th><th>Match</th><th>DB člen</th><th>KIS stav</th><th>Soupisky</th><th>Důvod</th></tr></thead>
                    <tbody>
                    <?php foreach ($detail['rows'] as $row): ?>
                        <tr>
                            <td><?= h($row['prijmeni'] . ' ' . $row['jmeno']) ?><div class="small text-muted"><?= h($row['email'] ?? '') ?></div></td>
                            <td><span class="badge <?= $row['match_status'] === 'matched' ? 'bg-success' : ($row['match_status'] === 'new' ? 'bg-primary' : 'bg-warning text-dark') ?>"><?= h($row['match_status']) ?> <?= (int)$row['confidence'] ?>%</span></td>
                            <td><?php if ($row['sportovec_id']): ?><a href="sportovec_karta.php?sportovec_id=<?= (int)$row['sportovec_id'] ?>" title="Administrační karta člena"><?= h($row['db_prijmeni'] . ' ' . $row['db_jmeno']) ?></a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                            <td><?= !empty($row['kis_aktivni']) ? '<span class="badge bg-success">aktivní</span>' : '<span class="badge bg-secondary">neaktivní</span>' ?> <?= (float)$row['kis_neuhrazeno'] > 0 ? '<span class="badge bg-danger">' . h($row['kis_neuhrazeno']) . ' Kč</span>' : '' ?></td>
                            <td class="small"><?= h($row['kis_soupisky'] ?? '') ?></td>
                            <td class="small"><?= h($row['reason'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Poslední importy</div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>ID</th><th>Vytvořeno</th><th>Stav</th><th>Řádků</th><th>Konflikty</th><th>Soubory</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td>#<?= (int)$run['id'] ?></td>
                        <td><?= dt($run['created_at']) ?></td>
                        <td><span class="badge <?= $run['status'] === 'applied' ? 'bg-success' : ($run['status'] === 'failed' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= h($run['status']) ?></span></td>
                        <td><?= (int)$run['row_count'] ?></td>
                        <td><?= (int)$run['ambiguous_count'] + (int)$run['conflict_count'] ?></td>
                        <td class="small"><?= h($run['source_users'] ?? '') ?><br><?= h($run['source_payments'] ?? '') ?><br><?= h($run['source_rosters'] ?? '') ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="kis_sync_center.php?run_id=<?= (int)$run['id'] ?>">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$runs): ?><tr><td colspan="7" class="text-muted p-3">Zatím není uložený žádný KIS import.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
