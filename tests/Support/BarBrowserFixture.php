<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';
require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
$port = (int)(getenv('BAR_SMOKE_DB_PORT') ?: 3327);
if ($port < 1024 || $port > 65535) {
    throw new RuntimeException('Invalid local fixture database port.');
}
$root = dirname(__DIR__, 2);
if (file_exists($root . '/config.php')) {
    throw new RuntimeException('Refusing to overwrite an existing application configuration.');
}
$database = 'evidence_bar_test_browser_' . bin2hex(random_bytes(5));
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$server = new PDO('mysql:host=127.0.0.1;port=' . $port, 'root', '', $options);
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo = new PDO('mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4', 'root', '', $options);
$client = (string)(getenv('BAR_MYSQL_CLIENT') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql'));
$process = proc_open(
    [$client, '--protocol=tcp', '-h', '127.0.0.1', '-P', (string)$port, '-u', 'root', $database],
    [0 => ['file', $root . '/database/local-demo-schema.sql', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start local schema importer.');
}
stream_get_contents($pipes[1]);
$importError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Synthetic schema import failed: ' . $importError);
}
$pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?) ON DUPLICATE KEY UPDATE hodnota=VALUES(hodnota)')
    ->execute(['schema_version', LEGACY_SCHEMA_VERSION]);
$runner = new EvidenceMigrationRunner($pdo, EvidenceMigrationCatalog::load($root . '/migrations'));
$runner->apply();
$runtime = sys_get_temp_dir() . '/kis-bar-browser-' . bin2hex(random_bytes(6));
mkdir($runtime, 0700);
mkdir($runtime . '/images', 0700);
putenv('APP_PRIVATE_STORAGE_ROOT=' . $runtime . '/private');
$image = imagecreatetruecolor(12, 8);
imagepng($image, $runtime . '/images/tea.png');
imagedestroy($image);
$insert = $pdo->prepare("INSERT INTO treneri(id,jmeno,email,heslo,role,aktivni,session_version) VALUES(?,?,?,?,'trener',1,1)");
$insert->execute([7001, 'LOCALHOST Bar System', 'bar.system@example.invalid', password_hash('LOCALHOST-System-Only-123!', PASSWORD_DEFAULT)]);
$insert->execute([7003, 'LOCALHOST Bar Admin', 'bar.admin@example.invalid', password_hash('LOCALHOST-BarAdmin-Only-123!', PASSWORD_DEFAULT)]);
$pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(7001,'system_admin',1),(7003,'bar_admin',1)");
$source = ['categories' => [['id' => 41, 'nazev' => 'LOCALHOST Nápoje']],
    'products' => [['id' => 91, 'kategorie_id' => 41, 'nazev' => 'LOCALHOST Čaj', 'popis' => 'Pouze syntetický testovací katalog.', 'cena' => '35.00',
        'nakupni_cena' => '11.01', 'obrazek' => 'tea.png', 'skladovy_rezim' => 'sledovat', 'sklad' => 99, 'predsklad' => 88]],
    'recipes' => [], 'lucka' => ['id' => 77, 'jmeno' => 'lucka', 'role' => 'prodejce', 'schvalen' => 1,
        'heslo_hash' => password_hash('LOCALHOST-Lucka-Only-123!', PASSWORD_DEFAULT)]];
$bundle = barBuildTransferBundle($source, $runtime . '/images');
barWriteTransferPackage($bundle, $runtime . '/images', $runtime . '/package');
$_SESSION = ['trener_id' => 7001, 'trener_session_version' => 1, 'staff_active_position' => 'system_admin'];
$result = barImportTransfer($pdo, $bundle, $runtime . '/package/images');
$config = ['DB_HOST' => '127.0.0.1;port=' . $port, 'DB_NAME' => $database, 'DB_USER' => 'root', 'DB_PASS' => '',
    'APP_BASE_URL' => 'http://127.0.0.1:8895', 'JE_LOKALNE' => true, 'VELOCOTA_INTEGRATION' => false,
    'AUTH_RATE_LIMIT_PEPPER' => 'LOCALHOST-bar-browser-rate-limit-0000000000000000', 'APP_PRIVATE_STORAGE_ROOT' => $runtime . '/private'];
$configPhp = "<?php\n// LOCALHOST synthetic bar browser fixture; never deploy.\n";
foreach ($config as $key => $value) {
    $configPhp .= 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ");\n";
}
barWritePrivateFile($root . '/config.php', $configPhp);
barWritePrivateFile($runtime . '/fixture.json', json_encode(['database' => $database, 'port' => $port, 'lucka_id' => $result['trainer_id']], JSON_THROW_ON_ERROR));
echo json_encode(['ok' => true, 'database' => $database, 'runtime' => $runtime, 'source' => 'synthetic', 'production_mutations' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
