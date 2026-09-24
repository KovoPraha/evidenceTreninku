<?php
declare(strict_types=1);

require_once __DIR__.'/legacy_club_catalog.php';
require_once __DIR__.'/club_program_wizard.php';
require_once __DIR__.'/club_program_storefront.php';

final class ClubCatalogImportException extends RuntimeException{}

const CLUB_CATALOG_DUMMY_TERMS=[
    'program_cancellation'=>'vzorový text – Přihlášku lze před zahájením zrušit písemným oznámením klubu. Způsob případného vrácení uhrazené ceny bude posouzen podle okamžiku zrušení a již vzniklých nákladů.',
    'program_consent'=>'vzorový text – Zákonný zástupce souhlasí s přihlášením vybraného dítěte do uvedeného kroužku, jeho účastí na programu a s organizační komunikací klubu.',
];

/** @return array{program_cancellation:array{id:int,text:string,approved:bool,dummy:bool},program_consent:array{id:int,text:string,approved:bool,dummy:bool>} */
function clubCatalogImportTermTemplates(PDO $pdo):array
{
    $result=[];
    $statement=$pdo->prepare(
        "SELECT t.id,t.consent_text_plain FROM club_event_term_versions t "
        ."JOIN club_programs p ON t.scope_key="
        .((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?"CONCAT('program:',p.id)":"'program:' || p.id")
        ." WHERE t.scope_type='club_program' AND t.consent_purpose=? AND t.status='active' "
        ."AND p.status='active' AND p.name NOT LIKE 'TEST - %' AND t.consent_text_plain NOT LIKE ? "
        ."ORDER BY t.id DESC LIMIT 1"
    );
    foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){
        $statement->execute([$purpose,CLUB_PROGRAM_TERM_DRAFT_MARKER.'%']);$row=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$row||trim((string)$row['consent_text_plain'])==='')$result[$purpose]=['id'=>0,'text'=>CLUB_CATALOG_DUMMY_TERMS[$purpose],'approved'=>false,'dummy'=>true];
        else$result[$purpose]=['id'=>(int)$row['id'],'text'=>(string)$row['consent_text_plain'],'approved'=>true,'dummy'=>false];
    }
    return$result;
}

function clubCatalogImportSlug(string$value):string{return clubProgramWizardSlug($value);}

function clubCatalogImportPurchaseOption(string$label):string
{
    $label=mb_strtolower($label,'UTF-8');
    if(str_contains($label,'celý rok')||str_contains($label,'září–červen')||str_contains($label,'září-červen'))return'full_year';
    if(str_contains($label,'2. pololetí')||str_contains($label,'únor'))return'second_half';
    return'first_half';
}

/** @return list<array{weekday:int,starts_at:string,ends_at:string}> */
function clubCatalogImportSchedule(string$value):array
{
    if(str_contains($value,'Úterý nebo čtvrtek'))return[['weekday'=>2,'starts_at'=>'16:00','ends_at'=>'17:00'],['weekday'=>4,'starts_at'=>'16:00','ends_at'=>'17:00']];
    if(str_contains($value,'Úterý a čtvrtek'))return[['weekday'=>2,'starts_at'=>'16:00','ends_at'=>'17:00'],['weekday'=>4,'starts_at'=>'16:00','ends_at'=>'17:00']];
    $days=['Pondělí'=>1,'Po'=>1,'Úterý'=>2,'Středa'=>3,'St'=>3,'Čtvrtek'=>4];$slots=[];
    foreach(preg_split('/\s*·\s*/u',$value)?:[]as$part){
        if(preg_match('/^(Pondělí|Po|Úterý|Středa|St|Čtvrtek)\s+(\d{2}:\d{2})[–-](\d{2}:\d{2})/u',trim($part),$match)!==1)continue;
        $slots[]=['weekday'=>$days[$match[1]],'starts_at'=>$match[2],'ends_at'=>$match[3]];
    }
    return$slots;
}

/** @return array{created:int,updated:int,programs:int,products:int,variants:int,terms_ready:bool,terms_dummy:bool} */
function clubCatalogImport(PDO$pdo,int$actorId,string$applicationRoot):array
{
    if($actorId<1)throw new InvalidArgumentException('Import vyžaduje aktivního správce.');
    $templates=clubCatalogImportTermTemplates($pdo);$reason='Import nabídky cyklistických kroužků 2026/27 do KIS.';
    $season=kisRosterCreateSeason($pdo,$actorId,'SCHOOL-2026-27','Školní rok 2026/27','2026-09-01','2027-08-31','school_year');
    $seasonId=(int)$season['id'];$created=0;$updated=0;
    $images=['source-group.jpg','source-trail.jpg','source-youngest.jpg','source-descent.jpg','source-coach.jpg'];
    foreach(legacyClubCatalog()as$index=>$row){
        $slug=clubCatalogImportSlug((string)$row['name']);$programCode='KROUZKY-2627-'.$slug;$teamCode=substr('KR-2627-'.$slug,0,48);
        $team=kisRosterCreateTeam($pdo,$seasonId,$actorId,$teamCode,(string)$row['name'].' 2026/27','Cyklistika',(string)$row['age'],$reason);
        $programQuery=$pdo->prepare('SELECT * FROM club_programs WHERE code=?');$programQuery->execute([$programCode]);$program=$programQuery->fetch(PDO::FETCH_ASSOC);
        $imagePath=rtrim($applicationRoot,'/\\').DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'clubs'.DIRECTORY_SEPARATOR.$images[$index%count($images)];
        if(!is_file($imagePath))throw new ClubCatalogImportException('Chybí importovaný obrázek: '.basename($imagePath));
        $first=$row['offers'][0];$firstOption=clubCatalogImportPurchaseOption((string)$first['label']);
        if(!$program){
            $firstEnds=$firstOption==='full_year'?'2027-06-30':($firstOption==='second_half'?'2027-06-30':'2027-01-31');
            $result=clubProgramWizardCreate($pdo,$actorId,[
                'request_key'=>md5('club-catalog-2026-27-'.$slug),'source_mode'=>'new','name'=>$row['name'],
                'description'=>'Cyklistický kroužek pro děti: '.$row['age'].', '.$row['schedule'].', '.$row['location'].'.',
                'currency'=>'CZK','amount_minor'=>(int)$first['price']*100,'category_path'=>'Kroužky > Dětské',
                'starts_on'=>'2026-09-01','ends_on'=>$firstEnds,'sales_open_at'=>'2026-09-01T00:00','sales_close_at'=>$firstEnds.'T23:59',
                'capacity'=>'','program_code'=>$programCode,'offer_code'=>substr($programCode.'-'.strtoupper($firstOption),0,64),
                'sku'=>substr('KP-KR-2627-'.$slug.'-'.strtoupper($firstOption),0,64),'purchase_option'=>$firstOption,'is_featured'=>$firstOption==='full_year',
                'program_cancellation_source'=>$templates['program_cancellation']['id']>0?'existing':'new',
                'program_cancellation_version_id'=>$templates['program_cancellation']['id'],'program_cancellation_text'=>$templates['program_cancellation']['text'],
                'program_consent_source'=>$templates['program_consent']['id']>0?'existing':'new',
                'program_consent_version_id'=>$templates['program_consent']['id'],'program_consent_text'=>$templates['program_consent']['text'],
                'team_mode'=>'existing','team_id'=>(int)$team['id'],'reason'=>$reason,'confirmed'=>true,
            ],$imagePath,false,$applicationRoot);
            $programId=(int)$result['program_id'];$productId=(int)$result['product_id'];$baseOfferId=(int)$result['offer_id'];$created++;
        }else{
            if((string)$program['name']!==(string)$row['name'])throw new ClubCatalogImportException('Stabilní kód programu už používá jiný kroužek: '.$programCode);
            $programId=(int)$program['id'];$base=$pdo->prepare('SELECT * FROM club_program_offers WHERE program_id=? ORDER BY id LIMIT 1');$base->execute([$programId]);$base=$base->fetch(PDO::FETCH_ASSOC);
            if(!$base)throw new ClubCatalogImportException('Existující program nemá výchozí nabídku: '.$programCode);
            $productId=(int)$base['product_id'];$baseOfferId=(int)$base['id'];$updated++;
        }
        foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){
            if(!clubProgramTermsCurrent($pdo,'program',$programId,$purpose))clubProgramTermsConfigure($pdo,$actorId,'program',$programId,$purpose,$templates[$purpose]['text'],true);
        }
        $pdo->prepare('UPDATE club_program_offers SET purchase_option=?,is_featured=? WHERE id=?')->execute([$firstOption,$firstOption==='full_year'?1:0,$baseOfferId]);
        foreach(array_slice($row['offers'],1)as$offer){
            $option=clubCatalogImportPurchaseOption((string)$offer['label']);$exists=$pdo->prepare('SELECT id FROM club_program_offers WHERE program_id=? AND purchase_option=?');$exists->execute([$programId,$option]);
            if($exists->fetchColumn()!==false)continue;$ends=$option==='full_year'?'2027-06-30':($option==='second_half'?'2027-06-30':'2027-01-31');$starts=$option==='second_half'?'2027-02-01':'2026-09-01';
            clubProgramCreatePaymentOption($pdo,$actorId,$baseOfferId,[
                'purchase_option'=>$option,'is_featured'=>$option==='full_year','sku'=>substr('KP-KR-2627-'.$slug.'-'.strtoupper($option),0,64),
                'amount_minor'=>(int)$offer['price']*100,'code'=>substr($programCode.'-'.strtoupper($option),0,64),'name'=>$row['name'].' · '.$offer['label'],
                'starts_on'=>$starts,'ends_on'=>$ends,'sales_open_at'=>'2026-09-01T00:00','sales_close_at'=>$ends.'T23:59','capacity'=>'','status'=>'active',
            ],$reason,true);
        }
        clubProgramStorefrontSavePresentation($pdo,$actorId,$programId,[
            'public_name'=>$row['name'],'public_summary'=>'','location_name'=>$row['location'],'age_label'=>$row['age'],
            'listing_status'=>'published','sort_order'=>$index,'interest_enabled'=>1,
        ],$reason,true);
        foreach(clubCatalogImportSchedule((string)$row['schedule'])as$sort=>$slot){
            $exists=$pdo->prepare('SELECT id FROM club_program_schedule_slots WHERE program_id=? AND season_id=? AND weekday=? AND starts_at=? AND ends_at=?');
            $exists->execute([$programId,$seasonId,$slot['weekday'],$slot['starts_at'].':00',$slot['ends_at'].':00']);
            if($exists->fetchColumn()===false)clubProgramStorefrontAddSchedule($pdo,$actorId,$programId,['season_id'=>$seasonId,'weekday'=>$slot['weekday'],'starts_at'=>$slot['starts_at'],'ends_at'=>$slot['ends_at'],'location_name'=>$row['location'],'sort_order'=>$sort],$reason,true);
        }
        $programImages=$pdo->prepare('SELECT COUNT(*) FROM club_program_images WHERE program_id=?');$programImages->execute([$programId]);
        if((int)$programImages->fetchColumn()===0)clubProgramStorefrontAddImage($pdo,$actorId,$programId,$imagePath,'Děti na cyklistickém kroužku '.$row['name'],0,$reason,true,false,$applicationRoot);
        $productImages=$pdo->prepare('SELECT COUNT(*) FROM shop_product_images WHERE product_id=?');$productImages->execute([$productId]);
        if((int)$productImages->fetchColumn()===0)shopProductImageAdd($pdo,$actorId,$productId,$imagePath,0,$reason,true,false,$applicationRoot);
    }
    $termsDummy=false;foreach($templates as$template)$termsDummy=$termsDummy||$template['dummy'];
    return['created'=>$created,'updated'=>$updated,'programs'=>(int)$pdo->query("SELECT COUNT(*) FROM club_programs WHERE code LIKE 'KROUZKY-2627-%'")->fetchColumn(),'products'=>(int)$pdo->query("SELECT COUNT(DISTINCT product_id) FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE p.code LIKE 'KROUZKY-2627-%'")->fetchColumn(),'variants'=>(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE p.code LIKE 'KROUZKY-2627-%'")->fetchColumn(),'terms_ready'=>true,'terms_dummy'=>$termsDummy];
}
