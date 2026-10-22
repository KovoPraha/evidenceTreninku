<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/accounting_export.php';
staffRequireActivePosition('finance_manager');
try{
    $file=accountingExportDownload($pdo,(int)($_GET['id']??0),(int)$_SESSION['trener_id']);
    header('Content-Type: application/zip');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="ucetni-podklady-'.$file['row']['period_from'].'-r'.(int)$file['row']['revision'].'.zip"');
    header('Content-Length: '.filesize($file['path']));readfile($file['path']);
}catch(DomainException $e){http_response_code(404);echo htmlspecialchars($e->getMessage(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
