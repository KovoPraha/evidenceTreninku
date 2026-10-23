<?php
declare(strict_types=1);
require_once __DIR__.'/private_storage.php';
require_once __DIR__.'/accounting_ledger.php';
require_once __DIR__.'/accounting_stock_export.php';
require_once __DIR__.'/accounting_club_export.php';

const ACCOUNTING_EXPORT_VERSION = 'club-accounting-administration-v3';

function accountingExportActor(PDO $pdo,int $actor):void
{
    $lock=$pdo->inTransaction()&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $q=$pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1'.$lock);
    $q->execute([$actor]);
    if(!$q->fetchColumn())throw new DomainException('Účetní export je dostupný pouze hospodáři.');
    $q=$pdo->prepare("SELECT trainer_id FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'".$lock);$q->execute([$actor]);
    if(!$q->fetchColumn()){$q=$pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?'.$lock);$q->execute([$actor]);if(!$q->fetchColumn())throw new DomainException('Účetní export je dostupný pouze hospodáři.');}
}

function accountingExportDates(string $from,string $to):void
{
    foreach([$from,$to] as $date){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Europe/Prague'));if(!$d||$d->format('Y-m-d')!==$date)throw new DomainException('Neplatné datum období.');}
    if($from>$to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>366)throw new DomainException('Zvolte období nejvýše 367 dní.');
}

/** A consistent, immutable projection. No financial writes or synthetic receipts. */
function accountingExportSnapshot(PDO $pdo,string $from,string $to):array
{
    accountingExportDates($from,$to);
    $rows=static function(string $sql,array $args=[])use($pdo):array{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);};
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $databaseTimezone=$mysql?$pdo->query('SELECT @@session.time_zone AS session_zone,@@system_time_zone AS server_zone')->fetch(PDO::FETCH_ASSOC):['session_zone'=>'UTC','server_zone'=>'UTC'];
    $snapshot=['version'=>ACCOUNTING_EXPORT_VERSION,'period_from'=>$from,'period_to'=>$to,'timezone'=>'Europe/Prague','snapshot_timezone'=>'UTC','recorded_database_timezone'=>$databaseTimezone,
        'snapshot_at'=>(string)$pdo->query($mysql?'SELECT UTC_TIMESTAMP()':'SELECT CURRENT_TIMESTAMP')->fetchColumn(),'scope'=>'R13–R36: peníze, doklady, sklad, výdaje, zálohy, odměny a rozhodnutí',
        'status'=>'NEÚPLNÝ','limitations'=>[
            'Podklady R13–R36; označení úplnosti čeká na odsouhlasení metodiky, zdrojů a otevíracích stavů oddílu.',
            'Výdaje, zálohy a odměny jsou nepeněžní podklady a závazky. Skutečné pohyby jsou pouze jednou v listu Penize.',
            'Dosud nepřevzaté zdroje a neověřitelná skutečná data jsou uvedena ve výjimkách. Nejsou nahrazena nulou.',
            'Saldo odděluje účty, nepřidělené příjmy, vratky, závazky a zálohy. Historický stav nepřevzaté pohledávky není nahrazen nulou.',
        ]];
    $snapshot['accounts']=$rows('SELECT id,code,name,kind,currency,opened_on,opening_minor FROM accounting_accounts WHERE opened_on<=? ORDER BY id',[$to]);
    $snapshot['movements']=$rows('SELECT id,account_id,kind,amount_minor,currency,effective_on,recorded_at,source_context,source_reference,reversal_of_id,transfer_key,case_type,case_id,evidence FROM accounting_movements WHERE effective_on<=? ORDER BY effective_on,id',[$to]);
    $snapshot['allocations']=$rows('SELECT id,movement_id,case_type,case_id,amount_minor,classification_code,activity_code,effective_on,recorded_at,reverses_id FROM accounting_allocations WHERE effective_on<=? ORDER BY effective_on,id',[$to]);
    $snapshot['reconciliations']=$rows('SELECT id,account_id,as_of,ledger_minor,observed_minor,difference_minor,is_closed,evidence,recorded_at FROM accounting_reconciliations WHERE as_of<=? ORDER BY as_of,id',[$to]);
    $snapshot['classifications']=$rows('SELECT id,movement_id,splits_json,replaces_id,recorded_at FROM accounting_classifications ORDER BY id');
    $snapshot['refund_decisions']=$rows('SELECT id,original_movement_id,original_document_id,correction_document_id,amount_minor,currency,decided_on,recorded_at FROM accounting_refund_decisions WHERE decided_on<=? ORDER BY id',[$to]);
    $snapshot['refund_payments']=$rows('SELECT id,decision_id,movement_id,amount_minor,effective_on,recorded_at FROM accounting_refund_payments WHERE effective_on<=? ORDER BY id',[$to]);
    $snapshot['adjustment_events']=$rows('SELECT id,decision_id,document_id,event_type,line_key,quantity,recorded_at FROM accounting_adjustment_events WHERE recorded_at<? ORDER BY id',[(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d')]);
    $snapshot['documents']=$rows("SELECT id,kind,source_type,source_id,original_document_id,status,number,snapshot_json,snapshot_sha256,total_minor,currency,effective_on,issued_at,pdf_storage_key,pdf_sha256 FROM accounting_documents WHERE effective_on<=? AND status<>'void' ORDER BY id",[$to]);
    $snapshot['pending_sources']=accountingLedgerPendingSources($pdo,10001);
    $snapshot['exceptions']=[];
    if(count($snapshot['pending_sources'])>=10000)$snapshot['exceptions'][]='Přehled nepřevzatých zdrojů dosáhl limitu; nelze tvrdit úplnost.';
    foreach($snapshot['documents'] as &$document){
        if($document['status']!=='issued'){$snapshot['exceptions'][]='Doklad #'.$document['id'].' není vydán ('.$document['status'].').';continue;}
        $path=privateStorageResolve((string)$document['pdf_storage_key']);
        $valid=$path!==null&&hash_equals((string)$document['pdf_sha256'],(string)hash_file('sha256',$path));
        if(!$valid)$snapshot['exceptions'][]='Chybějící nebo poškozené PDF dokladu #'.$document['id'].'.';
        $document['archive_path']=$valid?'doklady/'.$document['id'].'.pdf':null;
        if(!hash_equals((string)$document['snapshot_sha256'],hash('sha256',(string)$document['snapshot_json'])))$snapshot['exceptions'][]='Neshodný datový otisk dokladu #'.$document['id'].'.';
    }unset($document);
    foreach($snapshot['movements'] as $m){
        if(in_array($m['kind'],['transfer','transfer_in','transfer_out','settlement'],true))continue;
        $classification=null;foreach($snapshot['classifications'] as $c)if((int)$c['movement_id']===(int)$m['id'])$classification=$c;
        if(!$classification)$snapshot['exceptions'][]='Pohyb #'.$m['id'].' nemá doložené úplné účetní členění.';
    }
    foreach($snapshot['accounts'] as $account){
        $proof=null;foreach($snapshot['reconciliations'] as $r)if((int)$r['account_id']===(int)$account['id']&&$r['as_of']===$to)$proof=$r;
        if(!$proof||!(int)$proof['is_closed']||(int)$proof['difference_minor']!==0)$snapshot['exceptions'][]='Účet '.$account['code'].' nemá uzavřené odsouhlasení ke konci období.';
    }
    $stock=accountingStockSnapshot($pdo,$from,$to);$snapshot=array_merge($snapshot,$stock);
    $snapshot['exceptions']=array_merge($snapshot['exceptions'],$stock['stock_exceptions']);
    $club=accountingClubSnapshot($pdo,$from,$to);$snapshot=array_merge($snapshot,$club);$snapshot['exceptions']=array_merge($snapshot['exceptions'],$club['club_exceptions']);
    return $snapshot;
}

/** Explicit text cells protect leading zeros and prevent spreadsheet formula injection. */
function accountingExportWorkbook(array $snapshot,string $path):void
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$book->removeSheetByIndex(0);
    $sheet=static function(string $name,array $headers,array $rows,array $numeric=[])use($book):void{
        $s=$book->createSheet();$s->setTitle($name);
        foreach(array_merge([$headers],$rows) as $ri=>$row)foreach(array_values($row) as $ci=>$value){
            $coord=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).($ri+1);
            if($value!==null && $ri>0&&in_array($ci,$numeric,true))$s->setCellValueExplicit($coord,(float)$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            else $s->setCellValueExplicit($coord,$value===null?'NEZNÁMÉ':(string)$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $s->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $s->getStyle('A1:'.$last.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF23465C');
        $s->freezePane('A2');$s->setAutoFilter('A1:'.$last.max(1,count($rows)+1));
        $s->getStyle('A1:'.$last.max(1,count($rows)+1))->getAlignment()->setVertical('top')->setWrapText(true);
        for($i=1;$i<=count($headers);$i++)$s->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth($name==='Manifest'?($i===1?28:95):24);
        foreach($numeric as $ci)$s->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).'2:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).max(2,count($rows)+1))->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
    };
    $meta=[];foreach(['version','period_from','period_to','timezone','snapshot_at','snapshot_timezone','scope','status'] as $k)$meta[]=[$k,$snapshot[$k]];
    $meta[]=['Časové pásmo evidence v DB',json_encode($snapshot['recorded_database_timezone'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
    foreach($snapshot['limitations'] as $v)$meta[]=['Omezení',$v];foreach($snapshot['exceptions'] as $v)$meta[]=['K dořešení',$v];
    if(isset($snapshot['revision']))$meta[]=['Revize',(string)$snapshot['revision']];
    if(isset($snapshot['replacement_mode']))$meta[]=['Režim náhrady',$snapshot['replacement_mode']];
    foreach($snapshot['changes']??[] as $change)$meta[]=['Změna proti předchozí revizi',$change['section'].' #'.$change['id'].' '.$change['change']];
    foreach($snapshot['pending_sources'] as $p)$meta[]=['Nepřevzatý zdroj',json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)];
    $meta[]=['Význam období','Penize obsahuje i starší reference označené MIMO OBRAT. Do obratu patří jen V OBDOBÍ. Částky jsou v měnových jednotkách, JSON obsahuje celé haléře.'];
    $sheet('Manifest',['Údaj','Hodnota'],$meta);
    $docs=[];$items=[];$claims=[];
    foreach($snapshot['documents'] as $d){
        $docs[]=[(string)$d['id'],$d['number'],$d['kind'],$d['source_type'],(string)$d['source_id'],$d['original_document_id'],$d['status'],$d['effective_on'],$d['issued_at'],(int)$d['total_minor']/100,$d['currency'],$d['archive_path']??null,$d['pdf_sha256']];
        $data=json_decode($d['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        foreach($data['lines']??[] as $line)$items[]=[(string)$d['id'],(string)($line['line_key']??''),$line['label']??'',(string)($line['quantity']??''),(int)($line['amount_minor']??0)/100,$line['category_code']??null,$line['activity']??null];
        if($d['status']==='issued'&&$d['original_document_id']===null)$claims[]=[(string)$d['source_type'],(string)$d['source_id'],(string)$d['id'],'Vazba na původní případ, nikoli nová pohledávka'];
    }
    $sheet('Doklady',['ID','Číslo (text)','Druh','Zdroj','ID zdroje','Původní doklad','Stav','Datum případu','Vydáno','Částka','Měna','PDF v archivu','SHA-256 PDF'],$docs,[9]);
    $sheet('Polozky',['ID dokladu','Klíč položky','Popis','Množství','Částka','Členění','Činnost'],$items,[4]);
    $money=[];foreach($snapshot['movements'] as $m)$money[]=[(string)$m['id'],(string)$m['account_id'],$m['kind'],$m['effective_on'],$m['recorded_at'],(int)$m['amount_minor']/100,$m['currency'],$m['source_context'],$m['source_reference'],$m['reversal_of_id'],$m['transfer_key'],$m['effective_on']>=$snapshot['period_from']?'V OBDOBÍ':'MIMO OBRAT'];
    $sheet('Penize',['ID pohybu','Účet','Druh','Skutečné datum','Zapsáno','Částka se znaménkem','Měna','Zdroj','Reference (text)','Původní pohyb','Převod','Obrat'],$money,[5]);
    $alloc=[];$classification=[];
    foreach($snapshot['allocations'] as $a)$alloc[]=[(string)$a['id'],(string)$a['movement_id'],$a['case_type'],(string)$a['case_id'],(int)$a['amount_minor']/100,$a['effective_on'],$a['recorded_at'],$a['reverses_id']];
    $latest=[];foreach($snapshot['classifications'] as $c)$latest[(int)$c['movement_id']]=$c;
    foreach($snapshot['movements'] as $m){$c=$latest[(int)$m['id']]??null;if(!$c)continue;foreach(json_decode($c['splits_json'],true,512,JSON_THROW_ON_ERROR) as $part)$classification[]=[(string)$m['id'],(string)$c['id'],$part['category_code']??null,$part['activity']??null,((int)$m['amount_minor']<0?-1:1)*(int)$part['amount_minor']/100,'Verze členění; ne nové peníze'];}
    $sheet('Parovani',['ID přídělu','ID pohybu','Případ','ID případu','Částka','Účinné datum','Zapsáno','Ruší příděl'],$alloc,[4]);
    $sheet('Cleneni_penez',['ID pohybu','Verze členění','Členění','Činnost','Částka se znaménkem','Význam'],$classification,[4]);
    foreach($snapshot['club_receivables']??[] as$r)$claims[]=[$r['case_type'],(string)$r['case_id'],$r['reference'],$r['label'].'; nominální '.number_format((int)$r['amount_minor']/100,2,',',' ').' '.$r['currency'].'; '.$r['historical_status']];
    $sheet('Predpisy',['Zdroj','ID případu','Doklad','Význam'],$claims);
    $balances=[];foreach($snapshot['accounts'] as $account){$opening=(int)$account['opening_minor'];$turnover=0;foreach($snapshot['movements'] as $m)if((int)$m['account_id']===(int)$account['id']){if($m['effective_on']<$snapshot['period_from'])$opening+=(int)$m['amount_minor'];else$turnover+=(int)$m['amount_minor'];}$late=$account['opened_on']>$snapshot['period_from'];$meaning=$account['name'].($late?' — doložený počátek až '.$account['opened_on'].': '.number_format((int)$account['opening_minor']/100,2,',',' ').' '.$account['currency'].'; dřívější stav neznámý':'');$balances[]=['Účet',(string)$account['id'],$meaning,$late?null:$opening/100,$turnover/100,($opening+$turnover)/100,$account['currency']];}
    foreach($snapshot['movements'] as $m)if((int)$m['amount_minor']>0&&$m['kind']==='income'){$net=(int)$m['amount_minor'];foreach($snapshot['allocations'] as $a)if((int)$a['movement_id']===(int)$m['id'])$net-=(int)$a['amount_minor'];foreach($snapshot['movements'] as $r)if((int)($r['reversal_of_id']??0)===(int)$m['id'])$net+=(int)$r['amount_minor'];$balances[]=['Nepřiděleno',(string)$m['id'],'Příjem po přídělech a skutečných vratkách',null,null,$net/100,$m['currency']];}
    foreach($snapshot['refund_decisions'] as $decision){$cancelled=false;foreach($snapshot['adjustment_events'] as $event)if((int)($event['decision_id']??0)===(int)$decision['id']&&$event['event_type']==='decision_cancelled')$cancelled=true;$paid=0;foreach($snapshot['refund_payments'] as $payment)if((int)$payment['decision_id']===(int)$decision['id'])$paid+=(int)$payment['amount_minor'];$balances[]=['Vratka k vyplacení',(string)$decision['id'],($cancelled?'Zrušeno; ':'').'pohyb '.$decision['original_movement_id'].'; opravný doklad '.($decision['correction_document_id']??'přeplatek'),null,null,($cancelled?0:(int)$decision['amount_minor']-$paid)/100,$decision['currency']];}
    $balances=array_merge($balances,accountingClubBalanceRows($snapshot));
    $sheet('Saldo',['Druh','ID','Význam','Počátek','Čistý obrat','Konec','Měna'],$balances,[3,4,5]);
    $sheet('Vydaje',['Druh','ID verze','ID případu','Číslo / verze','Dodavatel / osoba','Věřitel / příjemce','Datum','Splatnost','Stav podkladu','Celkem / limit','Uznáno','Uhrazena část','Zůstatek','Měna','Doložení'],accountingClubExpenseRows($snapshot),[9,10,11,12]);
    $sheet('Sklad',['Řádek','ID','SKU / vazba (text)','Popis','Datum','Fyzické kusy','Rezervováno','Blokováno','Dostupné','Ocenění Kč','Kusy bez ceny','Podklad / význam'],accountingStockWorkbookRows($snapshot),[5,6,7,8,9,10]);
    $book->setActiveSheetIndex(0);$writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);$writer->setPreCalculateFormulas(false);$writer->save($path);$book->disconnectWorksheets();
}

function accountingExportGet(PDO $pdo,int $id):array
{
    $q=$pdo->prepare('SELECT * FROM accounting_export_batches WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Export nenalezen.');return $r;
}

/** Archive construction is transactional: failed files never become a finished batch. */
function accountingExportCreate(PDO $pdo,string $from,string $to,string $key,int $actor,string $reason,bool $confirmed):array
{
    accountingExportDates($from,$to);$reason=trim($reason);
    if(!$confirmed||$reason===''||mb_strlen($reason)>1000||preg_match('/^[A-Za-z0-9._:-]{8,100}$/D',$key)!==1)throw new DomainException('Potvrďte přípravu exportu a vyplňte důvod.');
    if($pdo->inTransaction())throw new LogicException('Export vyžaduje vlastní transakci.');
    $hash=hash('sha256',json_encode([$from,$to,$actor,$reason],JSON_THROW_ON_ERROR));$files=[];
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    try{
        accountingExportActor($pdo,$actor);
        $pdo->query('SELECT id FROM accounting_export_mutex WHERE id=1'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetchColumn();
        $q=$pdo->prepare('SELECT * FROM accounting_export_batches WHERE request_key=?');$q->execute([$key]);$old=$q->fetch(PDO::FETCH_ASSOC);
        if($old){if(!hash_equals($old['request_sha256'],$hash))throw new DomainException('Klíč požadavku už patří jinému zadání.');$pdo->commit();return $old;}
        $snapshot=accountingExportSnapshot($pdo,$from,$to);
        $q=$pdo->prepare('SELECT id,revision,snapshot_sha256 FROM accounting_export_batches WHERE period_from=? AND period_to=? ORDER BY revision DESC LIMIT 1');$q->execute([$from,$to]);$prior=$q->fetch(PDO::FETCH_ASSOC);
        $snapshot['revision']=$prior?(int)$prior['revision']+1:1;$snapshot['predecessor_id']=$prior?(int)$prior['id']:null;
        $snapshot['replacement_mode']='Úplná náhrada předchozí revize stejného období. Nesmí se přičíst k původnímu exportu.';
        $snapshot['previous_snapshot_sha256']=$prior?$prior['snapshot_sha256']:null;
        $snapshot['changes']=[];
        if($prior){$previous=json_decode(accountingExportGet($pdo,(int)$prior['id'])['snapshot_json'],true,512,JSON_THROW_ON_ERROR);foreach(['accounts','movements','allocations','classifications','documents','refund_decisions','refund_payments','adjustment_events','reconciliations','stock_balances','stock_movements','stock_cost_revisions','stock_documents','stock_lots','stock_lot_uses','club_expenses','club_advances','club_consumptions','club_transfers','club_expense_events','club_reward_claims','club_reward_versions','club_reward_decisions','club_reward_payouts','club_reward_rules','club_decisions','club_receivables','club_attachments','club_payment_returns'] as $section){$before=[];foreach($previous[$section]??[] as $v)$before[(string)$v['id']]=hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));foreach($snapshot[$section] as $v){$id=(string)$v['id'];$hashNow=hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));if(!isset($before[$id]))$snapshot['changes'][]=['section'=>$section,'id'=>$id,'change'=>'added'];elseif($before[$id]!==$hashNow)$snapshot['changes'][]=['section'=>$section,'id'=>$id,'change'=>'changed'];unset($before[$id]);}foreach($before as $id=>$unused)$snapshot['changes'][]=['section'=>$section,'id'=>(string)$id,'change'=>'removed'];}}
        $json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $name=bin2hex(random_bytes(16));$dir=privateStorageEnsureDirectory(PRIVATE_STORAGE_ACCOUNTING_EXPORTS);
        $xlsx=$dir.'/'.$name.'.tmp.xlsx';$zipPath=$dir.'/'.$name.'.zip';$files=[$xlsx,$zipPath];
        accountingExportWorkbook($snapshot,$xlsx);
        $zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Archiv nelze vytvořit.');
        try{
            $manifest=['version'=>ACCOUNTING_EXPORT_VERSION,'files'=>[]];
            $zip->addFromString('data.json',$json);$manifest['files'][]=['path'=>'data.json','bytes'=>strlen($json),'sha256'=>hash('sha256',$json)];
            $zip->addFile($xlsx,'ucetni-podklady.xlsx');$manifest['files'][]=['path'=>'ucetni-podklady.xlsx','bytes'=>filesize($xlsx),'sha256'=>hash_file('sha256',$xlsx)];
            $statement=accountingClubStatementPdf($snapshot);$zip->addFromString('klubove-vyporadani.pdf',$statement);$manifest['files'][]=['path'=>'klubove-vyporadani.pdf','bytes'=>strlen($statement),'sha256'=>hash('sha256',$statement)];
            foreach($snapshot['club_attachments']??[] as$attachment){$path=privateStorageResolve($attachment['storage_key']);if(!$path||!hash_equals($attachment['sha256'],hash_file('sha256',$path)))throw new RuntimeException('Klubový podklad se během přípravy změnil.');$zip->addFile($path,$attachment['archive_path']);$manifest['files'][]=['path'=>$attachment['archive_path'],'bytes'=>filesize($path),'sha256'=>$attachment['sha256']];}
            foreach(array_merge($snapshot['documents'],$snapshot['stock_documents']??[]) as $document)if(!empty($document['archive_path'])){
                $path=privateStorageResolve($document['pdf_storage_key']);if(!$path||!hash_equals($document['pdf_sha256'],hash_file('sha256',$path)))throw new RuntimeException('PDF se během přípravy změnilo.');
                $zip->addFile($path,$document['archive_path']);$manifest['files'][]=['path'=>$document['archive_path'],'bytes'=>filesize($path),'sha256'=>$document['pdf_sha256']];
            }
            $zip->addFromString('manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        }finally{if(!$zip->close())throw new RuntimeException('Archiv nelze dokončit.');}
        $verify=new ZipArchive();if($verify->open($zipPath)!==true)throw new RuntimeException('Dokončený archiv nelze ověřit.');
        try{foreach($manifest['files'] as $entry){$bytes=$verify->getFromName($entry['path']);if(!is_string($bytes)||strlen($bytes)!==$entry['bytes']||!hash_equals($entry['sha256'],hash('sha256',$bytes)))throw new RuntimeException('Obsah archivu neodpovídá manifestu.');}}finally{$verify->close();}
        chmod($zipPath,0600);unlink($xlsx);
        $q=$pdo->prepare('INSERT INTO accounting_export_batches(request_key,request_sha256,period_from,period_to,revision,predecessor_id,snapshot_at,scope_code,status,snapshot_json,snapshot_sha256,storage_key,archive_sha256,created_by,reason) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$key,$hash,$from,$to,$snapshot['revision'],$snapshot['predecessor_id'],$snapshot['snapshot_at'],'r13-r36','incomplete',$json,hash('sha256',$json),'private://accounting-exports/'.$name.'.zip',hash_file('sha256',$zipPath),$actor,$reason]);
        $id=(int)$pdo->lastInsertId();accountingExportEvent($pdo,$id,$actor,'created',$reason);$result=accountingExportGet($pdo,$id);$pdo->commit();return $result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach($files as $file)if(is_file($file))unlink($file);throw $e;}
}

function accountingExportEvent(PDO $pdo,int $id,int $actor,string $action,string $reason):void
{
    $q=$pdo->prepare('INSERT INTO accounting_export_events(batch_id,actor_trainer_id,action,reason) VALUES(?,?,?,?)');$q->execute([$id,$actor,$action,$reason]);
}

function accountingExportDownload(PDO $pdo,int $id,int $actor):array
{
    accountingExportActor($pdo,$actor);$r=accountingExportGet($pdo,$id);$path=privateStorageResolve($r['storage_key']);
    if(!$path||!hash_equals($r['archive_sha256'],hash_file('sha256',$path)))throw new DomainException('Archiv chybí nebo nesouhlasí jeho otisk.');
    accountingExportEvent($pdo,$id,$actor,'downloaded','Stažení neměnné revize.');return ['path'=>$path,'row'=>$r];
}
