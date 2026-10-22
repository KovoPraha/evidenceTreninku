<?php
declare(strict_types=1);

namespace Tests\Integration;

use DomainException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/athlete_reward_guard.php';

final class AthleteRewardGuardTest extends TestCase
{
    private PDO $pdo;
    private array $oldSession;

    protected function setUp(): void
    {
        $this->oldSession = $_SESSION ?? [];
        $_SESSION = ['trener_id' => 1, 'role' => 'admin', 'opravneni' => ['kreditni_obdobi' => 'hlavni']];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE sportovci (id INTEGER PRIMARY KEY)');
        $this->pdo->exec('INSERT INTO sportovci VALUES (1), (2), (3)');
        $this->pdo->exec('CREATE TABLE sportovec_obdobi (id INTEGER PRIMARY KEY AUTOINCREMENT, sportovec_id INT NOT NULL, datum_od TEXT NOT NULL, datum_do TEXT NULL, sazba_kc DECIMAL(10,2), pocet_treninku INT NULL, castka_celkem DECIMAL(10,2) NULL, vyplaceno INT NOT NULL DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE treninky (id INTEGER PRIMARY KEY, datum TEXT)');
        $this->pdo->exec('CREATE TABLE trenink_sportovec (trenink_id INT, sportovec_id INT)');
        $this->pdo->exec("INSERT INTO treninky VALUES (1, '2026-10-01'), (2, '2026-10-10'), (3, '2026-10-21')");
        $this->pdo->exec('INSERT INTO trenink_sportovec VALUES (1,1), (1,1), (2,1), (3,1), (1,2)');
        $this->pdo->exec('CREATE TABLE ucto_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, uzivatel_id INT, akce TEXT, tabulka TEXT, zaznam_id INT, detail TEXT, ip_adresa TEXT, user_agent TEXT)');
    }

    protected function tearDown(): void { $_SESSION = $this->oldSession; }

    private function row(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sportovec_obdobi WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function blocked(callable $action, string $reason): void
    {
        try { $action(); self::fail('Expected protected-state rejection.'); }
        catch (DomainException $e) { self::assertStringContainsString($reason, $e->getMessage()); }
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testOpenEditsAndFirstCloseProduceAuditedSnapshot(): void
    {
        $id = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', null);
        athleteRewardSave($this->pdo, 1, $id, 12.5, '2026-10-01', null);
        self::assertSame(['pocet' => 2, 'kredit' => 25.0], athleteRewardClose($this->pdo, 1, $id, '2026-10-20'));
        self::assertSame(2, (int)$this->row($id)['pocet_treninku']);
        self::assertSame(25.0, (float)$this->row($id)['castka_celkem']);
        self::assertSame(3, (int)$this->pdo->query('SELECT COUNT(*) FROM ucto_audit_log')->fetchColumn());
    }

    public function testRepeatCloseAndEditDoNotRecomputeAfterTrainingChanges(): void
    {
        $id = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', '2026-10-20');
        $before = $this->row($id);
        $this->pdo->exec('DELETE FROM trenink_sportovec');
        $this->blocked(fn() => athleteRewardClose($this->pdo, 1, $id, '2026-10-22'), 'Období #' . $id);
        $this->blocked(fn() => athleteRewardSave($this->pdo, 1, $id, 100, '2026-01-01', null), 'uzavřené nebo vyplacené');
        self::assertSame($before, $this->row($id));
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM ucto_audit_log')->fetchColumn());
    }

    public function testPaidOpenAnomalyBlocksEditCloseAndEntireBulkBatch(): void
    {
        $first = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', null);
        $paid = athleteRewardSave($this->pdo, 2, 0, 20, '2026-10-01', null);
        $this->pdo->exec('UPDATE sportovec_obdobi SET vyplaceno = 1 WHERE id = ' . $paid);
        $before = $this->row($paid);
        $this->blocked(fn() => athleteRewardSave($this->pdo, 2, $paid, 900, '2026-01-01', null), 'hospodáři');
        $this->blocked(fn() => athleteRewardClose($this->pdo, 2, $paid, '2026-10-20'), 'hospodáři');
        $this->blocked(fn() => athleteRewardBulkRates($this->pdo, [1 => 500, 2 => 900], '2026-10-22'), 'Období #' . $paid);
        self::assertSame($before, $this->row($paid));
        self::assertSame(10.0, (float)$this->row($first)['sazba_kc']);
        self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM ucto_audit_log')->fetchColumn());
    }

    public function testBulkUpdatesOnlyOpenPeriodAndPreservesClosedAndPaidHistory(): void
    {
        $closed = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', '2026-10-10');
        athleteRewardMarkPaid($this->pdo, 1, $closed);
        $before = $this->row($closed);
        $open = athleteRewardSave($this->pdo, 1, 0, 20, '2026-10-11', null);
        self::assertSame(['updated' => 1, 'inserted' => 1], athleteRewardBulkRates($this->pdo, [2 => 25, 1 => 30], '2026-10-22'));
        self::assertSame($before, $this->row($closed));
        self::assertSame(30.0, (float)$this->row($open)['sazba_kc']);
    }

    public function testHistoricalMissingSnapshotRemainsUnknownAndCannotBeMarkedPaid(): void
    {
        $this->pdo->exec("INSERT INTO sportovec_obdobi (sportovec_id, datum_od, datum_do, sazba_kc) VALUES (1, '2026-10-01', '2026-10-20', 10)");
        $id = (int)$this->pdo->lastInsertId();
        $before = $this->row($id);
        self::assertFalse(athleteRewardHasSnapshot($before));
        $this->blocked(fn() => athleteRewardMarkPaid($this->pdo, 1, $id), 'nemá uzavřený souhrn');
        $this->blocked(fn() => athleteRewardClose($this->pdo, 1, $id, '2026-10-22'), 'uzavřené nebo vyplacené');
        self::assertSame($before, $this->row($id));
    }

    public function testPaidMarkerIsAuditedAndCannotBeChangedRepeatedly(): void
    {
        $id = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', '2026-10-20');
        athleteRewardMarkPaid($this->pdo, 1, $id);
        $before = $this->row($id);
        $this->blocked(fn() => athleteRewardMarkPaid($this->pdo, 1, $id), 'uzavřené nebo vyplacené');
        $this->blocked(fn() => athleteRewardSave($this->pdo, 1, $id, 100, '2026-01-01', null), 'uzavřené nebo vyplacené');
        self::assertSame($before, $this->row($id));
        $audit = json_decode((string)$this->pdo->query("SELECT detail FROM ucto_audit_log WHERE akce = 'reward_period_mark_paid'")->fetchColumn(), true);
        self::assertSame(0, (int)$audit['before']['vyplaceno']);
        self::assertSame(1, (int)$audit['after']['vyplaceno']);
    }

    public function testPersonCannotBeReassignedThroughMismatchedId(): void
    {
        $id = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', null);
        $before = $this->row($id);
        $this->blocked(fn() => athleteRewardSave($this->pdo, 2, $id, 20, '2026-10-01', null), 'nebylo nalezeno');
        self::assertSame($before, $this->row($id));
    }

    public function testBadDateAndNegativeRateLeaveNoChanges(): void
    {
        $this->blocked(fn() => athleteRewardSave($this->pdo, 1, 0, -1, '2026-10-01', null), 'Sazba');
        $this->blocked(fn() => athleteRewardSave($this->pdo, 1, 0, 10, '2026-02-30', null), 'platné datum');
        $this->blocked(fn() => athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-20', '2026-10-01'), 'Konec období');
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM sportovec_obdobi')->fetchColumn());
    }

    public function testMissingWritePermissionPreventsEvenOverviewStyleClose(): void
    {
        $id = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', null);
        $_SESSION['opravneni'] = ['prehled_kreditu' => 'trener'];
        $this->blocked(fn() => athleteRewardClose($this->pdo, 1, $id, '2026-10-20'), 'Nemáte oprávnění');
        $this->blocked(fn() => athleteRewardBulkRates($this->pdo, [1 => 900], '2026-10-22'), 'Nemáte oprávnění');
        self::assertNull($this->row($id)['datum_do']);
    }

    public function testAuditFailureRollsBackFinancialChange(): void
    {
        $id = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', null);
        $before = $this->row($id);
        $this->pdo->exec('DROP TABLE ucto_audit_log');
        try { athleteRewardClose($this->pdo, 1, $id, '2026-10-20'); self::fail('Audit must be mandatory.'); }
        catch (\PDOException $e) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame($before, $this->row($id));
    }

    public function testZeroSnapshotIsValidAndZeroDateRemainsOpen(): void
    {
        $id = athleteRewardSave($this->pdo, 3, 0, 0, '2026-10-01', '2026-10-20');
        self::assertTrue(athleteRewardHasSnapshot($this->row($id)));
        athleteRewardMarkPaid($this->pdo, 3, $id);
        self::assertFalse(athleteRewardIsProtected(['datum_do' => '0000-00-00', 'vyplaceno' => 0]));
        self::assertTrue(athleteRewardIsProtected(['datum_do' => '0000-00-00', 'vyplaceno' => 1]));
    }

    public function testAmbiguousMultipleOpenPeriodsBlockBulkWithoutChanges(): void
    {
        $id = athleteRewardSave($this->pdo, 1, 0, 10, '2026-10-01', null);
        athleteRewardSave($this->pdo, 1, 0, 20, '2026-10-02', null);
        $this->blocked(fn() => athleteRewardBulkRates($this->pdo, [1 => 900], '2026-10-22'), 'více otevřených období');
        self::assertSame(10.0, (float)$this->row($id)['sazba_kc']);
    }
}
