<?php
/**
 * Záloha databáze evidence před nasazením.
 * Volá ho GitHub Actions; jde spustit i ručně v prohlížeči.
 *
 * GET /bin/zaloha.php?token=<DEPLOY_TOKEN z config.php>
 *
 * Záloha se ukládá přednostně MIMO webový adresář (../db_zalohy vedle
 * složky webu); pokud to hosting nedovolí, do /bin/zalohy uvnitř projektu
 * (chráněno .htaccess). Drží se posledních 20 záloh.
 *
 * Databáze je sdílená s dalšími aplikacemi — tabulky prodejního terminálu
 * (jidlo_*, bar_*) se do této zálohy nezahrnují.
 */

set_time_limit(600);
header('Content-Type: application/json; charset=utf-8');

$koren = dirname(__DIR__);

if (!is_file($koren . '/config.php')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'chyba' => 'Na serveru chybí config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $koren . '/config.php';

if (!defined('DEPLOY_TOKEN') || DEPLOY_TOKEN === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'chyba' => 'V config.php chybí konstanta DEPLOY_TOKEN.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!hash_equals((string)DEPLOY_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'chyba' => 'Neplatný token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $konstanta) {
    if (!defined($konstanta)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'chyba' => "V config.php chybí konstanta {$konstanta}."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'chyba' => 'Chyba připojení k databázi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- kam zálohovat ----------
$mimo   = dirname($koren) . '/db_zalohy';   // vedle webu (mimo webroot)
$uvnitr = __DIR__ . '/zalohy';              // fallback uvnitř projektu

$cil = null;
foreach ([$mimo, $uvnitr] as $adresar) {
    if ((is_dir($adresar) || @mkdir($adresar, 0755, true)) && is_writable($adresar)) {
        $cil = $adresar;
        break;
    }
}
if ($cil === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'chyba' => 'Nelze vytvořit adresář pro zálohy.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($cil === $uvnitr) {
    @file_put_contents($uvnitr . '/.htaccess', "Require all denied\n");
}

// ---------- výběr tabulek (bez tabulek prodejního terminálu) ----------
$tabulky = [];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    if (str_starts_with($t, 'jidlo_') || str_starts_with($t, 'bar_')) {
        continue;
    }
    $tabulky[] = $t;
}
sort($tabulky);

// ---------- dump ----------
$soubor = $cil . '/evidence_' . date('Y-m-d_His') . '.sql.gz';
$gz = gzopen($soubor, 'wb6');
if (!$gz) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'chyba' => 'Nelze zapisovat soubor zálohy.'], JSON_UNESCAPED_UNICODE);
    exit;
}

gzwrite($gz, '-- Záloha evidence tréninků — ' . date('Y-m-d H:i:s') . "\n");
gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

$radku_celkem = 0;
foreach ($tabulky as $tabulka) {
    $create = $pdo->query("SHOW CREATE TABLE `$tabulka`")->fetch(PDO::FETCH_NUM)[1];
    gzwrite($gz, "DROP TABLE IF EXISTS `$tabulka`;\n$create;\n\n");

    // Sloupce bez generovaných (ty se při obnově nesmí vkládat)
    $sloupce = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `$tabulka`")->fetchAll(PDO::FETCH_ASSOC) as $sl) {
        if (stripos((string)($sl['Extra'] ?? ''), 'GENERATED') !== false) {
            continue;
        }
        $sloupce[] = $sl['Field'];
    }
    if (!$sloupce) {
        continue;
    }
    $seznam_sloupcu = '`' . implode('`,`', $sloupce) . '`';

    $stmt = $pdo->query("SELECT $seznam_sloupcu FROM `$tabulka`");
    $davka = [];
    while ($radek = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hodnoty = array_map(
            fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
            array_values($radek)
        );
        $davka[] = '(' . implode(',', $hodnoty) . ')';
        $radku_celkem++;
        if (count($davka) >= 200) {
            gzwrite($gz, "INSERT INTO `$tabulka` ($seznam_sloupcu) VALUES\n" . implode(",\n", $davka) . ";\n");
            $davka = [];
        }
    }
    if ($davka) {
        gzwrite($gz, "INSERT INTO `$tabulka` ($seznam_sloupcu) VALUES\n" . implode(",\n", $davka) . ";\n");
    }
    gzwrite($gz, "\n");
}
gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
gzclose($gz);

// ---------- úklid: držet posledních 20 záloh ----------
$stare = glob($cil . '/evidence_*.sql.gz');
rsort($stare);
foreach (array_slice($stare, 20) as $s) {
    @unlink($s);
}

echo json_encode([
    'ok'       => true,
    'soubor'   => basename($soubor),
    'umisteni' => $cil === $uvnitr ? 'uvnitř projektu (bin/zalohy, chráněno)' : 'mimo webový adresář (db_zalohy)',
    'tabulek'  => count($tabulky),
    'radku'    => $radku_celkem,
    'velikost' => filesize($soubor),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
