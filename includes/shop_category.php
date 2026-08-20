<?php
declare(strict_types=1);

final class ShopCategoryException extends RuntimeException {}

function shopCategoryPath(string $value): string
{
    $value=trim($value);
    if($value===''||strlen($value)>500||preg_match('/[\x00-\x1F\x7F]/',$value)===1){
        throw new InvalidArgumentException('Cesta kategorie musí mít 1 až 500 znaků bez řídicích znaků.');
    }
    $parts=preg_split('/\s*>\s*/u',$value)?:[];
    $parts=array_map('trim',$parts);
    if($parts===[]||in_array('',$parts,true))throw new InvalidArgumentException('Cesta kategorie obsahuje prázdnou úroveň.');
    $normalized=implode(' > ',$parts);
    if(strlen($normalized)>500)throw new InvalidArgumentException('Normalizovaná cesta kategorie je příliš dlouhá.');
    return $normalized;
}

function shopCategoryDerivedParent(string $path): ?string
{
    $parts=explode(' > ',shopCategoryPath($path));
    if(count($parts)<2)return null;
    array_pop($parts);
    return implode(' > ',$parts);
}

/** @return list<string> */
function shopCategoryPrefixes(string $path): array
{
    $parts=explode(' > ',shopCategoryPath($path));$prefixes=[];$current=[];
    foreach($parts as$part){$current[]=$part;$prefixes[]=implode(' > ',$current);}
    return$prefixes;
}

/** @return array<string,array<string,mixed>> */
function shopCategoryNodes(PDO $pdo): array
{
    $paths=[];
    foreach(['SELECT DISTINCT category_path FROM shop_product_categories','SELECT DISTINCT category_path FROM shop_member_category_rules']as$sql){
        foreach($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)as$raw){
            try{$path=shopCategoryPath((string)$raw);}catch(InvalidArgumentException){continue;}
            foreach(shopCategoryPrefixes($path)as$prefix)$paths[$prefix]=true;
        }
    }
    $metadata=[];
    foreach($pdo->query('SELECT * FROM shop_category_meta')->fetchAll(PDO::FETCH_ASSOC)as$row){
        try{$path=shopCategoryPath((string)$row['category_path']);}catch(InvalidArgumentException){continue;}
        $metadata[$path]=$row;$paths[$path]=true;
        if($row['parent_path']!==null&&trim((string)$row['parent_path'])!==''){
            try{$parent=shopCategoryPath((string)$row['parent_path']);foreach(shopCategoryPrefixes($parent)as$prefix)$paths[$prefix]=true;}
            catch(InvalidArgumentException){}
        }
    }
    $nodes=[];
    foreach(array_keys($paths)as$path){
        $meta=$metadata[$path]??null;
        $parent=$meta!==null?($meta['parent_path']===null?null:shopCategoryPath((string)$meta['parent_path'])):shopCategoryDerivedParent($path);
        $leaf=explode(' > ',$path);$leaf=(string)end($leaf);
        $nodes[$path]=[
            'category_path'=>$path,
            'display_name'=>$meta!==null?(string)$meta['display_name']:$leaf,
            'parent_path'=>$parent,
            'sort_order'=>$meta!==null?(int)$meta['sort_order']:PHP_INT_MAX,
            'visible_in_menu'=>$meta!==null?(int)$meta['visible_in_menu']:1,
            'description'=>$meta['description']??null,
            'has_metadata'=>$meta!==null,
        ];
    }
    return$nodes;
}

/** @param array<string,array<string,mixed>> $nodes @return list<string> */
function shopCategoryDescendants(array $nodes,string $selectedPath):array
{
    $selectedPath=shopCategoryPath($selectedPath);
    if(!isset($nodes[$selectedPath]))return[];
    $result=[];$queue=[$selectedPath];
    while($queue!==[]){$path=array_shift($queue);if(in_array($path,$result,true))continue;$result[]=$path;
        foreach($nodes as$childPath=>$node)if(($node['parent_path']??null)===$path)$queue[]=$childPath;
    }
    return$result;
}

/** @param array<string,array<string,mixed>> $nodes @return list<array<string,mixed>> */
function shopCategoryTreeOrder(array $nodes):array
{
    $children=[];
    foreach($nodes as$path=>$node){$parent=$node['parent_path']??null;if($parent!==null&&!isset($nodes[$parent]))$parent=null;$children[$parent??''][]=$path;}
    $sort=static function(array &$paths)use($nodes):void{usort($paths,static function(string$a,string$b)use($nodes):int{
        $order=($nodes[$a]['sort_order']<=>$nodes[$b]['sort_order']);
        return$order!==0?$order:(strnatcasecmp((string)$nodes[$a]['display_name'],(string)$nodes[$b]['display_name'])?:strcmp($a,$b));
    });};
    foreach($children as&$paths)$sort($paths);unset($paths);
    $ordered=[];$visited=[];
    $walk=function(string$parent,int$depth)use(&$walk,&$ordered,&$visited,$children,$nodes):void{
        foreach($children[$parent]??[]as$path){if(isset($visited[$path]))continue;$visited[$path]=true;$node=$nodes[$path];$node['depth']=$depth;$ordered[]=$node;$walk($path,$depth+1);}
    };
    $walk('',0);
    foreach($nodes as$path=>$node)if(!isset($visited[$path])){$node['depth']=0;$ordered[]=$node;}
    return$ordered;
}

/** @param list<array<string,mixed>> $products @return list<array<string,mixed>> */
function shopStorefrontCategoryMenu(PDO $pdo,array $products):array
{
    $nodes=shopCategoryNodes($pdo);$direct=[];
    foreach($products as$product)foreach(($product['categories']??[])as$path){
        try{$path=shopCategoryPath((string)$path);}catch(InvalidArgumentException){continue;}
        $direct[$path][(int)($product['product_id']??0)]=true;
    }
    $menu=[];
    foreach(shopCategoryTreeOrder($nodes)as$node){
        if(!(bool)$node['visible_in_menu'])continue;
        $paths=shopCategoryDescendants($nodes,(string)$node['category_path']);$productIds=[];
        foreach($paths as$path)foreach(array_keys($direct[$path]??[])as$productId)$productIds[$productId]=true;
        $count=count($productIds);
        if($count<1)continue;$node['product_count']=$count;$node['descendant_paths']=$paths;$menu[]=$node;
    }
    return$menu;
}
