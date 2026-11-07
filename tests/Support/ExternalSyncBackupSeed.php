<?php

declare(strict_types=1);

/** Nonempty synthetic data in all eight owned tables, including pending review and token hash. */
function externalSyncBackupSeed(PDO $pdo): array
{
    $pdo->exec("INSERT INTO external_sync_connections(id,provider,scope_id,collection,next_sequence,latest_sequence,latest_row_count)
        VALUES('backup-test','csc','synthetic-club','csc_licenses',1,1,1)");
    $pdo->prepare('INSERT INTO external_sync_devices(id,connection_id,token_hash) VALUES(?,?,?)')
        ->execute(['backup-test', 'backup-test', hash('sha256', 'TEST-INACTIVE-BACKUP-DEVICE')]);
    $pdo->exec("INSERT INTO external_sync_runs(id,connection_id,device_id,sequence_no,lease_generation,lease_until,started_at,status,row_count)
        VALUES('00000000-0000-4000-8000-000000000001','backup-test','backup-test',1,1,0,1,'complete',1)");
    $pdo->exec("UPDATE external_sync_connections SET latest_run_id='00000000-0000-4000-8000-000000000001' WHERE id='backup-test'");
    $pdo->prepare('INSERT INTO external_sync_parts(run_id,part_no,request_hash,payload_json,payload_bytes) VALUES(?,1,?,?,2)')
        ->execute(['00000000-0000-4000-8000-000000000001', hash('sha256', '{}'), '{}']);
    $pdo->exec("INSERT INTO external_sync_records(run_id,ordinal_no,part_no,row_index,entity_key,uci_id,row_json,normalized_json,match_status,sportovec_id)
        VALUES('00000000-0000-4000-8000-000000000001',1,1,0,'test-key','00000000001','{}','{}','identity_conflict',NULL)");
    $pdo->exec("INSERT INTO external_identity_links(connection_id,entity_key,uci_id,sportovec_id,status,run_id,revision)
        VALUES('backup-test','test-key','00000000001',987654,'identity_conflict','00000000-0000-4000-8000-000000000001',3)");
    $pdo->exec("INSERT INTO external_sync_reviews(run_id,ordinal_no,reason_code,candidate_ids_json)
        VALUES('00000000-0000-4000-8000-000000000001',1,'identity_conflict','[987654]')");
    $pdo->exec("INSERT INTO external_sync_audit(connection_id,run_id,actor_kind,actor_id,action_code,recorded_at)
        VALUES('backup-test','00000000-0000-4000-8000-000000000001','device','backup-test','complete',1)");
    return externalSyncBackupSnapshot($pdo);
}

function externalSyncBackupSnapshot(PDO $pdo): array
{
    $snapshot = [];
    foreach (['external_sync_connections', 'external_sync_devices', 'external_sync_runs', 'external_sync_parts',
        'external_sync_records', 'external_identity_links', 'external_sync_reviews', 'external_sync_audit'] as $table) {
        $snapshot[$table] = $pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    }
    return $snapshot;
}
