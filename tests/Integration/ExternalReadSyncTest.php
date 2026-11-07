<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/external_sync_read.php';

final class ExternalReadSyncTest extends TestCase
{
    private PDO $pdo;
    private string $token;
    private array $fixture;
    private ?PDO $server = null;
    private ?string $database = null;

    protected function setUp(): void
    {
        if (getenv('EVIDENCE_EXTERNAL_SYNC_MARIADB') === '1') {
            $host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
            $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
            $this->server = new PDO('mysql:host=' . $host . ';port=' . $port, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->database = 'evidence_external_sync_test_' . bin2hex(random_bytes(6));
            $this->server->exec('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
            $this->pdo = new PDO(
                'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $this->database . ';charset=utf8mb4',
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
            );
        } else {
            $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('PRAGMA foreign_keys=ON');
        }
        $migration = require dirname(__DIR__, 2) . '/migrations/20261107210000_external_read_sync.php';
        $migration['up']($this->pdo);
        $migration['up']($this->pdo);
        self::assertTrue($migration['verify']($this->pdo));
        $this->pdo->exec("CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT);
            INSERT INTO sportovci VALUES(1,'TEST Syntetický','A','2000-01-02','00000000001');
            CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT);
            INSERT INTO treneri VALUES(7,1),(8,1);
            CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT);
            INSERT INTO staff_user_positions VALUES(7,'registrar');
            CREATE TABLE staff_superadmins(trainer_id INT);
            CREATE TABLE payments(id INT PRIMARY KEY,amount INT);
            INSERT INTO payments VALUES(1,123)");
        $this->fixture = json_decode(file_get_contents(dirname(__DIR__, 2)
            . '/docs/verification/csc-sportsoft-s0-20261107/fixtures.json'), true, 32, JSON_THROW_ON_ERROR);
        $this->configure('csc_licenses');
    }

    protected function tearDown(): void
    {
        if ($this->server !== null && $this->database !== null) {
            // Generated here, never an application database supplied by configuration.
            $this->server->exec('DROP DATABASE `' . $this->database . '`');
        }
    }

    private function configure(string $collection): void
    {
        $this->token = hash('sha256', 'LOCALHOST-NEPLATIT-' . $collection);
        $provider = $collection === 'csc_licenses' ? 'csc' : 'sportsoft';
        \externalSyncQuery(
            $this->pdo,
            'INSERT INTO external_sync_connections(id,provider,scope_id,collection,event_id,enabled) VALUES(?,?,?,?,?,1)',
            [$collection, $provider, 'synthetic-club', $collection, $collection === 'sportsoft_registrations' ? 'synthetic-event-a' : null]
        );
        \externalSyncQuery(
            $this->pdo,
            'INSERT INTO external_sync_devices(id,connection_id,token_hash,enabled) VALUES(?,?,?,1)',
            [$collection, $collection, hash('sha256', $this->token)]
        );
    }

    private function call(string $action, array $data = [], ?string $raw = null): array
    {
        return \externalSyncDispatch($this->pdo, $this->token, $action, $raw ?? ($data === [] ? '{}' : \externalSyncJson($data)));
    }

    private function batch(array $job, int $part = 1, int $total = 1, int $page = 1): array
    {
        $batch = array_values(array_filter($this->fixture['valid_batches'], static fn (array $b): bool => $b['collection'] === $job['collection']))[0];
        foreach (['run_id', 'connection_id', 'collection', 'scope_id', 'event_id', 'lease_generation'] as $field) {
            $batch[$field] = $job[$field];
        }
        $batch['observed_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $batch['part_no'] = $part;
        $batch['page'] = ['index' => $page, 'row_offset' => 0, 'reported_total' => $total, 'row_count' => 1];
        return $batch;
    }

    private function manifest(array $job, array $receipts, int $count = 1, int $pages = 1): array
    {
        return ['run_id' => $job['run_id'], 'lease_generation' => $job['lease_generation'],
            'parts' => array_map(static fn (array $r): array => ['part_no' => $r['part_no'], 'sha256' => $r['sha256']], $receipts),
            'page_count' => $pages, 'row_count' => $count, 'end_reached' => true, 'filters_clear' => true];
    }

    private function assertRejected(callable $work, string $reason): void
    {
        try {
            $work();
            self::fail('Expected rejection ' . $reason);
        } catch (\ExternalSyncException $error) {
            self::assertSame($reason, $error->getMessage());
        }
        self::assertFalse($this->pdo->inTransaction());
    }

    private function published(): array
    {
        $job = $this->call('claim');
        $receipt = $this->call('upload_part', $this->batch($job));
        $manifest = $this->manifest($job, [$receipt]);
        self::assertSame('review_required', $this->call('complete', $manifest)['status']);
        \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7);
        return [$job, $manifest];
    }

    public function testAllCollectionsStageThenPublishWithNoCanonicalWrites(): void
    {
        $before = $this->pdo->query('SELECT * FROM sportovci')->fetchAll(PDO::FETCH_ASSOC);
        foreach (['csc_licenses', 'sportsoft_members', 'sportsoft_events', 'sportsoft_registrations'] as $collection) {
            if ($collection !== 'csc_licenses') {
                $this->configure($collection);
            }
            $job = $this->call('claim');
            self::assertSame($job, $this->call('claim'));
            $batch = $this->batch($job);
            $receipt = $this->call('upload_part', $batch);
            self::assertTrue($this->call('upload_part', $batch)['duplicate']);
            self::assertNull(\externalSyncReadSnapshot($this->pdo, $collection, 7)['run_id']);
            $manifest = $this->manifest($job, [$receipt]);
            self::assertSame('review_required', $this->call('complete', $manifest)['status']);
            $proposal = \externalSyncReadReview($this->pdo, $job['run_id'], 7);
            self::assertCount(1, $proposal['records']);
            self::assertFalse($proposal['superseded']);
            self::assertNull(\externalSyncReadSnapshot($this->pdo, $collection, 7)['run_id']);
            self::assertTrue($this->call('complete', $manifest)['duplicate']);
            try {
                \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 8);
                self::fail('Nonregistrar approved');
            } catch (\DomainException) {
                self::assertFalse($this->pdo->inTransaction());
            }
            \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7);
            $snapshot = \externalSyncReadSnapshot($this->pdo, $collection, 7);
            self::assertSame($job['run_id'], $snapshot['run_id']);
            self::assertSame($collection === 'sportsoft_events' ? 'not_applicable' : 'matched', $snapshot['records'][0]['match']['status']);
            self::assertTrue($this->call('complete', $manifest)['duplicate']);
        }
        self::assertSame($before, $this->pdo->query('SELECT * FROM sportovci')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame(123, (int)$this->pdo->query('SELECT amount FROM payments')->fetchColumn());
        self::assertSame(3, (int)$this->pdo->query('SELECT COUNT(*) FROM external_identity_links')->fetchColumn());
    }

    public function testPartConflictUnknownFieldsScopeAndSchemaAreRejectedAtomically(): void
    {
        $job = $this->call('claim');
        $batch = $this->batch($job);
        $this->call('upload_part', $batch);
        $different = $batch;
        $different['rows'][0]['validity_year_raw'] = '2025';
        $this->assertRejected(fn () => $this->call('upload_part', $different), 'part_content_conflict');
        $bad = $batch;
        $bad['rows'][0]['birth_number'] = 'FORBIDDEN';
        $this->assertRejected(fn () => $this->call('upload_part', $bad), 'unknown_or_missing_field');
        $bad = $batch;
        $bad['scope_id'] = 'other-club';
        $this->assertRejected(fn () => $this->call('upload_part', $bad), 'batch_scope_denied');
        $bad = $batch;
        $bad['rows'][0]['uci_id_raw'] = 1;
        $this->assertRejected(fn () => $this->call('upload_part', $bad), 'invalid_text');
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_parts')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_records')->fetchColumn());
    }

    public function testMissingPageHashAndTotalCannotPublish(): void
    {
        $job = $this->call('claim');
        $first = $this->call('upload_part', $this->batch($job, 1, 2));
        $this->assertRejected(fn () => $this->call('complete', $this->manifest($job, [$first])), 'incomplete_rows');
        $secondBatch = $this->batch($job, 2, 2, 3);
        $secondBatch['rows'][0]['license_id'] = 'synthetic-b';
        $second = $this->call('upload_part', $secondBatch);
        $this->assertRejected(fn () => $this->call('complete', $this->manifest($job, [$first, $second], 2, 3)), 'page_gap_or_overlap');
        $manifest = $this->manifest($job, [$first]);
        $manifest['parts'][0]['sha256'] = str_repeat('0', 64);
        $this->assertRejected(fn () => $this->call('complete', $manifest), 'missing_parts');
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_records')->fetchColumn());
    }

    public function testMultiPageLicencesAndLaterCanonicalConflictAreSafe(): void
    {
        $job = $this->call('claim');
        $a = $this->call('upload_part', $this->batch($job, 1, 2));
        $batch = $this->batch($job, 2, 2, 2);
        $batch['rows'][0]['license_id'] = 'synthetic-b';
        $b = $this->call('upload_part', $batch);
        $this->call('complete', $this->manifest($job, [$a, $b], 2, 2));
        \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7);
        self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM external_identity_links')->fetchColumn());
        $this->pdo->exec("UPDATE sportovci SET uciid='00000000002' WHERE id=1");
        $snapshot = \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7);
        foreach ($snapshot['records'] as $record) {
            self::assertSame('identity_conflict', $record['match']['status']);
            self::assertNull($record['match']['sportovec_id']);
            self::assertArrayNotHasKey('sportovec_id', $record);
        }
    }

    public function testExpiredLeaseRevocationAndStaleManifestCannotReplaceSnapshot(): void
    {
        [$old, $oldManifest] = $this->published();
        $job = $this->call('claim');
        $receipt = $this->call('upload_part', $this->batch($job));
        $this->pdo->exec('UPDATE external_sync_runs SET lease_until=0 WHERE status=\'collecting\'');
        $this->assertRejected(fn () => $this->call('complete', $this->manifest($job, [$receipt])), 'stale_lease');
        $next = $this->call('claim');
        self::assertNotSame($job['run_id'], $next['run_id']);
        $receipt = $this->call('upload_part', $this->batch($next));
        self::assertSame('complete', $this->call('complete', $this->manifest($next, [$receipt]))['status']);
        self::assertTrue($this->call('complete', $oldManifest)['duplicate']);
        self::assertSame($next['run_id'], \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['run_id']);
        $this->pdo->exec('UPDATE external_sync_devices SET enabled=0');
        $this->assertRejected(fn () => $this->call('claim'), 'unauthorized');
    }

    public function testEmptyDropStaysInReviewAndEarlierApprovedSnapshotRemains(): void
    {
        [$first] = $this->published();
        $job = $this->call('claim');
        $empty = $this->batch($job);
        $empty['rows'] = [];
        $empty['page']['row_count'] = 0;
        $empty['page']['reported_total'] = 0;
        $receipt = $this->call('upload_part', $empty);
        self::assertSame('review_required', $this->call('complete', $this->manifest($job, [$receipt], 0))['status']);
        self::assertSame($first['run_id'], \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['run_id']);
        $this->call('claim');
        $this->assertRejected(fn () => \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7), 'review_no_longer_current');
    }

    public function testDuplicateCanonicalUciAndSourceConflictsNeverAutoLink(): void
    {
        $this->pdo->exec("INSERT INTO sportovci VALUES(2,'TEST Syntetický','A','2000-01-02','000 000 000 01')");
        [$job] = $this->published();
        self::assertSame('duplicate_kis_uci', \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['records'][0]['match']['status']);
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM external_identity_links')->fetchColumn());
        self::assertSame('[1,2]', $this->pdo->query('SELECT candidate_ids_json FROM external_sync_reviews')->fetchColumn());
        $this->pdo->exec('DELETE FROM sportovci WHERE id=2');
        $next = $this->call('claim');
        $batch = $this->batch($next);
        $batch['rows'][] = $batch['rows'][0];
        $batch['rows'][1]['license_id'] = 'synthetic-b';
        $batch['rows'][1]['display_name'] = 'OTHER TEST';
        $batch['page']['row_count'] = $batch['page']['reported_total'] = 2;
        $receipt = $this->call('upload_part', $batch);
        $this->call('complete', $this->manifest($next, [$receipt], 2));
        $rows = \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['records'];
        self::assertSame(['identity_conflict', 'identity_conflict'], array_column(array_column($rows, 'match'), 'status'));
    }

    public function testTransactionalFailureRollsBackPublicationAndCanRetry(): void
    {
        [$first] = $this->published();
        $next = $this->call('claim');
        $receipt = $this->call('upload_part', $this->batch($next));
        $manifest = $this->manifest($next, [$receipt]);
        $this->pdo->exec($this->server === null
            ? "CREATE TRIGGER fail_publish BEFORE UPDATE ON external_sync_connections
                WHEN NEW.latest_run_id <> OLD.latest_run_id BEGIN SELECT RAISE(ABORT,'synthetic failure'); END"
            : "CREATE TRIGGER fail_publish BEFORE UPDATE ON external_sync_connections FOR EACH ROW
                BEGIN IF NEW.latest_run_id <> OLD.latest_run_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'; END IF; END");
        try {
            $this->call('complete', $manifest);
            self::fail('Expected simulated storage failure');
        } catch (\PDOException) {
            self::assertFalse($this->pdo->inTransaction());
        }
        self::assertSame($first['run_id'], \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['run_id']);
        self::assertSame(0, (int)\externalSyncQuery($this->pdo, 'SELECT COUNT(*) FROM external_sync_records WHERE run_id=?', [$next['run_id']])->fetchColumn());
        $this->pdo->exec('DROP TRIGGER fail_publish');
        self::assertSame('complete', $this->call('complete', $manifest)['status']);
    }

    public function testMissingUciDoesNotFallBackToNameAndBirth(): void
    {
        $this->configure('sportsoft_members');
        $job = $this->call('claim');
        $batch = $this->batch($job);
        $batch['rows'][0]['uci_id_raw'] = null;
        $receipt = $this->call('upload_part', $batch);
        $this->call('complete', $this->manifest($job, [$receipt]));
        \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7);
        $record = \externalSyncReadSnapshot($this->pdo, 'sportsoft_members', 7)['records'][0];
        self::assertSame('missing_uci', $record['match']['status']);
        self::assertNull($record['match']['sportovec_id']);
    }

    public function testManifestHashLeaseScopeAndRevokedApproval(): void
    {
        $job = $this->call('claim');
        $batch = $this->batch($job);
        $batch['lease_generation']++;
        $this->assertRejected(fn () => $this->call('upload_part', $batch), 'stale_lease');
        $receipt = $this->call('upload_part', $this->batch($job));
        $manifest = $this->manifest($job, [$receipt]);
        $bad = $manifest;
        $bad['parts'][0]['sha256'] = str_repeat('0', 64);
        $this->assertRejected(fn () => $this->call('complete', $bad), 'manifest_hash_mismatch');
        $originalToken = $this->token;
        $this->configure('sportsoft_members');
        $this->assertRejected(fn () => $this->call('complete', $manifest), 'run_scope_denied');
        $this->token = $originalToken;
        $this->call('complete', $manifest);
        $this->pdo->exec("UPDATE external_sync_devices SET enabled=0 WHERE id='csc_licenses'");
        $this->assertRejected(fn () => \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7), 'review_no_longer_current');
        self::assertNull(\externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['run_id']);
    }

    public function testSplitPageAndDuplicateCompositePreserveBothRows(): void
    {
        $this->configure('sportsoft_members');
        $job = $this->call('claim');
        $first = $this->call('upload_part', $this->batch($job, 1, 2));
        $batch = $this->batch($job, 2, 2);
        $batch['page']['row_offset'] = 1;
        $second = $this->call('upload_part', $batch);
        $this->call('complete', $this->manifest($job, [$first, $second], 2));
        \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7);
        $rows = \externalSyncReadSnapshot($this->pdo, 'sportsoft_members', 7)['records'];
        self::assertCount(2, $rows);
        self::assertSame(['duplicate_source_key', 'duplicate_source_key'], array_column(array_column($rows, 'match'), 'status'));
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM external_identity_links')->fetchColumn());
    }

    public function testClaimRateHeartbeatAndFailureKeepLastSnapshot(): void
    {
        [$first] = $this->published();
        for ($i = 1; $i < 10; $i++) {
            $job = $this->call('claim');
            $request = ['run_id' => $job['run_id'], 'lease_generation' => $job['lease_generation']];
            self::assertGreaterThan(time(), $this->call('heartbeat', $request)['lease_until']);
            self::assertSame('failed', $this->call('fail', $request + ['failure_code' => 'auth_required'])['status']);
        }
        $this->assertRejected(fn () => $this->call('claim'), 'claim_rate_limited');
        self::assertSame($first['run_id'], \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['run_id']);
    }

    public function testConcurrentRevocationFencesWaitingDevice(): void
    {
        if ($this->server === null) {
            self::markTestSkipped('Requires two MariaDB connections; run in the 10.3/11.4 matrix.');
        }
        $code = <<<'PHP'
            require $argv[1];
            $pdo = new PDO($argv[2], 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET innodb_lock_wait_timeout=5');
            echo "ready\n"; flush();
            try { externalSyncDispatch($pdo, $argv[3], 'claim', '{}'); echo 'unexpected'; }
            catch (ExternalSyncException $e) { echo $e->getMessage(); }
            PHP;
        $host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
        $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $this->database . ';charset=utf8mb4';
        $this->pdo->beginTransaction();
        $this->pdo->query('SELECT id FROM external_sync_connections FOR UPDATE')->fetchAll();
        $process = proc_open(
            [PHP_BINARY, '-r', $code, dirname(__DIR__, 2) . '/includes/external_sync_ingest.php', $dsn, $this->token],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        try {
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 10);
            self::assertSame("ready\n", fgets($pipes[1]));
            $waiting = false;
            for ($i = 0; $i < 100; $i++) {
                $query = $this->server->prepare('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w
                    JOIN information_schema.INNODB_TRX t ON t.trx_id=w.blocking_trx_id WHERE t.trx_mysql_thread_id=?');
                $query->execute([(int)$this->pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
                if ((int)$query->fetchColumn() > 0) {
                    $waiting = true;
                    break;
                }
                usleep(20000);
            }
            self::assertTrue($waiting, 'Device must actually wait on the connection lock');
            $this->pdo->exec('UPDATE external_sync_devices SET enabled=0');
            $this->pdo->commit();
            self::assertSame('unauthorized', stream_get_contents($pipes[1]));
            self::assertSame('', stream_get_contents($pipes[2]));
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM external_sync_runs')->fetchColumn());
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    public function testDropThresholdDuplicateLicenseAndReadAuthorization(): void
    {
        foreach ([5, 4, 3] as $count) {
            $job = $this->call('claim');
            $batch = $this->batch($job);
            $template = $batch['rows'][0];
            $batch['rows'] = [];
            for ($i = 0; $i < $count; $i++) {
                $row = $template;
                $row['license_id'] = 'synthetic-license-' . $i;
                $batch['rows'][] = $row;
            }
            $batch['page']['row_count'] = $batch['page']['reported_total'] = $count;
            $receipt = $this->call('upload_part', $batch);
            self::assertSame(
                $count === 4 ? 'complete' : 'review_required',
                $this->call('complete', $this->manifest($job, [$receipt], $count))['status']
            );
            if ($count === 5) {
                \externalSyncApproveSnapshot($this->pdo, $job['run_id'], 7);
            }
        }
        self::assertCount(4, \externalSyncReadSnapshot($this->pdo, 'csc_licenses', 7)['records']);
        self::assertCount(3, \externalSyncReadReview($this->pdo, $job['run_id'], 7)['records']);
        $this->pdo->exec('DELETE FROM staff_user_positions');
        foreach (['externalSyncReadSnapshot' => 'csc_licenses', 'externalSyncReadReview' => $job['run_id']] as $function => $id) {
            try {
                $function($this->pdo, $id, 7);
                self::fail('Revoked registrar can read private records');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
        }
        $next = $this->call('claim');
        $batch = $this->batch($next);
        $batch['rows'][] = $batch['rows'][0];
        $batch['page']['row_count'] = $batch['page']['reported_total'] = 2;
        $receipt = $this->call('upload_part', $batch);
        $this->assertRejected(fn () => $this->call('complete', $this->manifest($next, [$receipt], 2)), 'duplicate_license_id');
    }
}
