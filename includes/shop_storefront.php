<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';
require_once __DIR__ . '/app_url.php';

/**
 * Only explicitly published text is returned. The imported HTML description is
 * intentionally absent because it has never passed a public-content review.
 *
 * @return list<array<string,mixed>>
 */
function shopStorefrontCatalog(PDO $pdo): array
{
    $products = [];
    foreach (shopStorefrontProducts($pdo) as $row) {
        $productId = (int)$row['product_id'];
        if (!isset($products[$productId])) {
            $products[$productId] = [
                'product_id' => $productId,
                'public_name' => (string)$row['public_name'],
                'public_summary' => (string)$row['public_summary'],
                'variants' => [],
                'images' => [],
                'categories' => [],
            ];
        }
        $row['in_stock'] = $row['stock_quantity_decimal'] === null
            || (float)$row['stock_quantity_decimal'] > 0.0;
        $products[$productId]['variants'][] = $row;
    }

    if ($products !== []) {
        $images = shopStorefrontImages($pdo, array_keys($products));
        foreach ($images as $productId => $urls) {
            if (isset($products[$productId])) {
                $products[$productId]['images'] = $urls;
            }
        }
        $categories=shopStorefrontCategories($pdo,array_keys($products));
        foreach($categories as$productId=>$paths)if(isset($products[$productId]))$products[$productId]['categories']=$paths;
    }

    return array_values($products);
}

/** @param list<int> $productIds @return array<int,list<string>> */
function shopStorefrontCategories(PDO $pdo,array $productIds):array
{
    $productIds=array_values(array_unique(array_filter(array_map('intval',$productIds),static fn(int$id):bool=>$id>0)));
    if($productIds===[])return[];$marks=implode(',',array_fill(0,count($productIds),'?'));
    $statement=$pdo->prepare("SELECT product_id,category_path FROM shop_product_categories WHERE product_id IN ($marks) ORDER BY product_id,is_default DESC,sort_order,id");
    $statement->execute($productIds);$result=[];
    foreach($statement->fetchAll(PDO::FETCH_ASSOC)as$row){$path=trim((string)$row['category_path']);if($path==='')continue;$id=(int)$row['product_id'];$result[$id]??=[];if(!in_array($path,$result[$id],true))$result[$id][]=$path;}
    return$result;
}

/** @return array<string,mixed>|null */
function shopStorefrontProductDetail(PDO $pdo, int $productId): ?array
{
    if ($productId < 1) {
        return null;
    }
    foreach (shopStorefrontCatalog($pdo) as $product) {
        if ((int)$product['product_id'] === $productId) {
            return $product;
        }
    }
    return null;
}

/**
 * @param list<int> $productIds
 * @return array<int,list<string>>
 */
function shopStorefrontImages(PDO $pdo, array $productIds): array
{
    $productIds = array_values(array_unique(array_filter(
        array_map('intval', $productIds),
        static fn(int $id): bool => $id > 0
    )));
    if ($productIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $statement = $pdo->prepare(
        'SELECT product_id,image_url FROM shop_product_images '
        . "WHERE product_id IN ($placeholders) ORDER BY product_id,sort_order,id"
    );
    $statement->execute($productIds);
    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $url = shopStorefrontSafeImageUrl((string)$row['image_url']);
        if ($url === null) {
            continue;
        }
        $productId = (int)$row['product_id'];
        $result[$productId] ??= [];
        if (!in_array($url, $result[$productId], true)) {
            $result[$productId][] = $url;
        }
    }
    return $result;
}

function shopStorefrontSafeImageUrl(string $url): ?string
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
        return null;
    }
    if (shopStorefrontIsLocalImagePath($url)) {
        return appUrl($url);
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
        return null;
    }
    if (($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    return $url;
}

function shopStorefrontIsLocalImagePath(string $url): bool
{
    return preg_match('~^uploads/shop-products/[a-f0-9]{32}\.jpg$~D',trim($url))===1;
}

function shopStorefrontIsLocalImageUrl(string $url): bool
{
    $prefix=appUrl('uploads/shop-products/');
    if (!str_starts_with($url,$prefix)) return false;
    return preg_match('~^[a-f0-9]{32}\.jpg$~D',substr($url,strlen($prefix)))===1;
}
