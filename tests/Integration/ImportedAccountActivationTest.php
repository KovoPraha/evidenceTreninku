<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/imported_account_activation.php';
require_once dirname(__DIR__, 2) . '/includes/unified_account.php';
require_once dirname(__DIR__, 2) . '/includes/family_portal.php';

final class ImportedAccountActivationTest extends TestCase
{
    private array $keys;

    protected function setUp(): void
    {
        $this->keys = ['keys'=>['v1'=>random_bytes(32)], 'active_version'=>'v1', 'index_key'=>random_bytes(32)];
    }

    public function testPendingRelationBlocksInviteAndApprovedParentWithTwoChildrenCanActivateOnce(): void
    {
        $pdo = $this->database();
        $this->expectInviteBlocked($pdo);
        $pdo->exec("UPDATE account_person_roles SET status='approved',source='admin'");
        self::assertSame('activate', \importedAccountInvite($pdo, 1, 7, 'Kontrola zákonného zástupce a adresy',
            'https://example.test/reset.php', 'https://example.test/activate.php', $this->keys));
        $mail = $pdo->query('SELECT * FROM auth_mail_outbox')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('pending', $mail['status']);
        self::assertTrue(\authMailTokenIsCurrent($pdo, $mail));
        $body = \authMailDecrypt($mail, $this->keys);
        self::assertSame(1, preg_match('~activate\.php#token=([a-f0-9]{64})~', $body, $matches));
        self::assertStringNotContainsString($matches[1], (string)$mail['body_ciphertext']);
        self::assertTrue(\importedAccountActivationConsume($pdo, $matches[1], 'Long secure parent password 123!'));
        self::assertFalse(\importedAccountActivationConsume($pdo, $matches[1], 'Another secure parent password 123!'));
        $account = $pdo->query('SELECT * FROM verejni_uzivatele WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1, (int)$account['aktivni']);
        self::assertSame(1, (int)$account['email_overeno']);
        self::assertSame(2, (int)$account['session_version']);
        self::assertTrue(password_verify('Long secure parent password 123!', (string)$account['heslo_hash']));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles WHERE account_id=1 AND status=\'approved\'')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM public_account_activation_events')->fetchColumn());
        self::assertFalse(\authMailTokenIsCurrent($pdo, $mail));
        self::assertNotNull(\unifiedAccountAuthenticate($pdo, 'parent@example.test', 'Long secure parent password 123!'));
        self::assertSame([10,11], array_column(\familyPortalAuthorizedPeople($pdo, 1), 'sportovec_id'));
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 2));
    }

    public function testDuplicateEmailChangedAddressAndRevokedRelationBlockActivation(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE account_person_roles SET status='approved',source='admin'");
        $pdo->exec("INSERT INTO verejni_uzivatele(id,email,aktivni,email_overeno,heslo_hash,session_version) VALUES(3,'parent@example.test',0,0,'old',1)");
        $this->expectInviteBlocked($pdo);
        $pdo->exec('DELETE FROM verejni_uzivatele WHERE id=3');
        \importedAccountInvite($pdo, 1, 7, 'Kontrola zákonného zástupce a adresy',
            'https://example.test/reset.php', 'https://example.test/activate.php', $this->keys);
        $mail = $pdo->query('SELECT * FROM auth_mail_outbox')->fetch(PDO::FETCH_ASSOC);
        $body = \authMailDecrypt($mail, $this->keys);
        preg_match('~activate\.php#token=([a-f0-9]{64})~', $body, $matches);
        $pdo->exec("UPDATE verejni_uzivatele SET email='changed@example.test' WHERE id=1");
        self::assertFalse(\authMailTokenIsCurrent($pdo, $mail));
        self::assertFalse(\importedAccountActivationConsume($pdo, $matches[1], 'Long secure parent password 123!'));
        $pdo->exec("UPDATE verejni_uzivatele SET email='parent@example.test' WHERE id=1");
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=1");
        self::assertFalse(\importedAccountActivationConsume($pdo, $matches[1], 'Long secure parent password 123!'));
        self::assertSame(0, (int)$pdo->query('SELECT aktivni FROM verejni_uzivatele WHERE id=1')->fetchColumn());
    }

    public function testVerifiedExistingAccountReceivesResetAndKeepsApprovedChildren(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE account_person_roles SET status='approved',source='admin'");
        $pdo->exec('UPDATE verejni_uzivatele SET aktivni=1,email_overeno=1 WHERE id=1');
        self::assertSame('reset', \importedAccountInvite($pdo, 1, 7, 'Kontrola zákonného zástupce a adresy',
            'https://example.test/reset.php', 'https://example.test/activate.php', $this->keys));
        $mail = $pdo->query('SELECT * FROM auth_mail_outbox')->fetch(PDO::FETCH_ASSOC);
        preg_match('~reset\.php#token=([a-f0-9]{64})~', \authMailDecrypt($mail, $this->keys), $matches);
        self::assertSame('public', \passwordResetConsume($pdo, $matches[1], 'Long secure parent password 123!')['target_type']);
        self::assertSame(2, (int)$pdo->query('SELECT session_version FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles WHERE account_id=1')->fetchColumn());
    }

    public function testExpiredActivationLinkCannotEnableAccount(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE account_person_roles SET status='approved',source='admin'");
        \importedAccountInvite($pdo, 1, 7, 'Kontrola zákonného zástupce a adresy',
            'https://example.test/reset.php', 'https://example.test/activate.php', $this->keys);
        $mail = $pdo->query('SELECT * FROM auth_mail_outbox')->fetch(PDO::FETCH_ASSOC);
        preg_match('~activate\.php#token=([a-f0-9]{64})~', \authMailDecrypt($mail, $this->keys), $matches);
        self::assertFalse(\importedAccountActivationConsume($pdo, $matches[1], 'Long secure parent password 123!', time()+3601));
        self::assertSame(0, (int)$pdo->query('SELECT aktivni FROM verejni_uzivatele WHERE id=1')->fetchColumn());
    }

    public function testActivationLinkStopsWorkingAfterAccountWasActivatedElsewhere(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE account_person_roles SET status='approved',source='admin'");
        \importedAccountInvite($pdo, 1, 7, 'Kontrola zákonného zástupce a adresy',
            'https://example.test/reset.php', 'https://example.test/activate.php', $this->keys);
        $mail = $pdo->query('SELECT * FROM auth_mail_outbox')->fetch(PDO::FETCH_ASSOC);
        preg_match('~activate\\.php#token=([a-f0-9]{64})~', \authMailDecrypt($mail, $this->keys), $matches);
        $pdo->exec('UPDATE verejni_uzivatele SET aktivni=1,email_overeno=1 WHERE id=1');
        self::assertFalse(\authMailTokenIsCurrent($pdo, $mail));
        self::assertFalse(\importedAccountActivationConsume($pdo, $matches[1], 'Long secure parent password 123!'));
        self::assertSame('old', $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn());
    }

    public function testOrdinaryEmailVerificationCannotBypassImportedAccountActivation(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET aktivni=1 WHERE id=1');
        self::assertSame(['accepted'=>true,'queued'=>false], \authMailResendVerification(
            $pdo, 'parent@example.test', 'https://example.test/verify.php', $this->keys));
        $issued = \one_time_token_issue(\ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3600);
        $pdo->prepare('UPDATE verejni_uzivatele SET verifikacni_token=?,verifikacni_token_expires_at=? WHERE id=1')
            ->execute([$issued['hash'], $issued['expires_at']]);
        self::assertNull(\one_time_email_verification_consume($pdo, $issued['token']));
        self::assertSame(0, (int)$pdo->query('SELECT email_overeno FROM verejni_uzivatele WHERE id=1')->fetchColumn());
    }

    public function testOtherImportedProfileOriginUsesExistingApprovedRelation(): void
    {
        $pdo = $this->database();
        $pdo->exec('DELETE FROM kis_person_profiles');
        $pdo->exec("UPDATE sportovci SET kis_external_id=CASE id WHEN 10 THEN 'source-10' ELSE 'source-11' END");
        $pdo->exec("UPDATE account_person_roles SET status='approved',source='admin'");
        self::assertSame('activate', \importedAccountInvite($pdo, 1, 7, 'Kontrola zákonného zástupce a adresy',
            'https://example.test/reset.php', 'https://example.test/activate.php', $this->keys));
    }

    private function expectInviteBlocked(PDO $pdo): void
    {
        try {
            \importedAccountInvite($pdo, 1, 7, 'Kontrola zákonného zástupce a adresy',
                'https://example.test/reset.php', 'https://example.test/activate.php', $this->keys);
            self::fail('Unreviewed or ambiguous account was invited.');
        } catch (\ImportedAccountActivationException $exception) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'admin@example.test')");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER,heslo_hash TEXT,session_version INTEGER,trener_id INTEGER,verifikacni_token TEXT,verifikacni_token_expires_at TEXT)');
        $pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno,heslo_hash,session_version) VALUES(1,'Test','Parent','parent@example.test',0,0,'old',1),(2,'Other','Parent','other@example.test',1,1,'old',1)");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT,kis_external_id TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Child','2014-01-01','aktivni',NULL),(11,'Bara','Child','2015-01-01','aktivni',NULL)");
        $pdo->exec('CREATE TABLE kis_person_profiles(sportovec_id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO kis_person_profiles VALUES(10),(11)');
        $pdo->exec('CREATE TABLE kis_person_contacts(contact_key TEXT PRIMARY KEY,account_id INTEGER)');
        $pdo->exec("INSERT INTO kis_person_contacts VALUES('parent',1)");
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,status TEXT,relation_role TEXT,source TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,10,'pending','guardian','kis_import','2020-01-01',NULL),(2,1,11,'pending','guardian','kis_import','2020-01-01',NULL)");
        $pdo->exec('CREATE TABLE password_reset_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,target_type TEXT,target_id INTEGER,delivery_account_id INTEGER,token_hash TEXT,expires_at TEXT,consumed_at TEXT)');
        (require dirname(__DIR__,2).'/migrations/20261019100000_auth_mail_outbox.php')['up']($pdo);
        $migration = require dirname(__DIR__,2).'/migrations/20261112150000_imported_account_activation.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
