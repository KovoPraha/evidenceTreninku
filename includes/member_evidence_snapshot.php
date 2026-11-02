<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_hierarchy.php';

function memberEvidenceIdentifier(string $name): string
{
    if ($name === '' || str_contains($name, "\0")) {
        throw new InvalidArgumentException('Neplatný databázový identifikátor.');
    }
    // Catalogs also contain legacy identifiers with spaces, dashes or backticks.
    // Quoting, not a naming convention, provides SQL isolation.
    return '`' . str_replace('`', '``', $name) . '`';
}

/** Includes unknown tables: an unowned dependency must not disappear from the audit. */
function memberEvidenceTables(PDO $pdo): array
{
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"
        : "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
    return array_map('strval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
}

/** No personal values in the summary. The full read is also the apply-time lock set. */
function memberEvidenceSnapshot(PDO $pdo, bool $lock = false): array
{
    $snapshot = [];
    foreach (memberEvidenceTables($pdo) as $table) {
        if ($table === 'member_evidence_cutover_runs') {
            continue;
        }
        $query = $pdo->query('SELECT * FROM ' . memberEvidenceIdentifier($table)
            . ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $hashes = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            ksort($row);
            // serialize preserves binary values as well as NULL and native PDO types.
            $hashes[] = hash('sha256', serialize($row));
        }
        sort($hashes, SORT_STRING);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $ddl = $pdo->query('SHOW CREATE TABLE ' . memberEvidenceIdentifier($table))->fetch(PDO::FETCH_NUM);
            $schema = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=0', (string)$ddl[1]);
            if ($lock && !str_contains($schema, 'ENGINE=InnoDB')) {
                throw new RuntimeException('Přechod vyžaduje transakční tabulky InnoDB: ' . $table);
            }
        } else {
            $query = $pdo->prepare('SELECT type,name,sql FROM sqlite_master WHERE tbl_name=? ORDER BY type,name');
            $query->execute([$table]);
            $schema = serialize($query->fetchAll(PDO::FETCH_ASSOC));
        }
        $snapshot[$table] = ['count' => count($hashes),'sha256' => hash('sha256', implode('', $hashes)),

            'schema_sha256' => hash('sha256', $schema)];
    }
    return $snapshot;
}

/** All inbound foreign keys, including SET NULL/CASCADE, are blocking unless explicitly selected. */
function memberEvidenceForeignKeys(PDO $pdo): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return $pdo->query('SELECT TABLE_NAME AS child_table,COLUMN_NAME AS child_column,REFERENCED_TABLE_NAME AS parent_table,'
            . 'REFERENCED_COLUMN_NAME AS parent_column FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')
            ->fetchAll(PDO::FETCH_ASSOC);
    }
    $keys = [];
    foreach (memberEvidenceTables($pdo) as $table) {
        foreach ($pdo->query('PRAGMA foreign_key_list(' . memberEvidenceIdentifier($table) . ')')->fetchAll(PDO::FETCH_ASSOC) as $key) {
            $keys[] = ['child_table' => $table,'child_column' => $key['from'],'parent_table' => $key['table'],

    'parent_column' => $key['to']];
        }
    }
    return $keys;
}

/** Primary records and financial/access ledgers are always preserved by this cutover. */
function memberEvidenceDeletionRows(PDO $pdo, array $input, string $on): array
{
    $allowed = ['club_roster_members','club_roster_events','kis_legacy_roster_claims','kis_current_roster_claims'];
    $rows = [];
    foreach ($input as $item) {
        $table = (string) ($item['table'] ?? '');
        $id = (int) ($item['id'] ?? 0);
        if (!in_array($table, $allowed, true) || $id < 1 || ($item['classification'] ?? '') !== 'imported_history') {
            throw new InvalidArgumentException('Úklid přijímá jen výslovná ID podporovaných historických importních záznamů.');
        }
        kisRosterText((string) ($item['reason'] ?? ''), 1000, 'Důvod odstranění');
        $query = $pdo->prepare('SELECT * FROM ' . memberEvidenceIdentifier($table) . ' WHERE id=?');
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Záznam manifestu neexistuje: ' . $table . '#' . $id);
        }
        $rows[$table][$id] = $row;
    }
    foreach ($rows as $table => $items) {
        foreach ($items as $row) {
            if ($table === 'club_roster_members') {
                if ($row['source'] !== 'kis_shadow' || !$row['valid_to'] || $row['valid_to'] >= $on) {
                    throw new RuntimeException('Aktuální nárok nebo vlastní nové členství nelze odstranit jako starý import.');
                }
            } elseif ($table === 'club_roster_events') {
                if (!in_array($row['action'], ['import_legacy_member','import_current_member'], true)
                    || !isset($rows['club_roster_members'][(int) $row['roster_member_id']])) {
                    throw new RuntimeException('Audit skutečného provozu nebo zachovaného nároku musí zůstat.');
                }
            } elseif (!empty($row['member_id']) && !isset($rows['club_roster_members'][(int) $row['member_id']])) {
                throw new RuntimeException('Importní vazba zachovaného členství vyžaduje samostatné vypořádání.');
            }
        }
    }
    // Only single-column legacy IDs are deleted. Composite references are checked conservatively per column.
    foreach (memberEvidenceForeignKeys($pdo) as $key) {
        foreach ($rows[$key['parent_table']] ?? [] as $row) {
            if (!array_key_exists($key['parent_column'], $row)) {
                throw new RuntimeException('Neúplná referenční metadata.');
            }
            $query = $pdo->prepare('SELECT * FROM ' . memberEvidenceIdentifier($key['child_table'])
                . ' WHERE ' . memberEvidenceIdentifier($key['child_column']) . '=?');
            $query->execute([$row[$key['parent_column']]]);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $child) {
                if (!isset($child['id'], $rows[$key['child_table']][(int) $child['id']])) {
                    throw new RuntimeException('Zachovaná závislost brání odstranění: ' . $key['child_table'] .

     '.' . $key['child_column']);
                }
            }
        }
    }
    return $rows;
}
