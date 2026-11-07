<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExternalSyncRegistrarFixture as Fixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/external_sync_admin_view.php';

final class ExternalSyncRegistrarTest extends TestCase
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

    private function read(array $query = []): array
    {
        return \externalSyncAdminDetail($this->pdo, 7, \externalSyncAdminInput(['connection' => 'test-csc'] + $query));
    }

    public function testOverviewSeparatesAcceptedSnapshotFromFailedOrPendingAttempts(): void
    {
        self::assertNull($this->read()['run']);
        $row = Fixture::batch()['rows'][0];
        $accepted = Fixture::ingest($this->pdo, $this->token, [$row]);
        $job = \externalSyncDispatch($this->pdo, $this->token, 'claim', '{}');
        \externalSyncDispatch($this->pdo, $this->token, 'fail', \externalSyncJson([
            'run_id' => $job['run_id'], 'lease_generation' => $job['lease_generation'], 'failure_code' => 'auth_required',
        ]));
        $before = Fixture::state($this->pdo);
        $overview = \externalSyncAdminOverview($this->pdo, 7)['rows'][0];
        self::assertSame($accepted, $overview['latest_run_id']);
        self::assertSame('failed', $overview['attempt_status']);
        self::assertSame('auth_required', $overview['failure_code']);
        self::assertSame($accepted, $this->read()['run']['id']);
        self::assertNull($this->read(['run' => $job['run_id']])['projection']);
        self::assertSame($before, Fixture::state($this->pdo));
        $pending = Fixture::ingest($this->pdo, $this->token, [], false);
        self::assertSame($accepted, $this->read()['run']['id']);
        $review = $this->read(['run' => $pending]);
        self::assertSame('review_required', $review['run']['status']);
        self::assertSame(0, $review['projection']['total']);
    }

    public function testPaginationWholeSourceConflictsAndConstantSelectCount(): void
    {
        $base = Fixture::batch()['rows'][0];
        $rows = [];
        for ($i = 0; $i < 301; $i++) {
            $rows[] = array_replace($base, ['license_id' => 'TEST-' . $i, 'uci_id_raw' => sprintf('%011d', $i + 100)]);
        }
        $rows[300]['uci_id_raw'] = $rows[0]['uci_id_raw'];
        $rows[300]['display_name'] = 'TEST Jiná identita';
        Fixture::ingest($this->pdo, $this->token, $rows);
        $before = Fixture::state($this->pdo);
        $this->pdo->profiling = true;
        $this->pdo->profile = [];
        $first = $this->read(['filter' => 'all'])['projection'];
        $queries = count($this->pdo->profile);
        self::assertLessThanOrEqual(10, $queries);
        self::assertCount(50, $first['rows']);
        self::assertSame(301, $first['total']);
        self::assertSame('identity_conflict', $first['rows'][0]['match']['status']);
        self::assertNotNull($first['rows'][0]['observed_at']);
        self::assertTrue($first['has_next']);
        $this->pdo->profile = [];
        $last = $this->read(['filter' => 'all', 'page' => '7'])['projection'];
        self::assertSame($queries, count($this->pdo->profile));
        self::assertCount(1, $last['rows']);
        self::assertSame(301, $last['rows'][0]['ordinal']);
        self::assertFalse($last['has_next']);
        foreach ($this->pdo->profile as $query) {
            self::assertMatchesRegularExpression('/^SELECT /', $query['sql']);
        }
        self::assertCount(2, $this->read(['filter' => 'identity_conflict'])['projection']['rows']);
        self::assertSame([], $this->read(['page' => '999999'])['projection']['rows']);
        self::assertSame($before, Fixture::state($this->pdo));
    }

    public function testCurrentUciRevocationAndDuplicatesNeverExposeStoredPerson(): void
    {
        Fixture::ingest($this->pdo, $this->token, Fixture::batch()['rows']);
        self::assertSame(1, $this->read(['filter' => 'all'])['projection']['rows'][0]['match']['sportovec_id']);
        $this->pdo->exec("INSERT INTO sportovci VALUES(2,'TEST Syntetický','A','2000-01-02','00000000001')");
        $match = $this->read()['projection']['rows'][0]['match'];
        self::assertSame('duplicate_kis_uci', $match['status']);
        self::assertNull($match['sportovec_id']);
        self::assertSame([1, 2], $match['candidate_ids']);
        $this->pdo->exec("UPDATE sportovci SET uciid='00000000002' WHERE id=1");
        $match = $this->read()['projection']['rows'][0]['match'];
        self::assertSame('identity_conflict', $match['status']);
        self::assertNull($match['sportovec_id']);
        self::assertSame([2], $match['candidate_ids']);
    }

    public function testFourCollectionsReviewFiltersEscapingAndObservationTime(): void
    {
        foreach (['csc_licenses', 'sportsoft_members', 'sportsoft_events', 'sportsoft_registrations'] as $collection) {
            $token = Fixture::connection($this->pdo, $collection, $collection);
            $rows = Fixture::batch($collection)['rows'];
            $field = $collection === 'sportsoft_events' ? 'event_name' : 'display_name';
            $rows[0][$field] = '<img src=x onerror=alert(1)> TEST';
            if ($collection === 'sportsoft_registrations') {
                $rows[0]['paid_raw'] = 'ne';
            }
            $run = Fixture::ingest($this->pdo, $token, $rows, false);
            $input = \externalSyncAdminInput(['connection' => $collection, 'run' => $run, 'filter' => 'all']);
            $before = Fixture::state($this->pdo);
            $detail = \externalSyncAdminDetail($this->pdo, 7, $input);
            ob_start();
            \externalSyncAdminRender(\externalSyncAdminOverview($this->pdo, 7), $detail, $input, false);
            $html = ob_get_clean();
            self::assertStringNotContainsString('<img src=x', $html);
            self::assertStringContainsString('&lt;img src=x', $html);
            self::assertStringContainsString('Návrh čekající na kontrolu', $html);
            self::assertStringContainsString('Pozorováno ve zdroji:', $html);
            self::assertStringNotContainsString('token_hash', $html);
            self::assertStringNotContainsString('method="post"', $html);
            if ($collection === 'sportsoft_registrations') {
                self::assertStringContainsString('Platba dle kontraktu: neznámá', $html);
            }
            self::assertSame($before, Fixture::state($this->pdo));
        }
    }

    public function testRolesRevocationAndRunScopeFailClosed(): void
    {
        foreach ([0, 8, 9] as $actor) {
            foreach (['overview', 'detail'] as $operation) {
                try {
                    $operation === 'overview' ? \externalSyncAdminOverview($this->pdo, $actor)
                        : \externalSyncAdminDetail($this->pdo, $actor, \externalSyncAdminInput(['connection' => 'test-csc']));
                    self::fail('Nonregistrar read');
                } catch (\DomainException) {
                    self::assertFalse($this->pdo->inTransaction());
                }
            }
        }
        $token = Fixture::connection($this->pdo, 'other');
        $run = Fixture::ingest($this->pdo, $token, Fixture::batch()['rows']);
        try {
            $this->read(['run' => $run]);
            self::fail('Cross-scope run read');
        } catch (\ExternalSyncException $error) {
            self::assertSame(404, $error->getCode());
            self::assertFalse($this->pdo->inTransaction());
        }
        $this->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');
        $this->expectException(\DomainException::class);
        $this->read();
    }

    public function testInvalidQueryParametersAreRejected(): void
    {
        foreach ([['page' => []], ['page' => '-1'], ['page' => '1 OR 1=1'], ['filter' => 'unexpected'], ['run' => ['x']]] as $query) {
            try {
                \externalSyncAdminInput($query);
                self::fail('Invalid parameter accepted');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
