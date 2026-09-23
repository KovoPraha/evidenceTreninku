<?php
declare(strict_types=1);

/**
 * Reviewed snapshot of the 2026/27 offer from cyklokrouzekpraha.cz.
 * It is only a transitional public fallback. A canonical KIS program with the
 * same normalized name always replaces this row.
 *
 * @return list<array{name:string,age:string,schedule:string,location:string,offers:list<array{label:string,price:int,url:string}>}>
 */
function legacyClubCatalog():array
{
    $trebesin='Velodrom Třebešín';
    return[
        ['name'=>'Aligátoři','age'=>'7–8 let','schedule'=>'Pondělí 15:50–16:50','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/aligatori--2014-2015/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/aligatori--2018-2019/']]],
        ['name'=>'Kamzíci','age'=>'4–6 let','schedule'=>'Úterý 16:00–16:40','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>3400,'url'=>'https://shop.kovopraha.cz/kamzici--2020-2021/'],['label'=>'Celý rok','price'=>5800,'url'=>'https://shop.kovopraha.cz/kamzici-2021-2022/']]],
        ['name'=>'Papuchálci','age'=>'3–4 roky','schedule'=>'Úterý 16:00–16:40','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>3400,'url'=>'https://shop.kovopraha.cz/papuchalci/'],['label'=>'Celý rok','price'=>5800,'url'=>'https://shop.kovopraha.cz/papuchalci--2019-2020/']]],
        ['name'=>'Zebry','age'=>'6–7 let','schedule'=>'Úterý 16:00–17:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/zebry-6-7-let-1--pololeti-skolniho-roku-2026-2027/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/zebry-6-7-let-skolni-rok-2026-2027/']]],
        ['name'=>'Svišti','age'=>'4–5 let','schedule'=>'Úterý 16:10–16:50','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>3400,'url'=>'https://shop.kovopraha.cz/svisti/'],['label'=>'Celý rok','price'=>5800,'url'=>'https://shop.kovopraha.cz/svisti--2020-2021/']]],
        ['name'=>'Jezevci','age'=>'6–7 let','schedule'=>'Úterý 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/jezevci--2019-2020/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/jezevci--2015-2016/']]],
        ['name'=>'Sumci','age'=>'7–8 let','schedule'=>'Úterý 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/sumci--2018-2019/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/sumci-2019-2020/']]],
        ['name'=>'Zubříci','age'=>'8–9 let','schedule'=>'Úterý 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/zubrici--2017-2018/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/zubrici-2017-2018/']]],
        ['name'=>'Kapybary','age'=>'3–4 roky','schedule'=>'Středa 16:00–16:40','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>3400,'url'=>'https://shop.kovopraha.cz/streda-15-50-16-30-kapybary--2020-2021-/'],['label'=>'Celý rok','price'=>5800,'url'=>'https://shop.kovopraha.cz/kapybary--2021-2022/']]],
        ['name'=>'Lenochodi','age'=>'4–5 let','schedule'=>'Středa 16:00–16:50','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/lenochodi--2017-2018/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/lenochodi--2019-2020/']]],
        ['name'=>'Lamy','age'=>'9–10 let','schedule'=>'Středa 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/lamy--2016-2017/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/lamy--2016-2017-2/']]],
        ['name'=>'Pásovci','age'=>'7–8 let','schedule'=>'Středa 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/pasovci--2018-2019/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/pasovci--2018-2019-2/']]],
        ['name'=>'Mývalové','age'=>'11–15 let','schedule'=>'Středa 17:10–18:10','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/myvalove--2011-2015-/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/myvalove--2011-2015/']]],
        ['name'=>'Lvíčci','age'=>'4–5 let','schedule'=>'Čtvrtek 16:00–16:50','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/lvicci--2019-2020/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/lvicci--2020-2021/']]],
        ['name'=>'Lemuři','age'=>'6–7 let','schedule'=>'Čtvrtek 16:00–17:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/lemuri--2017-2018/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/lemuri--2019-2020/']]],
        ['name'=>'Sloni','age'=>'11–15 let','schedule'=>'Čtvrtek 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/sloni--2015-2016/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/sloni--2015-2016-2/']]],
        ['name'=>'Surikaty','age'=>'7–8 let','schedule'=>'Čtvrtek 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/surikaty--2018-2019/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/surikaty-2017-2018/']]],
        ['name'=>'Žirafy','age'=>'8–9 let','schedule'=>'Čtvrtek 17:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4100,'url'=>'https://shop.kovopraha.cz/zirafy--2018-2019/'],['label'=>'Celý rok','price'=>7200,'url'=>'https://shop.kovopraha.cz/zirafy--2017-2018/']]],
        ['name'=>'Kroužek +','age'=>'4–6 let','schedule'=>'Úterý 17:00–18:30','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4400,'url'=>'https://shop.kovopraha.cz/pumptrack-2016-2017/'],['label'=>'Celý rok','price'=>8200,'url'=>'https://shop.kovopraha.cz/krouzek--4-6let--zari-cerven/']]],
        ['name'=>'Pumptrack','age'=>'6–10 let','schedule'=>'Úterý 17:00–18:30','location'=>$trebesin,'offers'=>[['label'=>'Září–leden','price'=>6500,'url'=>'https://shop.kovopraha.cz/pumptrack/'],['label'=>'Září–červen','price'=>11500,'url'=>'https://shop.kovopraha.cz/pumptrack-2/']]],
        ['name'=>'Sportovní přípravka','age'=>'6–10 let','schedule'=>'Pondělí 17:00–18:30','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4400,'url'=>'https://shop.kovopraha.cz/sportovni-pripravka/'],['label'=>'Celý rok','price'=>8200,'url'=>'https://shop.kovopraha.cz/sportovni-pripravka-2/']]],
        ['name'=>'Předpřípravka','age'=>'4–6 let','schedule'=>'Po 17:00–18:00 · St 16:00–17:30','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>4900,'url'=>'https://shop.kovopraha.cz/cyklisticka-pripravka-treninky-2x-tydne--utery-a-ctvrtek-/'],['label'=>'Celý rok','price'=>8800,'url'=>'https://shop.kovopraha.cz/predpripravka/']]],
        ['name'=>'Cyklistická přípravka','age'=>'6–10 let','schedule'=>'Po 17:00–18:30 · St 16:00–18:00','location'=>$trebesin,'offers'=>[['label'=>'1. pololetí','price'=>6500,'url'=>'https://shop.kovopraha.cz/uvod-do-mtb/'],['label'=>'Celý rok','price'=>11500,'url'=>'https://shop.kovopraha.cz/uvod-do-mtb-a-drahy--2-dny/']]],
        ['name'=>'Točňáci 1× týdně','age'=>'6–10 let','schedule'=>'Úterý nebo čtvrtek 16:00–17:00','location'=>'Točná','offers'=>[['label'=>'Září–leden','price'=>4400,'url'=>'https://shop.kovopraha.cz/tocnaci-1x-tydne--utery-nebo-ctvrtek/']]],
        ['name'=>'Točňáci 2× týdně','age'=>'6–10 let','schedule'=>'Úterý a čtvrtek 16:00–17:00','location'=>'Točná','offers'=>[['label'=>'Září–leden','price'=>6500,'url'=>'https://shop.kovopraha.cz/tocnaci-2x-tydne--utery--ctvrtek/']]],
        ['name'=>'Cyklo kroužek ZŠ Kunratice','age'=>'školní děti','schedule'=>'Čtvrtek · u ZŠ Kunratice','location'=>'ZŠ Kunratice','offers'=>[['label'=>'Září–červen','price'=>6800,'url'=>'https://shop.kovopraha.cz/cyklo-krouzek-zs-kunratice/']]],
    ];
}

function legacyClubCatalogKey(string$value):string
{
    $value=mb_strtolower(trim($value),'UTF-8');$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
    return preg_replace('/[^a-z0-9]+/','',is_string($ascii)?strtolower($ascii):$value)??'';
}

/** @return list<array<string,mixed>> */
function legacyClubCatalogFallback(array$canonical,?DateTimeImmutable$now=null):array
{
    $now??=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
    if($now>new DateTimeImmutable('2027-06-30 23:59:59',new DateTimeZone('Europe/Prague')))return[];
    $known=[];foreach($canonical as$program)$known[legacyClubCatalogKey((string)$program['public_name'])]=true;
    $result=[];$weekdayNames=['pondělí'=>1,'po'=>1,'úterý'=>2,'středa'=>3,'st'=>3,'čtvrtek'=>4,'pátek'=>5];
    foreach(legacyClubCatalog()as$index=>$row){if(isset($known[legacyClubCatalogKey($row['name'])]))continue;$days=[];$lower=mb_strtolower($row['schedule'],'UTF-8');foreach($weekdayNames as$word=>$day)if(preg_match('/(^|[^[:alpha:]])'.preg_quote($word,'/').'([^[:alpha:]]|$)/u',$lower)===1)$days[$day]=true;
        $offers=[];foreach($row['offers']as$offer)$offers[]=['purchase_label'=>$offer['label'],'name'=>$offer['label'].' · školní rok 2026/27','amount_minor'=>$offer['price']*100,'currency'=>'CZK','saleable'=>true,'sale_reason'=>'Nabídka ve stávajícím e-shopu.','source_url'=>$offer['url'],'is_featured'=>str_contains(mb_strtolower($offer['label'],'UTF-8'),'rok')?1:0,'product_id'=>0,'variant_id'=>0];
        $result[]=['id'=>0,'public_name'=>$row['name'],'public_summary'=>'Aktuální nabídka převzatá z rozcestníku cyklokrouzekpraha.cz.','location_name'=>$row['location'],'age_label'=>$row['age'],'sort_order'=>1000+$index,'interest_enabled'=>1,'schedule'=>[],'schedule_label'=>$row['schedule'],'filter_days'=>array_keys($days),'images'=>[],'offers'=>$offers,'legacy_fallback'=>true];
    }return$result;
}
