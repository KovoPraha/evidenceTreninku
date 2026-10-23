<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sportovec_note_access.php';

final class SportovecNoteAccessTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)');
        $this->pdo->exec('INSERT INTO verejni_uzivatele VALUES(21,1,1)');
        $this->pdo->exec(
            'CREATE TABLE child_access_accounts '
            . '(id INTEGER PRIMARY KEY,sportovec_id INTEGER NOT NULL,active INTEGER NOT NULL)'
        );
        $this->pdo->exec(
            'CREATE TABLE account_person_roles '
            . '(account_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,relation_role TEXT NOT NULL,'
            . 'status TEXT NOT NULL,valid_from TEXT NOT NULL,valid_to TEXT NULL)'
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testAnonymousBearerCannotWrite(): void
    {
        self::assertFalse(\sportovecNoteCanWrite($this->pdo, 7));
    }

    public function testMatchingActiveAthleteAccountCanWrite(): void
    {
        $this->pdo->exec('INSERT INTO child_access_accounts VALUES (11,7,1)');
        $_SESSION['sportovec_pristup_id'] = 11;
        self::assertTrue(\sportovecNoteCanWrite($this->pdo, 7));
        self::assertFalse(\sportovecNoteCanWrite($this->pdo, 8));
    }

    public function testApprovedCurrentGuardianCanWrite(): void
    {
        $this->pdo->exec(
            "INSERT INTO account_person_roles VALUES (21,7,'guardian','approved','2020-01-01 00:00:00',NULL)"
        );
        $_SESSION['verejny_uzivatel_id'] = 21;
        self::assertTrue(\sportovecNoteCanWrite($this->pdo, 7));
    }

    public function testExpiredOrPendingRelationCannotWrite(): void
    {
        $this->pdo->exec(
            "INSERT INTO account_person_roles VALUES (21,7,'guardian','approved','2020-01-01 00:00:00','2020-01-02 00:00:00')"
        );
        $this->pdo->exec(
            "INSERT INTO account_person_roles VALUES (21,8,'self','pending','2020-01-01 00:00:00',NULL)"
        );
        $_SESSION['verejny_uzivatel_id'] = 21;
        self::assertFalse(\sportovecNoteCanWrite($this->pdo, 7));
        self::assertFalse(\sportovecNoteCanWrite($this->pdo, 8));
    }

    public function testUnverifiedDisabledAndRevokedGuardianCannotWriteEvenWithProfileAccess(): void
    {
        $this->pdo->exec("INSERT INTO account_person_roles VALUES(21,7,'guardian','approved','2020-01-01',NULL)");
        $_SESSION['verejny_uzivatel_id']=21;
        $_SESSION['public_profile_access']=['sportovec_id'=>7,'expires_at'=>time()+1000];
        foreach (['email_overeno=0','email_overeno=1,aktivni=0'] as $change) {
            $this->pdo->exec('UPDATE verejni_uzivatele SET '.$change.' WHERE id=21');
            self::assertFalse(\sportovecNoteCanWrite($this->pdo,7));
        }
        $this->pdo->exec('UPDATE verejni_uzivatele SET aktivni=1,email_overeno=1 WHERE id=21');
        $this->pdo->exec("UPDATE account_person_roles SET status='revoked'");
        self::assertFalse(\sportovecNoteCanWrite($this->pdo,7));
    }

    public function testPublicEndpointsUseTheAuthenticatedWriteGate(): void
    {
        $root = dirname(__DIR__, 2);
        $save = (string)file_get_contents($root . '/ajax_sportovec_poznamka.php');
        $list = (string)file_get_contents($root . '/ajax_sportovec_treninky.php');
        self::assertStringContainsString('sportovecNoteCanWrite($pdo, $sportovec_id)', $save);
        self::assertStringContainsString('http_response_code(403)', $save);
        self::assertStringContainsString('$canWriteNote = sportovecNoteCanWrite', $list);
        self::assertStringContainsString('Pro úpravu se přihlaste účtem sportovce nebo rodiče.', $list);
    }
}
