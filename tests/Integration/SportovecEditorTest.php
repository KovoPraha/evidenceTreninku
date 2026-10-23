<?php
declare(strict_types=1);
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sportovec_editor.php';

final class SportovecEditorTest extends TestCase
{
    protected function tearDown(): void { $_SESSION = []; }

    public function testSavePersistsAddressesAndContactsWithoutChangingRelationsOrBirthNumber(): void
    {
        $pdo = $this->database();
        sportovecEditorSave($pdo, 1, ['profile_revision' => $this->revision($pdo, 1), 'jmeno' => ' Eva ', 'prijmeni' => 'Test', 'narozeni' => '',
            'email' => 'a@example.test; b@example.test', 'telefon' => '+420 123', 'adresa_ulice' => 'Nová',
            'adresa_cp' => '5', 'adresa_co' => '7a', 'adresa_obec' => 'Praha', 'adresa_psc' => '100 00',
            'country' => 'Česká republika', 'contact_address' => 'Doručovací adresa',
            'rc' => 'DO_NOT_WRITE', 'stav_clenstvi' => 'archiv'], 7);
        $person = sportovecEditorPerson($pdo, 1);
        self::assertSame('Eva', $person['jmeno']);
        self::assertSame('Nová', $person['adresa_ulice']);
        self::assertSame('7a', $person['adresa_co']);
        self::assertSame('+420 123', $person['telefon']);
        self::assertSame('a@example.test; b@example.test', $person['email']);
        self::assertNull($person['narozeni']);
        self::assertSame('KEEP_CATEGORY', $person['category']);
        self::assertSame('KEEP_RC', $person['rc']);
        self::assertSame('aktivni', $person['stav_clenstvi']);
        self::assertSame('Doručovací adresa', $pdo->query('SELECT contact_address FROM kis_person_profiles')->fetchColumn());
        self::assertSame('pending', $pdo->query('SELECT status FROM account_person_roles')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
        self::assertStringNotContainsString('KEEP_RC', $pdo->query('SELECT old_json FROM sportovec_history')->fetchColumn());
    }

    public function testProfileFailureRollsBackPersonalDataToo(): void
    {
        $pdo = $this->database();
        try {
            sportovecEditorSave($pdo, 1, ['profile_revision' => $this->revision($pdo, 1), 'jmeno' => 'Changed', 'country' => str_repeat('x', 101)], 7);
            self::fail('Invalid profile accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame('LOCALHOST', sportovecEditorPerson($pdo, 1)['jmeno']);
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
            self::assertFalse($pdo->inTransaction());
        }
    }

    public function testInvalidDateAndMissingPersonDoNotReportSuccess(): void
    {
        $pdo = $this->database();
        foreach ([[1, ['narozeni' => '2026-02-30']], [999, ['jmeno' => 'Changed']], [1, ['jmeno' => '']]] as [$id, $input]) {
            try { sportovecEditorSave($pdo, $id, $input + ['profile_revision' => $this->revision($pdo, 1)], 7); self::fail('Invalid save accepted'); }
            catch (\InvalidArgumentException) { self::assertFalse($pdo->inTransaction()); }
        }
        self::assertSame('LOCALHOST', sportovecEditorPerson($pdo, 1)['jmeno']);
    }

    public function testContextShowsPendingImportedParentsAndIndependentApprovedRoles(): void
    {
        $pdo = $this->database();
        $context = sportovecEditorContext($pdo, 1);
        self::assertCount(1, $context['parents']);
        self::assertSame('matka', $context['parents'][0]['relationship_label']);
        self::assertSame('pending', $context['parents'][0]['relation_status']);
        self::assertSame('parent@example.test', sportovecEditorContactValues($context['parents'][0]['emails_json']));
        self::assertCount(1, $context['roles']);
        self::assertSame([], sportovecEditorContext($pdo, 2)['parents']);
    }

    public function testBirthDateCannotBeChangedToConflictWithProtectedBirthNumber(): void
    {
        $pdo = $this->database();
        $keys = ['keys' => ['test' => str_repeat('a', 32)], 'active_version' => 'test', 'index_key' => str_repeat('b', 32)];
        $saved = [];
        foreach (['PERSON_RC_KEYRING_JSON', 'PERSON_RC_ACTIVE_KEY_VERSION', 'PERSON_RC_INDEX_KEY'] as $name) $saved[$name] = getenv($name);
        putenv('PERSON_RC_KEYRING_JSON=' . json_encode(['test' => base64_encode(str_repeat('a', 32))]));
        putenv('PERSON_RC_ACTIVE_KEY_VERSION=test');
        putenv('PERSON_RC_INDEX_KEY=' . base64_encode(str_repeat('b', 32)));
        $_SESSION = ['trener_id' => 7, 'role' => 'admin'];
        $encrypted = personSensitiveEncrypt('8501010001', str_repeat('a', 32), 1, $keys);
        $pdo->exec('ALTER TABLE osoba_citlive_udaje ADD COLUMN record_token TEXT');
        $pdo->exec('ALTER TABLE osoba_citlive_udaje ADD COLUMN request_id INTEGER');
        foreach (['rc_ciphertext','rc_nonce','rc_key_version'] as $column) $pdo->exec('ALTER TABLE osoba_citlive_udaje ADD COLUMN ' . $column . ' BLOB');
        $pdo->prepare("INSERT INTO osoba_citlive_udaje VALUES(1,1,'approved',?,1,?,?,?)")->execute([
            str_repeat('a', 32), $encrypted['ciphertext'], $encrypted['nonce'], $encrypted['key_version'],
        ]);
        try {
            sportovecEditorSave($pdo, 1, ['profile_revision' => $this->revision($pdo, 1), 'narozeni' => '1985-01-01'], 7);
            sportovecEditorSave($pdo, 1, ['profile_revision' => $this->revision($pdo, 1), 'narozeni' => '1986-01-01', 'telefon' => 'Changed'], 7);
            self::fail('Conflicting birth date accepted');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('neodpovídá datu narození', $exception->getMessage());
            self::assertSame('1985-01-01', sportovecEditorPerson($pdo, 1)['narozeni']);
            self::assertNull(sportovecEditorPerson($pdo, 1)['telefon']);
        } finally {
            foreach ($saved as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function testRevealScopesArchiveToAllAliasesOfSelectedPersonAndAuditsWithoutValues(): void
    {
        $pdo = $this->database();
        $keys = $this->archive($pdo);
        $_SESSION = ['trener_id' => 7, 'role' => 'admin'];
        $result = sportovecEditorReveal($pdo, 1, 'Kontrola evidence člena', $keys);
        self::assertSame(['one', 'two'], array_column($result['source_rows'], 'id_hash'));
        self::assertSame('RC_SENTINEL', $result['source_rows'][0]['rodnecislo']);
        self::assertArrayNotHasKey('heslo', $result['source_rows'][0]);
        self::assertArrayNotHasKey('password', $result['source_rows'][0]['nested']);
        self::assertSame(1, (int)$pdo->query('SELECT sportovec_id FROM osoba_citlive_pristupy')->fetchColumn());
        self::assertSame('reveal', $pdo->query('SELECT action FROM osoba_citlive_pristupy')->fetchColumn());
        self::assertStringNotContainsString('RC_SENTINEL', json_encode($pdo->query('SELECT * FROM osoba_citlive_pristupy')->fetchAll()));
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
    }

    public function testRevealCannotSucceedWithoutAuditOrAdmin(): void
    {
        $pdo = $this->database();
        $keys = $this->archive($pdo);
        $_SESSION = ['trener_id' => 7, 'role' => 'trener'];
        try { sportovecEditorReveal($pdo, 1, 'Kontrola evidence člena', $keys); self::fail('Non-admin reveal'); }
        catch (\PersonSensitiveException) { self::assertFalse($pdo->inTransaction()); }
        $_SESSION['role'] = 'admin';
        $pdo->exec('DROP TABLE osoba_citlive_pristupy');
        try { sportovecEditorReveal($pdo, 1, 'Kontrola evidence člena', $keys); self::fail('Unaudited reveal'); }
        catch (\PDOException) { self::assertFalse($pdo->inTransaction()); }
    }

    public function testStaleProfileCannotOverwriteAnotherRegistrarsContactChange(): void
    {
        $pdo = $this->database();
        $revision = $this->revision($pdo, 1);
        sportovecEditorSave($pdo, 1, ['profile_revision' => $revision, 'telefon' => 'LOCALHOST first'], 7);
        try {
            sportovecEditorSave($pdo, 1, ['profile_revision' => $revision, 'telefon' => 'LOCALHOST stale', 'jmeno' => 'Changed'], 7);
            self::fail('Stale form overwrote a newer contact');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Nic nebylo přepsáno', $exception->getMessage());
            self::assertSame('LOCALHOST first', sportovecEditorPerson($pdo, 1)['telefon']);
            self::assertSame('LOCALHOST', sportovecEditorPerson($pdo, 1)['jmeno']);
            self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
            self::assertFalse($pdo->inTransaction());
        }
        sportovecEditorSave($pdo, 1, ['profile_revision' => $this->revision($pdo, 1), 'telefon' => 'LOCALHOST fresh'], 7);
        self::assertSame('LOCALHOST fresh', sportovecEditorPerson($pdo, 1)['telefon']);
    }

    public function testRevisionAlsoProtectsImportedContactProfileAndCannotBeOmitted(): void
    {
        $pdo = $this->database();
        $revision = $this->revision($pdo, 1);
        $pdo->exec("UPDATE kis_person_profiles SET contact_address='LOCALHOST new address' WHERE sportovec_id=1");
        foreach ([$revision, '', null, ['invalid']] as $stale) {
            try {
                sportovecEditorSave($pdo, 1, ['profile_revision' => $stale, 'contact_address' => 'Old', 'telefon' => 'Changed'], 7);
                self::fail('Missing or stale revision accepted');
            } catch (\InvalidArgumentException) {
                self::assertSame('LOCALHOST new address', $pdo->query('SELECT contact_address FROM kis_person_profiles')->fetchColumn());
                self::assertNull(sportovecEditorPerson($pdo, 1)['telefon']);
                self::assertFalse($pdo->inTransaction());
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
    }

    public function testValidationFailureCanBeCorrectedUsingSameUnchangedRevision(): void
    {
        $pdo = $this->database();
        $revision = $this->revision($pdo, 1);
        try { sportovecEditorSave($pdo, 1, ['profile_revision' => $revision, 'jmeno' => ''], 7); self::fail('Empty name accepted'); }
        catch (\InvalidArgumentException) { self::assertSame($revision, $this->revision($pdo, 1)); }
        sportovecEditorSave($pdo, 1, ['profile_revision' => $revision, 'jmeno' => 'LOCALHOST corrected'], 7);
        self::assertSame('LOCALHOST corrected', sportovecEditorPerson($pdo, 1)['jmeno']);
    }

    private function revision(\PDO $pdo, int $id): string
    {
        $profile = $pdo->prepare('SELECT country,contact_address FROM kis_person_profiles WHERE sportovec_id=?');
        $profile->execute([$id]);
        return sportovecEditorRevision(sportovecEditorPerson($pdo, $id), $profile->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    private function archive(\PDO $pdo): array
    {
        $keys = ['keys' => ['test' => str_repeat('a', 32)], 'active_version' => 'test', 'index_key' => str_repeat('b', 32)];
        $plain = json_encode(['source_archive' => ['people' => [
            ['id_hash' => 'one', 'rodnecislo' => 'RC_SENTINEL', 'heslo' => 'SECRET', 'nested' => ['password' => 'SECRET', 'info' => 'visible']], ['id_hash' => 'two', 'poznamka' => 'Druhý zdroj'],
            ['id_hash' => 'other', 'rodnecislo' => 'FOREIGN_RC'],
        ]]], JSON_THROW_ON_ERROR);
        $encrypted = kisPersonEncryptArchive($plain, hash('sha256', $plain), $keys);
        $pdo->prepare('INSERT INTO kis_person_batches VALUES(?,?,?)')->execute([hash('sha256', $plain), $encrypted['ciphertext'], 'test']);
        $pdo->prepare('UPDATE kis_person_profiles SET batch_fingerprint=?')->execute([hash('sha256', $plain)]);
        return $keys;
    }

    private function database(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $columns = implode(',', array_map(static fn($field) => $field . ' TEXT', array_keys(sportovecEditorFields())));
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,' . $columns . ',rc TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,category,rc,stav_clenstvi) VALUES(1,'LOCALHOST','Test','KEEP_CATEGORY','KEEP_RC','aktivni'),(2,'Other','Person','','','aktivni')");
        $pdo->exec('CREATE TABLE kis_person_profiles(sportovec_id INTEGER,canonical_key TEXT,batch_fingerprint TEXT,country TEXT,contact_address TEXT)');
        $pdo->exec("INSERT INTO kis_person_profiles VALUES(1,'O0001','','CZ','Old')");
        $pdo->exec('CREATE TABLE kis_person_batches(fingerprint TEXT,payload_ciphertext TEXT,key_version TEXT)');
        $pdo->exec('CREATE TABLE kis_person_aliases(sportovec_id INTEGER,source_key TEXT)');
        $pdo->exec("INSERT INTO kis_person_aliases VALUES(1,'one'),(1,'two'),(2,'other')");
        $pdo->exec('CREATE TABLE kis_person_links(source_key TEXT,sportovec_id INTEGER,contact_key TEXT,relationship_label TEXT,source_slot INTEGER)');
        $pdo->exec("INSERT INTO kis_person_links VALUES('one:ZZ1',1,'parent','matka',1)");
        $pdo->exec('CREATE TABLE kis_person_contacts(contact_key TEXT,display_name TEXT,names_json TEXT,emails_json TEXT,phones_json TEXT,account_id INTEGER,account_state TEXT)');
        $pdo->exec("INSERT INTO kis_person_contacts VALUES('parent','LOCALHOST Rodič','[]','[\"parent@example.test\"]','[]',10,'disabled_reset_required')");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'LOCALHOST','Rodič','parent@example.test',0,0)");
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,10,1,'guardian','pending',NULL)");
        $pdo->exec('CREATE TABLE osoba_citlive_udaje(id INTEGER,sportovec_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE osoba_citlive_pristupy(sensitive_record_id INTEGER,private_file_id INTEGER,sportovec_id INTEGER,request_id INTEGER,actor_trainer_id INTEGER,action TEXT,reason TEXT,ip_address TEXT)');
        $pdo->exec('CREATE TABLE sportovec_history(sportovec_id INTEGER,created_by INTEGER,source TEXT,event_type TEXT,title TEXT,detail TEXT,old_json TEXT,new_json TEXT,ref_table TEXT,ref_id INTEGER)');
        $pdo->exec('CREATE TABLE ucto_audit_log(uzivatel_id INTEGER,akce TEXT,tabulka TEXT,zaznam_id INTEGER,detail TEXT,ip_adresa TEXT,user_agent TEXT)');
        return $pdo;
    }
}
