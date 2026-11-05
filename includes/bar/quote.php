<?php

declare(strict_types=1);

require_once __DIR__ . '/cashbox.php';
require_once __DIR__ . '/offers.php';

function barNormalizeCart(array $cart): array
{
    if (!array_is_list($cart) || count($cart) > 100) {
        throw new InvalidArgumentException('Objednávka smí mít nejvýše sto řádků.');
    }
    $quantities = [];
    foreach ($cart as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Neplatná položka objednávky.');
        }
        $id = barSourceInteger($row['product_id'] ?? null, 1, 2147483647);
        $quantity = barSourceInteger($row['quantity'] ?? null, 1, 500);
        $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
    }
    if (array_sum($quantities) > 500) {
        throw new InvalidArgumentException('Objednávka smí obsahovat nejvýše pět set kusů.');
    }
    ksort($quantities, SORT_NUMERIC);
    return array_map(static fn (int $id, int $quantity): array => ['product_id' => $id, 'quantity' => $quantity], array_keys($quantities), array_values($quantities));
}

/** Integer largest-remainder allocation preserves the total after the legacy rounded discount. */
function barAllocateSaleNet(array $lines, int $gross, int $net): array
{
    if ($gross === 0) {
        return array_map(static fn (array $line): array => $line + ['net_minor' => 0], $lines);
    }
    $remainders = [];
    $allocated = 0;
    foreach ($lines as $index => &$line) {
        $weighted = $line['gross_minor'] * $net;
        $line['net_minor'] = intdiv($weighted, $gross);
        $allocated += $line['net_minor'];
        $remainders[$index] = $weighted % $gross;
    }
    unset($line);
    arsort($remainders, SORT_NUMERIC);
    foreach (array_keys($remainders) as $index) {
        if ($allocated >= $net) {
            break;
        }
        $lines[$index]['net_minor']++;
        $allocated++;
    }
    return $lines;
}

/** Internal projection: purchase prices and component details never cross the public quote boundary. */
function barSaleSnapshot(PDO $pdo, array $cart, int $discount, int $cups, bool $consumption, bool $allowHidden, string $suffix): array
{
    if (!in_array($discount, [0, 20], true) || $cups < 0 || $cups > 200 || ($consumption && ($discount !== 0 || $cups !== 0))
        || ($cart === [] && $cups === 0)) {
        throw new InvalidArgumentException('Vyberte produkty nebo kelímky a platnou slevu.');
    }
    $settings = $pdo->query('SELECT cup_deposit_minor FROM kis_bar_settings WHERE id=1' . $suffix)->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        throw new RuntimeException('Chybí nastavení pokladny.');
    }
    $cupUnit = (int)$settings['cup_deposit_minor'];
    if ($cups > 0 && $cupUnit <= 0) {
        throw new DomainException('Vratné kelímky nemají nastavenou zálohu.');
    }
    $recipes = [];
    $ids = array_column($cart, 'product_id');
    if ($ids !== []) {
        $query = $pdo->prepare('SELECT product_id,ingredient_id,quantity FROM kis_bar_recipes WHERE product_id IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY product_id,ingredient_id' . $suffix);
        $query->execute($ids);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recipes[(int)$row['product_id']][(int)$row['ingredient_id']] = (int)$row['quantity'];
            $ids[] = (int)$row['ingredient_id'];
        }
    }
    $products = [];
    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);
    if ($ids !== []) {
        $query = $pdo->prepare('SELECT * FROM kis_bar_products WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id' . $suffix);
        $query->execute($ids);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $products[(int)$row['id']] = $row;
        }
    }
    $lines = [];
    $deductions = [];
    $commercial = [];
    $gross = 0;
    $offer = $consumption ? null : barActiveOffer($pdo);
    foreach ($cart as $row) {
        $id = $row['product_id'];
        $quantity = $row['quantity'];
        $product = $products[$id] ?? null;
        if ($offer !== null && !in_array($id, $offer['products'], true)) {
            throw new DomainException('Produkt není v aktuálně zveřejněné nabídce.');
        }
        if (!$product || (int)$product['archived'] === 1 || (int)$product['sold_out'] === 1 || (!$allowHidden && (int)$product['on_terminal'] !== 1)) {
            throw new DomainException('Některý z vybraných produktů už není dostupný. Zkontrolujte nabídku.');
        }
        $parts = $recipes[$id] ?? ($product['stock_mode'] === 'sledovat' ? [$id => 1] : []);
        $cost = $product['purchase_minor'] === null ? null : (int)$product['purchase_minor'];
        if (isset($recipes[$id])) {
            $cost = 0;
        }
        foreach ($parts as $componentId => $units) {
            $component = $products[$componentId] ?? null;
            if (!$component || (int)$component['archived'] === 1 || $component['stock_mode'] !== 'sledovat' || isset($recipes[$componentId])) {
                throw new DomainException('Surovina není dostupná pro skladový odpis.');
            }
            $deductions[$componentId] = ($deductions[$componentId] ?? 0) + $units * $quantity;
            if (isset($recipes[$id])) {
                $cost = $cost === null || $component['purchase_minor'] === null ? null : $cost + (int)$component['purchase_minor'] * $units;
            }
        }
        $unit = $consumption ? 0 : (int)$product['price_minor'];
        $lineGross = $unit * $quantity;
        $gross = barCheckMinor($gross + $lineGross);
        $lines[] = ['product_id' => $id, 'product_name' => $product['name'], 'description' => $product['description'],
            'quantity' => $quantity, 'unit_price_minor' => $unit, 'gross_minor' => $lineGross, 'purchase_unit_minor' => $cost];
        $commercial[] = [$id, $quantity, $product['name'], $product['description'], (int)$product['price_minor'], $product['stock_mode'], $parts];
    }
    ksort($deductions, SORT_NUMERIC);
    // The old bar rounds the discounted goods total down to whole tens of CZK. Deposits are never discounted.
    $net = $discount === 20 ? intdiv($gross * 80, 100000) * 1000 : $gross;
    $deposit = barCheckMinor($cups * $cupUnit);
    return ['lines' => barAllocateSaleNet($lines, $gross, $net), 'deductions' => $deductions, 'products' => $products,
        'goods_gross_minor' => $gross, 'discount_percent' => $discount, 'discount_minor' => $gross - $net, 'goods_net_minor' => $net,
        'cup_quantity' => $cups, 'cup_unit_minor' => $cupUnit, 'deposit_minor' => $deposit, 'total_minor' => barCheckMinor($net + $deposit),
        'fingerprint' => hash('sha256', json_encode([$commercial, $discount, $cups, $cupUnit, $consumption, $offer['publication_id'] ?? null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
}

function barQuote(PDO $pdo, array $cart, int $discount = 0, int $cups = 0, bool $consumption = false): array
{
    $cart = barNormalizeCart($cart);
    if ($pdo->inTransaction()) {
        throw new LogicException('Cenový náhled řídí vlastní čtecí transakci.');
    }
    $pdo->beginTransaction();
    try {
        $actor = barActor($pdo, $consumption ? 'own_consumption' : 'sell');
        $snapshot = barSaleSnapshot($pdo, $cart, $discount, $cups, $consumption, $consumption && $actor['position'] === 'bar_admin', '');
        unset($snapshot['products'], $snapshot['deductions']);
        foreach ($snapshot['lines'] as &$line) {
            unset($line['purchase_unit_minor']);
        }
        unset($line);
        $pdo->commit();
        return $snapshot + ['cart' => $cart];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
