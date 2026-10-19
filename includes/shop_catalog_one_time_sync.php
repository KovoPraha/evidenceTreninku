<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_catalog_stage.php';
require_once __DIR__ . '/shop_catalog_review.php';
require_once __DIR__ . '/shop_manual_catalog.php';

final class ShopCatalogOneTimeSyncException extends RuntimeException {}

const SHOP_CATALOG_ONE_TIME_CONFIRM = 'IMPORTOVAT-ESHOP-JEDNORAZOVE';
const SHOP_CATALOG_ASSET_MANIFEST_VERSION = 'evidence.shop.catalog-assets.v1';

/** @return array<string,mixed> */
function shopCatalogOneTimeManifest(string $path, string $sourceSha256): array
{
    if (!is_file($path) || is_link($path)) {
        throw new ShopCatalogOneTimeSyncException('Manifest obrázků nebyl nalezen.');
    }
    $raw = file_get_contents($path);
    try {
        $manifest = is_string($raw) ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null;
    } catch (JsonException $exception) {
        throw new ShopCatalogOneTimeSyncException('Manifest obrázků není čitelný.', 0, $exception);
    }
    if (!is_array($manifest)
        || ($manifest['version'] ?? null) !== SHOP_CATALOG_ASSET_MANIFEST_VERSION
        || !hash_equals($sourceSha256, (string)($manifest['source_sha256'] ?? ''))
        || !is_array($manifest['images'] ?? null)
    ) {
        throw new ShopCatalogOneTimeSyncException('Manifest obrázků neodpovídá zdrojovému exportu.');
    }
    $manifest['_directory'] = dirname(realpath($path) ?: $path);
    return $manifest;
}

/** @return array{path:string,image_url:string,sha256:string} */
function shopCatalogOneTimeManifestImage(array $manifest, string $sourceUrl): array
{
    $row = $manifest['images'][$sourceUrl] ?? null;
    if (!is_array($row)) {
        throw new ShopCatalogOneTimeSyncException('Manifest neobsahuje všechny obrázky exportu.');
    }
    $relative = (string)($row['file'] ?? '');
    $sha256 = (string)($row['sha256'] ?? '');
    if (preg_match('~^images/([a-f0-9]{32})\.jpg$~D', $relative, $matches) !== 1
        || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
    ) {
        throw new ShopCatalogOneTimeSyncException('Manifest obsahuje neplatnou cestu nebo otisk obrázku.');
    }
    $base = (string)$manifest['_directory'];
    $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $realBase = realpath($base);
    $realPath = realpath($path);
    if ($realBase === false || $realPath === false || is_link($path)
        || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)
        || !hash_equals($sha256, (string)hash_file('sha256', $realPath))
    ) {
        throw new ShopCatalogOneTimeSyncException('Připravený obrázek neprošel kontrolou integrity.');
    }
    return [
        'path' => $realPath,
        'image_url' => 'uploads/shop-products/' . $matches[1] . '.jpg',
        'sha256' => $sha256,
    ];
}

/** @return list<string> */
function shopCatalogOneTimeSourceImageUrls(array $catalog): array
{
    $urls = [];
    foreach (($catalog['products'] ?? []) as $product) {
        foreach (($product['images'] ?? []) as $url) {
            $url = trim((string)$url);
            if ($url !== '') $urls[$url] = true;
        }
    }
    return array_keys($urls);
}

/** @return array<string,mixed> */
function shopCatalogOneTimePreview(PDO $pdo, array $catalog, array $manifest, array $allowedActiveKeys=[]): array
{
    if (($catalog['summary']['contract_ready'] ?? null) !== true) {
        throw new ShopCatalogOneTimeSyncException('Zdrojový katalog neprošel vstupním kontraktem.');
    }
    $incomingKeys = [];
    $incomingSkus = [];
    $unclassified = [];
    foreach ($catalog['products'] as $product) {
        $key = (string)$product['external_product_key'];
        $incomingKeys[$key] = true;
        if (($product['offer_classification']['type'] ?? null) === ShopOfferClassifier::UNCLASSIFIED) {
            $unclassified[] = $key;
        }
        foreach ($product['variants'] as $variant) $incomingSkus[(string)$variant['sku']] = $key;
    }

    $manualProductCollisions = [];
    $importedMatches = 0;
    if ($incomingKeys !== []) {
        foreach (shopCatalogOneTimeRowsForValues($pdo, 'shop_products', 'external_product_key', array_keys($incomingKeys)) as $row) {
            if ((string)($row['origin'] ?? 'import') === 'import') $importedMatches++;
            else $manualProductCollisions[] = (string)$row['external_product_key'];
        }
    }
    $skuCollisions = [];
    if ($incomingSkus !== []) {
        foreach (shopCatalogOneTimeVariantRowsForSkus($pdo, array_keys($incomingSkus)) as $row) {
            $expectedKey = $incomingSkus[(string)$row['sku']] ?? '';
            if ((string)$row['external_product_key'] !== $expectedKey
                || (string)($row['variant_origin'] ?? 'import') !== 'import'
                || (string)($row['product_origin'] ?? 'import') !== 'import'
            ) {
                $skuCollisions[] = (string)$row['sku'];
            }
        }
    }

    $allowedActiveKeys=shopCatalogOneTimeAllowedActiveKeys($allowedActiveKeys);
    $activeRows=$pdo->query("SELECT external_product_key,name FROM shop_products WHERE origin='import' AND external_product_key LIKE 'shoptet:%' AND catalog_status='active' ORDER BY external_product_key")->fetchAll(PDO::FETCH_ASSOC);
    $activeKeys=array_map(static fn(array$row):string=>(string)$row['external_product_key'],$activeRows);
    $activeImported=count($activeRows);
    $activeImportedVariants=(int)$pdo->query("SELECT COUNT(*) FROM shop_variants v INNER JOIN shop_products p ON p.id=v.product_id WHERE p.origin='import' AND p.external_product_key LIKE 'shoptet:%' AND p.catalog_status='active' AND v.catalog_status='active'")->fetchColumn();
    $existingImported = (int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE origin='import' AND external_product_key LIKE 'shoptet:%'")->fetchColumn();
    $existingManual = (int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE origin='manual'")->fetchColumn();

    $missingAssets = 0;
    foreach (shopCatalogOneTimeSourceImageUrls($catalog) as $url) {
        try { shopCatalogOneTimeManifestImage($manifest, $url); }
        catch (ShopCatalogOneTimeSyncException) { $missingAssets++; }
    }
    $supportedReview = $unclassified === ['shoptet:pair:250'];
    $blockers = [];
    if ($activeKeys !== $allowedActiveKeys) $blockers[] = 'active_imported_products_not_allowlisted';
    if ($manualProductCollisions !== []) $blockers[] = 'manual_product_key_collisions';
    if ($skuCollisions !== []) $blockers[] = 'sku_collisions';
    if ($missingAssets > 0) $blockers[] = 'missing_or_invalid_assets';
    if ($unclassified !== [] && !$supportedReview) $blockers[] = 'unsupported_manual_review';

    return [
        'ok' => $blockers === [],
        'source_sha256' => (string)$catalog['source']['sha256'],
        'incoming_products' => (int)$catalog['summary']['products'],
        'incoming_variants' => (int)$catalog['summary']['variants'],
        'incoming_images' => count(shopCatalogOneTimeSourceImageUrls($catalog)),
        'existing_imported_products' => $existingImported,
        'existing_manual_products' => $existingManual,
        'matching_imported_products' => $importedMatches,
        'active_imported_products' => $activeImported,
        'active_imported_variants' => $activeImportedVariants,
        'active_products' => array_map(static fn(array$row):array=>['external_product_key'=>(string)$row['external_product_key'],'name'=>(string)$row['name']],$activeRows),
        'manual_product_key_collisions' => array_slice(array_values(array_unique($manualProductCollisions)), 0, 20),
        'sku_collisions' => array_slice(array_values(array_unique($skuCollisions)), 0, 20),
        'missing_assets' => $missingAssets,
        'manual_review_keys' => $unclassified,
        'blockers' => $blockers,
        'database_writes' => 0,
    ];
}

/** @return list<array<string,mixed>> */
function shopCatalogOneTimeRowsForValues(PDO $pdo, string $table, string $column, array $values): array
{
    if ($values === []) return [];
    if (!in_array([$table, $column], [
        ['shop_products', 'external_product_key'],
    ], true)) throw new LogicException('Nepovolený katalogový dotaz.');
    $rows = [];
    foreach (array_chunk($values, 300) as $chunk) {
        $statement = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE ' . $column . ' IN ('
            . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $statement->execute($chunk);
        array_push($rows, ...$statement->fetchAll(PDO::FETCH_ASSOC));
    }
    return $rows;
}

/** @return list<array<string,mixed>> */
function shopCatalogOneTimeVariantRowsForSkus(PDO $pdo, array $skus): array
{
    if ($skus === []) return [];
    $rows = [];
    foreach (array_chunk($skus, 300) as $chunk) {
        $statement = $pdo->prepare(
            'SELECT v.sku,v.origin AS variant_origin,p.origin AS product_origin,p.external_product_key '
            . 'FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE v.sku IN ('
            . implode(',', array_fill(0, count($chunk), '?')) . ')'
        );
        $statement->execute($chunk);
        array_push($rows, ...$statement->fetchAll(PDO::FETCH_ASSOC));
    }
    return $rows;
}

function shopCatalogOneTimeActor(PDO $pdo): int
{
    $actor = (int)$pdo->query(
        "SELECT id FROM treneri WHERE aktivni=1 AND role='admin' ORDER BY id LIMIT 1"
    )->fetchColumn();
    if ($actor < 1) throw new ShopCatalogOneTimeSyncException('Produkce nemá aktivního administrátora pro audit importu.');
    return $actor;
}

/** @return list<string> */
function shopCatalogOneTimeAllowedActiveKeys(array$keys):array
{
    $normalized=[];
    foreach($keys as$key){$key=trim((string)$key);if(preg_match('/^shoptet:pair:[0-9]+$/D',$key)!==1)throw new ShopCatalogOneTimeSyncException('Allowlist aktivních produktů obsahuje neplatný klíč.');$normalized[$key]=true;}
    $normalized=array_keys($normalized);sort($normalized,SORT_STRING);return$normalized;
}

/** @return array{copied:int,reused:int} */
function shopCatalogOneTimeInstallAssets(array $catalog, array $manifest, string $applicationRoot): array
{
    $directory = rtrim($applicationRoot, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'shop-products';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new ShopCatalogOneTimeSyncException('Cílové úložiště obrázků nelze připravit.');
    }
    $copied = 0;$reused = 0;
    foreach (shopCatalogOneTimeSourceImageUrls($catalog) as $url) {
        $image = shopCatalogOneTimeManifestImage($manifest, $url);
        $target = $directory . DIRECTORY_SEPARATOR . basename($image['image_url']);
        if (is_file($target)) {
            if (!hash_equals($image['sha256'], (string)hash_file('sha256', $target))) {
                throw new ShopCatalogOneTimeSyncException('Cílový obrázek má stejný název, ale jiný obsah.');
            }
            $reused++;continue;
        }
        $pending = $target . '.pending-' . bin2hex(random_bytes(8));
        if (!copy($image['path'], $pending)
            || !hash_equals($image['sha256'], (string)hash_file('sha256', $pending))
            || !rename($pending, $target)
        ) {
            @unlink($pending);
            throw new ShopCatalogOneTimeSyncException('Obrázek se nepodařilo bezpečně uložit.');
        }
        @chmod($target, 0644);$copied++;
    }
    return ['copied' => $copied, 'reused' => $reused];
}

/** @return array<string,mixed> */
function shopCatalogOneTimeApply(
    PDO $pdo,
    array $catalog,
    array $manifest,
    string $applicationRoot,
    string $confirmation,
    array $allowedActiveKeys=[]
): array {
    if (!hash_equals(SHOP_CATALOG_ONE_TIME_CONFIRM, $confirmation)) {
        throw new ShopCatalogOneTimeSyncException('Jednorázový import nemá přesné potvrzení.');
    }
    $allowedActiveKeys=shopCatalogOneTimeAllowedActiveKeys($allowedActiveKeys);
    $preview = shopCatalogOneTimePreview($pdo, $catalog, $manifest, $allowedActiveKeys);
    if (!$preview['ok']) {
        throw new ShopCatalogOneTimeSyncException('Předletová kontrola import zablokovala: ' . implode(', ', $preview['blockers']));
    }
    $actorId = shopCatalogOneTimeActor($pdo);
    $stage = shopCatalogStage($pdo, $catalog);
    $runId = (int)$stage['run_id'];
    $run = $pdo->prepare('SELECT status FROM shop_catalog_import_runs WHERE id=?');$run->execute([$runId]);
    if ((string)$run->fetchColumn() === 'promoted') {
        return array_merge($preview, ['ok'=>true,'applied'=>false,'idempotent'=>true,'run_id'=>$runId]);
    }
    $pending = $pdo->prepare("SELECT id,external_product_key FROM shop_catalog_product_candidates WHERE run_id=? AND review_status='pending' ORDER BY id");
    $pending->execute([$runId]);$pendingRows=$pending->fetchAll(PDO::FETCH_ASSOC);
    foreach($pendingRows as$row){
        if((string)$row['external_product_key']!=='shoptet:pair:250'){
            throw new ShopCatalogOneTimeSyncException('Import obsahuje nepodporované ruční rozhodnutí.');
        }
        shopCatalogReviewProduct($pdo,$runId,(int)$row['id'],$actorId,'approve',ShopOfferClassifier::GOODS,'Trička jsou zboží; potvrzený jednorázový převod původního e-shopu.');
    }
    if($pendingRows===[]){
        $pdo->prepare("UPDATE shop_catalog_import_runs SET status='ready_for_promotion' WHERE id=? AND status='pending_review'")->execute([$runId]);
    }
    $assets = shopCatalogOneTimeInstallAssets($catalog,$manifest,$applicationRoot);
    $result = shopCatalogOneTimeSynchronizeRun($pdo,$runId,$actorId,$manifest,$allowedActiveKeys);
    $verification = shopCatalogOneTimeVerifyRun($pdo,$runId);
    return array_merge($preview,$result,$assets,['ok'=>true,'applied'=>true,'idempotent'=>false,'verification'=>$verification]);
}

/** @return array<string,mixed> */
function shopCatalogOneTimeSynchronizeRun(PDO $pdo,int $runId,int $actorId,array $manifest,array $allowedActiveKeys=[]):array
{
    $pdo->beginTransaction();
    try{
        $runSql='SELECT * FROM shop_catalog_import_runs WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$runSql.=' FOR UPDATE';
        $s=$pdo->prepare($runSql);$s->execute([$runId]);$run=$s->fetch(PDO::FETCH_ASSOC);
        if(!$run||!in_array((string)$run['status'],['ready_for_promotion','promoted'],true))throw new ShopCatalogOneTimeSyncException('Stagingový běh není připravený k převodu.');
        $existingPromotion=$pdo->prepare('SELECT * FROM shop_catalog_promotions WHERE run_id=?');$existingPromotion->execute([$runId]);$existingPromotion=$existingPromotion->fetch(PDO::FETCH_ASSOC);
        if($existingPromotion&&(string)$existingPromotion['status']==='completed'){$pdo->commit();return['run_id'=>$runId,'products_created'=>0,'products_updated'=>0,'variants_created'=>0,'variants_updated'=>0,'variants_retired'=>0,'products_retired'=>0,'images_linked'=>0];}
        $pdo->prepare("INSERT INTO shop_catalog_promotions(run_id,actor_trainer_id,status) VALUES(?,?,'in_progress')")->execute([$runId,$actorId]);

        $candidates=$pdo->prepare("SELECT * FROM shop_catalog_product_candidates WHERE run_id=? AND review_status IN ('auto_classified','approved') ORDER BY id");$candidates->execute([$runId]);$candidates=$candidates->fetchAll(PDO::FETCH_ASSOC);
        $incomingKeys=[];$counts=['products_created'=>0,'products_updated'=>0,'variants_created'=>0,'variants_updated'=>0,'variants_retired'=>0,'products_retired'=>0,'images_linked'=>0];
        foreach($candidates as$candidate){
            $payload=shopCatalogOneTimeJson((string)$candidate['payload_json']);$key=(string)$candidate['external_product_key'];$incomingKeys[]=$key;
            $offerType=(string)($candidate['reviewed_offer_type']?:$candidate['offer_type']);
            $product=shopCatalogOneTimeLockProductByKey($pdo,$key);$wasActive=$product&&(string)$product['catalog_status']==='active';$productStatus=$wasActive?'active':(shopCatalogOneTimeVisible($payload['visibility']??null)?'draft':'inactive');
            if($product){
                if((string)($product['origin']??'import')!=='import'||($wasActive&&!in_array($key,$allowedActiveKeys,true)))throw new ShopCatalogOneTimeSyncException('Existující produkt nelze jednorázovým importem bezpečně aktualizovat.');
                $pdo->prepare('UPDATE shop_products SET source_candidate_id=?,source_run_id=?,source_pair_code=?,name=?,short_description=?,description_html_untrusted=?,offer_type=?,visibility=?,item_type=?,catalog_status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$candidate['id'],$runId,$candidate['source_pair_code'],(string)$candidate['name'],$payload['short_description']??null,$payload['description_html_untrusted']??null,$offerType,$payload['visibility']??null,$payload['item_type']??null,$productStatus,(int)$product['id']]);
                $productId=(int)$product['id'];$counts['products_updated']++;
            }else{
                $pdo->prepare("INSERT INTO shop_products(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,source_pair_code,name,short_description,description_html_untrusted,offer_type,visibility,item_type,catalog_status) VALUES(?,?,'import',NULL,?,?,?,?,?,?,?,?,?)")->execute([(int)$candidate['id'],$runId,$key,$candidate['source_pair_code'],(string)$candidate['name'],$payload['short_description']??null,$payload['description_html_untrusted']??null,$offerType,$payload['visibility']??null,$payload['item_type']??null,$productStatus]);
                $productId=(int)$pdo->lastInsertId();$counts['products_created']++;
            }
            $pdo->prepare('DELETE FROM shop_product_categories WHERE product_id=?')->execute([$productId]);
            $insertCategory=$pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,?,?,?)');$categorySort=0;$default=trim((string)($payload['default_category_path']??''));
            if($default!=='')$insertCategory->execute([$productId,$default,1,$categorySort++]);
            foreach(($payload['additional_category_paths']??[])as$category){$category=trim((string)$category);if($category!==''&&$category!==$default)$insertCategory->execute([$productId,$category,0,$categorySort++]);}
            $pdo->prepare('DELETE FROM shop_product_images WHERE product_id=?')->execute([$productId]);$insertImage=$pdo->prepare('INSERT INTO shop_product_images(product_id,image_url,sort_order) VALUES(?,?,?)');
            foreach(($payload['images']??[])as$sort=>$url){$image=shopCatalogOneTimeManifestImage($manifest,(string)$url);$insertImage->execute([$productId,$image['image_url'],(int)$sort]);$counts['images_linked']++;}

            $variantCandidates=$pdo->prepare('SELECT * FROM shop_catalog_variant_candidates WHERE product_candidate_id=? ORDER BY id');$variantCandidates->execute([(int)$candidate['id']]);$variantCandidates=$variantCandidates->fetchAll(PDO::FETCH_ASSOC);$incomingVariantIds=[];
            foreach($variantCandidates as$variantCandidate){$variant=shopCatalogOneTimeJson((string)$variantCandidate['payload_json']);$sku=(string)$variantCandidate['sku'];$existing=shopCatalogOneTimeLockVariantBySku($pdo,$sku);$visible=shopCatalogOneTimeBool($variant['visible']??null);$variantStatus=$productStatus==='draft'&&($visible===null||$visible===1)?'draft':'inactive';if($productStatus==='active')$variantStatus=$existing&&$existing['catalog_status']==='active'&&($visible===null||$visible===1)?'active':(($visible===null||$visible===1)?'draft':'inactive');$beforeStock=$existing['stock_quantity_decimal']??null;
                $values=[(int)$variantCandidate['id'],$variant['ean']??null,json_encode($variant['attributes']??[],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)$variantCandidate['price_mode'],$variantCandidate['amount_minor'],$variant['price']['compare_at_amount_minor']??null,$variantCandidate['currency'],shopCatalogOneTimeBool($variant['price']['includes_vat']??null),$variant['price']['vat_rate_basis_points']??null,$variantCandidate['stock_quantity_decimal'],$variant['unit']['code']??null,$variant['stock']['availability_in_stock']??null,$variant['stock']['availability_out_of_stock']??null,shopCatalogOneTimeBool($variant['fulfillment']['free_shipping']??null),shopCatalogOneTimeBool($variant['fulfillment']['free_billing']??null),$visible,$variantStatus];
                if($existing){if((int)$existing['product_id']!==$productId||(string)($existing['origin']??'import')!=='import'||((string)$existing['catalog_status']==='active'&&!$wasActive))throw new ShopCatalogOneTimeSyncException('SKU koliduje s jiným nebo nepovoleným aktivním produktem.');$pdo->prepare('UPDATE shop_variants SET source_candidate_id=?,ean=?,attributes_json=?,price_mode=?,amount_minor=?,compare_at_amount_minor=?,currency=?,includes_vat=?,vat_rate_basis_points=?,stock_quantity_decimal=?,unit_code=?,availability_in_stock=?,availability_out_of_stock=?,free_shipping=?,free_billing=?,visible=?,catalog_status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...$values,(int)$existing['id']]);$variantId=(int)$existing['id'];$counts['variants_updated']++;}
                else{$sql="INSERT INTO shop_variants(product_id,source_candidate_id,origin,created_by_trainer_id,sku,ean,attributes_json,price_mode,amount_minor,compare_at_amount_minor,currency,includes_vat,vat_rate_basis_points,stock_quantity_decimal,unit_code,availability_in_stock,availability_out_of_stock,free_shipping,free_billing,visible,catalog_status) VALUES(?,?,'import',NULL,".implode(',',array_fill(0,17,'?')).')';$pdo->prepare($sql)->execute([$productId,(int)$variantCandidate['id'],$sku,...array_slice($values,1)]);$variantId=(int)$pdo->lastInsertId();$counts['variants_created']++;}
                $incomingVariantIds[]=$variantId;$afterStock=$variantCandidate['stock_quantity_decimal'];if(shopCatalogOneTimeStockComparable($beforeStock)!==shopCatalogOneTimeStockComparable($afterStock)&&$afterStock!==null)shopManualCatalogInventoryMovement($pdo,$variantId,$actorId,$beforeStock,$afterStock,'Jednorázový převod stavu skladu ze Shoptetu.');
            }
            $retired=shopCatalogOneTimeRetireMissingVariants($pdo,$productId,$incomingVariantIds);$counts['variants_retired']+=$retired;
        }
        $counts['products_retired']=shopCatalogOneTimeRetireMissingProducts($pdo,$incomingKeys);
        $variantCount=$pdo->prepare('SELECT COUNT(*) FROM shop_catalog_variant_candidates WHERE run_id=?');$variantCount->execute([$runId]);$variantTotal=(int)$variantCount->fetchColumn();
        $pdo->prepare("UPDATE shop_catalog_promotions SET status='completed',product_count=?,variant_count=?,completed_at=CURRENT_TIMESTAMP WHERE run_id=?")->execute([count($candidates),$variantTotal,$runId]);
        $pdo->prepare("UPDATE shop_catalog_import_runs SET status='promoted',promoted_at=CURRENT_TIMESTAMP,promoted_by=? WHERE id=?")->execute([$actorId,$runId]);
        $pdo->commit();return array_merge(['run_id'=>$runId],$counts);
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof ShopCatalogOneTimeSyncException)throw$e;throw new ShopCatalogOneTimeSyncException('Jednorázový převod selhal bez částečného databázového zápisu.',0,$e);}
}

/** @return array<string,mixed> */
function shopCatalogOneTimeJson(string $json):array{try{$value=json_decode($json,true,32,JSON_THROW_ON_ERROR);}catch(JsonException$e){throw new ShopCatalogOneTimeSyncException('Stagingový payload není platný.',0,$e);}if(!is_array($value))throw new ShopCatalogOneTimeSyncException('Stagingový payload není objekt.');return$value;}
function shopCatalogOneTimeVisible(mixed$value):bool{return!in_array(mb_strtolower(trim((string)$value),'UTF-8'),['hidden','private','false','no','0'],true);}
function shopCatalogOneTimeBool(mixed$value):?int{return$value===null?null:($value?1:0);}
function shopCatalogOneTimeStockComparable(mixed$value):?string{return$value===null?null:shopManualCatalogMicrosDecimal(shopManualCatalogStockMicros((string)$value));}
/** @return array{products:int,variants:int,images:int,remote_images:int,active_products:int,active_variants:int} */
function shopCatalogOneTimeVerifyRun(PDO$pdo,int$runId):array
{
    $queries=[
        'products'=>"SELECT COUNT(*) FROM shop_products WHERE source_run_id=? AND origin='import'",
        'variants'=>"SELECT COUNT(*) FROM shop_variants v INNER JOIN shop_products p ON p.id=v.product_id WHERE p.source_run_id=? AND p.origin='import' AND v.origin='import'",
        'images'=>"SELECT COUNT(*) FROM shop_product_images i INNER JOIN shop_products p ON p.id=i.product_id WHERE p.source_run_id=? AND p.origin='import'",
        'remote_images'=>"SELECT COUNT(*) FROM shop_product_images i INNER JOIN shop_products p ON p.id=i.product_id WHERE p.source_run_id=? AND p.origin='import' AND (i.image_url LIKE 'http://%' OR i.image_url LIKE 'https://%')",
        'active_products'=>"SELECT COUNT(*) FROM shop_products WHERE source_run_id=? AND origin='import' AND catalog_status='active'",
        'active_variants'=>"SELECT COUNT(*) FROM shop_variants v INNER JOIN shop_products p ON p.id=v.product_id WHERE p.source_run_id=? AND p.origin='import' AND v.catalog_status='active'",
    ];
    $result=[];
    foreach($queries as$key=>$sql){$statement=$pdo->prepare($sql);$statement->execute([$runId]);$result[$key]=(int)$statement->fetchColumn();}
    return$result;
}
/** @return array<string,mixed>|false */
function shopCatalogOneTimeLockProductByKey(PDO$pdo,string$key):array|false{$sql='SELECT * FROM shop_products WHERE external_product_key=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$s=$pdo->prepare($sql);$s->execute([$key]);return$s->fetch(PDO::FETCH_ASSOC);}
/** @return array<string,mixed>|false */
function shopCatalogOneTimeLockVariantBySku(PDO$pdo,string$sku):array|false{$sql='SELECT * FROM shop_variants WHERE sku=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$s=$pdo->prepare($sql);$s->execute([$sku]);return$s->fetch(PDO::FETCH_ASSOC);}
function shopCatalogOneTimeRetireMissingVariants(PDO$pdo,int$productId,array$ids):int{$sql="UPDATE shop_variants SET visible=0,catalog_status='inactive',updated_at=CURRENT_TIMESTAMP WHERE product_id=? AND origin='import'";$params=[$productId];if($ids!==[]){$sql.=' AND id NOT IN ('.implode(',',array_fill(0,count($ids),'?')).')';array_push($params,...$ids);} $s=$pdo->prepare($sql);$s->execute($params);return$s->rowCount();}
function shopCatalogOneTimeRetireMissingProducts(PDO$pdo,array$keys):int{$sql="UPDATE shop_products SET catalog_status='inactive',updated_at=CURRENT_TIMESTAMP WHERE origin='import' AND external_product_key LIKE 'shoptet:%'";$params=[];if($keys!==[]){$sql.=' AND external_product_key NOT IN ('.implode(',',array_fill(0,count($keys),'?')).')';$params=$keys;}$s=$pdo->prepare($sql);$s->execute($params);$pdo->exec("UPDATE shop_variants SET visible=0,catalog_status='inactive',updated_at=CURRENT_TIMESTAMP WHERE origin='import' AND product_id IN (SELECT id FROM shop_products WHERE origin='import' AND external_product_key LIKE 'shoptet:%' AND catalog_status='inactive')");return$s->rowCount();}
