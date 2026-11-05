<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/includes/bar/stock.php';
require_once dirname(__DIR__, 2) . '/includes/trainer_password_reset.php';
$port = (int)(getenv('BAR_SMOKE_DB_PORT') ?: 3306);
if ($port < 1024 || $port > 65535) {
    throw new RuntimeException('Invalid isolated database port.');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', $options);
$database = 'evidence_bar_test_' . bin2hex(random_bytes(6));
$runtime = sys_get_temp_dir() . '/bar-transfer-mariadb-' . bin2hex(random_bytes(6));
mkdir($runtime, 0700);
mkdir($runtime . '/source-images', 0700);
$oldStorage = getenv('APP_PRIVATE_STORAGE_ROOT');
putenv('APP_PRIVATE_STORAGE_ROOT=' . $runtime . '/private');
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', $options);
    $writer = new PDO($dsn, 'root', '', $options);
    $pdo->exec('CREATE TABLE treneri(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),email VARCHAR(255),heslo VARCHAR(100),'
        . 'role VARCHAR(20),aktivni INT,session_version INT) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO treneri(id,jmeno,role,aktivni,session_version) VALUES(1,'LOCALHOST System','trener',1,1),(3,'LOCALHOST Bar admin','trener',1,1)");
    foreach (['20260821150000_staff_workspaces', '20261105150000_bar_foundation', '20261105160000_bar_transfer', '20261105170000_bar_stock'] as $id) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $id . '.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        if (!$migration['verify']($pdo)) {
            throw new RuntimeException('Bar transfer migration verify failed.');
        }
    }
    $pdo->exec('DELETE FROM staff_user_positions');
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(1,'system_admin',1),(3,'bar_admin',1)");
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY,email VARCHAR(255),trener_id INT,aktivni INT,email_overeno INT,session_version INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE jidlo_kategorie(id INT PRIMARY KEY,nazev VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO jidlo_kategorie VALUES(10,'LOCALHOST Drinks')");
    $pdo->exec('CREATE TABLE jidlo_produkty(id INT PRIMARY KEY,kategorie_id INT,nazev VARCHAR(100),cena DECIMAL(10,2),nakupni_cena DECIMAL(10,2),'
        . 'skladovy_rezim VARCHAR(20),sklad INT,predsklad INT,obrazek VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO jidlo_produkty VALUES(11,10,'LOCALHOST Tea',35.00,11.01,'sledovat',23,47,'tea.png')");
    $pdo->exec('CREATE TABLE jidlo_produkty_slozeni(produkt_id INT,slozka_id INT,mnozstvi INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE bar_uzivatele(id INT PRIMARY KEY,jmeno VARCHAR(100),heslo_hash VARCHAR(255),role VARCHAR(20),schvalen INT) ENGINE=InnoDB');
    $pdo->prepare("INSERT INTO bar_uzivatele VALUES(77,'lucka',?,'prodejce',1)")->execute([password_hash('LOCALHOST-Lucka-123', PASSWORD_DEFAULT)]);
    $image = imagecreatetruecolor(8, 4);
    imagepng($image, $runtime . '/source-images/tea.png');
    imagedestroy($image);
    $source = barReadLegacyTransferSource($pdo);
    $bundle = barBuildTransferBundle($source, $runtime . '/source-images');
    barWriteTransferPackage($bundle, $runtime . '/source-images', $runtime . '/package');
    $_SESSION = ['trener_id' => 1, 'trener_session_version' => 1, 'staff_active_position' => 'system_admin'];
    $result = barImportTransfer($pdo, $bundle, $runtime . '/package/images');
    if ($result['products'] !== 1 || $result['images'] !== 1 || trainerPasswordResetTarget($pdo, 'info1@kovopraha.cz') !== null) {
        throw new RuntimeException('Transfer or technical-account policy failed.');
    }
    if ((int)$pdo->query('SELECT stock_stall+stock_office FROM kis_bar_products')->fetchColumn() !== 0
        || (int)$pdo->query('SELECT sklad+predsklad FROM jidlo_produkty')->fetchColumn() !== 70) {
        throw new RuntimeException('Initial zero stock or source immutability failed.');
    }
    $asset = $pdo->query('SELECT * FROM kis_bar_assets')->fetch(PDO::FETCH_ASSOC);
    barTransferAssetPath($asset);
    $_SESSION = ['trener_id' => 3, 'trener_session_version' => 1, 'staff_active_position' => 'bar_admin'];
    $productId = (int)$pdo->query('SELECT id FROM kis_bar_products')->fetchColumn();
    barStockOperation($pdo, $productId, 1, 'receive_office', 10, str_repeat('a', 64), 'LOCALHOST receipt');
    if (!barStockOperation($pdo, $productId, 1, 'receive_office', 10, str_repeat('a', 64), 'LOCALHOST receipt')['repeated']) {
        throw new RuntimeException('Repeated stock receipt was not idempotent.');
    }
    barStockOperation($pdo, $productId, 2, 'to_stall', 3, str_repeat('b', 64), 'LOCALHOST transfer');
    if ((int)$pdo->query('SELECT SUM(quantity_delta) FROM kis_bar_stock_movements')->fetchColumn() !== 10) {
        throw new RuntimeException('Transfer created or lost quantity.');
    }
    $_SESSION = ['trener_id' => 1, 'trener_session_version' => 1, 'staff_active_position' => 'system_admin'];
    if (!barImportTransfer($pdo, $bundle, $runtime . '/package/images')['repeated']
        || (int)$pdo->query('SELECT stock_office FROM kis_bar_products')->fetchColumn() !== 7) {
        throw new RuntimeException('Repeated import overwrote post-cutover changes.');
    }
    // A password-reset decision must see a policy change beyond an older read snapshot.
    $pdo->beginTransaction();
    $pdo->query('SELECT * FROM treneri')->fetchAll();
    $writer->exec('UPDATE treneri SET technical_email=1 WHERE id=3');
    if (!staffAccountHasTechnicalEmail($pdo, 3, true)) {
        throw new RuntimeException('Technical email policy used a stale snapshot.');
    }
    $pdo->rollBack();
    echo 'MariaDB bar transfer, private photographs, restricted lucka, idempotence and stock journal PASS ('
        . $server->query('SELECT VERSION()')->fetchColumn() . ")\n";
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $server->exec("DROP DATABASE `$database`");
    $children = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtime, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($children as $child) {
        if (!str_starts_with($child->getPathname(), $runtime . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Unsafe smoke cleanup path.');
        }
        $child->isDir() && !$child->isLink() ? rmdir($child->getPathname()) : unlink($child->getPathname());
    }
    rmdir($runtime);
    putenv($oldStorage === false ? 'APP_PRIVATE_STORAGE_ROOT' : 'APP_PRIVATE_STORAGE_ROOT=' . $oldStorage);
    $_SESSION = [];
}
