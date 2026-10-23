<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/weekly_report_storage.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if (!isset($_SESSION['trener_id']) || !canAccess('exporty')) { http_response_code(403); exit('Report není dostupný.'); }
try {
    $file = weeklyReportFile($pdo, (string)($_GET['id'] ?? ''));
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="tydenni-report.pdf"');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($file['path']);
} catch (DomainException $error) {
    http_response_code(403);
    echo 'Report není dostupný ve vašem aktuálním pracovním rozsahu.';
}
