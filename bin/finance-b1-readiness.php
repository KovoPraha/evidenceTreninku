<?php
declare(strict_types=1);

/** Opens every present, explicitly owned table; SELECT DATABASE()/a socket is insufficient. */
function financeB1TableReadiness(PDO $pdo, array $ownedTables, array $optionalLegacyTables = []): array
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $present = $mysql
        ? $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN)
        : $pdo->query("SELECT name FROM sqlite_master WHERE type IN ('table','view')")->fetchAll(PDO::FETCH_COLUMN);
    $failed = [];
    $checked = 0;
    $missing = [];
    $missingRequired = [];
    foreach (array_unique($ownedTables) as $table) {
        if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]*$/D', $table)) throw new InvalidArgumentException('Invalid ownership contract.');
        if (!in_array($table, $present, true)) { $missing[] = $table; if (!in_array($table, $optionalLegacyTables, true)) $missingRequired[] = $table; continue; }
        try {
            // COUNT(*) forces engine access even for an empty table (unlike LIMIT 0).
            $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            ++$checked;
        } catch (PDOException $error) {
            $failed[] = ['table'=>$table,'sqlstate'=>(string)$error->getCode(),'driver_code'=>(int)($error->errorInfo[1]??0)];
        }
    }
    return ['status'=>$failed || $missingRequired || $checked === 0 ? 'FAIL' : 'PASS','readable_owned_tables'=>$checked,'unreadable_tables'=>$failed,'missing_required_tables'=>$missingRequired,'absent_contract_tables'=>$missing];
}

function financeB1OwnedTables(string $appRoot): array
{
    $source = (string)file_get_contents($appRoot . '/bin/db-backup.php');
    if (!preg_match('/const EVIDENCE_TABLES\s*=\s*\[(.*?)\];/s', $source, $match)) throw new RuntimeException('Missing ownership contract.');
    preg_match_all("/'([a-z][a-z0-9_]*)'/", $match[1], $names);
    if (count($names[1]) < 100) throw new RuntimeException('Incomplete ownership contract.');
    return array_values(array_unique($names[1]));
}
