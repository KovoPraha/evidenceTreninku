<?php

declare(strict_types=1);

require_once __DIR__ . '/checkout_web.php';

function barRenderCheckoutPage(PDO $pdo, bool $consumption): void
{
    $actor = barRequireWebActor($pdo, 'sell');
    $error = '';
    $retry = false;
    $quote = null;
    $cart = [];
    $discount = 0;
    $cups = 0;
    $reason = '';
    $location = 'stall';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            barPostCsrf();
            $cart = barPostCart();
            $discount = $consumption ? 0 : barSourceInteger($_POST['discount'] ?? 0, 0, 20);
            $cups = $consumption ? 0 : barSourceInteger($_POST['cups'] ?? 0, 0, 200);
            $location = $consumption ? barPostText('location', 10) : 'stall';
            $reason = $consumption ? barReason(barPostText('reason', 1000)) : '';
            if (($_POST['action'] ?? null) === 'quote') {
                $quote = barQuote($pdo, $cart, $discount, $cups, $consumption);
            } elseif (($_POST['action'] ?? null) === 'commit') {
                $fingerprint = barPostText('fingerprint', 64);
                $token = barPostText('token', 64);
                $result = $consumption ? barSubmitConsumption($pdo, $cart, $location, $fingerprint, $reason, $token)
                    : barSubmitSale(
                        $pdo,
                        barSourceInteger($_POST['cashbox_id'] ?? null, 1, 2147483647),
                        $cart,
                        $discount,
                        $cups,
                        $fingerprint,
                        barPostText('method', 10),
                        $token
                    );
                header('Location: ' . appUiUrl('bar/receipt.php?id=' . $result['sale_id']), true, 303);
                return;
            } else {
                throw new InvalidArgumentException('Neplatná operace pokladny.');
            }
        } catch (DomainException | InvalidArgumentException $exception) {
            http_response_code($exception instanceof BarConflictException ? 409 : 422);
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            http_response_code(503);
            $retry = ($_POST['action'] ?? null) === 'commit';
            $error = $retry ? '' : 'Náhled nelze načíst. Zkuste to znovu.';
        }
    }
    $box = barOwnOpenCashbox($pdo);
    try {
        $products = barCheckoutProducts($pdo, $consumption, $location);
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
        $products = [];
        $quote = null;
    }
    $history = $consumption ? barRecentOwnSales($pdo, true) : [];
    barCheckoutPageStart($pdo, $consumption ? 'Vlastní spotřeba baru' : 'Pokladna baru');
    if ($error !== '') {
        echo '<div class="alert alert-danger" role="alert">' . barH($error) . '</div>';
    }
    if ($retry) {
        barRenderRequestRetry($_POST);
    } elseif (!$consumption && $box === null) {
        echo '<p>Před prodejem <a href="' . barH(appUiUrl('bar/cashbox.php')) . '">otevřete vlastní kasu</a>.</p>';
    } elseif ($quote !== null) {
        barRenderQuoteConfirmation($quote, $consumption, $box, $location, $reason);
    }
    if (!$retry && ($consumption || $box !== null) && $products !== []) {
        barRenderCartForm($products, $cart, $consumption, $actor['position'] === 'bar_admin', $location, $discount, $cups, $reason);
    }
    if ($consumption && $history !== []) {
        echo '<h2 class="h4 mt-4">Moje odběry</h2><ul>';
        foreach ($history as $row) {
            echo '<li><a href="' . barH(appUiUrl('bar/receipt.php?id=' . (int)$row['id'])) . '">Odběr #' . (int)$row['id'] . '</a> · '
                . barH($row['created_at']) . ($row['void_id'] !== null ? ' · stornovaný' : '') . '</li>';
        }
        echo '</ul>';
    }
    barCheckoutPageEnd();
}

function barRenderCartForm(array $products, array $cart, bool $consumption, bool $admin, string $location, int $discount, int $cups, string $reason): void
{
    $quantities = array_column($cart, 'quantity', 'product_id');
    ?>
    <section class="card card-body mt-4">
        <h2 class="h4">Vybrat produkty</h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="quote">
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produkt</th><th>Cena</th><th>Dostupné kusy</th><th>Počet</th></tr></thead><tbody>
            <?php foreach ($products as $product): ?>
                <tr><td>
                    <?php if ($product['image_id'] !== null): ?>
                        <img src="<?= barH(appUiUrl('bar/image.php?id=' . (int)$product['image_id'])) ?>" alt="" width="64" height="64" style="object-fit:contain" loading="lazy">
                    <?php endif; ?>
                    <label for="bar-qty-<?= (int)$product['id'] ?>"><?= barH($product['name']) ?></label><div class="small"><?= barH($product['category']) ?></div></td>
                    <td><?= $consumption ? 'Vlastní spotřeba' : barH(barFormatMoney((int)$product['price_minor'])) ?></td>
                    <td><?= $product['available'] === null ? 'Nesledováno' : (int)$product['available'] ?></td>
                    <td><input class="form-control" style="min-width:5rem;max-width:8rem" type="number" min="0" max="500"
                        id="bar-qty-<?= (int)$product['id'] ?>" name="quantities[<?= (int)$product['id'] ?>]" value="<?= (int)($quantities[(int)$product['id']] ?? 0) ?>"></td></tr>
            <?php endforeach; ?></tbody></table></div>
            <?php if ($consumption): ?>
                <label class="form-label" for="bar-location">Místo odběru</label>
                <select class="form-select mb-2" id="bar-location" name="location">
                    <option value="stall" <?= $location === 'stall' ? 'selected' : '' ?>>Stánek</option>
                    <?php if ($admin): ?><option value="office" <?= $location === 'office' ? 'selected' : '' ?>>Předsklad</option><?php endif; ?>
                </select>
                <label class="form-label" for="bar-consumption-reason">Důvod odběru</label>
                <input class="form-control mb-2" id="bar-consumption-reason" name="reason" value="<?= barH($reason) ?>" minlength="5" maxlength="1000" required>
            <?php else: ?>
                <label class="form-label" for="bar-discount">Sleva na zboží</label>
                <select class="form-select mb-2" name="discount" id="bar-discount">
                    <option value="0">Bez slevy</option><option value="20" <?= $discount === 20 ? 'selected' : '' ?>>Trenér −20 %</option>
                </select>
                <p class="form-text">Při trenérské slevě se cena zboží zaokrouhlí dolů na celé desetikoruny. Zálohy se neslevují.</p>
                <label class="form-label" for="bar-cups">Počet zálohovaných kelímků</label>
                <input class="form-control mb-2" type="number" min="0" max="200" id="bar-cups" name="cups" value="<?= $cups ?>">
            <?php endif; ?>
            <button class="btn btn-primary">Zobrazit souhrn k potvrzení</button>
        </form>
    </section>
    <?php
}

function barRenderQuoteConfirmation(array $quote, bool $consumption, ?array $box, string $location, string $reason): void
{
    ?>
    <section class="card card-body border-primary mt-4">
        <h2 class="h4"><?= $consumption ? 'Potvrdit vlastní spotřebu' : 'Potvrdit objednávku' ?></h2>
        <ul><?php foreach ($quote['lines'] as $line): ?><li><?= (int)$line['quantity'] ?> × <?= barH($line['product_name']) ?>
            — <?= barH(barFormatMoney((int)$line['net_minor'])) ?></li><?php endforeach; ?></ul>
        <?php if (!$consumption): ?>
            <p>Zboží před slevou: <?= barH(barFormatMoney($quote['goods_gross_minor'])) ?> · Sleva: <?= barH(barFormatMoney($quote['discount_minor'])) ?></p>
            <p>Vratné kelímky: <?= (int)$quote['cup_quantity'] ?> ks · Záloha: <?= barH(barFormatMoney($quote['deposit_minor'])) ?></p>
        <?php else: ?><p>Místo: <?= $location === 'office' ? 'Předsklad' : 'Stánek' ?> · <?= barH($reason) ?></p><?php endif; ?>
        <p class="fs-4">Celkem: <strong><?= barH(barFormatMoney($quote['total_minor'])) ?></strong></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="commit">
            <input type="hidden" name="token" value="<?= bin2hex(random_bytes(32)) ?>">
            <input type="hidden" name="fingerprint" value="<?= barH($quote['fingerprint']) ?>">
            <input type="hidden" name="cashbox_id" value="<?= (int)($box['id'] ?? 0) ?>">
            <input type="hidden" name="discount" value="<?= (int)$quote['discount_percent'] ?>">
            <input type="hidden" name="cups" value="<?= (int)$quote['cup_quantity'] ?>">
            <input type="hidden" name="location" value="<?= barH($location) ?>"><input type="hidden" name="reason" value="<?= barH($reason) ?>">
            <?php foreach ($quote['cart'] as $row): ?><input type="hidden" name="quantities[<?= (int)$row['product_id'] ?>]" value="<?= (int)$row['quantity'] ?>"><?php endforeach; ?>
            <?php if (!$consumption && $quote['total_minor'] === 0): ?>
                <input type="hidden" name="method" value="cash"><p>Objednávka nevyžaduje úhradu.</p>
            <?php elseif (!$consumption): ?>
                <label class="form-label" for="bar-payment">Způsob platby</label>
                <select class="form-select mb-2" id="bar-payment" name="method">
                    <option value="cash">Hotovost přijatá do kasy</option><option value="card">Karta – čeká na potvrzení</option><option value="qr">QR převod – čeká na potvrzení</option>
                </select>
                <p>U karty a QR následně potvrďte skutečně přijatou platbu. Čekající objednávka již blokuje zásobu.</p>
            <?php endif; ?>
            <button class="btn btn-success"><?= $consumption ? 'Potvrdit odběr' : 'Uložit objednávku' ?></button>
        </form>
    </section>
    <?php
}
