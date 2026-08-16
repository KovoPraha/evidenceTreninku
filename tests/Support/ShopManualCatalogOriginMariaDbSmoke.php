<?php
declare(strict_types=1);

$database = getenv('EVIDENCE_MANUAL_CATALOG_SMOKE_DB') ?: 'evidence_prompt_e_r1_test';
if (preg_match('/\Aevidence_prompt_e_r1_test_[a-z0-9_]+\z/', $database) !== 1
    && $database !== 'evidence_prompt_e_r1_test'
) {
    throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
}

$server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec(
        'CREATE TABLE treneri('
        . 'id INT NOT NULL PRIMARY KEY,jmeno VARCHAR(100) NOT NULL'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
    foreach ([
        '20260802170000_shop_catalog_staging.php',
        '20260802190000_shop_catalog_review.php',
        '20260802210000_shop_canonical_catalog.php',
    ] as $filename) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
        $migration['up']($pdo);
        if (!$migration['verify']($pdo)) {
            throw new RuntimeException('Base MariaDB migration verification failed: ' . $filename);
        }
    }
    $pdo->exec(
        "INSERT INTO shop_catalog_import_runs "
        . '(id,source_sha256,source_filename,contract_version,status,product_count,variant_count,'
        . "warning_count,manual_review_count) VALUES(1,'" . str_repeat('a', 64)
        . "','test.csv','shop-v1','promoted',1,1,0,0)"
    );
    $pdo->exec(
        "INSERT INTO shop_catalog_product_candidates "
        . '(id,run_id,external_product_key,name,offer_type,classification_confidence,'
        . "needs_manual_review,payload_json,review_status) VALUES"
        . "(101,1,'shoptet:101','Importovaný produkt','goods','high',0,'{}','auto_classified')"
    );
    $pdo->exec(
        "INSERT INTO shop_catalog_variant_candidates "
        . '(id,run_id,product_candidate_id,sku,price_mode,amount_minor,currency,payload_json) '
        . "VALUES(201,1,101,'IMPORT-1','fixed',5000,'CZK','{}')"
    );
    $pdo->exec(
        "INSERT INTO shop_products "
        . '(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,catalog_status) '
        . "VALUES(501,101,1,'shoptet:101','Importovaný produkt','goods','active')"
    );
    $pdo->exec(
        "INSERT INTO shop_variants "
        . '(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,'
        . "catalog_status) VALUES(601,501,201,'IMPORT-1','{}','fixed',5000,'CZK','active')"
    );

    $migration = require dirname(__DIR__, 2)
        . '/migrations/20260816200000_shop_manual_catalog_origin.php';
    $migration['up']($pdo);
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) {
        throw new RuntimeException('Prompt E R1 MariaDB migration verification failed.');
    }
    $imported = $pdo->query(
        'SELECT source_candidate_id,source_run_id,origin,created_by_trainer_id '
        . 'FROM shop_products WHERE id=501'
    )->fetch(PDO::FETCH_ASSOC);
    if ($imported !== [
        'source_candidate_id' => 101,
        'source_run_id' => 1,
        'origin' => 'import',
        'created_by_trainer_id' => null,
    ]) {
        throw new RuntimeException('Prompt E R1 changed an existing import product.');
    }

    $pdo->exec(
        "INSERT INTO shop_products "
        . '(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,name,'
        . "offer_type,catalog_status) VALUES(NULL,NULL,'manual',7,'manual:abc','Ruční produkt','goods','draft')"
    );
    $manualProductId = (int)$pdo->lastInsertId();
    $statement = $pdo->prepare(
        'INSERT INTO shop_variants '
        . '(product_id,source_candidate_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,'
        . "amount_minor,currency,catalog_status) VALUES(?,NULL,'manual',7,'KP-TEST','{}','fixed',10000,"
        . "'CZK','draft')"
    );
    $statement->execute([$manualProductId]);
    if ((int)$pdo->query(
        "SELECT COUNT(*) FROM shop_products WHERE origin='manual' "
        . 'AND source_candidate_id IS NULL AND source_run_id IS NULL AND created_by_trainer_id=7'
    )->fetchColumn() !== 1) {
        throw new RuntimeException('Prompt E R1 MariaDB manual product insert failed.');
    }
    if ((int)$pdo->query(
        "SELECT COUNT(*) FROM shop_variants WHERE origin='manual' "
        . 'AND source_candidate_id IS NULL AND created_by_trainer_id=7'
    )->fetchColumn() !== 1) {
        throw new RuntimeException('Prompt E R1 MariaDB manual variant insert failed.');
    }

    echo 'MariaDB manual catalog origin smoke OK ('
        . (string)$pdo->query('SELECT VERSION()')->fetchColumn() . ")\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
