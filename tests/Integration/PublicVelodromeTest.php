<?php
declare(strict_types=1);

namespace Tests\Integration;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use PublicVelodromeException;

require_once dirname(__DIR__, 2) . '/includes/public_velodrome.php';

final class PublicVelodromeTest extends TestCase
{
    public function testRequiredProfileDataAndExactlyOneSelfPerson(): void
    {
        $pdo = $this->database();
        foreach ([
            ['', 'Test', '2010-01-01'],
            ['Anna', 'Test', ''],
            ['Anna', 'Test', '2030-01-01'],
        ] as [$first, $last, $birth]) {
            try {
                \publicProfileSave($pdo, 10, $first, $last, $birth, '+420 777 111 222');
                self::fail('Incomplete public profile must fail.');
            } catch (InvalidArgumentException) {
            }
        }
        try {
            \publicProfileSave($pdo, 13, 'Bez', 'Kontaktu', '2010-01-01', '');
            self::fail('A valid account contact is mandatory.');
        } catch (\PublicProfileException) {
        }
        $first = \publicProfileSave($pdo, 10, 'Anna', 'Veřejná', '2010-01-01', '+420 777 111 222');
        $second = \publicProfileSave($pdo, 10, 'Anna', 'Upravená', '2010-01-01', '+420 777 111 222');

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['sportovec_id'], $second['sportovec_id']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM public_self_profiles')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM account_person_roles WHERE account_id=10 AND relation_role='self' "
            . "AND status='approved' AND source='self_registration'"
        )->fetchColumn());
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM account_person_role_events WHERE action='self_create' "
            . "AND relation_role='self'"
        )->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM public_profile_events')->fetchColumn());
        self::assertSame('account10@example.test', $pdo->query(
            'SELECT email FROM sportovci WHERE id=' . $first['sportovec_id']
        )->fetchColumn());
    }

    public function testProfileLookupAndReservationAreAccountScoped(): void
    {
        $pdo = $this->database();
        \publicProfileSave($pdo, 10, 'Anna', 'První', '2010-01-01', '777111222');
        \publicProfileSave($pdo, 11, 'Běla', 'Druhá', '2011-01-01', '777222333');
        $slot = $this->slot($pdo, 2, false, 0);
        $reservation = \publicVelodromeReserve($pdo, $slot, 10);

        self::assertSame('Anna', \publicProfileForAccount($pdo, 10)['jmeno']);
        self::assertSame('Běla', \publicProfileForAccount($pdo, 11)['jmeno']);
        self::assertCount(1, \publicVelodromeReservationsForAccount($pdo, 10));
        self::assertCount(0, \publicVelodromeReservationsForAccount($pdo, 11));
        try {
            \publicVelodromeCancel($pdo, $reservation['id'], 11, 'Cizí storno.');
            self::fail('Another account must not cancel a reservation.');
        } catch (PublicVelodromeException) {
        }
        self::assertSame('potvrzena', $pdo->query(
            'SELECT stav FROM verejne_rezervace WHERE id=' . $reservation['id']
        )->fetchColumn());
    }

    public function testProfileCreationParticipatesInOuterRegistrationTransaction(): void
    {
        $pdo = $this->database();
        $pdo->beginTransaction();
        \publicProfileSave($pdo, 10, 'Anna', 'Rollback', '2010-01-01', '777111222');
        $pdo->rollBack();

        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM public_self_profiles')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM public_profile_events')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_role_events')->fetchColumn());
    }

    public function testCapacityExclusivityIdempotenceCancellationAndAudit(): void
    {
        $pdo = $this->database();
        \publicProfileSave($pdo, 10, 'Anna', 'První', '2010-01-01', '777111222');
        \publicProfileSave($pdo, 11, 'Běla', 'Druhá', '2011-01-01', '777222333');
        $slot = $this->slot($pdo, 5, true, 0);

        $first = \publicVelodromeReserve($pdo, $slot, 10, 'První vstup.');
        $duplicate = \publicVelodromeReserve($pdo, $slot, 10);
        self::assertTrue($first['created']);
        self::assertFalse($duplicate['created']);
        try {
            \publicVelodromeReserve($pdo, $slot, 11);
            self::fail('Exclusive slot must have effective capacity one.');
        } catch (PublicVelodromeException $exception) {
            self::assertStringContainsString('Kapacita', $exception->getMessage());
        }
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM verejne_rezervace WHERE stav='potvrzena'")->fetchColumn());
        $cancel = \publicVelodromeCancel($pdo, $first['id'], 10, 'Změna plánu.');
        self::assertTrue($cancel['changed']);
        self::assertNull($pdo->query('SELECT active_token FROM verejne_rezervace')->fetchColumn() ?: null);
        $replacement = \publicVelodromeReserve($pdo, $slot, 11);
        self::assertTrue($replacement['created']);
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM public_velodrome_reservation_events')->fetchColumn());
    }

    public function testPaidSlotCannotBypassShopCheckout(): void
    {
        $pdo = $this->database();
        \publicProfileSave($pdo, 10, 'Anna', 'První', '2010-01-01', '777111222');
        $slot = $this->slot($pdo, 2, false, 25000);
        try {
            \publicVelodromeReserve($pdo, $slot, 10);
            self::fail('Paid slot must be routed through shop checkout.');
        } catch (PublicVelodromeException $exception) {
            self::assertStringContainsString('košíku', $exception->getMessage());
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM verejne_rezervace')->fetchColumn());
    }

    public function testOverlappingReservationForSameParticipantIsRejected(): void
    {
        $pdo = $this->database();
        \publicProfileSave($pdo, 10, 'Anna', 'První', '2010-01-01', '777111222');
        $first = $this->slot($pdo, 2, false, 0);
        \publicVelodromeReserve($pdo, $first, 10);
        $year = (int)date('Y') + 1;
        $pdo->exec(
            "INSERT INTO individualni_lekce(trener_id,sportoviste_id,datum,cas_od,cas_do,slot_delka_min,typ,nazev,popis,cena_kc,max_osob,vyjimka_3_dny,stav,public_exclusive_booking) "
            . "VALUES(7,20,'$year-06-01','10:30','11:30',60,'zelena','Překryv',NULL,0,2,1,'aktivni',0)"
        );
        $overlapId = (int)$pdo->lastInsertId();
        try {
            \publicVelodromeReserve($pdo, $overlapId, 10);
            self::fail('Overlapping participant booking must fail.');
        } catch (PublicVelodromeException $exception) {
            self::assertStringContainsString('jinou aktivní rezervaci', $exception->getMessage());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM verejne_rezervace')->fetchColumn());
    }

    public function testLegacyCancellationReleasesActiveTokenAndIsAudited(): void
    {
        $pdo = $this->database();
        \publicProfileSave($pdo, 10, 'Anna', 'První', '2010-01-01', '777111222');
        $slot = $this->slot($pdo, 1, false, 0);
        \publicVelodromeReserve($pdo, $slot, 10);

        $pdo->exec("UPDATE verejne_rezervace SET stav='zrusena' WHERE lekce_id=$slot");

        self::assertNull($pdo->query('SELECT active_token FROM verejne_rezervace')->fetchColumn() ?: null);
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM public_velodrome_reservation_events "
            . "WHERE action='legacy_close' AND actor_type='legacy' AND to_status='zrusena'"
        )->fetchColumn());
        self::assertTrue(\publicVelodromeReserve($pdo, $slot, 10)['created']);
    }

    public function testCoordinatorCanEditCloseAndAuditSlotLifecycle(): void
    {
        $pdo=$this->database();
        \publicProfileSave($pdo,10,'Anna','První','2010-01-01','777111222');
        $slot=$this->slot($pdo,3,false,0);$year=(int)date('Y')+1;
        self::assertTrue(\publicVelodromeUpdateSlot($pdo,$slot,7,"$year-06-02",'12:00','13:00',4,false,0,'Posun dle provozního plánu.',true)['changed']);
        $reservation=\publicVelodromeReserve($pdo,$slot,10);
        try{\publicVelodromeUpdateSlot($pdo,$slot,7,"$year-06-03",'12:00','13:00',4,false,0,'Pokus přesunu.',true);self::fail('Booked slot must not move.');}catch(PublicVelodromeException $exception){self::assertStringContainsString('aktivní rezervací',$exception->getMessage());}
        self::assertTrue(\publicVelodromeAdminCancelReservation($pdo,$reservation['id'],7,'Provozní kolize.',true)['changed']);
        self::assertTrue(\publicVelodromeCloseSlot($pdo,$slot,7,'Termín se neuskuteční.',true)['changed']);
        self::assertSame(['create','update','close'],$pdo->query("SELECT action FROM venue_operation_events WHERE target_type='lesson' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame('admin_cancel',$pdo->query("SELECT action FROM public_velodrome_reservation_events ORDER BY id DESC LIMIT 1")->fetchColumn());
    }

    private function slot(PDO $pdo, int $capacity, bool $exclusive, int $priceMinor): int
    {
        $year = (int)date('Y') + 1;
        return \publicVelodromeCreateSlot(
            $pdo,
            7,
            "$year-06-01",
            '10:00',
            '11:00',
            $capacity,
            $exclusive,
            $priceMinor
        )['id'];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,heslo TEXT,role TEXT,aktivni INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin','admin@example.test','x','admin',1)");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,telefon TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Účet','První','account10@example.test',NULL,1,1),(11,'Účet','Druhý','account11@example.test',NULL,1,1),(12,'Neověřený','Účet','account12@example.test',NULL,1,0),(13,'Bez','Kontaktu','neplatny-email',NULL,1,1)");
        $pdo->exec("CREATE TABLE sportovci(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT NOT NULL,prijmeni TEXT NOT NULL,narozeni TEXT NOT NULL,email TEXT NOT NULL,telefon TEXT NULL,hash TEXT NOT NULL,uci INTEGER NOT NULL,stav_clenstvi TEXT NOT NULL)");
        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,kod TEXT UNIQUE,nazev TEXT,je_verejne INTEGER,aktivni INTEGER,max_kapacita INTEGER)');
        $pdo->exec("INSERT INTO sportovist VALUES(20,'velodrom','Velodrom',1,1,10)");
        $pdo->exec("CREATE TABLE individualni_lekce(id INTEGER PRIMARY KEY AUTOINCREMENT,trener_id INTEGER NOT NULL,sportoviste_id INTEGER NOT NULL,datum TEXT NOT NULL,cas_od TEXT NOT NULL,cas_do TEXT NOT NULL,slot_delka_min INTEGER NOT NULL,typ TEXT NOT NULL,nazev TEXT NOT NULL,popis TEXT,cena_kc NUMERIC NOT NULL,max_osob INTEGER NOT NULL,vyjimka_3_dny INTEGER NOT NULL,stav TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE verejne_rezervace(id INTEGER PRIMARY KEY AUTOINCREMENT,lekce_id INTEGER NOT NULL,uzivatel_id INTEGER NOT NULL,stav TEXT NOT NULL,zaplaceno INTEGER NOT NULL DEFAULT 0,poznamka_klienta TEXT,poznamka_trenera TEXT,potvrzovaci_token TEXT,cas_rezervace TEXT DEFAULT CURRENT_TIMESTAMP,cas_potvrzeni TEXT,slot_cas_od TEXT,slot_cas_do TEXT,potvrzovaci_token_expires_at TEXT)");
        $roleMigration = require dirname(__DIR__, 2) . '/migrations/20260802230000_account_person_roles.php';
        $roleMigration['up']($pdo);
        self::assertTrue($roleMigration['verify']($pdo));
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804180000_public_velodrome.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $venueEvents = require dirname(__DIR__, 2) . '/migrations/20260822150000_venue_operation_events.php';
        $venueEvents['up']($pdo);
        $venueEvents['up']($pdo);
        self::assertTrue($venueEvents['verify']($pdo));
        return $pdo;
    }
}
