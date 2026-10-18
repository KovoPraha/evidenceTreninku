<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/rotate-production-uat-passwords.php';

final class ProductionUatPasswordRotationTest extends TestCase
{
    private function fixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,trener_id INTEGER,heslo_hash TEXT,email_overeno INTEGER,aktivni INTEGER,verifikacni_token TEXT,verifikacni_token_expires_at TEXT,session_version INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT)');
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY,sportovec_id INTEGER,login_key TEXT,password_hash TEXT,active INTEGER,password_changed_at TEXT,session_version INTEGER,updated_at TEXT)');
        $pdo->exec('CREATE TABLE child_access_events(access_account_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,note TEXT)');
        $pdo->exec('CREATE TABLE password_reset_tokens(delivery_account_id INTEGER,consumed_at TEXT)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES
            (1,'Tester','Karel','tester.karel@velocota.com',NULL,'old',0,0,NULL,NULL,1),
            (2,'Tester','Petra','tester.petra@velocota.com',NULL,'old',0,0,NULL,NULL,1),
            (3,'Real','Member','real@example.test',NULL,'real-hash',1,1,NULL,NULL,1)");
        $pdo->exec("INSERT INTO sportovci VALUES (10,'Ema','Tester','2018-04-12'),(11,'Adam','Tester','2017-09-18')");
        $pdo->exec("INSERT INTO child_access_accounts VALUES
            (20,10,'tester.ema','old',0,NULL,1,NULL),
            (21,11,'tester.adam','old',0,NULL,1,NULL)");
        $pdo->exec('INSERT INTO password_reset_tokens VALUES (1,NULL)');
        return $pdo;
    }

    public function testRotatesExactlyFourFixtureAccountsAndAuditsChildren(): void
    {
        $pdo = $this->fixture();
        $result = kisUatRotatePasswords($pdo, 'Aa1!UniqueTemporaryTestPassword2026');
        self::assertSame(['parents_rotated' => 2, 'children_rotated' => 2], $result);
        foreach ([1, 2] as $id) {
            $row = $pdo->query('SELECT heslo_hash,aktivni,email_overeno,session_version FROM verejni_uzivatele WHERE id=' . $id)->fetch();
            self::assertTrue(password_verify('Aa1!UniqueTemporaryTestPassword2026', $row['heslo_hash']));
            self::assertSame(1, (int)$row['aktivni']);
            self::assertSame(1, (int)$row['email_overeno']);
            self::assertSame(2, (int)$row['session_version']);
        }
        self::assertSame('real-hash', $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=3')->fetchColumn());
        self::assertNotNull($pdo->query('SELECT consumed_at FROM password_reset_tokens')->fetchColumn());
        foreach ([20, 21] as $id) {
            $row = $pdo->query('SELECT password_hash,active,session_version FROM child_access_accounts WHERE id=' . $id)->fetch();
            self::assertTrue(password_verify('Aa1!UniqueTemporaryTestPassword2026', $row['password_hash']));
            self::assertSame(1, (int)$row['active']);
            self::assertSame(2, (int)$row['session_version']);
        }
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM child_access_events WHERE actor_type='system' AND action='password_reset'")->fetchColumn());
    }

    public function testIdentityCollisionRollsBackEveryAccount(): void
    {
        $pdo = $this->fixture();
        $pdo->exec("UPDATE sportovci SET narozeni='2000-01-01' WHERE id=11");
        try {
            kisUatRotatePasswords($pdo, 'Aa1!UniqueTemporaryTestPassword2026');
            self::fail('Identity collision must fail closed.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('identity changed', $exception->getMessage());
        }
        self::assertSame('old', $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM child_access_events')->fetchColumn());
    }
}
