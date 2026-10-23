<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';require_once __DIR__.'/includes/financial_decisions.php';
staffRequireActivePosition('finance_manager');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{$file=financialDecisionFile($pdo,(int)$_SESSION['trener_id'],(int)($_GET['id']??0));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['path']);header('Content-Type: '.$mime);header('Content-Disposition: attachment; filename="podklad-'.$file['row']['id'].'.'.pathinfo($file['path'],PATHINFO_EXTENSION).'"');readfile($file['path']);}catch(DomainException){http_response_code(403);echo 'Podklad není dostupný.';}
