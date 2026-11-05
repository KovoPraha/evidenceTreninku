<?php

declare(strict_types=1);

require_once __DIR__ . '/invoice_pdf.php';
require_once __DIR__ . '/transfer_assets.php';

function barPrivateManifestFilePath(array $file): string
{
    if (!is_string($file['key'] ?? null) || !is_string($file['sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $file['sha256']) || !is_int($file['bytes'] ?? null)
        || $file['bytes'] < 1 || $file['bytes'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Neplatný manifest soukromých souborů baru.');
    }
    if (str_starts_with($file['key'], 'private://bar-invoices/')) {
        $path = barInvoicePdfPath(['storage_key' => $file['key'], 'pdf_sha256' => $file['sha256']]);
    } else {
        $path = barTransferAssetPath(['storage_key' => $file['key'], 'sha256_hex' => $file['sha256']]);
    }
    if (filesize($path) !== $file['bytes'] || (new finfo(FILEINFO_MIME_TYPE))->file($path) !== ($file['mime'] ?? null)) {
        throw new RuntimeException('Soukromý soubor baru nemá očekávanou velikost nebo formát.');
    }
    return $path;
}

/** Includes replaced photographs too: historical private assets are retained for recovery. No absolute paths leave this helper. */
function barPrivateFileManifest(PDO $pdo): array
{
    barActor($pdo, 'cash_recovery');
    $files = [];
    $add = static function (array $entry) use (&$files): void {
        barPrivateManifestFilePath($entry);
        if (isset($files[$entry['key']]) && $files[$entry['key']] !== $entry) {
            throw new RuntimeException('Dvě přílohy se stejným klíčem mají odlišné údaje.');
        }
        $files[$entry['key']] = $entry;
    };
    foreach ($pdo->query('SELECT storage_key,sha256_hex,byte_size,mime_type FROM kis_bar_assets ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $add(['key' => $row['storage_key'], 'sha256' => $row['sha256_hex'], 'bytes' => (int)$row['byte_size'], 'mime' => $row['mime_type']]);
    }
    foreach ($pdo->query('SELECT storage_key,pdf_sha256,pdf_bytes FROM kis_bar_invoices ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $add(['key' => $row['storage_key'], 'sha256' => $row['pdf_sha256'], 'bytes' => (int)$row['pdf_bytes'], 'mime' => 'application/pdf']);
    }
    ksort($files, SORT_STRING);
    $files = array_values($files);
    return ['schema' => 'kis.bar.private-files.v1', 'files' => $files,
        'sha256' => hash('sha256', json_encode($files, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))];
}

function barVerifyPrivateFileManifest(PDO $pdo, array $expected): void
{
    $actual = barPrivateFileManifest($pdo);
    if (($expected['schema'] ?? null) !== $actual['schema'] || ($expected['files'] ?? null) !== $actual['files']
        || !is_string($expected['sha256'] ?? null) || !hash_equals($actual['sha256'], $expected['sha256'])) {
        throw new RuntimeException('Obnovená databáze a soukromé soubory baru neodpovídají stejnému manifestu.');
    }
}
