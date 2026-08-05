<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/family_weekly_delivery.php';

header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function familyWeeklyAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
$localAllowed = defined('JE_LOKALNE') && JE_LOKALNE === true;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            if (!$localAllowed || (string)($_POST['confirm'] ?? '') !== '1') {
                throw new InvalidArgumentException('Testovací akce vyžaduje localhost a výslovné potvrzení.');
            }
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'generate') {
                $result = familyWeeklyDeliveryGenerate($pdo, null, true);
                $_SESSION['flash_weekly_admin'] = 'Fronta byla idempotentně připravena. Nové: ' . (int)$result['queued'] . ', existující: ' . (int)$result['existing'] . '.';
            } elseif ($action === 'deliver') {
                $result = familyWeeklyDeliveryProcessOne($pdo, familyWeeklyDeliveryLocalOutboxSender('localhost'), 'trainer', (int)$_SESSION['trener_id']);
                $_SESSION['flash_weekly_admin'] = $result === null
                    ? 'Ve frontě není žádný souhrn připravený ke zpracování.'
                    : ($result ? 'Jeden souhrn byl uložen do localhost outboxu. Na internet nic neodešlo.' : 'Testovací zpracování selhalo.');
            } else {
                throw new InvalidArgumentException('Neplatná testovací akce.');
            }
            header('Location: family_weekly_summaries_admin.php', true, 303);
            exit;
        } catch (InvalidArgumentException | FamilyWeeklyDeliveryException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('family_weekly_summaries_admin.php: ' . $exception->getMessage());
            $errors[] = 'Operace selhala bez částečného odeslání.';
        }
    }
}

$success = (string)($_SESSION['flash_weekly_admin'] ?? '');
unset($_SESSION['flash_weekly_admin']);
$status = (string)($_GET['status'] ?? '');
$preview = null;
try {
    $summary = familyWeeklyDeliveryAdminSummary($pdo);
    $rows = familyWeeklyDeliveryAdminList($pdo, $status);
    if (isset($_GET['preview_id'])) $preview = familyWeeklyDeliveryAdminPreview($pdo, (int)$_GET['preview_id']);
} catch (InvalidArgumentException | FamilyWeeklyDeliveryException $exception) {
    $errors[] = $exception->getMessage();
    $status = '';
    $summary = familyWeeklyDeliveryAdminSummary($pdo);
    $rows = familyWeeklyDeliveryAdminList($pdo);
}

require_once __DIR__ . '/hlavicka.php';
?>
<main class="container-fluid py-4" style="max-width:1450px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><h1 class="h4 mb-1">Týdenní rodinné souhrny</h1><div class="small text-muted">Dobrovolný odběr, idempotentní fronta a bezpečný localhostový outbox.</div></div>
        <a href="provozni_prehled_admin.php" class="btn btn-outline-secondary btn-sm">Provozní přehled</a>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= familyWeeklyAdminH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= familyWeeklyAdminH($success) ?></div><?php endif; ?>
    <div class="alert alert-info small"><strong>Produkční e-mailový transport není implementovaný.</strong> Webová stránka umí pouze na localhostu vytvořit snapshot zprávy a uložit jej do chráněného souborového outboxu.</div>
    <?php if ($localAllowed): ?><section class="card border-warning mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><strong>Localhost test</strong><div class="small text-muted">Použije pouze účty, které si odběr samy zapnuly.</div></div><div class="d-flex flex-wrap gap-2"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="generate"><input type="hidden" name="confirm" value="1"><button class="btn btn-warning btn-sm">Připravit aktuální týden</button></form><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="deliver"><input type="hidden" name="confirm" value="1"><button class="btn btn-outline-warning btn-sm">Uložit 1 zprávu do outboxu</button></form></div></div></section><?php endif; ?>
    <div class="row g-3 mb-3"><?php foreach (['pending'=>'Čeká','processing'=>'Zpracovává se','failed'=>'Selhalo','sent'=>'Zpracováno','cancelled'=>'Zrušeno'] as $key=>$label): ?><div class="col-6 col-lg"><a class="text-decoration-none text-reset" href="?status=<?= $key ?>"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted"><?= familyWeeklyAdminH($label) ?></div><div class="h3 mb-0"><?= (int)$summary[$key] ?></div></div></div></a></div><?php endforeach; ?></div>
    <?php if ($preview !== null): ?><section class="card border-primary mb-3"><div class="card-header bg-primary-subtle"><strong>Náhled uloženého souhrnu</strong></div><div class="card-body"><dl class="row small"><dt class="col-sm-2">Příjemce</dt><dd class="col-sm-10"><?= familyWeeklyAdminH($preview['recipient_name'] . ' <' . $preview['recipient_email'] . '>') ?></dd><dt class="col-sm-2">Období</dt><dd class="col-sm-10"><?= familyWeeklyAdminH($preview['period_from'] . '–' . $preview['period_to']) ?></dd><dt class="col-sm-2">Předmět</dt><dd class="col-sm-10"><strong><?= familyWeeklyAdminH($preview['subject_plain']) ?></strong></dd></dl><pre class="bg-light border rounded p-3 mb-0" style="white-space:pre-wrap"><?= familyWeeklyAdminH($preview['body_plain']) ?></pre></div></section><?php endif; ?>
    <section class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between"><strong><?= familyWeeklyAdminH($status === '' ? 'Neodeslané souhrny' : 'Filtr: ' . $status) ?></strong><a href="family_weekly_summaries_admin.php" class="btn btn-sm btn-outline-secondary">Neodeslané</a></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Stav</th><th>Období</th><th>Příjemce</th><th>Položek</th><th>Pokusy</th><th>Chyba</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><span class="badge text-bg-<?= $row['status']==='failed'?'danger':($row['status']==='sent'?'success':($row['status']==='cancelled'?'secondary':'warning')) ?>"><?= familyWeeklyAdminH($row['status']) ?></span></td><td><?= familyWeeklyAdminH($row['period_from'] . '–' . $row['period_to']) ?></td><td><?= familyWeeklyAdminH($row['recipient_name']) ?><div class="small text-muted"><?= familyWeeklyAdminH($row['recipient_email']) ?></div></td><td><?= (int)$row['item_count'] ?></td><td><?= (int)$row['attempts'] ?></td><td class="small"><?= familyWeeklyAdminH($row['last_error'] ?? '') ?></td><td><a class="btn btn-sm btn-outline-primary" href="?preview_id=<?= (int)$row['id'] ?><?= $status !== '' ? '&amp;status=' . urlencode($status) : '' ?>">Náhled</a></td></tr><?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">V tomto filtru nejsou žádné souhrny.</td></tr><?php endif; ?></tbody></table></div></section>
</main>
</body></html>
