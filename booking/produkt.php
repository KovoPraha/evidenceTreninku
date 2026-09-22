<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/shop_storefront.php';
require_once dirname(__DIR__) . '/includes/club_program.php';
require_once dirname(__DIR__) . '/includes/family_portal.php';
require_once dirname(__DIR__) . '/includes/shop_product_interest.php';
require_once dirname(__DIR__) . '/includes/shop_purchase_mode.php';
require_once dirname(__DIR__) . '/includes/auth_rate_limit.php';

function shopProductH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function shopProductMoney(int $minor, string $currency): string
{
    return number_format($minor / 100, 2, ',', ' ') . ' ' . shopProductH($currency);
}

function shopProductDate(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('j. n. Y') : $date;
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
$hasProgramOffer = $product !== null && clubProgramProductHasOfferLink($pdo, $productId);
$isProgram = $product !== null && shopProductRequiresAthlete($pdo, $productId);
if ($product !== null) {
    $product['variants'] = array_values(array_filter(
        $product['variants'],
        static function (array $variant) use ($pdo, $hasProgramOffer, $isProgram): bool {
            $offer = clubProgramOfferForVariant($pdo, (int)$variant['variant_id']);
            if ($hasProgramOffer) {
                return clubProgramVariantSaleState($pdo,(int)$variant['variant_id'])['saleable'];
            }
            if ($isProgram) return true;
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
$interestSuccess = (string)($_SESSION['shop_interest_success'] ?? '');
unset($_SESSION['shop_interest_success']);
if ($product !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            if ($action === 'interest') {
                $interestEmail = strtolower(trim((string)($_POST['interest_email'] ?? '')));
                if (!auth_rate_limit_reserve_attempt($pdo, 'product_interest', $interestEmail, auth_rate_limit_request_ip())) {
                    throw new ShopProductInterestException('Příliš mnoho pokusů. Zkuste kontakt odeslat později.');
                }
                shopProductInterestSubmit(
                    $pdo,
                    $productId,
                    (int)($_POST['interest_variant_id'] ?? 0) > 0 ? (int)$_POST['interest_variant_id'] : null,
                    $interestEmail,
                    'booking/produkt.php?id=' . $productId,
                    ($_POST['contact_consent'] ?? '') === '1'
                );
                $_SESSION['shop_interest_success'] = 'Děkujeme. E-mail jsme uložili a k této nabídce se vám ozveme.';
                header('Location: produkt.php?id=' . $productId . '#mam-zajem', true, 303);
                exit;
            }
            if ($action !== 'add') {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            if (!$isLoggedIn) {
                header('Location: prihlaseni.php?redirect=' . rawurlencode('produkt.php?id=' . $productId), true, 303);
                exit;
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
            $offer = false;
            if(clubProgramVariantHasOfferLink($pdo,$variantId)){$saleState=clubProgramVariantSaleState($pdo,$variantId);if(!$saleState['saleable'])throw new ClubProgramException($saleState['reason']);$offer=$saleState['offer'];}
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
        } catch (InvalidArgumentException|ShopCheckoutException|ClubProgramException|ShopProductInterestException $exception) {
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
        <?php $imageUrl = shopStorefrontPrimaryImageUrl($product['images']); ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= shopProductH($error) ?></div>
        <?php endforeach; ?>
        <?php if ($interestSuccess !== ''): ?><div class="alert alert-success"><?=shopProductH($interestSuccess)?></div><?php endif; ?>
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="row g-0">
                <div class="col-lg-5 bg-white d-flex align-items-center justify-content-center p-3">
                    <img src="<?= shopProductH($imageUrl) ?>" alt="<?= shopProductH($product['public_name']) ?>" class="img-fluid rounded app-product-image" style="max-height:480px" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='<?=shopProductH(shopStorefrontPlaceholderImageUrl())?>'">
                </div>
                <div class="col-lg-7">
                    <div class="card-body p-4">
                        <?php if ($isProgram): ?><span class="badge text-bg-primary mb-2">kroužek</span><?php endif; ?>
                        <h1 class="h3"><?= shopProductH($product['public_name']) ?></h1>
                        <p class="text-muted"><?= nl2br(shopProductH($product['public_summary'])) ?></p>
                        <h2 class="h5 mt-4"><?= $isProgram ? 'Termín a přihlášení' : 'Vyberte variantu' ?></h2>
                        <?php if (!$isLoggedIn): ?>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6"><div class="border rounded bg-body-tertiary p-3 h-100"><strong>Už máte účet?</strong><p class="small text-muted my-2">Přihlaste se. Uvidíte své osoby, historii a klubové ceny. Po přihlášení se zobrazí případná klubová cena.</p><a class="btn btn-sm btn-outline-primary" href="prihlaseni.php?redirect=<?=rawurlencode('produkt.php?id='.$productId)?>">Přihlásit se</a></div></div>
                                <div class="col-md-6"><div class="border rounded border-primary p-3 h-100"><strong><?=$isProgram?'Jste tu poprvé?':'Chcete nakoupit bez účtu?'?></strong><p class="small text-muted my-2"><?=$isProgram?'Pro přihlášení dítěte nebo účastníka potřebujete účet. Založte kontakt rodiče a po ověření bezpečně doplňte údaje sportovce.':'Stačí jméno a e-mail. Adresa je při osobním odběru nepovinná.'?></p><?php if($isProgram):?><a class="btn btn-sm btn-primary" href="registrace.php?purpose=nakup&amp;redirect=<?=rawurlencode('registrace_sportovce.php?product_id='.$productId)?>">Začít registraci sportovce</a><?php else:?><span class="small text-muted">Rychlý nákup vyberete u konkrétní varianty níže.</span><?php endif;?></div></div>
                            </div>
                        <?php endif; ?>
                        <div class="vstack gap-2">
                            <?php foreach ($product['variants'] as $variant): $offer = clubProgramOfferForVariant($pdo, (int)$variant['variant_id']); ?>
                                <div class="border rounded p-3">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                        <div>
                                            <strong><?= shopProductH(shopProductVariantLabel($variant)) ?></strong>
                                            <?php if (!$offer): ?><div class="small text-muted">SKU <?= shopProductH($variant['sku']) ?></div><?php endif; ?>
                                            <?php if ($offer): ?>
                                                <div class="small mt-1">
                                                    <strong><?= shopProductH($offer['name']) ?></strong><br>
                                                    <?= shopProductH(shopProductDate((string)$offer['starts_on'])) ?> – <?= shopProductH(shopProductDate((string)$offer['ends_on'])) ?><br>
                                                    <span class="text-primary"><?=shopProductH(clubProgramBirthYearLabel($offer))?></span><br>
                                                    Skupina: <?= shopProductH($offer['team_name']) ?>
                                                    <?php if ($offer['capacity'] !== null): ?><br>Volná místa: <strong><?= (int)$offer['available_count'] ?></strong> z <?= (int)$offer['capacity'] ?><?php else: ?><br>Kapacita není omezena.<?php endif; ?>
                                                </div>
                                                <?php if (trim((string)($offer['program_description'] ?? '')) !== ''): ?><p class="small mt-2 mb-1"><?= nl2br(shopProductH($offer['program_description'])) ?></p><?php endif; ?>
                                                <?php $terms = clubProgramTermsEffective($pdo, (int)$offer['program_id'], (int)$offer['id']); ?>
                                                <?php if (clubProgramTermsComplete($terms)): ?>
                                                    <details class="small mt-2"><summary>Storno podmínky a souhlas</summary>
                                                        <p class="mt-2 mb-1"><strong>Storno:</strong> <?= nl2br(shopProductH($terms['program_cancellation']['consent_text_plain'])) ?></p>
                                                        <p class="mb-0"><strong>Souhlas:</strong> <?= nl2br(shopProductH($terms['program_consent']['consent_text_plain'])) ?></p>
                                                    </details>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!$offer && !$isProgram): ?><div class="small <?= $variant['in_stock'] ? 'text-success' : 'text-danger' ?> mt-1"><?= $variant['in_stock'] ? 'Skladem' : 'Momentálně vyprodáno' ?></div><?php elseif(!$offer):?><div class="small text-warning mt-1">Přihlášení k tomuto termínu se připravuje.</div><?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <?php if (($variant['member_price']['is_member_price'] ?? false) === true): ?>
                                                <div class="small text-muted text-decoration-line-through">Veřejná cena <?= shopProductMoney((int)$variant['public_amount_minor'], (string)$variant['currency']) ?></div>
                                                <div class="fw-semibold text-success"><?= shopProductMoney((int)$variant['amount_minor'], (string)$variant['currency']) ?></div>
                                                <div class="small text-success mb-2">Klubová cena · <?= shopProductH($variant['member_price']['team_name']) ?></div>
                                            <?php else: ?>
                                                <div class="fw-semibold mb-2"><?= shopProductMoney((int)$variant['amount_minor'], (string)$variant['currency']) ?></div>
                                            <?php endif; ?>
                                            <?php if($isLoggedIn && (!$isProgram || $offer)): ?><form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="variant_id" value="<?= (int)$variant['variant_id'] ?>">
                                                <?php if($offer):?><label class="form-label small" for="program-person-<?=(int)$variant['variant_id']?>">Dítě / účastník</label><select class="form-select form-select-sm mb-2" id="program-person-<?=(int)$variant['variant_id']?>" name="sportovec_id" required><option value="">Vyberte</option><?php foreach($people as$person):?><option value="<?=(int)$person['sportovec_id']?>"><?=shopProductH($person['prijmeni'].' '.$person['jmeno'])?></option><?php endforeach;?></select><?php if($people===[]):?><div class="small text-danger mb-2">Nejdříve propojte dítě v části Moje osoby.</div><?php endif;?><?php endif;?>
                                                <button class="btn btn-primary btn-sm" <?= $variant['in_stock'] ? '' : 'disabled' ?>><?= $offer ? 'Přihlásit účastníka' : 'Přidat do košíku' ?></button>
                                            </form><?php elseif($isLoggedIn && $isProgram):?><div class="d-grid gap-1"><a class="btn btn-outline-primary btn-sm" href="registrace_sportovce.php?product_id=<?=$productId?>">Doplnit sportovce</a><span class="small text-muted">Nákup se zpřístupní po vypsání prodejního termínu.</span></div><?php else: ?><?php if($isProgram):?><div class="d-grid gap-1"><a class="btn btn-primary btn-sm <?= $offer && !$variant['in_stock'] ? 'disabled' : '' ?>" href="registrace.php?purpose=nakup&amp;redirect=<?=rawurlencode('registrace_sportovce.php?product_id='.$productId)?>"><?= $offer && !$variant['in_stock'] ? 'Kapacita naplněna' : 'Začít registraci sportovce' ?></a><a class="btn btn-outline-primary btn-sm" href="prihlaseni.php?redirect=<?=rawurlencode('produkt.php?id='.$productId)?>">Přihlásit se</a></div><?php else:?><div class="d-grid gap-1"><a class="btn btn-primary btn-sm <?= $variant['in_stock'] ? '' : 'disabled' ?>" href="rychly_nakup.php?product_id=<?=$productId?>&amp;variant_id=<?=(int)$variant['variant_id']?>"><?= $variant['in_stock'] ? 'Koupit bez registrace' : 'Vyprodáno' ?></a><a class="btn btn-outline-primary btn-sm <?= $variant['in_stock'] ? '' : 'disabled' ?>" href="prihlaseni.php?redirect=<?=rawurlencode('produkt.php?id='.$productId)?>">Přihlásit se</a></div><?php endif;?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="small text-muted mt-4 mb-0"><?= $isProgram ? 'Cena, věk účastníka a volná kapacita se před dokončením přihlášení znovu ověří.' : 'Cena a dostupnost se při vytvoření objednávky znovu bezpečně ověří. Objednávka používá neměnný cenový snapshot.' ?></p>
                        <section id="mam-zajem" class="card bg-body-tertiary border-0 mt-4"><div class="card-body">
                            <h2 class="h5">Nevyhovuje vám termín nebo varianta?</h2><p class="small text-muted">Zanechte nám e-mail. Ozveme se, jakmile budeme řešit další termín, velikost nebo vhodnou variantu.</p>
                            <form method="post" class="row g-2 align-items-end"><?=csrf_field()?><input type="hidden" name="action" value="interest"><div class="col-md-6"><label class="form-label" for="interest-email">E-mail</label><input id="interest-email" type="email" name="interest_email" class="form-control" maxlength="254" value="<?=shopProductH($_POST['interest_email']??'')?>" required></div><div class="col-md-6"><label class="form-label" for="interest-variant">Termín / varianta <span class="text-muted">(nepovinné)</span></label><select id="interest-variant" name="interest_variant_id" class="form-select"><option value="">Obecný zájem o produkt</option><?php foreach($product['variants'] as$interestVariant):?><option value="<?=(int)$interestVariant['variant_id']?>"><?=shopProductH(shopProductVariantLabel($interestVariant))?></option><?php endforeach;?></select></div><div class="col-12"><div class="form-check"><input id="contact-consent" class="form-check-input" type="checkbox" name="contact_consent" value="1" required><label class="form-check-label small" for="contact-consent">Souhlasím, aby mě KOVO Praha kontaktovalo k této konkrétní nabídce.</label></div></div><div class="col-12"><button class="btn btn-outline-primary">Chci vědět o další možnosti</button></div></form>
                        </div></section>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php publicShellFooter(); ?>
</body>
</html>
