<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/checkout_web.php';
require_once dirname(__DIR__) . '/includes/bar/cashbox_view.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
barRequireWebActor($pdo, 'sell');
$error = '';
$retry = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        barPostCsrf();
        $action = barPostText('action', 30);
        $token = barPostText('token', 64);
        $id = barSourceInteger($_POST['cashbox_id'] ?? 0, 0, 2147483647);
        if ($action === 'open') {
            $result = barOpenCashbox($pdo, barPostMinor('opening'), barPostText('reason', 1000), $token);
        } elseif ($action === 'adjust') {
            $direction = barPostText('direction', 5);
            if (!in_array($direction, ['in', 'out'], true)) {
                throw new InvalidArgumentException('Vyberte vklad nebo výběr.');
            }
            $result = barAdjustCash($pdo, $id, barPostMinor('amount') * ($direction === 'out' ? -1 : 1), barPostText('reason', 1000), $token);
        } elseif ($action === 'close') {
            $result = barCloseCashbox(
                $pdo,
                $id,
                barSourceInteger($_POST['version'] ?? null, 1, 2147483647),
                barPostMinor('counted'),
                barPostText('reason', 1000),
                $token
            );
        } elseif ($action === 'confirm') {
            $result = barConfirmPayment($pdo, barSourceInteger($_POST['sale_id'] ?? null, 1, 2147483647), barPostText('reference', 255), $token);
        } elseif ($action === 'void') {
            if (($_POST['goods_returned'] ?? null) !== '1') {
                throw new InvalidArgumentException('Potvrďte vrácení zboží nebo zrušení nevydaného odběru.');
            }
            $result = barVoidSale(
                $pdo,
                barSourceInteger($_POST['sale_id'] ?? null, 1, 2147483647),
                $id === 0 ? null : $id,
                barPostText('reference', 255, false),
                barPostText('reason', 1000),
                $token,
                barSourceInteger($_POST['expected_refund_minor'] ?? null, 0, BAR_CHECKOUT_MAX_MINOR)
            );
        } elseif ($action === 'cups') {
            $result = barReturnCups(
                $pdo,
                barSourceInteger($_POST['sale_id'] ?? null, 1, 2147483647),
                $id,
                barSourceInteger($_POST['quantity'] ?? null, 1, 200),
                barPostText('reason', 1000),
                $token
            );
        } else {
            throw new InvalidArgumentException('Neplatná operace kasy.');
        }
        $target = in_array($action, ['confirm', 'void'], true) ? 'bar/receipt.php?id=' . $result['sale_id']
            : 'bar/cashbox.php' . (isset($result['cashbox_id']) ? '?id=' . $result['cashbox_id'] : '');
        header('Location: ' . appUiUrl($target), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        http_response_code(503);
        $retry = true;
    }
}
$selectedId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try {
    $data = barCashboxView($pdo, $selectedId === false ? null : $selectedId);
} catch (DomainException $exception) {
    http_response_code(403);
    exit(barH($exception->getMessage()));
}
barCheckoutPageStart($pdo, 'Moje kasa');
if ($error !== '') {
    echo '<div class="alert alert-danger" role="alert">' . barH($error) . '</div>';
}
if ($retry) {
    barRenderRequestRetry($_POST);
} else {
    barRenderCashbox($data);
}
barCheckoutPageEnd();
