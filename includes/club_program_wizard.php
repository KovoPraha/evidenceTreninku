<?php
declare(strict_types=1);

require_once __DIR__.'/shop_product_image.php';
require_once __DIR__.'/kis_roster.php';
require_once __DIR__.'/club_program_terms.php';

final class ClubProgramWizardException extends RuntimeException{}

/** @return array{seasons:list<array<string,mixed>>,teams:list<array<string,mixed>>,categories:list<string>,terms:array<string,list<array<string,mixed>>>} */
function clubProgramWizardReferenceData(PDO $pdo):array
{
    $categories=$pdo->query("SELECT DISTINCT category_path FROM shop_product_categories WHERE TRIM(category_path)<>'' ORDER BY category_path")
        ->fetchAll(PDO::FETCH_COLUMN);
    $terms=['program_cancellation'=>[],'program_consent'=>[]];
    if(clubProgramTermsRegistryAvailable($pdo)){
        $rows=$pdo->query("SELECT id,scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,created_at FROM club_event_term_versions WHERE status='active' AND consent_purpose IN ('program_cancellation','program_consent') ORDER BY created_at DESC,id DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as$row)$terms[(string)$row['consent_purpose']][]=$row;
    }
    return['seasons'=>kisRosterSeasons($pdo),'teams'=>kisRosterTeams($pdo),'categories'=>array_values(array_map('strval',$categories)),'terms'=>$terms];
}

/**
 * @param array<string,mixed> $input
 * @return array{product_id:int,variant_id:int,program_id:int,offer_id:int,season_id:int,team_id:int,image_id:?int,category_path:string}
 */
function clubProgramWizardCreate(
    PDO $pdo,int $actorId,array $input,?string $imageSource=null,bool $uploaded=true,?string $applicationRoot=null
):array{
    $reason=trim((string)($input['reason']??''));$confirmed=($input['confirmed']??false)===true;
    shopManualCatalogDecision($actorId,$reason,$confirmed);
    $requestKey=strtolower(trim((string)($input['request_key']??'')));
    if(preg_match('/^[a-f0-9]{32}$/D',$requestKey)!==1)throw new InvalidArgumentException('Průvodce nemá platný jednorázový klíč. Obnovte stránku.');
    $name=shopManualCatalogText((string)($input['name']??''),160,'Název kroužku');
    $description=shopManualCatalogText((string)($input['description']??''),4000,'Veřejný popis');
    if($description==='')throw new InvalidArgumentException('Veřejný popis je povinný.');
    if(strtoupper(trim((string)($input['currency']??'')))!=='CZK')throw new InvalidArgumentException('Průvodce nyní bezpečně podporuje pouze měnu CZK.');
    $category=clubProgramWizardCategory((string)($input['category_path']??''));
    $suffix=strtoupper(substr($requestKey,0,6));$slug=clubProgramWizardSlug($name);
    $programCode=clubProgramCode((string)($input['program_code']??'')?:substr($slug.'-'.$suffix,0,64));
    $offerCode=clubProgramCode((string)($input['offer_code']??'')?:substr($slug.'-'.$suffix.'-OFFER',0,64));
    $sku=strtoupper(trim((string)($input['sku']??'')));
    if($sku==='')$sku=substr(shopCatalogManualSkuPrefix().$slug.'-'.$suffix,0,64);
    $amount=$input['amount_minor']??null;if(!is_int($amount))throw new InvalidArgumentException('Cena musí být zadána přesně v haléřích.');
    $attributes=trim((string)($input['attributes_json']??'{}'));
    $stored=null;$applicationRoot??=dirname(__DIR__);
    if($imageSource!==null&&$imageSource!=='')$stored=shopProductImageStoreFile($imageSource,$uploaded,$applicationRoot);
    try{
        $pdo->beginTransaction();
        $catalog=shopManualCatalogCreateInTransaction($pdo,$actorId,[
            'name'=>$name,'short_description'=>$description,'offer_type'=>'program','visibility'=>'visible','item_type'=>'service',
        ],[
            'sku'=>$sku,'ean'=>'','attributes_json'=>$attributes,'amount_minor'=>$amount,'compare_at_amount_minor'=>null,
            'includes_vat'=>clubProgramWizardNullableInt($input['includes_vat']??null),'vat_rate_basis_points'=>clubProgramWizardNullableInt($input['vat_rate_basis_points']??null),
            'stock_quantity_decimal'=>'','unit_code'=>'person','visible'=>1,
        ],$reason,true);
        $productId=(int)$catalog['product_id'];$variantId=(int)$catalog['variant_id'];$imageId=null;
        $pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,?,1,0)')->execute([$productId,$category]);
        shopManualCatalogEvent($pdo,$productId,null,$actorId,'assign_category',null,['category_path'=>$category,'is_default'=>1,'sort_order'=>0],$reason);
        if($stored!==null)$imageId=(int)shopProductImageAddStoredInTransaction($pdo,$actorId,$productId,$stored,0,$reason,true)['id'];

        [$seasonId,$teamId]=clubProgramWizardTargetInTransaction($pdo,$actorId,$input,$reason);
        $program=clubProgramCreateInTransaction($pdo,$actorId,$programCode,$name,$description);
        $offer=clubProgramCreateOfferInTransaction(
            $pdo,$actorId,(int)$program['id'],$seasonId,$teamId,$productId,$variantId,$offerCode,$name,
            (string)($input['starts_on']??''),(string)($input['ends_on']??''),
            clubProgramWizardNullableText($input['sales_open_at']??null),clubProgramWizardNullableText($input['sales_close_at']??null),
            clubProgramWizardNullableInt($input['capacity']??null),'active',
            clubProgramWizardNullableInt($input['birth_year_from']??null),clubProgramWizardNullableInt($input['birth_year_to']??null)
        );
        foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){
            $text=clubProgramWizardTermText($pdo,$purpose,$input);
            clubProgramTermsConfigureInTransaction($pdo,$actorId,'program',(int)$program['id'],$purpose,$text,true);
        }
        shopManualCatalogEvent($pdo,$productId,$variantId,$actorId,'create_program_wizard',null,[
            'product_id'=>$productId,'variant_id'=>$variantId,'program_id'=>(int)$program['id'],'offer_id'=>(int)$offer['id'],
            'season_id'=>$seasonId,'team_id'=>$teamId,'category_path'=>$category,'image_id'=>$imageId,
        ],$reason);
        shopCatalogPublicationActivateInTransaction($pdo,$productId,$actorId,$name,$description,$reason,true);
        $pdo->commit();
        return['product_id'=>$productId,'variant_id'=>$variantId,'program_id'=>(int)$program['id'],'offer_id'=>(int)$offer['id'],'season_id'=>$seasonId,'team_id'=>$teamId,'image_id'=>$imageId,'category_path'=>$category];
    }catch(Throwable$exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($stored!==null)shopProductImageQuarantine(shopProductImagePath((string)$stored['image_url'],$applicationRoot),$applicationRoot);
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopManualCatalogException||$exception instanceof ShopProductImageException||$exception instanceof KisRosterException||$exception instanceof ClubProgramException||$exception instanceof ClubProgramTermsException||$exception instanceof ShopCatalogPublicationException||$exception instanceof ClubProgramWizardException)throw$exception;
        throw new ClubProgramWizardException('Kroužek se nepodařilo vypsat; žádná jeho část nebyla zveřejněna.',0,$exception);
    }
}

/** @param array<string,mixed> $input @return array{int,int} */
function clubProgramWizardTargetInTransaction(PDO $pdo,int $actorId,array $input,string $reason):array
{
    if(!$pdo->inTransaction())throw new LogicException('Výběr soupisky vyžaduje otevřenou transakci.');
    $mode=(string)($input['team_mode']??'existing');
    if($mode==='existing'){
        $teamId=(int)($input['team_id']??0);$sql="SELECT t.id,t.season_id FROM club_teams t JOIN club_seasons s ON s.id=t.season_id WHERE t.id=? AND t.status='active' AND s.status='active'";
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $statement=$pdo->prepare($sql);$statement->execute([$teamId]);$team=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$team)throw new ClubProgramWizardException('Vybraná aktivní soupiska nebyla nalezena.');
        return[(int)$team['season_id'],(int)$team['id']];
    }
    if($mode!=='new')throw new InvalidArgumentException('Vyberte existující nebo novou soupisku.');
    $season=kisRosterCreateSeasonInTransaction($pdo,$actorId,(string)($input['season_code']??''),(string)($input['season_name']??''),(string)($input['season_starts_on']??''),(string)($input['season_ends_on']??''),(string)($input['season_type']??''));
    $team=kisRosterCreateTeamInTransaction($pdo,(int)$season['id'],$actorId,(string)($input['team_code']??''),(string)($input['team_name']??''),(string)($input['team_discipline']??''),(string)($input['team_age_label']??''),$reason,null);
    return[(int)$season['id'],(int)$team['id']];
}

/** @param array<string,mixed> $input */
function clubProgramWizardTermText(PDO $pdo,string $purpose,array $input):string
{
    if(!in_array($purpose,CLUB_PROGRAM_TERM_PURPOSES,true))throw new InvalidArgumentException('Typ podmínek není podporován.');
    $source=(string)($input[$purpose.'_source']??'new');
    if($source==='new')return(string)($input[$purpose.'_text']??'');
    if($source!=='existing')throw new InvalidArgumentException('Vyberte existující nebo nový text podmínek.');
    $id=(int)($input[$purpose.'_version_id']??0);$sql="SELECT consent_text_plain FROM club_event_term_versions WHERE id=? AND consent_purpose=? AND status='active'";
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$id,$purpose]);$text=$statement->fetchColumn();
    if(!is_string($text)||trim($text)==='')throw new ClubProgramWizardException('Vybraná verze podmínek už není platná.');
    return$text;
}

function clubProgramWizardSlug(string $value):string
{
    $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);$ascii=is_string($ascii)?strtoupper($ascii):'PROGRAM';
    $slug=trim((string)preg_replace('/[^A-Z0-9]+/','-',$ascii),'-');return$slug!==''?substr($slug,0,42):'PROGRAM';
}
function clubProgramWizardCategory(string $value):string
{
    $value=trim($value);if($value===''||mb_strlen($value,'UTF-8')>500||preg_match('/[<\x00-\x1F]/u',$value)===1)throw new InvalidArgumentException('Kategorie musí být prostý text do 500 znaků.');return$value;
}
function clubProgramWizardNullableInt(mixed $value):?int{return$value===null||trim((string)$value)===''?null:(int)$value;}
function clubProgramWizardNullableText(mixed $value):?string{$value=trim((string)$value);return$value===''?null:$value;}
