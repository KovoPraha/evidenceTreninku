<?php

declare(strict_types=1);

require_once __DIR__ . '/web.php';
require_once __DIR__ . '/checkout_read.php';

function barCheckoutPageStart(PDO $pdo, string $title): void
{
    ?>
<!doctype html><html lang="cs"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= barH($title) ?> | KIS</title><?php appUiAssets(); ?>
</head><body>
<?php require dirname(__DIR__, 2) . '/hlavicka.php'; ?>
<main class="container py-4"><h1><?= barH($title) ?></h1>
    <?php if (in_array($_SESSION['staff_active_position'] ?? '', ['bar_operator', 'bar_admin'], true)): ?>
    <p><a href="<?= barH(appUiUrl('bar/checkout.php')) ?>">Pokladna</a> ·
        <a href="<?= barH(appUiUrl('bar/cashbox.php')) ?>">Moje kasa</a> ·
        <a href="<?= barH(appUiUrl('bar/consumption.php')) ?>">Vlastní spotřeba</a></p>
    <?php else: ?>
    <p><a href="<?= barH(appUiUrl('bar/invoices.php')) ?>">Faktury baru</a> ·
        <a href="<?= barH(appUiUrl('bar/accounting.php')) ?>">Účetní podklady baru</a></p>
    <?php endif; ?>
    <?php
}

function barCheckoutPageEnd(): void
{
    echo '</main></body></html>';
}

function barPostCsrf(): void
{
    if (!is_string($_POST['csrf_token'] ?? null) || !csrf_verify($_POST['csrf_token'])) {
        throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
    }
}

function barPostText(string $key, int $length, bool $required = true): string
{
    return barSourceText($_POST[$key] ?? '', $length, $required);
}

function barPostMinor(string $key): int
{
    return barCheckMinor(barSourceMoney(barPriceInput($_POST[$key] ?? null)));
}

function barPostCart(): array
{
    $quantities = $_POST['quantities'] ?? [];
    if (!is_array($quantities) || count($quantities) > 10000) {
        throw new InvalidArgumentException('Neplatný seznam produktů.');
    }
    $cart = [];
    foreach ($quantities as $id => $value) {
        $quantity = barSourceInteger($value, 0, 500);
        if ($quantity > 0) {
            $cart[] = ['product_id' => barSourceInteger($id, 1, 2147483647), 'quantity' => $quantity];
        }
    }
    return barNormalizeCart($cart);
}

function barPaymentLabel(?array $payment): string
{
    if ($payment === null) {
        return 'Bez platby – vlastní spotřeba';
    }
    if (($payment['verification_kind'] ?? null) === 'zero_amount') {
        return 'Bez úhrady – nulová částka';
    }
    $method = ['cash' => 'Hotovost', 'card' => 'Karta', 'qr' => 'QR převod'][$payment['method']] ?? 'Platba';
    return $method . match ($payment['status']) {
        'pending' => ' – čeká na potvrzení', 'cancelled' => ' – zrušená',
        default => ($payment['verification_kind'] ?? null) === 'manual' ? ' – ručně potvrzená' : ' – potvrzená',
    };
}

/** Keep the original token and body on an uncertain DB outcome. Never generate a replacement sale. */
function barRenderRequestRetry(array $payload): void
{
    $fields = ['action', 'token', 'cashbox_id', 'sale_id', 'version', 'discount', 'cups', 'fingerprint', 'method', 'location', 'reason',
        'opening', 'counted', 'amount', 'direction', 'reference', 'quantity', 'goods_returned', 'expected_refund_minor'];
    ?>
    <div class="alert alert-warning" role="alert">Výsledek operace se nepodařilo ověřit. Zopakujte stejný požadavek; již uložená operace se neprovede podruhé.</div>
    <form method="post" class="mb-4"><?= csrf_field() ?>
        <?php foreach ($fields as $field): if (is_string($payload[$field] ?? null)): ?>
            <input type="hidden" name="<?= $field ?>" value="<?= barH($payload[$field]) ?>">
        <?php endif; endforeach; ?>
        <?php foreach (is_array($payload['quantities'] ?? null) ? $payload['quantities'] : [] as $id => $quantity): ?>
            <?php if (ctype_digit((string)$id) && is_string($quantity)): ?>
                <input type="hidden" name="quantities[<?= barH($id) ?>]" value="<?= barH($quantity) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <button class="btn btn-primary">Zopakovat stejný požadavek</button>
    </form>
    <?php
}

function barCheckoutProducts(PDO $pdo, bool $consumption, string $location): array
{
    $actor = barActor($pdo, $consumption ? 'own_consumption' : 'sell');
    if (!in_array($location, ['stall', 'office'], true) || ($location === 'office' && (!$consumption || $actor['position'] !== 'bar_admin'))) {
        throw new DomainException('Toto skladové místo není dostupné.');
    }
    $column = $location === 'office' ? 'stock_office' : 'stock_stall';
    $stock = [];
    $query = $pdo->query('SELECT id,' . $column . ' AS quantity,stock_mode,archived FROM kis_bar_products');
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stock[(int)$row['id']] = $row;
    }
    $recipes = [];
    foreach ($pdo->query('SELECT product_id,ingredient_id,quantity FROM kis_bar_recipes ORDER BY product_id,ingredient_id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $part = $stock[(int)$row['ingredient_id']] ?? null;
        $available = !$part || (int)$part['archived'] === 1 || $part['stock_mode'] !== 'sledovat' ? 0 : intdiv((int)$part['quantity'], (int)$row['quantity']);
        $id = (int)$row['product_id'];
        $recipes[$id] = min($recipes[$id] ?? 1000000, $available);
    }
    $hidden = $consumption && $actor['position'] === 'bar_admin' ? '' : ' AND p.on_terminal=1';
    $rows = $pdo->query('SELECT p.id,p.name,p.description,p.price_minor,p.stock_mode,p.sold_out,c.name AS category,a.id AS image_id FROM kis_bar_products p '
        . 'JOIN kis_bar_categories c ON c.id=p.category_id LEFT JOIN kis_bar_assets a ON a.source_name=p.image_name '
        . 'WHERE p.archived=0' . $hidden . ' ORDER BY c.sort_order,c.id,p.sort_order,p.id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $id = (int)$row['id'];
        $row['available'] = $recipes[$id] ?? ($row['stock_mode'] === 'sledovat' ? (int)$stock[$id]['quantity'] : null);
        if ((int)$row['sold_out'] === 1) {
            $row['available'] = 0;
        }
    }
    unset($row);
    return $consumption ? $rows : barFilterPublishedProducts($pdo, $rows);
}
