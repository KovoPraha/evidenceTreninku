<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/password_security.php';
require_once dirname(__DIR__,2).'/includes/public_profile_token.php';

final class SecurityCredentialRotationTest extends TestCase
{
    public function testRotationReplacesEveryPublicTokenAndPlaintextPasswordExactlyOnce():void
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE nastaveni(klic TEXT PRIMARY KEY,hodnota TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,hash TEXT NOT NULL)');
        $pdo->exec("INSERT INTO sportovci VALUES(1,'Alice','".hash('sha256','1-Alice')."'),(2,'Bob','SHORT')");
        $modern=\trainer_password_hash('already-safe');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,heslo TEXT NOT NULL)');
        $insert=$pdo->prepare('INSERT INTO treneri VALUES(?,?)');$insert->execute([1,'legacy-secret']);$insert->execute([2,$modern]);
        $migration=require dirname(__DIR__,2).'/migrations/20260804235500_public_profile_token_rotation.php';

        $migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $tokens=$pdo->query('SELECT hash FROM sportovci ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(2,array_unique($tokens));foreach($tokens as$token)self::assertTrue(\public_profile_token_is_strong((string)$token));
        $passwords=$pdo->query('SELECT heslo FROM treneri ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertTrue(password_verify('legacy-secret',(string)$passwords[0]));self::assertSame($modern,$passwords[1]);

        $snapshot=[$tokens,$passwords];$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        self::assertSame($snapshot[0],$pdo->query('SELECT hash FROM sportovci ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame($snapshot[1],$pdo->query('SELECT heslo FROM treneri ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }
}
