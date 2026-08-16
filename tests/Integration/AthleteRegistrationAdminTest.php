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
            INSERT INTO verejni_uzivatele VALUES(1,'Rodič','Testovací','verified@example.test',1,1);
            INSERT INTO treneri VALUES(7,'Admin');
            SQL);
        foreach ([
            '20260802230000_account_person_roles.php',
            '20260802233000_account_person_claim_requests.php',
            '20260803150000_club_event_terms.php',
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
