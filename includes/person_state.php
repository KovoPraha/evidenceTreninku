<?php
declare(strict_types=1);

require_once __DIR__.'/sportovec_status_lib.php';
require_once __DIR__.'/member_charge_read.php';

/** Staff read model only. Not an authorization or pricing shortcut. */
function personStateSummary(PDO $pdo,int $personId,?string $onDate=null): array
{
    $date=$onDate??date('Y-m-d');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new InvalidArgumentException('Neplatné datum přehledu.');
    $stmt=$pdo->prepare('SELECT * FROM sportovci WHERE id=?');$stmt->execute([$personId]);
    $person=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$person) throw new InvalidArgumentException('Osoba neexistuje.');
    $membership=sportovecStatusCompute($person);
    $relations=[];$rosters=[];$programs=[];$reviews=[];
    if (memberChargeReadTableExists($pdo,'account_person_roles') && memberChargeReadTableExists($pdo,'verejni_uzivatele')) {
        $stmt=$pdo->prepare('SELECT r.id,r.account_id,r.relation_role,r.status,r.valid_from,r.valid_to,u.aktivni,u.email_overeno FROM account_person_roles r JOIN verejni_uzivatele u ON u.id=r.account_id WHERE r.sportovec_id=? ORDER BY r.id');
        $stmt->execute([$personId]);$relations=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $now=$onDate===null?date('Y-m-d H:i:s'):$date.' 00:00:00';
        foreach ($relations as &$relation) $relation['access_active']=(int)$relation['aktivni']===1 && (int)$relation['email_overeno']===1
            && $relation['status']==='approved' && in_array($relation['relation_role'],['self','guardian'],true)
            && $relation['valid_from']<=$now && ($relation['valid_to']===null || $relation['valid_to']>$now);
        unset($relation);
    }
    if (memberChargeReadTableExists($pdo,'club_roster_members') && memberChargeReadTableExists($pdo,'club_teams')) {
        $stmt=$pdo->prepare("SELECT DISTINCT t.id,t.name FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE m.sportovec_id=? AND m.status='active' AND t.status='active' AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) ORDER BY t.name,t.id");
        $stmt->execute([$personId,$date,$date]);$rosters=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (memberChargeReadTableExists($pdo,'club_program_enrollments')) {
        $stmt=$pdo->prepare('SELECT id,offer_id,status,valid_from,valid_to FROM club_program_enrollments WHERE sportovec_id=? ORDER BY id');
        $stmt->execute([$personId]);$programs=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($programs as &$program) $program['active_now']=$program['status']==='active' && $program['valid_from']<=$date && ($program['valid_to']===null || $program['valid_to']>=$date);
        unset($program);
    }
    if (memberChargeReadTableExists($pdo,'person_identity_reviews')) {
        $stmt=$pdo->prepare('SELECT id,source_namespace,source_id,status,reason,decided_by_trainer_id,decided_at FROM person_identity_reviews WHERE sportovec_id=? ORDER BY id');
        $stmt->execute([$personId]);$reviews=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $charges=memberChargeRowsForSportovec($pdo,$personId);
    return ['person_id'=>$personId,'on_date'=>$date,'membership'=>$membership,'relations'=>$relations,'rosters'=>$rosters,'programs'=>$programs,'charges'=>$charges,'identity_reviews'=>$reviews];
}

/** Staff only: do not expose other family accounts to a public account. */
function personStateSummaryHtml(array $summary): string
{
    $h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $membership=$summary['membership'];
    $counts=[];
    foreach ($summary['charges'] as $charge) $counts[$charge['status']]=($counts[$charge['status']]??0)+1;
    $chargeLabels=['pending'=>'čeká na úhradu','paid'=>'uhrazeno','cancelled'=>'stornováno','refund_required'=>'k vrácení','refunded'=>'vráceno'];
    $chargeParts=[];foreach($counts as $state=>$count)$chargeParts[]=$count.' × '.($chargeLabels[$state]??$state);
    $accountIds=[];$activeIds=[];
    foreach($summary['relations'] as $relation){$accountIds[(int)$relation['account_id']]=true;if($relation['access_active'])$activeIds[(int)$relation['account_id']]=true;}
    $html='<section class="card my-3"><div class="card-body"><h2 class="h5">Členství, přístupy a účast</h2><dl class="row mb-0">';
    $rows=[
        'Členství'=>$membership['label'].($membership['verification']==='review'?' · k ověření':'').' — '.$membership['reason'],
        'Účty a vztahy'=>count($accountIds).' navázaných účtů; '.count($activeIds).' s aktuálním schváleným přístupem. Samotný kontakt přístup nedává.',
        'Aktuální soupisky'=>$summary['rosters']?implode(', ',array_column($summary['rosters'],'name')):'Bez aktuálního zařazení.',
        'Účast v programech'=>count(array_filter($summary['programs'],static fn(array $p):bool=>$p['active_now'])).' aktuálních účastí. Přihláška ani platba samy nenahrazují přijetí.',
        'Platební povinnosti'=>$chargeParts?implode('; ',$chargeParts):'Bez evidovaných předpisů. Tento údaj nepotvrzuje bezdlužnost ani členství.',
    ];
    foreach($rows as $label=>$value)$html.='<dt class="col-md-3">'.$h($label).'</dt><dd class="col-md-9">'.$h($value).'</dd>';
    return $html.'</dl><p class="small text-muted mb-0">Klubová cena vychází z platné schválené vazby aktivního ověřeného účtu a aktuální soupisky. Členství, přístup, účast a úhrady mají samostatný stav.</p></div></section>';
}
