<?php
declare(strict_types=1);
require_once __DIR__.'/club_expenses.php';
require_once __DIR__.'/staff_record_list.php';
require_once __DIR__.'/operational_cases.php';

function clubAdminFinanceActor(PDO $pdo): int
{
    if (!staffActivePositionIs('finance_manager')) throw new DomainException('Tato agenda patří hospodáři.');
    $actor=(int)($_SESSION['trener_id']??0); operationalCaseActor($pdo,$actor,'finance_manager'); return $actor;
}

function clubAdminMinor(string $s): int
{
    if(!preg_match('/^-?\d{1,10}(?:[.,]\d{1,2})?$/D',trim($s)))throw new ClubExpenseException('Částku zadejte v Kč s nejvýše dvěma desetinnými místy.');
    $s=trim($s);$negative=str_starts_with($s,'-');$a=explode('.',str_replace(',','.',ltrim($s,'-')));
    return ($negative?-1:1)*((int)$a[0]*100+(int)str_pad($a[1]??'',2,'0'));
}

/** Resolve names server-side; posted labels cannot impersonate a different selected person. */
function clubAdminPersonInput(PDO $pdo,array $input,string $prefix): array
{
    if(!array_key_exists($prefix.'_ref',$input))return $input; // Existing callers retain their explicit snapshots.
    $ref=staffListText($input,$prefix.'_ref');
    if($ref==='supplier'){
        if($prefix==='recipient')throw new ClubExpenseException('Zálohu předávejte konkrétní osobě.');
        return array_replace($input,[$prefix.'_kind'=>'supplier',$prefix.'_id'=>0]);
    }
    if(!preg_match('/^(trainer|person):([1-9][0-9]*)$/D',$ref,$m))throw new ClubExpenseException('Vyberte konkrétní osobu z nabídky.');
    $q=$pdo->prepare($m[1]==='trainer'?'SELECT jmeno AS label FROM treneri WHERE id=?':'SELECT jmeno,prijmeni FROM sportovci WHERE id=?');$q->execute([(int)$m[2]]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new ClubExpenseException('Vybraná osoba již neexistuje.');
    $label=$m[1]==='trainer'?$row['label']:trim($row['prijmeni'].' '.$row['jmeno']);
    // A correction must retain the original counterparty snapshot even after a name change.
    if($prefix!=='recipient'&&!empty($input['previous_id'])){
        $old=clubExpenseOne($pdo,'club_expense_claims',(int)$input['previous_id']);
        if($old[$prefix.'_kind']===$m[1]&&(int)$old[$prefix.'_id']===(int)$m[2])$label=$old[$prefix.'_label'];
    }
    return array_replace($input,[$prefix.'_kind'=>$m[1],$prefix.'_id'=>(int)$m[2],$prefix.'_label'=>$label]);
}

function clubAdminClaimInput(PDO $pdo,array $input): array
{
    clubAdminFinanceActor($pdo);
    foreach(['payer','creditor'] as $prefix)$input=clubAdminPersonInput($pdo,$input,$prefix);
    $input['amount_minor']=clubAdminMinor(staffListText($input,'amount'));
    $input['original_amount_minor']=staffListText($input,'original_amount')===''?$input['amount_minor']:clubAdminMinor(staffListText($input,'original_amount'));
    $items=[];
    if(isset($input['lines'])){
        if(!is_array($input['lines'])||count($input['lines'])>100)throw new ClubExpenseException('Výdaj smí mít nejvýše 100 položek.');
        foreach($input['lines'] as $line){
            if(!is_array($line))throw new ClubExpenseException('Neplatná položka výdaje.');
            if(implode('',array_map(static fn($k)=>staffListText($line,$k),['amount','activity','purpose']))==='')continue;
            if(!in_array(staffListText($line,'accepted'),['1','0'],true))throw new ClubExpenseException('U položky vyberte uznanou nebo neuznanou část.');
            $items[]=['amount_minor'=>clubAdminMinor(staffListText($line,'amount')),'accepted'=>staffListText($line,'accepted')==='1','case_id'=>(int)staffListText($line,'case_id'),'activity'=>staffListText($line,'activity'),'purpose'=>staffListText($line,'purpose'),'stock_movement_id'=>(int)staffListText($line,'stock_movement_id')];
        }
    }else{
        // Compatibility for existing integrations; the staff UI uses normal item fields.
        foreach(preg_split('/\R/u',trim(staffListText($input,'items'))) as $line){$p=explode(';',$line,6);if(count($p)<5||!in_array(trim($p[1]),['ano','ne'],true))throw new ClubExpenseException('Neplatné položkové rozdělení.');$items[]=['amount_minor'=>clubAdminMinor(trim($p[0])),'accepted'=>trim($p[1])==='ano','case_id'=>(int)$p[2],'activity'=>trim($p[3]),'purpose'=>trim($p[4]),'stock_movement_id'=>(int)($p[5]??0)];}
    }
    $input['items']=$items;return $input;
}

/** Untruncated named choices; no financial mutations and no inferred event linkage. */
function clubAdminChoices(PDO $pdo): array
{
    clubAdminFinanceActor($pdo);$out=[];
    $sql=[
        'trainer'=>'SELECT id,jmeno AS label FROM treneri ORDER BY jmeno,id',
        'person'=>'SELECT id,prijmeni,jmeno FROM sportovci ORDER BY prijmeni,jmeno,id',
        'club_event'=>'SELECT e.id,e.name AS label,(SELECT MIN(s.starts_at) FROM club_event_sessions s WHERE s.event_id=e.id) AS date_label FROM club_events e ORDER BY e.id DESC',
        'legacy_event'=>'SELECT id,nazev AS label,datum_od AS date_label,stav AS state FROM ucto_udalosti ORDER BY datum_od DESC,id DESC',
        'case'=>'SELECT id,title AS label,due_on AS date_label,state FROM club_expense_cases ORDER BY id DESC',
        'claim'=>'SELECT id,document_number AS label,supplier,creditor_label,version_no,state,document_on AS date_label,root_id FROM club_expense_claims ORDER BY id DESC',
        'advance'=>'SELECT id,recipient_label AS label,purpose,due_on AS date_label,state FROM club_expense_advances ORDER BY id DESC',
        'receipt'=>'SELECT id,poznamka AS label,datum AS date_label,castka FROM ucto_uctenky ORDER BY datum DESC,id DESC',
        'movement'=>"SELECT m.id,m.source_reference AS label,m.effective_on AS date_label,m.kind,m.amount_minor,COALESCE((SELECT SUM(a.amount_minor) FROM accounting_allocations a WHERE a.movement_id=m.id),0) AS allocated_minor FROM accounting_movements m WHERE m.kind IN ('income','expense') ORDER BY m.effective_on DESC,m.id DESC",
        'allocation'=>"SELECT id,case_type AS label,case_id,effective_on AS date_label,amount_minor FROM accounting_allocations WHERE case_type IN ('expense_claim','expense_claim_return','expense_advance_out','expense_advance_in') ORDER BY id DESC",
        'consumption'=>'SELECT id,claim_root_id,advance_id,effective_on AS date_label,amount_minor FROM club_expense_consumptions ORDER BY id DESC',
    ];
    foreach($sql as $key=>$query){$out[$key]=$pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);foreach($out[$key] as &$r){
        if($key==='person')$r['label']=$r['prijmeni'].' '.$r['jmeno'];
        if($key==='consumption')$r['label']='Výdaj #'.$r['claim_root_id'].' / záloha #'.$r['advance_id'];
        $parts=[$r['label']??'',isset($r['version_no'])?'v'.$r['version_no']:null,$r['supplier']??null,$r['creditor_label']??null,$r['purpose']??null,$r['date_label']??null,$r['state']??null];
        if(isset($r['amount_minor']))$parts[]=number_format((int)$r['amount_minor']/100,2,',',' ').' Kč';
        if(isset($r['castka']))$parts[]=number_format((float)$r['castka'],2,',',' ').' Kč';
        if($key==='movement')$parts[]='již přiřazeno '.number_format((int)$r['allocated_minor']/100,2,',',' ').' Kč';
        $r['label']=implode(' · ',array_filter($parts,static fn($v)=>$v!==null&&$v!==''));
    }unset($r);}return $out;
}

function clubAdminCaseRead(PDO $pdo,int $id): array
{
    clubAdminFinanceActor($pdo);$case=clubExpenseOne($pdo,'club_expense_cases',$id);$claims=[];$blockers=[];
    foreach($pdo->query('SELECT * FROM club_expense_claims ORDER BY root_id,version_no DESC')->fetchAll(PDO::FETCH_ASSOC) as $r){
        $linked=(int)$r['case_id']===$id;
        foreach(json_decode($r['items_json'],true,512,JSON_THROW_ON_ERROR) as $item)$linked=$linked||(int)($item['case_id']??0)===$id;
        if(!$linked)continue;$r['balance']=clubExpenseClaimBalance($pdo,(int)$r['root_id']);$claims[]=$r;
        if(!in_array($r['state'],['superseded','returned','rejected'],true)&&($r['state']!=='approved'||$r['balance']['remaining_minor']!==0))$blockers[]='Výdaj '.$r['document_number'].' — '.$r['state'].'; zbývá '.number_format($r['balance']['remaining_minor']/100,2,',',' ').' Kč.';
    }
    $q=$pdo->prepare('SELECT * FROM club_expense_advances WHERE case_id=? ORDER BY id DESC');$q->execute([$id]);$advances=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($advances as &$a){$a['balance']=clubExpenseAdvanceBalance($pdo,(int)$a['id']);if($a['state']!=='closed')$blockers[]='Záloha '.$a['recipient_label'].' není uzavřená; saldo '.number_format($a['balance']['balance_minor']/100,2,',',' ').' Kč.';}unset($a);
    $receipts=[];
    if($case['legacy_event_id']){
        $q=$pdo->prepare("SELECT u.id,u.poznamka,u.datum FROM ucto_uctenky u WHERE u.udalost_id=? AND NOT EXISTS(SELECT 1 FROM club_expense_claims c WHERE c.receipt_id=u.id AND c.state='approved')");$q->execute([$case['legacy_event_id']]);$receipts=$q->fetchAll(PDO::FETCH_ASSOC);
        if($receipts)$blockers[]='Původní účtenky bez schváleného výdaje: '.count($receipts).'.';
        $q=$pdo->prepare('SELECT zalohova_castka FROM ucto_udalosti WHERE id=?');$q->execute([$case['legacy_event_id']]);$legacy=(int)round((float)$q->fetchColumn()*100);
        if($legacy>array_sum(array_map(static fn($a)=>$a['balance']['out_minor'],$advances)))$blockers[]='Předání původní zálohy není plně doložené.';
    }
    $claimIds=array_map('intval',array_column($claims,'id'));$placeholders=implode(',',array_fill(0,count($claimIds),'?'))?:'NULL';
    $q=$pdo->prepare("SELECT e.*,t.jmeno FROM club_expense_events e LEFT JOIN treneri t ON t.id=e.actor_trainer_id WHERE (e.entity_type='case' AND e.entity_id=?) OR (e.entity_type='claim' AND e.entity_id IN ($placeholders)) OR (e.entity_type='advance' AND e.entity_id IN (SELECT id FROM club_expense_advances WHERE case_id=?)) ORDER BY e.id DESC");$q->execute(array_merge([$id],$claimIds,[$id]));$history=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('case','claims','advances','blockers','receipts','history');
}
