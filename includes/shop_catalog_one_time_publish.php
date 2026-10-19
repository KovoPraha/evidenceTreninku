<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_catalog_one_time_sync.php';
require_once __DIR__ . '/shop_catalog_publication.php';

final class ShopCatalogOneTimePublishException extends RuntimeException {}

const SHOP_CATALOG_ONE_TIME_PUBLISH_CONFIRM = 'ZVEREJNIT-SHOPTET-KATALOG';

/** @return array<string,mixed> */
function shopCatalogOneTimePublishPreview(
    PDO $pdo,
    string $sourceSha256,
    int $expectedProducts,
    int $expectedVisibleProducts,
    int $expectedHiddenProducts,
    int $expectedActiveVariants
): array {
    if (preg_match('/^[a-f0-9]{64}$/D', $sourceSha256) !== 1) {
        throw new ShopCatalogOneTimePublishException('Otisk zdrojového katalogu není platný.');
    }
    foreach ([$expectedProducts,$expectedVisibleProducts,$expectedHiddenProducts,$expectedActiveVariants] as $expected) {
        if ($expected < 0) throw new ShopCatalogOneTimePublishException('Očekávané počty nesmějí být záporné.');
    }
    $runStatement=$pdo->prepare('SELECT * FROM shop_catalog_import_runs WHERE source_sha256=? AND status=\'promoted\'');
    $runStatement->execute([$sourceSha256]);$run=$runStatement->fetch(PDO::FETCH_ASSOC);
    if(!$run)return['ok'=>false,'source_sha256'=>$sourceSha256,'blockers'=>['promoted_source_run_not_found'],'database_writes'=>0];
    $runId=(int)$run['id'];
    $productsStatement=$pdo->prepare("SELECT p.*,pub.status AS publication_status,pub.public_name,pub.public_summary FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.source_run_id=? AND p.origin='import' AND p.external_product_key LIKE 'shoptet:%' ORDER BY p.external_product_key");
    $productsStatement->execute([$runId]);$products=$productsStatement->fetchAll(PDO::FETCH_ASSOC);
    $variantsStatement=$pdo->prepare("SELECT v.*,p.external_product_key,p.visibility AS product_visibility FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.source_run_id=? AND p.origin='import' AND v.origin='import' ORDER BY p.external_product_key,v.id");
    $variantsStatement->execute([$runId]);$variants=$variantsStatement->fetchAll(PDO::FETCH_ASSOC);
    $variantsByProduct=[];foreach($variants as$variant)$variantsByProduct[(int)$variant['product_id']][]=$variant;
    $visibleProducts=[];$hiddenProducts=[];$visibleVariants=0;$invalidKeys=[];$invalidProducts=[];$invalidImages=0;$nonGoods=0;$freePrices=0;
    foreach($products as$product){
        $key=(string)$product['external_product_key'];if(preg_match('/^shoptet:pair:[0-9]+$/D',$key)!==1)$invalidKeys[]=$key;
        $sourceVisible=shopCatalogOneTimeVisible($product['visibility']??null);$sourceVisible?$visibleProducts[]=$product:$hiddenProducts[]=$product;
        if(!$sourceVisible)continue;
        if((string)$product['offer_type']!=='goods')$nonGoods++;
        $productVisibleVariants=0;
        foreach($variantsByProduct[(int)$product['id']]??[]as$variant){
            if($variant['visible']!==null&&(int)$variant['visible']!==1)continue;$productVisibleVariants++;$visibleVariants++;
            $mode=(string)$variant['price_mode'];$amount=$variant['amount_minor'];$currency=(string)$variant['currency'];
            if($mode==='free'){$freePrices++;if($amount!==null&&(int)$amount!==0)$invalidProducts[]=$key;}
            elseif($mode!=='fixed'||$amount===null||(int)$amount<0)$invalidProducts[]=$key;
            if($currency!=='CZK')$invalidProducts[]=$key;
        }
        if($productVisibleVariants===0||trim((string)$product['name'])==='')$invalidProducts[]=$key;
        $imageStatement=$pdo->prepare('SELECT image_url FROM shop_product_images WHERE product_id=?');$imageStatement->execute([(int)$product['id']]);
        foreach($imageStatement->fetchAll(PDO::FETCH_COLUMN)as$imageUrl)if(preg_match('~^uploads/shop-products/[a-f0-9]{32}\.jpg$~D',trim((string)$imageUrl))!==1)$invalidImages++;
    }
    $activeProducts=count(array_filter($products,static fn(array$p):bool=>(string)$p['catalog_status']==='active'));
    $activePublications=count(array_filter($products,static fn(array$p):bool=>(string)($p['publication_status']??'')==='active'));
    $hiddenActive=count(array_filter($hiddenProducts,static fn(array$p):bool=>(string)$p['catalog_status']==='active'||(string)($p['publication_status']??'')==='active'));
    $blockers=[];
    if(count($products)!==$expectedProducts)$blockers[]='unexpected_product_count';
    if(count($visibleProducts)!==$expectedVisibleProducts)$blockers[]='unexpected_visible_product_count';
    if(count($hiddenProducts)!==$expectedHiddenProducts)$blockers[]='unexpected_hidden_product_count';
    if($visibleVariants!==$expectedActiveVariants)$blockers[]='unexpected_visible_variant_count';
    if((int)$run['product_count']!==$expectedProducts||count($variants)!==(int)$run['variant_count'])$blockers[]='canonical_run_count_mismatch';
    if($invalidKeys!==[])$blockers[]='invalid_external_product_keys';
    if($invalidProducts!==[])$blockers[]='products_not_publication_ready';
    if($invalidImages>0)$blockers[]='invalid_product_images';
    if($hiddenActive>0)$blockers[]='hidden_products_already_active';
    return[
        'ok'=>$blockers===[],'source_sha256'=>$sourceSha256,'run_id'=>$runId,'imported_products'=>count($products),'imported_variants'=>count($variants),
        'source_visible_products'=>count($visibleProducts),'source_hidden_products'=>count($hiddenProducts),'source_visible_variants'=>$visibleVariants,
        'catalog_active_products_before'=>$activeProducts,'publication_active_products_before'=>$activePublications,
        'normalization'=>['offer_types_to_goods'=>$nonGoods,'free_prices_to_fixed_zero'=>$freePrices],
        'invalid_keys'=>array_slice(array_values(array_unique($invalidKeys)),0,20),'invalid_products'=>array_slice(array_values(array_unique($invalidProducts)),0,20),
        'invalid_images'=>$invalidImages,'blockers'=>$blockers,'database_writes'=>0,
    ];
}

function shopCatalogOneTimePublishSummary(array$product):string
{
    $value=trim((string)($product['short_description']??''));if($value==='')$value=trim((string)($product['description_html_untrusted']??''));if($value==='')$value=(string)$product['name'];
    $value=html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');$value=(string)preg_replace('/\s+/u',' ',trim($value));
    if($value==='')$value='Nabídka KOVO Praha.';return mb_substr($value,0,1000,'UTF-8');
}

/** @return array<string,int> */
function shopCatalogOneTimePublishVerification(PDO$pdo,int$runId):array
{
    $queries=[
        'active_products'=>"SELECT COUNT(*) FROM shop_products WHERE source_run_id=? AND origin='import' AND catalog_status='active'",
        'active_publications'=>"SELECT COUNT(*) FROM shop_product_publications pub JOIN shop_products p ON p.id=pub.product_id WHERE p.source_run_id=? AND p.origin='import' AND pub.status='active'",
        'active_variants'=>"SELECT COUNT(*) FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.source_run_id=? AND p.origin='import' AND v.catalog_status='active'",
        'hidden_active_products'=>"SELECT COUNT(*) FROM shop_products WHERE source_run_id=? AND origin='import' AND catalog_status='active' AND LOWER(TRIM(COALESCE(visibility,''))) IN ('hidden','private','false','no','0')",
        'active_non_goods'=>"SELECT COUNT(*) FROM shop_products WHERE source_run_id=? AND origin='import' AND catalog_status='active' AND offer_type<>'goods'",
        'public_storefront_products'=>"SELECT COUNT(DISTINCT p.id) FROM shop_products p JOIN shop_product_publications pub ON pub.product_id=p.id JOIN shop_variants v ON v.product_id=p.id WHERE p.source_run_id=? AND p.origin='import' AND p.offer_type='goods' AND p.catalog_status='active' AND pub.status='active' AND v.catalog_status='active' AND (v.visible=1 OR v.visible IS NULL) AND v.price_mode='fixed' AND v.amount_minor>=0 AND v.currency='CZK'",
    ];
    $result=[];foreach($queries as$key=>$sql){$statement=$pdo->prepare($sql);$statement->execute([$runId]);$result[$key]=(int)$statement->fetchColumn();}return$result;
}

/** @return array<string,mixed> */
function shopCatalogOneTimePublishApply(PDO$pdo,string$sourceSha256,int$expectedProducts,int$expectedVisibleProducts,int$expectedHiddenProducts,int$expectedActiveVariants,string$confirmation):array
{
    if(!hash_equals(SHOP_CATALOG_ONE_TIME_PUBLISH_CONFIRM,$confirmation))throw new ShopCatalogOneTimePublishException('Zveřejnění nemá přesné potvrzení.');
    $preview=shopCatalogOneTimePublishPreview($pdo,$sourceSha256,$expectedProducts,$expectedVisibleProducts,$expectedHiddenProducts,$expectedActiveVariants);
    if(!$preview['ok'])throw new ShopCatalogOneTimePublishException('Předletová kontrola zveřejnění selhala: '.implode(', ',$preview['blockers']));
    $actorId=shopCatalogOneTimeActor($pdo);$runId=(int)$preview['run_id'];$changed=0;$unchanged=0;$normalizedTypes=0;$normalizedPrices=0;
    $pdo->beginTransaction();
    try{
        $runSql='SELECT status FROM shop_catalog_import_runs WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$runSql.=' FOR UPDATE';$runLock=$pdo->prepare($runSql);$runLock->execute([$runId]);if((string)$runLock->fetchColumn()!=='promoted')throw new ShopCatalogOneTimePublishException('Zdrojový import už není v očekávaném stavu.');
        $productSql="SELECT p.*,pub.status AS publication_status,pub.public_name,pub.public_summary FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.source_run_id=? AND p.origin='import' AND p.external_product_key LIKE 'shoptet:%' ORDER BY p.external_product_key";if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$productSql.=' FOR UPDATE';$products=$pdo->prepare($productSql);$products->execute([$runId]);
        foreach($products->fetchAll(PDO::FETCH_ASSOC)as$product){if(!shopCatalogOneTimeVisible($product['visibility']??null))continue;$productId=(int)$product['id'];
            if((string)$product['offer_type']!=='goods'){$pdo->prepare("UPDATE shop_products SET offer_type='goods',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$productId]);$normalizedTypes++;}
            $free=$pdo->prepare("UPDATE shop_variants SET price_mode='fixed',amount_minor=0,updated_at=CURRENT_TIMESTAMP WHERE product_id=? AND origin='import' AND price_mode='free'");$free->execute([$productId]);$normalizedPrices+=$free->rowCount();
            if((string)$product['catalog_status']==='active'&&(string)($product['publication_status']??'')==='active'){$unchanged++;continue;}
            $result=shopCatalogPublicationActivateInTransaction($pdo,$productId,$actorId,(string)$product['name'],shopCatalogOneTimePublishSummary($product),'Jednorázové zveřejnění viditelné nabídky z potvrzeného exportu Shoptet.',true);$result['changed']?$changed++:$unchanged++;
        }
        $verification=shopCatalogOneTimePublishVerification($pdo,$runId);
        if($verification['active_products']!==$expectedVisibleProducts||$verification['active_publications']!==$expectedVisibleProducts||$verification['active_variants']!==$expectedActiveVariants||$verification['hidden_active_products']!==0||$verification['active_non_goods']!==0||$verification['public_storefront_products']!==$expectedVisibleProducts)throw new ShopCatalogOneTimePublishException('Kontrola výsledku zveřejnění neodpovídá očekávanému katalogu.');
        $pdo->commit();return array_merge($preview,['ok'=>true,'applied'=>true,'products_changed'=>$changed,'products_unchanged'=>$unchanged,'offer_types_normalized'=>$normalizedTypes,'prices_normalized'=>$normalizedPrices,'verification'=>$verification]);
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof ShopCatalogOneTimePublishException||$e instanceof ShopCatalogPublicationException||$e instanceof InvalidArgumentException)throw$e;throw new ShopCatalogOneTimePublishException('Zveřejnění selhalo bez částečného databázového zápisu.',0,$e);}
}
