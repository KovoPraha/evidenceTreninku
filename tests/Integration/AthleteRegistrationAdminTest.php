<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/athlete_registration_admin.php';

final class AthleteRegistrationAdminTest extends TestCase
{
    private array $sessionBackup;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = ['trener_id' => 7, 'role' => 'admin'];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testReviewAndExistingPersonApprovalAreAuditedAndAtomic(): void
    {
        $pdo = $this->database();
        $birthDate = '2012-03-04';
        $request = $this->submit($pdo, $birthDate);
        $pdo->prepare(
            "INSERT INTO sportovci(jmeno,prijmeni,narozeni,email,hash,uci,stav_clenstvi,stav_manualni,kis_external_id) "
            . "VALUES ('Testovací','Sportovec',?,'','candidate-a',0,'cekajici',1,NULL)"
        )->execute([$birthDate]);
        $personId = (int)$pdo->lastInsertId();

        $review = \athleteRegistrationAdminReview($pdo, $request['id'], $this->sensitiveConfig(), '127.0.0.1');
        self::assertSame('exact', $review['match']['level']);
        self::assertSame([$personId], array_column($review['match']['candidates'], 'id'));
        self::assertMatchesRegularExpression('/^\d{6}\/\*{4}$/D', (string)$review['birth_number_masked']);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM osoba_citlive_pristupy WHERE action='masked_view'")->fetchColumn());

        $approved = \athleteRegistrationAdminApproveExisting(
            $pdo,
            $request['id'],
            $personId,
            7,
            'Ověřeno podle přiložených registračních podkladů.'
        );

        self::assertSame($personId, $approved['person_id']);
        self::assertSame('approved', $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        self::assertSame($personId, (int)$pdo->query('SELECT sportovec_id FROM osoba_citlive_udaje')->fetchColumn());
        self::assertSame('active', $pdo->query('SELECT status FROM osoba_citlive_udaje')->fetchColumn());
        self::assertSame('Zkušební', $pdo->query('SELECT adresa_ulice FROM sportovci WHERE id=' . $personId)->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM ucto_audit_log WHERE akce='person_match_v1_athlete_registration_link'")->fetchColumn());
    }

    public function testExactMatchBlocksCreateUntilAuditedOverrideAndF7CreatesManualPerson(): void
    {
        $pdo = $this->database();
        $birthDate = '2012-03-04';
        $request = $this->submit($pdo, $birthDate);
        $pdo->prepare(
            "INSERT INTO sportovci(jmeno,prijmeni,narozeni,email,hash,uci,stav_clenstvi,stav_manualni,kis_external_id) "
            . "VALUES ('Testovací','Sportovec',?,'','candidate-b',0,'aktivni',0,'KIS-EXISTING')"
        )->execute([$birthDate]);
        $existingId = (int)$pdo->lastInsertId();

        try {
            \athleteRegistrationAdminCreatePerson(
                $pdo,
                $request['id'],
                7,
                'Ověřeno podle přiložených registračních podkladů.',
                '',
                ''
            );
            self::fail('Exact match allowed an unconfirmed create.');
        } catch (\AthleteRegistrationAdminException) {
            self::addToAssertionCount(1);
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());

        $created = \athleteRegistrationAdminCreatePerson(
            $pdo,
            $request['id'],
            7,
            'Ověřeno podle přiložených registračních podkladů.',
            'exact_override',
            'Jde o jinou osobu se shodným jménem a datem.'
        );

        self::assertNotSame($existingId, $created['person_id']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        self::assertNull($pdo->query('SELECT kis_external_id FROM sportovci WHERE id=' . $created['person_id'])->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM ucto_audit_log WHERE akce='person_match_v1_athlete_registration_override_create' AND detail LIKE '%candidate_ids%'")->fetchColumn());
        self::assertSame('approved', $pdo->query('SELECT status FROM account_person_claim_requests WHERE id=' . $request['id'])->fetchColumn());
    }

    public function testDifferentProtectedNumberBlocksLinkAndRejectSchedulesRetentionWithoutPartialWrites(): void
    {
        $pdo = $this->database();
        $birthDate = '2012-03-04';
        $request = $this->submit($pdo, $birthDate);
        $pdo->prepare(
            "INSERT INTO sportovci(jmeno,prijmeni,narozeni,email,hash,uci,stav_clenstvi,stav_manualni,kis_external_id) "
            . "VALUES ('Testovací','Sportovec',?,'','candidate-c',0,'aktivni',0,NULL)"
        )->execute([$birthDate]);
        $personId = (int)$pdo->lastInsertId();
        $otherClaim = \accountPersonClaimSubmit($pdo, 1, 'guardian', 'Jiná', 'Osoba', '2015-01-02', 'Podklad konfliktu.');
        $pdo->prepare(
            'INSERT INTO osoba_citlive_udaje(record_token,request_id,sportovec_id,rc_ciphertext,rc_nonce,rc_key_version,'
            . "rc_blind_index,contract_version,status) VALUES (?,?,?,randomblob(32),randomblob(24),'v1',?,'person-sensitive-v1','active')"
        )->execute(['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $otherClaim['id'], $personId, str_repeat("\x55", 32)]);

        try {
            \athleteRegistrationAdminApproveExisting(
                $pdo,
                $request['id'],
                $personId,
                7,
                'Ověřeno podle přiložených registračních podkladů.'
            );
            self::fail('A conflicting protected record was overwritten.');
        } catch (\AthleteRegistrationAdminException) {
            self::addToAssertionCount(1);
        }
        self::assertSame('pending', $pdo->query('SELECT status FROM account_person_claim_requests WHERE id=' . $request['id'])->fetchColumn());
        self::assertSame('', (string)$pdo->query('SELECT adresa_ulice FROM sportovci WHERE id=' . $personId)->fetchColumn());
        self::assertNull($pdo->query('SELECT sportovec_id FROM osoba_citlive_udaje WHERE request_id=' . $request['id'])->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());

        \athleteRegistrationAdminReject($pdo, $request['id'], 7, 'Podklady nebylo možné spolehlivě ověřit.');
        self::assertSame('rejected', $pdo->query('SELECT status FROM account_person_claim_requests WHERE id=' . $request['id'])->fetchColumn());
        self::assertSame('retention_pending', $pdo->query('SELECT status FROM osoba_citlive_udaje WHERE request_id=' . $request['id'])->fetchColumn());
        self::assertNotEmpty($pdo->query('SELECT retention_until FROM osoba_citlive_udaje WHERE request_id=' . $request['id'])->fetchColumn());
    }

    public function testApprovedRegistrationCanBeAssignedOnceAndInvalidSelectionsRollBack(): void
    {
        $pdo = $this->database();
        $request = $this->submit($pdo, '2012-03-04');
        $approved = \athleteRegistrationAdminCreatePerson(
            $pdo,
            $request['id'],
            7,
            'Ověřeno podle přiložených registračních podkladů.',
            '',
            ''
        );
        $personId = $approved['person_id'];

        $pdo->exec("INSERT INTO skupiny(id,nazev,poradi) VALUES(10,'Mládež',1),(20,'Dospělí',2)");
        $pdo->exec("INSERT INTO podskupiny(id,skupina_id,nazev,poradi) VALUES(11,10,'U15',1)");
        $today = new DateTimeImmutable('today');
        $startsOn = $today->modify('-1 month')->format('Y-m-d');
        $endsOn = $today->modify('+10 months')->format('Y-m-d');
        $pdo->prepare(
            "INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('CURRENT','Aktuální',?,?,'active',7)"
        )->execute([$startsOn, $endsOn]);
        $seasonId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(?,'U15','U15 silnice','silnice','U15','active',7)"
        )->execute([$seasonId]);
        $teamId = (int)$pdo->lastInsertId();

        try {
            \athleteRegistrationAdminAssign($pdo, $request['id'], 20, 11, $teamId, 7, 'Kontrola chybné podskupiny.');
            self::fail('A subgroup from another group was accepted.');
        } catch (\AthleteRegistrationAdminException) {
            self::addToAssertionCount(1);
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_skupina')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_podskupina')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());

        $first = \athleteRegistrationAdminAssign($pdo, $request['id'], 10, 11, $teamId, 7, 'Zařazení podle schválené registrace.');
        $second = \athleteRegistrationAdminAssign($pdo, $request['id'], 10, 11, $teamId, 7, 'Opakované ověření stejného zařazení.');
        self::assertTrue($first['changed']);
        self::assertFalse($second['changed']);
        self::assertSame($personId, $first['person_id']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_skupina')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_podskupina')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame('active:manual', $pdo->query("SELECT status || ':' || source FROM club_roster_members")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_roster_events WHERE action='athlete_registration_assign'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM ucto_audit_log WHERE akce='athlete_registration_assign'")->fetchColumn());

        $pdo->exec("INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('INACTIVE','Neaktivní','2020-01-01','2099-12-31','draft',7)");
        $inactiveSeasonId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(?,'BAD','Neaktivní tým','silnice','U15','active',7)"
        )->execute([$inactiveSeasonId]);
        try {
            \athleteRegistrationAdminAssign($pdo, $request['id'], 20, 11, (int)$pdo->lastInsertId(), 7, 'Kontrola neaktivní sezony.');
            self::fail('An inactive season was accepted.');
        } catch (\AthleteRegistrationAdminException) {
            self::addToAssertionCount(1);
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_skupina')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_podskupina')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
    }

    public function testMemberChargeRequiresAllReadinessRulesAndIsIdempotent(): void
    {
        $pdo = $this->database();
        $request = $this->submit($pdo, '2012-03-04');
        $today = new DateTimeImmutable('today');
        $startsOn = $today->modify('-1 month')->format('Y-m-d');
        $endsOn = $today->modify('+10 months')->format('Y-m-d');
        $dueOn = $today->modify('+14 days')->format('Y-m-d');
        $pdo->prepare(
            "INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('CHARGE','Aktuální',?,?,'active',7)"
        )->execute([$startsOn, $endsOn]);
        $seasonId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(?,'CHARGE','Členský tým','silnice','U15','active',7)"
        )->execute([$seasonId]);
        $teamId = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno) VALUES(2,'Cizí','Plátce','foreign@localhost.test',1,1)");

        $create = function (array $overrides = []) use ($pdo, $request, $seasonId, $startsOn, $endsOn, $dueOn): array {
            $input = array_replace([
                'payer' => 1,
                'title' => 'LOCALHOST členský příspěvek',
                'period_from' => $startsOn,
                'period_to' => $endsOn,
                'due_on' => $dueOn,
                'amount' => '250000',
                'currency' => 'CZK',
                'reason' => 'Ruční vystavení po ověření zařazení.',
                'confirmed' => true,
            ], $overrides);
            return \athleteRegistrationAdminCreateCharge(
                $pdo,
                $request['id'],
                $seasonId,
                $input['payer'],
                $input['title'],
                $input['period_from'],
                $input['period_to'],
                $input['due_on'],
                $input['amount'],
                $input['currency'],
                7,
                $input['reason'],
                $input['confirmed']
            );
        };

        $this->assertChargeRejected($pdo, $create, \AthleteRegistrationAdminException::class);
        $approved = \athleteRegistrationAdminCreatePerson(
            $pdo,
            $request['id'],
            7,
            'Ověřeno podle přiložených registračních podkladů.',
            '',
            ''
        );
        $personId = $approved['person_id'];

        $this->assertChargeRejected($pdo, $create, \AthleteRegistrationAdminException::class);
        $pdo->exec("INSERT INTO skupiny(id,nazev,poradi) VALUES(10,'Mládež',1)");
        $pdo->prepare('INSERT INTO sportovec_skupina(sportovec_id,skupina_id) VALUES(?,10)')->execute([$personId]);
        $this->assertChargeRejected($pdo, $create, \AthleteRegistrationAdminException::class);
        $pdo->exec("INSERT INTO podskupiny(id,skupina_id,nazev,poradi) VALUES(11,10,'U15',1)");
        $pdo->prepare('INSERT INTO sportovec_podskupina(sportovec_id,podskupina_id) VALUES(?,11)')->execute([$personId]);
        $this->assertChargeRejected($pdo, $create, \AthleteRegistrationAdminException::class);
        $pdo->prepare(
            "INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES(?,?,'active','manual',?,NULL,7)"
        )->execute([$teamId, $personId, $startsOn]);

        $context = \athleteRegistrationAdminChargeContext($pdo, $request['id'], $seasonId);
        self::assertTrue($context['ready']);
        self::assertSame([true, true, true, true], array_values($context['readiness']));
        self::assertSame([1], array_map('intval', array_column($context['payers'], 'account_id')));

        $this->assertChargeRejected($pdo, fn (): array => $create(['payer' => 2]), \AthleteRegistrationAdminException::class);
        $this->assertChargeRejected($pdo, fn (): array => $create(['amount' => '-1']), \InvalidArgumentException::class);
        $this->assertChargeRejected($pdo, fn (): array => $create(['amount' => '12.5']), \InvalidArgumentException::class);
        $this->assertChargeRejected($pdo, fn (): array => $create(['currency' => 'CZ1']), \InvalidArgumentException::class);
        $this->assertChargeRejected($pdo, fn (): array => $create(['confirmed' => false]), \InvalidArgumentException::class);

        $first = $create();
        $second = $create();
        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['charge_id'], $second['charge_id']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame('membership:membership:pending', $pdo->query("SELECT charge_type || ':' || source_system || ':' || status FROM club_member_charges")->fetchColumn());
        self::assertSame(250000, (int)$pdo->query('SELECT amount_minor FROM club_member_charges')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_member_charge_events WHERE action='athlete_registration_create' AND actor_type='trainer' AND actor_id=7")->fetchColumn());

        $this->assertChargeRejected(
            $pdo,
            fn (): array => $create(['title' => 'LOCALHOST jiný předpis']),
            \AthleteRegistrationAdminException::class,
            1
        );
    }

    /** @param callable():array $operation */
    private function assertChargeRejected(PDO $pdo, callable $operation, string $exceptionClass, int $expectedCount = 0): void
    {
        try {
            $operation();
            self::fail('Invalid member charge input was accepted.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
        }
        self::assertSame($expectedCount, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame($expectedCount, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charge_events')->fetchColumn());
    }

    /** @return array{id:int,status:string,created:bool} */
    private function submit(PDO $pdo, string $birthDate): array
    {
        $terms = \athleteRegistrationCurrentTerms($pdo);
        $versions = [];
        foreach ($terms as $purpose => $term) $versions[$purpose] = $term['version'];
        return \athleteRegistrationSubmit($pdo, 1, [
            'requested_role' => 'guardian',
            'jmeno' => 'Testovací',
            'prijmeni' => 'Sportovec',
            'narozeni' => $birthDate,
            'contact_phone' => '+420 777 000 111',
            'citizenship_country_code' => 'CZ',
            'address_street' => 'Zkušební',
            'address_house_number' => '12',
            'address_orientation_number' => '3',
            'address_city' => 'Praha',
            'address_postcode' => '19000',
            'has_czech_birth_number' => true,
            'birth_number' => $this->syntheticBirthNumber($birthDate),
            'member_data_notice' => true,
            'birth_number_legal_notice' => true,
            'photo_internal' => false,
            'photo_public' => false,
            'message' => '',
        ], $versions, null, $this->sensitiveConfig());
    }

    /** @return array{keys:array<string,string>,active_version:string,index_key:string} */
    private function sensitiveConfig(): array
    {
        return ['keys' => ['v1' => str_repeat("\x11", 32)], 'active_version' => 'v1', 'index_key' => str_repeat("\x22", 32)];
    }

    private function syntheticBirthNumber(string $birthDate): string
    {
        $date = new DateTimeImmutable($birthDate);
        $prefix = $date->format('ymd');
        for ($suffix = 0; $suffix <= 9999; $suffix++) {
            $candidate = $prefix . str_pad((string)$suffix, 4, '0', STR_PAD_LEFT);
            $remainder = 0;
            foreach (str_split($candidate) as $digit) $remainder = (($remainder * 10) + (int)$digit) % 11;
            if ($remainder === 0) return $candidate;
        }
        self::fail('Cannot create synthetic birth number.');
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec(<<<'SQL'
            CREATE TABLE verejni_uzivatele(
                id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT NOT NULL,
                aktivni INTEGER NOT NULL,email_overeno INTEGER NOT NULL
            );
            CREATE TABLE sportovci(
                id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,
                telefon TEXT,adresa_ulice TEXT DEFAULT '',adresa_cp TEXT,adresa_co TEXT,adresa_obec TEXT,
                adresa_psc TEXT,hash TEXT UNIQUE,uci INTEGER,stav_clenstvi TEXT,stav_manualni INTEGER DEFAULT 0,
                kis_external_id TEXT NULL
            );
            CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT);
            CREATE TABLE club_events(id INTEGER PRIMARY KEY);
            CREATE TABLE club_event_registrations(id INTEGER PRIMARY KEY);
            CREATE TABLE ucto_audit_log(
                id INTEGER PRIMARY KEY AUTOINCREMENT,uzivatel_id INTEGER,akce TEXT,tabulka TEXT,
                zaznam_id INTEGER NULL,detail TEXT,ip_adresa TEXT,user_agent TEXT
            );
            CREATE TABLE skupiny(id INTEGER PRIMARY KEY,nazev TEXT NOT NULL,poradi INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE podskupiny(id INTEGER PRIMARY KEY,skupina_id INTEGER NOT NULL,nazev TEXT NOT NULL,poradi INTEGER NOT NULL DEFAULT 0,FOREIGN KEY(skupina_id) REFERENCES skupiny(id));
            CREATE TABLE sportovec_skupina(sportovec_id INTEGER NOT NULL,skupina_id INTEGER NOT NULL,PRIMARY KEY(sportovec_id,skupina_id),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id),FOREIGN KEY(skupina_id) REFERENCES skupiny(id));
            CREATE TABLE sportovec_podskupina(sportovec_id INTEGER NOT NULL,podskupina_id INTEGER NOT NULL,PRIMARY KEY(sportovec_id,podskupina_id),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id),FOREIGN KEY(podskupina_id) REFERENCES podskupiny(id));
            CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY);
            CREATE TABLE kis_import_rows(id INTEGER PRIMARY KEY);
            INSERT INTO verejni_uzivatele VALUES(1,'Rodič','Testovací','verified@example.test',1,1);
            INSERT INTO treneri VALUES(7,'Admin');
            SQL);
        foreach ([
            '20260802230000_account_person_roles.php',
            '20260802233000_account_person_claim_requests.php',
            '20260803150000_club_event_terms.php',
            '20260804090000_kis_teams_rosters.php',
            '20260804234950_member_charge_target.php',
            '20260816143000_athlete_registration_foundation.php',
            '20260816180000_registration_terms_scope.php',
        ] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo), $file);
        }
        return $pdo;
    }
}
