<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/athlete_registration.php';

final class AthleteRegistrationTest extends TestCase
{
    private string $storageRoot;
    private string|false $previousStorageRoot;

    protected function setUp(): void
    {
        $this->previousStorageRoot = getenv('APP_PRIVATE_STORAGE_ROOT');
        $this->storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'athlete-registration-' . bin2hex(random_bytes(6));
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->storageRoot);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storageRoot)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->storageRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->storageRoot);
        }
        $this->previousStorageRoot === false
            ? putenv('APP_PRIVATE_STORAGE_ROOT')
            : putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->previousStorageRoot);
    }

    public function testCzechRequestIsAtomicIdempotentAndCancellationSchedulesRetention(): void
    {
        $pdo = $this->database();
        $terms = \athleteRegistrationCurrentTerms($pdo);
        $versions = $this->versions($terms);
        $birthDate = '2012-03-04';
        $input = $this->validInput($birthDate, $this->syntheticBirthNumber($birthDate));

        $first = \athleteRegistrationSubmit($pdo, 1, $input, $versions, null, $this->sensitiveConfig());
        $second = \athleteRegistrationSubmit($pdo, 1, $input, $versions, null, $this->sensitiveConfig());

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['id'], $second['id']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_requests')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM athlete_registration_request_details')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_udaje')->fetchColumn());
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM athlete_registration_consent_snapshots')->fetchColumn());
        self::assertSame(
            ['birth_number_legal_notice', 'member_data_notice', 'photo_public'],
            $pdo->query('SELECT purpose FROM athlete_registration_consent_snapshots ORDER BY purpose')->fetchAll(PDO::FETCH_COLUMN)
        );

        $cancelled = \athleteRegistrationCancel($pdo, $first['id'], 1);
        self::assertTrue($cancelled['changed']);
        self::assertSame('cancelled', $pdo->query('SELECT status FROM account_person_claim_requests')->fetchColumn());
        self::assertSame('retention_pending', $pdo->query('SELECT status FROM osoba_citlive_udaje')->fetchColumn());
        self::assertNotEmpty($pdo->query('SELECT retention_until FROM osoba_citlive_udaje')->fetchColumn());
        self::assertSame(
            ['submit', 'cancelled'],
            $pdo->query('SELECT action FROM account_person_claim_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testForeignerWithoutAssignedNumberStoresNoSubstituteAndOptionalPhotoIsPrivate(): void
    {
        $pdo = $this->database();
        $terms = \athleteRegistrationCurrentTerms($pdo);
        $input = $this->validInput('2014-05-06', '');
        $input['has_czech_birth_number'] = false;
        $input['citizenship_country_code'] = 'SK';
        $input['photo_internal'] = true;
        $input['photo_public'] = false;
        $photo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'athlete-registration-source-' . bin2hex(random_bytes(6)) . '.png';
        file_put_contents($photo, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));

        $result = \athleteRegistrationSubmit(
            $pdo,
            1,
            $input,
            $this->versions($terms),
            ['tmp_name' => $photo, 'error' => UPLOAD_ERR_OK],
            null,
            false
        );

        self::assertTrue($result['created']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_udaje')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT has_czech_birth_number FROM athlete_registration_request_details')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM athlete_private_files')->fetchColumn());
        self::assertSame('profile_photo', $pdo->query('SELECT file_kind FROM athlete_private_files')->fetchColumn());
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM athlete_registration_consent_snapshots')->fetchColumn());
        self::assertSame(
            0,
            (int)$pdo->query("SELECT accepted FROM athlete_registration_consent_snapshots WHERE purpose='photo_public'")->fetchColumn()
        );
        $key = (string)$pdo->query('SELECT storage_key FROM athlete_private_files')->fetchColumn();
        self::assertNotNull(\privateStorageResolve($key));
        self::assertFileDoesNotExist($photo);
    }

    public function testRoleAccountAndTermsGatesFailBeforeAnyPartialWrite(): void
    {
        $pdo = $this->database();
        $terms = \athleteRegistrationCurrentTerms($pdo);
        $versions = $this->versions($terms);
        $adultDate = '1990-01-02';
        $adult = $this->validInput($adultDate, $this->syntheticBirthNumber($adultDate));

        foreach ([
            [1, $adult, $versions],
            [2, $this->validInput('2012-03-04', $this->syntheticBirthNumber('2012-03-04')), $versions],
            [1, $this->validInput('2012-03-04', $this->syntheticBirthNumber('2012-03-04')), array_replace($versions, ['member_data_notice' => 'stale'])],
        ] as [$accountId, $input, $submittedVersions]) {
            try {
                \athleteRegistrationSubmit($pdo, $accountId, $input, $submittedVersions, null, $this->sensitiveConfig());
                self::fail('A closed registration gate accepted the request.');
            } catch (\InvalidArgumentException | \AthleteRegistrationException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_requests')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_udaje')->fetchColumn());
    }

    /** @param array<string,array{id:int,version:string,text:string}> $terms @return array<string,string> */
    private function versions(array $terms): array
    {
        $versions = [];
        foreach ($terms as $purpose => $term) $versions[$purpose] = $term['version'];
        return $versions;
    }

    /** @return array<string,mixed> */
    private function validInput(string $birthDate, string $birthNumber): array
    {
        return [
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
            'birth_number' => $birthNumber,
            'member_data_notice' => true,
            'birth_number_legal_notice' => true,
            'photo_internal' => false,
            'photo_public' => false,
            'message' => '',
        ];
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
            CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT NOT NULL,aktivni INTEGER NOT NULL,email_overeno INTEGER NOT NULL);
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT);
            CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT);
            CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY);
            CREATE TABLE club_events(id INTEGER PRIMARY KEY);
            CREATE TABLE club_event_registrations(id INTEGER PRIMARY KEY);
            INSERT INTO verejni_uzivatele VALUES(1,'verified@example.test',1,1);
            INSERT INTO verejni_uzivatele VALUES(2,'unverified@example.test',1,0);
            SQL);
        foreach ([
            '20260802233000_account_person_claim_requests.php',
            '20260803150000_club_event_terms.php',
            '20260816143000_athlete_registration_foundation.php',
            '20260816180000_registration_terms_scope.php',
        ] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo), $file);
        }
        self::assertSame(1, (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn());
        return $pdo;
    }
}
