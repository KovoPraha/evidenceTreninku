<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/private_storage.php';

$apply = in_array('--apply', $argv, true) || getenv('MIGRATE_PRIVATE_APPLY') === '1';
$appRoot = dirname(__DIR__);
$moved = 0;
$missing = 0;
$alreadyPrivate = 0;
$errors = 0;

/**
 * @param array<int,array{id:int,path:string}> $rows
 */
function migratePrivateRows(
    PDO $pdo,
    array $rows,
    string $legacyPrefix,
    string $category,
    string $table,
    string $column,
    bool $apply,
    string $appRoot,
    int &$moved,
    int &$missing,
    int &$alreadyPrivate,
    int &$errors
): void {
    $legacyDirectory = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($legacyPrefix, '/'));
    foreach ($rows as $row) {
        $value = (string)$row['path'];
        if (str_starts_with($value, 'private://')) {
            $alreadyPrivate++;
            continue;
        }
        $expectedPrefix = trim($legacyPrefix, '/') . '/';
        $basename = basename(str_replace('\\', '/', $value));
        if (!str_starts_with(str_replace('\\', '/', ltrim($value, '/')), $expectedPrefix)
            || $basename === ''
            || $basename !== substr(str_replace('\\', '/', ltrim($value, '/')), strlen($expectedPrefix))
        ) {
            fwrite(STDERR, "SKIP neplatna cesta {$table}#{$row['id']}: {$value}\n");
            $errors++;
            continue;
        }
        $source = $legacyDirectory . DIRECTORY_SEPARATOR . $basename;
        if (!is_file($source)) {
            fwrite(STDERR, "CHYBI {$table}#{$row['id']}: {$source}\n");
            $missing++;
            continue;
        }
        if (!$apply) {
            echo "DRY-RUN {$table}#{$row['id']}: {$value}\n";
            $moved++;
            continue;
        }

        $key = '';
        try {
            $key = privateStorageStore($source, $category, false);
            $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ? AND {$column} = ?");
            $stmt->execute([$key, (int)$row['id'], $value]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Databazovy zaznam se mezitim zmenil.');
            }
            echo "MOVED {$table}#{$row['id']}: {$key}\n";
            $moved++;
        } catch (Throwable $exception) {
            if ($key !== '') {
                $privatePath = privateStorageResolve($key);
                if ($privatePath !== null && !is_file($source)) {
                    @rename($privatePath, $source);
                }
            }
            fwrite(STDERR, "ERROR {$table}#{$row['id']}: {$exception->getMessage()}\n");
            $errors++;
        }
    }
}

$receipts = $pdo->query(
    "SELECT id, obrazek_path AS path FROM ucto_uctenky WHERE obrazek_path IS NOT NULL AND obrazek_path <> '' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);
migratePrivateRows(
    $pdo,
    $receipts,
    'uploads/uctenky',
    PRIVATE_STORAGE_RECEIPTS,
    'ucto_uctenky',
    'obrazek_path',
    $apply,
    $appRoot,
    $moved,
    $missing,
    $alreadyPrivate,
    $errors
);

$stressFiles = $pdo->query(
    "SELECT id, cesta AS path FROM zatezove_testy_soubory WHERE cesta IS NOT NULL AND cesta <> '' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);
migratePrivateRows(
    $pdo,
    $stressFiles,
    'uploads/zatezove_testy',
    PRIVATE_STORAGE_STRESS_TESTS,
    'zatezove_testy_soubory',
    'cesta',
    $apply,
    $appRoot,
    $moved,
    $missing,
    $alreadyPrivate,
    $errors
);

$serviceDocuments = $pdo->query(
    "SELECT id, dokument AS path FROM ucto_servis WHERE dokument IS NOT NULL AND dokument <> '' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);
migratePrivateRows(
    $pdo,
    $serviceDocuments,
    'uploads/servis',
    PRIVATE_STORAGE_SERVICE_DOCUMENTS,
    'ucto_servis',
    'dokument',
    $apply,
    $appRoot,
    $moved,
    $missing,
    $alreadyPrivate,
    $errors
);

$mode = $apply ? 'APPLY' : 'DRY-RUN';
echo "{$mode} summary: candidates={$moved}, already_private={$alreadyPrivate}, missing={$missing}, errors={$errors}\n";
exit($errors > 0 ? 1 : 0);
