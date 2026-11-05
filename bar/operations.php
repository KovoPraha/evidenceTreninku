<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/checkout_web.php';
require_once dirname(__DIR__) . '/includes/bar/recovery.php';
barRequireWebActor($pdo, 'cash_recovery');
$error = '';
$id = max(0, (int)($_GET['id'] ?? 0));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        barPostCsrf();
        $result = barRecoverCashbox(
            $pdo,
            barSourceInteger($_POST['cashbox_id'] ?? null, 1, 2147483647),
            barSourceInteger($_POST['version'] ?? null, 1, 2147483647),
            barPostText('action', 20),
            barSourceInteger($_POST['sale_id'] ?? 0, 0, 2147483647),
            barPostMinor('counted'),
            barPostText('reference', 255, false),
            ($_POST['confirmed'] ?? '') === '1',
            barPostText('reason', 1000),
            barPostText('token', 64)
        );
        header('Location: ' . appUiUrl('bar/operations.php?id=' . $result['cashbox_id']), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    }
}
$data = barRecoveryView($pdo, $id > 0 ? $id : null);
$box = $data['box'];
$clock = $pdo->query('SELECT clock_contract FROM kis_bar_state WHERE id=1')->fetchColumn();
barCheckoutPageStart($pdo, 'Vypořádání kas');
?>
<p>Historie zůstává původnímu vlastníkovi. Nouzové vypořádání je dostupné pouze u účtu, který je vypnutý nebo již nemá přístup do baru.
    Aktivní vlastník uzavírá svou kasu sám. Každá čekající platba vyžaduje zvlášť kontrolu podkladu nebo vrácení nevydaného zboží.</p>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= barH($error) ?></div><?php endif; ?>
<?php if ($clock !== 'utc-v1'): ?><div class="alert alert-warning">Historie baru používá původní časový režim.
    Zápisy a nový účetní export jsou zablokované do řízeného nového začátku.</div><?php endif; ?>
<?php if ($box !== null): ?>
<section class="card card-body mb-4"><h2 class="h4">Kasa #<?= (int)$box['id'] ?> · <?= barH($box['owner_name']) ?></h2>
<p>Očekávaná hotovost: <?= barH(barFormatMoney($data['totals']['expected_minor'])) ?> · Čekající platby: <?= (int)$data['totals']['pending_count'] ?></p>
<?php if ($box['closed_at'] !== null): ?>
    <p><?= $box['closure_kind'] === 'recovery' ? 'Nouzově uzavřena' : 'Uzavřena vlastníkem' ?>:
        <?= barH($clock === 'utc-v1' ? barDisplayTime($box['closed_at']) : $box['closed_at']) ?></p>
    <p>Napočítáno <?= barH(barFormatMoney((int)$box['counted_minor'])) ?> · Rozdíl <?= barH(barFormatMoney((int)$box['difference_minor'])) ?>.</p>
    <p><?= barH($box['closing_reason']) ?></p>
<?php elseif ($box['owner_available']): ?>
    <p class="alert alert-info">Vlastník má přístup do baru. Vypořádá svou kasu pod vlastním účtem.</p>
<?php elseif ($clock === 'utc-v1' && $error === ''): ?>
    <?php foreach ($data['pending'] as $pending): ?>
        <section class="border rounded p-3 mb-3"><h3 class="h5">Čekající doklad #<?= (int)$pending['id'] ?>
            · <?= barH(barFormatMoney((int)$pending['total_minor'])) ?> · <?= $pending['method'] === 'qr' ? 'QR převod' : 'Karta' ?></h3>
        <form method="post" class="mb-3"><?php barRecoveryFormFields($box, 'confirm', (int)$pending['id']); ?>
            <label>Podklad skutečně přijaté platby<input class="form-control" name="reference" minlength="3" maxlength="255" required></label>
            <label>Důvod a způsob ověření<input class="form-control" name="reason" minlength="5" maxlength="1000" required></label>
            <label class="d-block my-2"><input type="checkbox" name="confirmed" value="1" required> Ověřil(a) jsem skutečné přijetí celé částky</label>
            <button class="btn btn-success">Potvrdit doloženou platbu</button>
        </form>
        <details><summary>Stornovat nevydanou nebo vrácenou objednávku</summary>
        <form method="post" class="mt-3"><?php barRecoveryFormFields($box, 'cancel', (int)$pending['id']); ?>
            <label>Důvod a podklad storna<input class="form-control" name="reason" minlength="5" maxlength="1000" required></label>
            <label class="d-block my-2"><input type="checkbox" name="confirmed" value="1" required>
                Platba nebyla přijata a veškeré zboží bylo vráceno nebo nebylo vydáno</label>
            <button class="btn btn-outline-danger">Stornovat a vrátit zásobu</button>
        </form></details></section>
    <?php endforeach; ?>
    <?php if ($data['pending'] === []): ?>
        <form method="post"><?php barRecoveryFormFields($box, 'close', 0, true); ?>
            <label>Skutečně spočtená hotovost v Kč<input class="form-control" name="counted" inputmode="decimal" required></label>
            <label>Důvod a podklad převzetí hotovosti<input class="form-control" name="reason" minlength="5" maxlength="1000" required></label>
            <label class="d-block my-2"><input type="checkbox" name="confirmed" value="1" required>
                Převzal(a) jsem a fyzicky přepočítal(a) hotovost a ověřil(a) všechny doklady této kasy</label>
            <button class="btn btn-danger">Nouzově uzavřít původní kasu</button>
        </form>
    <?php else: ?><p>Uzávěrka bude dostupná po vyřízení všech čekajících plateb.</p><?php endif; ?>
<?php endif; ?></section>
<?php endif; ?>
<h2 class="h4">Posledních 100 kas</h2><table class="table"><thead><tr><th>Kasa</th><th>Vlastník</th><th>Stav</th></tr></thead><tbody>
<?php foreach ($data['history'] as $row): ?><tr><td><a href="?id=<?= (int)$row['id'] ?>">#<?= (int)$row['id'] ?></a></td>
<td><?= barH($row['owner_name']) ?></td><td><?= $row['closed_at'] === null ? 'Otevřená' : ($row['closure_kind'] === 'recovery' ? 'Nouzově uzavřená' : 'Uzavřená') ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php barCheckoutPageEnd();

function barRecoveryFormFields(array $box, string $action, int $saleId, bool $close = false): void
{
    echo csrf_field();
    foreach (['cashbox_id' => $box['id'], 'version' => $box['lock_version'], 'action' => $action,
        'sale_id' => $saleId, 'token' => bin2hex(random_bytes(32))] as $name => $value) {
        echo '<input type="hidden" name="' . $name . '" value="' . barH($value) . '">';
    }
    if (!$close) {
        echo '<input type="hidden" name="counted" value="0">';
    }
}
