<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/club_event.php';
require_once __DIR__ . '/includes/club_event_export.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Použijte tlačítko exportu v administraci akce.');
}
if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(400);
    exit('Formulář vypršel. Vraťte se do administrace a zkuste export znovu.');
}

try {
    $export = clubEventParticipantExport($pdo, (int)($_POST['event_id'] ?? 0));
    $csv = clubEventParticipantExportCsv($export);
    clubEventAuditParticipantExport($pdo, $export, (int)$_SESSION['trener_id']);
} catch (InvalidArgumentException|ClubEventExportException $exception) {
    http_response_code(404);
    exit($exception->getMessage());
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . clubEventParticipantExportFilename($export['event']) . '"');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
echo $csv;
