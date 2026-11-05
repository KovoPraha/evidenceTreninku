<?php

declare(strict_types=1);

require_once __DIR__.'/monthly_membership_catalog.php';
require_once __DIR__.'/member_fee_plan.php';
require_once __DIR__.'/family_portal.php';
require_once __DIR__.'/fio_readonly_import.php';
require_once __DIR__.'/roster_hierarchy.php';
require_once __DIR__.'/accounting_documents.php';

function monthlyMembershipToday(?DateTimeImmutable $now = null): string
{
    return ($now ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->setTimezone(new DateTimeZone('Europe/Prague'))
    ->format('Y-m-d');
}

function monthlyMembershipLock(PDO $pdo): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Členství vyžaduje transakci.');
    }
    // Shared reference lock precedes membership state; manual receipts use this same lock.
    if (clubFinanceTableExists($pdo, 'club_finance_settings')) {
        $pdo->exec('UPDATE club_finance_settings SET id=id WHERE id=1');
    }
    if ($pdo->exec('UPDATE monthly_membership_mutex SET revision=revision+1 WHERE id=1') !== 1) {
        throw new DomainException('Chybí zámek měsíčních členství.');
    }
}

function monthlyMembershipTransaction(PDO $pdo, callable $operation): mixed
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Operace členství vyžaduje vlastní transakci.');
    }
    $pdo->beginTransaction();
    try {
        monthlyMembershipLock($pdo);
        $result = $operation();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function monthlyMembershipEvent(PDO $pdo, ?int $membership, string $type, ?int $actor, string $action, array $detail): void
{
    $pdo->prepare('INSERT INTO monthly_membership_events(membership_id,actor_type,actor_id,action,detail_json) VALUES(?,?,?,?,?)')
        ->execute([$membership,$type,$actor,$action,json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
}

/** The tariff is immutable. Use another variant for a newly approved price or terms. */
function monthlyMembershipConfigure(PDO $pdo, array $input): int
{
    return monthlyMembershipTransaction($pdo, static function () use ($pdo, $input): int {
        $actor = accountingDocumentRequireFinance($pdo);
        $variantId = (int)($input['variant_id'] ?? 0);
        $teamId = (int)($input['team_id'] ?? 0);
        $day = (int)($input['due_day'] ?? 0);
        $interval = membershipInterval($input);
        $teams = membershipTeamIds((array)($input['team_ids'] ?? []));
        membershipValidateTeams($pdo, $teamId, $teams, monthlyMembershipToday());
        $terms = trim((string)($input['terms_text'] ?? ''));
        $kind = (string)($input['document_kind'] ?? '');
        $issuer = (int)($input['issuer_id'] ?? 0);
        $rule = (int)($input['rule_id'] ?? 0);
        if (($input['confirmed'] ?? '') !== '1' || $day < 1 || $day > 28 || mb_strlen($terms) < 20 || mb_strlen($terms) > 10000
    || !in_array(
        $kind,
        ['invoice','contribution_confirmation'],
        true
    )) {
            throw new InvalidArgumentException('Potvrďte měsíční podmínky, splatnost a druh dokladu.');
        }
        $q = $pdo->prepare('SELECT v.*,p.offer_type FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE v.id=?');
        $q->execute([$variantId]);
        $v = $q->fetch(PDO::FETCH_ASSOC);
        if (!$v || $v['offer_type'] !== 'program' || $v['price_mode'] !== 'fixed' || $v['currency'] !== 'CZK' || (int)$v['amount_minor'] < 1
    || $v['stock_quantity_decimal']
    !== null) {
            throw new DomainException('Měsíční tarif vyžaduje neskladovou programovou variantu s kladnou pevnou cenou v CZK.');
        }
        if (clubProgramVariantHasOfferLink($pdo, $variantId) || clubProgramProductHasOfferLink($pdo, (int)$v['product_id'])) {
            throw new DomainException('Varianta již prodává jednorázové období. Pro měsíční členství založte samostatnou variantu.');
        }
        $q = $pdo->prepare("SELECT id FROM club_teams WHERE id=? AND status='active'");
        $q->execute([$teamId]);
        if (!$q->fetchColumn()) {
            throw new DomainException('Cílová soupiska není aktivní.');
        }
        $q = $pdo->prepare('SELECT id FROM accounting_issuer_versions WHERE id=?');
        $q->execute([$issuer]);
        if (!$q->fetchColumn()) {
            throw new DomainException('Nejdříve schvalte vystavovatele dokladů.');
        }
        $q = $pdo->prepare("SELECT id FROM accounting_rules WHERE id=? AND source_type='member_charge' AND document_kind=? AND valid_from<=?");
        $q->execute([$rule,$kind,monthlyMembershipToday()]);
        if (!$q->fetchColumn()) {
            throw new DomainException('Chybí platné schválené pravidlo členského dokladu.');
        }
        $q = $pdo->prepare('SELECT id FROM accounting_document_series WHERE kind=? AND series_year=?');
        $q->execute([$kind,(int)date('Y')]);
        if (!$q->fetchColumn()) {
            throw new DomainException('Nejdříve nastavte letošní číselnou řadu.');
        }
        $pdo->prepare(('INSERT INTO ' .
    'monthly_membership_tariffs(variant_id,team_id,amount_minor,due_day,terms_text,terms_sha256,issuer_id,rule_id,document_kind,ap'
    .
    'proved_by) ' .
    'VALUES(?,?,?,?,?,?,?,?,?,?)'))
            ->execute([$variantId,$teamId,(int)$v['amount_minor'],$day,$terms,hash('sha256', $terms),$issuer,$rule
    ,$kind,$actor]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE monthly_membership_tariffs SET billing_interval=? WHERE id=?')->execute([$interval, $id]);
        foreach ($teams as $team) {
            $pdo->prepare('INSERT INTO monthly_membership_tariff_teams(tariff_id,team_id) VALUES(?,?)')->execute([$id, $team]);
        }
        monthlyMembershipEvent($pdo, null, 'trainer', $actor, 'tariff_approved', ['tariff_id' => $id,'variant_id'
    => $variantId,'terms_sha256' => hash(
        'sha256',
        $terms
    )]);
        return $id;
    });
}

function monthlyMembershipPayer(PDO $pdo, int $account, int $person): void
{
    $q = $pdo->prepare('SELECT id FROM verejni_uzivatele WHERE id=? AND aktivni=1 AND email_overeno=1');
    $q->execute([$account]);
    if (!$q->fetchColumn() || !familyPortalCanViewPerson($pdo, $account, $person)) {
        throw new DomainException('Vyberte vlastní schválený profil a ověřený aktivní účet plátce.');
    }
}

function monthlyMembershipSubscribe(
    PDO $pdo,
    int $account,
    int $person,
    int $tariffId,
    array $buyer,
    string $termsHash,
    string $requestKey,
    bool $confirmed,
    ?DateTimeImmutable $now = null,
    array $selectedTeams = []
): int {
    if (!monthlyMembershipSalesEnabled()) {
        throw new DomainException('Sjednávání měsíčních členství je nyní pozastavené.');
    }
    $today = monthlyMembershipToday($now);
    if (!$confirmed || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) {
        throw new InvalidArgumentException('Potvrďte měsíční závazek.');
    }
    $buyer = accountingDocumentParty($buyer);
    $selectedTeams = membershipTeamIds($selectedTeams);
    return monthlyMembershipTransaction($pdo, static function () use ($pdo, $account, $person, $tariffId, $buyer, $termsHash, $requestKey, $today, $selectedTeams): int {
        monthlyMembershipPayer($pdo, $account, $person);
        $key = hash('sha256', $account.':'.$requestKey);
        $q = $pdo->prepare('SELECT * FROM monthly_memberships WHERE request_key=?');
        $q->execute([$key]);
        $old = $q->fetch(PDO::FETCH_ASSOC);
        if ($old) {
            $q = $pdo->prepare('SELECT team_id FROM monthly_membership_team_grants WHERE membership_id=? ORDER BY team_id');
            $q->execute([$old['id']]);
            if (membershipTeamIds($q->fetchAll(PDO::FETCH_COLUMN)) !== $selectedTeams) {
                throw new DomainException('Tento požadavek již potvrdil jiné disciplíny.');
            }
            if ((int)$old['tariff_id'] !== $tariffId || (int)$old['sportovec_id'] !== $person || $old['buyer_json'] !== accountingDocumentJson($buyer)
    || $old['consent_sha256'] !== $termsHash) {
                throw new DomainException('Tento požadavek již potvrdil jiné členství.');
            }
            return (int)$old['id'];
        }
        $q = $pdo->prepare(('SELECT t.*,p.catalog_status AS product_status,pub.status AS publication_status,v.catalog_status AS '
    .
    'variant_status,v.visible,v.amount_minor AS variant_amount,v.currency,v.price_mode,v.stock_quantity_decimal,p.offer_type '
    . 'FROM monthly_membership_tariffs t JOIN shop_variants v '
    .
    'ON v.id=t.variant_id JOIN shop_products p ON p.id=v.product_id JOIN shop_product_publications pub ON pub.product_id=p.id '
    .
    'WHERE t.id=?'));
        $q->execute([$tariffId]);
        $t = $q->fetch(PDO::FETCH_ASSOC);
        if (!$t || $t['status'] !== 'active' || $t['product_status'] !== 'active' || $t['publication_status'] !== 'active'
    || $t['variant_status']
    !== 'active' || ($t['visible'] !== null && (int)$t['visible'] !== 1) || $t['currency'] !== 'CZK' || (int)$t['variant_amount'] !== (int)$t['amount_minor']
    || $t['price_mode'] !== 'fixed' || $t['stock_quantity_decimal'] !== null || $t['offer_type'] !== 'program'
    || !hash_equals($t['terms_sha256'], $termsHash)) {
            throw new DomainException('Tarif, cena nebo podmínky se změnily. Obnovte nabídku.');
        }
        $q = $pdo->prepare(('SELECT m.id FROM monthly_memberships m JOIN monthly_membership_tariffs t ON t.id=m.tariff_id WHERE m.sportovec_id=? AND '
    .
    't.team_id=? AND (m.ends_on IS NULL OR m.ends_on>=?)'));
        $q->execute([$person,$t['team_id'],$today]);
        if ($q->fetchColumn()) {
            throw new DomainException('Sportovec již má měsíční členství v této soupisce.');
        }
        $allowedTeams = membershipTeamIds(array_column(membershipTariffTeams($pdo, $tariffId), 'id'));
        if (($allowedTeams !== [] && $selectedTeams === []) || array_diff($selectedTeams, $allowedTeams) !== []) {
            throw new DomainException('Vyberte alespoň jednu disciplínu z nabídky tarifu.');
        }
        membershipValidateTeams($pdo, (int)$t['team_id'], $selectedTeams, $today);
        AccountingDocumentWorker::assertMandate($pdo, (int)$t['approved_by']);
        clubFinanceAssertCanRegister($pdo, $person);
        $bank = shopBankSettingsEffective($pdo);
        $vs = memberFeePlanStandingVariableSymbol($pdo, $account);
        $pdo->prepare(('INSERT INTO ' .
    'monthly_memberships(tariff_id,payer_account_id,sportovec_id,starts_on,variable_symbol,iban_snapshot,buyer_json,consent_sha256'
    .
    ',request_key) ' .
    'VALUES(?,?,?,?,?,?,?,?,?)'))
            ->execute([$tariffId,$account,$person,$today,$vs,$bank['iban'],accountingDocumentJson($buyer),$termsHash
    ,$key]);
        $id = (int)$pdo->lastInsertId();
        foreach ($selectedTeams ?: [(int)$t['team_id']] as $teamId) {
            $grant = kisRosterGrantPeriod(
                $pdo,
                $teamId,
                $person,
                (int)$t['approved_by'],
                'shop',
                $today,
                null,
                'Zařazení podle členského tarifu #'.$tariffId.', souhlas plátce #'.$account.'.',
                'monthly:'.$id.($selectedTeams === [] ? '' : ':'.$teamId)
            );
            if ($selectedTeams === []) {
                $pdo->prepare('UPDATE monthly_memberships SET roster_member_id=? WHERE id=?')->execute([$grant['id'], $id]);
            } else {
                $pdo->prepare('INSERT INTO monthly_membership_team_grants(membership_id,team_id,roster_member_id) VALUES(?,?,?)')
                    ->execute([$id, $teamId, $grant['id']]);
            }
        }
        monthlyMembershipEvent($pdo, $id, 'account', $account, 'subscribed', [
            'tariff_id' => $tariffId, 'terms_sha256' => $termsHash, 'amount_minor' => (int)$t['amount_minor'],
            'billing_interval' => membershipInterval($t), 'selected_teams' => $selectedTeams,
            'first_period' => 'full', 'termination' => 'period_end',
        ]);
        monthlyMembershipGenerateLocked($pdo, $id, $today);
        return $id;
    });
}

function monthlyMembershipCancel(PDO $pdo, int $account, int $id, bool $confirmed, ?DateTimeImmutable $now = null): string
{
    if (!$confirmed) {
        throw new DomainException('Potvrďte ukončení členství.');
    }
    $today = monthlyMembershipToday($now);
    return monthlyMembershipTransaction($pdo, static function () use ($pdo, $account, $id, $today): string {
        $q = $pdo->prepare('SELECT m.*,t.approved_by,t.billing_interval FROM monthly_memberships m JOIN monthly_membership_tariffs t ON t.id=m.tariff_id WHERE m.id=? AND m.payer_account_id=?');
        $q->execute([$id,$account]);
        $m = $q->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            throw new DomainException('Členství nebylo nalezeno.');
        }
        monthlyMembershipPayer($pdo, $account, (int)$m['sportovec_id']);
        if ($m['ends_on'] !== null) {
            return $m['ends_on'];
        }
        $end = membershipPeriodEnd($m['starts_on'], $today, membershipInterval($m));
        $pdo->prepare('UPDATE monthly_memberships SET ends_on=? WHERE id=?')->execute([$end,$id]);
        if ($m['roster_member_id'] !== null) {
            kisRosterEndPeriod($pdo, (int)$m['roster_member_id'], (int)$m['approved_by'], $end, 'Výpověď měsíčního členství plátcem #'
    .$account.'.');
        }
        $q = $pdo->prepare('SELECT roster_member_id FROM monthly_membership_team_grants WHERE membership_id=? ORDER BY team_id');
        $q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $grantId) {
            kisRosterEndPeriod($pdo, (int)$grantId, (int)$m['approved_by'], $end, 'Ukončení členství plátcem #'.$account.'.');
        }
        monthlyMembershipEvent($pdo, $id, 'account', $account, 'cancelled', ['ends_on' => $end]);
        return $end;
    });
}

/** Generates only elapsed/current months; a receipt or worker replay cannot create duplicates. */
function monthlyMembershipGenerateLocked(PDO $pdo, int $id, string $today): int
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Generování vyžaduje zámek.');
    }
    $q = $pdo->prepare('SELECT m.*,t.amount_minor,t.due_day,t.variant_id,t.billing_interval FROM monthly_memberships m JOIN monthly_membership_tariffs t ON t.id=m.tariff_id WHERE m.id=?');
    $q->execute([$id]);
    $m = $q->fetch(PDO::FETCH_ASSOC);
    if (!$m) {
        throw new DomainException('Členství neexistuje.');
    }
    monthlyMembershipPayer($pdo, (int)$m['payer_account_id'], (int)$m['sportovec_id']);
    $last = min($today, $m['ends_on'] ?? $today);
    $made = 0;
    $bank = shopBankSettingsEffective($pdo);
    if (fioNormalizeIban((string)$bank['iban']) !== fioNormalizeIban($m['iban_snapshot'])) {
        throw new DomainException('Změna bankovního účtu vyžaduje novou dohodu o trvalém příkazu.');
    }
    $step = membershipInterval($m) === 'year' ? '+12 months' : '+1 month';
    for ($month = new DateTimeImmutable(substr($m['starts_on'], 0, 7).'-01'); $month->format('Y-m') <= substr($last, 0, 7); $month = $month->modify($step)) {
        $period = $month->format('Y-m');
        $q = $pdo->prepare('SELECT id FROM monthly_membership_periods WHERE membership_id=? AND period_key=?');
        $q->execute([$id,$period]);
        if ($q->fetchColumn()) {
            continue;
        }
        $from = max($month->format('Y-m-d'), $m['starts_on']);
        $to = min($month->modify($step)->modify('-1 day')->format('Y-m-d'), $m['ends_on'] ?? '9999-12-31');
        if ($from > $to) {
            continue;
        }
        $due = $period.'-'.str_pad((string)$m['due_day'], 2, '0', STR_PAD_LEFT);
        if ($period === substr($m['starts_on'], 0, 7)) {
            $due = max($due, (new DateTimeImmutable($m['starts_on']))->modify('+7 days')->format('Y-m-d'));
        }
        $code = memberChargeAdminPublicCode();
        $external = 'monthly:'.$id.':'.$period;
        $pdo->prepare(('INSERT INTO ' .
    'club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,period_from,period_to,amount_minor,c'
    .
    'urrency,due_on,status,source_system,source_external_id,source_import_run_id) ' .
    'VALUES(?,?,?,\'membership\',?,?,?,?, \'CZK\',?,\'pending\',\'monthly_membership\',?,NULL)'))
            ->execute([$m['sportovec_id'],$m['payer_account_id'],$code,(membershipInterval($m) === 'year' ? 'Roční členství ' : 'Měsíční členství ').$period,$from,$to,$m['amount_minor']
    ,$due,$external]);
        $charge = (int)$pdo->lastInsertId();
        memberChargeAdminInsertPayment(
            $pdo,
            $charge,
            ['amount_minor' => (int)$m['amount_minor'],'currency' => 'CZK','due_on' => $due],
            $bank,
            $external,
            0,
            null,
            '',
            $m['variable_symbol']
        );
        $pdo->prepare('INSERT INTO monthly_membership_periods(membership_id,period_key,charge_id) VALUES(?,?,?)')
    ->execute([$id,$period,$charge]);
        monthlyMembershipEvent($pdo, $id, 'system', null, 'charge_created', ['charge_id' => $charge,'period' => $period
    ,'amount_minor' => (int)$m['amount_minor']]);
        $made++;
    }
    monthlyMembershipAllocateLocked($pdo, (int)$m['payer_account_id']);
    return $made;
}

function monthlyMembershipRemaining(PDO $pdo, int $chargeId): int
{
    $q = $pdo->prepare('SELECT amount_minor,status FROM club_member_charges WHERE id=?');
    $q->execute([$chargeId]);
    $c = $q->fetch(PDO::FETCH_ASSOC);
    return !$c || $c['status'] !== 'pending' ? 0 : max(0, (int)$c['amount_minor'] - clubFinanceChargeAllocatedMinor($pdo, $chargeId));
}

function monthlyMembershipCredit(PDO $pdo, int $payer): int
{
    $q = $pdo->prepare(('SELECT COALESCE(SUM(r.amount_minor-COALESCE(a.used_minor,0)),0) FROM monthly_membership_receipts r LEFT JOIN (SELECT '
    .
    'receipt_id,SUM(amount_minor) AS used_minor FROM monthly_membership_allocations GROUP BY receipt_id) a ON a.receipt_id=r.id '
    .
    'WHERE r.payer_account_id=?'));
    $q->execute([$payer]);
    return (int)$q->fetchColumn();
}

/** Oldest due date, then charge id; only the explicitly contracted monthly ledger. */
function monthlyMembershipAllocateLocked(PDO $pdo, int $payer): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Alokace vyžaduje transakci.');
    }
    $q = $pdo->prepare(('SELECT r.*,COALESCE(a.used_minor,0) AS used_minor FROM monthly_membership_receipts r LEFT JOIN (SELECT '
    .
    'receipt_id,SUM(amount_minor) AS used_minor FROM monthly_membership_allocations GROUP BY receipt_id) a ON a.receipt_id=r.id '
    .
    'WHERE r.payer_account_id=? ORDER BY r.paid_on,r.id'));
    $q->execute([$payer]);
    $receipts = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($receipts as $receipt) {
        $credit = (int)$receipt['amount_minor'] - (int)$receipt['used_minor'];
        if ($credit <= 0) {
            continue;
        }
        $q = $pdo->prepare(('SELECT c.*,p.id AS payment_id,p.iban_snapshot,p.status AS payment_status FROM club_member_charges c JOIN '
    .
    'monthly_membership_periods mp ON mp.charge_id=c.id JOIN payments p ON p.payable_type=\'member_charge\' AND p.payable_id=c.id '
    .
    'WHERE c.payer_account_id=? AND c.status=\'pending\' ORDER BY c.due_on,c.id' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE'
    : '')));
        $q->execute([$payer]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $charge) {
            if ($credit <= 0) {
                break;
            }
            if ($charge['currency'] !== 'CZK' || $charge['payment_status'] !== 'pending' || fioNormalizeIban($charge['iban_snapshot']) !== fioNormalizeIban($receipt['iban_snapshot'])) {
                continue;
            }
            $part = min($credit, monthlyMembershipRemaining($pdo, (int)$charge['id']));
            if ($part <= 0) {
                continue;
            }
            $q = $pdo->prepare('SELECT id FROM monthly_membership_allocations WHERE receipt_id=? AND charge_id=?');
            $q->execute([$receipt['id'],$charge['id']]);
            $existing = $q->fetchColumn();
            if ($existing) {
                $pdo->prepare('UPDATE monthly_membership_allocations SET amount_minor=amount_minor+? WHERE id=?')
    ->execute([$part,$existing]);
            } else {
                $pdo->prepare('INSERT INTO monthly_membership_allocations(receipt_id,charge_id,amount_minor) VALUES(?,?,?)')
    ->execute([$receipt['id'],$charge['id'],$part]);
            }
            $credit -= $part;
            if (monthlyMembershipRemaining($pdo, (int)$charge['id']) === 0) {
                $pdo->prepare(('UPDATE payments SET status=\'paid\',payment_source=\'bank_transfer\',paid_at=?,confirmation_note=?,updated_at=CURRENT_TIMESTAMP '
    .
    'WHERE id=? AND status=\'pending\''))->execute([$receipt['paid_on'].' 12:00:00','Automatická alokace trvalé platby #'
    .$receipt['id'],$charge['payment_id']]);
                $pdo->prepare("UPDATE club_member_charges SET status='paid',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'")
    ->execute([$charge['id']]);
                $pdo->prepare("UPDATE monthly_membership_messages SET status='cancelled' WHERE charge_id=? AND kind<>'issued' AND status='pending'")
    ->execute([$charge['id']]);
            }
            monthlyMembershipEvent($pdo, null, 'system', null, 'bank_allocated', ['receipt_id' => (int)$receipt['id']
    ,'charge_id' => (int)$charge['id'],'amount_minor'
    => $part]);
        }
    }
}

/** Only a unique payer VS, current bank and unambiguous contracted ledger may auto-allocate. */
function monthlyMembershipImportMovement(PDO $pdo, int $movementId, string $iban): bool
{
    return monthlyMembershipTransaction($pdo, static function () use ($pdo, $movementId, $iban): bool {
        $q = $pdo->prepare('SELECT id FROM monthly_membership_receipts WHERE movement_id=?');
        $q->execute([$movementId]);
        if ($q->fetchColumn()) {
            return false;
        }
        $q = $pdo->prepare('SELECT m.*,r.source_account_iban FROM fio_account_movements m JOIN fio_import_runs r ON r.id=m.import_run_id WHERE m.id=?'
    .($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
    === 'mysql' ? ' FOR UPDATE' : ''));
        $q->execute([$movementId]);
        $movement = $q->fetch(PDO::FETCH_ASSOC);
        if (!$movement || $movement['match_status'] === 'confirmed' || $movement['currency'] !== 'CZK' || (int)$movement['amount_minor'] <= 0
    || fioNormalizeIban($movement['source_account_iban'])
    !== fioNormalizeIban($iban)) {
            return false;
        }
        $q = $pdo->prepare('SELECT payer_account_id FROM member_payer_variable_symbols WHERE variable_symbol=?');
        $q->execute([$movement['variable_symbol']]);
        $payer = (int)$q->fetchColumn();
        if ($payer < 1) {
            return false;
        }
        $q = $pdo->prepare('SELECT MIN(starts_on) FROM monthly_memberships WHERE payer_account_id=? AND variable_symbol=?');
        $q->execute([$payer,$movement['variable_symbol']]);
        $start = $q->fetchColumn();
        if (!$start || $movement['booked_on'] < $start) {
            return false;
        }
        $q = $pdo->prepare("SELECT id FROM club_member_charges WHERE payer_account_id=? AND status='pending' AND source_system<>'monthly_membership'");
        $q->execute([$payer]);
        if ($q->fetchColumn()) {
            return false;
        } // Existing mixed family liabilities need a human decision.
        $q = $pdo->prepare('SELECT DISTINCT iban_snapshot,sportovec_id FROM monthly_memberships WHERE payer_account_id=?');
        $q->execute([$payer]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $m) {
            if (fioNormalizeIban($m['iban_snapshot']) !== fioNormalizeIban($iban)) {
                return false;
            }
            monthlyMembershipPayer($pdo, $payer, (int)$m['sportovec_id']);
        }
        clubFinanceAssertBankReferenceUnused($pdo, (string)$movement['fio_movement_id'], (int)$movement['amount_minor'], (string)$movement['booked_on']);
        $pdo->prepare('INSERT INTO monthly_membership_receipts(movement_id,reference,payer_account_id,amount_minor,paid_on,iban_snapshot) VALUES(?,?,?,?,?,?)')
    ->execute([$movementId,$movement['fio_movement_id'],$payer,$movement['amount_minor'],$movement['booked_on']
    ,$iban]);
        monthlyMembershipAllocateLocked($pdo, $payer);
        $pdo->prepare(('UPDATE fio_account_movements SET ' .
    'match_status=\'confirmed\',candidate_payment_id=NULL,candidate_order_id=NULL,match_reason=?,confirmed_at=CURRENT_TIMESTAMP,conf'
    .
    'irmed_by_trainer_id=NULL,confirmation_note=? ' .
    'WHERE id=? AND match_status<>\'confirmed\''))->execute(['Automatická trvalá platba měsíčního členství.','Rozděleno dle sjednaného pořadí; zbytek zůstává evidovaným přeplatkem.'
    ,$movementId]);
        monthlyMembershipEvent($pdo, null, 'system', null, 'bank_received', ['movement_id' => $movementId,'payer_account_id'
    => $payer,'amount_minor'
    => (int)$movement['amount_minor']]);
        return true;
    });
}

/** After SQL commit: document number/PDF retry cannot roll back the underlying charge. */
function monthlyMembershipIssueDocument(PDO $pdo, int $periodId): int
{
    $q = $pdo->prepare(('SELECT mp.*,m.buyer_json,m.payer_account_id,t.approved_by,t.issuer_id,t.rule_id,t.document_kind,c.due_on,c.period_from FROM '
    .
    'monthly_membership_periods mp JOIN monthly_memberships m ON m.id=mp.membership_id JOIN monthly_membership_tariffs t ON '
    .
    't.id=m.tariff_id JOIN club_member_charges c ON c.id=mp.charge_id WHERE mp.id=?'));
    $q->execute([$periodId]);
    $p = $q->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        throw new DomainException('Období členství nebylo nalezeno.');
    }
    return AccountingDocumentWorker::run($pdo, (int)$p['approved_by'], static function () use ($pdo, $p, $periodId): int {
        $doc = $p['document_id'] ? (int)$p['document_id'] : accountingDocumentCreateDraft($pdo, [
            'source_type' => 'member_charge','source_id' => (int)$p['charge_id'],'kind' => $p['document_kind'],
            'effective_on' => $p['period_from'],'due_on' => $p['due_on'],'buyer' => json_decode($p['buyer_json'], true, 32, JSON_THROW_ON_ERROR),
            'issuer_id' => (int)$p['issuer_id'],'request_key' => 'monthly-period:'.$periodId,
            'line_rules' => ['charge:'.$p['charge_id'] => (int)$p['rule_id']],'reason' => 'Automatický doklad dle schváleného měsíčního tarifu.'
    ,
        ]);
        // Persist the identity before rendering, including a failed first attempt.
        $pdo->prepare('UPDATE monthly_membership_periods SET document_id=? WHERE id=?')->execute([$doc,$periodId]);
        $issued = accountingDocumentIssue($pdo, $doc, 'Automatické vydání dle schválené metodiky měsíčního členství.');
        $pdo->prepare('UPDATE monthly_membership_periods SET last_error=NULL WHERE id=?')->execute([$periodId]);
        monthlyMembershipQueue($pdo, (int)$p['charge_id'], (int)$p['payer_account_id'], 'issued');
        return (int)$issued['id'];
    });
}

function monthlyMembershipQueue(PDO $pdo, int $charge, int $account, string $kind): void
{
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
    $pdo->prepare($sql.' INTO monthly_membership_messages(charge_id,account_id,kind) VALUES(?,?,?)')->execute([$charge
    ,$account,$kind]);
}

function monthlyMembershipQueueOverdue(PDO $pdo, ?DateTimeImmutable $now = null): int
{
    $today = monthlyMembershipToday($now);
    $made = 0;
    return monthlyMembershipTransaction($pdo, static function () use ($pdo, $today, &$made): int {
        $q = $pdo->query(('SELECT c.id,c.payer_account_id,c.due_on FROM club_member_charges c JOIN monthly_membership_periods p ON p.charge_id=c.id '
    .
    'WHERE c.status=\'pending\''));
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if (monthlyMembershipRemaining($pdo, (int)$c['id']) < 1) {
                continue;
            }
            $days = (int)(new DateTimeImmutable($c['due_on']))->diff(new DateTimeImmutable($today))->format('%r%a');
            // Catch-up sends only the latest relevant stage, not three messages at once.
            $stage = $days >= 14 ? 14 : ($days >= 7 ? 7 : ($days >= 3 ? 3 : 0));
            if ($stage === 0) {
                continue;
            }
            $check = $pdo->prepare('SELECT id FROM monthly_membership_messages WHERE charge_id=? AND kind=?');
            $check->execute([$c['id'],'overdue'.$stage]);
            if (!$check->fetchColumn()) {
                monthlyMembershipQueue($pdo, (int)$c['id'], (int)$c['payer_account_id'], 'overdue'.$stage);
                $made++;
            }
            $pdo->prepare("UPDATE monthly_membership_messages SET status='cancelled' WHERE charge_id=? AND kind<>'issued' AND kind<>? AND status='pending'")
    ->execute([$c['id'],'overdue'.$stage]);
        }
        return $made;
    });
}

function monthlyMembershipDocumentForAccount(PDO $pdo, int $account, int $documentId): string
{
    $q = $pdo->prepare('SELECT m.* FROM monthly_memberships m JOIN monthly_membership_periods p ON p.membership_id=m.id WHERE p.document_id=? AND m.payer_account_id=?');
    $q->execute([$documentId,$account]);
    $m = $q->fetch(PDO::FETCH_ASSOC);
    if (!$m) {
        throw new DomainException('Doklad nebyl nalezen.');
    }
    monthlyMembershipPayer($pdo, $account, (int)$m['sportovec_id']);
    $doc = accountingDocumentGet($pdo, $documentId);
    $path = privateStorageResolve((string)$doc['pdf_storage_key']);
    if ($doc['status'] !== 'issued'
        || $path === null
        || !hash_equals($doc['pdf_sha256'], (string)hash_file('sha256', $path))
        || !hash_equals($doc['snapshot_sha256'], hash('sha256', $doc['snapshot_json']))) {
        throw new DomainException('Doklad není dostupný nebo nesouhlasí jeho otisk.');
    }
    monthlyMembershipEvent($pdo, (int)$m['id'], 'account', $account, 'document_downloaded', ['document_id' => $documentId]);
    return $path;
}
