<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/password_reset.php';

final class PasswordResetTest extends TestCase
{
    public function testPublicAndChildResetAreSingleUseAndRevokeSessions(): void
    {
        $pdo = $this->database();
        $delivered = [];
        $deliver = static function (string $email, string $token) use (&$delivered): bool {
            $delivered[$email] = $token;
            return true;
        };

        $public = \passwordResetRequest($pdo, 'parent@example.test', $deliver, 1_700_000_000);
        self::assertTrue($public['issued']);
        self::assertSame('public', \passwordResetConsume(
            $pdo,
            $delivered['parent@example.test'],
            'NoveBezpecneHeslo123!',
            1_700_000_100
        )['target_type']);
        self::assertNull(\passwordResetConsume($pdo, $delivered['parent@example.test'], 'JineBezpecneHeslo123!', 1_700_000_101));
        self::assertSame(2, (int)$pdo->query('SELECT session_version FROM verejni_uzivatele WHERE id=1')->fetchColumn());

        $child = \passwordResetRequest($pdo, 'anna.u15', $deliver, 1_700_000_200);
        self::assertTrue($child['issued']);
        self::assertSame('child', \passwordResetConsume(
            $pdo,
            $delivered['parent@example.test'],
            'DetskeNoveHeslo123!',
            1_700_000_300
        )['target_type']);
        self::assertSame(2, (int)$pdo->query('SELECT session_version FROM child_access_accounts')->fetchColumn());
        self::assertSame('password_reset', $pdo->query('SELECT action FROM child_access_events')->fetchColumn());
    }

    public function testUnknownIdentifierAndRevokedGuardianDoNotResetAnything(): void
    {
        $pdo = $this->database();
        $tokens = [];
        $deliver = static function (string $email, string $token) use (&$tokens): bool {
            $tokens[] = $token;
            return true;
        };
        $unknown = \passwordResetRequest($pdo, 'nobody@example.test', $deliver, 1_700_000_000);
        self::assertTrue($unknown['accepted']);
        self::assertFalse($unknown['issued']);
        self::assertSame([], $tokens);

        \passwordResetRequest($pdo, 'anna.u15', $deliver, 1_700_000_000);
        $pdo->exec("UPDATE account_person_roles SET status='revoked',valid_to=CURRENT_TIMESTAMP WHERE id=1");
        self::assertNull(\passwordResetConsume($pdo, $tokens[0], 'DetskeNoveHeslo123!', 1_700_000_100));
        self::assertSame(1, (int)$pdo->query('SELECT session_version FROM child_access_accounts')->fetchColumn());
    }

    public function testExpiredTokenAndNewRequestInvalidateOlderToken(): void
    {
        $pdo = $this->database();
        $tokens = [];
        $deliver = static function (string $email, string $token) use (&$tokens): bool {
            $tokens[] = $token;
            return true;
        };
        \passwordResetRequest($pdo, 'parent@example.test', $deliver, 1_700_000_000);
        \passwordResetRequest($pdo, 'parent@example.test', $deliver, 1_700_000_010);
        self::assertNull(\passwordResetConsume($pdo, $tokens[0], 'NoveBezpecneHeslo123!', 1_700_000_020));
        self::assertNotNull(\passwordResetConsume($pdo, $tokens[1], 'NoveBezpecneHeslo123!', 1_700_000_020));

        \passwordResetRequest($pdo, 'parent@example.test', $deliver, 1_700_100_000);
        self::assertNull(\passwordResetConsume($pdo, $tokens[2], 'JineBezpecneHeslo123!', 1_700_103_601));
    }

    public function testOneParentCanResetTwoChildrenWithoutCrossInvalidation(): void
    {
        $pdo = $this->database();
        $tokens = [];
        $deliver = static function (string $email, string $token) use (&$tokens): bool {
            $tokens[] = [$email, $token];
            return true;
        };

        self::assertTrue(\passwordResetRequest($pdo, 'anna.u15', $deliver, 1_700_000_000)['issued']);
        self::assertTrue(\passwordResetRequest($pdo, 'bara.u13', $deliver, 1_700_000_001)['issued']);
        self::assertSame(['parent@example.test', 'parent@example.test'], array_column($tokens, 0));
        self::assertSame(20, \passwordResetConsume($pdo, $tokens[0][1], 'AnnaNoveHeslo123!', 1_700_000_100)['target_id']);
        self::assertSame(21, \passwordResetConsume($pdo, $tokens[1][1], 'BaraNoveHeslo123!', 1_700_000_101)['target_id']);
    }

    public function testLinkedTrainerAndCustomerPasswordStayUnified(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE verejni_uzivatele SET trener_id=1 WHERE id=1");
        $tokens = [];
        \passwordResetRequest($pdo, 'parent@example.test', static function (string $email, string $token) use (&$tokens): bool {
            $tokens[] = $token;
            return true;
        }, 1_700_000_000);
        self::assertNotNull(\passwordResetConsume($pdo, $tokens[0], 'UnifiedPassword123!', 1_700_000_100));
        $publicHash = (string)$pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn();
        $trainerHash = (string)$pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn();
        self::assertSame($publicHash, $trainerHash);
        self::assertTrue(password_verify('UnifiedPassword123!', $trainerHash));
        self::assertSame(2, (int)$pdo->query('SELECT session_version FROM treneri WHERE id=1')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,aktivni INTEGER,email_overeno INTEGER,session_version INTEGER,trener_id INTEGER NULL)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test','old',1,1,1,NULL),(2,'inactive@example.test','old',0,1,1,NULL)");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','První'),(11,'Bára','Druhá')");
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,heslo TEXT,aktivni INTEGER,session_version INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'old',1,1)");
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY,sportovec_id INTEGER,login_key TEXT,password_hash TEXT,active INTEGER,session_version INTEGER,password_changed_at TEXT,updated_at TEXT)');
        $pdo->exec("INSERT INTO child_access_accounts VALUES(20,10,'anna.u15','old',1,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(21,11,'bara.u13','old',1,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE child_access_events(id INTEGER PRIMARY KEY AUTOINCREMENT,access_account_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,10,'guardian','approved','2020-01-01',NULL),(2,1,11,'guardian','approved','2020-01-01',NULL)");
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804235900_password_reset_tokens.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
