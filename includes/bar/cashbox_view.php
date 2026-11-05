<?php

declare(strict_types=1);

function barCashboxFormFields(string $action, int $id = 0): void
{
    echo csrf_field();
    echo '<input type="hidden" name="action" value="' . barH($action) . '">';
    echo '<input type="hidden" name="cashbox_id" value="' . $id . '">';
    echo '<input type="hidden" name="token" value="' . bin2hex(random_bytes(32)) . '">';
}

function barRenderCashbox(array $data): void
{
    $box = $data['box'];
    $totals = $data['totals'];
    ?>
    <?php if ($box === null): ?>
        <section class="card card-body mb-4"><h2 class="h4">Otevřít vlastní kasu</h2>
            <form method="post"><?php barCashboxFormFields('open'); ?>
                <label class="form-label" for="bar-opening">Počáteční hotovost v Kč</label>
                <input class="form-control mb-2" id="bar-opening" name="opening" inputmode="decimal" required>
                <label class="form-label" for="bar-opening-reason">Poznámka k zahájení</label>
                <input class="form-control mb-2" id="bar-opening-reason" name="reason" minlength="5" maxlength="1000" required>
                <button class="btn btn-primary">Otevřít kasu</button>
            </form>
        </section>
    <?php else: ?>
        <section class="card card-body mb-4"><h2 class="h4">Kasa #<?= (int)$box['id'] ?> · <?= $box['closed_at'] === null ? 'otevřená' : 'uzavřená' ?></h2>
            <p>Otevření: <?= barH($box['opened_at']) ?> · Počáteční hotovost: <?= barH(barFormatMoney((int)$box['opening_minor'])) ?></p>
            <p class="fs-4">Očekávaná hotovost: <strong><?= barH(barFormatMoney($totals['expected_minor'])) ?></strong></p>
            <p>Přijaté platby: hotovost <?= barH(barFormatMoney($totals['cash'])) ?> · karta <?= barH(barFormatMoney($totals['card'])) ?> · QR <?= barH(barFormatMoney($totals['qr'])) ?></p>
            <p>Vratky kelímků hotově: <?= barH(barFormatMoney($totals['cup_refunds_minor'])) ?>
                <?php foreach ($totals['refunds'] as $refund): ?> · Storna <?= barH(['cash' => 'hotově', 'card' => 'kartou', 'qr' => 'převodem'][$refund['refund_method']] ?? '') ?>:
                    <?= barH(barFormatMoney((int)$refund['amount'])) ?><?php endforeach; ?></p>
            <?php if ($box['closed_at'] !== null): ?>
                <p>Uzavření: <?= barH($box['closed_at']) ?> · Napočítáno: <?= barH(barFormatMoney((int)$box['counted_minor'])) ?>
                    · Rozdíl: <?= barH(barFormatMoney((int)$box['difference_minor'])) ?></p>
                <p><?= barH($box['closing_reason']) ?></p>
                <p><a href="<?= barH(appUiUrl('bar/cashbox.php')) ?>">Přejít na aktuální kasu nebo otevřít novou</a></p>
            <?php else: ?>
                <p>Čekající platby: <?= (int)$totals['pending_count'] ?> · <?= barH(barFormatMoney($totals['pending_minor'])) ?></p>
                <a class="btn btn-primary align-self-start" href="<?= barH(appUiUrl('bar/checkout.php')) ?>">Přejít k prodeji</a>
            <?php endif; ?>
        </section>
        <?php if ($box['closed_at'] === null): barRenderOpenCashboxForms($box); endif; ?>
        <h2 class="h4">Pohyby hotovosti</h2>
        <div class="table-responsive"><table class="table"><thead><tr><th>Čas</th><th>Pohyb</th><th>Částka</th><th>Podklad</th></tr></thead><tbody>
        <?php foreach ($data['movements'] as $row): ?>
            <tr><td><?= barH($row['created_at']) ?></td><td><?= barH(['opening' => 'Počáteční hotovost', 'cash_in' => 'Vklad', 'cash_out' => 'Výběr',
                'sale' => 'Prodej', 'sale_refund' => 'Storno', 'cup_refund' => 'Vrácení kelímků'][$row['kind']] ?? 'Pohyb') ?></td>
                <td><?= barH(barFormatMoney((int)$row['amount_minor'])) ?></td><td><?= barH($row['reason']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <h2 class="h4 mt-4">Moje poslední objednávky</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Doklad</th><th>Kasa</th><th>Čas</th><th>Celkem</th><th>Platba</th></tr></thead><tbody>
    <?php foreach ($data['sales'] as $sale): ?>
        <tr><td><a href="<?= barH(appUiUrl('bar/receipt.php?id=' . (int)$sale['id'])) ?>">#<?= (int)$sale['id'] ?></a><?= $sale['void_id'] !== null ? ' · storno' : '' ?></td>
            <td><?= (int)$sale['cashbox_id'] ?></td><td><?= barH($sale['created_at']) ?></td><td><?= barH(barFormatMoney((int)$sale['total_minor'])) ?></td>
            <td><?= barH(barPaymentLabel($sale)) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <h2 class="h4 mt-4">Moje kasy</h2><ul><?php foreach ($data['history'] as $history): ?>
        <li><a href="<?= barH(appUiUrl('bar/cashbox.php?id=' . (int)$history['id'])) ?>">Kasa #<?= (int)$history['id'] ?></a>
            · <?= barH($history['opened_at']) ?> · <?= $history['closed_at'] === null ? 'otevřená' : 'uzavřená' ?></li>
    <?php endforeach; ?></ul>
    <?php
}

function barRenderOpenCashboxForms(array $box): void
{
    ?>
    <details class="card card-body mb-4"><summary>Vklad nebo výběr hotovosti</summary>
        <form method="post" class="mt-3"><?php barCashboxFormFields('adjust', (int)$box['id']); ?>
            <label class="form-label" for="bar-direction">Pohyb</label>
            <select class="form-select mb-2" id="bar-direction" name="direction"><option value="in">Vklad do kasy</option><option value="out">Výběr z kasy</option></select>
            <label class="form-label" for="bar-cash-amount">Částka v Kč</label><input class="form-control mb-2" id="bar-cash-amount" name="amount" inputmode="decimal" required>
            <label class="form-label" for="bar-cash-reason">Důvod a podklad</label><input class="form-control mb-2" id="bar-cash-reason" name="reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-primary">Zapsat pohyb hotovosti</button>
        </form>
    </details>
    <details class="card card-body mb-4"><summary>Vrátit zálohu za kelímky</summary>
        <p class="mt-3">Zadejte číslo původního dokladu. Vrátí se jeho původní záloha pouze za dosud nevrácené kelímky, hotově z vaší kasy.</p>
        <form method="post"><?php barCashboxFormFields('cups', (int)$box['id']); ?>
            <label class="form-label" for="bar-cup-sale">Číslo dokladu</label><input class="form-control mb-2" type="number" min="1" id="bar-cup-sale" name="sale_id" required>
            <label class="form-label" for="bar-cup-quantity">Počet vrácených kelímků</label>
            <input class="form-control mb-2" type="number" min="1" max="200" id="bar-cup-quantity" name="quantity" required>
            <label class="form-label" for="bar-cup-reason">Podklad vratky</label><input class="form-control mb-2" id="bar-cup-reason" name="reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-primary">Vrátit zálohu hotově</button>
        </form>
    </details>
    <section class="card card-body mb-4"><h2 class="h4">Uzavřít kasu</h2>
        <p>Nejprve vyřešte čekající platby. Uzávěrka uloží souhrn a uzavřenou kasu už nelze změnit.</p>
        <form method="post"><?php barCashboxFormFields('close', (int)$box['id']); ?>
            <input type="hidden" name="version" value="<?= (int)$box['lock_version'] ?>">
            <label class="form-label" for="bar-counted">Skutečně napočítaná hotovost v Kč</label>
            <input class="form-control mb-2" id="bar-counted" name="counted" inputmode="decimal" required>
            <label class="form-label" for="bar-closing-reason">Poznámka a vysvětlení případného rozdílu</label>
            <input class="form-control mb-2" id="bar-closing-reason" name="reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-danger">Potvrdit uzávěrku</button>
        </form>
    </section>
    <?php
}
