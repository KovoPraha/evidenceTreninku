<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/web.php';
require_once dirname(__DIR__) . '/includes/bar/offers.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
barRequireWebActor($pdo, 'catalog_read');
barRenderCatalog($pdo, barFilterPublishedProducts($pdo, barCatalog($pdo)), false);
