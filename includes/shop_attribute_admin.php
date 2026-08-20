<?php
declare(strict_types=1);

require_once __DIR__.'/shop_attribute.php';

final class ShopAttributeAdminException extends RuntimeException{}

/** @return array<string,mixed>|null */
function shopAttributeAdminDefinition(PDO $pdo,int $id,bool $lock=false):?array
{
    if($id<1)return null;$sql='SELECT * FROM shop_attribute_definitions WHERE id=?';if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$id]);$row=$statement->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
    $choices=$pdo->prepare('SELECT * FROM shop_attribute_choices WHERE attribute_id=? ORDER BY active DESC,sort_order,value,id');$choices->execute([$id]);$row['choice_rows']=$choices->fetchAll(PDO::FETCH_ASSOC);return$row;
}

/** @return array{id:int,changed:bool} */
function shopAttributeAdminSave(PDO $pdo,int $actorId,array $input,string $reason,bool $confirmed):array
{
    $reason=trim($reason);if($actorId<1||!$confirmed||mb_strlen($reason,'UTF-8')<3||mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Změna parametru vyžaduje administrátora, důvod a výslovné potvrzení.');
    $id=max(0,(int)($input['definition_id']??0));$key=trim((string)($input['attribute_key']??''));$name=trim((string)($input['display_name']??''));$type=(string)($input['value_type']??'');$unit=trim((string)($input['unit']??''));$sort=(int)($input['sort_order']??0);
    if($key===''||mb_strlen($key,'UTF-8')>191||preg_match('/[<>\x00-\x1F\x7F]/u',$key)===1)throw new InvalidArgumentException('Technický klíč je povinný prostý text do 191 znaků.');
    if($name===''||mb_strlen($name,'UTF-8')>255||preg_match('/[<>\x00-\x1F\x7F]/u',$name)===1)throw new InvalidArgumentException('Zobrazovaný název je povinný prostý text do 255 znaků.');
    if(!in_array($type,['text','number','choice'],true))throw new InvalidArgumentException('Typ parametru není podporovaný.');
    if(mb_strlen($unit,'UTF-8')>32||preg_match('/[<>\x00-\x1F\x7F]/u',$unit)===1)throw new InvalidArgumentException('Jednotka smí být prostý text do 32 znaků.');
    if($sort < -100000||$sort>100000)throw new InvalidArgumentException('Pořadí je mimo podporovaný rozsah.');
    $choiceLines=$input['choice_values']??[];if(!is_array($choiceLines))$choiceLines=preg_split('/\R/u',(string)$choiceLines)?:[];$choices=[];
    foreach($choiceLines as$value){$value=trim((string)$value);if($value==='')continue;if(mb_strlen($value,'UTF-8')>255||preg_match('/[<>\x00-\x1F\x7F]/u',$value)===1)throw new InvalidArgumentException('Hodnota výběru smí být prostý text do 255 znaků.');$choices[$value]=true;}
    $choices=array_keys($choices);if($type==='choice'&&$choices===[])throw new InvalidArgumentException('Výběrový parametr potřebuje alespoň jednu hodnotu.');
    $values=['attribute_key'=>$key,'display_name'=>$name,'value_type'=>$type,'unit'=>$unit!==''?$unit:null,'sort_order'=>$sort,'show_in_listing'=>($input['show_in_listing']??'')==='1'?1:0,'show_in_detail'=>($input['show_in_detail']??'')==='1'?1:0,'active'=>($input['active']??'')==='1'?1:0];
    $pdo->beginTransaction();try{
        $before=$id>0?shopAttributeAdminDefinition($pdo,$id,true):null;if($id>0&&!$before)throw new ShopAttributeAdminException('Definice parametru nebyla nalezena.');
        $duplicate=$pdo->prepare('SELECT id FROM shop_attribute_definitions WHERE attribute_key=? AND id<>?');$duplicate->execute([$key,$id]);if($duplicate->fetchColumn()!==false)throw new InvalidArgumentException('Technický klíč už má jinou definici.');
        if($id===0){$pdo->prepare('INSERT INTO shop_attribute_definitions(attribute_key,display_name,value_type,unit,sort_order,show_in_listing,show_in_detail,active) VALUES(?,?,?,?,?,?,?,?)')->execute(array_values($values));$id=(int)$pdo->lastInsertId();}
        else{$pdo->prepare('UPDATE shop_attribute_definitions SET attribute_key=?,display_name=?,value_type=?,unit=?,sort_order=?,show_in_listing=?,show_in_detail=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...array_values($values),$id]);}
        $pdo->prepare('UPDATE shop_attribute_choices SET active=0,updated_at=CURRENT_TIMESTAMP WHERE attribute_id=?')->execute([$id]);
        if($type==='choice')foreach($choices as$order=>$value){
            if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$pdo->prepare('INSERT INTO shop_attribute_choices(attribute_id,value,sort_order,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order),active=1,updated_at=CURRENT_TIMESTAMP')->execute([$id,$value,$order]);
            else $pdo->prepare('INSERT INTO shop_attribute_choices(attribute_id,value,sort_order,active) VALUES(?,?,?,1) ON CONFLICT(attribute_id,value) DO UPDATE SET sort_order=excluded.sort_order,active=1,updated_at=CURRENT_TIMESTAMP')->execute([$id,$value,$order]);
        }
        $after=shopAttributeAdminDefinition($pdo,$id,false);$beforeJson=$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$afterJson=json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if($beforeJson!==$afterJson)$pdo->prepare("INSERT INTO shop_attribute_definition_events(attribute_id,attribute_key,actor_type,actor_id,action,before_json,after_json,reason) VALUES(?,?,'trainer',?,?,?,?,?)")->execute([$id,$key,$actorId,$before===null?'create':'update',$beforeJson,$afterJson,$reason]);
        $pdo->commit();return['id'=>$id,'changed'=>$beforeJson!==$afterJson];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopAttributeAdminException)throw$exception;throw new ShopAttributeAdminException('Definici parametru se nepodařilo uložit bez částečné změny.',0,$exception);}
}
