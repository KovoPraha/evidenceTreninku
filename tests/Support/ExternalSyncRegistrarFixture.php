<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

require_once dirname(__DIR__, 2) . '/includes/external_sync_admin_read.php';

/** Owned in-memory/random test database only. No application configuration. */
final class ExternalSyncRegistrarFixture
{
    public static function schema(PDO $pdo): void
    {
        $migration = require dirname(__DIR__, 2) . '/migrations/20261107210000_external_read_sync.php';
        $migration['up']($pdo);
        $pdo->exec("CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT);
            INSERT INTO sportovci VALUES(1,'TEST Syntetický','A','2000-01-02','00000000001');
            CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT);
            INSERT INTO treneri VALUES(7,1),(8,1),(9,0);
            CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT);
            INSERT INTO staff_user_positions VALUES(7,'registrar'),(9,'registrar');
            CREATE TABLE staff_superadmins(trainer_id INT)");
    }

    public static function batch(string $collection = 'csc_licenses'): array
    {
        $fixtures = json_decode(file_get_contents(dirname(__DIR__, 2)
            . '/docs/verification/csc-sportsoft-s0-20261107/fixtures.json'), true, 32, JSON_THROW_ON_ERROR);
        return array_values(array_filter($fixtures['valid_batches'], static fn (array $b): bool => $b['collection'] === $collection))[0];
    }

    public static function connection(PDO $pdo, string $id, string $collection = 'csc_licenses'): string
    {
        $token = hash('sha256', 'LOCALHOST-NEPLATIT-S4-' . $id);
        \externalSyncQuery(
            $pdo,
            'INSERT INTO external_sync_connections(id,provider,scope_id,collection,event_id,enabled) VALUES(?,?,?,?,?,1)',
            [$id, $collection === 'csc_licenses' ? 'csc' : 'sportsoft', 'TEST-club-' . $id, $collection,
                $collection === 'sportsoft_registrations' ? 'TEST-event' : null]
        );
        \externalSyncQuery($pdo, 'INSERT INTO external_sync_devices(id,connection_id,token_hash,enabled) VALUES(?,?,?,1)', [$id, $id, hash('sha256', $token)]);
        return $token;
    }

    /** Goes through S1's real claim/upload/complete/publish, including real manifests. */
    public static function ingest(PDO $pdo, string $token, array $rows, bool $approve = true): string
    {
        $job = \externalSyncDispatch($pdo, $token, 'claim', '{}');
        $batch = self::batch($job['collection']);
        foreach (['run_id', 'connection_id', 'scope_id', 'event_id', 'lease_generation'] as $key) {
            $batch[$key] = $job[$key];
        }
        $batch['observed_at'] = gmdate('Y-m-d\TH:i:s\Z', time() - 30);
        $parts = [];
        $chunks = $rows === [] ? [[]] : array_chunk($rows, 250);
        foreach ($chunks as $index => $chunk) {
            $batch['part_no'] = $index + 1;
            $batch['page'] = ['index' => $index + 1, 'reported_total' => count($rows), 'row_offset' => 0, 'row_count' => count($chunk)];
            $batch['rows'] = $chunk;
            $receipt = \externalSyncDispatch($pdo, $token, 'upload_part', \externalSyncJson($batch));
            $parts[] = ['part_no' => $receipt['part_no'], 'sha256' => $receipt['sha256']];
        }
        $result = \externalSyncDispatch($pdo, $token, 'complete', \externalSyncJson([
            'run_id' => $job['run_id'], 'lease_generation' => $job['lease_generation'], 'parts' => $parts,
            'page_count' => count($chunks), 'row_count' => count($rows), 'end_reached' => true, 'filters_clear' => true,
        ]));
        if ($approve && $result['status'] === 'review_required') {
            \externalSyncApproveSnapshot($pdo, $job['run_id'], 7);
        }
        return $job['run_id'];
    }

    public static function state(PDO $pdo): string
    {
        $state = [];
        foreach (['sportovci', 'treneri', 'staff_user_positions', 'external_sync_connections', 'external_sync_devices',
            'external_sync_runs', 'external_sync_parts', 'external_sync_records', 'external_identity_links',
            'external_sync_reviews', 'external_sync_audit'] as $table) {
            $rows = $pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
            $encoded = array_map('externalSyncJson', $rows);
            sort($encoded);
            $state[$table] = $encoded;
        }
        return hash('sha256', \externalSyncJson($state));
    }
}
