<?php
declare(strict_types=1);

require_once __DIR__.'/kis_roster.php';
require_once __DIR__.'/club_program.php';
require_once __DIR__.'/shop_purchase_mode.php';

final class ClubYoungestPrepareException extends RuntimeException {}

/** @return list<array{product_id:int,variant_id:int,sku:string,code:string,team_code:string,name:string,age:string}> */
function clubYoungestGroups(): array
{
    return [
        ['product_id'=>157,'variant_id'=>685,'sku'=>'717','code'=>'KROUZKY-2627-DETI-8-14-MESICU','team_code'=>'KR-2627-DETI-8-14M','name'=>'Děti 8–14 měsíců','age'=>'8–14 měsíců'],
        ['product_id'=>158,'variant_id'=>686,'sku'=>'718','code'=>'KROUZKY-2627-DETI-1-2-ROKY','team_code'=>'KR-2627-DETI-1-2R','name'=>'Děti 1–2 roky','age'=>'1–2 roky'],
        ['product_id'=>159,'variant_id'=>687,'sku'=>'719','code'=>'KROUZKY-2627-DETI-2-3-ROKY','team_code'=>'KR-2627-DETI-2-3R','name'=>'Děti 2–3 roky','age'=>'2–3 roky'],
    ];
}

/**
 * Verify the reviewed Shoptet identity and absence of a saleable KIS offer.
 * Neither the imported 250 CZK lesson price nor its old Thursday schedule is
 * used as a price/date for a whole course that opens irregularly.
 * @return list<array<string,mixed>>
 */
function clubYoungestPreparePlan(PDO $pdo): array
{
    $source=$pdo->prepare('SELECT p.id AS product_id,p.name AS product_name,p.origin,p.external_product_key,p.offer_type,p.catalog_status AS product_status,'
        .'v.id AS variant_id,v.sku,v.amount_minor,v.currency,v.catalog_status AS variant_status '
        .'FROM shop_products p JOIN shop_variants v ON v.product_id=p.id WHERE p.id=?');
    $existing=$pdo->prepare('SELECT p.id AS program_id,p.name,p.description,p.status,pr.listing_status '
        .'FROM club_programs p LEFT JOIN club_program_presentations pr ON pr.program_id=p.id WHERE p.code=?');
    $teamQuery=$pdo->prepare("SELECT t.id AS team_id,t.name,t.discipline,t.age_label,t.status,
        s.name AS season_name,s.starts_on,s.ends_on,s.season_type,s.status AS season_status
        FROM club_teams t JOIN club_seasons s ON s.id=t.season_id
        WHERE s.code='SCHOOL-2026-27' AND t.code=?");
    $offers=$pdo->prepare('SELECT COUNT(*) FROM club_program_offers WHERE program_id=?');
    $plan=[];
    foreach(clubYoungestGroups() as $group){
        $source->execute([$group['product_id']]);$variants=$source->fetchAll(PDO::FETCH_ASSOC);
        if(count($variants)!==1)throw new ClubYoungestPrepareException('Importovaný dětský kurz nemá právě jednu variantu: '.$group['product_id']);
        $row=$variants[0];
        if((int)$row['variant_id']!==$group['variant_id']||(string)$row['sku']!==$group['sku']
            ||(string)$row['offer_type']!=='goods'||(string)$row['product_status']!=='active'
            ||(string)$row['variant_status']!=='active'||(string)$row['currency']!=='CZK'
            ||(int)$row['amount_minor']!==25000||!shopProductIsInterestOnlyYoungestCourse($row))
            throw new ClubYoungestPrepareException('Importovaný dětský kurz změnil identitu nebo režim: '.$group['product_id']);
        $ageNumbers=match($group['product_id']){157=>[8,14],158=>[1,2],159=>[2,3]};
        if(preg_match('/(?<!\d)'.$ageNumbers[0].'\D+'.$ageNumbers[1].'(?!\d)/u',(string)$row['product_name'])!==1)
            throw new ClubYoungestPrepareException('Název importovaného kurzu neodpovídá věkové skupině: '.$group['product_id']);
        $existing->execute([$group['code']]);$program=$existing->fetch(PDO::FETCH_ASSOC)?:null;
        if($program!==null){
            $description=clubYoungestDescription($group);
            if((string)$program['name']!==$group['name']||(string)($program['description']??'')!==$description
                ||(string)$program['status']!=='active'||(string)$program['listing_status']!=='draft')
                throw new ClubYoungestPrepareException('Koncept programu byl mezitím změněn: '.$group['code']);
            $offers->execute([(int)$program['program_id']]);
            if((int)$offers->fetchColumn()!==0)throw new ClubYoungestPrepareException('Program již má nabídku a vyžaduje ruční kontrolu: '.$group['code']);
        }
        $teamQuery->execute([$group['team_code']]);$team=$teamQuery->fetch(PDO::FETCH_ASSOC)?:null;
        if($team!==null&&((string)$team['name']!==$group['name'].' 2026/27'
            ||(string)$team['discipline']!=='Cyklistika'||(string)$team['age_label']!==$group['age']
            ||(string)$team['status']!=='active'||(string)$team['season_name']!=='Školní rok 2026/27'
            ||(string)$team['starts_on']!=='2026-09-01'||(string)$team['ends_on']!=='2027-08-31'
            ||(string)$team['season_type']!=='school_year'||(string)$team['season_status']!=='active'))
            throw new ClubYoungestPrepareException('Soupiska nejmenších se změnila: '.$group['team_code']);
        $plan[]=$group+['program_id'=>$program===null?null:(int)$program['program_id'],
            'team_id'=>$team===null?null:(int)$team['team_id'],'publication'=>'draft','purchase'=>'email_only'];
    }
    return $plan;
}

/** @param array{name:string} $group */
function clubYoungestDescription(array $group): string
{
    return 'Dětský kroužek '.$group['name'].'. Otevření kurzu, rozsah a celkovou cenu klub sdělí e-mailem.';
}

/** @return array{created:int,groups:list<array{program_id:int,team_id:int,product_id:int,purchase:string}>} */
function clubYoungestPrepare(PDO $pdo,int $actorId,bool $apply): array
{
    if($actorId<1)throw new InvalidArgumentException('Příprava vyžaduje správce.');
    $plan=clubYoungestPreparePlan($pdo);
    if(!$apply)return ['created'=>0,'groups'=>array_map(static fn(array $row):array=>[
        'program_id'=>$row['program_id']??0,'team_id'=>$row['team_id']??0,'product_id'=>$row['product_id'],'purchase'=>'email_only'], $plan)];
    $reason='Příprava interních soupisek 2026/27 pro nepravidelně otevírané dětské kurzy; bez prodeje a ceny celého kurzu.';
    $pdo->beginTransaction();
    try{
        $plan=clubYoungestPreparePlan($pdo);
        $season=kisRosterCreateSeasonInTransaction($pdo,$actorId,'SCHOOL-2026-27','Školní rok 2026/27','2026-09-01','2027-08-31','school_year');
        $result=[];$created=0;
        foreach($plan as $group){
            $teamName=$group['name'].' 2026/27';
            $team=kisRosterCreateTeamInTransaction($pdo,(int)$season['id'],$actorId,$group['team_code'],$teamName,'Cyklistika',$group['age'],$reason);
            $program=clubProgramCreateInTransaction($pdo,$actorId,$group['code'],$group['name'],clubYoungestDescription($group));
            if((string)$program['status']!=='active')throw new ClubYoungestPrepareException('Program není aktivní: '.$group['code']);
            if($group['program_id']===null){
                $pdo->prepare("INSERT INTO club_program_presentations(program_id,public_name,public_summary,age_label,listing_status,sort_order,interest_enabled) VALUES(?,?,?,?,'draft',0,0)")
                    ->execute([(int)$program['id'],$group['name'],null,$group['age']]);
                clubProgramEvent($pdo,(int)$program['id'],null,'trainer',$actorId,'update_storefront_presentation',null,
                    ['public_name'=>$group['name'],'age_label'=>$group['age'],'listing_status'=>'draft','interest_enabled'=>0,'_audit_reason'=>$reason]);
                $created++;
            }
            $result[]=['program_id'=>(int)$program['id'],'team_id'=>(int)$team['id'],'product_id'=>$group['product_id'],'purchase'=>'email_only'];
        }
        $pdo->commit();return ['created'=>$created,'groups'=>$result];
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}
