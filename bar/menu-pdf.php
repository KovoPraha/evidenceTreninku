<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/menu_view.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
barRequireWebActor($pdo, 'offers_manage');
$options = new Dompdf\Options();
$options->setIsRemoteEnabled(false);
$options->setIsPhpEnabled(false);
$options->setIsJavascriptEnabled(false);
$pdf = new Dompdf\Dompdf($options);
$pdf->loadHtml(barMenuHtml(barPublicMenu($pdo), true), 'UTF-8');
$pdf->setPaper('A4');
$pdf->render();
$pdf->stream('nabidka-baru.pdf', ['Attachment' => false]);
