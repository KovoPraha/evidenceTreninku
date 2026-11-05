<?php

declare(strict_types=1);

require_once __DIR__ . '/transfer.php';

/** Credential hashes belong only in a private package outside the repository and webroot. */
function barWriteTransferPackage(array $bundle, string $imagesDirectory, string $destination): void
{
    $bundle = barValidateTransferBundle($bundle, $imagesDirectory);
    $parent = realpath(dirname($destination));
    if ($parent === false || basename($destination) === '' || in_array(basename($destination), ['.', '..'], true) || file_exists($destination)) {
        throw new InvalidArgumentException('Zvolte nový soukromý adresář balíčku s existujícím rodičem.');
    }
    $target = $parent . DIRECTORY_SEPARATOR . basename($destination);
    for ($ancestor = $parent; ; $ancestor = dirname($ancestor)) {
        if (file_exists($ancestor . DIRECTORY_SEPARATOR . '.git')) {
            throw new InvalidArgumentException('Soukromý převodní balíček nesmí být v žádné pracovní kopii Gitu.');
        }
        if (dirname($ancestor) === $ancestor) {
            break;
        }
    }
    foreach ([dirname(__DIR__, 2), (string)($_SERVER['DOCUMENT_ROOT'] ?? '')] as $root) {
        $web = $root === '' ? false : realpath($root);
        if ($web !== false && ($target === $web || str_starts_with($target . DIRECTORY_SEPARATOR, $web . DIRECTORY_SEPARATOR))) {
            throw new InvalidArgumentException('Balíček s přihlašovacím hashem nesmí být v repozitáři ani webrootu.');
        }
    }
    if (!mkdir($target, 0700) || !mkdir($target . '/images', 0700)) {
        throw new RuntimeException('Soukromý adresář nelze vytvořit.');
    }
    foreach ($bundle['images'] as $row) {
        $image = barReadImage(barSourceImagePath($imagesDirectory, $row['name']));
        if (!hash_equals($row['sha256'], $image['meta']['sha256'])) {
            throw new RuntimeException('Zdrojová fotografie se během balení změnila.');
        }
        barWritePrivateFile($target . '/images/' . $row['name'], $image['bytes']);
    }
    // The manifest is the completion marker and is written last.
    barWritePrivateFile($target . '/bundle.json', json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function barWritePrivateFile(string $path, string $contents): void
{
    $handle = @fopen($path, 'x+b');
    if ($handle === false) {
        throw new RuntimeException('Soukromý soubor existuje nebo jej nelze vytvořit.');
    }
    try {
        @chmod($path, 0600);
        if (fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) {
            throw new RuntimeException('Soukromý soubor se nepodařilo úplně uložit.');
        }
    } finally {
        fclose($handle);
    }
}

/** Prepared CLI can write only to an explicitly named loopback test database. */
function barIsolatedTransferConnection(string $prefix): PDO
{
    $dsn = (string)getenv($prefix . '_DSN');
    if (!preg_match('/^mysql:host=127\.0\.0\.1;port=([0-9]{4,5});dbname=((?:evidence_bar_(?:test|local)_[a-z0-9_]+)|bar_pos_test);charset=utf8mb4$/D', $dsn, $match)
        || (int)$match[1] > 65535 || (int)$match[1] < 1024) {
        throw new InvalidArgumentException('CLI přijímá pouze explicitní izolovanou databázi na 127.0.0.1. Produkční převod není povolený.');
    }
    if ($prefix === 'BAR_TARGET' && $match[2] === 'bar_pos_test') {
        throw new InvalidArgumentException('Cílem musí být oddělená KIS barová testovací databáze.');
    }
    return new PDO(
        $dsn,
        (string)getenv($prefix . '_USER'),
        (string)getenv($prefix . '_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

/** Source credentials are explicit; the caller immediately opens a read-only snapshot. */
function barReadOnlySourceConnection(): PDO
{
    $dsn = (string)getenv('BAR_SOURCE_DSN');
    if (!preg_match('/^mysql:host=[a-zA-Z0-9.-]+;(?:port=([0-9]{4,5});)?dbname=[a-zA-Z0-9_]+;charset=utf8mb4$/D', $dsn, $match)
        || (isset($match[1]) && ((int)$match[1] < 1024 || (int)$match[1] > 65535))) {
        throw new InvalidArgumentException('Zadejte explicitní MySQL zdroj v BAR_SOURCE_DSN. Zdroj se pouze čte.');
    }
    return new PDO(
        $dsn,
        (string)getenv('BAR_SOURCE_USER'),
        (string)getenv('BAR_SOURCE_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}
