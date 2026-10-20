<?php $personPayments = $editContext['payments']; ?>
<section class="mt-4" aria-labelledby="payments-title">
    <h2 class="h5 border-bottom pb-2" id="payments-title">Všechny přiřazené platby</h2>
    <p class="small text-muted">Všechna období a všechny stavy v evidenci, včetně uhrazených, neuhrazených, zrušených a vrácených položek. Přijaté částky a jejich přidělení mohou pokrývat stejné předpisy; přehled je nesčítá podruhé.</p>
    <h3 class="h6">Předpisy a jejich platby (<?= count($personPayments['charges']) ?>)</h3>
    <?php if (!$personPayments['charges']): ?><p class="text-muted small">Sportovec nemá přiřazené žádné předpisy.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Předpis</th><th>Období / splatnost</th><th>Částka</th><th>Stav předpisu / platby</th><th>Platba, VS a úhrada</th></tr></thead><tbody>
    <?php foreach ($personPayments['charges'] as $charge): ?><tr>
        <td><?= h($charge['title_snapshot']) ?><br><small><?= h($charge['public_code']) ?> · <?= h($charge['source_system']) ?></small></td>
        <td><?= h(($charge['period_from'] ?: '—') . ' – ' . ($charge['period_to'] ?: '—')) ?><br>Splatnost <?= h($charge['due_on'] ?: 'neuvedena') ?></td>
        <td class="text-nowrap"><?= h(sportovecEditorMoney($charge['amount_minor'], $charge['currency'])) ?></td>
        <td><?= h(sportovecEditorStatusLabel($charge['status'])) ?><br><?= h($charge['payment_status'] ? sportovecEditorStatusLabel($charge['payment_status']) : 'Bez platebního záznamu') ?></td>
        <td><?= $charge['payment_id'] ? '#' . (int)$charge['payment_id'] . ' · ' . h(sportovecEditorStatusLabel($charge['method'])) : '—' ?><br>VS <?= h($charge['variable_symbol'] ?: '—') ?><br><?= h($charge['paid_at'] ?: 'Bez data úhrady') ?></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

    <h3 class="h6 mt-3">Objednávky a jejich platby (<?= count($personPayments['orders']) ?>)</h3>
    <?php if (!$personPayments['orders']): ?><p class="text-muted small">Sportovec nemá přiřazené žádné objednávky.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Objednávka / datum</th><th>Částka objednávky</th><th>Přiřazení sportovci</th><th>Stav / platba</th><th>VS a úhrada</th></tr></thead><tbody>
    <?php foreach ($personPayments['orders'] as $order): ?><tr>
        <td><?= h($order['public_code']) ?><br><?= h($order['placed_at']) ?></td><td class="text-nowrap"><?= h(sportovecEditorMoney($order['total_minor'], $order['currency'])) ?></td>
        <td><?= (int)$order['owner_id'] === (int)$editId ? 'Finanční vlastník objednávky' : 'Příjemce položek' ?><?php if ($order['athlete_items_minor'] !== null): ?><br>Položky sportovce: <?= h(sportovecEditorMoney($order['athlete_items_minor'], $order['currency'])) ?><?php endif; ?></td>
        <td><?= h(sportovecEditorStatusLabel($order['status'])) ?> / <?= h(sportovecEditorStatusLabel($order['ledger_status'] ?? $order['payment_status'])) ?><br><?= $order['payment_id'] ? '#' . (int)$order['payment_id'] . ' · ' . h(sportovecEditorStatusLabel($order['method'])) : 'Bez platebního záznamu' ?></td>
        <td>VS <?= h($order['variable_symbol'] ?: '—') ?><br><?= h($order['paid_at'] ?: 'Bez data úhrady') ?></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

    <h3 class="h6 mt-3">Přijaté částky a jejich rozdělení (<?= count($personPayments['receipts']) ?>)</h3>
    <?php if (!$personPayments['receipts']): ?><p class="text-muted small">Sportovec nemá evidované samostatné přijaté částky.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Datum a reference</th><th>Přijatá částka</th><th>Rozdělení na předpisy a objednávky</th></tr></thead><tbody>
    <?php foreach ($personPayments['receipts'] as $receipt): ?><tr>
        <td><?= h($receipt['paid_on']) ?><br><?= h($receipt['reference']) ?> · <?= h(sportovecEditorStatusLabel($receipt['kind'])) ?></td><td class="text-nowrap"><?= h(sportovecEditorMoney($receipt['amount_minor'], $receipt['currency'])) ?></td>
        <td><?php $allocated = 0; foreach ($personPayments['allocations'] as $allocation): if ((int)$allocation['receipt_id'] !== (int)$receipt['id']) continue; $allocated += (int)$allocation['amount_minor']; ?>
            <?= $allocation['charge_id'] ? 'Předpis #' . (int)$allocation['charge_id'] : 'Objednávka #' . (int)$allocation['order_id'] ?>: <?= h(sportovecEditorMoney($allocation['amount_minor'], $receipt['currency'])) ?><br>
        <?php endforeach; ?>Zbývá nepřiděleno: <?= h(sportovecEditorMoney(max(0, (int)$receipt['amount_minor'] - $allocated), $receipt['currency'])) ?></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
