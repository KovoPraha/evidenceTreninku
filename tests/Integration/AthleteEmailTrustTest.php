<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/password_reset.php';

final class AthleteEmailTrustTest extends TestCase
{
    public function testAutoCreatedAthleteAccessUsesSpecificLoginOnSharedMailAndDoesNotGrantFamilyRoles(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20261124162000_athlete_self_access.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM child_access_accounts')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
        self::assertSame('undeliverable', $pdo->query('SELECT state FROM athlete_email_trust WHERE sportovec_id=13')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT active FROM child_access_accounts WHERE sportovec_id=10')->fetchColumn());
        $keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
        self::assertFalse(\passwordResetQueueRequest($pdo, 'family@example.test', 'https://example.test/reset.php', $keys)['issued']);
        $queued = \passwordResetQueueRequest($pdo, 'sportovec.10', 'https://example.test/reset.php', $keys);
        self::assertTrue($queued['issued']);
        $mail = $pdo->query('SELECT * FROM auth_mail_outbox')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('family@example.test', $mail['recipient_email']);
        self::assertTrue(\authMailTokenIsCurrent($pdo, $mail));
        self::assertStringContainsString('sportovec.10', \authMailDecrypt($mail, $keys));
        self::assertSame('sent', \authMailProcessOne($pdo, static fn (): bool => true, $keys));
        self::assertSame('child', \passwordResetConsume($pdo, $queued['token'], 'Athlete first password 123!')['target_type']);
        self::assertSame(1, (int)$pdo->query('SELECT active FROM child_access_accounts WHERE sportovec_id=10')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        $pdo->exec('UPDATE child_access_accounts SET active=0 WHERE sportovec_id=10');
        self::assertFalse(\passwordResetQueueRequest($pdo, 'sportovec.10', 'https://example.test/reset.php', $keys)['issued']);
        $migration['up']($pdo);
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM child_access_accounts')->fetchColumn());
    }

    public function testUniqueAthleteEmailCanRequestResetAndAddressChangeRevokesLink(): void
    {
        $pdo = $this->database();
        (require dirname(__DIR__, 2) . '/migrations/20261124162000_athlete_self_access.php')['up']($pdo);
        $token = '';
        self::assertTrue(\passwordResetRequest($pdo, 'own@example.test', static function ($email, $issued) use (&$token): bool {
            $token = $issued;
            return true;
        })['issued']);
        $pdo->exec("UPDATE sportovci SET email='changed@example.test' WHERE id=12");
        self::assertNull(\passwordResetLookup($pdo, $token));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'coach@example.test')");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'A','One','family@example.test'),(11,'B','Two','family@example.test'),(12,'C','Three','own@example.test'),(13,'D','Four',NULL)");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,aktivni INTEGER,email_overeno INTEGER,trener_id INTEGER,heslo_hash TEXT,session_version INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test',1,1,NULL,'old',1)");
        $pdo->exec('CREATE TABLE account_person_roles(account_id INTEGER,sportovec_id INTEGER,status TEXT,relation_role TEXT,valid_from TEXT,valid_to TEXT)');
        foreach (['20260804190000_child_access_accounts','20260804235900_password_reset_tokens',
            '20261112150000_imported_account_activation','20261019100000_auth_mail_outbox'] as $id) {
            (require dirname(__DIR__, 2) . '/migrations/' . $id . '.php')['up']($pdo);
        }
        return $pdo;
    }
}
