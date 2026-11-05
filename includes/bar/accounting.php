<?php

declare(strict_types=1);

require_once __DIR__ . '/requests.php';

function barAccountingJson(array $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Includes already exported evidence. Closures and drawer transfers never enter revenue again. */
function barAccountingReport(PDO $pdo, string $from, string $to): array
{
    barActor($pdo, 'accounting');
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $last = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if (!$first || !$last || $first->format('Y-m-d') !== $from || $last->format('Y-m-d') !== $to
        || $last < $first || $first->diff($last)->days > 366) {
        throw new InvalidArgumentException('Vyberte platné období nejvýše 367 dní.');
    }
    $bounds = [$from . ' 00:00:00', $last->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
    $query = $pdo->prepare("SELECT COALESCE(SUM(s.goods_net_minor),0) AS goods,COALESCE(SUM(s.deposit_minor),0) AS deposits,
        COALESCE(SUM(CASE WHEN p.method='cash' THEN p.amount_minor ELSE 0 END),0) AS cash,
        COALESCE(SUM(CASE WHEN p.method='card' THEN p.amount_minor ELSE 0 END),0) AS card,
        COALESCE(SUM(CASE WHEN p.method='qr' THEN p.amount_minor ELSE 0 END),0) AS qr
        FROM kis_bar_sales s JOIN kis_bar_sale_payments p ON p.sale_id=s.id
        WHERE p.status='confirmed' AND p.confirmed_at>=? AND p.confirmed_at<?");
    $query->execute($bounds);
    $report = array_map('intval', $query->fetch(PDO::FETCH_ASSOC));
    $query = $pdo->prepare("SELECT COALESCE(SUM(v.goods_minor),0) AS goods,COALESCE(SUM(v.deposit_minor),0) AS deposits,
        COALESCE(SUM(CASE WHEN v.refund_method='cash' THEN v.refunded_minor ELSE 0 END),0) AS cash,
        COALESCE(SUM(CASE WHEN v.refund_method='card' THEN v.refunded_minor ELSE 0 END),0) AS card,
        COALESCE(SUM(CASE WHEN v.refund_method='qr' THEN v.refunded_minor ELSE 0 END),0) AS qr
        FROM kis_bar_voids v JOIN kis_bar_sale_payments p ON p.sale_id=v.sale_id
        WHERE p.status='confirmed' AND v.created_at>=? AND v.created_at<?");
    $query->execute($bounds);
    foreach ($query->fetch(PDO::FETCH_ASSOC) as $key => $value) {
        $report[$key] -= (int)$value;
    }
    $query = $pdo->prepare('SELECT COALESCE(SUM(total_minor),0) FROM kis_bar_cup_returns WHERE created_at>=? AND created_at<?');
    $query->execute($bounds);
    $cups = (int)$query->fetchColumn();
    $report['cash'] -= $cups;
    $report['deposits'] -= $cups;
    $report['payments'] = $report['cash'] + $report['card'] + $report['qr'];
    $query = $pdo->prepare("SELECT COUNT(*) FROM kis_bar_sale_payments p JOIN kis_bar_sales s ON s.id=p.sale_id
        WHERE p.status='pending' AND s.created_at>=? AND s.created_at<?");
    $query->execute($bounds);
    $report['pending_count'] = (int)$query->fetchColumn();
    return $report;
}

/** Operational evidence only. Never insert these rows directly into accounting_movements. */

function barAccountingSources(PDO $pdo): array
{
    barActor($pdo, 'accounting');
    $concat = static fn (string $prefix, string $column): string => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? "CONCAT('" . $prefix . "'," . $column . ")" : "('" . $prefix . "' || " . $column . ")";
    $events = [];
    $add = static function (string $key, string $kind, string $date, array $detail) use (&$events): void {
        $event = ['source_key' => $key, 'kind' => $kind, 'occurred_at' => $date, 'currency' => 'CZK'] + $detail;
        $events[$key] = ['event' => $event, 'sha256' => hash('sha256', barAccountingJson($event))];
    };
    // Each source type has its own cursor through the assignment table. Old exported rows cannot starve new rows.
    $sales = $pdo->query("SELECT s.*,p.method,p.amount_minor,p.confirmation_reference,p.verification_kind,p.confirmed_at FROM kis_bar_sales s
        LEFT JOIN kis_bar_sale_payments p ON p.sale_id=s.id
        WHERE (s.kind='consumption' OR p.status='confirmed') AND NOT EXISTS(SELECT 1 FROM kis_bar_accounting_export_items e WHERE e.source_key="
    . $concat(
        'bar:sale:',
        's.id'
    ) . ")
        ORDER BY s.id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC);
    $costs = [];
    foreach (array_chunk(array_column($sales, 'id'), 400) as $ids) {
        $query = $pdo->prepare('SELECT sale_id,quantity,purchase_unit_minor FROM kis_bar_sale_lines WHERE sale_id IN (' .
    implode(
        ',',
        array_fill(0, count($ids), '?')
    ) . ') ORDER BY sale_id,id');
        $query->execute($ids);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $sid = (int)$line['sale_id'];
            if (!array_key_exists($sid, $costs)) {
                $costs[$sid] = 0;
            }
            $costs[$sid] = $costs[$sid] === null || $line['purchase_unit_minor'] === null ? null : $costs[$sid] + (int)$line['quantity']
    * (int)$line['purchase_unit_minor'];
        }
    }
    foreach ($sales as $row) {
        $consumption = $row['kind'] === 'consumption';
        $add(
            'bar:sale:' . $row['id'],
            $consumption ? 'own_consumption' : 'sale_settlement',
            $consumption ? $row['created_at']
    : $row['confirmed_at'],
            [
            'sale_id' => (int)$row['id'], 'cashbox_id' => $row['cashbox_id'] === null ? null : (int)$row['cashbox_id'],
            'goods_including_tax_minor' => (int)$row['goods_net_minor'], 'deposit_liability_minor' => (int)$row['deposit_minor'],
            'payment_minor' => $consumption ? 0 : (int)$row['amount_minor'], 'payment_method' => $row['method'],
            'payment_reference' => $row['confirmation_reference'], 'verification_kind' => $row['verification_kind'],
            'estimated_cost_minor' => $costs[(int)$row['id']] ?? null, 'tax_breakdown' => null,
        ]
        );
    }
    $voids = $pdo->query("SELECT v.*,s.kind,s.created_at AS sale_created_at FROM kis_bar_voids v JOIN kis_bar_sales s ON s.id=v.sale_id
        WHERE (s.kind='consumption' OR EXISTS(SELECT 1 FROM kis_bar_sale_payments p WHERE p.sale_id=s.id AND p.status='confirmed'))
        AND NOT EXISTS(SELECT 1 FROM kis_bar_accounting_export_items e WHERE e.source_key=" . $concat('bar:void:', 'v.id')
    . ") ORDER BY v.id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($voids as $row) {
        $add(
            'bar:void:' . $row['id'],
            $row['kind'] === 'consumption' ? 'own_consumption_reversal' : 'sale_refund',
            $row['created_at'],
            [
            'original_source_key' => 'bar:sale:' . $row['sale_id'], 'goods_including_tax_minor' => -(int)$row['goods_minor'],
            'deposit_liability_minor' => -(int)$row['deposit_minor'], 'payment_minor' => -(int)$row['refunded_minor'],
            'payment_method' => $row['refund_method'], 'payment_reference' => $row['refund_reference'], 'reason' => $row['reason'],
            'cost_reversal_source_key' => 'bar:sale:' . $row['sale_id'], 'tax_breakdown' => null,
        ]
        );
    }
    foreach ($pdo->query("SELECT r.* FROM kis_bar_cup_returns r WHERE NOT EXISTS(SELECT 1 FROM kis_bar_accounting_export_items e WHERE e.source_key="
    . $concat(
        'bar:cup:',
        'r.id'
    ) . ") ORDER BY r.id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $add('bar:cup:' . $row['id'], 'deposit_return', $row['created_at'], ['original_source_key' => 'bar:sale:' . $row['sale_id'],
            'cashbox_id' => (int)$row['cashbox_id'], 'goods_including_tax_minor' => 0, 'deposit_liability_minor' => -(int)$row['total_minor'],
            'payment_minor' => -(int)$row['total_minor'], 'payment_method' => 'cash', 'reason' => $row['reason']]);
    }
    foreach ($pdo->query("SELECT r.* FROM kis_bar_invoice_receipts r WHERE NOT EXISTS(SELECT 1 FROM kis_bar_accounting_export_items e WHERE e.source_key="
    . $concat(
        'bar:receipt:',
        'r.id'
    ) . ") ORDER BY r.id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $add('bar:receipt:' . $row['id'], 'supplier_receipt', $row['created_at'], ['receipt_id' => (int)$row['id'],
            'received_on' => $row['received_on'], 'snapshot' => json_decode($row['snapshot_json'], true, 32, JSON_THROW_ON_ERROR),
     'payment_minor' => 0]);
    }
    foreach ($pdo->query("SELECT r.* FROM kis_bar_invoice_receipts r WHERE r.status='reversed' AND NOT EXISTS(SELECT 1 FROM kis_bar_accounting_export_items e WHERE e.source_key="
    . $concat(
        'bar:receipt-reversal:',
        'r.id'
    ) . ") ORDER BY r.id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $add('bar:receipt-reversal:' . $row['id'], 'supplier_receipt_reversal', $row['reversed_at'], ['original_source_key'
    => 'bar:receipt:' . $row['id'],
            'snapshot' => json_decode($row['snapshot_json'], true, 32, JSON_THROW_ON_ERROR), 'sign' => -1, 'reason' => $row['reversal_reason'],
     'payment_minor' => 0]);
    }
    foreach ($pdo->query("SELECT m.* FROM kis_bar_cash_movements m WHERE m.source_type IN ('manual','cashbox_open')
        AND NOT EXISTS(SELECT 1 FROM kis_bar_accounting_export_items e WHERE e.source_key=" . $concat('bar:drawer:', 'm.id')
    . ") ORDER BY m.id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $add('bar:drawer:' . $row['id'], 'drawer_transfer', $row['created_at'], ['cashbox_id' => (int)$row['cashbox_id'],
            'drawer_delta_minor' => (int)$row['amount_minor'], 'goods_including_tax_minor' => 0, 'payment_minor' => 0, 'reason'
    => $row['reason']]);
    }
    foreach ($pdo->query("SELECT b.* FROM kis_bar_cashboxes b WHERE b.closed_at IS NOT NULL
        AND NOT EXISTS(SELECT 1 FROM kis_bar_accounting_export_items e WHERE e.source_key=" . $concat('bar:closure:', 'b.id')
    . ") ORDER BY b.id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $add('bar:closure:' . $row['id'], 'cashbox_closure', $row['closed_at'], ['cashbox_id' => (int)$row['id'],
            'expected_minor' => (int)$row['expected_minor'], 'counted_minor' => (int)$row['counted_minor'], 'difference_minor'
    => (int)$row['difference_minor'],
            'snapshot' => json_decode($row['closing_snapshot_json'], true, 32, JSON_THROW_ON_ERROR), 'goods_including_tax_minor'
    => 0,
     'payment_minor' => 0]);
    }
    ksort($events, SORT_STRING);
    return $events;
}

function barCreateAccountingExport(PDO $pdo, array $selection, string $reason, string $token): array
{
    if (count($selection) < 1 || count($selection) > 500 || array_is_list($selection)) {
        throw new InvalidArgumentException('Vyberte 1 až 500 ověřených zdrojů.');
    }
    foreach ($selection as $key => $sha) {
        if (!is_string($key) || !preg_match('/^bar:[a-z-]+:\d+$/D', $key) || !is_string($sha) || !preg_match(
            '/^[a-f0-9]{64}$/D',
            $sha
        )) {
            throw new InvalidArgumentException('Neplatný účetní zdroj.');
        }
    }
    ksort($selection, SORT_STRING);
    $reason = barReason($reason);
    return barRunRequest($pdo, 'accounting_export', $token, [$selection, $reason], function (array $actor) use (
        $pdo,
        $selection,
        $reason
    ): array {
        $available = barAccountingSources($pdo);
        $events = [];
        foreach ($selection as $key => $sha) {
            if (!isset($available[$key]) || !hash_equals($available[$key]['sha256'], $sha)) {
                throw new BarConflictException('Zdroj se změnil nebo již patří jinému exportu. Obnovte náhled.');
            }
            $events[] = $available[$key]['event'];
        }
        $manifest = ['schema' => 'kis.bar.accounting.v1', 'status' => 'review_required', 'currency' => 'CZK',
            'database_timezone' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? (string)$pdo->query('SELECT @@session.time_zone')->fetchColumn()
    : 'test-sqlite',
            'business_timezone' => 'Europe/Prague', 'generated_at' => gmdate('c'), 'generated_by' => $actor['id'], 'reason'
    => $reason,
            'rules' => ['No automatic ledger posting. Reconcile card/QR references with canonical bank/terminal movements before posting.',
                'Sale totals include tax; VAT split is unavailable. Purchase receipts are not supplier payments.',
                'Deposits are liabilities. Drawer transfers and closures are evidence, not additional revenue.',
                'Costs are historical estimates, not FIFO. A null cost must be resolved, never treated as zero.',
                'Reversals compensate the referenced source. Preserve source keys across every downstream import.'], 'events'
    => $events];
        $json = barAccountingJson($manifest);
        $sha = hash('sha256', $json);
        $pdo->prepare('INSERT INTO kis_bar_accounting_exports(actor_id,manifest_json,sha256) VALUES(?,?,?)')->execute([$actor['id'],
     $json, $sha]);
        $id = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO kis_bar_accounting_export_items(source_key,export_id,event_sha256) VALUES(?,?,?)');
        foreach ($selection as $key => $eventSha) {
            $insert->execute([$key, $id, $eventSha]);
        }
        barEvent($pdo, $actor['id'], 'accounting_export', $id, 'created', ['sha256' => $sha, 'sources' => array_keys($selection),
     'reason' => $reason]);
        return ['export_id' => $id, 'sha256' => $sha];
    }, 'accounting');
}

function barAccountingExport(PDO $pdo, int $id): array
{
    barActor($pdo, 'accounting');
    $query = $pdo->prepare('SELECT * FROM kis_bar_accounting_exports WHERE id=?');
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row || !hash_equals($row['sha256'], hash('sha256', $row['manifest_json']))) {
        throw new DomainException('Export nebyl nalezen nebo má jiný otisk.');
    }
    return $row;
}

function barAccountingCsv(array $events): string
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('Export nelze vytvořit.');
    }
    fputcsv($stream, ['source_key', 'kind', 'occurred_at', 'goods_including_tax_minor', 'deposit_liability_minor', 'payment_minor',
     'payment_method', 'payment_reference'], ';', '"', '');
    foreach ($events as $event) {
        $row = [];
        foreach (['source_key', 'kind', 'occurred_at', 'goods_including_tax_minor', 'deposit_liability_minor', 'payment_minor',
     'payment_method', 'payment_reference'] as $key) {
            $value = $event[$key] ?? '';
            $row[] = is_string($value) && preg_match('/^\s*[=+@-]/u', $value) ? "'" . $value : $value;
        }
        fputcsv($stream, $row, ';', '"', '');
    }
    rewind($stream);
    $result = stream_get_contents($stream);
    fclose($stream);
    return (string)$result;
}
