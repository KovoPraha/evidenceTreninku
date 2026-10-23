<?php
declare(strict_types=1);
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/includes/sportovec_history_lib.php';

final class SportovecInternalNoteTest extends TestCase
{
    private function database(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO sportovci VALUES(1)');
        $pdo->exec('CREATE TABLE sportovec_interni_poznamka(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,trener_id INTEGER,text TEXT)');
        $pdo->exec('CREATE TABLE sportovec_history(sportovec_id INTEGER,created_by INTEGER,source TEXT,event_type TEXT,title TEXT,detail TEXT,old_json TEXT,new_json TEXT,ref_table TEXT,ref_id INTEGER)');
        $pdo->exec('CREATE TABLE ucto_audit_log(uzivatel_id INTEGER,akce TEXT,tabulka TEXT,zaznam_id INTEGER,detail TEXT,ip_adresa TEXT,user_agent TEXT)');
        return $pdo;
    }

    public function testNoteAndLinkedHistoryPersistTogether(): void
    {
        $pdo = $this->database();
        $id = sportovecInternalNoteAdd($pdo, 1, 7, ' LOCALHOST kontrolní poznámka ');
        $note = $pdo->query('SELECT * FROM sportovec_interni_poznamka')->fetch(\PDO::FETCH_ASSOC);
        $history = $pdo->query('SELECT * FROM sportovec_history')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('LOCALHOST kontrolní poznámka', $note['text']);
        self::assertSame(7, (int)$note['trener_id']);
        self::assertSame($id, (int)$history['ref_id']);
        self::assertSame('sportovec_interni_poznamka', $history['ref_table']);
        self::assertSame($note['text'], $history['detail']);
        self::assertFalse($pdo->inTransaction());
    }

    public function testAuditFailureRollsBackNoteAndAllowsSafeRetry(): void
    {
        $pdo = $this->database();
        $pdo->exec("CREATE TRIGGER fail_history BEFORE INSERT ON sportovec_history BEGIN SELECT RAISE(ABORT,'LOCALHOST audit failure'); END");
        try { sportovecInternalNoteAdd($pdo, 1, 7, 'LOCALHOST poznámka'); self::fail('Note persisted without audit'); }
        catch (\PDOException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_interni_poznamka')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
            self::assertFalse($pdo->inTransaction());
        }
        $pdo->exec('DROP TRIGGER fail_history');
        sportovecInternalNoteAdd($pdo, 1, 7, 'LOCALHOST poznámka');
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_interni_poznamka')->fetchColumn());
    }

    public function testUnknownPersonAndEmptyNoteCannotCreateOrphans(): void
    {
        $pdo = $this->database();
        foreach ([[999,7,'LOCALHOST'], [1,0,'LOCALHOST'], [1,7,' ']] as [$person, $actor, $text]) {
            try { sportovecInternalNoteAdd($pdo, $person, $actor, $text); self::fail('Invalid note accepted'); }
            catch (\InvalidArgumentException) { self::assertFalse($pdo->inTransaction()); }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_interni_poznamka')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
    }
}
