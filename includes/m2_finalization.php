<?php
declare(strict_types=1);

require_once __DIR__ . '/migration_runner.php';

function m2FinalizationTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) return false;
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    return false;
}

/** @return array{status:string,label:string,detail:string} */
function m2FinalizationMigrationCheck(PDO $pdo, string $root): array
{
    $catalog = EvidenceMigrationCatalog::load($root . '/migrations');
    if (!m2FinalizationTableExists($pdo, EvidenceMigrationRunner::LEDGER_TABLE)) {
        return ['status' => 'fail', 'label' => 'Databázové migrace', 'detail' => 'Chybí migrační evidence.'];
    }
    $applied = [];
    foreach ($pdo->query('SELECT id,checksum FROM ' . EvidenceMigrationRunner::LEDGER_TABLE)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $applied[(string)$row['id']] = (string)$row['checksum'];
    }
    unset($applied[EvidenceMigrationRunner::BASELINE_ID]);
    $missing = array_diff_key($catalog, $applied);
    $unknown = array_diff_key($applied, $catalog);
    $changed = [];
    foreach (array_intersect_key($catalog, $applied) as $id => $migration) {
        if (!hash_equals($migration['checksum'], $applied[$id])) $changed[] = $id;
    }
    if ($missing !== [] || $unknown !== [] || $changed !== []) {
        return [
            'status' => 'fail',
            'label' => 'Databázové migrace',
            'detail' => 'Katalog není aktuální: chybí ' . count($missing) . ', neznámé ' . count($unknown) . ', změněné ' . count($changed) . '.',
        ];
    }
    return ['status' => 'pass', 'label' => 'Databázové migrace', 'detail' => count($catalog) . '/' . count($catalog) . ' migrací odpovídá kódu.'];
}

/** @return array{status:string,label:string,detail:string} */
function m2FinalizationDemoCheck(PDO $pdo): array
{
    $tables = ['treneri', 'verejni_uzivatele', 'sportovci', 'account_person_roles', 'child_access_accounts', 'shop_products'];
    $missing = array_values(array_filter($tables, static fn (string $table): bool => !m2FinalizationTableExists($pdo, $table)));
    if ($missing !== []) {
        return ['status' => 'fail', 'label' => 'Testovací identity a data', 'detail' => 'Chybí potřebná databázová struktura.'];
    }

    $admin = (int)$pdo->query("SELECT COUNT(*) FROM treneri WHERE jmeno='localhost-admin' AND aktivni=1")->fetchColumn();
    $parent = (int)$pdo->query("SELECT COUNT(*) FROM verejni_uzivatele WHERE email='rodic@localhost.test' AND aktivni=1 AND email_overeno=1")->fetchColumn();
    $children = (int)$pdo->query(
        "SELECT COUNT(DISTINCT r.sportovec_id) FROM account_person_roles r "
        . "JOIN verejni_uzivatele a ON a.id=r.account_id WHERE a.email='rodic@localhost.test' "
        . "AND r.relation_role='guardian' AND r.status='approved' AND r.valid_to IS NULL"
    )->fetchColumn();
    $childAccess = (int)$pdo->query("SELECT COUNT(*) FROM child_access_accounts WHERE login_key='localhost-sportovec' AND active=1")->fetchColumn();
    $products = (int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE external_product_key LIKE 'local-demo:%' AND catalog_status='active'")->fetchColumn();

    if ($admin < 1 || $parent < 1 || $children < 2 || $childAccess < 1 || $products < 1) {
        return [
            'status' => 'wait',
            'label' => 'Testovací identity a data',
            'detail' => 'Demo není úplné; použijte potvrzené tlačítko Obnovit demo data.',
        ];
    }
    return [
        'status' => 'pass',
        'label' => 'Testovací identity a data',
        'detail' => 'Administrátor, rodič, dvě děti, dětský přístup a demo nabídka jsou připravené.',
    ];
}

/**
 * @param list<array<string,mixed>> $scenarios
 * @param array<string,array<string,mixed>> $feedback
 * @return array{checks:list<array{status:string,label:string,detail:string}>,technical_passed:int,technical_total:int,accepted:int,scenario_total:int,blocking:int,close_ready:bool}
 */
function m2FinalizationStatus(PDO $pdo, string $root, array $scenarios, array $feedback): array
{
    $routesReady = count(array_filter($scenarios, static fn (array $scenario): bool => ($scenario['status'] ?? '') === 'ready'));
    $checks = [[
        'status' => $routesReady === count($scenarios) ? 'pass' : 'fail',
        'label' => 'Cesty scénářů A01–A10',
        'detail' => $routesReady . '/' . count($scenarios) . ' scénářů má dostupné všechny potřebné stránky.',
    ]];
    try {
        $checks[] = m2FinalizationMigrationCheck($pdo, $root);
    } catch (Throwable $exception) {
        error_log('M2 finalization migration check: ' . $exception->getMessage());
        $checks[] = ['status' => 'fail', 'label' => 'Databázové migrace', 'detail' => 'Stav migrací se nepodařilo bezpečně ověřit.'];
    }
    try {
        $checks[] = m2FinalizationDemoCheck($pdo);
    } catch (Throwable $exception) {
        error_log('M2 finalization demo check: ' . $exception->getMessage());
        $checks[] = ['status' => 'fail', 'label' => 'Testovací identity a data', 'detail' => 'Demo data se nepodařilo bezpečně ověřit.'];
    }

    $accepted = 0;
    $blocking = 0;
    foreach ($scenarios as $scenario) {
        $saved = $feedback[(string)$scenario['id']] ?? [];
        if (($saved['result'] ?? 'not_tested') === 'pass') $accepted++;
        if (in_array(($saved['result'] ?? ''), ['fail', 'blocked'], true) || ($saved['importance'] ?? '') === 'blocks') $blocking++;
    }
    $technicalPassed = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'pass'));
    return [
        'checks' => $checks,
        'technical_passed' => $technicalPassed,
        'technical_total' => count($checks),
        'accepted' => $accepted,
        'scenario_total' => count($scenarios),
        'blocking' => $blocking,
        'close_ready' => $technicalPassed === count($checks) && $accepted === count($scenarios) && $blocking === 0,
    ];
}
