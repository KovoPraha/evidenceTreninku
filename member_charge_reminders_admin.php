<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/member_charge_reminder.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function memberChargeReminderAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $result = memberChargeReminderAdminRetry(
                $pdo,
                (int)($_POST['reminder_id'] ?? 0),
                (int)$_SESSION['trener_id'],
                (string)($_POST['reason'] ?? ''),
                ($_POST['confirm_retry'] ?? '') === '1'
            );
            $_SESSION['flash_charge_reminder_success'] = $result['changed']
                ? 'Připomínka byla auditovaně vrácena do fronty.'
                : 'Připomínka už čeká na nejbližší spuštění workeru.';
            header('Location: member_charge_reminders_admin.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('member_charge_reminders_admin.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala bez částečného zápisu.';
        } catch (InvalidArgumentException | MemberChargeReminderException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_charge_reminder_success'] ?? '');
unset($_SESSION['flash_charge_reminder_success']);
$status = (string)($_GET['status'] ?? '');
try {
    $summary = memberChargeReminderAdminSummary($pdo);
    $rows = memberChargeReminderAdminList($pdo, $status);
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
    $status = '';
    $summary = memberChargeReminderAdminSummary($pdo);
    $rows = memberChargeReminderAdminList($pdo);
}
require_once __DIR__ . '/hlavicka.php';
?>
<main class="container-fluid py-4" style="max-width:1450px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><h1 class="h4 mb-1">Připomínky členských plateb</h1><div class="small text-muted">Provozní fronta dobrovolně zapnutých připomínek splatnosti.</div></div>
        <div class="d-flex gap-2"><a href="member_charges_admin.php" class="btn btn-outline-primary btn-sm">Členské předpisy</a><a href="kis_sync_center.php" class="btn btn-outline-secondary btn-sm">KIS centrum</a></div>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= memberChargeReminderAdminH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= memberChargeReminderAdminH($success) ?></div><?php endif; ?>
    <div class="row g-3 mb-3">
        <?php foreach (['pending' => 'Čeká', 'processing' => 'Zpracovává se', 'failed' => 'Vyžaduje zásah', 'sent' => 'Odesláno', 'cancelled' => 'Zrušeno'] as $key => $label): ?>
            <div class="col-6 col-lg"><a class="text-decoration-none text-reset" href="?status=<?= $key ?>"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted"><?= memberChargeReminderAdminH($label) ?></div><div class="h3 mb-0"><?= (int)$summary[$key] ?></div></div></div></a></div>
        <?php endforeach; ?>
    </div>
    <div class="alert alert-info small">Ruční opakování nikdy neodesílá e-mail z webového požadavku. Pouze jej auditovaně vrátí do fronty. Nelze jím obejít odhlášení uživatele ani uhrazený či zrušený předpis.</div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between"><strong><?= memberChargeReminderAdminH($status === '' ? 'Neodeslané připomínky' : 'Filtr: ' . $status) ?></strong><a href="member_charge_reminders_admin.php" class="btn btn-sm btn-outline-secondary">Neodeslané</a></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Stav</th><th>Sportovec a předpis</th><th>Příjemce</th><th>Splatnost</th><th>Pokusy</th><th>Poslední chyba</th><th>Ruční opakování</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><span class="badge text-bg-<?= $row['status'] === 'failed' ? 'danger' : ($row['status'] === 'sent' ? 'success' : ($row['status'] === 'processing' ? 'info' : ($row['status'] === 'cancelled' ? 'secondary' : 'warning'))) ?>"><?= memberChargeReminderAdminH($row['status']) ?></span></td>
            <td><strong><?= memberChargeReminderAdminH($row['child_first_name'] . ' ' . $row['child_last_name']) ?></strong><div><?= memberChargeReminderAdminH($row['title_snapshot']) ?></div><div class="small text-muted"><code><?= memberChargeReminderAdminH($row['public_code']) ?></code> · <?= memberChargeReminderAdminH(number_format(((int)$row['amount_minor']) / 100, 2, ',', ' ') . ' ' . $row['currency']) ?></div></td>
            <td><?= memberChargeReminderAdminH($row['recipient_email']) ?><div class="small text-muted"><?= memberChargeReminderAdminH($row['account_first_name'] . ' ' . $row['account_last_name']) ?></div></td>
            <td><?= memberChargeReminderAdminH($row['due_on'] ?: 'neuvedena') ?><div class="small text-muted">Dostupné <?= memberChargeReminderAdminH($row['available_at']) ?></div></td>
            <td><?= (int)$row['attempts'] ?></td><td class="small"><?= memberChargeReminderAdminH($row['last_error'] ?? '') ?></td>
            <td><?php if (in_array($row['status'], ['failed', 'pending'], true)): ?><form method="post" class="d-flex flex-column gap-1" style="min-width:250px"><?= csrf_field() ?><input type="hidden" name="reminder_id" value="<?= (int)$row['id'] ?>"><input class="form-control form-control-sm" name="reason" maxlength="1000" required placeholder="Důvod ručního opakování"><label class="small"><input type="checkbox" name="confirm_retry" value="1" required> Potvrzuji nové zařazení</label><button class="btn btn-sm btn-outline-primary">Vrátit do fronty</button></form><?php else: ?><span class="text-muted small">V tomto stavu nelze opakovat</span><?php endif; ?></td>
        </tr><?php endforeach; ?>
        <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">V tomto filtru nejsou žádné připomínky.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
</main>
</body></html>
