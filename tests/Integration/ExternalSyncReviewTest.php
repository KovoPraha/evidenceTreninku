<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExternalSyncRegistrarFixture as Fixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/external_sync_person_panel.php';

final class ExternalSyncReviewTest extends TestCase
{
    private QueryProfilePdo $pdo;
    private ?PDO $server = null;
    private ?string $database = null;
    private string $token;

    protected function setUp(): void
    {
        if (getenv('EVIDENCE_EXTERNAL_SYNC_MARIADB') === '1') {
            $dsn = 'mysql:host=' . (getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1')
                . ';port=' . (getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306');
            $this->server = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->database = 'evidence_external_s4_' . bin2hex(random_bytes(6));
            $this->server->exec('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
            $this->pdo = new QueryProfilePdo($dsn . ';dbname=' . $this->database . ';charset=utf8mb4', 'root', '');
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } else {
            $this->pdo = new QueryProfilePdo('sqlite::memory:');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
        }
        Fixture::schema($this->pdo);
        $this->token = Fixture::connection($this->pdo, 'test-csc');
    }

    protected function tearDown(): void
    {
        if ($this->server !== null && $this->database !== null) {
            $this->server->exec('DROP DATABASE `' . $this->database . '`');
        }
    }

    private function unresolved(): string
    {
        $row = Fixture::batch()['rows'][0];
        $row['uci_id_raw'] = '00000000002';
        return Fixture::ingest($this->pdo, $this->token, [$row]);
    }

    private function decide(array $context, string $state, string $reason = 'TEST ověření registrářem'): int
    {
        return \externalSyncReviewDecide(
            $this->pdo,
            7,
            $context['run']['id'],
            1,
            $context['review']['revision'],
            $context['review']['basis_hash'],
            $state,
            $reason
        );
    }

    private function rejected(callable $call, string $code): void
    {
        $before = Fixture::state($this->pdo);
        try {
            $call();
            self::fail('Expected rejected review');
        } catch (\ExternalSyncException $error) {
            self::assertSame($code, $error->getMessage());
        }
        self::assertSame($before, Fixture::state($this->pdo));
    }

    public function testAuditLifecycleStaleRevisionAndOverviewUseSameBasis(): void
    {
        $run = $this->unresolved();
        $context = \externalSyncReviewGet($this->pdo, 7, $run, 1);
        $this->rejected(fn () => $this->decide($context, 'resolved'), 'identity_not_resolved');
        $id = $this->decide($context, 'deferred', 'TEST <script>audit</script>');
        self::assertGreaterThan(0, $id);
        $this->rejected(fn () => $this->decide($context, 'excluded'), 'review_changed');
        $input = \externalSyncAdminInput(['connection' => 'test-csc', 'work' => 'deferred']);
        $rows = \externalSyncAdminDetail($this->pdo, 7, $input)['projection']['rows'];
        self::assertCount(1, $rows);
        self::assertSame('deferred', $rows[0]['review']['state']);
        self::assertSame($context['review']['basis_hash'], $rows[0]['review']['basis_hash']);
        foreach (['excluded', 'open'] as $state) {
            $context = \externalSyncReviewGet($this->pdo, 7, $run, 1);
            $this->decide($context, $state);
        }
        $context = \externalSyncReviewGet($this->pdo, 7, $run, 1);
        self::assertSame(3, $context['review']['revision']);
        self::assertSame(['open', 'excluded', 'deferred'], array_column($context['history'], 'state'));
        self::assertSame([7, 7, 7], array_map('intval', array_column($context['history'], 'actor_id')));
        self::assertSame(3, (int)$this->pdo->query("SELECT COUNT(*) FROM external_sync_audit WHERE action_code LIKE 'row_%'")->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
    }

    public function testCanonicalCorrectionReopensDecisionAndResolvedAuditIsAtomic(): void
    {
        $run = $this->unresolved();
        $context = \externalSyncReviewGet($this->pdo, 7, $run, 1);
        $this->decide($context, 'excluded');
        $old = \externalSyncReviewGet($this->pdo, 7, $run, 1);
        $this->pdo->exec("UPDATE sportovci SET uciid='00000000002' WHERE id=1");
        $current = \externalSyncReviewGet($this->pdo, 7, $run, 1);
        self::assertTrue($current['review']['stale']);
        self::assertSame('open', $current['review']['state']);
        self::assertSame('matched', $current['match']['status']);
        $this->rejected(fn () => $this->decide($old, 'resolved'), 'review_changed');
        $this->rejected(fn () => $this->decide($current, 'excluded'), 'record_already_matched');
        $id = $this->decide($current, 'resolved');
        $history = $this->pdo->query('SELECT * FROM sportovec_history')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('external_sync_decisions', $history['ref_table']);
        self::assertSame($id, (int)$history['ref_id']);
        self::assertSame(['decision_id' => $id, 'revision' => 2], json_decode($history['new_json'], true));
        self::assertSame(1, (int)$history['sportovec_id']);
        $this->pdo->exec("INSERT INTO sportovci VALUES(2,'TEST Syntetický','A','2000-01-02','00000000002')");
        $duplicate = \externalSyncReviewGet($this->pdo, 7, $run, 1);
        self::assertTrue($duplicate['review']['stale']);
        $this->rejected(fn () => $this->decide($duplicate, 'resolved'), 'identity_not_resolved');
        $this->pdo->exec('DELETE FROM sportovci WHERE id=2');
        $this->pdo->exec('ALTER TABLE sportovec_history RENAME TO test_history_unavailable');
        $count = (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_decisions')->fetchColumn();
        $audits = (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_audit')->fetchColumn();
        try {
            $this->decide(\externalSyncReviewGet($this->pdo, 7, $run, 1), 'resolved');
            self::fail('History failure must rollback decision');
        } catch (\PDOException) {
            self::assertFalse($this->pdo->inTransaction());
        }
        self::assertSame($count, (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_decisions')->fetchColumn());
        self::assertSame($audits, (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_audit')->fetchColumn());
    }

    public function testSnapshotFencingInvalidInputsAndBoundedHistory(): void
    {
        $run = $this->unresolved();
        for ($i = 0; $i < 23; $i++) {
            $this->decide(\externalSyncReviewGet($this->pdo, 7, $run, 1), $i % 2 ? 'open' : 'deferred');
        }
        $context = \externalSyncReviewGet($this->pdo, 7, $run, 1);
        self::assertCount(20, $context['history']);
        self::assertTrue($context['history_has_next']);
        $older = \externalSyncReviewGet($this->pdo, 7, $run, 1, 4);
        self::assertSame([3, 2, 1], array_map('intval', array_column($older['history'], 'revision')));
        self::assertFalse($older['history_has_next']);
        $this->rejected(fn () => $this->decide($context, 'force_match'), 'invalid_review_input');
        $this->rejected(fn () => $this->decide($context, 'open', 'short'), 'invalid_review_input');
        $next = Fixture::ingest($this->pdo, $this->token, Fixture::batch()['rows']);
        self::assertFalse(\externalSyncReviewGet($this->pdo, 7, $run, 1)['current']);
        $this->rejected(fn () => $this->decide($context, 'open'), 'review_changed');
        self::assertSame([], \externalSyncReviewGet($this->pdo, 7, $next, 1)['history']);
        foreach ([8, 9] as $actor) {
            try {
                \externalSyncReviewGet($this->pdo, $actor, $run, 1);
                self::fail('Unauthorized review');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
            try {
                \externalSyncReviewDecide($this->pdo, $actor, $next, 1, 0, str_repeat('a', 64), 'resolved', 'TEST invalid actor');
                self::fail('Unauthorized write');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function testPersonPanelAllCollectionsMultipleLicencesPaginationAndFreshIdentity(): void
    {
        $rows = [];
        $base = Fixture::batch()['rows'][0];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = array_replace($base, ['license_id' => 'TEST-license-' . $i]);
        }
        $run = Fixture::ingest($this->pdo, $this->token, $rows);
        foreach (['sportsoft_members', 'sportsoft_registrations', 'sportsoft_events'] as $collection) {
            $token = Fixture::connection($this->pdo, $collection, $collection);
            Fixture::ingest($this->pdo, $token, Fixture::batch($collection)['rows']);
        }
        $this->pdo->exec('UPDATE external_sync_connections SET enabled=0');
        $before = Fixture::state($this->pdo);
        $this->pdo->profiling = true;
        $this->pdo->profile = [];
        $panel = \externalSyncPersonPanel($this->pdo, 7, 1);
        $queries = count($this->pdo->profile);
        self::assertLessThanOrEqual(7, $queries);
        self::assertSame(32, $panel['total']);
        self::assertCount(25, $panel['rows']);
        self::assertTrue($panel['has_next']);
        self::assertTrue($panel['rows'][0]['stale']);
        self::assertNotNull($panel['rows'][0]['observed_at']);
        $this->pdo->profile = [];
        $last = \externalSyncPersonPanel($this->pdo, 7, 1, 2);
        self::assertSame($queries, count($this->pdo->profile));
        self::assertCount(7, $last['rows']);
        self::assertFalse($last['has_next']);
        self::assertSame(['csc_licenses', 'sportsoft_members', 'sportsoft_registrations'], array_values(array_unique(array_column($last['rows'], 'collection'))));
        self::assertSame($before, Fixture::state($this->pdo));
        $this->pdo->exec("UPDATE sportovci SET prijmeni='TEST Changed'");
        self::assertSame([], \externalSyncPersonPanel($this->pdo, 7, 1)['rows']);
        $this->pdo->exec("UPDATE sportovci SET prijmeni='A'; INSERT INTO sportovci VALUES(2,'TEST Syntetický','A','2000-01-02','00000000001')");
        self::assertSame('duplicate_kis_uci', \externalSyncPersonPanel($this->pdo, 7, 1)['identity']);
        $this->pdo->exec("UPDATE sportovci SET uciid=NULL WHERE id=1");
        self::assertSame('missing_or_invalid', \externalSyncPersonPanel($this->pdo, 7, 1)['identity']);
    }

    public function testPanelRejectsWholeRunConflictsAndPendingProposal(): void
    {
        $row = Fixture::batch()['rows'][0];
        $run = Fixture::ingest($this->pdo, $this->token, [$row], false);
        self::assertSame([], \externalSyncPersonPanel($this->pdo, 7, 1)['rows']);
        self::assertTrue(\externalSyncReviewGet($this->pdo, 7, $run, 1)['current']);
        \externalSyncApproveSnapshot($this->pdo, $run, 7);
        self::assertCount(1, \externalSyncPersonPanel($this->pdo, 7, 1)['rows']);
        $rows = [$row, array_replace($row, ['license_id' => 'TEST-other', 'display_name' => 'TEST Jiná osoba'])];
        Fixture::ingest($this->pdo, $this->token, $rows);
        $panel = \externalSyncPersonPanel($this->pdo, 7, 1);
        self::assertSame([], $panel['rows']);
        self::assertSame(2, $panel['rejected']);
        $this->pdo->exec('DELETE FROM staff_user_positions');
        $this->expectException(\DomainException::class);
        \externalSyncPersonPanel($this->pdo, 7, 1);
    }

    public function testWaitingReviewRechecksActorAndCanonicalIdentity(): void
    {
        if ($this->server === null) {
            self::markTestSkipped('Native MariaDB lock-wait proof runs on 10.3 and 11.4.');
        }
        $run = $this->unresolved();
        foreach (['identity', 'actor', 'revision'] as $change) {
            $context = \externalSyncReviewGet($this->pdo, 7, $run, 1);
            $this->pdo->beginTransaction();
            $this->pdo->query('SELECT id FROM external_sync_connections FOR UPDATE')->fetchAll();
            $code = <<<'PHP'
                require $argv[1];
                $pdo = new PDO($argv[2], 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                $pdo->exec('SET innodb_lock_wait_timeout=10');
                echo "ready\n"; flush();
                try { externalSyncReviewDecide($pdo, 7, $argv[3], 1, 0, $argv[4], 'deferred', 'TEST waiting decision'); echo 'unexpected'; }
                catch (ExternalSyncException $e) { echo $e->getMessage(); }
                catch (DomainException $e) { echo 'denied'; }
                PHP;
            $dsn = 'mysql:host=' . (getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1') . ';port='
                . (getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306') . ';dbname=' . $this->database . ';charset=utf8mb4';
            $process = proc_open([PHP_BINARY, '-r', $code, dirname(__DIR__, 2) . '/includes/external_sync_review.php',
                $dsn, $run, $context['review']['basis_hash']], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            try {
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 10);
                self::assertSame("ready\n", fgets($pipes[1]));
                $waiting = false;
                for ($i = 0; $i < 25; $i++) {
                    $query = $this->server->prepare('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w
                        JOIN information_schema.INNODB_TRX t ON t.trx_id=w.blocking_trx_id WHERE t.trx_mysql_thread_id=?');
                    $query->execute([(int)$this->pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
                    if ((int)$query->fetchColumn() > 0) {
                        $waiting = true;
                        break;
                    }
                    usleep(200000);
                }
                self::assertTrue($waiting, 'Review must actually wait on the shared publication fence');
                if ($change === 'actor') {
                    $this->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');
                } elseif ($change === 'identity') {
                    $this->pdo->exec("UPDATE sportovci SET uciid='00000000002'");
                } else {
                    \externalSyncQuery(
                        $this->pdo,
                        'INSERT INTO external_sync_decisions(run_id,ordinal_no,revision,state,reason,actor_id,recorded_at,basis_hash) VALUES(?,1,1,?,?,7,1,?)',
                        [$run, 'excluded', 'TEST concurrent registrar', $context['review']['basis_hash']]
                    );
                }
                $this->pdo->commit();
                self::assertSame($change === 'actor' ? 'denied' : 'review_changed', stream_get_contents($pipes[1]));
                self::assertSame('', stream_get_contents($pipes[2]));
                self::assertSame($change === 'revision' ? 1 : 0, (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_decisions')->fetchColumn());
                self::assertSame(0, (int)$this->pdo->query("SELECT COUNT(*) FROM external_sync_audit WHERE action_code LIKE 'row_%'")->fetchColumn());
            } finally {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
            if ($change === 'actor') {
                $this->pdo->exec("INSERT INTO staff_user_positions VALUES(7,'registrar')");
            }
            $this->pdo->exec("UPDATE sportovci SET uciid='00000000001'");
        }
    }
}
