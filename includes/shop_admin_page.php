<?php
declare(strict_types=1);

require_once __DIR__.'/init.php';
require_once __DIR__.'/shop_admin_catalog.php';
require_once __DIR__.'/shop_admin_input.php';
require_once __DIR__.'/shop_admin_view.php';
require_once __DIR__.'/shop_product_image.php';
require_once __DIR__.'/shop_storefront.php';
require_once __DIR__.'/shop_catalog_publication.php';
if (!isset($_SESSION['trener_id'])||!roleAtLeast('admin')) {header('Location: login.php');exit;}

$screen=$shopAdminScreen??'product';
$actorId=(int)$_SESSION['trener_id'];
$productId=max(0,(int)($_GET['id']??$_POST['product_id']??0));
$tab=(string)($_GET['tab']??$_POST['tab']??'variants');
if (!in_array($tab,['main','variants','inventory','images','category','history'],true)) $tab='variants';
$variantId=max(0,(int)($_GET['variant_id']??$_POST['variant_id']??0));
$definitions=shopAttributeDefinitions($pdo,true);
$errors=[];$detail=null;$product=null;$variant=null;$publication=null;
$page=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
try {
    if ($productId>0) {
        $detail=shopManualCatalogDetail($pdo,$productId);$product=$detail['product'];
        $query=$pdo->prepare('SELECT * FROM shop_product_publications WHERE product_id=?');$query->execute([$productId]);$publication=$query->fetch(PDO::FETCH_ASSOC)?:null;
        $product['publication_status']=$publication['status']??null;
        foreach ($detail['variants'] as $row) if ((int)$row['id']===$variantId) $variant=$row;
        if ($variantId>0&&$variant===null) throw new InvalidArgumentException('Varianta nepatří k vybranému produktu.');
    }
    if (in_array($screen,['variant','withdraw'],true)&&$product===null) throw new InvalidArgumentException('Vyberte produkt.');
    if (in_array($screen,['prices','stock'],true)||($screen==='product'&&$product===null)) {
        $page=shopAdminCatalogPage($pdo,$_GET,in_array($screen,['prices','stock'],true));
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!csrf_verify((string)($_POST['csrf_token']??''))) throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        if (isset($_POST['product_id'])&&(int)$_POST['product_id']!==$productId) throw new InvalidArgumentException('Nesouhlasí vybraný produkt.');
        if (isset($_POST['variant_id'])&&(int)$_POST['variant_id']!==$variantId) throw new InvalidArgumentException('Nesouhlasí vybraná varianta.');
        $action=(string)($_POST['action']??'');$reason=trim((string)($_POST['reason']??''));$confirmed=($_POST['confirm_action']??'')==='1';
        $allowed=match($screen){'new'=>['create'],'withdraw'=>['unpublish'],'variant'=>['update_variant','add_variants'],'prices'=>['save_prices'],'stock'=>[],default=>$product?['save_product','save_prices','adjust_stock','publish','add_image','reorder_image','remove_image','categories']:[]};
        if (!in_array($action,$allowed,true)) throw new InvalidArgumentException('Tato akce není na této stránce dostupná.');
        $redirect=$productId>0?shopAdminUrl($productId,$tab):'eshop_produkt_admin.php';
        if ($action==='create') {
            $type=(string)($_POST['offer_type']??'goods');
            if (!in_array($type,['goods','club_event','camp'],true)) throw new InvalidArgumentException('Kroužek založte průvodcem.');
            $input=$_POST;$input['item_type']=$type==='goods'?'product':'service';$input['visibility']='visible';
            if($type!=='goods')$input['packeta_shipping_enabled']=0;
            $variantInput=shopAdminParseVariant($_POST,$definitions);$variantInput['visible']=1;
            $result=shopManualCatalogCreate($pdo,$actorId,$input,$variantInput,$reason,$confirmed);
            $redirect=shopAdminUrl((int)$result['product_id'],'main');$message='Produkt byl založen jako koncept. Doplňte obrázky a kategorii a potom jej zveřejněte.';
        } elseif ($action==='save_product') {
            $input=array_merge($product,$_POST);
            if (($input['offer_type']??'')==='program'&&$product['offer_type']!=='program') throw new InvalidArgumentException('Kroužek založte průvodcem.');
            shopManualCatalogUpdateProduct($pdo,$actorId,$productId,$input,$reason,$confirmed);$message='Údaje produktu byly uloženy.';
        } elseif ($action==='save_prices') {
            $prices=[];
            foreach ((array)($_POST['prices']??[]) as $id=>$amount) {
                $expected=$_POST['expected_prices'][$id]??null;
                if ($expected===null) throw new InvalidArgumentException('Chybí původní cena. Obnovte stránku.');
                if ((string)$expected!==''&&preg_match('/^\d+$/D',(string)$expected)!==1) throw new InvalidArgumentException('Neplatná původní cena.');
                $prices[$id]=['amount'=>couponAdminMoneyInput((string)$amount),'expected'=>$expected===''?null:(int)$expected];
            }
            $changed=shopAdminUpdatePrices($pdo,$actorId,$prices,$reason,$confirmed,$screen==='prices'?null:$productId);
            $message='Ceny byly uloženy. Změněno variant: '.$changed.'.';
            if ($screen==='prices') $redirect='eshop_ceny_admin.php?'.http_build_query(array_intersect_key($_GET,array_flip(['q','status','stock','page'])));
        } elseif ($action==='adjust_stock') {
            $stockVariant=(int)($_POST['stock_variant_id']??0);
            shopAdminAdjustStock($pdo,$actorId,$stockVariant,$productId,(string)($_POST['stock_quantity_decimal']??''),(string)($_POST['expected_stock']??''),$reason,$confirmed);
            $message='Skladový stav byl upraven se samostatným skladovým pohybem.';$redirect=shopAdminUrl($productId,'inventory');
        } elseif ($action==='update_variant') {
            if ($variant===null) throw new InvalidArgumentException('Vyberte variantu.');
            $existing=json_decode((string)$variant['attributes_json'],true,64,JSON_THROW_ON_ERROR);
            $input=shopAdminParseVariant($_POST,$definitions,is_array($existing)?$existing:[]);unset($input['stock_quantity_decimal']);
            shopManualCatalogUpdateVariant($pdo,$actorId,$variantId,$input,$reason,$confirmed);
            $message='Varianta byla uložena.';$redirect=shopAdminUrl($productId);
        } elseif ($action==='add_variants') {
            if ($product['offer_type']==='program') throw new InvalidArgumentException('Varianty kroužku spravujte přes nabídky a termíny.');
            $base=shopAdminParseVariant($_POST,$definitions);
            $inputs=shopAdminVariantCombinations($base,(string)($_POST['new_colors']??''),(string)($_POST['new_sizes']??''));
            $ids=shopManualCatalogAddVariants($pdo,$actorId,$productId,$inputs,$reason,$confirmed);
            $message='Přidáno variant: '.count($ids).'.';$redirect=shopAdminUrl($productId);
        } elseif ($action==='unpublish') {
            if (!$confirmed) throw new InvalidArgumentException('Potvrďte stažení produktu.');
            if ($product['catalog_status']==='draft') {
                shopManualCatalogArchive($pdo,$actorId,$productId,$reason,$confirmed);
                $message='Koncept byl archivován. Údaje a historie zůstaly zachované.';
            } else {
                shopCatalogPublicationDeactivate($pdo,$productId,$actorId,$reason);
                $message='Produkt byl stažen z prodeje. Objednávky a historie zůstaly zachované.';
            }
            $redirect='eshop_produkt_admin.php';
        } elseif ($action==='publish') {
            $name=trim((string)($_POST['public_name']??''));$summary=trim((string)($_POST['public_summary']??''));
            shopCatalogPublicationActivate($pdo,$productId,$actorId,$name,$summary!==''?$summary:$name,$reason,$confirmed);
            $message='Zobrazení produktu v e-shopu bylo uloženo.';$redirect=shopAdminUrl($productId,'main');
        } elseif ($action==='categories') {
            shopCategoryAdminAssignProduct($pdo,$actorId,$productId,(array)($_POST['category_paths']??[]),(string)($_POST['default_category']??''),$reason,$confirmed);
            $message='Kategorie produktu byly uloženy.';
        } else {
            if ($action==='add_image') {
                $upload=$_FILES['product_image']??null;
                if (!is_array($upload)||(int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Vyberte JPG nebo PNG obrázek do 5 MB.');
                shopProductImageAdd($pdo,$actorId,$productId,(string)$upload['tmp_name'],(int)($_POST['sort_order']??0),$reason,$confirmed,true);
            } else {
                $imageId=(int)($_POST['image_id']??0);
                if (!in_array($imageId,array_map('intval',array_column($detail['images'],'id')),true)) throw new InvalidArgumentException('Obrázek nepatří k produktu.');
                if ($action==='reorder_image') shopProductImageUpdateOrder($pdo,$actorId,$imageId,(int)($_POST['sort_order']??0),$reason,$confirmed);
                else shopProductImageRemove($pdo,$actorId,$imageId,$reason,$confirmed);
            }
            $message='Obrázky produktu byly upraveny.';$redirect=shopAdminUrl($productId,'images');
        }
        $_SESSION['shop_admin_flash']=$message;
        header('Location: '.$redirect,true,303);exit;
    }
} catch (InvalidArgumentException|ShopManualCatalogException|ShopCatalogPublicationException|ShopCatalogManagementException|ShopCategoryException|ShopProductImageException $exception) {
    $errors[]=$exception->getMessage();
} catch (Throwable $exception) {
    error_log('Shop admin: '.$exception->getMessage());$errors[]='Požadavek se nepodařilo dokončit. Obnovte stránku a zkuste to znovu.';
}
$flash=(string)($_SESSION['shop_admin_flash']??$_SESSION['product_admin_flash']??'');unset($_SESSION['shop_admin_flash'],$_SESSION['product_admin_flash']);
$titles=['new'=>'Přidat produkt','variant'=>$variant?'Upravit variantu':'Přidat varianty','withdraw'=>($product['catalog_status']??'')==='draft'?'Archivovat koncept':'Stáhnout produkt z prodeje','prices'=>'Ceny produktů','stock'=>'Skladové zásoby'];
$title=$titles[$screen]??($product?$product['name']:'Přehled produktů');
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=shopAdminH($title)?></title><?php appUiAssets(); ?></head><body class="bg-light">
<?php include dirname(__DIR__).'/hlavicka.php'; ?>
<main class="app-page app-product-grid">
  <nav class="app-breadcrumb" aria-label="Drobečková navigace"><a href="pracovni_pozice.php">KIS</a><span>›</span><a href="eshop_produkt_admin.php">Produkty</a><?php if ($title!=='Přehled produktů'): ?><span>›</span><span><?=shopAdminH($title)?></span><?php endif; ?></nav>
  <section class="app-page-paper">
    <div class="app-page-heading"><div><h1 class="app-page-title"><?=shopAdminH($title)?></h1><?php if ($product): ?><div class="d-flex align-items-center flex-wrap gap-2 small"><?=shopAdminBadge($product)?><span class="text-muted"><?=shopAdminH(shopAdminOfferLabel($product['offer_type']))?> · <?=shopAdminH(shopAdminVariantCount(count($detail['variants'])))?></span></div><?php endif; ?></div><div class="d-flex gap-2 flex-wrap">
      <?php if ($screen==='product'&&$product===null): ?><a class="btn btn-primary" href="eshop_produkt_novy_admin.php">Přidat produkt</a><a class="btn btn-outline-secondary" href="club_program_wizard_admin.php">Vypsat kroužek</a>
      <?php elseif ($product): ?><a class="btn btn-outline-secondary" href="<?=$screen==='product'?'eshop_produkt_admin.php':shopAdminH(shopAdminUrl($productId))?>"><?=$screen==='product'?'← Přehled':'← Zpět na produkt'?></a><?php if ($screen==='product'&&(($product['publication_status']??'')==='active'||$product['catalog_status']==='draft')): ?><a class="btn btn-outline-secondary" href="eshop_produkt_stahnout_admin.php?id=<?=$productId?>"><?=$product['catalog_status']==='draft'?'Archivovat koncept':'Stáhnout z prodeje'?></a><?php endif; ?>
      <?php elseif ($screen==='new'): ?><a class="btn btn-outline-secondary" href="eshop_produkt_admin.php">Zrušit</a><?php endif; ?>
    </div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger" role="alert"><?=shopAdminH($error)?></div><?php endforeach; ?>
    <?php if ($flash!==''): ?><div class="alert alert-success" role="status"><?=shopAdminH($flash)?></div><?php endif; ?>
    <?php require __DIR__.'/shop_admin_templates.php'; ?>
  </section>
</main>
<script nonce="<?=shopAdminH(app_csp_nonce())?>">window.shopAdminDefinitions=<?=json_encode($definitions,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?>;</script>
<script nonce="<?=shopAdminH(app_csp_nonce())?>" src="<?=shopAdminH(appUiUrl('assets/shop-admin.js'))?>?v=20261020"></script>
</body></html>
