<?php
declare(strict_types=1);

final class ShopMemberPricingException extends RuntimeException {}

function shopMemberPricingAvailable(PDO $pdo): bool
{
    static $cache = null;
    $cache ??= new WeakMap();
    if (isset($cache[$pdo])) return true;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('shop_member_category_rules','shop_member_product_prices')");
        $available=(int)$statement->fetchColumn() === 2;if($available)$cache[$pdo]=true;return $available;
    }
    $statement = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('shop_member_category_rules','shop_member_product_prices')");
    $available=(int)$statement->fetchColumn() === 2;if($available)$cache[$pdo]=true;return $available;
}

/** @return list<array{id:int,name:string}> */
function shopMemberPricingEligibleTeams(PDO $pdo, int $accountId, ?string $onDate = null): array
{
    if ($accountId < 1 || !shopMemberPricingAvailable($pdo)) return [];
    $onDate ??= date('Y-m-d');
    $statement = $pdo->prepare(
        "SELECT DISTINCT t.id,t.name FROM account_person_roles r "
        . "JOIN club_roster_members m ON m.sportovec_id=r.sportovec_id "
        . "JOIN club_teams t ON t.id=m.team_id "
        . "WHERE r.account_id=? AND r.status='approved' AND r.relation_role IN ('self','guardian') "
        . "AND DATE(r.valid_from)<=? AND (r.valid_to IS NULL OR DATE(r.valid_to)>=?) "
        . "AND m.status='active' AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) "
        . "AND t.status='active' ORDER BY t.name,t.id"
    );
    $statement->execute([$accountId,$onDate,$onDate,$onDate,$onDate]);
    return array_map(static fn(array $row): array => ['id'=>(int)$row['id'],'name'=>(string)$row['name']], $statement->fetchAll(PDO::FETCH_ASSOC));
}

/** @param array<string,mixed> $variant @return array<string,mixed> */
function shopMemberPriceQuoteForVariant(PDO $pdo, int $accountId, array $variant, ?string $onDate = null): array
{
    $public = (int)($variant['amount_minor'] ?? 0);
    $currency = (string)($variant['currency'] ?? '');
    $productId = (int)($variant['product_id'] ?? 0);
    $base = [
        'public_amount_minor'=>$public,'effective_amount_minor'=>$public,'currency'=>$currency,
        'is_member_price'=>false,'team_id'=>null,'team_name'=>null,'source_type'=>null,'source_label'=>null,
    ];
    if ($accountId < 1 || $productId < 1 || $public < 0 || !shopMemberPricingAvailable($pdo)) return $base;
    $teams = shopMemberPricingEligibleTeams($pdo,$accountId,$onDate);
    if ($teams === []) return $base;
    $teamNames = [];$ids = [];
    foreach ($teams as $team) {$ids[]=(int)$team['id'];$teamNames[(int)$team['id']]=(string)$team['name'];}
    $marks = implode(',',array_fill(0,count($ids),'?'));
    $productPrices = $pdo->prepare("SELECT team_id,amount_minor FROM shop_member_product_prices WHERE active=1 AND product_id=? AND currency=? AND team_id IN ($marks)");
    $productPrices->execute([$productId,$currency,...$ids]);
    $specificTeams=[];$candidates=[];
    foreach ($productPrices->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teamId=(int)$row['team_id'];$specificTeams[$teamId]=true;
        $candidates[]=['amount'=>(int)$row['amount_minor'],'team_id'=>$teamId,'type'=>'product','label'=>'Cena produktu pro soupisku'];
    }
    $categories=$pdo->prepare('SELECT category_path FROM shop_product_categories WHERE product_id=? ORDER BY is_default DESC,sort_order,id');
    $categories->execute([$productId]);$paths=$categories->fetchAll(PDO::FETCH_COLUMN);
    if ($paths !== []) {
        $pathMarks=implode(',',array_fill(0,count($paths),'?'));
        $rules=$pdo->prepare("SELECT team_id,category_path,discount_type,value_minor_or_basis_points,currency FROM shop_member_category_rules WHERE active=1 AND team_id IN ($marks) AND category_path IN ($pathMarks)");
        $rules->execute([...$ids,...$paths]);
        foreach($rules->fetchAll(PDO::FETCH_ASSOC) as $row){
            $teamId=(int)$row['team_id'];if(isset($specificTeams[$teamId]))continue;
            $type=(string)$row['discount_type'];$value=(int)$row['value_minor_or_basis_points'];
            if($type==='percentage')$amount=max(0,$public-intdiv($public*$value,10000));
            elseif($type==='fixed_discount' && (string)$row['currency']===$currency)$amount=max(0,$public-$value);
            else continue;
            $candidates[]=['amount'=>$amount,'team_id'=>$teamId,'type'=>'category','label'=>'Sleva kategorie '.(string)$row['category_path']];
        }
    }
    usort($candidates,static fn(array $a,array $b):int=>[$a['amount'],$a['team_id']]<=>[$b['amount'],$b['team_id']]);
    $winner=$candidates[0]??null;
    if($winner===null || $winner['amount'] >= $public)return $base;
    return array_replace($base,[
        'effective_amount_minor'=>$winner['amount'],'is_member_price'=>true,'team_id'=>$winner['team_id'],
        'team_name'=>$teamNames[$winner['team_id']]??('#'.$winner['team_id']),
        'source_type'=>$winner['type'],'source_label'=>$winner['label'],
    ]);
}

/** @param array<string,mixed> $item */
function shopMemberPriceApplyToItem(PDO $pdo,int $accountId,array &$item,?string $onDate=null):void
{
    $quote=shopMemberPriceQuoteForVariant($pdo,$accountId,$item,$onDate);
    $item['public_amount_minor']=$quote['public_amount_minor'];
    $item['amount_minor']=$quote['effective_amount_minor'];
    $item['member_price']=$quote;
}

/** @return array<string,mixed> */
function shopMemberPricingSetCategoryRule(PDO $pdo,int $teamId,string $categoryPath,string $type,int $value,?string $currency,int $actorId,string $note):array
{
    $categoryPath=trim($categoryPath);$note=trim($note);$currency=$currency!==null?strtoupper(trim($currency)):null;
    if($teamId<1||$actorId<1||$categoryPath===''||strlen($categoryPath)>500||$note===''||strlen($note)>1000)throw new InvalidArgumentException('Vyplňte soupisku, kategorii a důvod změny.');
    if(!in_array($type,['percentage','fixed_discount'],true))throw new InvalidArgumentException('Neplatný typ slevy.');
    if($type==='percentage'&&($value<1||$value>10000))throw new InvalidArgumentException('Procentní sleva musí být větší než 0 a nejvýše 100 %.');
    if($type==='fixed_discount'&&($value<1||$currency===null||preg_match('/^[A-Z]{3}$/D',$currency)!==1))throw new InvalidArgumentException('Pevná sleva musí mít kladnou částku a měnu.');
    if($type==='percentage')$currency=null;
    return shopMemberPricingSave($pdo,'category',$teamId,null,$categoryPath,$type,$value,$currency,$actorId,$note);
}

/** @return array<string,mixed> */
function shopMemberPricingSetProductPrice(PDO $pdo,int $teamId,int $productId,int $amount,string $currency,int $actorId,string $note):array
{
    $currency=strtoupper(trim($currency));$note=trim($note);
    if($teamId<1||$productId<1||$actorId<1||$amount<0||preg_match('/^[A-Z]{3}$/D',$currency)!==1||$note===''||strlen($note)>1000)throw new InvalidArgumentException('Vyplňte soupisku, produkt, platnou cenu, měnu a důvod změny.');
    return shopMemberPricingSave($pdo,'product',$teamId,$productId,null,'exact_price',$amount,$currency,$actorId,$note);
}

/** @return array<string,mixed> */
function shopMemberPricingSave(PDO $pdo,string $kind,int $teamId,?int $productId,?string $categoryPath,string $type,int $value,?string $currency,int $actorId,string $note):array
{
    if(!shopMemberPricingAvailable($pdo))throw new ShopMemberPricingException('Databázová migrace klubových cen ještě nebyla použita.');
    $table=$kind==='product'?'shop_member_product_prices':'shop_member_category_rules';
    $where=$kind==='product'?'team_id=? AND product_id=?':'team_id=? AND category_path=?';$key=$kind==='product'?$productId:$categoryPath;
    $pdo->beginTransaction();
    try{
        $find=$pdo->prepare("SELECT * FROM $table WHERE $where");$find->execute([$teamId,$key]);$before=$find->fetch(PDO::FETCH_ASSOC)?:null;
        if($kind==='product'){
            if($before){$pdo->prepare('UPDATE shop_member_product_prices SET amount_minor=?,currency=?,active=1,created_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$value,$currency,$actorId,$before['id']]);$id=(int)$before['id'];}
            else{$pdo->prepare('INSERT INTO shop_member_product_prices(team_id,product_id,amount_minor,currency,active,created_by_trainer_id) VALUES(?,?,?,?,1,?)')->execute([$teamId,$productId,$value,$currency,$actorId]);$id=(int)$pdo->lastInsertId();}
        }else{
            if($before){$pdo->prepare('UPDATE shop_member_category_rules SET discount_type=?,value_minor_or_basis_points=?,currency=?,active=1,created_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$type,$value,$currency,$actorId,$before['id']]);$id=(int)$before['id'];}
            else{$pdo->prepare('INSERT INTO shop_member_category_rules(team_id,category_path,discount_type,value_minor_or_basis_points,currency,active,created_by_trainer_id) VALUES(?,?,?,?,?,1,?)')->execute([$teamId,$categoryPath,$type,$value,$currency,$actorId]);$id=(int)$pdo->lastInsertId();}
        }
        $find=$pdo->prepare("SELECT * FROM $table WHERE id=?");$find->execute([$id]);$after=$find->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare('INSERT INTO shop_member_price_events(rule_type,rule_id,team_id,product_id,category_path,actor_trainer_id,action,before_json,after_json,note) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$kind,$id,$teamId,$productId,$categoryPath,$actorId,$before?'update':'create',$before?json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$note]);
        $pdo->commit();return $after;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopMemberPricingException)throw$exception;throw new ShopMemberPricingException('Klubovou cenu se nepodařilo uložit bez částečné změny.',0,$exception);}
}

function shopMemberPricingDeactivate(PDO $pdo,string $kind,int $id,int $actorId,string $note):void
{
    $note=trim($note);if(!in_array($kind,['product','category'],true)||$id<1||$actorId<1||$note==='')throw new InvalidArgumentException('Neplatné vypnutí cenového pravidla.');
    $table=$kind==='product'?'shop_member_product_prices':'shop_member_category_rules';$pdo->beginTransaction();
    try{$s=$pdo->prepare("SELECT * FROM $table WHERE id=?");$s->execute([$id]);$before=$s->fetch(PDO::FETCH_ASSOC);if(!$before)throw new ShopMemberPricingException('Cenové pravidlo neexistuje.');
        $pdo->prepare("UPDATE $table SET active=0,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);$after=$before;$after['active']=0;
        $pdo->prepare('INSERT INTO shop_member_price_events(rule_type,rule_id,team_id,product_id,category_path,actor_trainer_id,action,before_json,after_json,note) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$kind,$id,(int)$before['team_id'],$before['product_id']??null,$before['category_path']??null,$actorId,'deactivate',json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$note]);$pdo->commit();
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopMemberPricingException)throw$exception;throw new ShopMemberPricingException('Pravidlo se nepodařilo vypnout.',0,$exception);}
}
