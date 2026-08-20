<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_category.php';

function shopCategoryAdminDecision(int$actorId,string&$reason,bool$confirmed):void
{
    $reason=trim($reason);
    if($actorId<1)throw new InvalidArgumentException('Chybí správce změny.');
    if(!$confirmed)throw new InvalidArgumentException('Potvrďte auditovanou změnu kategorie.');
    if(mb_strlen($reason,'UTF-8')<3||mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Důvod musí mít 3 až 1000 znaků.');
}

/** @return array<string,mixed>|null */
function shopCategoryAdminMeta(PDO$pdo,string$path,bool$lock=false):?array
{
    $sql='SELECT * FROM shop_category_meta WHERE category_path=?';
    if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$path]);$row=$statement->fetch(PDO::FETCH_ASSOC);
    return$row?:null;
}

/** @param array<string,mixed>|null $before @param array<string,mixed> $after */
function shopCategoryAdminEvent(PDO$pdo,string$path,int$actorId,string$action,?array$before,array$after,string$reason):void
{
    if(!$pdo->inTransaction())throw new LogicException('Audit kategorie vyžaduje transakci.');
    $pdo->prepare('INSERT INTO shop_category_meta_events(category_path,actor_type,actor_id,action,before_json,after_json,reason) '
        . "VALUES(?,'trainer',?,?,?,?,?)")->execute([$path,$actorId,$action,$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$reason]);
}

/** @param array<string,mixed> $input @return array<string,mixed> */
function shopCategoryAdminInput(array$input):array
{
    $path=shopCategoryPath((string)($input['category_path']??''));
    $name=trim((string)($input['display_name']??''));
    if($name===''||mb_strlen($name,'UTF-8')>255||preg_match('/[\x00-\x1F\x7F]/',$name)===1)throw new InvalidArgumentException('Zobrazovaný název musí mít 1 až 255 znaků.');
    $parentRaw=trim((string)($input['parent_path']??''));
    $parent=$parentRaw==='__ROOT__'?null:($parentRaw===''?shopCategoryDerivedParent($path):shopCategoryPath($parentRaw));
    if($parent===$path)throw new InvalidArgumentException('Kategorie nemůže být vlastním rodičem.');
    $sort=filter_var($input['sort_order']??0,FILTER_VALIDATE_INT);
    if($sort===false||$sort<-100000||$sort>100000)throw new InvalidArgumentException('Pořadí musí být celé číslo od -100000 do 100000.');
    $description=trim((string)($input['description']??''));
    if(mb_strlen($description,'UTF-8')>10000)throw new InvalidArgumentException('Popis kategorie je příliš dlouhý.');
    return['category_path'=>$path,'display_name'=>$name,'parent_path'=>$parent,'sort_order'=>(int)$sort,'visible_in_menu'=>($input['visible_in_menu']??'')==='1'?1:0,'description'=>$description===''?null:$description];
}

/** @param array<string,mixed> $values */
function shopCategoryAdminAssertHierarchy(PDO$pdo,array$values):void
{
    $nodes=shopCategoryNodes($pdo);$path=(string)$values['category_path'];$parent=$values['parent_path'];
    if($parent!==null&&!isset($nodes[$parent])){
        if($parent!==shopCategoryDerivedParent($path))throw new ShopCategoryException('Vybraný rodič neexistuje. Nejprve jej založte nebo použijte odvozenou cestu.');
        foreach(shopCategoryPrefixes($path)as$prefix){
            if(isset($nodes[$prefix]))continue;
            $leaf=explode(' > ',$prefix);$leaf=(string)end($leaf);
            $nodes[$prefix]=['category_path'=>$prefix,'display_name'=>$leaf,'parent_path'=>shopCategoryDerivedParent($prefix),'sort_order'=>PHP_INT_MAX,'visible_in_menu'=>1,'description'=>null,'has_metadata'=>false];
        }
    }
    $nodes[$path]=array_merge($nodes[$path]??[],$values,['has_metadata'=>true]);
    $seen=[];$cursor=$path;
    while($cursor!==null){if(isset($seen[$cursor]))throw new ShopCategoryException('Hierarchie kategorií obsahuje cyklus.');$seen[$cursor]=true;$cursor=$nodes[$cursor]['parent_path']??null;}
}

/** @param array<string,mixed> $input @return array{category_path:string,changed:bool} */
function shopCategoryAdminSave(PDO$pdo,int$actorId,array$input,string$reason,bool$confirmed):array
{
    shopCategoryAdminDecision($actorId,$reason,$confirmed);$values=shopCategoryAdminInput($input);
    $pdo->beginTransaction();
    try{$path=(string)$values['category_path'];$before=shopCategoryAdminMeta($pdo,$path,true);shopCategoryAdminAssertHierarchy($pdo,$values);
        if($before===null){$pdo->prepare('INSERT INTO shop_category_meta(category_path,display_name,parent_path,sort_order,visible_in_menu,description) VALUES(?,?,?,?,?,?)')->execute(array_values($values));$action='create';}
        else{
            $changed=false;foreach(['display_name','parent_path','sort_order','visible_in_menu','description']as$key)if((string)($before[$key]??'')!==(string)($values[$key]??''))$changed=true;
            if(!$changed){$pdo->commit();return['category_path'=>$path,'changed'=>false];}
            $pdo->prepare('UPDATE shop_category_meta SET display_name=?,parent_path=?,sort_order=?,visible_in_menu=?,description=?,updated_at=CURRENT_TIMESTAMP WHERE category_path=?')->execute([$values['display_name'],$values['parent_path'],$values['sort_order'],$values['visible_in_menu'],$values['description'],$path]);$action='update';
        }
        $after=shopCategoryAdminMeta($pdo,$path,false);if($after===null)throw new LogicException('Kategorie po uložení chybí.');
        shopCategoryAdminEvent($pdo,$path,$actorId,$action,$before,$after,$reason);
        $pdo->commit();return['category_path'=>$path,'changed'=>true];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopCategoryException)throw$exception;throw new ShopCategoryException('Kategorii se nepodařilo uložit bez částečného zápisu.',0,$exception);}
}

/** @return array{category_path:string,changed:bool} */
function shopCategoryAdminDelete(PDO$pdo,int$actorId,string$rawPath,string$reason,bool$confirmed):array
{
    shopCategoryAdminDecision($actorId,$reason,$confirmed);$path=shopCategoryPath($rawPath);$pdo->beginTransaction();
    try{$before=shopCategoryAdminMeta($pdo,$path,true);if($before===null)throw new ShopCategoryException('Kategorie nemá metadata, která by šla odstranit.');
        $products=$pdo->prepare('SELECT COUNT(*) FROM shop_product_categories WHERE category_path=?');$products->execute([$path]);
        $rules=$pdo->prepare('SELECT COUNT(*) FROM shop_member_category_rules WHERE category_path=?');$rules->execute([$path]);
        $children=$pdo->prepare('SELECT COUNT(*) FROM shop_category_meta WHERE parent_path=?');$children->execute([$path]);
        if((int)$products->fetchColumn()>0||(int)$rules->fetchColumn()>0)throw new ShopCategoryException('Kategorii používá produkt nebo cenové pravidlo. Skryjte ji z menu, nemažte ji.');
        if((int)$children->fetchColumn()>0)throw new ShopCategoryException('Kategorie má podkategorie. Nejprve je přesuňte nebo skryjte.');
        shopCategoryAdminEvent($pdo,$path,$actorId,'delete',$before,['deleted'=>true],$reason);
        $pdo->prepare('DELETE FROM shop_category_meta WHERE category_path=?')->execute([$path]);$pdo->commit();return['category_path'=>$path,'changed'=>true];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopCategoryException)throw$exception;throw new ShopCategoryException('Kategorii se nepodařilo odstranit bez částečného zápisu.',0,$exception);}
}

/** @param list<string> $rawPaths @return array{product_id:int,changed:bool} */
function shopCategoryAdminAssignProduct(PDO$pdo,int$actorId,int$productId,array$rawPaths,string$rawDefault,string$reason,bool$confirmed):array
{
    shopCategoryAdminDecision($actorId,$reason,$confirmed);if($productId<1)throw new InvalidArgumentException('Produkt nebyl vybrán.');
    $paths=[];foreach($rawPaths as$raw){$path=shopCategoryPath((string)$raw);if(!in_array($path,$paths,true))$paths[]=$path;}
    $default=trim($rawDefault)===''?null:shopCategoryPath($rawDefault);
    if($default!==null&&!in_array($default,$paths,true))throw new InvalidArgumentException('Výchozí kategorie musí být mezi přiřazenými kategoriemi.');
    if($paths!==[]&&$default===null)throw new InvalidArgumentException('Vyberte jednu výchozí kategorii.');
    $nodes=shopCategoryNodes($pdo);foreach($paths as$path)if(!isset($nodes[$path]))throw new ShopCategoryException('Kategorie „'.$path.'“ neexistuje.');
    $pdo->beginTransaction();
    try{$sql='SELECT id FROM shop_products WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$product=$pdo->prepare($sql);$product->execute([$productId]);if(!(bool)$product->fetchColumn())throw new ShopCategoryException('Produkt nebyl nalezen.');
        $beforeStatement=$pdo->prepare('SELECT category_path,is_default,sort_order FROM shop_product_categories WHERE product_id=? ORDER BY is_default DESC,sort_order,id');$beforeStatement->execute([$productId]);$before=array_map(static fn(array$row):array=>['category_path'=>(string)$row['category_path'],'is_default'=>(int)$row['is_default'],'sort_order'=>(int)$row['sort_order']],$beforeStatement->fetchAll(PDO::FETCH_ASSOC));
        $desired=[];foreach($paths as$index=>$path)$desired[]=['category_path'=>$path,'is_default'=>$path===$default?1:0,'sort_order'=>$index];
        usort($desired,static fn(array$a,array$b):int=>($b['is_default']<=>$a['is_default'])?:($a['sort_order']<=>$b['sort_order']));
        if($before===$desired){$pdo->commit();return['product_id'=>$productId,'changed'=>false];}
        $pdo->prepare('DELETE FROM shop_product_categories WHERE product_id=?')->execute([$productId]);$insert=$pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,?,?,?)');
        foreach($paths as$index=>$path)$insert->execute([$productId,$path,$path===$default?1:0,$index]);
        $afterStatement=$pdo->prepare('SELECT category_path,is_default,sort_order FROM shop_product_categories WHERE product_id=? ORDER BY is_default DESC,sort_order,id');$afterStatement->execute([$productId]);$after=array_map(static fn(array$row):array=>['category_path'=>(string)$row['category_path'],'is_default'=>(int)$row['is_default'],'sort_order'=>(int)$row['sort_order']],$afterStatement->fetchAll(PDO::FETCH_ASSOC));
        $pdo->prepare('INSERT INTO shop_catalog_admin_events(product_id,variant_id,actor_type,actor_id,action,before_json,after_json,reason) '
            . "VALUES(?,NULL,'trainer',?,'assign_categories',?,?,?)")->execute([$productId,$actorId,json_encode(['categories'=>$before],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode(['categories'=>$after],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();return['product_id'=>$productId,'changed'=>true];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopCategoryException)throw$exception;throw new ShopCategoryException('Kategorie produktu se nepodařilo změnit bez částečného zápisu.',0,$exception);}
}

/** @return list<array<string,mixed>> */
function shopCategoryAdminOverview(PDO$pdo):array
{
    $nodes=shopCategoryNodes($pdo);$productCounts=[];$ruleCounts=[];
    foreach($pdo->query('SELECT category_path,COUNT(DISTINCT product_id) count_value FROM shop_product_categories GROUP BY category_path')->fetchAll(PDO::FETCH_ASSOC)as$row)$productCounts[(string)$row['category_path']]=(int)$row['count_value'];
    foreach($pdo->query('SELECT category_path,COUNT(*) count_value FROM shop_member_category_rules GROUP BY category_path')->fetchAll(PDO::FETCH_ASSOC)as$row)$ruleCounts[(string)$row['category_path']]=(int)$row['count_value'];
    $rows=[];foreach(shopCategoryTreeOrder($nodes)as$row){$path=(string)$row['category_path'];$row['product_count']=$productCounts[$path]??0;$row['rule_count']=$ruleCounts[$path]??0;$rows[]=$row;}return$rows;
}
