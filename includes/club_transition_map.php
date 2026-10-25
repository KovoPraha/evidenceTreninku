<?php
declare(strict_types=1);

/** Source descriptions are proposals to confirm, never an inventory of an uninspected legacy installation. */
function clubTransitionCatalog():array {
    return [
        'people'=>['Osoby a členství','Evidence / původní KIS: konkrétní obrazovku ověřit','person_lifecycle_admin.php','Registrář','Identifikátory osob, doložené změny členství; sporné shody samostatně'],
        'rosters'=>['Skupiny a pokračování období','Evidence / původní KIS: konkrétní obrazovku ověřit','season_preparation_admin.php','Registrář a vedoucí sportu','Časové intervaly zařazení a hierarchie; žádné automatické placené pokračování'],
        'plans'=>['Rozvrh a plánované lekce','Evidence: konkrétní obrazovku rozvrhu ověřit','planovac.php','Vedoucí sportu / pověřený trenér','Budoucí termíny, odpovědní trenéři a vazby na skupiny'],
        'attendance'=>['Docházka a omluvenky','Evidence a papír / Excel: skutečný seznam ověřit','training_closure_admin.php','Trenér a vedoucí sportu','Historické tréninky, účast a omluvy s původními identifikátory'],
        'consents'=>['Přihlášky a souhlasy','Papírové přihlášky a souhlasy','paper_registrations_admin.php','Registrář','Originály, platné verze souhlasů a dohledatelné ověření; neodhadovat podpis'],
        'medical'=>['Zdravotní podklady','Papír / Excel zdravotních prohlídek','medical_clearance_admin.php','Registrář','Platnost a soukromé originály podle potvrzeného pravidla uchování'],
        'events'=>['Akce, účast a doprava','Evidence a osobní klubová organizace: konkrétní postup ověřit','club_event_work_admin.php','Vedoucí sportu / trenér; vypořádání hospodář','Otevřené akce, účastníci a doprava; rozlišit rezervaci a skutečnou jízdu'],
        'expenses'=>['Výdaje a zálohy na akce','Papír / Excel výdajů a záloh','club_expenses_admin.php','Hospodář','Nevyúčtované zálohy, otevřené případy a originály dokladů; uzavřené období chránit'],
        'rewards'=>['Odměny trenérů a sportovců','Papír / Excel odměn','club_rewards_admin.php','Hospodář; vlastní podklady trenér','Neuzavřené výkazy, schválení a doložené sazby; nedopočítávat odhadem'],
        'communication'=>['Komunikace a osobní vyřízení','Osobní kontakt a používané zprávy: kanály ověřit','club_communication_admin.php','Správce; obsah registrář / obsluha účtů','Otevřené požadavky a nedoručené zprávy; nevytvářet zpětné hromadné rozesílání'],
    ];
}
function clubTransitionTemplate(string $key):array {
    $row=clubTransitionCatalog()[$key]??null;if(!$row)throw new DomainException('Neznámý návrh postupu.');
    [$title,$screen,$route,$role,$history]=$row;
    return ['kind'=>'agenda','entry_key'=>'agenda:'.$key,'title'=>$title,'owner_trainer_id'=>'','source_key'=>'','source_screen'=>$screen,'target_route'=>$route,'history_scope'=>$history,'mapping_status'=>'proposed','writer'=>'KIS – návrh k potvrzení','case_scope'=>$title.'; zamýšlená role: '.$role,'boundary_at'=>'Neurčeno – vyžaduje rozhodnutí vlastníka','old_system_mode'=>'Beze změny do potvrzeného přechodu','fallback'=>'Osobní evidence; určit odpovědnou osobu a kontrolu následného přepisu','evidence'=>'Návrh podle funkční analýzy; skutečný zdroj, vlastník a rozsah zatím nepotvrzeny.'];
}
function clubTransitionFields(array $input):array {
    $out=[];foreach(['source_screen'=>500,'target_route'=>100,'history_scope'=>2000] as $key=>$max){$v=$input[$key]??'';if(!is_scalar($v)||mb_strlen(trim((string)$v))>$max)throw new DomainException('Neplatný údaj mapy přechodu.');$out[$key]=trim((string)$v);}
    $state=$input['mapping_status']??'proposed';if(!is_string($state)||!in_array($state,['proposed','verified','blocked'],true))throw new DomainException('Vyberte stav ověření mapy.');$out['mapping_status']=$state;
    if($out['target_route']!==''&&!in_array($out['target_route'],array_column(clubTransitionCatalog(),2),true))throw new DomainException('Vyberte známý cílový postup z nabídky.');
    if($state==='verified'&&in_array('',array_intersect_key($out,array_flip(['source_screen','target_route','history_scope'])),true))throw new DomainException('Ověřená mapa vyžaduje konkrétní obrazovku, cíl a rozsah historie.');
    return $out;
}
function clubTransitionHistory(PDO $pdo,int $run,string $key,int $page=1):array {
    $q=$pdo->prepare('SELECT COUNT(*) FROM club_cutover_entries WHERE run_id=? AND entry_key=?');$q->execute([$run,$key]);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/25));$page=max(1,min($page,$pages));
    $q=$pdo->prepare('SELECT e.*,t.jmeno AS actor_name FROM club_cutover_entries e LEFT JOIN treneri t ON t.id=e.actor_trainer_id WHERE run_id=? AND entry_key=? ORDER BY revision DESC LIMIT 25 OFFSET '.(($page-1)*25));$q->execute([$run,$key]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$r){if(!hash_equals($r['data_sha256'],hash('sha256',$r['data_json'])))throw new DomainException('Otisk historie registru nesouhlasí.');$r['data']=json_decode($r['data_json'],true,512,JSON_THROW_ON_ERROR);}unset($r);
    return compact('rows','page','pages','total');
}
