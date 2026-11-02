<?php

declare(strict_types=1);

/** Pre-deploy metadata only: no identities, contacts, payment details or writes. */
function diagnoseMemberEvidenceMain(): void
{
    $pdo = null;
    $phase = 'target';
    try {
        $root = realpath((string)getenv('APP_ROOT'));
        if (PHP_SAPI !== 'cli' || getenv('APP_HOST') !== 'kis.kovopraha.cz'
            || $root === false || !is_file($root . '/config.php')) {
            throw new RuntimeException('Unexpected diagnostic target.');
        }
        $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
        $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        $phase = 'configuration';
        require $root . '/config.php';
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
            throw new RuntimeException('Production target required.');
        }
        $phase = 'connection';
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $phase = 'read_only_transaction';
        $pdo->exec('START TRANSACTION READ ONLY');
        $phase = 'table_catalog';
        $tables = $pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll();
        foreach ($tables as &$table) {
            $name = (string)$table['TABLE_NAME'];
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
                throw new RuntimeException('Unsupported table identifier.');
            }
            $phase = 'count:' . $name;
            $table['rows'] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $name . '`')->fetchColumn();
        }
        unset($table);
        $phase = 'foreign_keys';
        $keys = $pdo->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME '
            . 'FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() '
            . 'AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll();
        $phase = 'team_columns';
        $columns = array_column($pdo->query('SHOW COLUMNS FROM club_teams')->fetchAll(), 'Field');
        $activity = in_array('activity_kind', $columns, true) ? 't.activity_kind' : 'NULL AS activity_kind';
        $phase = 'teams';
        $teams = $pdo->query('SELECT t.id,t.name,t.code,t.standing_code,t.team_kind,t.season_id,t.status,' . $activity
            . ',s.season_type FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id ORDER BY t.id')->fetchAll();
        $phase = 'memberships';
        $memberships = $pdo->query('SELECT team_id,source,status,COUNT(*) AS intervals,'
            . 'SUM(valid_from<=CURRENT_DATE AND (valid_to IS NULL OR valid_to>=CURRENT_DATE)) AS current_intervals,'
            . 'SUM(valid_from>CURRENT_DATE) AS future_intervals,'
            . 'SUM(valid_to<CURRENT_DATE) AS expired_intervals FROM club_roster_members GROUP BY team_id,source,status '
            . 'ORDER BY team_id,source,status')->fetchAll();
        $phase = 'charges';
        $charges = $pdo->query('SELECT source_system,status,currency,COUNT(*) AS charges,SUM(amount_minor) AS amount_minor '
            . 'FROM club_member_charges GROUP BY source_system,status,currency ORDER BY source_system,status,currency')->fetchAll();
        $phase = 'offers';
        $offers = $pdo->query('SELECT id,program_id,team_id,status,starts_on,ends_on FROM club_program_offers ORDER BY id')->fetchAll();
        $pdo->exec('ROLLBACK');
        echo json_encode(
            ['contract' => 'member-evidence-predeploy-inventory-v1', 'read_only' => true,
            'generated_at' => gmdate('c'), 'tables' => $tables, 'foreign_keys' => $keys, 'teams' => $teams,
            'memberships' => $memberships, 'charges' => $charges, 'offers' => $offers],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ) . PHP_EOL;
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Never print exception messages: PDO messages can contain credentials or row data.
        $details = ['phase' => $phase, 'type' => get_class($error)];
        if ($error instanceof PDOException) {
            $details['sqlstate'] = $error->errorInfo[0] ?? $error->getCode();
            $details['driver_code'] = $error->errorInfo[1] ?? null;
        }
        fwrite(STDERR, 'Read-only member evidence inventory failed; no changes applied. '
            . json_encode($details, JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(1);
    }
}
