<?php
declare(strict_types=1);

/** Explicit accounting defaults for isolated publication fixtures, never production data. */
function shopAccountingPublicationFixture(PDO $pdo): void
{
    $migration = require dirname(__DIR__, 2) . '/migrations/20261021210000_shop_number_series.php';
    $migration['up']($pdo);
    $pdo->exec("UPDATE shop_accounting_mappings SET category_id=30 WHERE mapping_type='kind' AND mapping_key='goods'");
}
