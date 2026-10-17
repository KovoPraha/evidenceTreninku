<?php
declare(strict_types=1);

require_once __DIR__.'/app_url.php';

function publicSeoH(mixed $value):string
{
    return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function publicSeoIsTestContent(string $name):bool
{
    return preg_match('/^TEST\s*[-–—]/iu',trim($name))===1;
}

function publicSeoDescription(string $description,int $limit=160):string
{
    $description=trim(preg_replace('/\s+/u',' ',strip_tags($description))??'');
    if(mb_strlen($description,'UTF-8')<=$limit)return $description;
    return rtrim(mb_substr($description,0,$limit-1,'UTF-8'),' ,.;:').'…';
}

/** @param array<string,mixed> $product */
function publicSeoProductDescription(array $product):string
{
    $summary=publicSeoDescription((string)($product['public_summary']??''),500);
    if(mb_strlen($summary,'UTF-8')>=40)return $summary;
    return trim((string)($product['public_name']??''))
        .' – varianty, aktuální cena a dostupnost v e-shopu KOVO Praha.';
}

/**
 * Render only trusted canonical paths from the application itself, never the
 * Host header or an unvalidated return URL. Optional schema must describe
 * the exact visible offer on this page.
 *
 * @param array<string,mixed>|null $schema
 */
function publicSeoHead(string $title,string $description,string $path,?string $imageUrl=null,?array $schema=null,bool $indexable=true):void
{
    if(!$indexable){echo '<meta name="robots" content="noindex,follow">';return;}
    $url=$path===''?appCanonicalBaseUrl().'/':appUrl($path);
    $description=publicSeoDescription($description);
    echo '<meta name="description" content="'.publicSeoH($description).'">';
    echo '<link rel="canonical" href="'.publicSeoH($url).'">';
    echo '<meta property="og:type" content="'.($schema!==null?'product':'website').'">';
    echo '<meta property="og:locale" content="cs_CZ">';
    echo '<meta property="og:site_name" content="KOVO Praha">';
    echo '<meta property="og:title" content="'.publicSeoH($title).'">';
    echo '<meta property="og:description" content="'.publicSeoH($description).'">';
    echo '<meta property="og:url" content="'.publicSeoH($url).'">';
    if($imageUrl!==null&&preg_match('~^https://[^\s<>]+$~iD',$imageUrl)===1)echo '<meta property="og:image" content="'.publicSeoH($imageUrl).'">';
    if($schema!==null){
        $json=json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        echo '<script type="application/ld+json">'.$json.'</script>';
    }
}

/** @param array<string,mixed> $product @return array<string,mixed>|null */
function publicSeoProductSchema(array $product,string $url):?array
{
    if(publicSeoIsTestContent((string)($product['public_name']??'')))return null;
    $offers=[];
    foreach(($product['variants']??[])as$variant){
        if(($variant['offer_type']??null)!=='goods'||(int)($variant['amount_minor']??-1)<0)continue;
        $offers[]=[
            '@type'=>'Offer','url'=>$url,'priceCurrency'=>(string)$variant['currency'],
            'price'=>number_format((int)$variant['amount_minor']/100,2,'.',''),
            'availability'=>!empty($variant['in_stock'])?'https://schema.org/InStock':'https://schema.org/OutOfStock',
            'sku'=>(string)$variant['sku'],
        ];
    }
    if($offers===[])return null;
    $schema=['@context'=>'https://schema.org','@type'=>'Product','@id'=>$url.'#product','name'=>(string)$product['public_name'],'url'=>$url,'offers'=>$offers];
    $description=publicSeoProductDescription($product);
    if($description!=='')$schema['description']=$description;
    $images=array_values(array_filter((array)($product['images']??[]),static fn(mixed $image):bool=>is_string($image)&&preg_match('~^https://[^\s<>]+$~iD',$image)===1));
    if($images!==[])$schema['image']=$images;
    return $schema;
}

/** @param list<array<string,mixed>> $products @return list<string> */
function publicSeoSitemapPaths(array $products):array
{
    $paths=['','booking/cyklisticke_krouzky.php','booking/eshop.php','booking/klubovy_kalendar.php','booking/krouzky.php','booking/treninky.php','booking/kalendar.php','booking/velodrom.php'];
    foreach($products as$product){
        $id=(int)($product['product_id']??0);
        if($id<1||publicSeoIsTestContent((string)($product['public_name']??'')))continue;
        $paths[]='booking/produkt.php?id='.$id;
        foreach((array)($product['categories']??[])as$category){
            if(is_string($category)&&trim($category)!=='')$paths[]='booking/eshop.php?kategorie='.rawurlencode($category);
        }
    }
    return array_values(array_unique($paths));
}
