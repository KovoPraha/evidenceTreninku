<?php

declare(strict_types=1);

// Frozen from feb8fe8c/includes/shop_member_pricing.php; no application config or transport.

function beforeShopMemberPricingAvailable(PDO $pdo): bool
{
    static $cache = null;
    $cache ??= new WeakMap();
    if (isset($cache[$pdo])) {
        return true;
    }
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('shop_member_category_rules','shop_member_product_prices')");
        $available = (int)$statement->fetchColumn() === 2;
        if ($available) {
            $cache[$pdo] = true;
        }return $available;
    }
    $statement = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('shop_member_category_rules','shop_member_product_prices')");
    $available = (int)$statement->fetchColumn() === 2;
    if ($available) {
        $cache[$pdo] = true;
    }return $available;
}

/** @return list<array{id:int,name:string}> */
function beforeShopMemberPricingEligibleTeams(PDO $pdo, int $accountId, ?string $onDate = null): array
{
    if ($accountId < 1 || !beforeShopMemberPricingAvailable($pdo)) {
        return [];
    }
    $at = $onDate === null ? date('Y-m-d H:i:s') : $onDate . ' 00:00:00';
    $onDate ??= date('Y-m-d');
    $statement = $pdo->prepare(
        "SELECT DISTINCT t.id,t.name FROM account_person_roles r "
        . "JOIN verejni_uzivatele u ON u.id=r.account_id AND u.aktivni=1 AND u.email_overeno=1 "
        . "JOIN club_roster_members m ON m.sportovec_id=r.sportovec_id "
        . "JOIN club_teams t ON t.id=m.team_id "
        . "WHERE r.account_id=? AND r.status='approved' AND r.relation_role IN ('self','guardian') "
        . "AND r.valid_from<=? AND (r.valid_to IS NULL OR r.valid_to>?) "
        . "AND m.status='active' AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) "
        . "AND t.status='active' ORDER BY t.name,t.id"
    );
    $statement->execute([$accountId,$at,$at,$onDate,$onDate]);
    return array_map(static fn (array $row): array => ['id' => (int)$row['id'],'name' => (string)$row['name']], $statement->fetchAll(PDO::FETCH_ASSOC));
}

/** @param array<string,mixed> $variant @return array<string,mixed> */
function beforeShopMemberPriceQuoteForVariant(PDO $pdo, int $accountId, array $variant, ?string $onDate = null): array
{
    $public = (int)($variant['amount_minor'] ?? 0);
    $currency = (string)($variant['currency'] ?? '');
    $productId = (int)($variant['product_id'] ?? 0);
    $base = [
        'public_amount_minor' => $public,'effective_amount_minor' => $public,'currency' => $currency,
        'is_member_price' => false,'team_id' => null,'team_name' => null,'source_type' => null,'source_label' => null,
    ];
    if ($accountId < 1 || $productId < 1 || $public < 0 || !beforeShopMemberPricingAvailable($pdo)) {
        return $base;
    }
    $teams = beforeShopMemberPricingEligibleTeams($pdo, $accountId, $onDate);
    if ($teams === []) {
        return $base;
    }
    $teamNames = [];
    $ids = [];
    foreach ($teams as $team) {
        $ids[] = (int)$team['id'];
        $teamNames[(int)$team['id']] = (string)$team['name'];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $productPrices = $pdo->prepare("SELECT team_id,amount_minor FROM shop_member_product_prices WHERE active=1 AND product_id=? AND currency=? AND team_id IN ($marks)");
    $productPrices->execute([$productId,$currency,...$ids]);
    $specificTeams = [];
    $candidates = [];
    foreach ($productPrices->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teamId = (int)$row['team_id'];
        $specificTeams[$teamId] = true;
        $candidates[] = ['amount' => (int)$row['amount_minor'],'team_id' => $teamId,'type' => 'product','label' => 'Cena produktu pro soupisku'];
    }
    $categories = $pdo->prepare('SELECT category_path FROM shop_product_categories WHERE product_id=? ORDER BY is_default DESC,sort_order,id');
    $categories->execute([$productId]);
    $paths = $categories->fetchAll(PDO::FETCH_COLUMN);
    if ($paths !== []) {
        $pathMarks = implode(',', array_fill(0, count($paths), '?'));
        $rules = $pdo->prepare("SELECT team_id,category_path,discount_type,value_minor_or_basis_points,currency "
            . "FROM shop_member_category_rules WHERE active=1 AND team_id IN ($marks) AND category_path IN ($pathMarks)");
        $rules->execute([...$ids,...$paths]);
        foreach ($rules->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $teamId = (int)$row['team_id'];
            if (isset($specificTeams[$teamId])) {
                continue;
            }
            $type = (string)$row['discount_type'];
            $value = (int)$row['value_minor_or_basis_points'];
            if ($type === 'percentage') {
                $amount = max(0, $public - intdiv($public * $value, 10000));
            } elseif ($type === 'fixed_discount' && (string)$row['currency'] === $currency) {
                $amount = max(0, $public - $value);
            } else {
                continue;
            }
            $candidates[] = ['amount' => $amount,'team_id' => $teamId,'type' => 'category','label' => 'Sleva kategorie '.(string)$row['category_path']];
        }
    }
    usort($candidates, static fn (array $a, array $b): int => [$a['amount'],$a['team_id']] <=> [$b['amount'],$b['team_id']]);
    $winner = $candidates[0] ?? null;
    if ($winner === null || $winner['amount'] >= $public) {
        return $base;
    }
    return array_replace($base, [
        'effective_amount_minor' => $winner['amount'],'is_member_price' => true,'team_id' => $winner['team_id'],
        'team_name' => $teamNames[$winner['team_id']] ?? ('#'.$winner['team_id']),
        'source_type' => $winner['type'],'source_label' => $winner['label'],
    ]);
}

/** @param array<string,mixed> $item */
function beforeShopMemberPriceApplyToItem(PDO $pdo, int $accountId, array &$item, ?string $onDate = null): void
{
    $quote = beforeShopMemberPriceQuoteForVariant($pdo, $accountId, $item, $onDate);
    $item['public_amount_minor'] = $quote['public_amount_minor'];
    $item['amount_minor'] = $quote['effective_amount_minor'];
    $item['member_price'] = $quote;
}


/** Canonical presentation loop from feb8fe8c booking/eshop.php. */
function beforeMemberPricingCatalog(PDO $pdo, int $accountId, array $products, ?string $onDate = null): array
{
    foreach ($products as &$product) {
        foreach ($product['variants'] as &$variant) {
            if (!isset($variant['monthly_tariff_id'])) {
                beforeShopMemberPriceApplyToItem($pdo, $accountId, $variant, $onDate);
            }
        }
        unset($variant);
    }
    unset($product);
    return $products;
}
