<?php
declare(strict_types=1);

/** Read-only diagnostic: no application bootstrap, migrations, downloads or export creation. */
function productionAccountingAudit(PDO $pdo, string $storageRoot): array
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
            if (!$root || !preg_match('~^private://(accounting-documents|accounting-exports)/([a-f0-9]{32}\.(pdf|zip))$~D', $key, $m)) return null;
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
        return ['ok' => !in_array('violation', array_column($checks, 'status'), true), 'read_only' => true,
            'inventory' => $inventory, 'checks' => $checks, 'issued_pdfs_checked' => $issued, 'archive_entries_checked' => $entriesChecked,
            'limitations' => ['Empty tables do not prove an end-to-end production transaction.', 'No invoices or exports created; accounting methodology and completeness require human acceptance.']];
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($driver === 'sqlite') $pdo->exec('PRAGMA query_only='.($oldQueryOnly ? 'ON' : 'OFF'));
    }
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
        $report = productionAccountingAudit($pdo, $storage);
        $report['release'] = json_decode((string)file_get_contents($root.'/var/deployment.json'), true);
        foreach (['includes/accounting_export.php','includes/accounting_adjustments.php','includes/accounting_ledger.php'] as $file) $report['runtime_sha256'][$file] = hash_file('sha256', $root.'/'.$file);
        echo json_encode($report, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        return $report['ok'] ? 0 : 1;
    } catch (Throwable) { echo "{\"ok\":false,\"error\":\"accounting_audit_failed\"}\n"; return 2; }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) exit(productionAccountingAuditMain());
