<?php
declare(strict_types=1);
require_once __DIR__.'/accounting_ledger.php';
/** Immutable links for actual money returned from a specific payout, never an allocation release. */
function clubPaymentReturnOriginal(PDO $p,string $domain,int $case,int $original,array $incoming,int $amount,string $date):array {
 if(!in_array($domain,['expense','reward'],true)||$original<1||$amount<1||(int)$incoming['amount_minor']<=0||$incoming['kind']!=='income'||$date!==$incoming['effective_on'])throw new DomainException('Návrat platby vyžaduje doložený příjem a datum jeho skutečného přijetí.');
 $type=$domain==='expense'?'expense_claim':'reward_claim';$returnType=$domain==='expense'?'expense_claim_return':'reward_claim';
 $q=$p->prepare('SELECT a.*,m.amount_minor AS movement_minor,m.currency AS movement_currency FROM accounting_allocations a JOIN accounting_movements m ON m.id=a.movement_id WHERE a.id=?');$q->execute([$original]);$old=$q->fetch(PDO::FETCH_ASSOC);
 if(!$old||$old['case_type']!==$type||(int)$old['case_id']!==$case||(int)$old['amount_minor']<1||(int)$old['movement_minor']>=0||$old['movement_currency']!==$incoming['currency']||$date<$old['effective_on'])throw new DomainException('Původní výplata neodpovídá případu, měně nebo datu vrácení.');
 $q=$p->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE reverses_id=?');$q->execute([$original]);$available=(int)$old['amount_minor']+(int)$q->fetchColumn();
 $q=$p->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM club_payment_returns WHERE original_allocation_id=?');$q->execute([$original]);$available-=(int)$q->fetchColumn();
 // Older overpayment returns did not name the original payout. Conservatively reserve
 // them against every candidate so introducing this link cannot refund the same money twice.
 $q=$p->prepare("SELECT COALESCE(SUM(a.amount_minor),0) FROM accounting_allocations a JOIN accounting_movements m ON m.id=a.movement_id LEFT JOIN club_payment_returns r ON r.return_allocation_id=a.id WHERE a.case_type=? AND a.case_id=? AND m.amount_minor>0 AND r.id IS NULL");$q->execute([$returnType,$case]);$available-=(int)$q->fetchColumn();
 if($amount>$available)throw new DomainException('Návrat přesahuje dosud nevrácenou část původní výplaty.');
 return $old;
}
function clubPaymentReturnLink(PDO $p,string $domain,int $case,int $original,int $returned,int $amount):void {
 $q=$p->prepare('INSERT INTO club_payment_returns(domain,case_id,original_allocation_id,return_allocation_id,amount_minor) VALUES(?,?,?,?,?)');$q->execute([$domain,$case,$original,$returned,$amount]);
}
