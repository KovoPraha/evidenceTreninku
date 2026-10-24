<?php
declare(strict_types=1);

/** Read-only diagnostic: no application bootstrap, migrations, downloads or export creation. */
function productionAccountingAudit(PDO $pdo, string $storageRoot, bool $includeStock = false): array
{
    if ($pdo->inTransaction()) throw new RuntimeException('Audit requires its own read-only transaction.');
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $oldQueryOnly = $driver === 'sqlite' ? (int)$pdo->query('PRAGMA query_only')->fetchColumn() : 0;
    if ($driver === 'mysql') $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    elseif ($driver === 'sqlite') { $pdo->exec('PRAGMA query_only=ON'); $pdo->beginTransaction(); }
    else throw new RuntimeException('Unsupported audit database.');
    try {
        $checks = [];
        $record = static function (string $name, int $count) use (&$checks): void {
            $checks[$name] = ['status' => $count === 0 ? 'ok' : 'violation', 'count' => $count];
        };
        $sqlChecks = [
            'orphan_order_documents' => "SELECT COUNT(*) FROM accounting_documents d LEFT JOIN shop_orders o ON o.id=d.source_id WHERE d.source_type='order' AND o.id IS NULL",
            'order_invoice_amount_currency' => "SELECT COUNT(*) FROM accounting_documents d JOIN shop_orders o ON o.id=d.source_id WHERE d.source_type='order' AND d.kind='invoice' AND d.status='issued' AND (d.total_minor<>o.total_minor OR d.currency<>o.currency)",
            'orphan_correction_documents' => "SELECT COUNT(*) FROM accounting_documents d LEFT JOIN accounting_documents p ON p.id=d.original_document_id WHERE d.kind='correction' AND (p.id IS NULL OR p.status<>'issued' OR p.currency<>d.currency OR p.source_type<>d.source_type OR p.source_id<>d.source_id)",
            'orphan_order_allocations' => "SELECT COUNT(*) FROM accounting_allocations a LEFT JOIN shop_orders o ON o.id=a.case_id LEFT JOIN accounting_movements m ON m.id=a.movement_id WHERE a.case_type='shop_order' AND (o.id IS NULL OR m.id IS NULL OR o.currency<>m.currency)",
            'allocation_over_receipt' => 'SELECT COUNT(*) FROM (SELECT a.movement_id FROM accounting_allocations a JOIN accounting_movements m ON m.id=a.movement_id GROUP BY a.movement_id,m.amount_minor HAVING SUM(a.amount_minor)<0 OR SUM(a.amount_minor)>m.amount_minor) x',
            'refund_payment_links' => "SELECT COUNT(*) FROM accounting_refund_payments p LEFT JOIN accounting_refund_decisions d ON d.id=p.decision_id LEFT JOIN accounting_movements m ON m.id=p.movement_id WHERE d.id IS NULL OR m.id IS NULL OR p.amount_minor<=0 OR m.kind<>'refund' OR m.amount_minor<>-p.amount_minor OR m.currency<>d.currency OR m.reversal_of_id IS NULL OR m.reversal_of_id<>d.original_movement_id OR m.refund_decision_id IS NULL OR m.refund_decision_id<>d.id",
            'refund_exceeds_decision' => 'SELECT COUNT(*) FROM (SELECT p.decision_id FROM accounting_refund_payments p JOIN accounting_refund_decisions d ON d.id=p.decision_id GROUP BY p.decision_id,d.amount_minor HAVING SUM(p.amount_minor)>d.amount_minor) x',
            'export_revision_links' => 'SELECT COUNT(*) FROM accounting_export_batches b LEFT JOIN accounting_export_batches p ON p.id=b.predecessor_id WHERE (b.revision=1 AND b.predecessor_id IS NOT NULL) OR (b.revision>1 AND (p.id IS NULL OR p.revision<>b.revision-1 OR p.period_from<>b.period_from OR p.period_to<>b.period_to))',
        ];
        foreach ($sqlChecks as $name => $sql) $record($name, (int)$pdo->query($sql)->fetchColumn());
        $inventory = [];
        foreach (['shop_orders','accounting_documents','accounting_movements','accounting_allocations','accounting_refund_decisions','accounting_refund_payments','accounting_export_batches'] as $table) {
            $inventory[$table] = (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        }
        // Resolve only existing expected files, inside the configured private root, without creating directories.
        $root = realpath($storageRoot);
        $resolve = static function (string $key) use ($root): ?string {
            if (!$root || !preg_match('~^private://(accounting-documents|accounting-exports|stock-documents)/([a-f0-9]{32}\.(pdf|zip))$~D', $key, $m)) return null;
            $path = realpath($root.DIRECTORY_SEPARATOR.$m[1].DIRECTORY_SEPARATOR.$m[2]);
            return $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path) && is_readable($path) ? $path : null;
        };
        $badSnapshot = $badTotal = $badPdf = $issued = 0;
        $documents = $pdo->query('SELECT status,snapshot_json,snapshot_sha256,total_minor,currency,pdf_storage_key,pdf_sha256 FROM accounting_documents');
        while ($d = $documents->fetch(PDO::FETCH_ASSOC)) {
            $snapshot = json_decode($d['snapshot_json'], true);
            if (!hash_equals($d['snapshot_sha256'], hash('sha256', $d['snapshot_json'])) || !is_array($snapshot)) $badSnapshot++;
            if (!is_array($snapshot) || !isset($snapshot['lines'],$snapshot['total_minor'],$snapshot['currency']) || !is_array($snapshot['lines'])
                || array_sum(array_column($snapshot['lines'], 'amount_minor')) !== (int)$d['total_minor']
                || (int)$snapshot['total_minor'] !== (int)$d['total_minor'] || $snapshot['currency'] !== $d['currency']) $badTotal++;
            if ($d['status'] === 'issued') {
                $issued++;
                $path = $resolve((string)$d['pdf_storage_key']);
                if (!$path || !hash_equals((string)$d['pdf_sha256'], (string)hash_file('sha256', $path))) $badPdf++;
            }
        }
        $record('document_snapshot_integrity', $badSnapshot); $record('document_snapshot_totals', $badTotal); $record('issued_pdf_integrity', $badPdf);
        $badBatch = $badArchive = $badManifest = $entriesChecked = 0;
        $batches = $pdo->query('SELECT snapshot_json,snapshot_sha256,storage_key,archive_sha256 FROM accounting_export_batches');
        while ($b = $batches->fetch(PDO::FETCH_ASSOC)) {
            if (!hash_equals($b['snapshot_sha256'], hash('sha256', $b['snapshot_json'])) || !is_array(json_decode($b['snapshot_json'], true))) $badBatch++;
            $path = $resolve($b['storage_key']);
            if (!$path || !hash_equals($b['archive_sha256'], (string)hash_file('sha256', $path))) { $badArchive++; continue; }
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::RDONLY) !== true) { $badArchive++; continue; }
            try {
                $manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
                if (!is_array($manifest) || empty($manifest['files']) || !is_array($manifest['files'])) { $badManifest++; continue; }
                $names = [];
                foreach ($manifest['files'] as $entry) {
                    if (!is_array($entry) || !is_string($entry['path'] ?? null) || !is_int($entry['bytes'] ?? null) || !is_string($entry['sha256'] ?? null)) { $badManifest++; continue; }
                    $name = $entry['path'];
                    if (isset($names[$name])) $badManifest++;
                    $names[$name] = true;
                    $stream = $zip->getStream($name);
                    if ($stream === false) { $badManifest++; continue; }
                    try { $hash = hash_init('sha256'); $bytes = hash_update_stream($hash, $stream); $digest = hash_final($hash); }
                    finally { fclose($stream); }
                    $entriesChecked++;
                    if ($bytes !== $entry['bytes'] || !hash_equals($entry['sha256'], $digest)) $badManifest++;
                }
                if (!isset($names['data.json'],$names['ucetni-podklady.xlsx']) || count($names)+1 !== $zip->numFiles
                    || $zip->getFromName('data.json') !== $b['snapshot_json']) $badManifest++;
            } finally { $zip->close(); }
        }
        $record('export_snapshot_integrity', $badBatch); $record('export_archive_integrity', $badArchive); $record('export_manifest_integrity', $badManifest);
        $stock = $includeStock ? productionStockAuditInTransaction($pdo, $resolve) : null;
        return ['ok' => !in_array('violation', array_column($checks, 'status'), true) && ($stock === null || $stock['ok']), 'read_only' => true,
            'stock' => $stock,
            'inventory' => $inventory, 'checks' => $checks, 'issued_pdfs_checked' => $issued, 'archive_entries_checked' => $entriesChecked,
            'limitations' => ['Empty tables do not prove an end-to-end production transaction.', 'No invoices or exports created; accounting methodology and completeness require human acceptance.']];
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($driver === 'sqlite') $pdo->exec('PRAGMA query_only='.($oldQueryOnly ? 'ON' : 'OFF'));
    }
}

/** Called inside the audit's consistent read-only transaction. Returns counts only, never customer data. */
function productionStockAuditInTransaction(PDO $pdo, callable $resolve): array
{
    if (!$pdo->inTransaction()) throw new RuntimeException('Stock audit requires the read-only snapshot.');
    $sql = [
        'invalid_balance' => 'SELECT COUNT(*) FROM shop_stock_balances WHERE physical_quantity<0 OR reserved_quantity<0 OR blocked_quantity<0 OR physical_quantity<reserved_quantity+blocked_quantity',
        'balance_vs_movements' => 'SELECT COUNT(*) FROM shop_stock_balances b WHERE b.physical_quantity<>COALESCE((SELECT SUM(physical_delta) FROM shop_stock_movements m WHERE m.variant_id=b.variant_id),0) OR b.reserved_quantity<>COALESCE((SELECT SUM(reserved_delta) FROM shop_stock_movements m WHERE m.variant_id=b.variant_id),0) OR b.blocked_quantity<>COALESCE((SELECT SUM(blocked_delta) FROM shop_stock_movements m WHERE m.variant_id=b.variant_id),0)',
        'balance_vs_lots' => 'SELECT COUNT(*) FROM shop_stock_balances b WHERE b.physical_quantity<>COALESCE((SELECT SUM(remaining_quantity) FROM shop_stock_lots l WHERE l.variant_id=b.variant_id),0) OR b.blocked_quantity<>COALESCE((SELECT SUM(blocked_quantity) FROM shop_stock_lots l WHERE l.variant_id=b.variant_id),0)',
        'balance_vs_reservations' => 'SELECT COUNT(*) FROM shop_stock_balances b WHERE b.reserved_quantity<>COALESCE((SELECT SUM(remaining_quantity) FROM shop_stock_reservations r WHERE r.variant_id=b.variant_id),0)',
        'catalog_availability' => "SELECT COUNT(*) FROM shop_stock_balances b LEFT JOIN shop_variants v ON v.id=b.variant_id WHERE b.mode='tracked' AND (v.id IS NULL OR v.stock_quantity_decimal IS NULL OR CAST(v.stock_quantity_decimal AS DECIMAL(20,4))<>b.physical_quantity-b.reserved_quantity-b.blocked_quantity)",
        'invalid_lot' => 'SELECT COUNT(*) FROM shop_stock_lots WHERE remaining_quantity<0 OR blocked_quantity<0 OR blocked_quantity>remaining_quantity OR remaining_quantity>original_quantity',
        'lot_usage_totals' => 'SELECT COUNT(*) FROM shop_stock_lots l WHERE l.original_quantity-l.remaining_quantity<>COALESCE((SELECT SUM(quantity) FROM shop_stock_lot_uses u WHERE u.lot_id=l.id),0)',
        'lot_source_links' => 'SELECT COUNT(*) FROM shop_stock_lots l LEFT JOIN shop_stock_movements m ON m.id=l.source_movement_id WHERE m.id IS NULL OR m.variant_id<>l.variant_id OR m.physical_delta<=0',
        'invalid_reservation' => 'SELECT COUNT(*) FROM shop_stock_reservations WHERE remaining_quantity<0 OR issued_quantity<0 OR released_quantity<0 OR reserved_quantity<>remaining_quantity+issued_quantity+released_quantity',
        'reservation_order_links' => "SELECT COUNT(*) FROM shop_stock_reservations r LEFT JOIN shop_order_items i ON i.id=r.order_item_id LEFT JOIN shop_orders o ON o.id=r.order_id WHERE r.source_type='order' AND (i.id IS NULL OR o.id IS NULL OR i.order_id<>r.order_id OR i.variant_id<>r.variant_id OR i.quantity<>r.reserved_quantity)",
        'reservation_exchange_links' => "SELECT COUNT(*) FROM shop_stock_reservations r LEFT JOIN shop_return_cases c ON c.id=r.source_id WHERE r.source_type='exchange' AND (c.id IS NULL OR c.exchange_reservation_id IS NULL OR c.exchange_reservation_id<>r.id OR c.exchange_variant_id<>r.variant_id OR c.quantity<>r.reserved_quantity)",
        'fulfillment_order_links' => 'SELECT COUNT(*) FROM shop_fulfillment_items i LEFT JOIN shop_fulfillments f ON f.id=i.fulfillment_id LEFT JOIN shop_order_items o ON o.id=i.order_item_id LEFT JOIN shop_stock_movements m ON m.id=i.inventory_movement_id WHERE f.id IS NULL OR o.id IS NULL OR m.id IS NULL OR o.order_id<>f.order_id OR o.variant_id<>i.variant_id OR m.order_id IS NULL OR m.order_id<>f.order_id OR m.order_item_id IS NULL OR m.order_item_id<>o.id OR m.variant_id<>i.variant_id OR i.quantity<=0 OR m.physical_delta<>-i.quantity OR m.kind<>\'issue\' OR COALESCE(m.cost_minor,-1)<>COALESCE(i.cost_minor,-1)',
        'returns_exceed_issue' => "SELECT COUNT(*) FROM (SELECT r.related_movement_id FROM shop_stock_movements r JOIN shop_stock_movements i ON i.id=r.related_movement_id WHERE r.kind='return' GROUP BY r.related_movement_id,i.physical_delta HAVING SUM(r.physical_delta)>-i.physical_delta) excess",
        'return_movement_links' => "SELECT COUNT(*) FROM shop_stock_movements r LEFT JOIN shop_stock_movements i ON i.id=r.related_movement_id WHERE r.kind='return' AND (i.id IS NULL OR i.kind<>'issue' OR r.variant_id<>i.variant_id OR r.physical_delta<=0)",
        'return_case_order_links' => "SELECT COUNT(*) FROM shop_return_cases c LEFT JOIN shop_order_items i ON i.id=c.order_item_id LEFT JOIN shop_orders o ON o.id=c.order_id LEFT JOIN shop_stock_movements m ON m.id=c.issue_movement_id WHERE i.id IS NULL OR o.id IS NULL OR m.id IS NULL OR i.order_id<>o.id OR m.kind<>'issue' OR m.variant_id<>c.variant_id",
        'received_return_links' => "SELECT COUNT(*) FROM shop_return_cases c LEFT JOIN shop_stock_movements m ON m.id=c.return_movement_id WHERE c.physical_state='owned_blocked' AND (m.id IS NULL OR m.kind<>'return' OR m.related_movement_id<>c.issue_movement_id OR m.physical_delta<>c.quantity OR m.variant_id<>c.variant_id)",
        'return_finance_links' => "SELECT COUNT(*) FROM shop_return_finance_links l LEFT JOIN shop_return_cases c ON c.id=l.case_id LEFT JOIN accounting_documents d ON d.id=l.correction_document_id LEFT JOIN accounting_refund_decisions r ON r.id=l.refund_decision_id WHERE c.id IS NULL OR d.id IS NULL OR d.status<>'issued' OR d.kind<>'correction' OR d.currency<>c.currency OR l.amount_minor<=0 OR (l.refund_decision_id IS NOT NULL AND (r.id IS NULL OR r.correction_document_id IS NULL OR r.correction_document_id<>d.id OR r.currency<>c.currency))",
        'closed_return_unpaid_refund' => "SELECT COUNT(*) FROM shop_return_finance_links l JOIN shop_return_cases c ON c.id=l.case_id JOIN accounting_refund_decisions d ON d.id=l.refund_decision_id WHERE c.state='closed' AND (d.amount_minor<>COALESCE((SELECT SUM(p.amount_minor) FROM accounting_refund_payments p WHERE p.decision_id=d.id),0) OR EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled'))",
    ];
    $checks = []; $inventory = [];
    foreach ($sql as $name => $query) { $count=(int)$pdo->query($query)->fetchColumn(); $checks[$name]=['status'=>$count===0?'ok':'violation','count'=>$count]; }
    foreach (['shop_stock_balances','shop_stock_movements','shop_stock_reservations','shop_stock_lots','shop_fulfillments','shop_fulfillment_items','shop_return_cases','shop_return_finance_links','shop_stock_documents'] as $table) $inventory[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    $inventory['goods_variants_without_managed_balance']=(int)$pdo->query("SELECT COUNT(*) FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type='goods' AND NOT EXISTS(SELECT 1 FROM shop_stock_balances b WHERE b.variant_id=v.id AND b.mode='tracked')")->fetchColumn();
    $invalidPdf = $invalidSnapshot = $checked = 0;
    $q=$pdo->query('SELECT snapshot_json,snapshot_sha256,storage_key,pdf_sha256 FROM shop_stock_documents');
    while($d=$q->fetch(PDO::FETCH_ASSOC)) {
        $checked++;$path=$resolve($d['storage_key']);
        if(!$path || !hash_equals($d['pdf_sha256'],(string)hash_file('sha256',$path)))$invalidPdf++;
        if(!hash_equals($d['snapshot_sha256'],hash('sha256',$d['snapshot_json'])) || !is_array(json_decode($d['snapshot_json'],true)))$invalidSnapshot++;
    }
    $checks['stock_pdf_integrity']=['status'=>$invalidPdf===0?'ok':'violation','count'=>$invalidPdf];
    $checks['stock_snapshot_integrity']=['status'=>$invalidSnapshot===0?'ok':'violation','count'=>$invalidSnapshot];
    return ['ok'=>!in_array('violation',array_column($checks,'status'),true),'inventory'=>$inventory,'checks'=>$checks,'pdfs_checked'=>$checked,
        'limitations'=>['Unmanaged catalog variants are an inventory, not an inferred physical count or an automatic migration.', 'Empty stock tables do not prove actual warehouse operations; physical inventory remains a human task.']];
}

function productionAccountingAuditMain(): int
{
    if (PHP_SAPI !== 'cli') { http_response_code(404); return 2; }
    ini_set('display_errors', '0');
    $root = realpath((string)getenv('APP_ROOT'));
    if (getenv('KIS_ACCOUNTING_AUDIT_CONFIRM') !== 'OVERIT-UCETNI-VAZBY' || !$root || !is_file($root.'/config.php') || getenv('APP_HOST') !== 'kis.kovopraha.cz') {
        echo "{\"ok\":false,\"error\":\"accounting_audit_refused\"}\n"; return 2;
    }
    $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz'; $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    ob_start();
    try { require $root.'/config.php'; } catch (Throwable) { ob_end_clean(); echo "{\"ok\":false,\"error\":\"accounting_audit_configuration\"}\n"; return 2; }
    ob_end_clean();
    try {
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false || !defined('APP_BASE_URL') || rtrim(APP_BASE_URL,'/') !== 'https://kis.kovopraha.cz') throw new RuntimeException('Wrong application.');
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        $storage = getenv('APP_PRIVATE_STORAGE_ROOT') ?: (defined('APP_PRIVATE_STORAGE_ROOT') ? APP_PRIVATE_STORAGE_ROOT : '');
        $report = productionAccountingAudit($pdo, $storage, true);
        $report['release'] = json_decode((string)file_get_contents($root.'/var/deployment.json'), true);
        foreach (['includes/accounting_export.php','includes/accounting_adjustments.php','includes/accounting_ledger.php','includes/shop_inventory.php','includes/shop_fulfillment.php','includes/shop_returns.php','includes/accounting_stock_export.php','includes/shop_stock_documents.php'] as $file) $report['runtime_sha256'][$file] = hash_file('sha256', $root.'/'.$file);
        echo json_encode($report, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        return $report['ok'] ? 0 : 1;
    } catch (Throwable) { echo "{\"ok\":false,\"error\":\"accounting_audit_failed\"}\n"; return 2; }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) exit(productionAccountingAuditMain());
