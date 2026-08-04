<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/unified_account.php';

final class UnifiedAccountTest extends TestCase
{
    public function testMigrationAndTrainerLinkAreIdempotent(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260805000000_unified_accounts_public_schedule.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));

        $trainer = $pdo->query('SELECT * FROM treneri WHERE id=7')->fetch(PDO::FETCH_ASSOC);
        $first = \unifiedAccountEnsureTrainerCustomer($pdo, $trainer);
        $second = \unifiedAccountEnsureTrainerCustomer($pdo, $trainer);
        self::assertSame((int)$first['id'], (int)$second['id']);
        self::assertSame(7, (int)$second['trener_id']);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM verejni_uzivatele WHERE email='coach@example.test'")->fetchColumn());
    }

    public function testExistingCustomerIsLinkedWithoutDuplicateAndTrainerCanUseShopLogin(): void
    {
        $pdo = $this->migratedDatabase();
        $pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,heslo_hash,email_overeno,aktivni,session_version) VALUES(4,'Coach','Customer','coach@example.test','old',1,1,1)");

        $identity = \unifiedAccountAuthenticate($pdo, 'COACH@example.test', 'TrainerPassword123!');
        self::assertNotNull($identity);
        self::assertSame(7, (int)$identity['trainer']['id']);
        self::assertSame(4, (int)$identity['public']['id']);
        self::assertSame(7, (int)$identity['public']['trener_id']);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM verejni_uzivatele WHERE email='coach@example.test'")->fetchColumn());
    }

    public function testOrdinaryCustomerStillAuthenticatesWithoutTrainerIdentity(): void
    {
        $pdo = $this->migratedDatabase();
        $hash = password_hash('CustomerPassword123!', PASSWORD_DEFAULT);
        $statement = $pdo->prepare("INSERT INTO verejni_uzivatele(jmeno,prijmeni,email,heslo_hash,email_overeno,aktivni,session_version) VALUES('Parent','One','parent@example.test',?,1,1,1)");
        $statement->execute([$hash]);
        $identity = \unifiedAccountAuthenticate($pdo, 'parent@example.test', 'CustomerPassword123!');
        self::assertNotNull($identity);
        self::assertNull($identity['trainer']);
    }

    private function migratedDatabase(): PDO
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260805000000_unified_accounts_public_schedule.php';
        $migration['up']($pdo);
        return $pdo;
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,heslo TEXT,role TEXT,aktivni INTEGER,session_version INTEGER)");
        $hash = password_hash('TrainerPassword123!', PASSWORD_DEFAULT);
        $statement = $pdo->prepare("INSERT INTO treneri VALUES(7,'Coach','coach@example.test',?,'admin',1,1)");
        $statement->execute([$hash]);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT NOT NULL,prijmeni TEXT NOT NULL,email TEXT NOT NULL UNIQUE,heslo_hash TEXT NOT NULL,email_overeno INTEGER NOT NULL DEFAULT 0,aktivni INTEGER NOT NULL DEFAULT 1,session_version INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec("CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,datum TEXT NOT NULL,stav TEXT NOT NULL DEFAULT 'planovany')");
        return $pdo;
    }
}
