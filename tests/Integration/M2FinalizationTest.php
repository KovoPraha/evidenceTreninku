<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/m2_finalization.php';

final class M2FinalizationTest extends TestCase
{
    public function testFinalizationSeparatesTechnicalReadinessFromOwnerAcceptance(): void
    {
        $pdo = $this->database();
        $root = $this->migrationRoot($pdo);
        $scenarios = [];
        for ($index = 1; $index <= 10; $index++) {
            $scenarios[] = ['id' => sprintf('A%02d', $index), 'status' => 'ready'];
        }
        try {
            $waiting = \m2FinalizationStatus($pdo, $root, $scenarios, []);
            self::assertSame(3, $waiting['technical_passed']);
            self::assertSame(0, $waiting['accepted']);
            self::assertFalse($waiting['close_ready']);

            $feedback = [];
            foreach ($scenarios as $scenario) $feedback[$scenario['id']] = ['result' => 'pass', 'importance' => 'none'];
            $ready = \m2FinalizationStatus($pdo, $root, $scenarios, $feedback);
            self::assertSame(10, $ready['accepted']);
            self::assertSame(0, $ready['blocking']);
            self::assertTrue($ready['close_ready']);

            $feedback['A07'] = ['result' => 'partial', 'importance' => 'blocks'];
            $blocked = \m2FinalizationStatus($pdo, $root, $scenarios, $feedback);
            self::assertSame(9, $blocked['accepted']);
            self::assertSame(1, $blocked['blocking']);
            self::assertFalse($blocked['close_ready']);
        } finally {
            unlink($root . '/migrations/20260805000000_demo.php');
            rmdir($root . '/migrations');
            rmdir($root);
        }
    }

    public function testMissingRouteOrIncompleteDemoFailsClosed(): void
    {
        $pdo = $this->database();
        $pdo->exec("DELETE FROM child_access_accounts");
        $root = $this->migrationRoot($pdo);
        try {
            $status = \m2FinalizationStatus($pdo, $root, [['id' => 'A01', 'status' => 'unavailable']], []);
            self::assertSame('fail', $status['checks'][0]['status']);
            self::assertSame('wait', $status['checks'][2]['status']);
            self::assertFalse($status['close_ready']);
        } finally {
            unlink($root . '/migrations/20260805000000_demo.php');
            rmdir($root . '/migrations');
            rmdir($root);
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE evidence_schema_migrations(id TEXT PRIMARY KEY,checksum TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE account_person_roles(account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE child_access_accounts(login_key TEXT,active INTEGER)');
        $pdo->exec('CREATE TABLE shop_products(external_product_key TEXT,catalog_status TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'localhost-admin',1)");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'rodic@localhost.test',1,1)");
        $pdo->exec('INSERT INTO sportovci VALUES(10),(11)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,10,'guardian','approved',NULL),(1,11,'guardian','approved',NULL)");
        $pdo->exec("INSERT INTO child_access_accounts VALUES('localhost-sportovec',1)");
        $pdo->exec("INSERT INTO shop_products VALUES('local-demo:club-event','active')");
        return $pdo;
    }

    private function migrationRoot(PDO $pdo): string
    {
        $root = sys_get_temp_dir() . '/m2-finalization-' . bin2hex(random_bytes(6));
        mkdir($root);
        mkdir($root . '/migrations');
        $file = $root . '/migrations/20260805000000_demo.php';
        file_put_contents($file, "<?php\nreturn ['id'=>'20260805000000_demo','up'=>static function(PDO \$pdo):void{},'verify'=>static function(PDO \$pdo):void{}];\n");
        $statement = $pdo->prepare('INSERT INTO evidence_schema_migrations(id,checksum) VALUES(?,?)');
        $statement->execute(['20260805000000_demo', hash_file('sha256', $file)]);
        return $root;
    }
}
