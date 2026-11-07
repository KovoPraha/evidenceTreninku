<?php

declare(strict_types=1);

require_once __DIR__.'/shop_payment_notification.php';
require_once __DIR__.'/accounting_documents.php';

function shopDocumentPolicy(PDO $pdo): ?array
{
    if (!shopPaymentNotificationTableExists($pdo, 'shop_document_policies')) {
        return null;
    }
    $row = $pdo->query('SELECT * FROM shop_document_policies ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    return $row && (int)$row['enabled'] === 1 ? $row : null;
}

/** Protected operations entry point: reuse a named, previously approved issuer and its still-valid finance mandate. */
function shopDocumentEnableApprovedIssuer(PDO $pdo, int $issuerId, string $registrationId, string $reason): int
{
    if (PHP_SAPI !== 'cli' || $issuerId < 1 || trim($registrationId) === '') {
        throw new DomainException('Aktivace vyžaduje chráněnou provozní operaci a přesného vystavovatele.');
    }
    $q = $pdo->prepare('SELECT * FROM accounting_issuer_versions WHERE id=?');
    $q->execute([$issuerId]);
    $issuer = $q->fetch(PDO::FETCH_ASSOC);
    $party = $issuer ? json_decode($issuer['snapshot_json'], true, 512, JSON_THROW_ON_ERROR) : [];
    if (!$issuer || !hash_equals((string)($party['registration_id'] ?? ''), $registrationId)) {
        throw new DomainException('Schválený vystavovatel neodpovídá očekávanému IČ.');
    }
    return AccountingDocumentWorker::run($pdo, (int)$issuer['approved_by'], static function () use (
        $pdo,
        $issuerId,
        $reason
    ): int {
        $current = shopDocumentPolicy($pdo);
        if ($current !== null) {
            if ((int)$current['issuer_id'] !== $issuerId) {
                throw new DomainException('Aktivní pověření používá jiného vystavovatele; změnu proveďte v administraci.');
            }
            return (int)$current['id'];
        }
        return shopDocumentConfigure($pdo, $issuerId, $reason);
    });
}

/** An explicit, versioned finance mandate. Never run during migrations or GET requests. */
function shopDocumentConfigure(PDO $pdo, int $issuerId, string $reason, bool $enabled = true): int
{
    $actor = accountingDocumentRequireFinance($pdo);
    $reason = accountingDocumentText($reason);
    return accountingDocumentConfigureTransaction($pdo, static function () use (
        $pdo,
        $issuerId,
        $reason,
        $enabled,
        $actor
    ): int {
        $q = $pdo->prepare('SELECT id FROM accounting_issuer_versions WHERE id=?');
        $q->execute([$issuerId]);
        if (!$q->fetchColumn()) {
            throw new DomainException('Nejprve schvalte fakturační údaje klubu.');
        }
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
        $rule = accountingDocumentConfigureRule($pdo, [
            'source_type' => 'order', 'document_kind' => 'invoice', 'category_code' => 'ESHOP', 'activity' => 'PRODEJ',
            'title' => 'Jednorázové objednávky e-shopu', 'valid_from' => $today, 'confirmed' => '1', 'approval_evidence'
    => $reason,
        ]);
        foreach ([(int)substr($today, 0, 4), (int)substr($today, 0, 4) + 1] as $year) {
            foreach (['invoice' => 'FA', 'correction' => 'OPR'] as $kind => $prefix) {
                $q = $pdo->prepare('SELECT id FROM accounting_document_series WHERE kind=? AND series_year=?');
                $q->execute([$kind, $year]);
                if (!$q->fetchColumn()) {
                    accountingDocumentConfigureSeries($pdo, [
                        'kind' => $kind, 'series_year' => $year, 'prefix' => $prefix, 'next_number' => 1,
                        'confirmed' => '1', 'reason' => $reason,
                    ]);
                }
            }
        }
        $pdo->prepare(
            'INSERT INTO shop_document_policies(issuer_id,rule_id,approved_by,enabled,reason) VALUES(?,?,?,?,?)'
        )
            ->execute([$issuerId, $rule, $actor, (int)$enabled, $reason]);
        return (int)$pdo->lastInsertId();
    });
}

/** Called inside the checkout transaction, after items and payment exist. No historical backfill. */
function shopDocumentRegister(PDO $pdo, int $orderId, array $billing = []): void
{
    $policy = shopDocumentPolicy($pdo);
    if ($policy === null) {
        return;
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('Registrace dokladu vyžaduje transakci objednávky.');
    }
    $q = $pdo->prepare('SELECT * FROM shop_orders WHERE id=?');
    $q->execute([$orderId]);
    $order = $q->fetch(PDO::FETCH_ASSOC);
    if (!$order || (int)$order['total_minor'] < 1 || shopPaymentNotificationIsOfflineSale($pdo, $orderId, (string)$order[
    'customer_email_snapshot'])) {
        return;
    }
    $street = trim((string)($billing['billing_street'] ?? $order['address_street_snapshot'] ?? ''));
    $city = trim((string)($billing['billing_city'] ?? $order['address_city_snapshot'] ?? ''));
    $postcode = trim((string)($billing['billing_postcode'] ?? $order['address_postcode_snapshot'] ?? ''));
    if ($street === '' || $city === '' || $postcode === '') {
        throw new InvalidArgumentException('Vyplňte fakturační adresu: ulici, město a PSČ.');
    }
    foreach ([$street, $city, $postcode] as $value) {
        accountingDocumentText($value, 180);
    }
    $buyer = accountingDocumentParty([
        'name' => trim((string)($billing['billing_name'] ?? '')) ?: $order['customer_name_snapshot'],
        'address' => $street.', '.$postcode.' '.$city,
        'registration_id' => $billing['billing_registration_id'] ?? '',
    ]);
    $source = accountingDocumentSource($pdo, 'order', $orderId);
    $json = accountingDocumentJson($source);
    $pdo->prepare(
        ('UPDATE shop_orders SET address_street_snapshot=?,address_city_snapshot=?,' .
    'address_postcode_snapshot=? WHERE id=?')
    )
        ->execute([$street, $city, $postcode, $orderId]);
    $pdo->prepare(
        'INSERT INTO shop_order_documents(order_id,policy_id,buyer_json,source_json,source_sha256) VALUES(?,?,?,?,?)'
    )
        ->execute([$orderId, $policy['id'], accountingDocumentJson($buyer), $json, hash('sha256', $json)]);
    if (($order['checkout_mode'] ?? 'account') === 'account') {
        shopDocumentNotify($pdo, $orderId, 'shop_order_placed');
    }
}

function shopDocumentRegistered(PDO $pdo, int $orderId): ?array
{
    if (!shopPaymentNotificationTableExists($pdo, 'shop_order_documents')) {
        return null;
    }
    $q = $pdo->prepare('SELECT * FROM shop_order_documents WHERE order_id=?');
    $q->execute([$orderId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Individual ledger payouts are distinct events; a decision alone never sends a refund confirmation. */
function shopDocumentRefundPaid(PDO $pdo, array $original, array $movement, array $decision): void
{
    $orderId = ($original['case_type'] ?? '') === 'shop_order' ? (int)$original['case_id'] : 0;
    if ($orderId < 1 && !empty($decision['original_document_id'])) {
        $doc = accountingDocumentGet($pdo, (int)$decision['original_document_id']);
        if ($doc['source_type'] === 'order') {
            $orderId = (int)$doc['source_id'];
        }
    }
    if ($orderId < 1 || !shopDocumentRegistered($pdo, $orderId)) {
        return;
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('Potvrzení vratky vyžaduje účetní transakci.');
    }
    $q = $pdo->prepare('SELECT * FROM shop_orders WHERE id=?');
    $q->execute([$orderId]);
    $order = $q->fetch(PDO::FETCH_ASSOC);
    if (!$order || $order['payment_status'] === 'refunded') {
        return;
    } // Canonical completion already enqueued the cumulative confirmation.
    $amount = -(int)$movement['amount_minor'];
    if ($amount < 1 || $movement['currency'] !== $order['currency']) {
        throw new DomainException('Částka nebo měna vratky neodpovídá objednávce.');
    }
    $type = 'shop_refund_paid:'.(int)$movement['id'];
    $q = $pdo->prepare('SELECT id FROM club_event_notifications WHERE order_id=? AND notification_type=?');
    $q->execute([$orderId,$type]);
    if ($q->fetchColumn()) {
        return;
    }
    $message = shopSystemEmailMessage($pdo, 'shop_refund_paid', [
        'order_code' => $order['public_code'],'customer_name' => $order['customer_name_snapshot'],
        'amount' => shopPaymentNotificationMoney($amount, $order['currency']),
        'status_instruction' => in_array($order['checkout_mode'] ?? 'account', ['guest','quick_program'], true)
            ? 'Aktuální stav najdete v bezpečném odkazu z potvrzení objednávky.'
            : 'Aktuální stav najdete po přihlášení: '.appUrl('booking/objednavka.php?code='.rawurlencode($order['public_code'
    ])),
    ]);
    $pdo->prepare(
        ('INSERT INTO club_event_notifications(order_id,notification_type,recipient_email,recipient_name,' .
    'subject_plain,body_plain) VALUES(?,?,?,?,?,?)')
    )
        ->execute([$orderId,$type,$order['customer_email_snapshot'],$order['customer_name_snapshot'],$message['subject'],
    $message['body']]);
    shopSystemEmailRecord($pdo, (int)$pdo->lastInsertId(), $message);
}

function shopDocumentPaid(PDO $pdo, int $orderId): void
{
    if (!shopDocumentRegistered($pdo, $orderId)) {
        return;
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('Doklad vyžaduje transakci úhrady.');
    }
    $pdo->prepare((("UPDATE shop_order_documents SET paid_at=(SELECT paid_at FROM payments WHERE " .
    "payable_type='shop_order' AND payable_id=? AND ") .
    "status='paid') WHERE order_id=? AND paid_at IS NULL"))
        ->execute([$orderId, $orderId]);
}

/** The queue's unique(order_id,notification_type) makes a repeated transition harmless. */
function shopDocumentNotify(PDO $pdo, int $orderId, string $type): int
{
    if (!shopDocumentRegistered($pdo, $orderId)) {
        return 0;
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('Oznámení vyžaduje transakci objednávky.');
    }
    $q = $pdo->prepare((('SELECT o.*,p.variable_symbol,p.due_at,p.iban_snapshot FROM shop_orders o JOIN payments p ON ' .
    'p.payable_type=\'shop_order\' AND ') .
    'p.payable_id=o.id WHERE o.id=?'));
    $q->execute([$orderId]);
    $order = $q->fetch(PDO::FETCH_ASSOC);
    $states = ['shop_order_placed' => ['placed'], 'shop_order_cancelled' => ['cancelled'], 'shop_order_refunded' => ['cancelled'
    ],
        'shop_invoice_issued' => ['placed','processing','ready','completed','cancelled'], 'shop_correction_issued' => ['cancelled'
    ]];
    if (!$order || !isset($states[$type]) || !in_array($order['status'], $states[$type], true)
        || ($type === 'shop_order_refunded' && $order['payment_status'] !== 'refunded')) {
        throw new DomainException('Událost neodpovídá stavu objednávky.');
    }
    $instruction = in_array($order['checkout_mode'] ?? 'account', ['guest','quick_program'], true)
        ? 'Objednávku a doklady otevřete bezpečným odkazem z původního potvrzení objednávky.'
        : 'Objednávku a doklady najdete po přihlášení: '.appUrl('booking/objednavka.php?code='.rawurlencode($order['public_code'
    ]));
    $values = ['order_code' => $order['public_code'], 'customer_name' => $order['customer_name_snapshot'],
        'amount' => shopPaymentNotificationMoney((int)$order['total_minor'], $order['currency']),
        'variable_symbol' => $order['variable_symbol'], 'due_at' => $order['due_at'], 'bank_account' => $order['iban_snapshot'
    ],
        'status_instruction' => $instruction,
        'refund_instruction' => $order['payment_status'] === 'refund_required'
            ? 'Platbu evidujeme. Vrácení peněz musí klub ještě provést; potvrdíme je samostatným e-mailem.'
            : (
                $order['payment_status'] === 'refunded' ? 'Vrácení platby již bylo potvrzeno.' : 'Neuhrazený platební předpis byl zrušen. Platbu již neposílejte.'
            ),
    ];
    $message = shopSystemEmailMessage($pdo, $type, $values);
    $q = $pdo->prepare('SELECT id FROM club_event_notifications WHERE order_id=? AND notification_type=?');
    $q->execute([$orderId,$type]);
    if ($id = $q->fetchColumn()) {
        return (int)$id;
    }
    try {
        $pdo->prepare(
            ('INSERT INTO club_event_notifications(order_id,notification_type,recipient_email,recipient_name,' .
    'subject_plain,body_plain) VALUES(?,?,?,?,?,?)')
        )
            ->execute([$orderId,$type,$order['customer_email_snapshot'],$order['customer_name_snapshot'],$message['subject'
    ],$message['body']]);
    } catch (PDOException $e) {
        if ((string)$e->getCode() !== '23000') {
            throw $e;
        }
        $q->execute([$orderId,$type]);
        $id = $q->fetchColumn();
        if (!$id) {
            throw $e;
        } return (int)$id;
    }
    $id = (int)$pdo->lastInsertId();
    shopSystemEmailRecord($pdo, $id, $message);
    return $id;
}

/** Read-only diagnostics: no recipient data or documents in operational logs. */
function shopDocumentStatus(PDO $pdo): array
{
    $policy = shopDocumentPolicy($pdo);
    $issuer = $pdo->query('SELECT id,snapshot_json FROM accounting_issuer_versions ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $party = $issuer ? json_decode($issuer['snapshot_json'], true, 512, JSON_THROW_ON_ERROR) : [];
    return ['enabled' => $policy !== null, 'policy_id' => $policy['id'] ?? null,
        'available_issuer' => $issuer ? ['id' => (int)$issuer['id'], 'name' => $party['name'],
            'registration_id' => $party['registration_id']] : null,
        'registered' => (int)$pdo->query('SELECT COUNT(*) FROM shop_order_documents')->fetchColumn(),
        'document_errors' => (int)$pdo->query('SELECT COUNT(*) FROM shop_order_documents WHERE last_error IS NOT NULL')->
    fetchColumn(),
        'pending_documents' => (int)$pdo->query(
            'SELECT COUNT(*) FROM shop_order_documents WHERE paid_at IS NOT NULL AND invoice_id IS NULL'
        )->fetchColumn(),
        'failed_emails' => (int)$pdo->query(
            ("SELECT COUNT(*) FROM club_event_notifications WHERE order_id IN (SELECT order_id FROM " .
    "shop_order_documents) AND status='failed'")
        )->fetchColumn(),
        'pending_emails' => (int)$pdo->query(
            ("SELECT COUNT(*) FROM club_event_notifications WHERE order_id IN (SELECT order_id FROM " .
    "shop_order_documents) AND status='pending'")
        )->fetchColumn()];
}

/** Validated private PDF; callers must first authorize the owning order. */
function shopDocumentFile(PDO $pdo, int $orderId, int $documentId): array
{
    $q = $pdo->prepare((('SELECT d.* FROM shop_order_documents s JOIN accounting_documents d ON d.id=s.invoice_id OR ' .
    'd.id=s.correction_id WHERE ') .
    's.order_id=? AND d.id=? AND d.status=\'issued\''));
    $q->execute([$orderId,$documentId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new DomainException('Doklad nebyl nalezen.');
    }
    $path = privateStorageResolve((string)$row['pdf_storage_key']);
    if ($path === null || !is_file($path) || !hash_equals($row['pdf_sha256'], (string)hash_file('sha256', $path))
        || !hash_equals($row['snapshot_sha256'], hash('sha256', $row['snapshot_json']))) {
        throw new DomainException('Kontrola souboru dokladu selhala.');
    }
    return ['path' => $path,'name' => 'doklad-'.$row['id'].'.pdf'];
}

/** Issue/retry using canonical accounting APIs. No money or membership mutation occurs here. */
function shopDocumentProcessOrder(PDO $pdo, int $orderId): void
{
    AccountingDocumentWorker::runOrder($pdo, $orderId, static function () use ($pdo, $orderId): void {
        $q = $pdo->prepare((('SELECT s.*,p.issuer_id,p.rule_id,o.status FROM shop_order_documents s JOIN ' .
    'shop_document_policies p ON p.id=s.policy_id JOIN ') .
    'shop_orders o ON o.id=s.order_id WHERE s.order_id=?'));
        $q->execute([$orderId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $source = AccountingDocumentWorker::orderSource($pdo, $orderId);
        $rules = [];
        foreach ($source['lines'] as $line) {
            $rules[$line['line_key']] = (int)$row['rule_id'];
        }
        $invoice = (int)$row['invoice_id'];
        if ($invoice < 1) {
            // Never rebuild an unrelated manual draft or send it with a different buyer snapshot.
            $q = $pdo->prepare('SELECT idempotency_key FROM accounting_documents WHERE source_unique_key=?');
            $q->execute(['order:'.$orderId.':invoice']);
            $existing = $q->fetchColumn();
            if ($existing !== false && $existing !== 'shop-auto:'.$orderId) {
                throw new DomainException('Objednávka má ruční doklad; vyžaduje kontrolu účetní.');
            }
            $invoice = accountingDocumentCreateDraft($pdo, [
                'kind' => 'invoice','source_type' => 'order','source_id' => $orderId,'request_key' => 'shop-auto:'.$orderId
    ,
                'effective_on' => substr($row['paid_at'], 0, 10),'due_on' => $source['due_on_suggestion'],
                'buyer' => json_decode($row['buyer_json'], true, 512, JSON_THROW_ON_ERROR), 'issuer_id' => (int)$row['issuer_id'
    ],
                'line_rules' => $rules,'reason' => 'Automatické vystavení po potvrzení úhrady objednávky.',
            ]);
            $pdo->prepare('UPDATE shop_order_documents SET invoice_id=? WHERE order_id=?')->execute([$invoice,$orderId]);
        }
        accountingDocumentIssue($pdo, $invoice, 'Automatické vystavení po úhradě.');
        $pdo->beginTransaction();
        try {
            shopDocumentNotify($pdo, $orderId, 'shop_invoice_issued');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            } throw $e;
        }
        // Re-read: a cancellation may have committed during PDF rendering.
        $q = $pdo->prepare('SELECT status FROM shop_orders WHERE id=?');
        $q->execute([$orderId]);
        if ($q->fetchColumn() === 'cancelled') {
            $correction = (int)$row['correction_id'];
            if ($correction < 1) {
                $original = accountingDocumentGet($pdo, $invoice);
                $snapshot = json_decode($original['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
                $q = $pdo->prepare(
                    ("SELECT COUNT(*) FROM accounting_documents WHERE original_document_id=? AND status<>'void' AND " .
    "idempotency_key<>?")
                );
                $q->execute([$invoice,'shop-cancel:'.$orderId]);
                if ((int)$q->fetchColumn() > 0) {
                    throw new DomainException('Doklad má ruční opravu; úplné storno musí zkontrolovat účetní.');
                }
                // Allocate the net cancellation across positive lines; do not refund the discount twice.
                $remaining = (int)$original['total_minor'];
                $lines = [];
                foreach ($snapshot['lines'] as $line) {
                    $amount = min($remaining, max(0, (int)$line['amount_minor']));
                    if ($amount > 0) {
                        $lines[] = ['line_key' => $line['line_key'],'amount_minor' => $amount,'quantity' => (int)$line['quantity'
    ]];
                    }
                    $remaining -= $amount;
                }
                if ($remaining !== 0) {
                    throw new DomainException('Částka storna nesouhlasí s dokladem.');
                }
                $correction = accountingDocumentCreateCorrection(
                    $pdo,
                    $invoice,
                    $lines,
                    'shop-cancel:'.$orderId,
                    'Storno celé objednávky; skutečné vrácení peněz se eviduje samostatně.'
                );
                $pdo->prepare('UPDATE shop_order_documents SET correction_id=? WHERE order_id=?')->execute([$correction,$orderId
    ]);
            }
            accountingDocumentIssue($pdo, $correction, 'Automatický opravný doklad ke stornu.');
            $pdo->beginTransaction();
            try {
                shopDocumentNotify($pdo, $orderId, 'shop_correction_issued');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                } throw $e;
            }
        }
        $pdo->prepare('UPDATE shop_order_documents SET last_error=NULL WHERE order_id=?')->execute([$orderId]);
    });
}

/** A bounded worker; canonical document keys/number reservation also protect concurrent retries. */
function shopDocumentDrain(PDO $pdo, callable $sender, int $limit = 25): array
{
    require_once __DIR__.'/club_event_notification.php';
    $result = ['documents' => 0,'sent' => 0,'failed' => 0];
    if (!shopPaymentNotificationTableExists($pdo, 'shop_order_documents')) {
        return $result;
    }
    $limit = max(1, min(100, $limit));
    $sql = (("SELECT s.order_id FROM shop_order_documents s JOIN shop_orders o ON o.id=s.order_id WHERE " .
    "s.paid_at IS NOT NULL AND ") .
    ("s.retry_at<=CURRENT_TIMESTAMP AND (s.invoice_id IS NULL OR NOT EXISTS (SELECT 1 FROM " .
    "club_event_notifications n WHERE ") .
    ("n.order_id=s.order_id AND n.notification_type='shop_invoice_issued') OR (o.status='cancelled' " .
    "AND NOT EXISTS (SELECT 1 FROM ") .
    ("club_event_notifications n WHERE n.order_id=s.order_id AND " .
    "n.notification_type='shop_correction_issued'))) ORDER BY ") .
    "s.retry_at,s.order_id LIMIT ").$limit;
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) {
        try {
            shopDocumentProcessOrder($pdo, (int)$id);
            $result['documents']++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->prepare('UPDATE shop_order_documents SET last_error=?,retry_at=? WHERE order_id=?')
                ->execute([mb_substr($e->getMessage(), 0, 500),date('Y-m-d H:i:s', time() + 300),$id]);
            $result['failed']++;
        }
    }
    for ($i = 0;$i < $limit;$i++) {
        $sent = clubEventNotificationProcessOne($pdo, $sender, false, true);
        if ($sent === null) {
            break;
        }
        $result[$sent === true ? 'sent' : 'failed']++;
    }
    $status = shopDocumentStatus($pdo);
    $result['attention'] = $status['document_errors'] + $status['failed_emails'];
    return $result;
}

function shopDocumentAttachments(PDO $pdo, array $notification): array
{
    $type = $notification['notification_type'];
    if (!in_array($type, ['shop_invoice_issued','shop_correction_issued'], true)) {
        return [];
    }
    $row = shopDocumentRegistered($pdo, (int)$notification['order_id']);
    $id = (int)($row[$type === 'shop_invoice_issued' ? 'invoice_id' : 'correction_id'] ?? 0);
    $file = shopDocumentFile($pdo, (int)$notification['order_id'], $id);
    return [['name' => $file['name'],'bytes' => file_get_contents($file['path'])]];
}

function shopDocumentBillingFields(PDO $pdo, array $input): void
{
    if (shopDocumentPolicy($pdo) === null) {
        return;
    }
    $h = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo(('<fieldset class="border rounded p-3 my-3"><legend class="h5">Fakturační údaje</legend><p ' .
    'class="small">Po úhradě vám zašleme ') .
    'doklad e-mailem. Adresa patří odběrateli, může se lišit od adresy sportovce.</p>');
    foreach (['billing_name' => ['Jméno nebo název odběratele (prázdné = jméno objednávajícího)',200,false],
        'billing_street' => ['Ulice a číslo domu',180,true], 'billing_city' => ['Město',100,true],
        'billing_postcode' => ['PSČ',20,true], 'billing_registration_id' => ['IČ (nepovinné)',40,false]] as $key => [$label
            ,$max,$required]) {
        echo '<label class="form-label" for="'.$key.'">'.$h($label).'</label><input class="form-control mb-2" id="'.$key.
    '" name="'.$key.'" maxlength="'.$max.'" value="'.$h($input[$key] ?? '').'"'.($required ? ' required' : '').'>';
    }
    echo '</fieldset>';
}
