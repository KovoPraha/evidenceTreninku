<?php
declare(strict_types=1);

require_once __DIR__.'/club_catalog_import.php';

final class ClubKunraticePrepareException extends RuntimeException{}

/** @return array{source:array<string,mixed>,texts:array<string,string>} */
function clubKunraticeSource(PDO $pdo):array
{
    $source=$pdo->query("SELECT p.id AS program_id,o.season_id,o.product_id,o.starts_on,o.ends_on,o.sales_open_at,o.sales_close_at,v.amount_minor
        FROM club_programs p JOIN club_program_offers o ON o.program_id=p.id
        JOIN shop_variants v ON v.id=o.variant_id
        WHERE p.code='KROUZKY-2627-CYKLO-KROU-EK-Z-KUNRATICE' AND o.purchase_option='full_year'
        ORDER BY o.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$source)throw new ClubKunraticePrepareException('Původní celoroční program Kunratice nebyl nalezen.');
    if((int)$source['amount_minor']!==680000)throw new ClubKunraticePrepareException('Původní cena Kunratic již není 6 800 Kč; rozdělení vyžaduje kontrolu.');
    $oldProduct=$pdo->prepare('SELECT p.id,p.name,v.id AS variant_id,v.amount_minor FROM shop_products p JOIN shop_variants v ON v.product_id=p.id WHERE p.id=227 AND v.id=793');
    $oldProduct->execute();$oldProduct=$oldProduct->fetch(PDO::FETCH_ASSOC);
    if(!$oldProduct||!str_contains(mb_strtolower((string)$oldProduct['name'],'UTF-8'),'kunratice')||(int)$oldProduct['amount_minor']!==680000)
        throw new ClubKunraticePrepareException('Importovaný produkt 227/793 neodpovídá očekávaným Kunraticím.');
    $texts=[];
    foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){
        $term=clubProgramTermsCurrent($pdo,'program',(int)$source['program_id'],$purpose);
        $text=(string)($term['consent_text_plain']??'');
        if(!clubProgramTermTextReady($text))throw new ClubKunraticePrepareException('Kunratice potřebují před přípravou schválené jednotné podmínky.');
        $texts[$purpose]=$text;
    }
    return['source'=>$source,'texts'=>$texts];
}

/**
 * Prepare separate, unpublished 2026/27 Kunratice groups. The original
 * Shoptet product and generic KIS program remain in the database for orders
 * placed before the split; they are only removed from sale and discovery.
 *
 * @return array{created:int,groups:list<array{program_id:int,product_id:int,variant_id:int,team_id:int}>,retired_products:list<int>}
 */
function clubKunraticePrepare(PDO $pdo,int $actorId):array
{
    if($actorId<1)throw new InvalidArgumentException('Příprava Kunratic vyžaduje správce.');
    $reason='Rozdělení Kunratic 2026/27 podle ročníků; čas není potvrzen, nové skupiny zůstávají nezveřejněné.';
    ['source'=>$source,'texts'=>$texts]=clubKunraticeSource($pdo);
    $groups=[
        ['code'=>'KROUZKY-2627-KUNRATICE-3-4','team_code'=>'KR-2627-KUNRATICE-34','name'=>'Cyklo kroužek ZŠ Kunratice · 3.–4. třída','age'=>'3.–4. třída'],
        ['code'=>'KROUZKY-2627-KUNRATICE-5-6','team_code'=>'KR-2627-KUNRATICE-56','name'=>'Cyklo kroužek ZŠ Kunratice · 5.–6. třída','age'=>'5.–6. třída'],
    ];
    $prepared=[];$created=0;
    foreach($groups as$group){
        $team=kisRosterCreateTeam($pdo,(int)$source['season_id'],$actorId,$group['team_code'],$group['name'].' 2026/27','Cyklistika',$group['age'],$reason);
        $existing=$pdo->prepare('SELECT p.id AS program_id,o.product_id,o.variant_id,o.team_id,pr.listing_status,p.name FROM club_programs p JOIN club_program_offers o ON o.program_id=p.id JOIN club_program_presentations pr ON pr.program_id=p.id WHERE p.code=?');
        $existing->execute([$group['code']]);$rows=$existing->fetchAll(PDO::FETCH_ASSOC);
        if($rows){
            if(count($rows)!==1||(int)$rows[0]['team_id']!==(int)$team['id']||(string)$rows[0]['name']!==$group['name']||(string)$rows[0]['listing_status']!=='draft')
                throw new ClubKunraticePrepareException('Existující rozdělená skupina Kunratic se liší od očekávaného konceptu: '.$group['code']);
            $product=$pdo->prepare('SELECT catalog_status FROM shop_products WHERE id=?');$product->execute([(int)$rows[0]['product_id']]);
            if((string)$product->fetchColumn()!=='draft')throw new ClubKunraticePrepareException('Rozdělená skupina Kunratic již byla zveřejněna: '.$group['code']);
            $prepared[]=['program_id'=>(int)$rows[0]['program_id'],'product_id'=>(int)$rows[0]['product_id'],'variant_id'=>(int)$rows[0]['variant_id'],'team_id'=>(int)$team['id']];
            continue;
        }
        $result=clubProgramWizardCreate($pdo,$actorId,[
            'request_key'=>md5($group['code']),'source_mode'=>'new','name'=>$group['name'],
            'description'=>'Cyklistický kroužek pro '.$group['age'].' u ZŠ Kunratice. Přesný čtvrteční čas bude upřesněn.',
            'currency'=>'CZK','amount_minor'=>680000,'category_path'=>'Kroužky > Dětské',
            'starts_on'=>$source['starts_on'],'ends_on'=>$source['ends_on'],
            'sales_open_at'=>$source['sales_open_at'],'sales_close_at'=>$source['sales_close_at'],
            'capacity'=>30,'program_code'=>$group['code'],'offer_code'=>$group['code'].'-FULL',
            'sku'=>'KP-KR-2627-KUNRATICE-'.($group['age']==='3.–4. třída'?'34':'56').'-FULL',
            'purchase_option'=>'full_year','is_featured'=>true,
            'program_cancellation_source'=>'new','program_cancellation_text'=>$texts['program_cancellation'],
            'program_consent_source'=>'new','program_consent_text'=>$texts['program_consent'],
            'team_mode'=>'existing','team_id'=>(int)$team['id'],'reason'=>$reason,'confirmed'=>true,
            'defer_publication'=>true,
        ]);
        $prepared[]=['program_id'=>(int)$result['program_id'],'product_id'=>(int)$result['product_id'],'variant_id'=>(int)$result['variant_id'],'team_id'=>(int)$team['id']];$created++;
    }
    $oldProgram=(int)$source['program_id'];
    $presentation=$pdo->prepare('SELECT * FROM club_program_presentations WHERE program_id=?');$presentation->execute([$oldProgram]);$presentation=$presentation->fetch(PDO::FETCH_ASSOC);
    if(!$presentation)throw new ClubKunraticePrepareException('Původní veřejná prezentace Kunratic chybí.');
    clubProgramStorefrontSavePresentation($pdo,$actorId,$oldProgram,[
        'public_name'=>$presentation['public_name'],'public_summary'=>$presentation['public_summary'],
        'location_name'=>$presentation['location_name'],'age_label'=>$presentation['age_label'],
        'listing_status'=>'hidden','sort_order'=>$presentation['sort_order'],'interest_enabled'=>0,
    ],$reason,true);
    $retired=[];
    foreach(array_unique([227,(int)$source['product_id']])as$productId){
        $result=shopManualCatalogArchive($pdo,$actorId,$productId,$reason,true);
        if($result['changed'])$retired[]=$productId;
    }
    return['created'=>$created,'groups'=>$prepared,'retired_products'=>$retired];
}
