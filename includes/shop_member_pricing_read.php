<?php

declare(strict_types=1);

require_once __DIR__ . '/shop_member_pricing.php';

/** One presentation operation only. Checkout keeps its canonical current reads.
 * @param array<array<string,mixed>> $products
 * @return array<array<string,mixed>>
 */
function shopMemberPricesApplyToCatalog(PDO $pdo, int $accountId, array $products, ?string $onDate = null): array
{
    if ($pdo->inTransaction()) {
        foreach ($products as &$product) {
            foreach ($product['variants'] as &$variant) {
                if (!isset($variant['monthly_tariff_id'])) {
                    shopMemberPriceApplyToItem($pdo, $accountId, $variant, $onDate);
                }
            }
            unset($variant);
        }
        unset($product);
        return $products;
    }

    $read = new ShopMemberPricingCatalogRead($pdo, $accountId, $products, $onDate);
    foreach ($products as &$product) {
        foreach ($product['variants'] as &$variant) {
            if (!isset($variant['monthly_tariff_id'])) {
                $quote = $read->quote($variant);
                $variant['public_amount_minor'] = $quote['public_amount_minor'];
                $variant['amount_minor'] = $quote['effective_amount_minor'];
                $variant['member_price'] = $quote;
            }
        }
        unset($variant);
    }
    unset($product);
    return $products;
}

/** No static cache: a new call must observe changed accounts, memberships and rules. */
final class ShopMemberPricingCatalogRead
{
    private array $teamNames = [];
    private array $currencyKeys = [];
    private array $prices = [];
    private array $productRules = [];
    private array $rules = [];

    /** @param array<array<string,mixed>> $products */
    public function __construct(PDO $pdo, int $accountId, array $products, ?string $onDate)
    {
        $ids = [];
        foreach ($products as $product) {
            foreach ($product['variants'] as $variant) {
                $id = (int) ($variant['product_id'] ?? 0);
                if (isset($variant['monthly_tariff_id']) || $id < 1 || (int) ($variant['amount_minor'] ?? 0) < 0) {
                    continue;
                }
                $ids[$id] = $id;
                $currency = (string) ($variant['currency'] ?? '');
                if (!array_key_exists($currency, $this->currencyKeys)) {
                    $this->currencyKeys[$currency] = count($this->currencyKeys);
                }
            }
        }
        if ($ids === [] || $accountId < 1 || !shopMemberPricingAvailable($pdo)) {
            return;
        }
        foreach (shopMemberPricingEligibleTeams($pdo, $accountId, $onDate) as $team) {
            $this->teamNames[$team['id']] = $team['name'];
        }
        if ($this->teamNames === []) {
            return;
        }
        $teamIds = array_keys($this->teamNames);
        $teamMarks = implode(',', array_fill(0, count($teamIds), '?'));
        $categories = [];
        foreach (array_chunk(array_values($ids), 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            // Let the price column compare each requested currency with SQL's own collation.
            // Product IDs alone are safe PHP keys; strings need not have byte-equal SQL matches.
            $flags = [];
            foreach ($this->currencyKeys as $key) {
                $flags[] = '(currency=?) AS currency_' . $key;
            }
            $q = $pdo->prepare('SELECT product_id,team_id,amount_minor,' . implode(',', $flags)
                . " FROM shop_member_product_prices WHERE active=1 AND product_id IN ($marks) AND team_id IN ($teamMarks)");
            $q->execute([...array_keys($this->currencyKeys), ...$chunk, ...$teamIds]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->prices[(int) $row['product_id']][] = $row;
            }
            $q = $pdo->prepare("SELECT product_id,category_path FROM shop_product_categories WHERE product_id IN ($marks) "
                . 'ORDER BY product_id,is_default DESC,sort_order,id');
            $q->execute($chunk);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $categories[(int) $row['product_id']][] = $row['category_path'];
            }
        }
        $requests = [];
        $keys = [];
        foreach ($categories as $id => $paths) {
            $signature = serialize($paths);
            if (!isset($keys[$signature])) {
                $keys[$signature] = count($requests);
                $requests[] = $paths;
            }
            $this->productRules[$id] = $keys[$signature];
        }
        // Each UNION branch retains the original predicate and input order. In particular,
        // a PHP string-key map or a join between differently collated columns is not equivalent.
        $branches = [];
        $params = [];
        foreach ($requests as $key => $paths) {
            $values = [...$teamIds, ...$paths];
            if ($branches !== [] && (count($params) + count($values) > 800 || count($branches) >= 80)) {
                $this->loadRules($pdo, $branches, $params);
                $branches = [];
                $params = [];
            }
            $pathMarks = implode(',', array_fill(0, count($paths), '?'));
            $branches[] = "SELECT $key AS pricing_read_key,team_id,category_path,discount_type,value_minor_or_basis_points,currency "
                . "FROM shop_member_category_rules WHERE active=1 AND team_id IN ($teamMarks) AND category_path IN ($pathMarks)";
            $params = [...$params, ...$values];
        }
        $this->loadRules($pdo, $branches, $params);
    }

    private function loadRules(PDO $pdo, array $branches, array $params): void
    {
        if ($branches === []) {
            return;
        }
        $q = $pdo->prepare(implode(' UNION ALL ', $branches));
        $q->execute($params);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->rules[(int) $row['pricing_read_key']][] = $row;
        }
    }

    /** @param array<string,mixed> $variant @return array<string,mixed> */
    public function quote(array $variant): array
    {
        $public = (int) ($variant['amount_minor'] ?? 0);
        $currency = (string) ($variant['currency'] ?? '');
        $productId = (int) ($variant['product_id'] ?? 0);
        $base = [
            'public_amount_minor' => $public, 'effective_amount_minor' => $public, 'currency' => $currency,
            'is_member_price' => false, 'team_id' => null, 'team_name' => null, 'source_type' => null, 'source_label' => null,
        ];
        if ($productId < 1 || $public < 0 || $this->teamNames === []) {
            return $base;
        }
        $specificTeams = [];
        $candidates = [];
        $currencyKey = $this->currencyKeys[$currency];
        foreach ($this->prices[$productId] ?? [] as $row) {
            if (!(bool) $row['currency_' . $currencyKey]) {
                continue;
            }
            $team = (int) $row['team_id'];
            $specificTeams[$team] = true;
            $candidates[] = ['amount' => (int) $row['amount_minor'], 'team_id' => $team,
                'type' => 'product', 'label' => 'Cena produktu pro soupisku'];
        }
        $key = $this->productRules[$productId] ?? null;
        foreach ($key === null ? [] : ($this->rules[$key] ?? []) as $row) {
            $team = (int) $row['team_id'];
            if (isset($specificTeams[$team])) {
                continue;
            }
            $type = (string) $row['discount_type'];
            $value = (int) $row['value_minor_or_basis_points'];
            if ($type === 'percentage') {
                $amount = max(0, $public - intdiv($public * $value, 10000));
            } elseif ($type === 'fixed_discount' && (string) $row['currency'] === $currency) {
                $amount = max(0, $public - $value);
            } else {
                continue;
            }
            $candidates[] = ['amount' => $amount, 'team_id' => $team,
                'type' => 'category', 'label' => 'Sleva kategorie ' . (string) $row['category_path']];
        }
        usort($candidates, static fn (array $a, array $b): int => [$a['amount'], $a['team_id']] <=> [$b['amount'], $b['team_id']]);
        $winner = $candidates[0] ?? null;
        if ($winner === null || $winner['amount'] >= $public) {
            return $base;
        }
        return array_replace($base, [
            'effective_amount_minor' => $winner['amount'], 'is_member_price' => true, 'team_id' => $winner['team_id'],
            'team_name' => $this->teamNames[$winner['team_id']] ?? ('#' . $winner['team_id']),
            'source_type' => $winner['type'], 'source_label' => $winner['label'],
        ]);
    }
}
