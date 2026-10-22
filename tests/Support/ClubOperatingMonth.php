<?php
declare(strict_types=1);
/** A single synthetic case chain; never a live pilot or human/accountant sign-off. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$app=dirname(__DIR__,2);require $app.'/config.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(DB_NAME!=='evidence_b1'||DB_HOST!=='127.0.0.1;port=33426'||($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true)throw new RuntimeException('Owned synthetic environment required.');
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$identity=$pdo->query('SELECT @@port port,@@datadir path')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',rtrim($identity['path'],'/\\')))!==strtolower(str_replace('\\','/',$runtime.'/mysql')))throw new RuntimeException('Unexpected database identity.');
require_once $app.'/includes/staff_workspaces.php';
require_once $app.'/includes/club_expenses.php';
require_once $app.'/includes/club_rewards.php';
require_once $app.'/includes/training_closure.php';
require_once $app.'/includes/shop_assisted_sale.php';
require_once $app.'/includes/shop_bank_settings.php';
require_once $app.'/includes/financial_decisions.php';
$run='LOCALHOST NEPLATIT R37 '.bin2hex(random_bytes(5));$key='r37-'.bin2hex(random_bytes(7));$checks=[];
$to=date('Y-m-d');$from=date('Y-m-d',strtotime('-29 days'));$activityDate=date('Y-m-d',strtotime('-20 days'));
function cmCheck(bool $condition,string $label):void {if(!$condition)throw new RuntimeException('FAIL '.$label);$GLOBALS['checks'][]=$label;}
function cmInsert(string $table,array $data):int {$p=$GLOBALS['pdo'];$p->prepare('INSERT INTO '.$table.'('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));return(int)$p->lastInsertId();}
function cmActor(string $role):int {$id=$GLOBALS['actors'][$role];$_SESSION=['trener_id'=>$id,'role'=>'admin','staff_positions'=>[$role],'staff_active_position'=>$role,'staff_superadmin'=>false];return$id;}
function cmMovement(int $amount,string $suffix):array {return accountingLedgerRecord($GLOBALS['pdo'],['account_id'=>$GLOBALS['account']['id'],'kind'=>$amount>0?'income':'expense','amount_minor'=>$amount,'effective_on'=>$GLOBALS['to'],'source_context'=>'synthetic-r37','source_reference'=>$GLOBALS['key'].'-'.$suffix,'request_key'=>$GLOBALS['key'].'-'.$suffix,'evidence'=>$GLOBALS['run']],$GLOBALS['actors']['finance_manager'],$GLOBALS['run'],true);}
function cmApprove(array $claim):array {$p=$GLOBALS['pdo'];clubExpenseClaimTransition($p,(int)$claim['id'],'submit',$GLOBALS['actors']['finance_manager'],$GLOBALS['run'],true);return clubExpenseClaimTransition($p,(int)$claim['id'],'approve',$GLOBALS['actors']['finance_second'],$GLOBALS['run'],true);}
function cmDenied(callable $action,string $label):void {try{$action();}catch(DomainException|AccountingLedgerException|ClubExpenseException|TrainingClosureException $e){cmCheck(true,$label);return;}throw new RuntimeException('Expected denial: '.$label);}
foreach(['finance_manager','finance_second','sports_lead','coach','catalog_manager','order_operator'] as $role){$id=cmInsert('treneri',['jmeno'=>$run.' '.$role,'email'=>bin2hex(random_bytes(10)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);cmInsert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$role==='finance_second'?'finance_manager':$role,'is_default'=>1]);$actors[$role]=$id;}
$person=cmInsert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run,'hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'','stav_clenstvi'=>'aktivni']);
$account=accountingLedgerCreateAccount($pdo,['code'=>$key,'name'=>$run,'kind'=>'bank','opened_on'=>$from,'opening_minor'=>1000000,'evidence'=>$run],cmActor('finance_manager'),$run,true);
// Closed actual attendance feeds the coach's submitted and approved reward.
$training=cmInsert('treninky',['datum'=>$activityDate,'napln'=>$run,'delka'=>2,'kategorie'=>'draha']);
cmInsert('trenink_trener',['trenink_id'=>$training,'trener_id'=>$actors['coach']]);cmInsert('trenink_sportovec',['trenink_id'=>$training,'sportovec_id'=>$person]);
$closed=trainingClosureClose($pdo,$training,0,['actual_started_at'=>$activityDate.' 15:00:00','actual_ended_at'=>$activityDate.' 17:00:00','participant_ids'=>[$person],'coach_ids'=>[$actors['coach']],'record_source'=>'paper','reason'=>$run,'confirmed'=>true],cmActor('sports_lead'));
cmCheck(count(trainingClosedActivitiesForPerson($pdo,$person,$from,$to))===1,'Actual attendance counted once in person history');
$rule=clubRewardRule($pdo,['kind'=>'coach','unit'=>'hour','rate_minor'=>25000,'valid_from'=>$from,'valid_to'=>$to,'title'=>$run,'approval_evidence'=>$run,'accounting_method'=>'SYNTHETIC explicit payable basis; no payroll calculation'],cmActor('sports_lead'),$run,true);
$reward=clubRewardSubmit($pdo,['kind'=>'coach','subject_id'=>$actors['coach'],'rule_id'=>$rule,'date_from'=>$from,'date_to'=>$to,'recipient_kind'=>'trainer','recipient_id'=>$actors['coach'],'recipient_evidence'=>$run,'request_key'=>$key.'-reward'],cmActor('coach'),$run,true);
cmCheck((int)$reward['amount_minor']===50000,'Two actual hours at explicit 250 CZK produce 500 CZK claim');
clubRewardDecide($pdo,(int)$reward['id'],'approved',cmActor('sports_lead'),$run,true);
// One event advance, accepted spending and actual cash return.
$case=clubExpenseCreateCase($pdo,['leader_trainer_id'=>$actors['coach'],'title'=>$run,'due_on'=>$to,'evidence'=>$run],cmActor('finance_manager'),$run,true);
$advance=clubExpenseCreateAdvance($pdo,['case_id'=>$case['id'],'recipient_kind'=>'trainer','recipient_id'=>$actors['coach'],'recipient_label'=>$run.' coach','payee_evidence'=>$run,'limit_minor'=>500000,'due_on'=>$to,'purpose'=>$run,'request_key'=>$key.'-advance'],$actors['finance_manager'],$run,true);
clubExpenseAdvanceTransition($pdo,(int)$advance['id'],'approve',$actors['finance_second'],$run,true);
$out=cmMovement(-500000,'advance-out');clubExpenseAllocate($pdo,'expense_advance_out',(int)$advance['id'],(int)$out['id'],500000,$key.'-advance-allocation',$to,$actors['finance_manager'],$run,true);
$base=['supplier'=>$run,'document_number'=>$key.'-receipt','document_on'=>$activityDate,'due_on'=>$to,'amount_minor'=>420000,'funding_source'=>'advance','payer_kind'=>'trainer','payer_id'=>$actors['coach'],'payer_label'=>$run.' coach','creditor_kind'=>'trainer','creditor_id'=>$actors['coach'],'creditor_label'=>$run.' coach','payee_evidence'=>$run,'purpose'=>$run,'document_evidence'=>$run.' paper original','case_id'=>$case['id'],'request_key'=>$key.'-spend','items'=>[['amount_minor'=>420000,'accepted'=>true,'activity'=>'club','purpose'=>$run]]];
$spend=cmApprove(clubExpenseCreateClaim($pdo,$base,$actors['finance_manager'],$run,true));
clubExpenseConsume($pdo,(int)$spend['root_id'],(int)$advance['id'],420000,$key.'-consume',$to,$actors['finance_manager'],$run,true);
cmDenied(fn()=>clubExpenseAdvanceTransition($pdo,(int)$advance['id'],'close',$actors['finance_manager'],$run,true),'Unreturned 800 CZK blocks closing');
$returned=cmMovement(80000,'advance-return');clubExpenseAllocate($pdo,'expense_advance_in',(int)$advance['id'],(int)$returned['id'],80000,$key.'-return-allocation',$to,$actors['finance_manager'],$run,true);
clubExpenseAdvanceTransition($pdo,(int)$advance['id'],'close',$actors['finance_manager'],$run,true);clubExpenseCloseCase($pdo,(int)$case['id'],$actors['finance_manager'],$run,true);
cmCheck(clubExpenseAdvanceBalance($pdo,(int)$advance['id'])['balance_minor']===0,'5000 advance = 4200 approved spending + actual 800 return');
// One 1100 CZK bank/cash movement pays a 600 expense AND the 500 reward.
$personal=cmApprove(clubExpenseCreateClaim($pdo,array_replace($base,['case_id'=>null,'document_number'=>$key.'-personal','request_key'=>$key.'-personal','funding_source'=>'personal','amount_minor'=>60000,'items'=>[['amount_minor'=>60000,'accepted'=>true,'activity'=>'club','purpose'=>$run]]]),$actors['finance_manager'],$run,true));
$shared=cmMovement(-110000,'shared-pay');clubExpenseAllocate($pdo,'expense_claim',(int)$personal['root_id'],(int)$shared['id'],60000,$key.'-personal-pay',$to,$actors['finance_manager'],$run,true);
$payInput=['movement_id'=>(int)$shared['id'],'amount_minor'=>50000,'paid_on'=>$to,'received_by'=>$run.' coach signed','request_key'=>$key.'-reward-pay'];
$paid=clubRewardPay($pdo,(int)$reward['claim_id'],$payInput,cmActor('finance_manager'),$run,true);
cmCheck($paid===clubRewardPay($pdo,(int)$reward['claim_id'],$payInput,$actors['finance_manager'],$run,true),'Reward retry reuses same payout');
cmCheck(clubExpenseClaimBalance($pdo,(int)$personal['root_id'])['remaining_minor']===0&&clubRewardPaid($pdo,(int)$reward['claim_id'])===50000,'Shared movement settles expense and reward exactly once');
cmDenied(fn()=>accountingLedgerRecord($pdo,['account_id'=>$account['id'],'kind'=>'reversal','amount_minor'=>110000,'effective_on'=>$to,'source_context'=>'synthetic-r37','source_reference'=>$key.'-bypass','request_key'=>$key.'-bypass','evidence'=>$run,'reversal_of_id'=>$shared['id']],$actors['finance_manager'],$run,true),'Generic reversal cannot erase assigned expense and reward');
// Clothing remains the same canonical stock and order workflow.
$product=cmInsert('shop_products',['external_product_key'=>$key,'name'=>$run,'offer_type'=>'goods','catalog_status'=>'active']);
$variant=cmInsert('shop_variants',['product_id'=>$product,'sku'=>$key,'attributes_json'=>'{"size":"M"}','price_mode'=>'fixed','amount_minor'=>10000,'currency'=>'CZK','stock_quantity_decimal'=>'2','unit_code'=>'piece','visible'=>1,'catalog_status'=>'active']);
cmInsert('shop_product_publications',['product_id'=>$product,'status'=>'active','public_name'=>$run,'public_summary'=>'Synthetic personal sale','decision_note'=>$run]);
shopInventoryOpen($pdo,cmActor('catalog_manager'),$variant,['physical_quantity'=>2,'blocked_quantity'=>0,'evidence'=>$run,'effective_on'=>$from],$run,true,$key.'-stock-open');
$q=$pdo->prepare('SELECT id FROM shop_stock_lots WHERE variant_id=?');$q->execute([$variant]);
shopInventoryCorrectCost($pdo,cmActor('finance_manager'),(int)$q->fetchColumn(),6000,$to,$run,$run,true,$key.'-stock-cost');
$bank=shopBankSettingsEffective($pdo);
$payer=cmInsert('verejni_uzivatele',['jmeno'=>'LOCALHOST','prijmeni'=>$run,'email'=>bin2hex(random_bytes(10)).'@example.invalid','heslo_hash'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'aktivni'=>1,'email_overeno'=>1]);
cmInsert('account_person_roles',['account_id'=>$payer,'sportovec_id'=>$person,'relation_role'=>'guardian','status'=>'approved','valid_from'=>$from,'created_by_trainer_id'=>$actors['sports_lead'],'decision_note'=>$run.' explicit synthetic relationship']);
$sale=shopAssistedSalePlace($pdo,$variant,1,['first_name'=>'LOCALHOST','last_name'=>$run,'email'=>'','confirmed'=>'1','expected_unit_minor'=>10000],cmActor('order_operator'),$run,bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),$bank,$payer,$person,$run.' payer',$run.' receiver');
cmCheck(shopInventoryBalance($pdo,$variant)['reserved_quantity']===1,'Personal sale reserves one physical garment');
$q=$pdo->prepare('SELECT id FROM payments WHERE payable_type=? AND payable_id=?');$q->execute(['shop_order',$sale['id']]);$payment=(int)$q->fetchColumn();
shopOrderAdminConfirmBankPayment($pdo,$payment,$actors['finance_manager'],$run,true);
$incoming=accountingLedgerReconcileSource($pdo,'payments',$payment,(int)$account['id'],$to,$run,$actors['finance_manager'],$run,true);
$q=$pdo->prepare('SELECT id FROM shop_order_items WHERE order_id=?');$q->execute([$sale['id']]);$item=(int)$q->fetchColumn();
$issue=shopFulfillmentIssue($pdo,(int)$sale['id'],[$item=>1],cmActor('order_operator'),$run.' receiver',date('Y-m-d H:i:s'),$run,$key.'-issue');
cmCheck(shopInventoryBalance($pdo,$variant)['physical_quantity']===1&&shopInventoryBalance($pdo,$variant)['reserved_quantity']===0,'Actual garment issue clears reservation exactly once');
cmCheck(accountingLedgerBalance($pdo,(int)$account['id'],$to)['closing_minor']===480000,'Cash reconciles: 10000 opening -5000 +800 -1100 +100 = 4800 CZK');
$export=accountingExportCreate($pdo,$from,$to,$key.'-export',cmActor('finance_manager'),$run,true);$snapshot=json_decode($export['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
$money=array_filter($snapshot['movements'],fn($m)=>(int)$m['account_id']===(int)$account['id']);
cmCheck(count($money)===4&&array_sum(array_column($money,'amount_minor'))===-520000,'One export contains four real movements; consumption and payout produce no duplicate money');
cmCheck(in_array((int)$reward['claim_id'],array_map('intval',array_column($snapshot['club_reward_claims'],'id')),true)&&in_array((int)$personal['id'],array_map('intval',array_column($snapshot['club_expenses'],'id')),true),'Same export links the month expense and sporting reward');
$download=accountingExportDownload($pdo,(int)$export['id'],$actors['finance_manager']);
cmCheck($snapshot['status']==='NEÚPLNÝ','Synthetic rehearsal cannot declare accountant acceptance or real source completeness');
$report=['status'=>'PASS','synthetic_only'=>true,'production_mutations'=>false,'human_uat'=>false,'period_from'=>$from,'period_to'=>$to,'checks'=>count($checks),'scenarios'=>$checks,'export_id'=>(int)$export['id'],'export_sha256'=>$export['archive_sha256'],'account_id'=>(int)$account['id'],'closing_minor'=>480000];
file_put_contents($runtime.'/club-operating-month.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
