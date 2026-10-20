<?php
declare(strict_types=1);

require_once __DIR__.'/club_catalog_import.php';

final class ClubTocnaciSplitException extends RuntimeException{}

const CLUB_TOCNACI_DAYS=[
    'UTERY'=>['name'=>'Točňáci – úterý','weekday'=>2,'label'=>'úterý'],
    'CTVRTEK'=>['name'=>'Točňáci – čtvrtek','weekday'=>4,'label'=>'čtvrtek'],
];

/** The legacy import slug depends on server transliteration, so identify the canonical program by its exact name and season. */
function clubTocnaciSplitFindProgram(PDO $pdo,string $name):?array
{
    $query=$pdo->prepare('SELECT * FROM club_programs WHERE name=? ORDER BY id');
    $query->execute([$name]);$matches=[];
    foreach($query->fetchAll(PDO::FETCH_ASSOC)as$row){
        if((string)$row['name']===$name&&str_starts_with((string)$row['code'],'KROUZKY-2627-'))$matches[]=$row;
    }
    if(count($matches)>1)throw new ClubTocnaciSplitException('Nalezeno více původních programů Točňáci pro školní rok 2026/27.');
    return$matches[0]??null;
}

/** @return array{program:array<string,mixed>,offers:list<array<string,mixed>>,terms:array<string,array<string,mixed>>,import_product_id:int} */
function clubTocnaciSplitSource(PDO $pdo,int $legacyVariantId=682):array
{
    $program=clubTocnaciSplitFindProgram($pdo,'Točňáci 1× týdně');
    if(!$program)throw new ClubTocnaciSplitException('Původní program Točňáci 1× týdně nebyl nalezen.');
    $query=$pdo->prepare('SELECT o.*,v.amount_minor,v.currency FROM club_program_offers o JOIN shop_variants v ON v.id=o.variant_id WHERE o.program_id=? ORDER BY o.id');
    $query->execute([(int)$program['id']]);$offers=$query->fetchAll(PDO::FETCH_ASSOC);
    $byOption=[];foreach($offers as$offer){$option=(string)($offer['purchase_option']??'');if(isset($byOption[$option]))throw new ClubTocnaciSplitException('Původní program má duplicitní platební období.');$byOption[$option]=$offer;}
    if(!isset($byOption['first_half'],$byOption['second_half']))throw new ClubTocnaciSplitException('Původní program nemá první a druhé pololetí.');
    if(count($offers)!==2)throw new ClubTocnaciSplitException('Původní program má jiné nabídky než dvě pololetí; převod je třeba zkontrolovat.');
    $first=$byOption['first_half'];$second=$byOption['second_half'];
    if((int)$first['season_id']!==(int)$second['season_id']||(int)$first['team_id']!==(int)$second['team_id']||(string)$first['currency']!=='CZK'||(string)$second['currency']!=='CZK')throw new ClubTocnaciSplitException('Původní období nemají společnou sezonu, soupisku nebo měnu CZK.');
    $terms=clubProgramTermsEffective($pdo,(int)$program['id'],(int)$first['id']);
    if(!clubProgramTermsComplete($terms))throw new ClubTocnaciSplitException('Před rozdělením je třeba schválit podmínky původního programu.');
    $legacy=$pdo->prepare('SELECT p.id,p.name,p.origin,p.offer_type,p.catalog_status AS product_status,v.origin AS variant_origin,'
        .'v.sku,v.unit_code,v.stock_quantity_decimal,v.catalog_status AS variant_status,pub.status AS publication_status '
        .'FROM shop_variants v JOIN shop_products p ON p.id=v.product_id '
        .'LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE v.id=?');
    $legacy->execute([$legacyVariantId]);$legacy=$legacy->fetch(PDO::FETCH_ASSOC);
    if(!$legacy||(int)$legacy['id']!==154||(string)$legacy['origin']!=='import'||(string)$legacy['variant_origin']!=='import'
        ||(string)$legacy['sku']!=='708'||(string)$legacy['offer_type']!=='goods'||(string)$legacy['unit_code']!=='piece'
        ||$legacy['stock_quantity_decimal']===null
        ||preg_match('/Točňáci.*1\s*[×x]/ui',(string)$legacy['name'])!==1)
        throw new ClubTocnaciSplitException('Importovaný starý produkt Točňáci 1× týdně neodpovídá očekávanému záznamu.');
    $active=(string)$legacy['product_status']==='active'&&(string)$legacy['variant_status']==='active'
        &&(string)$legacy['publication_status']==='active';
    $inactive=(string)$legacy['product_status']==='inactive'&&(string)$legacy['variant_status']==='inactive'
        &&(string)$legacy['publication_status']==='inactive';
    if(!$active&&!$inactive)throw new ClubTocnaciSplitException('Importovaný starý produkt Točňáci je částečně archivován.');
    $count=$pdo->prepare('SELECT COUNT(*) FROM shop_variants WHERE product_id=?');$count->execute([(int)$legacy['id']]);
    if((int)$count->fetchColumn()!==1)throw new ClubTocnaciSplitException('Importovaný starý produkt Točňáci má další varianty.');
    $linked=$pdo->prepare('SELECT id FROM club_program_offers WHERE variant_id=?');$linked->execute([$legacyVariantId]);
    if($linked->fetchColumn()!==false)throw new ClubTocnaciSplitException('Importovaný starý produkt Točňáci je již navázaný na program.');
    return['program'=>$program,'offers'=>$byOption,'terms'=>$terms,'import_product_id'=>(int)$legacy['id']];
}

/** @return array<string,mixed> */
function clubTocnaciSplitPlan(PDO $pdo,int $legacyVariantId=682):array
{
    $source=clubTocnaciSplitSource($pdo,$legacyVariantId);$targets=[];
    foreach(CLUB_TOCNACI_DAYS as$key=>$day){
        $code='KROUZKY-2627-TOCNACI-'.$key;$query=$pdo->prepare('SELECT id FROM club_programs WHERE code=?');$query->execute([$code]);
        $targets[$key]=['program_code'=>$code,'program_id'=>$query->fetchColumn()?:null,'name'=>$day['name'],'weekday'=>$day['weekday']];
    }
    return['old_program_id'=>(int)$source['program']['id'],'old_import_product_id'=>$source['import_product_id'],'old_offer_ids'=>array_map(static fn(array $offer):int=>(int)$offer['id'],$source['offers']),'targets'=>$targets];
}

/** @return array<string,mixed> */
function clubTocnaciSplitApply(PDO $pdo,int $actorId,string $applicationRoot,int $legacyVariantId=682):array
{
    if($actorId<1)throw new InvalidArgumentException('Převod vyžaduje správce.');
    if(!clubProgramRuntimeTableExists($pdo,'club_program_physical_teams'))throw new ClubTocnaciSplitException('Chybí migrace fyzických soupisek programu.');
    $source=clubTocnaciSplitSource($pdo,$legacyVariantId);$reason='Rozdělení Točňáků 1× týdně na samostatné úterý a čtvrtek podle rozhodnutí klubu.';
    $first=$source['offers']['first_half'];$second=$source['offers']['second_half'];$seasonId=(int)$first['season_id'];$results=[];
    foreach(CLUB_TOCNACI_DAYS as$key=>$day){
        $programCode='KROUZKY-2627-TOCNACI-'.$key;$teamCode='KR-2627-TOCNACI-'.$key;
        $team=kisRosterCreateTeam($pdo,$seasonId,$actorId,$teamCode,$day['name'].' 2026/27','Cyklistika','6–10 let',$reason);
        $query=$pdo->prepare('SELECT id FROM club_programs WHERE code=?');$query->execute([$programCode]);$programId=(int)$query->fetchColumn();
        if($programId===0){
            $result=clubProgramWizardCreate($pdo,$actorId,[
                'request_key'=>md5('tocnaci-2627-'.$key),'source_mode'=>'new','name'=>$day['name'],
                'description'=>'Cyklistický kroužek Točňáci 1× týdně: '.$day['label'].' 16:00–17:00, Točná.',
                'currency'=>'CZK','amount_minor'=>(int)$first['amount_minor'],'category_path'=>'Kroužky > Dětské',
                'starts_on'=>$first['starts_on'],'ends_on'=>$first['ends_on'],'sales_open_at'=>$first['sales_open_at'],'sales_close_at'=>$first['sales_close_at'],
                'capacity'=>30,'program_code'=>$programCode,'offer_code'=>$programCode.'-FIRST-HALF',
                'sku'=>'KP-KR-2627-TOCNACI-'.$key.'-FIRST-HALF','purchase_option'=>'first_half',
                'birth_year_from'=>$first['birth_year_from'],'birth_year_to'=>$first['birth_year_to'],
                'program_cancellation_source'=>'existing','program_cancellation_version_id'=>(int)$source['terms']['program_cancellation']['id'],
                'program_consent_source'=>'existing','program_consent_version_id'=>(int)$source['terms']['program_consent']['id'],
                'team_mode'=>'existing','team_id'=>(int)$team['id'],'reason'=>$reason,'confirmed'=>true,
            ],null,false,$applicationRoot);
            $programId=(int)$result['program_id'];
        }
        $query=$pdo->prepare('SELECT o.*,v.amount_minor FROM club_program_offers o JOIN shop_variants v ON v.id=o.variant_id WHERE o.program_id=? ORDER BY o.id');
        $query->execute([$programId]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);$byOption=[];
        foreach($rows as$row){$option=(string)$row['purchase_option'];if(isset($byOption[$option]))throw new ClubTocnaciSplitException('Nový den má duplicitní platební období.');$byOption[$option]=$row;}
        if(!isset($byOption['first_half'])||count($rows)>2)throw new ClubTocnaciSplitException('Nový den nemá očekávanou nabídku.');
        $base=$byOption['first_half'];
        if((int)$base['team_id']!==(int)$team['id']||(int)$base['season_id']!==$seasonId||(int)$base['amount_minor']!==(int)$first['amount_minor']||(int)$base['capacity']!==30)throw new ClubTocnaciSplitException('Nový den má jinou soupisku, sezonu, cenu nebo kapacitu.');
        if(!isset($byOption['second_half'])){
            clubProgramCreatePaymentOption($pdo,$actorId,(int)$base['id'],[
                'purchase_option'=>'second_half','sku'=>'KP-KR-2627-TOCNACI-'.$key.'-SECOND-HALF',
                'amount_minor'=>(int)$second['amount_minor'],'code'=>$programCode.'-SECOND-HALF',
                'name'=>$day['name'].' · 2. pololetí','starts_on'=>$second['starts_on'],'ends_on'=>$second['ends_on'],
                'sales_open_at'=>$second['sales_open_at'],'sales_close_at'=>$second['sales_close_at'],
                'capacity'=>30,'status'=>'active','birth_year_from'=>$second['birth_year_from'],'birth_year_to'=>$second['birth_year_to'],
            ],$reason,true);
        }else{
            $other=$byOption['second_half'];
            if((int)$other['team_id']!==(int)$team['id']||(int)$other['amount_minor']!==(int)$second['amount_minor']||(int)$other['capacity']!==30)throw new ClubTocnaciSplitException('Druhé pololetí nového dne má jiné údaje.');
        }
        clubProgramStorefrontSavePresentation($pdo,$actorId,$programId,[
            'public_name'=>$day['name'],'public_summary'=>'Točňáci 1× týdně: '.$day['label'].' 16:00–17:00.',
            'location_name'=>'Točná','age_label'=>'6–10 let','listing_status'=>'published','sort_order'=>23,'interest_enabled'=>1,
        ],$reason,true);
        $query=$pdo->prepare('SELECT id FROM club_program_schedule_slots WHERE program_id=? AND season_id=? AND weekday=? AND starts_at=? AND ends_at=?');
        $query->execute([$programId,$seasonId,$day['weekday'],'16:00:00','17:00:00']);
        if($query->fetchColumn()===false)clubProgramStorefrontAddSchedule($pdo,$actorId,$programId,[
            'season_id'=>$seasonId,'weekday'=>$day['weekday'],'starts_at'=>'16:00','ends_at'=>'17:00','location_name'=>'Točná','sort_order'=>0,
        ],$reason,true);
        $results[$key]=['program_id'=>$programId,'team_id'=>(int)$team['id'],'product_id'=>(int)$base['product_id'],'first_offer_id'=>(int)$base['id']];
    }
    $twice=clubTocnaciConnectTwiceWeekly($pdo,$results,$seasonId,$actorId);
    foreach($source['offers']as$offer)clubProgramCloseOffer($pdo,$actorId,(int)$offer['id'],$reason,true);
    clubProgramStorefrontSavePresentation($pdo,$actorId,(int)$source['program']['id'],[
        'public_name'=>'Točňáci 1× týdně','public_summary'=>'','location_name'=>'Točná','age_label'=>'6–10 let',
        'listing_status'=>'hidden','sort_order'=>23,'interest_enabled'=>0,
    ],$reason,true);
    shopManualCatalogArchive($pdo,$actorId,(int)$first['product_id'],$reason,true);
    shopManualCatalogArchive($pdo,$actorId,$source['import_product_id'],$reason,true);
    return['old_program_id'=>(int)$source['program']['id'],'old_import_product_id'=>$source['import_product_id'],'targets'=>$results,'twice_weekly'=>$twice];
}

/** @param array<string,array<string,int>> $dayPrograms */
function clubTocnaciConnectTwiceWeekly(PDO $pdo,array $dayPrograms,int $seasonId,int $actorId):array
{
    $program=clubTocnaciSplitFindProgram($pdo,'Točňáci 2× týdně');
    $programId=(int)($program['id']??0);
    if($programId<1)throw new ClubTocnaciSplitException('Program Točňáci 2× týdně nebyl nalezen.');
    $offers=$pdo->prepare('SELECT id,team_id,season_id,capacity FROM club_program_offers WHERE program_id=?');$offers->execute([$programId]);
    $rows=$offers->fetchAll(PDO::FETCH_ASSOC);
    if($rows===[])throw new ClubTocnaciSplitException('Program Točňáci 2× týdně nemá nabídku.');
    $primary=(int)$rows[0]['team_id'];
    foreach($rows as$row){
        if((int)$row['season_id']!==$seasonId||(int)$row['team_id']!==$primary||(int)$row['capacity']!==30)throw new ClubTocnaciSplitException('Točňáci 2× nemají společnou soupisku, sezonu a kapacitu 30.');
    }
    $targets=[(int)$dayPrograms['UTERY']['team_id'],(int)$dayPrograms['CTVRTEK']['team_id']];sort($targets,SORT_NUMERIC);
    if(count(array_unique($targets))!==2||in_array($primary,$targets,true))throw new ClubTocnaciSplitException('Fyzické soupisky úterý a čtvrtek nejsou oddělené.');
    $pdo->beginTransaction();
    try{
        $existing=$pdo->prepare('SELECT team_id FROM club_program_physical_teams WHERE program_id=? ORDER BY team_id');$existing->execute([$programId]);
        $mapped=array_map('intval',$existing->fetchAll(PDO::FETCH_COLUMN));
        if($mapped!==[]&&$mapped!==$targets)throw new ClubTocnaciSplitException('Program 2× už má jinak nastavené fyzické soupisky.');
        $insert=$pdo->prepare('INSERT INTO club_program_physical_teams(program_id,team_id) VALUES(?,?)');
        if($mapped===[])foreach($targets as$teamId)$insert->execute([$programId,$teamId]);
        $enrollments=$pdo->prepare("SELECT e.id,e.sportovec_id,e.valid_from FROM club_program_enrollments e JOIN club_program_offers o ON o.id=e.offer_id WHERE o.program_id=? AND e.status='active' ORDER BY e.id");
        $enrollments->execute([$programId]);$active=$enrollments->fetchAll(PDO::FETCH_ASSOC);
        foreach($targets as$teamId){
            $team=$pdo->prepare('SELECT id FROM club_teams WHERE id=?'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
            $team->execute([$teamId]);if($team->fetchColumn()===false)throw new ClubTocnaciSplitException('Denní soupiska nebyla nalezena.');
        }
        $created=0;
        foreach($active as$enrollment)foreach($targets as$teamId){
            if(clubProgramActivateRosterMember($pdo,$teamId,(int)$enrollment['sportovec_id'],(string)$enrollment['valid_from'],$actorId,(int)$enrollment['id'],'Doplnění úterní a čtvrteční soupisky k účasti Točňáci 2× týdně.'))$created++;
        }
        $pdo->commit();
        return['program_id'=>$programId,'physical_team_ids'=>$targets,'active_enrollments'=>count($active),'rosters_created'=>$created];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();throw$exception;}
}
