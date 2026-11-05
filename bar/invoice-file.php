<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/web.php';
require_once dirname(__DIR__) . '/includes/bar/invoices.php';
barRequireWebActor($pdo, 'invoice_read');
try {
    $invoice = barInvoice($pdo, (int)($_GET['id'] ?? 0));
    $path = barInvoicePdfPath($invoice);
} catch (DomainException | RuntimeException $error) {
    http_response_code(404);
    exit;
}
header("Content-Security-Policy: default-src 'none'; sandbox; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="bar-invoice-' . (int)$invoice['id'] . '.pdf"');
readfile($path);
