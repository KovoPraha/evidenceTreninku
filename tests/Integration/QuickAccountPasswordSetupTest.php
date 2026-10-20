<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/quick_account_password.php';

final class QuickAccountPasswordSetupTest extends TestCase
{
    public function testFreshEmailProofLeadsToUsablePasswordAndRetainsPurchaseReturn(): void
    {
        $pdo = $this->database();
        $proof = \one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION, 86400, 1_700_000_000);
        $pdo->prepare('UPDATE verejni_uzivatele SET email_overeno=0,verifikacni_token=?,verifikacni_token_expires_at=? WHERE id=1')
            ->execute([$proof['hash'], $proof['expires_at']]);
        self::assertNull(\quickAccountPasswordSetupIssue($pdo, 1, 1, 1_700_000_001));
        $verified = \one_time_email_verification_consume($pdo, $proof['token'], 1_700_000_002);
        self::assertSame(['id' => 1, 'session_version' => 1], $verified);
        $grant = \quickAccountPasswordSetupIssue($pdo, $verified['id'], $verified['session_version'], 1_700_000_003);
        self::assertNotNull($grant);
        self::assertTrue(\quickAccountPasswordSetupValid($pdo, $grant, 1, 1, 1_700_000_004));
        self::assertNotSame($grant['token'], $pdo->query('SELECT token_hash FROM password_reset_tokens')->fetchColumn());
        self::assertSame(2, \quickAccountPasswordSetupConsume($pdo, $grant, 1, 1, 'LOCALHOST nove heslo 123', 1_700_000_005));
        self::assertTrue(password_verify('LOCALHOST nove heslo 123', (string)$pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn()));
        self::assertNull(\quickAccountPasswordSetupConsume($pdo, $grant, 1, 1, 'LOCALHOST jine heslo 123', 1_700_000_006));
        self::assertNull(\one_time_email_verification_consume($pdo, $proof['token'], 1_700_000_007));
        self::assertSame('produkt.php?id=12&variant_id=34#varianta-34', \quickAccountPasswordSetupReturn('produkt.php?id=12&variant_id=34#varianta-34'));
    }

    public function testOrdinaryAndInactiveAccountsDoNotReceiveQuickGrant(): void
    {
        $pdo = $this->database();
        self::assertNull(\quickAccountPasswordSetupIssue($pdo, 2, 1));
        $pdo->exec('UPDATE verejni_uzivatele SET aktivni=0 WHERE id=1');
        self::assertNull(\quickAccountPasswordSetupIssue($pdo, 1, 1));
    }

    public function testGrantIsBoundToAccountAndCurrentSessionAndExpires(): void
    {
        $pdo = $this->database();
        $grant = \quickAccountPasswordSetupIssue($pdo, 1, 1, 1_700_000_000);
        self::assertFalse(\quickAccountPasswordSetupValid($pdo, $grant, 2, 1, 1_700_000_001));
        self::assertFalse(\quickAccountPasswordSetupValid($pdo, $grant, 1, 2, 1_700_000_001));
        self::assertFalse(\quickAccountPasswordSetupValid($pdo, $grant, 1, 1, 1_700_001_801));
        $pdo->exec('UPDATE verejni_uzivatele SET session_version=2 WHERE id=1');
        self::assertNull(\quickAccountPasswordSetupConsume($pdo, $grant, 1, 1, 'LOCALHOST nove heslo 123', 1_700_000_002));
    }

    public function testConcurrentNormalResetRevokesOnboardingGrant(): void
    {
        $pdo = $this->database();
        $grant = \quickAccountPasswordSetupIssue($pdo, 1, 1, 1_700_000_000);
        $token = '';
        \passwordResetRequest($pdo, 'parent@example.test', static function (string $email, string $value) use (&$token): bool {
            $token = $value;
            return true;
        }, 1_700_000_001);
        self::assertNotNull(\passwordResetConsume($pdo, $token, 'LOCALHOST obnovene heslo', 1_700_000_002));
        self::assertNull(\quickAccountPasswordSetupConsume($pdo, $grant, 1, 1, 'LOCALHOST jine heslo 123', 1_700_000_003));
        self::assertTrue(password_verify('LOCALHOST obnovene heslo', (string)$pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn()));
    }

    public function testWeakPasswordDoesNotConsumeTheGrant(): void
    {
        $pdo = $this->database();
        $grant = \quickAccountPasswordSetupIssue($pdo, 1, 1, 1_700_000_000);
        try {
            \quickAccountPasswordSetupConsume($pdo, $grant, 1, 1, 'short', 1_700_000_001);
            self::fail('Weak password must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertTrue(\quickAccountPasswordSetupValid($pdo, $grant, 1, 1, 1_700_000_002));
        }
    }

    public function testReturnCannotLeaveBookingPages(): void
    {
        foreach (['https://example.test', '//example.test', '../admin.php', "moje_osoby.php\r\nLocation: https://example.test"] as $unsafe) {
            self::assertSame('moje_osoby.php', \quickAccountPasswordSetupReturn($unsafe));
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,aktivni INTEGER,email_overeno INTEGER,session_version INTEGER,trener_id INTEGER NULL,verifikacni_token TEXT,verifikacni_token_expires_at TEXT)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test','unusable',1,1,1,NULL,NULL,NULL),(2,'ordinary@example.test','old',1,1,1,NULL,NULL,NULL)");
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,account_id INTEGER,checkout_mode TEXT)');
        $pdo->exec("INSERT INTO shop_orders VALUES(1,1,'quick_program')");
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804235900_password_reset_tokens.php';
        $migration['up']($pdo);
        return $pdo;
    }
}
