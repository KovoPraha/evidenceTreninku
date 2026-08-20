<?php
declare(strict_types=1);

function shopAttributeTableExists(PDO $pdo,string $table):bool
{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);return(bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");$statement->execute([$table]);return(bool)$statement->fetchColumn();
}

/** @return array<string,array<string,mixed>> */
function shopAttributeDefinitions(PDO $pdo,bool $includeInactive=false):array
{
    if(!shopAttributeTableExists($pdo,'shop_attribute_definitions'))return[];
    $sql='SELECT * FROM shop_attribute_definitions'.($includeInactive?'':' WHERE active=1').' ORDER BY sort_order,display_name,attribute_key,id';
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);$result=[];$ids=[];
    foreach($rows as$row){$row['choices']=[];$key=(string)$row['attribute_key'];$result[$key]=$row;$ids[(int)$row['id']]=$key;}
    if($ids!==[]&&shopAttributeTableExists($pdo,'shop_attribute_choices')){
        $marks=implode(',',array_fill(0,count($ids),'?'));$statement=$pdo->prepare("SELECT * FROM shop_attribute_choices WHERE active=1 AND attribute_id IN ($marks) ORDER BY attribute_id,sort_order,value,id");$statement->execute(array_keys($ids));
        foreach($statement->fetchAll(PDO::FETCH_ASSOC)as$choice){$key=$ids[(int)$choice['attribute_id']]??null;if($key!==null)$result[$key]['choices'][]=(string)$choice['value'];}
    }
    return$result;
}

function shopAttributeValueText(mixed $value):string
{
    if(is_string($value)||is_int($value)||is_float($value))return trim((string)$value);
    if(is_bool($value))return$value?'Ano':'Ne';
    if($value===null)return'';
    try{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    catch(JsonException){return'';}
}

/** @param array<string,mixed> $attributes @param array<string,array<string,mixed>>|null $definitions @return list<array<string,mixed>> */
function shopAttributePresentation(PDO $pdo,array $attributes,string $surface='detail',?array $definitions=null):array
{
    if(!in_array($surface,['listing','detail'],true))throw new InvalidArgumentException('Neplatná plocha parametrů.');
    $definitions??=shopAttributeDefinitions($pdo,true);$defined=[];$unknown=[];
    foreach($attributes as$key=>$raw){
        $key=trim((string)$key);if($key==='')continue;$value=shopAttributeValueText($raw);if($value==='')continue;
        $definition=$definitions[$key]??null;
        if($definition!==null){
            if((int)$definition['active']!==1||($surface==='listing'&&(int)$definition['show_in_listing']!==1)||($surface==='detail'&&(int)$definition['show_in_detail']!==1))continue;
            $unit=trim((string)($definition['unit']??''));$defined[]=['key'=>$key,'display_name'=>(string)$definition['display_name'],'value'=>$value,'formatted_value'=>$value.($unit!==''?' '.$unit:''),'defined'=>true,'sort_order'=>(int)$definition['sort_order']];
        }else{
            $unknown[]=['key'=>$key,'display_name'=>$key,'value'=>$value,'formatted_value'=>$value,'defined'=>false,'sort_order'=>PHP_INT_MAX];
        }
    }
    usort($defined,static fn(array$a,array$b):int=>[$a['sort_order'],mb_strtolower($a['display_name'],'UTF-8'),$a['key']]<=>[$b['sort_order'],mb_strtolower($b['display_name'],'UTF-8'),$b['key']]);
    usort($unknown,static fn(array$a,array$b):int=>[mb_strtolower($a['display_name'],'UTF-8'),$a['key']]<=>[mb_strtolower($b['display_name'],'UTF-8'),$b['key']]);
    return array_merge($defined,$unknown);
}

/** @return list<string> */
function shopAttributeDiscoveredKeys(PDO $pdo):array
{
    if(!shopAttributeTableExists($pdo,'shop_variants'))return[];$keys=[];
    foreach($pdo->query('SELECT attributes_json FROM shop_variants')->fetchAll(PDO::FETCH_COLUMN)as$json){
        try{$decoded=json_decode((string)$json,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){continue;}
        if(!is_array($decoded))continue;foreach(array_keys($decoded)as$key){$key=trim((string)$key);if($key!=='')$keys[$key]=true;}
    }
    $result=array_keys($keys);usort($result,static fn(string$a,string$b):int=>[mb_strtolower($a,'UTF-8'),$a]<=>[mb_strtolower($b,'UTF-8'),$b]);return$result;
}
