<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/venue_operations.php';
require_once __DIR__ . '/includes/shop_payment_policy.php';
require_once __DIR__ . '/includes/sumup_gateway.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function paymentMethodsAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
$actorId = (int)$_SESSION['trener_id'];
$migrated = shopPaymentPolicyColumnExists($pdo,'shop_products','payment_method_policy')
    && shopPaymentPolicyColumnExists($pdo,'individualni_lekce','payment_method_policy')
    && shopPaymentPolicyColumnExists($pdo,'payments','accepted_payment_methods');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'set_product') {
                $result = shopPaymentPolicySetProduct(
                    $pdo,$actorId,(int)($_POST['product_id'] ?? 0),(string)($_POST['payment_method_policy'] ?? ''),
                    (string)($_POST['reason'] ?? ''),($_POST['confirm_action'] ?? '') === '1'
                );
                $message = $result['changed'] ? 'Platební režim nabídky byl změněn.' : 'Nabídka už tento platební režim používá.';
            } elseif ($action === 'set_velodrome') {
                $result = shopPaymentPolicySetVelodromeSlot(
                    $pdo,$actorId,(int)($_POST['lesson_id'] ?? 0),(string)($_POST['payment_method_policy'] ?? ''),
                    (string)($_POST['reason'] ?? ''),($_POST['confirm_action'] ?? '') === '1'
                );
                $message = $result['changed'] ? 'Platební režim termínu byl změněn.' : 'Termín už tento platební režim používá.';
            } else {
                throw new InvalidArgumentException('Neznámá změna platebního režimu.');
            }
            $_SESSION['flash_payment_methods'] = $message;
            header('Location: eshop_payment_methods_admin.php', true, 303);
            exit;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_payment_methods'] ?? '');
unset($_SESSION['flash_payment_methods']);
$products = $migrated ? shopPaymentPolicyProducts($pdo) : [];
$velodromeSlots = $migrated ? shopPaymentPolicyVelodromeSlots($pdo) : [];
$sumupEnabled = sumupIsEnabled();
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Způsoby platby</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
</head>
<body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container-fluid py-4" style="max-width:1450px">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h3 mb-1"><i class="bi bi-credit-card me-2 text-primary"></i>Způsoby platby</h1><p class="text-muted mb-0">Určuje, které nové objednávky mohou zákazníci zaplatit kartou přes SumUp.</p></div>
        <a class="btn btn-outline-secondary btn-sm" href="pracovni_pozice.php">Finanční rozcestník</a>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=paymentMethodsAdminH($error)?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?=paymentMethodsAdminH($success)?></div><?php endif; ?>
    <?php if (!$migrated): ?><div class="alert alert-danger">Databázová migrace platebních metod ještě není dokončena. Nastavení je bezpečně nedostupné.</div><?php endif; ?>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Stav platební brány</div>
        <div class="card-body">
            <span class="badge text-bg-<?=$sumupEnabled?'success':'secondary'?>">SumUp <?=$sumupEnabled?'je technicky zapnutý':'není zapnutý'?></span>
            <p class="small text-muted mt-2 mb-0">Bankovní převod, QR kód a platební doklad zůstávají dostupné vždy. Karta se nabídne jen u objednávky, jejíž všechny položky mají režim „QR / převod + karta přes SumUp“. Smíšený košík se bezpečně omezí pouze na převod.</p>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Produkty, kroužky, tábory a klubové akce</div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nabídka</th><th>Stav</th><th style="min-width:600px">Platby nových objednávek</th></tr></thead><tbody>
        <?php foreach ($products as $product): ?><tr><td><strong><?=paymentMethodsAdminH($product['public_name'] ?: $product['name'])?></strong><div class="small text-muted"><?=paymentMethodsAdminH($product['offer_type'])?> · produkt #<?=(int)$product['id']?></div></td><td><?=paymentMethodsAdminH($product['catalog_status'])?><?=($product['publication_status']??null)==='active'?' · zveřejněno':''?></td><td><form method="post" class="row g-2 align-items-end"><?=csrf_field()?><input type="hidden" name="action" value="set_product"><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><div class="col-lg-5"><label class="form-label small">Přijímat</label><select class="form-select form-select-sm" name="payment_method_policy"><option value="bank_transfer" <?=$product['payment_method_policy']===SHOP_PAYMENT_POLICY_BANK_ONLY?'selected':''?>>Pouze QR / bankovní převod</option><option value="bank_transfer_sumup" <?=$product['payment_method_policy']===SHOP_PAYMENT_POLICY_SUMUP_AND_BANK?'selected':''?>>QR / převod + karta přes SumUp</option></select></div><div class="col-lg-4"><label class="form-label small">Důvod změny</label><input class="form-control form-control-sm" name="reason" maxlength="1000" required></div><div class="col-lg-3"><label class="form-check small"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Potvrzuji pro nové objednávky</label><button class="btn btn-primary btn-sm w-100">Uložit</button></div></form></td></tr><?php endforeach; ?>
        <?php if ($products === []): ?><tr><td colspan="3" class="text-center text-muted py-4">Žádné nastavitelné nabídky.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Placené termíny velodromu</div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Termín</th><th>Částka</th><th style="min-width:600px">Platby nových objednávek</th></tr></thead><tbody>
        <?php foreach ($velodromeSlots as $slot): ?><tr><td><strong><?=paymentMethodsAdminH($slot['nazev'])?></strong><div class="small text-muted"><?=paymentMethodsAdminH($slot['datum'].' '.substr((string)$slot['cas_od'],0,5).'–'.substr((string)$slot['cas_do'],0,5))?> · <?=paymentMethodsAdminH($slot['stav'])?></div></td><td><?=number_format((float)$slot['cena_kc'],2,',',' ')?> Kč</td><td><form method="post" class="row g-2 align-items-end"><?=csrf_field()?><input type="hidden" name="action" value="set_velodrome"><input type="hidden" name="lesson_id" value="<?=(int)$slot['id']?>"><div class="col-lg-5"><label class="form-label small">Přijímat</label><select class="form-select form-select-sm" name="payment_method_policy"><option value="bank_transfer" <?=$slot['payment_method_policy']===SHOP_PAYMENT_POLICY_BANK_ONLY?'selected':''?>>Pouze QR / bankovní převod</option><option value="bank_transfer_sumup" <?=$slot['payment_method_policy']===SHOP_PAYMENT_POLICY_SUMUP_AND_BANK?'selected':''?>>QR / převod + karta přes SumUp</option></select></div><div class="col-lg-4"><label class="form-label small">Důvod změny</label><input class="form-control form-control-sm" name="reason" maxlength="1000" required></div><div class="col-lg-3"><label class="form-check small"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Potvrzuji pro nové objednávky</label><button class="btn btn-primary btn-sm w-100">Uložit</button></div></form></td></tr><?php endforeach; ?>
        <?php if ($velodromeSlots === []): ?><tr><td colspan="3" class="text-center text-muted py-4">Žádné budoucí placené termíny.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main></body></html>
