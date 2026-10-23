<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/paper_registration.php';
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
try { paperRegistrationRequireRegistrar(); }
catch (DomainException $e) { http_response_code(403); exit('Přístup odepřen.'); }
try { $path = paperRegistrationDownload($pdo,(int)($_GET['id'] ?? 0)); }
catch (DomainException $e) { http_response_code(404); exit('Soubor není dostupný.'); }
$mime = privateStorageMime($path);
$extension = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime];
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="papirovy-podklad-'.(int)$_GET['id'].'.'.$extension.'"');
readfile($path);
