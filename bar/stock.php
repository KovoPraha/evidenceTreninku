<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/web.php';
require_once dirname(__DIR__) . '/includes/bar/stock.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
barRequireWebActor($pdo, 'stock_manage');
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !csrf_verify($_POST['csrf_token'])) {
            throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        }
        barStockOperation(
            $pdo,
            barSourceInteger($_POST['product_id'] ?? null, 1, 2147483647),
            barSourceInteger($_POST['version'] ?? null, 1, 2147483647),
            barSourceText($_POST['kind'] ?? null, 40, true),
            barSourceInteger($_POST['quantity'] ?? null),
            barSourceText($_POST['token'] ?? null, 64, true),
            barSourceText($_POST['reason'] ?? null, 1000, true)
        );
        header('Location: ' . appUiUrl('bar/stock.php?product_id=' . (int)$_POST['product_id']), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    }
}
$products = $pdo->query("SELECT p.* FROM kis_bar_products p WHERE p.archived=0 AND p.stock_mode='sledovat' "
    . 'AND NOT EXISTS(SELECT 1 FROM kis_bar_recipes r WHERE r.product_id=p.id) ORDER BY p.name,p.id')->fetchAll(PDO::FETCH_ASSOC);
$selectedId = (int)filter_var($_POST['product_id'] ?? $_GET['product_id'] ?? 0, FILTER_VALIDATE_INT);
$selected = null;
foreach ($products as $product) {
    if ((int)$product['id'] === $selectedId || ($selected === null && $selectedId === 0)) {
        $selected = $product;
    }
}
$history = $pdo->query('SELECT o.id,o.kind,o.reason,o.created_at,p.name,m.location_code,m.quantity_delta,m.quantity_after,m.purchase_minor '
    . 'FROM kis_bar_stock_operations o JOIN kis_bar_stock_movements m ON m.operation_id=o.id '
    . 'JOIN kis_bar_products p ON p.id=o.product_id ORDER BY o.id DESC,m.id LIMIT 40')->fetchAll(PDO::FETCH_ASSOC);
$kinds = ['receive_stall' => 'Přijmout na stánek', 'receive_office' => 'Přijmout do předskladu', 'to_stall' => 'Přesunout z předskladu na stánek',
    'to_office' => 'Vrátit ze stánku do předskladu', 'inventory_stall' => 'Inventura stánku', 'inventory_office' => 'Inventura předskladu'];
?>
<!doctype html>
<html lang="cs">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sklad baru | KIS</title><?php appUiAssets(); ?></head>
<body>
<?php require dirname(__DIR__) . '/hlavicka.php'; ?>
<main class="container py-4">
    <h1>Sklad baru</h1>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= barH($error) ?></div><?php endif; ?>
    <form method="get" class="mb-4">
        <label class="form-label" for="stock-product">Produkt</label>
        <select class="form-select mb-2" name="product_id" id="stock-product">
            <?php foreach ($products as $product): ?>
                <option value="<?= (int)$product['id'] ?>" <?= (int)($selected['id'] ?? 0) === (int)$product['id'] ? 'selected' : '' ?>><?= barH($product['name']) ?></option>
            <?php endforeach; ?>
        </select><button class="btn btn-outline-primary">Zobrazit zásobu</button>
    </form>
    <?php if ($selected !== null): ?>
        <h2 class="h4"><?= barH($selected['name']) ?></h2>
        <p>Stánek: <strong><?= (int)$selected['stock_stall'] ?></strong> · Předsklad: <strong><?= (int)$selected['stock_office'] ?></strong></p>
        <form method="post" class="card card-body mb-4">
            <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$selected['id'] ?>">
            <input type="hidden" name="version" value="<?= (int)$selected['lock_version'] ?>">
            <input type="hidden" name="token" value="<?= bin2hex(random_bytes(32)) ?>">
            <label class="form-label" for="stock-kind">Operace</label>
            <select class="form-select mb-2" name="kind" id="stock-kind">
                <?php foreach ($kinds as $kind => $label): ?><option value="<?= $kind ?>"><?= $label ?></option><?php endforeach; ?>
            </select>
            <label class="form-label" for="stock-quantity">Počet kusů</label>
            <input class="form-control mb-2" type="number" name="quantity" id="stock-quantity" min="0" max="1000000" required>
            <p class="form-text">U inventury zadejte celý skutečně napočítaný stav. U příjmu a přesunu počet přijatých nebo přesouvaných kusů.</p>
            <label class="form-label" for="stock-reason">Důvod nebo podklad</label>
            <input class="form-control mb-2" name="reason" id="stock-reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-primary">Potvrdit skladovou operaci</button>
        </form>
    <?php else: ?><p>Nejsou dostupné žádné jednoduché skladové produkty.</p><?php endif; ?>
    <h2 class="h4">Poslední pohyby</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Čas</th><th>Produkt</th><th>Operace</th><th>Místo</th><th>Změna</th><th>Po operaci</th><th>Podklad</th></tr></thead><tbody>
    <?php foreach ($history as $row): ?>
        <tr><td><?= barH($row['created_at']) ?></td><td><?= barH($row['name']) ?></td><td><?= barH($kinds[$row['kind']] ?? $row['kind']) ?></td>
            <td><?= $row['location_code'] === 'stall' ? 'Stánek' : 'Předsklad' ?></td><td><?= (int)$row['quantity_delta'] ?></td>
            <td><?= (int)$row['quantity_after'] ?></td><td><?= barH($row['reason']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</main></body></html>
