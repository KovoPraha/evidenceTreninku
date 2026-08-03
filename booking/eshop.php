<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) { header('Location: prihlaseni.php?redirect=eshop.php');exit; }
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/shop_checkout.php';
require_once dirname(__DIR__) . '/includes/family_portal.php';
require_once dirname(__DIR__) . '/includes/club_program.php';

function shopPublicH(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function shopPublicMoney(int $minor, string $currency = 'CZK'): string { return number_format($minor / 100, 2, ',', ' ') . ' ' . shopPublicH($currency); }

$accountId = (int)$_SESSION['verejny_uzivatel_id'];$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) $errors[] = 'Formulář vypršel. Obnovte stránku.';
    else try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $variantId = (int)($_POST['variant_id'] ?? 0);$offer = clubProgramOfferForVariant($pdo,$variantId);
            if ($offer && !clubProgramOfferIsOnSale($offer)) throw new ClubProgramException('Prodej tohoto období ještě nezačal nebo už skončil.');
            $detail = shopCartDetail($pdo,$accountId);$current = 0;
            foreach ($detail['items'] as $item) if ((int)$item['variant_id'] === $variantId) $current = (int)$item['quantity'];
            shopCartSetQuantity($pdo,$accountId,$variantId,$offer ? 1 : min(99,$current + 1));
            $_SESSION['flash_shop'] = $offer ? 'Období kroužku bylo přidáno. V košíku vyberte dítě.' : 'Položka byla přidána do košíku.';
        } elseif ($action === 'quantity') {
            $variantId = (int)($_POST['variant_id'] ?? 0);$quantity = (int)($_POST['quantity'] ?? 0);
            if (clubProgramOfferForVariant($pdo,$variantId) && !in_array($quantity,[0,1],true)) throw new ClubProgramException('Období kroužku lze koupit pouze jednou pro jedno dítě.');
            shopCartSetQuantity($pdo,$accountId,$variantId,$quantity);$_SESSION['flash_shop'] = 'Košík byl upraven.';
        } elseif ($action === 'beneficiary') {
            $cartItemId = (int)($_POST['cart_item_id'] ?? 0);$sportovecId = (int)($_POST['sportovec_id'] ?? 0);$matched = false;
            foreach (shopCartDetail($pdo,$accountId)['items'] as $item) if ((int)$item['cart_item_id'] === $cartItemId && clubProgramOfferForVariant($pdo,(int)$item['variant_id'])) $matched = true;
            if (!$matched || $sportovecId < 1) throw new ClubProgramException('Položka programu nebo příjemce nebyli nalezeni.');
            shopCartSetBeneficiary($pdo,$accountId,$cartItemId,$sportovecId);$_SESSION['flash_shop'] = 'Dítě pro kroužek bylo bezpečně vybráno.';
        } elseif ($action === 'apply_coupon') {
            $coupon = shopCouponApplyToCart($pdo,$accountId,(string)($_POST['coupon_code'] ?? ''));$_SESSION['flash_shop'] = 'Kupón ' . $coupon['code'] . ' byl použit.';
        } elseif ($action === 'remove_coupon') {
            shopCouponRemoveFromCart($pdo,$accountId);$_SESSION['flash_shop'] = 'Kupón byl z košíku odebrán.';
        } elseif ($action === 'checkout') {
            foreach (shopCartDetail($pdo,$accountId)['items'] as $item) { $offer=clubProgramOfferForVariant($pdo,(int)$item['variant_id']);if ($offer && (!clubProgramOfferIsOnSale($offer) || $item['beneficiary_sportovec_id'] === null)) throw new ClubProgramException('Období kroužku už není v prodeji nebo u něj není vybrané dítě.'); }
            $key = (string)($_POST['checkout_key'] ?? '');
            if (!isset($_SESSION['shop_checkout_key']) || !hash_equals((string)$_SESSION['shop_checkout_key'],$key)) throw new ShopCheckoutException('Checkout klíč vypršel. Obnovte stránku.');
            $order = shopCheckoutPlace($pdo,$accountId,$key,shopBankSettingsFromConfig(),(string)($_POST['cart_fingerprint'] ?? ''));unset($_SESSION['shop_checkout_key']);
            header('Location: objednavka.php?code=' . rawurlencode((string)$order['public_code']),true,303);exit;
        } else throw new InvalidArgumentException('Neplatná akce.');
        header('Location: eshop.php',true,303);exit;
    } catch (PDOException $exception) { error_log('booking/eshop.php: ' . $exception->getMessage());$errors[] = 'Databázová operace selhala bez částečného zápisu.'; }
    catch (InvalidArgumentException|ShopCheckoutException|ShopCouponException|ClubProgramException $exception) { $errors[] = $exception->getMessage(); }
}
$success = (string)($_SESSION['flash_shop'] ?? '');unset($_SESSION['flash_shop']);
$products = array_values(array_filter(shopStorefrontProducts($pdo),static function(array $product)use($pdo):bool{$offer=clubProgramOfferForVariant($pdo,(int)$product['variant_id']);if (!$offer && clubProgramProductHasActiveOffer($pdo,(int)$product['product_id'])) return false;return !$offer||clubProgramOfferIsOnSale($offer);}));$cart = shopCartDetail($pdo,$accountId);$people = familyPortalAuthorizedPeople($pdo,$accountId);
if (!isset($_SESSION['shop_checkout_key']) || preg_match('/^[a-f0-9]{32}$/D',(string)$_SESSION['shop_checkout_key']) !== 1) $_SESSION['shop_checkout_key'] = bin2hex(random_bytes(16));
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Klubový e-shop</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-0">Klubový e-shop</h1><div class="text-muted">Zboží a klubové služby na jednom místě.</div></div><div class="d-flex gap-2"><a href="moje_programy.php" class="btn btn-outline-primary btn-sm">Moje kroužky</a><a href="moje_objednavky.php" class="btn btn-outline-success btn-sm">Moje objednávky</a><a href="krouzky.php" class="btn btn-outline-secondary btn-sm">Události</a></div></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=shopPublicH($error)?></div><?php endforeach;?><?php if ($success !== ''): ?><div class="alert alert-success"><?=shopPublicH($success)?></div><?php endif;?>
<div class="row g-4"><section class="col-lg-8"><h2 class="h5">Aktivní nabídka</h2><div class="row g-3">
<?php foreach ($products as $product): $offer = clubProgramOfferForVariant($pdo,(int)$product['variant_id']); ?><div class="col-md-6"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><h3 class="h6"><?=shopPublicH($product['public_name'])?></h3><?php if ($offer): ?><span class="badge text-bg-primary">kroužek</span><?php endif;?></div><p class="small text-muted"><?=nl2br(shopPublicH($product['public_summary']))?></p><?php if ($offer): ?><div class="small mb-2"><strong><?=shopPublicH($offer['name'])?></strong><br><?=shopPublicH($offer['starts_on'])?> – <?=shopPublicH($offer['ends_on'])?></div><?php endif;?><div class="small"><code><?=shopPublicH($product['sku'])?></code></div><div class="d-flex justify-content-between align-items-center mt-3"><strong><?=shopPublicMoney((int)$product['amount_minor'],(string)$product['currency'])?></strong><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="add"><input type="hidden" name="variant_id" value="<?=(int)$product['variant_id']?>"><button class="btn btn-sm btn-primary">Přidat</button></form></div></div></div></div><?php endforeach;?>
<?php if ($products === []): ?><div class="col-12"><div class="alert alert-light border">Zatím není aktivní žádná nabídka.</div></div><?php endif;?></div></section>
<aside class="col-lg-4"><div class="card border-0 shadow-sm sticky-top" style="top:1rem"><div class="card-header bg-white fw-semibold">Košík</div><div class="card-body">
<?php foreach ($cart['items'] as $item): $offer = clubProgramOfferForVariant($pdo,(int)$item['variant_id']); ?><div class="border-bottom pb-3 mb-3"><strong><?=shopPublicH($item['public_name'])?></strong><div class="small text-muted"><?=shopPublicH($item['sku'])?></div><form method="post" class="d-flex gap-2 align-items-center mt-1"><?=csrf_field()?><input type="hidden" name="action" value="quantity"><input type="hidden" name="variant_id" value="<?=(int)$item['variant_id']?>"><input class="form-control form-control-sm" style="width:75px" type="number" name="quantity" min="0" max="<?=$offer?'1':'99'?>" value="<?=(int)$item['quantity']?>"><button class="btn btn-sm btn-outline-secondary">Uložit</button><span class="ms-auto"><?=shopPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></span></form>
<?php if ($offer): ?><form method="post" class="mt-2"><?=csrf_field()?><input type="hidden" name="action" value="beneficiary"><input type="hidden" name="cart_item_id" value="<?=(int)$item['cart_item_id']?>"><label class="form-label small mb-1">Dítě / účastník</label><div class="input-group input-group-sm"><select class="form-select" name="sportovec_id" required><option value="">Vyberte</option><?php foreach ($people as $person): ?><option value="<?=(int)$person['sportovec_id']?>" <?=(int)($item['beneficiary_sportovec_id']??0)===(int)$person['sportovec_id']?'selected':''?>><?=shopPublicH($person['prijmeni'].' '.$person['jmeno'])?></option><?php endforeach;?></select><button class="btn btn-outline-primary">Potvrdit</button></div></form><?php if ($people === []): ?><div class="small text-danger mt-1">Nejdříve propojte dítě v části Moje osoby.</div><?php endif;?><?php endif;?></div><?php endforeach;?>
<?php if ($cart['items'] === []): ?><div class="text-muted">Košík je prázdný.</div><?php else: ?><div class="border-bottom pb-3 mb-2"><?php if ($cart['coupon'] !== null): ?><div class="d-flex justify-content-between"><span>Kupón <code><?=shopPublicH($cart['coupon']['code'])?></code></span><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_coupon"><button class="btn btn-sm btn-link text-danger">Odebrat</button></form></div><?php else: ?><form method="post" class="d-flex gap-2"><?=csrf_field()?><input type="hidden" name="action" value="apply_coupon"><input class="form-control form-control-sm" name="coupon_code" maxlength="32" required placeholder="Slevový kupón"><button class="btn btn-sm btn-outline-primary">Použít</button></form><?php endif;?></div><div class="d-flex justify-content-between"><span>Mezisoučet</span><span><?=shopPublicMoney($cart['subtotal_minor'],(string)$cart['currency'])?></span></div><?php if ($cart['discount_minor'] > 0): ?><div class="d-flex justify-content-between text-success"><span>Sleva</span><span>− <?=shopPublicMoney($cart['discount_minor'],(string)$cart['currency'])?></span></div><?php endif;?><div class="d-flex justify-content-between fs-5 mt-2"><strong>Celkem</strong><strong><?=shopPublicMoney($cart['total_minor'],(string)$cart['currency'])?></strong></div><div class="small text-muted my-2">Osobní odběr · platba bankovním převodem</div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="checkout"><input type="hidden" name="checkout_key" value="<?=shopPublicH($_SESSION['shop_checkout_key'])?>"><input type="hidden" name="cart_fingerprint" value="<?=shopPublicH($cart['fingerprint'])?>"><button class="btn btn-success w-100" <?=$cart['coupon_error']!==null?'disabled':''?>>Vytvořit objednávku</button></form><?php endif;?></div></div></aside></div></main></body></html>
