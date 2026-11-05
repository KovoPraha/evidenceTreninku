<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/checkout_web.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
barRequireWebActor($pdo, 'catalog_manage');
$error = '';
$retry = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        barPostCsrf();
        barSetCupDeposit($pdo, barPostMinor('amount'), barSourceInteger($_POST['version'] ?? null, 1, 2147483647), barPostText('reason', 1000), barPostText('token', 64));
        header('Location: ' . appUiUrl('bar/settings.php'), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        http_response_code(503);
        $retry = true;
    }
}
$settings = $pdo->query('SELECT cup_deposit_minor,lock_version FROM kis_bar_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
barCheckoutPageStart($pdo, 'Nastavení baru');
if ($error !== '') {
    echo '<div class="alert alert-danger" role="alert">' . barH($error) . '</div>';
}
if ($retry) {
    barRenderRequestRetry($_POST);
} else {
    ?>
    <section class="card card-body"><h2 class="h4">Záloha za vratný kelímek</h2>
        <p>Nula vypíná nové zálohované kelímky. Starší vratky vždy používají sazbu z původního dokladu.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= bin2hex(random_bytes(32)) ?>">
            <input type="hidden" name="version" value="<?= (int)$settings['lock_version'] ?>">
            <label class="form-label" for="bar-deposit">Záloha za kus v Kč</label>
            <input class="form-control mb-2" id="bar-deposit" name="amount" value="<?= barH(barMinorDecimal((int)$settings['cup_deposit_minor'])) ?>" inputmode="decimal" required>
            <label class="form-label" for="bar-setting-reason">Důvod změny</label>
            <input class="form-control mb-2" id="bar-setting-reason" name="reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-primary">Uložit zálohu</button>
        </form>
    </section>
    <?php
}
barCheckoutPageEnd();
