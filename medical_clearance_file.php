<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/medical_clearance.php';
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'; sandbox");
try{medicalClearanceRegistrar($pdo);}catch(DomainException){http_response_code(403);exit('Přístup odepřen.');}
try{$path=medicalClearanceDownload($pdo,(int)($_GET['id']??0));}catch(DomainException){http_response_code(404);exit('Soubor není dostupný.');}
$mime=privateStorageMime($path);$ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime]??'bin';
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="podklad-'.(int)$_GET['id'].'.'.$ext.'"');readfile($path);
