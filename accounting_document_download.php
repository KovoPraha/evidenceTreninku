<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/accounting_documents.php';
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
try{accountingDocumentRequireFinance();}catch(DomainException){http_response_code(403);exit('Přístup má pouze Hospodář a platby.');}
try{$id=(int)($_GET['id']??0);$path=accountingDocumentDownload($pdo,$id);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="doklad-'.$id.'.pdf"');header('Content-Length: '.filesize($path));readfile($path);}catch(DomainException){http_response_code(404);echo 'Vydaný doklad není dostupný nebo nesouhlasí jeho otisk.';}
