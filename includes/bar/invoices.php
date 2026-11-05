<?php

declare(strict_types=1);

require_once __DIR__ . '/invoice_pdf.php';
require_once __DIR__ . '/sale.php';

function barInvoice(PDO $pdo, int $id, string $suffix = ''): array
{
    barActor($pdo, 'invoice_read');
    $query = $pdo->prepare('SELECT * FROM kis_bar_invoices WHERE id=?' . $suffix);
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new DomainException('Faktura nebyla nalezena.');
    }
    $row['draft'] = json_decode($row['draft_json'], true, 32, JSON_THROW_ON_ERROR);
    return $row;
}

function barNormalizeInvoice(array $input): array
{
    $draft = [];
    foreach (['supplier' => 255, 'supplier_id' => 50, 'number' => 100] as $key => $length) {
        $draft[$key] = trim(barSourceText($input[$key] ?? null, $length, true));
    }
    if (!preg_match('/^[A-Z0-9-]{3,50}$/D', $draft['supplier_id'])) {
        throw new InvalidArgumentException('Zadejte IČ dodavatele bez mezer.');
    }
    $draft['date'] = barDocumentDate(barSourceText($input['date'] ?? '', 10));
    $draft['currency'] = 'CZK';
    if (($input['currency'] ?? '') !== 'CZK') {
        throw new InvalidArgumentException('Příjem podporuje běžné faktury v CZK.');
    }
    foreach (['net_minor', 'vat_minor', 'gross_minor', 'payable_minor'] as $key) {
        $draft[$key] = barSourceInteger($input[$key] ?? null, 0, BAR_CHECKOUT_MAX_MINOR);
    }
    $draft['rounding_minor'] = barSourceInteger($input['rounding_minor'] ?? null, -100, 100);
    $rows = $input['rows'] ?? null;
    if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 200) {
        throw new InvalidArgumentException('Faktura musí mít 1 až 200 položek.');
    }
    $draft['rows'] = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !in_array($row['kind'] ?? '', ['stock', 'expense', 'deposit'], true)) {
            throw new InvalidArgumentException('Vyberte druh položky faktury.');
        }
        $normalized = ['code' => trim(barSourceText($row['code'] ?? '', 100)), 'description' => barSourceText(
            $row['description'] ?? '',
            500,
            true
        ),
            'quantity' => barSourceInteger($row['quantity'] ?? null, 1, 1000000), 'unit' => barSourceText(
                $row['unit'] ?? '',
                20,
                true
            ),
            'net_minor' => barSourceInteger($row['net_minor'] ?? null, 0, BAR_CHECKOUT_MAX_MINOR),
            'gross_minor' => barSourceInteger($row['gross_minor'] ?? null, 0, BAR_CHECKOUT_MAX_MINOR), 'kind' => $row['kind']];
        if ($normalized['net_minor'] > $normalized['gross_minor']) {
            throw new InvalidArgumentException('Základ položky převyšuje částku s DPH.');
        }
        if ($row['kind'] === 'stock') {
            $normalized += ['product_id' => barSourceInteger($row['product_id'] ?? null, 1, 2147483647),
                'multiplier' => barSourceInteger($row['multiplier'] ?? null, 1, 1000000),
                'confirmed' => barSourceInteger($row['confirmed'] ?? 0, 0, 1), 'remember' => barSourceInteger(
                    $row['remember'] ?? 0,
                    0,
                    1
                ),
                'cost_choice' => $row['cost_choice'] ?? '', 'purchase_version' => barSourceInteger(
                    $row['purchase_version'] ?? null,
                    1,
                    2147483647
                )];
            if (!in_array($normalized['cost_choice'], ['apply', 'keep'], true) || $normalized['quantity'] * $normalized['multiplier'] > 1000000) {
                throw new InvalidArgumentException('Ověřte přepočet balení a zvolte použití nebo ponechání nákupní ceny.');
            }
        }
        $draft['rows'][] = $normalized;
    }
    $draft['verified'] = barSourceInteger($input['verified'] ?? 0, 0, 1);
    return $draft;
}

function barInvoiceTotalsMatch(array $draft): bool
{
    return array_sum(array_column($draft['rows'], 'net_minor')) === $draft['net_minor']
        && array_sum(array_column($draft['rows'], 'gross_minor')) === $draft['gross_minor']
        && $draft['net_minor'] + $draft['vat_minor'] === $draft['gross_minor']
        && $draft['gross_minor'] + $draft['rounding_minor'] === $draft['payable_minor'];
}

function barInvoiceVersion(array $invoice, int $version, string $status): void
{
    if ((int)$invoice['lock_version'] !== $version || $invoice['status'] !== $status) {
        throw new BarConflictException('Fakturu mezitím někdo změnil. Načtěte ji znovu a změnu ověřte.');
    }
}

function barSaveInvoice(PDO $pdo, int $id, int $version, array $input, string $reason, string $token): array
{
    $draft = barNormalizeInvoice($input);
    $reason = barReason($reason);
    return barRunRequest($pdo, 'invoice_save', $token, [$id, $version, $draft, $reason], function (array $actor, string $suffix) use (
        $pdo,
        $id,
        $version,
        $draft,
        $reason
    ): array {
        $invoice = barInvoice($pdo, $id, $suffix);
        barInvoiceVersion($invoice, $version, 'draft');
        $identity = hash('sha256', $draft['supplier_id'] . '|' . mb_strtoupper(trim($draft['number']), 'UTF-8'));
        $query = $pdo->prepare('SELECT id FROM kis_bar_invoices WHERE identity_key=? AND id<>?');
        $query->execute([$identity, $id]);
        if ($query->fetchColumn()) {
            throw new DomainException('Tato faktura dodavatele již existuje, použijte původní doklad.');
        }
        $pdo->prepare('UPDATE kis_bar_invoices SET identity_key=?,draft_json=?,lock_version=lock_version+1 WHERE id=?')
            ->execute([$identity, json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $id]);
        barEvent($pdo, $actor['id'], 'invoice', $id, 'draft_saved', ['draft' => $draft, 'reason' => $reason]);
        return ['invoice_id' => $id, 'version' => $version + 1];
    }, 'invoice_manage');
}

/** Product and recipe metadata are read in two batches, irrespective of invoice line count. */

function barInvoiceProducts(PDO $pdo, array $rows, string $suffix = ''): array
{
    $ids = array_values(array_unique(array_filter(array_column($rows, 'product_id'))));
    if ($ids === []) {
        return [];
    }
    sort($ids, SORT_NUMERIC);
    $query = $pdo->prepare('SELECT p.*,EXISTS(SELECT 1 FROM kis_bar_recipes r WHERE r.product_id=p.id) AS composite FROM kis_bar_products p WHERE p.id IN (' . implode(
        ',',
        array_fill(0, count($ids), '?')
    ) . ') ORDER BY p.id' . $suffix);
    $query->execute($ids);
    return array_column($query->fetchAll(PDO::FETCH_ASSOC), null, 'id');
}

function barInvoiceMappingKey(string $supplier, string $code, string $unit): string
{
    return hash('sha256', json_encode([$supplier, $code, $unit], JSON_THROW_ON_ERROR));
}

function barReceiveInvoice(PDO $pdo, int $id, int $version, string $receivedOn, bool $goodsConfirmed, string $reason, string $token): array
{
    $reason = barReason($reason);
    $receivedOn = barDocumentDate($receivedOn);
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    if (!$goodsConfirmed || $receivedOn > $today) {
        throw new InvalidArgumentException('Potvrďte skutečné převzetí zboží a datum, které není v budoucnosti.');
    }
    return barRunRequest($pdo, 'invoice_receive', $token, [$id, $version, $receivedOn, $goodsConfirmed, $reason], function (
        array $actor,
        string $suffix
    ) use ($pdo, $id, $version, $receivedOn, $reason): array {
        $invoice = barInvoice($pdo, $id, $suffix);
        barInvoiceVersion($invoice, $version, 'draft');
        $draft = barNormalizeInvoice($invoice['draft']);
        if (!$invoice['identity_key'] || $draft['verified'] !== 1 || !barInvoiceTotalsMatch($draft) || $receivedOn < $draft['date']) {
            throw new DomainException('Ověřte běžnou fakturu v CZK s originálem, součet položek a datum příjmu.');
        }
        barInvoicePdfPath($invoice);
        $products = barInvoiceProducts($pdo, $draft['rows'], $suffix);
        $groups = [];
        foreach ($draft['rows'] as $row) {
            if ($row['kind'] !== 'stock') {
                continue;
            }
            $product = $products[$row['product_id']] ?? null;
            if (!$product || (int)$product['archived'] !== 0 || $product['stock_mode'] !== 'sledovat' || (int)$product['composite'] !== 0
                || $row['confirmed'] !== 1 || (int)$product['purchase_version'] !== $row['purchase_version']) {
                throw new BarConflictException('Ověřte párování, aktivní skladový produkt a aktuální nákupní cenu.');
            }
            $pid = (int)$product['id'];
            $groups[$pid] ??= ['quantity' => 0, 'gross_minor' => 0, 'choice' => $row['cost_choice']];
            if ($groups[$pid]['choice'] !== $row['cost_choice']) {
                throw new InvalidArgumentException('Řádky stejného produktu musí mít shodné rozhodnutí o nákupní ceně.');
            }
            $groups[$pid]['quantity'] += $row['quantity'] * $row['multiplier'];
            $groups[$pid]['gross_minor'] += $row['gross_minor'];
            if ($groups[$pid]['quantity'] > 1000000) {
                throw new InvalidArgumentException('Příliš vysoké množství produktu.');
            }
        }
        $snapshot = ['invoice_id' => $id, 'pdf_sha256' => $invoice['pdf_sha256'], 'document' => $draft, 'received_on' => $receivedOn,
     'reason' => $reason, 'costs' => []];
        $pdo->prepare('INSERT INTO kis_bar_invoice_receipts(invoice_id,actor_id,received_on,snapshot_json,created_at) VALUES(?,?,?,?,' . barSqlUtcNow($pdo) . ')')
            ->execute([$id, $actor['id'], $receivedOn, '{}']);
        $receiptId = (int)$pdo->lastInsertId();
        foreach ($groups as $pid => $group) {
            $cost = intdiv($group['gross_minor'] + intdiv($group['quantity'], 2), $group['quantity']);
            $product = $products[$pid];
            $operation = barRecordPhysicalMovement(
                $pdo,
                $actor,
                $product,
                $group['quantity'],
                'office',
                'invoice_receive',
                $receiptId,
                $cost,
                $product['name']
            );
            $pdo->prepare('INSERT INTO kis_bar_invoice_receipt_items(receipt_id,product_id,quantity,unit_cost_minor,stock_operation_id) VALUES(?,?,?,?,?)')
                ->execute([$receiptId, $pid, $group['quantity'], $cost, $operation]);
            $oldCost = $product['purchase_minor'] === null ? null : (int)$product['purchase_minor'];
            if ($group['choice'] === 'apply' && $oldCost !== $cost) {
                $pdo->prepare('UPDATE kis_bar_products SET purchase_minor=?,purchase_version=purchase_version+1 WHERE id=?')->execute([$cost,
     $pid]);
            }
            $snapshot['costs'][] = ['product_id' => $pid, 'quantity' => $group['quantity'], 'invoice_unit_estimate_minor' => $cost,
                'catalog_before_minor' => $oldCost, 'catalog_after_minor' => $group['choice'] === 'apply' ? $cost : $oldCost,
                'purchase_version_before' => (int)$product['purchase_version'], 'choice' => $group['choice']];
        }
        foreach ($draft['rows'] as $row) {
            if ($row['kind'] === 'stock' && $row['remember'] === 1 && $row['code'] !== '') {
                $key = barInvoiceMappingKey($draft['supplier_id'], $row['code'], $row['unit']);
                $pdo->prepare('DELETE FROM kis_bar_supplier_mappings WHERE mapping_key=?')->execute([$key]);
                $pdo->prepare('INSERT INTO kis_bar_supplier_mappings(mapping_key,supplier_id,supplier_code,invoice_unit,product_id,multiplier) VALUES(?,?,?,?,?,?)')
                    ->execute([$key, $draft['supplier_id'], $row['code'], $row['unit'], $row['product_id'], $row['multiplier']]);
            }
        }
        $pdo->prepare('UPDATE kis_bar_invoice_receipts SET snapshot_json=? WHERE id=?')->execute([json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
     $receiptId]);
        $pdo->prepare("UPDATE kis_bar_invoices SET status='received',current_receipt_id=?,lock_version=lock_version+1 WHERE id=?")->execute([$receiptId,
     $id]);
        barEvent($pdo, $actor['id'], 'invoice', $id, 'received', ['receipt_id' => $receiptId, 'reason' => $reason]);
        return ['invoice_id' => $id, 'receipt_id' => $receiptId];
    }, 'invoice_manage');
}

function barReverseInvoice(PDO $pdo, int $id, int $version, string $reason, string $token): array
{
    $reason = barReason($reason);
    return barRunRequest($pdo, 'invoice_reverse', $token, [$id, $version, $reason], function (array $actor, string $suffix) use (
        $pdo,
        $id,
        $version,
        $reason
    ): array {
        $invoice = barInvoice($pdo, $id, $suffix);
        barInvoiceVersion($invoice, $version, 'received');
        $receipt = (int)$invoice['current_receipt_id'];
        $query = $pdo->prepare('SELECT * FROM kis_bar_invoice_receipt_items WHERE receipt_id=? ORDER BY product_id');
        $query->execute([$receipt]);
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
        $products = barInvoiceProducts($pdo, $items, $suffix);
        foreach ($items as $item) {
            $product = $products[$item['product_id']] ?? null;
            if (!$product || barHasPendingStock($pdo, (int)$item['product_id'], 'office')) {
                throw new DomainException('Nejprve vyřešte rezervace a zásobu v předskladu.');
            }
            barRecordPhysicalMovement(
                $pdo,
                $actor,
                $product,
                -(int)$item['quantity'],
                'office',
                'invoice_reverse',
                $receipt,
                (int)$item['unit_cost_minor'],
                $product['name']
            );
        }
        $draft = $invoice['draft'];
        $draft['verified'] = 0;
        $pdo->prepare("UPDATE kis_bar_invoice_receipts SET status='reversed',reversed_at=" . barSqlUtcNow($pdo) . ",reversed_by=?,reversal_reason=? WHERE id=? AND status='received'")
            ->execute([$actor['id'], $reason, $receipt]);
        $pdo->prepare("UPDATE kis_bar_invoices SET status='draft',current_receipt_id=NULL,draft_json=?,lock_version=lock_version+1 WHERE id=?")
            ->execute([json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $id]);
        barEvent($pdo, $actor['id'], 'invoice', $id, 'receipt_reversed', ['receipt_id' => $receipt, 'reason' => $reason, 'catalog_prices_retained' => true]);
        return ['invoice_id' => $id, 'receipt_id' => $receipt];
    }, 'invoice_manage');
}
