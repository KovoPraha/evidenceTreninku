<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/shop_storefront.php';
require_once dirname(__DIR__) . '/includes/club_program.php';
require_once dirname(__DIR__) . '/includes/family_portal.php';

function shopProductH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function shopProductMoney(int $minor, string $currency): string
{
    return number_format($minor / 100, 2, ',', ' ') . ' ' . shopProductH($currency);
}

/** @return string */
function shopProductVariantLabel(array $variant): string
{
    $parts = [];
    foreach (($variant['attributes_detail'] ?? []) as $attribute) {
        $parts[] = trim((string)$attribute['display_name']) . ': ' . trim((string)$attribute['formatted_value']);
    }
    return $parts !== [] ? implode(' · ', $parts) : (string)$variant['sku'];
}

$productId = (int)($_GET['id'] ?? 0);
$product = shopStorefrontProductDetail($pdo, $productId);
$isProgram = $product !== null && clubProgramProductHasActiveOffer($pdo, $productId);
if ($product !== null) {
    $product['variants'] = array_values(array_filter(
        $product['variants'],
        static function (array $variant) use ($pdo, $isProgram): bool {
            $offer = clubProgramOfferForVariant($pdo, (int)$variant['variant_id']);
            if ($isProgram) {
                return $offer !== false && clubProgramOfferIsOnSale($offer);
            }
            return $offer === false;
        }
    ));
    if ($isProgram) {
        // Imported source images describe the former Shoptet product, not the approved club service.
        $product['images'] = array_values(array_filter(
            $product['images'],
            static fn(string $url): bool => shopStorefrontIsLocalImageUrl($url)
        ));
    }
}
if ($product === null || $product['variants'] === []) {
    http_response_code(404);
    $product = null;
}

$isLoggedIn = isset($_SESSION['verejny_uzivatel_id']);
$accountId = (int)($_SESSION['verejny_uzivatel_id'] ?? 0);
$people=$isLoggedIn?familyPortalAuthorizedPeople($pdo,$accountId):[];
if ($product !== null && $isLoggedIn) {
    foreach ($product['variants'] as &$variant) {
        shopMemberPriceApplyToItem($pdo, $accountId, $variant);
    }
    unset($variant);
}
$errors = [];
if ($product !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLoggedIn) {
        header('Location: prihlaseni.php?redirect=' . rawurlencode('produkt.php?id=' . $productId), true, 303);
        exit;
    }
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            if ((string)($_POST['action'] ?? '') !== 'add') {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            $variantId = (int)($_POST['variant_id'] ?? 0);
            $selected = null;
            foreach ($product['variants'] as $variant) {
                if ((int)$variant['variant_id'] === $variantId) {
                    $selected = $variant;
                    break;
                }
            }
            if ($selected === null || !$selected['in_stock']) {
                throw new ShopCheckoutException('Vybraná varianta není aktuálně skladem.');
            }
            $offer = clubProgramOfferForVariant($pdo, $variantId);
            if ($offer && !clubProgramOfferIsOnSale($offer)) {
                throw new ClubProgramException('Prodej tohoto období ještě nezačal nebo už skončil.');
            }
            $current = 0;
            foreach (shopCartDetail($pdo, $accountId)['items'] as $item) {
                if ((int)$item['variant_id'] === $variantId) {
                    $current = (int)$item['quantity'];
                }
            }
            shopCartSetQuantity($pdo,$accountId,$variantId,$offer?1:min(99,$current+1),$offer?(int)($_POST['sportovec_id']??0):null);
            $_SESSION['flash_shop'] = $offer
                ? 'Období kroužku bylo přidáno pro vybrané dítě.'
                : 'Položka byla přidána do košíku.';
            header('Location: eshop.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('booking/produkt.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala bez částečného zápisu.';
        } catch (InvalidArgumentException|ShopCheckoutException|ClubProgramException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $product ? shopProductH($product['public_name']) : 'Produkt nebyl nalezen' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav('shop'); ?>
<main class="container py-4" style="max-width: 1050px">
    <a href="eshop.php" class="btn btn-sm btn-outline-secondary mb-3">← Zpět do e-shopu</a>
    <?php if ($product === null): ?>
        <div class="alert alert-warning">Produkt není dostupný nebo už není v aktivní nabídce.</div>
    <?php else: ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= shopProductH($error) ?></div>
        <?php endforeach; ?>
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="row g-0">
                <div class="col-lg-5 bg-white d-flex align-items-center justify-content-center p-3">
                    <?php if ($product['images'] !== []): ?>
                        <img src="<?= shopProductH($product['images'][0]) ?>" alt="<?= shopProductH($product['public_name']) ?>" class="img-fluid rounded" style="max-height:480px" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <div class="text-center text-muted p-5"><div class="display-4 mb-2">◇</div>Obrázek zatím není k dispozici.</div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-7">
                    <div class="card-body p-4">
                        <?php if ($isProgram): ?><span class="badge text-bg-primary mb-2">kroužek</span><?php endif; ?>
                        <h1 class="h3"><?= shopProductH($product['public_name']) ?></h1>
                        <p class="text-muted"><?= nl2br(shopProductH($product['public_summary'])) ?></p>
                        <h2 class="h5 mt-4">Vyberte variantu</h2>
                        <?php if (!$isLoggedIn): ?>
                            <a class="alert alert-info d-block text-decoration-none" href="prihlaseni.php?redirect=<?=rawurlencode('produkt.php?id='.$productId)?>">Přihlásit se pro zobrazení klubové ceny</a>
                        <?php endif; ?>
                        <div class="vstack gap-2">
                            <?php foreach ($product['variants'] as $variant): $offer = clubProgramOfferForVariant($pdo, (int)$variant['variant_id']); ?>
                                <div class="border rounded p-3">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                        <div>
                                            <strong><?= shopProductH(shopProductVariantLabel($variant)) ?></strong>
                                            <div class="small text-muted">SKU <?= shopProductH($variant['sku']) ?></div>
                                            <?php if ($offer): ?>
                                                <div class="small mt-1"><?= shopProductH($offer['name']) ?><br><?= shopProductH($offer['starts_on']) ?> – <?= shopProductH($offer['ends_on']) ?><br><span class="text-primary"><?=shopProductH(clubProgramBirthYearLabel($offer))?></span></div>
                                            <?php endif; ?>
                                            <div class="small <?= $variant['in_stock'] ? 'text-success' : 'text-danger' ?> mt-1">
                                                <?= $variant['in_stock'] ? 'Skladem' : 'Momentálně vyprodáno' ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <?php if (($variant['member_price']['is_member_price'] ?? false) === true): ?>
                                                <div class="small text-muted text-decoration-line-through">Veřejná cena <?= shopProductMoney((int)$variant['public_amount_minor'], (string)$variant['currency']) ?></div>
                                                <div class="fw-semibold text-success"><?= shopProductMoney((int)$variant['amount_minor'], (string)$variant['currency']) ?></div>
                                                <div class="small text-success mb-2">Klubová cena · <?= shopProductH($variant['member_price']['team_name']) ?></div>
                                            <?php else: ?>
                                                <div class="fw-semibold mb-2"><?= shopProductMoney((int)$variant['amount_minor'], (string)$variant['currency']) ?></div>
                                            <?php endif; ?>
                                            <?php if($isLoggedIn): ?><form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="variant_id" value="<?= (int)$variant['variant_id'] ?>">
                                                <?php if($offer):?><label class="form-label small" for="program-person-<?=(int)$variant['variant_id']?>">Dítě / účastník</label><select class="form-select form-select-sm mb-2" id="program-person-<?=(int)$variant['variant_id']?>" name="sportovec_id" required><option value="">Vyberte</option><?php foreach($people as$person):?><option value="<?=(int)$person['sportovec_id']?>"><?=shopProductH($person['prijmeni'].' '.$person['jmeno'])?></option><?php endforeach;?></select><?php if($people===[]):?><div class="small text-danger mb-2">Nejdříve propojte dítě v části Moje osoby.</div><?php endif;?><?php endif;?>
                                                <button class="btn btn-primary btn-sm" <?= $variant['in_stock'] ? '' : 'disabled' ?>>Přidat do košíku</button>
                                            </form><?php else: ?><a class="btn btn-primary btn-sm <?= $variant['in_stock'] ? '' : 'disabled' ?>" href="prihlaseni.php?redirect=<?=rawurlencode('produkt.php?id='.$productId)?>"><?= $variant['in_stock'] ? 'Přihlásit se a koupit' : 'Vyprodáno' ?></a><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="small text-muted mt-4 mb-0">Cena a dostupnost se při vytvoření objednávky znovu bezpečně ověří. Objednávka používá neměnný cenový snapshot.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php publicShellFooter(); ?>
</body>
</html>
