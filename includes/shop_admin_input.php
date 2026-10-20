<?php
declare(strict_types=1);

require_once __DIR__.'/shop_admin_catalog.php';

function shopAdminSku(): string {return shopCatalogManualSkuPrefix().strtoupper(bin2hex(random_bytes(6)));}

/** @return array<string,mixed> */
function shopAdminParseVariant(array $input,array $definitions,array $existing=[]): array
{
    // A retired dictionary choice may remain on an imported variant. Keeping
    // that value while editing its price must not force a parameter change.
    foreach ($existing as $key=>$value) {
        if (($definitions[$key]['value_type']??'')==='choice') {
            $text=shopAttributeValueText($value);
            if (!in_array($text,(array)($definitions[$key]['choices']??[]),true)) $definitions[$key]['choices'][]=$text;
        }
    }
    $vat=trim((string)($input['includes_vat']??''));
    if (!in_array($vat,['','0','1'],true)) throw new InvalidArgumentException('Neplatný příznak DPH.');
    $percent=trim((string)($input['vat_percent']??''));
    $result=[
        'sku'=>trim((string)($input['sku']??''))?:shopAdminSku(),'ean'=>(string)($input['ean']??''),
        'attributes_json'=>shopAttributeFormJson($input,$definitions),
        'amount_minor'=>couponAdminMoneyInput((string)($input['amount']??'')),
        'compare_at_amount_minor'=>couponAdminMoneyInput((string)($input['compare_at_amount']??''),true),
        'includes_vat'=>$vat===''?null:(int)$vat,'vat_rate_basis_points'=>$percent===''?null:couponAdminMoneyInput($percent),
        'stock_quantity_decimal'=>(string)($input['stock_quantity_decimal']??''),'unit_code'=>(string)($input['unit_code']??'ks'),
        'visible'=>($input['visible']??'')==='1'?1:0,
    ];
    return $result;
}

/** @return list<array<string,mixed>> */
function shopAdminVariantCombinations(array $base,string $colors,string $sizes): array
{
    $split=static function(string $value):array {
        $values=array_values(array_unique(array_filter(array_map('trim',preg_split('/[,;\r\n]+/u',$value)?:[]),static fn(string $v):bool=>$v!=='')));
        foreach($values as $value) if(mb_strlen($value,'UTF-8')>191||preg_match('/[<>\x00-\x1F]/u',$value))throw new InvalidArgumentException('Parametr varianty musí být prostý text.');
        return $values===[]?['']:$values;
    };
    $colors=$split($colors);$sizes=$split($sizes);
    if(count($colors)*count($sizes)>64)throw new InvalidArgumentException('Najednou lze přidat nejvýše 64 variant.');
    $attributes=json_decode($base['attributes_json'],true,64,JSON_THROW_ON_ERROR);$result=[];
    foreach($colors as $color)foreach($sizes as $size){$values=$attributes;if($color!=='')$values['Barva']=$color;if($size!=='')$values['Velikost']=$size;$row=$base;$row['attributes_json']=json_encode((object)$values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(count($colors)*count($sizes)>1)$row['sku']=shopAdminSku();$result[]=$row;}
    return $result;
}
