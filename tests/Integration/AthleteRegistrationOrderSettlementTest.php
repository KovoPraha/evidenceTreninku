<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_program_quick_checkout.php';
require_once dirname(__DIR__, 2) . '/includes/athlete_registration_admin.php';

final class AthleteRegistrationOrderSettlementTest extends TestCase
{
    private const BANK = ['iban'=>'CZ6508000000192000145399','bic'=>'GIBACZPX','account_label'=>'LOCALHOST NEPLATIT','due_days'=>7];

    public function testParentCancellationSettlesUnpaidOrderAndReleasesCapacityExactlyOnce(): void
    {
        [$pdo, $order, $requestId, $accountId] = $this->quickOrder();
        self::assertSame(0, \clubProgramOfferForVariant($pdo, 601)['available_count']);
        $result = \athleteRegistrationCancel($pdo, $requestId, $accountId);
        self::assertTrue($result['changed']);
        $this->assertClosed($pdo, 'cancelled', 'cancelled', 'account', $accountId);
        self::assertSame(1, \clubProgramOfferForVariant($pdo, 601)['available_count']);
        self::assertFalse(\athleteRegistrationCancel($pdo, $requestId, $accountId)['changed']);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='cancel'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM account_person_claim_events WHERE action='cancelled'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='identity_cancelled'")->fetchColumn());
        self::assertStringNotContainsString('Vrácení peněz', (string)$pdo->query("SELECT body_plain FROM club_event_notifications WHERE notification_type='identity_cancelled'")->fetchColumn());
        try {
            \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'LOCALHOST NEPLATIT pozdní platba.', true);
            self::fail('Cancelled registration order accepted a payment.');
        } catch (\ShopCheckoutException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        }
    }

    public function testRejectionOfPaidRequestQueuesManualRefundWithoutSendingIt(): void
    {
        [$pdo, $order, $requestId] = $this->quickOrder(true);
        $result = \athleteRegistrationAdminReject($pdo, $requestId, 7, 'LOCALHOST nelze ověřit podklady.');
        self::assertTrue($result['changed']);
        $this->assertClosed($pdo, 'rejected', 'refund_required', 'trainer', 7);
        self::assertNull($pdo->query('SELECT refund_sent_at FROM payments')->fetchColumn());
        self::assertNull($pdo->query('SELECT refund_reference FROM payments')->fetchColumn());
        self::assertSame(1, \clubProgramOfferForVariant($pdo, 601)['available_count']);
        self::assertFalse(\athleteRegistrationAdminReject($pdo, $requestId, 7, 'LOCALHOST opakovaný pokus.')['changed']);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='identity_rejected'")->fetchColumn());
        $body = (string)$pdo->query("SELECT body_plain FROM club_event_notifications WHERE notification_type='identity_rejected'")->fetchColumn();
        self::assertStringContainsString('Vrácení peněz musí vyřídit správce', $body);
        self::assertStringNotContainsString('nelze ověřit podklady', $body);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='cancel'")->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
    }

    public function testParentCanCancelPaidPendingRegistrationWithCorrectAuditActor(): void
    {
        [$pdo, , $requestId, $accountId] = $this->quickOrder(true);
        \athleteRegistrationCancel($pdo, $requestId, $accountId);
        $this->assertClosed($pdo, 'cancelled', 'refund_required', 'account', $accountId);
        self::assertNull($pdo->query('SELECT decided_by_trainer_id FROM account_person_claim_requests')->fetchColumn());
    }

    public function testGenericIdentityTransitionsCannotBypassSettlementOrRetention(): void
    {
        [$pdo, , $requestId, $accountId] = $this->quickOrder();
        \accountPersonClaimCancel($pdo, $requestId, $accountId);
        $this->assertClosed($pdo, 'cancelled', 'cancelled', 'account', $accountId);
        self::assertFalse(\accountPersonClaimCancel($pdo, $requestId, $accountId)['changed']);

        [$pdo, , $requestId] = $this->quickOrder(true);
        \accountPersonClaimReject($pdo, $requestId, 7, 'LOCALHOST neověřené podklady.');
        $this->assertClosed($pdo, 'rejected', 'refund_required', 'trainer', 7);
    }

    public function testForeignAccountCannotCancelAnotherParentsRequest(): void
    {
        [$pdo, , $requestId] = $this->quickOrder();
        foreach (['athleteRegistrationCancel', 'accountPersonClaimCancel'] as $function) {
            try {
                $function($pdo, $requestId, 11);
                self::fail('Foreign account cancelled the request.');
            } catch (\AthleteRegistrationException|\AccountPersonClaimException) {
                $this->assertUntouched($pdo);
            }
        }
    }

    public function testOrderAccountMismatchRollsBackRequestAndPayment(): void
    {
        [$pdo, , $requestId, $accountId] = $this->quickOrder();
        $pdo->exec('UPDATE shop_orders SET account_id=11');
        try {
            \athleteRegistrationCancel($pdo, $requestId, $accountId);
            self::fail('Mismatched order ownership was ignored.');
        } catch (\AthleteRegistrationException) {
            $this->assertUntouched($pdo);
        }
    }

    public function testMixedOrderMustBeReviewedWithoutCancellingUnrelatedPurchase(): void
    {
        [$pdo, , $requestId, $accountId] = $this->quickOrder();
        $item = $pdo->query('SELECT * FROM shop_order_items LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        unset($item['id']);
        $item['athlete_registration_request_id'] = null;
        $item['variant_id'] = 602;
        $columns = array_keys($item);
        $pdo->prepare('INSERT INTO shop_order_items(' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')')->execute(array_values($item));
        try {
            \athleteRegistrationCancel($pdo, $requestId, $accountId);
            self::fail('Mixed order was partially or wholly cancelled.');
        } catch (\AthleteRegistrationException) {
            $this->assertUntouched($pdo);
            self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_order_items')->fetchColumn());
        }
        $orderId = (int)$pdo->query('SELECT id FROM shop_orders')->fetchColumn();
        \shopOrderAdminCancel($pdo, $orderId, 7, 'LOCALHOST správce vypořádal celou objednávku.', true);
        self::assertTrue(\athleteRegistrationCancel($pdo, $requestId, $accountId)['changed']);
        self::assertSame('cancelled', $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='cancel'")->fetchColumn());
    }

    public function testAuditFailureRollsBackBothOrderSettlementAndRequestDecision(): void
    {
        [$pdo, , $requestId] = $this->quickOrder(true);
        $pdo->exec("CREATE TRIGGER fail_decision BEFORE INSERT ON account_person_claim_events WHEN NEW.action='rejected' BEGIN SELECT RAISE(ABORT,'test audit failure'); END");
        try {
            \athleteRegistrationAdminReject($pdo, $requestId, 7, 'LOCALHOST záměrný test rollbacku.');
            self::fail('Failed audit did not abort the decision.');
        } catch (\AthleteRegistrationAdminException) {
            $this->assertUntouched($pdo, 'paid', 'processing');
            self::assertNull($pdo->query('SELECT cancelled_at FROM shop_orders')->fetchColumn());
        }
    }

    public function testAlreadyCancelledOrderDoesNotPreventRejectingRequestOrDuplicateOrderAudit(): void
    {
        [$pdo, $order, $requestId] = $this->quickOrder();
        \shopOrderAdminCancel($pdo, (int)$order['id'], 7, 'LOCALHOST předchozí storno.', true);
        \athleteRegistrationAdminReject($pdo, $requestId, 7, 'LOCALHOST uzavření přihlášky.');
        $this->assertClosed($pdo, 'rejected', 'cancelled', 'trainer', 7);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='cancel'")->fetchColumn());
    }

    public function testNotificationFailureRollsBackPaidRejectionAndRefundFollowup(): void
    {
        [$pdo, , $requestId] = $this->quickOrder(true);
        $pdo->exec("CREATE TRIGGER fail_decision_mail BEFORE INSERT ON club_event_notifications WHEN NEW.notification_type='identity_rejected' BEGIN SELECT RAISE(ABORT,'LOCALHOST queue unavailable'); END");
        try {
            \athleteRegistrationAdminReject($pdo, $requestId, 7, 'LOCALHOST rollback test.');
            self::fail('Paid rejection committed without the decision message.');
        } catch (\AthleteRegistrationAdminException) {
            $this->assertUntouched($pdo, 'paid', 'processing');
            self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='identity_rejected'")->fetchColumn());
        }
    }

    private function assertClosed(PDO $pdo, string $requestStatus, string $paymentStatus, string $actorType, int $actorId): void
    {
        self::assertSame($requestStatus, $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        self::assertSame('cancelled', $pdo->query('SELECT status FROM shop_orders')->fetchColumn());
        self::assertSame($paymentStatus, $pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertSame($paymentStatus, $pdo->query('SELECT status FROM payments')->fetchColumn());
        $audit = $pdo->query("SELECT actor_type,actor_id,note FROM shop_order_events WHERE action='cancel'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame($actorType, $audit['actor_type']);
        self::assertSame($actorId, (int)$audit['actor_id']);
        self::assertSame('retention_pending', $pdo->query('SELECT status FROM athlete_private_files')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    private function assertUntouched(PDO $pdo, string $paymentStatus = 'pending', string $orderStatus = 'placed'): void
    {
        self::assertSame('pending', $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        self::assertSame($orderStatus, $pdo->query('SELECT status FROM shop_orders')->fetchColumn());
        self::assertSame($paymentStatus, $pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertSame($paymentStatus, $pdo->query('SELECT status FROM payments')->fetchColumn());
        self::assertSame('active', $pdo->query('SELECT status FROM athlete_private_files')->fetchColumn());
        self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='cancel'")->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    private function quickOrder(bool $paid = false): array
    {
        $pdo = $this->database();
        $this->offer($pdo, 601, 501, 10, 1, 'LOCALHOST-NEPLATIT');
        $versions = [];
        foreach (\athleteRegistrationCurrentTerms($pdo) as $purpose => $term) $versions[$purpose] = $term['version'];
        $input = [
            'parent_first_name'=>'LOCALHOST','parent_last_name'=>'Rodič','email'=>'local-neplatit@example.test','contact_phone'=>'+420 777 123 456',
            'requested_role'=>'guardian','jmeno'=>'LOCALHOST','prijmeni'=>'NEPLATIT','narozeni'=>'2012-05-06','citizenship_country_code'=>'SK',
            'address_street'=>'Testovací','address_house_number'=>'12','address_orientation_number'=>'3','address_city'=>'Praha','address_postcode'=>'19000',
            'has_czech_birth_number'=>'0','birth_number'=>'','member_data_notice'=>'1','birth_number_legal_notice'=>'1','photo_internal'=>'0','photo_public'=>'0',
            'program_terms_accepted'=>'1','program_term_version'=>['program_cancellation'=>'v1','program_consent'=>'v1'],'message'=>'',
        ];
        $order = \shopProgramQuickCheckoutPlace($pdo, 601, $input, $versions, bin2hex(random_bytes(16)), bin2hex(random_bytes(32)), self::BANK);
        $requestId = (int)$pdo->query('SELECT id FROM account_person_claim_requests')->fetchColumn();
        $accountId = (int)$pdo->query('SELECT account_id FROM shop_orders')->fetchColumn();
        // Database-only synthetic photo metadata proves retention is part of the same transaction.
        $pdo->prepare("INSERT INTO athlete_private_files(request_id,file_kind,status,storage_key,mime_type,byte_size,sha256,width_px,height_px,consent_snapshot_id) VALUES (?,'profile_photo','active','LOCALHOST-NEPLATIT','image/jpeg',1,?,1,1,1)")->execute([$requestId, str_repeat('a', 32)]);
        if ($paid) \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'LOCALHOST NEPLATIT syntetická úhrada.', true);
        return [$pdo, $order, $requestId, $accountId];
    }

    private function offer(PDO $pdo,int $variantId,int $productId,int $teamId,int $capacity,string $code,?int $programId=null,string $from='2026-09-01',string $to='2027-01-31',?int $birthYearFrom=null,?int $birthYearTo=null):int
    {
        $programId??=(int)\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola')['id'];
        \clubProgramTermsConfigure($pdo,7,'program',$programId,'program_cancellation','Syntetické schválené storno podmínky pro integrační test.',true);
        \clubProgramTermsConfigure($pdo,7,'program',$programId,'program_consent','Syntetický schválený souhlas s účastí pro integrační test.',true);
        return(int)\clubProgramCreateOffer($pdo,7,$programId,1,$teamId,$productId,$variantId,$code,'Období '.$code,$from,$to,null,null,$capacity,'active',$birthYearFrom,$birthYearTo)['id'];
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,prijmeni TEXT,email TEXT,heslo_hash TEXT DEFAULT \'\',telefon TEXT NULL,verifikacni_token TEXT NULL,verifikacni_token_expires_at TEXT NULL,aktivni INTEGER,email_overeno INTEGER)');$pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno) VALUES(10,'Rodič','A','a@test.test',1,1),(11,'Rodič','B','b@test.test',1,1)");
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT NULL)');$pdo->exec("INSERT INTO sportovci VALUES(101,'Dítě','Jedna','2017-04-03'),(103,'Dítě','Cizí','2012-05-06'),(104,'Bez','Data',NULL)");
        $roles=require dirname(__DIR__,2).'/migrations/20260802230000_account_person_roles.php';$roles['up']($pdo);$pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,created_by_trainer_id,approved_by_trainer_id,decision_note) VALUES(10,101,'guardian','approved','admin','2020-01-01',7,7,'test'),(11,103,'guardian','approved','admin','2020-01-01',7,7,'test'),(10,104,'guardian','approved','admin','2020-01-01',7,7,'test')");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT)');$pdo->exec("INSERT INTO shop_products VALUES(501,'Kroužek A','program','active'),(502,'Kroužek B','program','active')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_variants VALUES(601,501,'A','{}','fixed',10000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP),(602,502,'B','{}','fixed',12000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');$pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Kroužek A','A'),(502,'active','Kroužek B','B')");
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT NOT NULL,scope_key TEXT NOT NULL,consent_purpose TEXT NOT NULL,event_id INTEGER NULL,terms_version TEXT NOT NULL,consent_text_plain TEXT NOT NULL,cancellation_policy_plain TEXT NULL,cancellation_deadline_at TEXT NULL,actor_trainer_id INTEGER NULL,actor_type TEXT NOT NULL,actor_id INTEGER NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))');
        foreach(['20260802233000_account_person_claim_requests.php','20260816143000_athlete_registration_foundation.php','20260816180000_registration_terms_scope.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $pdo->exec("UPDATE club_event_term_versions SET consent_text_plain='Syntetické schválené informace pro integrační test.' WHERE scope_type='athlete_registration' AND consent_purpose IN ('member_data_notice','birth_number_legal_notice')");
        foreach(['20260803230000_shop_checkout.php','20260804010000_shop_order_fulfillment.php','20260804030000_shop_order_refunds.php','20260804050000_shop_coupons.php','20260804120000_shop_item_beneficiaries.php','20260804090000_kis_teams_rosters.php','20260809090000_stripe_checkout.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(1,'SCHOOL','Školní rok','2026-09-01','2027-08-31','active',7)");$pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(10,1,'A','Kroužek A','vše','děti','active',7)");
        foreach(['20260804140000_club_programs.php','20260804160000_club_program_lifecycle.php','20260804235000_club_program_repeat_enrollment.php','20260817090000_club_program_events.php','20260817130000_club_program_offer_age.php','20260817150000_club_program_terms.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $expiration=require dirname(__DIR__,2).'/migrations/20260804210000_shop_order_expiration.php';$expiration['up']($pdo);self::assertTrue($expiration['verify']($pdo));
        foreach(['20260922140000_low_friction_storefront.php','20260922150000_quick_program_checkout.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        \Tests\Support\ParentJourneyNotificationFixture::install($pdo);
        return$pdo;
    }
}
