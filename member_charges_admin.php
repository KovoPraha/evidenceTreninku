<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/member_charge_read.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function memberChargeAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function memberChargeAdminStatus(string $status): string
{
    return ['pending' => 'Čeká na úhradu', 'paid' => 'Uhrazeno', 'cancelled' => 'Zrušeno'][$status] ?? $status;
}

function memberChargeAdminBadge(string $status): string
{
    return ['pending' => 'text-bg-warning', 'paid' => 'text-bg-success', 'cancelled' => 'text-bg-secondary'][$status] ?? 'text-bg-light';
}

$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$status = (string)($_GET['status'] ?? '');
if (!in_array($status, ['', 'pending', 'paid', 'cancelled'], true)) {
    $status = '';
}
$rows = memberChargeAdminRows($pdo, $query, $status);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Členské předpisy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div><h1 class="h3 mb-1">Členské předpisy</h1><div class="text-muted">Bezpečný přehled předpisů a jejich poslední známé platby. Změny se zde neprovádějí.</div></div>
        <div class="d-flex gap-2"><a href="member_charge_reminders_admin.php" class="btn btn-outline-primary">Připomínky plateb</a><a href="pracovni_pozice.php" class="btn btn-outline-secondary">Finanční rozcestník</a></div>
    </div>
    <form method="get" class="card card-body border-0 shadow-sm mb-4"><div class="row g-3 align-items-end">
        <div class="col-md-6"><label for="q" class="form-label">Hledat sportovce, kód nebo název</label><input id="q" name="q" class="form-control" value="<?= memberChargeAdminH($query) ?>"></div>
        <div class="col-md-3"><label for="status" class="form-label">Stav</label><select id="status" name="status" class="form-select"><option value="">Všechny</option><?php foreach (['pending', 'paid', 'cancelled'] as $option): ?><option value="<?= $option ?>"<?= $status === $option ? ' selected' : '' ?>><?= memberChargeAdminH(memberChargeAdminStatus($option)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Filtrovat</button><a href="member_charges_admin.php" class="btn btn-outline-secondary">Zrušit filtr</a></div>
    </div></form>
    <div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between"><strong>Předpisy</strong><span class="text-muted small"><?= count($rows) ?> záznamů</span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Sportovec</th><th>Předpis</th><th>Částka a splatnost</th><th>Stav</th><th>Poslední platba</th></tr></thead><tbody>
        <?php if ($rows === []): ?><tr><td colspan="5" class="text-muted p-4">Filtru neodpovídá žádný předpis.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr>
            <td><strong><?= memberChargeAdminH($row['jmeno'] . ' ' . $row['prijmeni']) ?></strong><div class="small text-muted"><?= memberChargeAdminH($row['narozeni'] ?: 'datum narození neuvedeno') ?></div></td>
            <td><?= memberChargeAdminH($row['title_snapshot']) ?><div class="small text-muted"><code><?= memberChargeAdminH($row['public_code']) ?></code> · <?= memberChargeAdminH($row['source_system']) ?></div></td>
            <td><?= memberChargeAdminH(number_format(((int)$row['amount_minor']) / 100, 2, ',', ' ') . ' ' . $row['currency']) ?><div class="small text-muted">Splatnost <?= memberChargeAdminH($row['due_on'] ?: 'neuvedena') ?></div></td>
            <td><span class="badge <?= memberChargeAdminH(memberChargeAdminBadge((string)$row['status'])) ?>"><?= memberChargeAdminH(memberChargeAdminStatus((string)$row['status'])) ?></span></td>
            <td><?php if (!$row['payment_status']): ?><span class="text-muted">Bez platby</span><?php else: ?><span class="badge <?= memberChargeAdminH(memberChargeAdminBadge((string)$row['payment_status'])) ?>"><?= memberChargeAdminH(memberChargeAdminStatus((string)$row['payment_status'])) ?></span><div class="small text-muted"><?= memberChargeAdminH($row['payment_method'] ?: 'metoda neuvedena') ?><?php if ($row['variable_symbol']): ?> · VS <?= memberChargeAdminH($row['variable_symbol']) ?><?php endif; ?><?php if ($row['paid_at']): ?><br>Uhrazeno <?= memberChargeAdminH(substr((string)$row['paid_at'], 0, 10)) ?><?php endif; ?></div><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div></div>
</main>

</body></html>
