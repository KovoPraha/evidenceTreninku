<?php

declare(strict_types=1);

return [
    'id' => '20261107210000_external_read_sync',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $text = $mysql ? 'LONGTEXT' : 'TEXT';
        // Binary collation: source identifiers and hashes must never compare accent/case-insensitively.
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin' : '';
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_connections (
            id VARCHAR(80) PRIMARY KEY, provider VARCHAR(20) NOT NULL, scope_id VARCHAR(80) NOT NULL,
            collection VARCHAR(40) NOT NULL, event_id VARCHAR(80) NULL, enabled INT NOT NULL DEFAULT 0,
            next_sequence BIGINT NOT NULL DEFAULT 0, active_run_id VARCHAR(36) NULL,
            latest_run_id VARCHAR(36) NULL, latest_sequence BIGINT NOT NULL DEFAULT 0,
            latest_row_count INT NOT NULL DEFAULT 0
        )' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_devices (
            id VARCHAR(80) PRIMARY KEY, connection_id VARCHAR(80) NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE, enabled INT NOT NULL DEFAULT 0,
            last_seen_at BIGINT NULL,
            FOREIGN KEY(connection_id) REFERENCES external_sync_connections(id)
        )' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_runs (
            id VARCHAR(36) PRIMARY KEY, connection_id VARCHAR(80) NOT NULL, device_id VARCHAR(80) NOT NULL,
            sequence_no BIGINT NOT NULL, lease_generation INT NOT NULL, lease_until BIGINT NOT NULL,
            started_at BIGINT NOT NULL, finished_at BIGINT NULL, status VARCHAR(40) NOT NULL,
            row_count INT NOT NULL DEFAULT 0, manifest_hash VARCHAR(64) NULL, failure_code VARCHAR(40) NULL,
            UNIQUE(connection_id,sequence_no),
            FOREIGN KEY(connection_id) REFERENCES external_sync_connections(id),
            FOREIGN KEY(device_id) REFERENCES external_sync_devices(id)
        )' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_parts (
            run_id VARCHAR(36) NOT NULL, part_no INT NOT NULL, request_hash VARCHAR(64) NOT NULL,
            payload_json ' . $text . ' NOT NULL, payload_bytes INT NOT NULL,
            PRIMARY KEY(run_id,part_no), FOREIGN KEY(run_id) REFERENCES external_sync_runs(id)
        )' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_records (
            id ' . $id . ', run_id VARCHAR(36) NOT NULL, ordinal_no INT NOT NULL,
            entity_key VARCHAR(64) NULL, uci_id VARCHAR(11) NULL, row_json TEXT NOT NULL,
            normalized_json TEXT NOT NULL, match_status VARCHAR(40) NOT NULL,
            sportovec_id INT NULL, UNIQUE(run_id,ordinal_no),
            FOREIGN KEY(run_id) REFERENCES external_sync_runs(id)
        )' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_identity_links (
            connection_id VARCHAR(80) NOT NULL, entity_key VARCHAR(64) NOT NULL,
            uci_id VARCHAR(11) NOT NULL, sportovec_id INT NOT NULL, status VARCHAR(40) NOT NULL,
            run_id VARCHAR(36) NOT NULL, revision INT NOT NULL DEFAULT 1,
            PRIMARY KEY(connection_id,entity_key),
            FOREIGN KEY(connection_id) REFERENCES external_sync_connections(id),
            FOREIGN KEY(run_id) REFERENCES external_sync_runs(id)
        )' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_reviews (
            id ' . $id . ', run_id VARCHAR(36) NOT NULL, ordinal_no INT NOT NULL,
            reason_code VARCHAR(40) NOT NULL, candidate_ids_json TEXT NOT NULL,
            UNIQUE(run_id,ordinal_no), FOREIGN KEY(run_id) REFERENCES external_sync_runs(id)
        )' . $tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_audit (
            id ' . $id . ', connection_id VARCHAR(80) NOT NULL, run_id VARCHAR(36) NULL,
            actor_kind VARCHAR(10) NOT NULL, actor_id VARCHAR(80) NOT NULL,
            action_code VARCHAR(40) NOT NULL, recorded_at BIGINT NOT NULL,
            FOREIGN KEY(connection_id) REFERENCES external_sync_connections(id)
        )' . $tail);
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $columns = [
                'external_sync_connections' => 'id,provider,scope_id,collection,event_id,enabled,next_sequence,active_run_id,latest_run_id,latest_sequence,latest_row_count',
                'external_sync_devices' => 'id,connection_id,token_hash,enabled,last_seen_at',
                'external_sync_runs' => 'id,connection_id,device_id,sequence_no,lease_generation,lease_until,started_at,finished_at,status,row_count,manifest_hash,failure_code',
                'external_sync_parts' => 'run_id,part_no,request_hash,payload_json,payload_bytes',
                'external_sync_records' => 'id,run_id,ordinal_no,entity_key,uci_id,row_json,normalized_json,match_status,sportovec_id',
                'external_identity_links' => 'connection_id,entity_key,uci_id,sportovec_id,status,run_id,revision',
                'external_sync_reviews' => 'id,run_id,ordinal_no,reason_code,candidate_ids_json',
                'external_sync_audit' => 'id,connection_id,run_id,actor_kind,actor_id,action_code,recorded_at',
            ];
            foreach ($columns as $table => $list) {
                $pdo->query('SELECT ' . $list . ' FROM ' . $table . ' LIMIT 0');
            }
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
