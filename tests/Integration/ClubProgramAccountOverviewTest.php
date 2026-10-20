<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/club_program.php';

final class ClubProgramAccountOverviewTest extends TestCase
{
    public function testPaidQuickRegistrationIsVisibleBeforeIdentityAndThenBecomesRosterEntry(): void
    {
        $pdo = $this->database();
        $rows = \clubProgramPendingOrderItems($pdo, 10);
        self::assertCount(1, $rows);
        self::assertSame('LOCALHOST dítě', $rows[0]['jmeno']);
        self::assertSame('identity_pending', \clubProgramAccountItemState($rows[0])['code']);
        self::assertFalse(\clubProgramAccountItemState($rows[0])['can_activate']);
        $pdo->exec("UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=10");
        self::assertSame('email_pending', \clubProgramAccountItemState(\clubProgramPendingOrderItems($pdo, 10)[0])['code']);
        $pdo->exec("UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=10");
        $pdo->exec("UPDATE account_person_claim_requests SET status='approved' WHERE id=50");
        $pdo->exec("UPDATE shop_order_items SET beneficiary_sportovec_id=100 WHERE id=1");
        $pdo->exec("INSERT INTO account_person_roles VALUES(10,100,'guardian','approved','2020-01-01',NULL)");
        self::assertTrue(\clubProgramAccountItemState(\clubProgramPendingOrderItems($pdo, 10)[0])['can_activate']);
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,100,10,1,'active','2026-09-01','2027-01-31')");
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,100,'active','shop','2026-09-01',NULL)");
        $row = \clubProgramPendingOrderItems($pdo, 10)[0];
        self::assertSame('active', \clubProgramAccountItemState($row)['code']);
        self::assertFalse(\clubProgramAccountItemState($row)['can_activate']);
        $history = \clubProgramEnrollmentsForAccount($pdo, 10);
        self::assertCount(1, $history);
        self::assertSame('LOCALHOST soupiska', $history[0]['team_name']);
        self::assertSame('2026-09-01', $history[0]['roster_valid_from']);
        self::assertNull($history[0]['roster_valid_to']);
        self::assertSame('Aktivní účast', \clubProgramAccountEnrollmentLabel($history[0], '2026-10-20'));
        $pdo->exec("UPDATE club_program_enrollments SET status='cancelled'");
        $pdo->exec("UPDATE club_roster_members SET status='removed',valid_to='2026-10-20'");
        self::assertSame('enrollment_cancelled', \clubProgramAccountItemState(\clubProgramPendingOrderItems($pdo, 10)[0])['code']);
        self::assertSame('Účast ukončena', \clubProgramAccountEnrollmentLabel(\clubProgramEnrollmentsForAccount($pdo, 10)[0]));
    }

    public function testOrdersAndPendingIdentityNamesNeverCrossAccountBoundary(): void
    {
        $pdo = $this->database();
        self::assertSame([], \clubProgramPendingOrderItems($pdo, 11));
        $pdo->exec("UPDATE shop_order_items SET athlete_registration_request_id=51");
        self::assertSame([], \clubProgramPendingOrderItems($pdo, 10));
        $pdo->exec("UPDATE shop_order_items SET athlete_registration_request_id=50,beneficiary_sportovec_id=101");
        self::assertSame([], \clubProgramPendingOrderItems($pdo, 10), 'Own claimed name does not authorize access to a matched foreign athlete.');
        $pdo->exec("INSERT INTO account_person_roles VALUES(10,101,'guardian','revoked','2020-01-01',NULL)");
        self::assertSame([], \clubProgramPendingOrderItems($pdo, 10));
        $pdo->exec("UPDATE account_person_roles SET status='approved',valid_to='2020-01-02'");
        self::assertSame([], \clubProgramPendingOrderItems($pdo, 10));
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,101,11,1,'active','2026-09-01','2027-01-31')");
        self::assertSame([], \clubProgramEnrollmentsForAccount($pdo, 10));
    }

    public function testCancelledQuickRegistrationRemainsVisibleWithHonestRefundState(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE account_person_claim_requests SET status='rejected' WHERE id=50");
        $pdo->exec("UPDATE shop_orders SET status='cancelled',payment_status='refund_required'");
        $rows = \clubProgramPendingOrderItems($pdo, 10);
        self::assertCount(1, $rows);
        self::assertSame('refund_required', \clubProgramAccountItemState($rows[0])['code']);
        self::assertFalse(\clubProgramAccountItemState($rows[0])['can_activate']);
        $pdo->exec("UPDATE shop_orders SET payment_status='refunded'");
        self::assertSame('refunded', \clubProgramAccountItemState(\clubProgramPendingOrderItems($pdo, 10)[0])['code']);
    }

    public function testActivationActionIsSuppressedForEveryKnownBlockingState(): void
    {
        $ready = [
            'payment_status'=>'paid', 'order_status'=>'processing', 'request_status'=>'approved',
            'enrollment_id'=>null, 'account_active'=>1, 'email_overeno'=>1, 'beneficiary_sportovec_id'=>100,
            'line_amount_minor'=>10000, 'quantity'=>1, 'offer_status'=>'active', 'terms_valid'=>true,
        ];
        self::assertTrue(\clubProgramAccountItemState($ready)['can_activate']);
        foreach ([
            ['payment_status'=>'pending','order_status'=>'placed'],
            ['payment_status'=>'refund_required'], ['payment_status'=>'refunded'],
            ['order_status'=>'cancelled'], ['payment_status'=>'cancelled'],
            ['request_status'=>'rejected'], ['request_status'=>'cancelled'],
            ['account_active'=>0], ['email_overeno'=>0], ['beneficiary_sportovec_id'=>null],
            ['offer_status'=>'closed'], ['quantity'=>2], ['line_amount_minor'=>-1],
            ['terms_valid'=>false], ['duplicate_enrollment'=>1],
            ['roster_status'=>'removed','roster_source'=>'manual'],
            ['order_status'=>'placed'],
            ['enrollment_id'=>1,'enrollment_status'=>'cancelled'],
            ['enrollment_id'=>1,'enrollment_status'=>'active','roster_status'=>'removed'],
            ['enrollment_id'=>1,'enrollment_status'=>'active','roster_status'=>'active'],
        ] as $overrides) {
            self::assertFalse(\clubProgramAccountItemState(array_replace($ready, $overrides))['can_activate'], json_encode($overrides));
        }
        self::assertTrue(\clubProgramAccountItemState(array_replace($ready, ['line_amount_minor'=>0,'payment_status'=>'pending','order_status'=>'placed']))['can_activate']);
        self::assertSame('payment_pending', \clubProgramAccountItemState(array_replace($ready, ['payment_status'=>'pending']))['code']);
        self::assertSame('Období skončilo', \clubProgramAccountEnrollmentLabel(['status'=>'active','valid_from'=>'2025-01-01','valid_to'=>'2025-02-01','roster_status'=>'active'], '2026-10-20'));
        self::assertSame('Aktivováno pro budoucí období', \clubProgramAccountEnrollmentLabel(['status'=>'active','valid_from'=>'2027-01-01','valid_to'=>'2027-02-01','roster_status'=>'active'], '2026-10-20'));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)',
            'shop_orders(id INTEGER PRIMARY KEY,account_id INTEGER,public_code TEXT,payment_status TEXT,status TEXT,created_at TEXT)',
            'shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,product_id INTEGER,variant_id INTEGER,beneficiary_sportovec_id INTEGER,athlete_registration_request_id INTEGER,quantity INTEGER,product_name_snapshot TEXT,line_amount_minor INTEGER,currency TEXT)',
            'club_programs(id INTEGER PRIMARY KEY,name TEXT)',
            'club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER,product_id INTEGER,variant_id INTEGER,team_id INTEGER,name TEXT,code TEXT,status TEXT,starts_on TEXT,ends_on TEXT)',
            'club_teams(id INTEGER PRIMARY KEY,name TEXT)',
            'sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)',
            'account_person_roles(account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)',
            'account_person_claim_requests(id INTEGER PRIMARY KEY,account_id INTEGER,status TEXT,claimed_jmeno TEXT,claimed_prijmeni TEXT)',
            'club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,sportovec_id INTEGER,account_id INTEGER,source_order_item_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)',
            'club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT)',
        ] as $table) $pdo->exec('CREATE TABLE '.$table);
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,1,1),(11,1,1)");
        $pdo->exec("INSERT INTO shop_orders VALUES(1,10,'LOCALHOST-NEPLATIT','paid','processing','2026-10-20')");
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,1,1,1,NULL,50,1,'LOCALHOST kroužek',10000,'CZK')");
        $pdo->exec("INSERT INTO club_programs VALUES(1,'LOCALHOST program')");
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,1,1,1,1,'Období','LOCALHOST','active','2026-09-01','2027-01-31')");
        $pdo->exec("INSERT INTO club_teams VALUES(1,'LOCALHOST soupiska')");
        $pdo->exec("INSERT INTO sportovci VALUES(100,'LOCALHOST dítě','NEPLATIT'),(101,'Cizí dítě','Soukromé')");
        $pdo->exec("INSERT INTO account_person_claim_requests VALUES(50,10,'pending','LOCALHOST dítě','NEPLATIT'),(51,11,'pending','Cizí žádost','Soukromé')");
        return $pdo;
    }
}
