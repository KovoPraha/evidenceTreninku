<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/shop_storefront.php';
require_once dirname(__DIR__) . '/includes/club_program.php';
require_once dirname(__DIR__) . '/includes/shop_guest_checkout.php';
require_once dirname(__DIR__) . '/includes/auth_rate_limit.php';

function guestCheckoutH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function guestCheckoutMoney(int $minor, string $currency): string
{
    return number_format($minor / 100, 2, ',', ' ') . ' ' . guestCheckoutH($currency);
}

$productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$variantId = (int)($_POST['variant_id'] ?? $_GET['variant_id'] ?? 0);
$product = shopStorefrontProductDetail($pdo, $productId);
$variant = null;
if ($product !== null) {
    foreach ($product['variants'] as $candidate) {
        if ((int)$candidate['variant_id'] === $variantId && !clubProgramVariantHasOfferLink($pdo, $variantId)) {
            $variant = $candidate;
            break;
        }
    }
}
if ($product === null || $variant === null || !$variant['in_stock']) {
    http_response_code(404);
    exit('Položka není dostupná pro rychlý nákup.');
}

$checkoutKeyName = 'shop_guest_checkout_' . $variantId;
if (!isset($_SESSION[$checkoutKeyName]) || !is_array($_SESSION[$checkoutKeyName])) {
    $_SESSION[$checkoutKeyName] = [
        'idempotency_key' => bin2hex(random_bytes(16)),
        'access_token' => bin2hex(random_bytes(32)),
    ];
}
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (!auth_rate_limit_reserve_attempt($pdo, 'guest_checkout', $email, auth_rate_limit_request_ip())) {
                throw new ShopCheckoutException('Příliš mnoho pokusů. Zkuste nákup dokončit později.');
            }
            $keys = $_SESSION[$checkoutKeyName];
            $order = shopGuestCheckoutPlace(
                $pdo,
                $variantId,
                (int)($_POST['quantity'] ?? 1),
                $_POST,
                (string)$keys['idempotency_key'],
                (string)$keys['access_token'],
                shopBankSettingsEffective($pdo)
            );
            unset($_SESSION[$checkoutKeyName]);
            header('Location: objednavka.php?code=' . rawurlencode((string)$order['public_code']) . '&access=' . rawurlencode((string)$keys['access_token']), true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('booking/rychly_nakup.php: ' . get_class($exception));
            $errors[] = 'Nákup se nepodařilo bezpečně dokončit. Zkuste to znovu.';
        } catch (InvalidArgumentException | ShopCheckoutException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
$attributes = [];
foreach (($variant['attributes_detail'] ?? []) as $attribute) {
    $attributes[] = trim((string)$attribute['display_name']) . ': ' . trim((string)$attribute['formatted_value']);
}
$variantLabel = $attributes !== [] ? implode(' · ', $attributes) : (string)$variant['sku'];
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Rychlý nákup – <?=guestCheckoutH($product['public_name'])?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav('shop'); ?><main class="container py-4" style="max-width:850px">
<a class="btn btn-sm btn-outline-secondary mb-3" href="produkt.php?id=<?=$productId?>">← Zpět na produkt</a>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-uppercase text-muted fw-semibold mb-2">Nákup bez registrace</div><h1 class="h4"><?=guestCheckoutH($product['public_name'])?></h1><p class="mb-2"><?=guestCheckoutH($variantLabel)?></p><div class="h5 text-primary mb-3"><?=guestCheckoutMoney((int)$variant['amount_minor'],(string)$variant['currency'])?></div><div class="alert alert-info small mb-0">Zboží se nyní vydává osobně. Účet ani heslo nepotřebujete; po objednání dostanete platební údaje a bezpečný odkaz e-mailem.</div></div></div></div>
<div class="col-lg-7"><?php foreach($errors as$error):?><div class="alert alert-danger"><?=guestCheckoutH($error)?></div><?php endforeach;?><form method="post" class="card border-0 shadow-sm"><div class="card-body p-4"><?=csrf_field()?><input type="hidden" name="product_id" value="<?=$productId?>"><input type="hidden" name="variant_id" value="<?=$variantId?>">
<h2 class="h5 mb-3">Kontaktní údaje</h2><div class="row g-3"><div class="col-sm-6"><label class="form-label" for="guest-first-name">Jméno</label><input id="guest-first-name" name="first_name" class="form-control" maxlength="100" value="<?=guestCheckoutH($_POST['first_name']??'')?>" autocomplete="given-name" required></div><div class="col-sm-6"><label class="form-label" for="guest-last-name">Příjmení</label><input id="guest-last-name" name="last_name" class="form-control" maxlength="100" value="<?=guestCheckoutH($_POST['last_name']??'')?>" autocomplete="family-name" required></div><div class="col-12"><label class="form-label" for="guest-email">E-mail</label><input id="guest-email" type="email" name="email" class="form-control" maxlength="254" value="<?=guestCheckoutH($_POST['email']??'')?>" autocomplete="email" required><div class="form-text">Na tuto adresu přijde odkaz na objednávku a platební údaje.</div></div><div class="col-sm-8"><label class="form-label" for="guest-phone">Telefon <span class="text-muted">(nepovinný)</span></label><input id="guest-phone" type="tel" name="phone" class="form-control" maxlength="50" value="<?=guestCheckoutH($_POST['phone']??'')?>" autocomplete="tel"></div><div class="col-sm-4"><label class="form-label" for="guest-quantity">Množství</label><input id="guest-quantity" type="number" name="quantity" class="form-control" min="1" max="99" value="<?=max(1,min(99,(int)($_POST['quantity']??1)))?>" required></div></div>
<details class="mt-4"><summary class="fw-semibold">Fakturační adresa <span class="text-muted fw-normal">(nepovinná)</span></summary><p class="small text-muted mt-2">Pro osobní odběr ji nyní nepotřebujeme. Vyplňte ji jen pokud ji chcete uvést u objednávky.</p><div class="row g-3"><div class="col-12"><label class="form-label" for="guest-street">Ulice a číslo</label><input id="guest-street" name="address_street" class="form-control" maxlength="200" value="<?=guestCheckoutH($_POST['address_street']??'')?>" autocomplete="street-address"></div><div class="col-sm-8"><label class="form-label" for="guest-city">Obec</label><input id="guest-city" name="address_city" class="form-control" maxlength="100" value="<?=guestCheckoutH($_POST['address_city']??'')?>" autocomplete="address-level2"></div><div class="col-sm-4"><label class="form-label" for="guest-postcode">PSČ</label><input id="guest-postcode" name="address_postcode" class="form-control" maxlength="20" value="<?=guestCheckoutH($_POST['address_postcode']??'')?>" autocomplete="postal-code"></div></div></details>
<div class="form-check mt-4"><input id="guest-confirm" class="form-check-input" type="checkbox" required><label class="form-check-label small" for="guest-confirm">Potvrzuji správnost údajů a objednávám zboží s osobním odběrem a platbou bankovním převodem.</label></div><button class="btn btn-primary btn-lg w-100 mt-3">Objednat a zobrazit platbu</button><div class="small text-muted text-center mt-2">Bez zakládání účtu. Cena a sklad se před objednáním znovu ověří.</div>
</div></form></div></div></main><?php publicShellFooter(); ?></body></html>
