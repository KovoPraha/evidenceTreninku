<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/web.php';
require_once dirname(__DIR__) . '/includes/bar/offers.php';
barRequireWebActor($pdo, 'catalog_read');
barRenderCatalog($pdo, barFilterPublishedProducts($pdo, barCatalog($pdo)), false);
