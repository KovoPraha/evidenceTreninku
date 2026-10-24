<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/includes/accounting_documents.php';
require_once dirname(__DIR__,2).'/includes/accounting_export.php';
require_once dirname(__DIR__,2).'/includes/accounting_adjustments.php';
require_once dirname(__DIR__,2).'/includes/staff_workspaces.php';

/** Runs only against the disposable, migrated checkout smoke database. */
final class FamilyAccountingAcceptance
{
    public static function run(PDO $pdo,array $order,array $receipts):void
    {
        if(!str_starts_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'evidence_shop_checkout_smoke_test'))throw new RuntimeException('Disposable checkout database required.');
        $oldRoot=getenv('APP_PRIVATE_STORAGE_ROOT');$oldSession=$_SESSION??[];
        $root=sys_get_temp_dir().'/evidence-family-accounting-'.bin2hex(random_bytes(8));
        if(!mkdir($root,0700))throw new RuntimeException('Cannot create isolated accounting storage.');
        putenv('APP_PRIVATE_STORAGE_ROOT='.$root);
        $_SESSION=['trener_id'=>7,'staff_positions'=>['finance_manager'],'staff_active_position'=>'finance_manager'];
        $check=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
        $reason='LOCALHOST NEPLATIT family accounting acceptance';$today=date('Y-m-d');$orderId=(int)$order['id'];
        try {
            $issuer=accountingDocumentConfigureIssuer($pdo,['name'=>'LOCALHOST NEPLATIT zkušební oddíl','address'=>'Testovací 1, Praha','registration_id'=>'LOCALHOST','confirmed'=>'1','approval_evidence'=>$reason]);
            $rule=accountingDocumentConfigureRule($pdo,['source_type'=>'order','document_kind'=>'invoice','category_code'=>'TEST','activity'=>'TEST','title'=>'LOCALHOST synthetic classification','valid_from'=>$today,'confirmed'=>'1','approval_evidence'=>$reason]);
            foreach(['invoice'=>'TEST-FA','correction'=>'TEST-OPR'] as $kind=>$prefix)accountingDocumentConfigureSeries($pdo,['kind'=>$kind,'series_year'=>(int)date('Y'),'prefix'=>$prefix,'next_number'=>1,'confirmed'=>'1','reason'=>$reason]);
            $source=accountingDocumentSource($pdo,'order',$orderId);
            $input=['source_type'=>'order','source_id'=>$orderId,'kind'=>'invoice','effective_on'=>$today,'due_on'=>$today,'buyer'=>['name'=>'LOCALHOST NEPLATIT rodič','address'=>'Testovací 2, Praha'],'issuer_id'=>$issuer,'request_key'=>'family-document-draft','line_rules'=>array_fill_keys(array_column($source['lines'],'line_key'),$rule),'reason'=>$reason];
            $id=accountingDocumentCreateDraft($pdo,$input);
            $check($id===accountingDocumentCreateDraft($pdo,$input),'Draft retry created another document.');
            $document=accountingDocumentIssue($pdo,$id,$reason);$originalHash=$document['pdf_sha256'];$originalJson=$document['snapshot_json'];
            $check((int)$document['total_minor']===53999,'Invoice differs from the discounted order.');
            $check($originalHash===hash_file('sha256',accountingDocumentDownload($pdo,$id)),'Invoice PDF checksum mismatch.');
            $check($document['number']===accountingDocumentIssue($pdo,$id,$reason)['number'],'Issue retry consumed another number.');
            $account=accountingLedgerCreateAccount($pdo,['code'=>'LOCALHOST-BANK','name'=>$reason,'kind'=>'bank','currency'=>'CZK','opened_on'=>$today,'opening_minor'=>0,'evidence'=>$reason],7,$reason,true);
            $movements=[];
            foreach($receipts as $receipt){
                $movement=accountingLedgerReconcileSource($pdo,'club_finance_receipts',(int)$receipt['receipt_id'],(int)$account['id'],$today,$reason,7,$reason,true);
                $check($movement['id']===accountingLedgerReconcileSource($pdo,'club_finance_receipts',(int)$receipt['receipt_id'],(int)$account['id'],$today,$reason,7,$reason,true)['id'],'Receipt reconciliation duplicated money.');
                accountingLedgerClassify($pdo,(int)$movement['id'],[['rule_id'=>$rule,'amount_minor'=>(int)$movement['amount_minor']]],'family-classify-'.$movement['id'],7,$reason,true);
                $movements[]=$movement;
            }
            $check(count($movements)===3&&(int)$pdo->query('SELECT SUM(amount_minor) FROM accounting_movements')->fetchColumn()===54500,'Actual receipts must be 545 CZK once, including overpayment.');
            $check((int)$pdo->query('SELECT SUM(amount_minor) FROM accounting_allocations')->fetchColumn()===53999,'Receipt allocations must match the order and exclude overpayment.');
            try {accountingLedgerReconcileSource($pdo,'payments',(int)$order['payment_id'],(int)$account['id'],$today,$reason,7,$reason,true);throw new RuntimeException('Derived paid status became new money.');}
            catch(AccountingLedgerException $e){$check(str_contains($e->getMessage(),'Odvozená'),'Unexpected derived-payment rejection.');}
            $batch=accountingExportCreate($pdo,$today,$today,'family-export-one',7,$reason,true);
            $frozen=accountingExportDownload($pdo,(int)$batch['id'],7);$frozenHash=hash_file('sha256',$frozen['path']);
            $check((int)$batch['id']===(int)accountingExportCreate($pdo,$today,$today,'family-export-one',7,$reason,true)['id'],'Export retry duplicated revision.');
            $zip=new ZipArchive();$check($zip->open($frozen['path'])===true,'Cannot open the export.');
            $manifest=json_decode($zip->getFromName('manifest.json'),true,512,JSON_THROW_ON_ERROR);
            foreach($manifest['files'] as $entry)$check(hash('sha256',$zip->getFromName($entry['path']))===$entry['sha256'],'Archive member checksum mismatch.');
            $xlsx=$root.'/verified.xlsx';file_put_contents($xlsx,$zip->getFromName('ucetni-podklady.xlsx'));
            $check(hash('sha256',$zip->getFromName('doklady/'.$id.'.pdf'))===$originalHash,'Archive PDF differs from the issued invoice.');$zip->close();
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx);
            try {
                $rows=$book->getSheetByName('Polozky')->toArray();
                $check(count($rows)===5&&$rows[2][7]==='101'&&$rows[3][7]==='102','XLSX lost separate siblings.');
                $check($rows[2][9]==='2026-01-01'&&$rows[3][10]==='2027-12-31','XLSX lost course periods.');
                $check($book->getSheetByName('Doklady')->getCell('N2')->getValue()===$order['public_code'],'XLSX lost order reference.');
                $check($book->getSheetByName('Doklady')->getCell('O2')->getValue()===$source['variable_symbol'],'XLSX lost the original VS.');
                $check($book->getSheetByName('Penize')->getHighestDataRow()===4,'XLSX duplicated derived payment revenue.');
            } finally {$book->disconnectWorksheets();}

            // Price correction is not a physical return, another payment, or enrollment cancellation.
            $childLine=array_values(array_filter($source['lines'],static fn(array $l):bool=>(int)($l['beneficiary']['person_id']??0)===101))[0];
            $correctionId=accountingDocumentCreateCorrection($pdo,$id,[['line_key'=>$childLine['line_key'],'quantity'=>0,'amount_minor'=>1000]],'family-correction',$reason);
            accountingDocumentIssue($pdo,$correctionId,$reason);
            $allocation=$pdo->query('SELECT * FROM accounting_allocations WHERE movement_id='.(int)$movements[0]['id'])->fetch(PDO::FETCH_ASSOC);
            accountingLedgerAllocate($pdo,(int)$movements[0]['id'],'shop_order',$orderId,-1000,'family-correction-release',7,$reason,true,(int)$allocation['id'],'','',$today);
            $decision=accountingRefundDecide($pdo,['original_movement_id'=>(int)$movements[0]['id'],'original_document_id'=>$id,'correction_document_id'=>$correctionId,'amount_minor'=>1000,'decided_on'=>$today,'request_key'=>'family-refund-decision'],7,$reason,true);
            $pdo->beginTransaction();
            try {accountingAdjustmentAssertLegacyRefundAllowed($pdo,$orderId);throw new RuntimeException('Allocated receipt bypassed full-refund guard.');}
            catch(DomainException $e){$check(str_contains($e->getMessage(),'rozpracovanou'),'Unexpected legacy-refund rejection.');}
            finally {$pdo->rollBack();}
            $refundInput=['account_id'=>(int)$account['id'],'amount_minor'=>1000,'effective_on'=>$today,'source_reference'=>'LOCALHOST-REFUND','request_key'=>'family-refund-paid','evidence'=>$reason];
            $refund=accountingRefundPay($pdo,(int)$decision['id'],$refundInput,7,$reason,true);
            $check($refund['id']===accountingRefundPay($pdo,(int)$decision['id'],$refundInput,7,$reason,true)['id'],'Refund replay created more money.');
            $check(accountingLedgerOrderRefundCoverage($pdo,$orderId,'CZK')['total']===1000,'Order refund coverage lost the receipt-linked correction.');
            $payment=$pdo->query('SELECT * FROM payments WHERE id='.(int)$order['payment_id'])->fetch(PDO::FETCH_ASSOC);
            $check(!accountingLedgerRefundDerived($pdo,array_replace($payment,['refund_reference'=>'accounting-order:'.$orderId])),'Partial accounting refund masquerades as a complete refund.');
            $balance=accountingAdjustmentDocumentBalance($pdo,$id);
            $check($balance['claim_minor']===52999&&$balance['allocated_minor']===52999&&$balance['outstanding_minor']===0,'Correction and allocation release do not reconcile.');
            $revision=accountingExportCreate($pdo,$today,$today,'family-export-two',7,$reason,true);
            $snapshot=json_decode($revision['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
            $check((int)$revision['revision']===2&&(int)$revision['predecessor_id']===(int)$batch['id'],'Export revision does not replace its predecessor.');
            $check(count($snapshot['movements'])===4&&array_sum(array_column($snapshot['movements'],'amount_minor'))===53500,'Export must show 545 CZK income minus 10 CZK actual refund.');
            $check(array_sum(array_column($snapshot['documents'],'total_minor'))===52999,'Export correction does not reconcile with original invoice.');
            $check($frozenHash===hash_file('sha256',$frozen['path'])&&$originalJson===accountingDocumentGet($pdo,$id)['snapshot_json'],'New revision rewrote an old archive or issued invoice.');
            $check((int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active'")->fetchColumn()===2,'Price correction changed course enrollment.');

            require_once dirname(__DIR__,2).'/bin/production-accounting-audit.php';
            $audit=productionAccountingAudit($pdo,$root);
            $check($audit['ok'],'Read-only accounting audit failed: '.json_encode($audit['checks']));
            $check($audit['issued_pdfs_checked']===2&&$audit['archive_entries_checked']>=9,'Audit did not inspect real PDF/ZIP files.');
            $check(!$pdo->inTransaction(),'Read-only audit leaked its transaction.');
            $beforeEvents=(int)$pdo->query('SELECT COUNT(*) FROM accounting_document_events')->fetchColumn();
            $beforeExportEvents=(int)$pdo->query('SELECT COUNT(*) FROM accounting_export_events')->fetchColumn();
            // Corrupt only this disposable fixture, prove detection, then restore it exactly.
            $pdo->prepare('UPDATE accounting_documents SET snapshot_sha256=? WHERE id=?')->execute([str_repeat('0',64),$id]);
            $bad=productionAccountingAudit($pdo,$root);
            $check(!$bad['ok']&&$bad['checks']['document_snapshot_integrity']['count']===1,'Audit missed a changed document snapshot.');
            $pdo->prepare('UPDATE accounting_documents SET snapshot_sha256=? WHERE id=?')->execute([hash('sha256',$originalJson),$id]);
            $pdf=privateStorageResolve($document['pdf_storage_key']);$pdfBytes=file_get_contents($pdf);file_put_contents($pdf,'LOCALHOST damaged PDF');
            $bad=productionAccountingAudit($pdo,$root);
            $check(!$bad['ok']&&$bad['checks']['issued_pdf_integrity']['count']===1,'Audit missed a changed PDF.');
            file_put_contents($pdf,$pdfBytes);
            $pdo->prepare('UPDATE accounting_refund_payments SET amount_minor=amount_minor+1 WHERE decision_id=?')->execute([$decision['id']]);
            $bad=productionAccountingAudit($pdo,$root);
            $check(!$bad['ok']&&$bad['checks']['refund_payment_links']['count']===1,'Audit missed inconsistent refund money.');
            $pdo->prepare('UPDATE accounting_refund_payments SET amount_minor=amount_minor-1 WHERE decision_id=?')->execute([$decision['id']]);
            $archiveBytes=file_get_contents($frozen['path']);file_put_contents($frozen['path'],'LOCALHOST damaged archive');
            $bad=productionAccountingAudit($pdo,$root);
            $check(!$bad['ok']&&$bad['checks']['export_archive_integrity']['count']===1,'Audit missed a changed export archive.');
            file_put_contents($frozen['path'],$archiveBytes);
            $tampered=new ZipArchive();$check($tampered->open($frozen['path'])===true,'Cannot open synthetic ZIP.');
            $tampered->addFromString('ucetni-podklady.xlsx','LOCALHOST altered entry');$tampered->close();
            $pdo->prepare('UPDATE accounting_export_batches SET archive_sha256=? WHERE id=?')->execute([hash_file('sha256',$frozen['path']),$batch['id']]);
            $bad=productionAccountingAudit($pdo,$root);
            $check(!$bad['ok']&&$bad['checks']['export_manifest_integrity']['count']===1,'Audit missed an altered entry behind a matching outer ZIP hash.');
            file_put_contents($frozen['path'],$archiveBytes);
            $pdo->prepare('UPDATE accounting_export_batches SET archive_sha256=? WHERE id=?')->execute([$frozenHash,$batch['id']]);
            $check(productionAccountingAudit($pdo,$root)['ok'],'Restored fixture failed read-only audit.');
            $check($beforeEvents===(int)$pdo->query('SELECT COUNT(*) FROM accounting_document_events')->fetchColumn()&&$beforeExportEvents===(int)$pdo->query('SELECT COUNT(*) FROM accounting_export_events')->fetchColumn(),'Read-only audit wrote download/export events.');
            echo "Read-only accounting audit and isolated corruption detection OK\n";

            $out=trim((string)getenv('EVIDENCE_FAMILY_ACCOUNTING_OUTPUT'));
            if($out!==''){
                if(!is_dir($out)&&!mkdir($out,0700,true))throw new RuntimeException('Cannot save synthetic acceptance output.');
                copy($frozen['path'],$out.'/LOCALHOST-NEPLATIT-rev1.zip');
                copy(accountingExportDownload($pdo,(int)$revision['id'],7)['path'],$out.'/LOCALHOST-NEPLATIT-rev2.zip');
                copy(accountingDocumentDownload($pdo,$id),$out.'/LOCALHOST-NEPLATIT-faktura.pdf');
                copy($xlsx,$out.'/LOCALHOST-NEPLATIT-podklady.xlsx');
                file_put_contents($out.'/verification.json',json_encode(['status'=>'PASS','synthetic_only'=>true,'order_minor'=>53999,'actual_receipts_minor'=>54500,'overpayment_minor'=>501,'correction_minor'=>-1000,'net_cash_minor'=>53500,'net_document_minor'=>52999,'export_version'=>ACCOUNTING_EXPORT_VERSION,'archive1_sha256'=>$frozenHash,'invoice_sha256'=>$originalHash],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            }
            echo "Family order -> issued PDF -> receipts -> XLSX/ZIP -> correction/refund -> immutable revision accounting acceptance OK\n";
            require_once __DIR__.'/StockReturnAccountingAcceptance.php';
            stockReturnAccountingAcceptance($pdo,$order,$id,(int)$account['id'],$movements,$today,$reason);
        } finally {
            $_SESSION=$oldSession;$oldRoot===false?putenv('APP_PRIVATE_STORAGE_ROOT'):putenv('APP_PRIVATE_STORAGE_ROOT='.$oldRoot);
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}
            rmdir($root);
        }
    }
}
