<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/shop_checkout.php';
require_once dirname(__DIR__) . '/includes/shop_storefront.php';
require_once dirname(__DIR__) . '/includes/family_portal.php';
require_once dirname(__DIR__) . '/includes/club_program.php';

function shopPublicH(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function shopPublicMoney(int $minor, string $currency = 'CZK'): string { return number_format($minor / 100, 2, ',', ' ') . ' ' . shopPublicH($currency); }

$isLoggedIn = isset($_SESSION['verejny_uzivatel_id']);
$accountId = (int)($_SESSION['verejny_uzivatel_id'] ?? 0);$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLoggedIn) { header('Location: prihlaseni.php?redirect=eshop.php',true,303);exit; }
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
        } elseif ($action === 'remove_velodrome') {
            publicVelodromeShopRemoveFromCart($pdo,$accountId,(int)($_POST['cart_item_id'] ?? 0));
            $_SESSION['flash_shop'] = 'Termín velodromu byl z košíku odebrán.';
        } elseif ($action === 'remove_event') {
            clubEventShopRemoveFromCart($pdo,$accountId,(int)($_POST['cart_item_id'] ?? 0));
            $_SESSION['flash_shop'] = 'Klubová událost byla z košíku odebrána.';
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
    catch (InvalidArgumentException|ShopCheckoutException|ShopCouponException|ClubProgramException|PublicVelodromeShopException|ClubEventShopException $exception) { $errors[] = $exception->getMessage(); }
}
$success = (string)($_SESSION['flash_shop'] ?? '');unset($_SESSION['flash_shop']);
$products = [];
foreach (shopStorefrontCatalog($pdo) as $product) {
    $isProgram = clubProgramProductHasActiveOffer($pdo, (int)$product['product_id']);
    $product['variants'] = array_values(array_filter(
        $product['variants'],
        static function (array $variant) use ($pdo, $isProgram): bool {
            $offer = clubProgramOfferForVariant($pdo, (int)$variant['variant_id']);
            return $isProgram
                ? ($offer !== false && clubProgramOfferIsOnSale($offer))
                : $offer === false;
        }
    ));
    if ($product['variants'] === []) continue;
    if ($isProgram) $product['images'] = [];
    $product['is_program'] = $isProgram;
    $product['min_amount_minor'] = min(array_column($product['variants'], 'amount_minor'));
    $product['max_amount_minor'] = max(array_column($product['variants'], 'amount_minor'));
    $product['currency'] = (string)$product['variants'][0]['currency'];
    $product['in_stock'] = count(array_filter($product['variants'], static fn(array $variant): bool => (bool)$variant['in_stock'])) > 0;
    $products[] = $product;
}
$cart = $isLoggedIn ? shopCartDetail($pdo,$accountId) : ['items'=>[],'event_items'=>[],'velodrome_items'=>[],'coupon'=>null,'subtotal_minor'=>0,'discount_minor'=>0,'total_minor'=>0,'currency'=>'CZK','fingerprint'=>'','coupon_error'=>null];
$people = $isLoggedIn ? familyPortalAuthorizedPeople($pdo,$accountId) : [];
if ($isLoggedIn && (!isset($_SESSION['shop_checkout_key']) || preg_match('/^[a-f0-9]{32}$/D',(string)$_SESSION['shop_checkout_key']) !== 1)) $_SESSION['shop_checkout_key'] = bin2hex(random_bytes(16));
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Klubový e-shop</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><?php appUiAssets(); ?></head><body class="bg-light"><?php publicShellNav('shop'); ?><main class="container py-4">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h1 class="h3 mb-0">Klubový e-shop</h1><div class="text-muted">Zboží a klubové služby si můžete prohlížet bez registrace.</div></div><div class="d-flex flex-wrap gap-2"><?php if($isLoggedIn):?><a href="moje_programy.php" class="btn btn-outline-primary btn-sm">Moje kroužky</a><a href="moje_objednavky.php" class="btn btn-outline-success btn-sm">Moje objednávky</a><?php else:?><a href="prihlaseni.php?redirect=eshop.php" class="btn btn-primary btn-sm">Přihlásit se</a><?php endif;?><a href="krouzky.php" class="btn btn-outline-secondary btn-sm">Události</a></div></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=shopPublicH($error)?></div><?php endforeach;?><?php if ($success !== ''): ?><div class="alert alert-success"><?=shopPublicH($success)?></div><?php endif;?>
<div class="row g-4"><section class="col-lg-8"><h2 class="h5">Aktivní nabídka</h2><div class="row g-3">
<?php foreach ($products as $product): $variantCount=count($product['variants']); ?><div class="col-md-6"><div class="card h-100 border-0 shadow-sm"><?php if ($product['images'] !== []): ?><img src="<?=shopPublicH($product['images'][0])?>" alt="<?=shopPublicH($product['public_name'])?>" class="card-img-top object-fit-contain bg-white p-2" style="height:220px" loading="lazy" decoding="async" referrerpolicy="no-referrer"><?php endif;?><div class="card-body d-flex flex-column"><div class="d-flex justify-content-between gap-2"><h3 class="h6"><?=shopPublicH($product['public_name'])?></h3><?php if ($product['is_program']): ?><span class="badge text-bg-primary align-self-start">kroužek</span><?php endif;?></div><p class="small text-muted"><?=nl2br(shopPublicH($product['public_summary']))?></p><div class="small text-muted"><?=$variantCount?> <?=$variantCount===1?'varianta':($variantCount<=4?'varianty':'variant')?></div><div class="d-flex justify-content-between align-items-center mt-auto pt-3"><strong><?php if ((int)$product['min_amount_minor'] !== (int)$product['max_amount_minor']): ?>od <?php endif;?><?=shopPublicMoney((int)$product['min_amount_minor'],(string)$product['currency'])?></strong><a class="btn btn-sm btn-primary" href="produkt.php?id=<?=(int)$product['product_id']?>"><?=$product['in_stock']?'Detail a varianty':'Zobrazit detail'?></a></div></div></div></div><?php endforeach;?>
<?php if ($products === []): ?><div class="col-12"><div class="alert alert-light border">Zatím není aktivní žádná nabídka.</div></div><?php endif;?></div></section>
<aside class="col-lg-4"><div class="card border-0 shadow-sm sticky-top" style="top:1rem"><div class="card-header bg-white fw-semibold"><?=$isLoggedIn?'Košík':'Chcete nakoupit?'?></div><div class="card-body">
<?php if(!$isLoggedIn):?><p class="text-muted">Nabídku můžete procházet bez účtu. Přihlášení nebo registraci budete potřebovat až při vložení do košíku.</p><a class="btn btn-primary w-100 mb-2" href="prihlaseni.php?redirect=eshop.php">Přihlásit se</a><a class="btn btn-outline-primary w-100" href="registrace.php">Zaregistrovat se</a><?php else:?>
<?php foreach ($cart['items'] as $item): $offer = clubProgramOfferForVariant($pdo,(int)$item['variant_id']); $quantityId='cart-quantity-'.(int)$item['variant_id']; ?><div class="border-bottom pb-3 mb-3"><strong><?=shopPublicH($item['public_name'])?></strong><div class="small text-muted"><?=shopPublicH($item['sku'])?></div><?php if(($item['member_price']['is_member_price']??false)===true): ?><div class="small text-success">Klubová cena · <?=shopPublicH($item['member_price']['team_name'])?> (veřejně <?=shopPublicMoney((int)$item['public_amount_minor'],(string)$item['currency'])?>)</div><?php endif; ?><form method="post" class="d-flex gap-2 align-items-center mt-1"><?=csrf_field()?><input type="hidden" name="action" value="quantity"><input type="hidden" name="variant_id" value="<?=(int)$item['variant_id']?>"><label class="small text-muted mb-0" for="<?=shopPublicH($quantityId)?>">Množství</label><input id="<?=shopPublicH($quantityId)?>" class="form-control form-control-sm" style="width:75px" type="number" name="quantity" min="0" max="<?=$offer?'1':'99'?>" value="<?=(int)$item['quantity']?>"><button class="btn btn-sm btn-outline-secondary">Uložit</button><span class="ms-auto"><?=shopPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></span></form>
<?php if ($offer): ?><form method="post" class="mt-2"><?=csrf_field()?><input type="hidden" name="action" value="beneficiary"><input type="hidden" name="cart_item_id" value="<?=(int)$item['cart_item_id']?>"><label class="form-label small mb-1">Dítě / účastník</label><div class="input-group input-group-sm"><select class="form-select" name="sportovec_id" required><option value="">Vyberte</option><?php foreach ($people as $person): ?><option value="<?=(int)$person['sportovec_id']?>" <?=(int)($item['beneficiary_sportovec_id']??0)===(int)$person['sportovec_id']?'selected':''?>><?=shopPublicH($person['prijmeni'].' '.$person['jmeno'])?></option><?php endforeach;?></select><button class="btn btn-outline-primary">Potvrdit</button></div></form><?php if ($people === []): ?><div class="small text-danger mt-1">Nejdříve propojte dítě v části Moje osoby.</div><?php endif;?><?php endif;?></div><?php endforeach;?>
<?php foreach ($cart['event_items'] as $item): ?><div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between gap-2"><div><strong><?=shopPublicH($item['event_name'])?></strong><div class="small text-muted"><?=shopPublicH($item['prijmeni'].' '.$item['jmeno'])?> · souhlas <?=shopPublicH($item['consent_version'])?></div></div><span><?=shopPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></span></div><form method="post" class="mt-2"><?=csrf_field()?><input type="hidden" name="action" value="remove_event"><input type="hidden" name="cart_item_id" value="<?=(int)$item['cart_item_id']?>"><button class="btn btn-sm btn-outline-danger">Odebrat událost</button></form></div><?php endforeach;?>
<?php foreach ($cart['velodrome_items'] as $item): ?><div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between gap-2"><div><strong><?=shopPublicH($item['lesson_name'])?></strong><div class="small text-muted"><?=shopPublicH($item['datum'].' '.substr((string)$item['cas_od'],0,5).'–'.substr((string)$item['cas_do'],0,5))?> · <?=shopPublicH($item['prijmeni'].' '.$item['jmeno'])?></div></div><span><?=shopPublicMoney((int)$item['line_amount_minor'],'CZK')?></span></div><form method="post" class="mt-2"><?=csrf_field()?><input type="hidden" name="action" value="remove_velodrome"><input type="hidden" name="cart_item_id" value="<?=(int)$item['cart_item_id']?>"><button class="btn btn-sm btn-outline-danger">Odebrat termín</button></form></div><?php endforeach;?>
<?php if($cart['event_items']!==[]&&$cart['items']===[])$cart['items'][]=['service_item'=>true]; ?>
<?php if ($cart['items'] === [] && $cart['velodrome_items'] === []): ?><div class="text-muted">Košík je prázdný.</div><?php else: ?><div class="border-bottom pb-3 mb-2"><?php if ($cart['coupon'] !== null): ?><div class="d-flex justify-content-between"><span>Kupón <code><?=shopPublicH($cart['coupon']['code'])?></code></span><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_coupon"><button class="btn btn-sm btn-link text-danger">Odebrat</button></form></div><?php else: ?><form method="post" class="d-flex gap-2"><?=csrf_field()?><input type="hidden" name="action" value="apply_coupon"><input class="form-control form-control-sm" name="coupon_code" maxlength="32" required placeholder="Slevový kupón"><button class="btn btn-sm btn-outline-primary">Použít</button></form><?php endif;?></div><div class="d-flex justify-content-between"><span>Mezisoučet</span><span><?=shopPublicMoney($cart['subtotal_minor'],(string)$cart['currency'])?></span></div><?php if ($cart['discount_minor'] > 0): ?><div class="d-flex justify-content-between text-success"><span>Sleva</span><span>− <?=shopPublicMoney($cart['discount_minor'],(string)$cart['currency'])?></span></div><?php endif;?><div class="d-flex justify-content-between fs-5 mt-2"><strong>Celkem</strong><strong><?=shopPublicMoney($cart['total_minor'],(string)$cart['currency'])?></strong></div><div class="small text-muted my-2">Osobní odběr / rezervace · platba bankovním převodem</div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="checkout"><input type="hidden" name="checkout_key" value="<?=shopPublicH($_SESSION['shop_checkout_key'])?>"><input type="hidden" name="cart_fingerprint" value="<?=shopPublicH($cart['fingerprint'])?>"><button class="btn btn-success w-100" <?=$cart['coupon_error']!==null?'disabled':''?>>Vytvořit objednávku</button></form><?php endif;?><?php endif;?></div></div></aside></div></main><?php publicShellFooter(); ?></body></html>
