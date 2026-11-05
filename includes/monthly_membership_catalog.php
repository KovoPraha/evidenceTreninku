<?php

declare(strict_types=1);

require_once __DIR__.'/membership_options.php';

function monthlyMembershipTable(PDO $pdo, string $table): bool
{
    if (!preg_match('/^monthly_membership[a-z_]*$/D', $table)) {
        throw new InvalidArgumentException('Neplatná tabulka členství.');
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    } else {
        $q = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    }
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

/** Inactive tariffs still identify monthly variants: never sell them as one-off goods. */
function monthlyMembershipTariffForVariant(PDO $pdo, int $variantId): ?array
{
    if (!monthlyMembershipTable($pdo, 'monthly_membership_tariffs')) {
        return null;
    }
    $q = $pdo->prepare('SELECT * FROM monthly_membership_tariffs WHERE variant_id=?');
    $q->execute([$variantId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

function monthlyMembershipProductConfigured(PDO $pdo, int $productId): bool
{
    if (!monthlyMembershipTable($pdo, 'monthly_membership_tariffs')) {
        return false;
    }
    $q = $pdo->prepare(('SELECT COUNT(*) FROM shop_variants v LEFT JOIN monthly_membership_tariffs t ON t.variant_id=v.id AND t.status=\'active\' WHERE '
    .
    'v.product_id=? AND (v.visible=1 OR v.visible IS NULL)'));
    $q->execute([$productId]);
    $count = (int)$q->fetchColumn();
    $q = $pdo->prepare(('SELECT COUNT(*) FROM shop_variants v JOIN monthly_membership_tariffs t ON t.variant_id=v.id AND t.status=\'active\' WHERE '
    .
    'v.product_id=? AND (v.visible=1 OR v.visible IS NULL)'));
    $q->execute([$productId]);
    return $count > 0 && $count === (int)$q->fetchColumn();
}

function monthlyMembershipSalesEnabled(): bool
{
    return defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED') && MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED === true;
}
