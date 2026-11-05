<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/checkout_web.php';
require_once dirname(__DIR__) . '/includes/bar/cashbox_view.php';
barRequireWebActor($pdo, 'sell');
try {
    $id = barSourceInteger($_GET['id'] ?? null, 1, 2147483647);
    $receipt = barSaleReceipt($pdo, $id);
    $box = barOwnOpenCashbox($pdo);
} catch (DomainException | InvalidArgumentException $exception) {
    http_response_code(403);
    exit(barH($exception->getMessage()));
}
$sale = $receipt['sale'];
$payment = $receipt['payment'];
$consumption = $sale['kind'] === 'consumption';
$paid = $payment !== null && $payment['status'] === 'confirmed';
$refund = $paid ? (int)$sale['total_minor'] - $receipt['returned_cups']['amount_minor'] : 0;
$voidConfirmation = 'Potvrzuji vrácení zboží nebo zrušení nevydaného odběru';
if ($paid && $payment['method'] !== 'cash') {
    $voidConfirmation .= ' a provedení uvedené vratky';
}
barCheckoutPageStart($pdo, ($consumption ? 'Vlastní spotřeba #' : 'Prodejní doklad #') . $id);
?>
<p>Vytvořeno: <?= barH(barDisplayTime($sale['created_at'])) ?><?= $sale['cashbox_id'] !== null ? ' · Kasa #' . (int)$sale['cashbox_id'] : '' ?></p>
<div class="table-responsive"><table class="table"><thead><tr><th>Produkt</th><th>Počet</th><th>Jednotková cena</th><th>Po slevě</th></tr></thead><tbody>
<?php foreach ($receipt['lines'] as $line): ?>
    <tr><td><?= barH($line['product_name']) ?><div class="small"><?= nl2br(barH($line['description'])) ?></div></td><td><?= (int)$line['quantity'] ?></td>
        <td><?= barH(barFormatMoney((int)$line['unit_price_minor'])) ?></td><td><?= barH(barFormatMoney((int)$line['net_minor'])) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<p>Zboží před slevou: <?= barH(barFormatMoney((int)$sale['goods_gross_minor'])) ?> · Sleva: <?= barH(barFormatMoney((int)$sale['discount_minor'])) ?></p>
<p>Vratné kelímky: <?= (int)$sale['cup_quantity'] ?> ks · Záloha: <?= barH(barFormatMoney((int)$sale['deposit_minor'])) ?></p>
<p class="fs-4">Celkem: <strong><?= barH(barFormatMoney((int)$sale['total_minor'])) ?></strong></p>
<p><?= barH(barPaymentLabel($payment)) ?></p>
<?php if ($payment !== null && $payment['verification_kind'] === 'manual'): ?>
    <p>Podklad ručního potvrzení: <?= barH($payment['confirmation_reference']) ?> · <?= barH(barDisplayTime($payment['confirmed_at'])) ?></p>
<?php endif; ?>
<?php if ($consumption): ?><p>Důvod: <?= barH($sale['reason']) ?></p><?php endif; ?>
<?php if ($receipt['returned_cups']['quantity'] > 0): ?><p>Vrácené kelímky: <?= $receipt['returned_cups']['quantity'] ?> ks,
    vyplaceno <?= barH(barFormatMoney($receipt['returned_cups']['amount_minor'])) ?>.</p><?php endif; ?>
<?php if ($receipt['void'] !== null): ?>
    <div class="alert alert-warning">Stornováno <?= barH(barDisplayTime($receipt['void']['created_at'])) ?> · <?= barH($receipt['void']['reason']) ?>
        <br>Vrácená platba: <?= barH(barFormatMoney((int)$receipt['void']['refunded_minor'])) ?>
        <?php if ($receipt['void']['refund_reference'] !== null): ?><br>Podklad vratky: <?= barH($receipt['void']['refund_reference']) ?><?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($payment !== null && $payment['status'] === 'pending'): ?>
        <section class="card card-body mb-4"><h2 class="h4">Potvrdit přijatou platbu</h2>
            <p>Potvrďte až po kontrole skutečně přijaté částky. Potvrzení se uloží jako ruční; KIS platbu sám neověřuje.</p>
            <form method="post" action="<?= barH(appUiUrl('bar/cashbox.php')) ?>"><?php barCashboxFormFields('confirm'); ?>
                <input type="hidden" name="sale_id" value="<?= $id ?>">
                <label class="form-label" for="bar-payment-proof">Podklad potvrzení platby</label>
                <input class="form-control mb-2" id="bar-payment-proof" name="reference" minlength="3" maxlength="255" required>
                <button class="btn btn-success">Potvrdit přijetí <?= barH(barFormatMoney((int)$sale['total_minor'])) ?></button>
            </form>
        </section>
    <?php endif; ?>
    <details class="card card-body mb-4"><summary><?= $consumption ? 'Stornovat vlastní spotřebu' : 'Stornovat objednávku' ?></summary>
        <?php if ($paid && $box === null): ?><p class="mt-3">Pro vrácení platby nejprve otevřete vlastní kasu.</p>
        <?php else: ?>
            <p class="mt-3">Vrátí se přesné původní suroviny. Již vrácené zálohy se podruhé nevyplácejí.</p>
            <?php if ($paid): ?><p>Vrátit zákazníkovi: <strong><?= barH(barFormatMoney($refund)) ?></strong>
                <?= $payment['method'] === 'cash' ? 'hotově z vaší aktuální kasy' : 'původní bezhotovostní cestou mimo KIS' ?>.</p><?php endif; ?>
            <form method="post" action="<?= barH(appUiUrl('bar/cashbox.php')) ?>"><?php barCashboxFormFields('void', $paid ? (int)$box['id'] : 0); ?>
                <input type="hidden" name="sale_id" value="<?= $id ?>"><input type="hidden" name="expected_refund_minor" value="<?= $refund ?>">
                <label class="form-label" for="bar-void-reason">Důvod storna</label>
                <input class="form-control mb-2" id="bar-void-reason" name="reason" minlength="5" maxlength="1000" required>
                <?php if ($paid && $payment['method'] !== 'cash'): ?>
                    <label class="form-label" for="bar-refund-proof">Podklad skutečně provedené bezhotovostní vratky</label>
                    <input class="form-control mb-2" id="bar-refund-proof" name="reference" minlength="3" maxlength="255" required>
                <?php endif; ?>
                <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="goods_returned" value="1" required>
                    <span class="form-check-label"><?= barH($voidConfirmation) ?>.</span></label>
                <button class="btn btn-danger">Potvrdit storno</button>
            </form>
        <?php endif; ?>
    </details>
<?php endif; ?>
<a class="btn btn-primary" href="<?= barH(appUiUrl($consumption ? 'bar/consumption.php' : 'bar/checkout.php')) ?>"><?= $consumption ? 'Další odběr' : 'Nová objednávka' ?></a>
<?php barCheckoutPageEnd(); ?>
