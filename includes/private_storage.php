<?php
declare(strict_types=1);

const PRIVATE_STORAGE_RECEIPTS = 'receipts';
const PRIVATE_STORAGE_STRESS_TESTS = 'stress-tests';

/** @return array{extension:string,mime:string} */
function privateStorageDetectAllowedFile(string $source): array
{
    if (!is_file($source)) {
        throw new RuntimeException('Nahrany soubor nebyl nalezen.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string)$finfo->file($source));
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Nepodporovany typ souboru. Povoleny jsou JPG, PNG a PDF.');
    }
    return ['extension' => $allowed[$mime], 'mime' => $mime];
}

function privateStorageRoot(): string
{
    $root = getenv('APP_PRIVATE_STORAGE_ROOT');
    if (!is_string($root) || trim($root) === '') {
        $root = defined('APP_PRIVATE_STORAGE_ROOT') ? (string)APP_PRIVATE_STORAGE_ROOT : '';
    }
    if (trim($root) === '' && defined('JE_LOKALNE') && JE_LOKALNE) {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'evidencePavel';
    }
    $root = rtrim(trim($root), "\\/");
    if ($root === '') {
        throw new RuntimeException('APP_PRIVATE_STORAGE_ROOT neni nastavena.');
    }

    $normalizedRoot = strtolower(str_replace('\\', '/', $root));
    $appRoot = strtolower(str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
    $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $normalizedDocumentRoot = $documentRoot !== ''
        ? strtolower(str_replace('\\', '/', realpath($documentRoot) ?: $documentRoot))
        : '';
    $inside = static fn(string $candidate, string $parent): bool =>
        $parent !== '' && ($candidate === $parent || str_starts_with($candidate . '/', rtrim($parent, '/') . '/'));
    if ($inside($normalizedRoot, $appRoot) || $inside($normalizedRoot, $normalizedDocumentRoot)) {
        throw new RuntimeException('Soukrome uloziste musi byt mimo webroot aplikace.');
    }
    return $root;
}

function privateStorageEnsureDirectory(string $category): string
{
    if (!in_array($category, [PRIVATE_STORAGE_RECEIPTS, PRIVATE_STORAGE_STRESS_TESTS], true)) {
        throw new InvalidArgumentException('Neplatna kategorie soukromeho souboru.');
    }
    $directory = privateStorageRoot() . DIRECTORY_SEPARATOR . $category;
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Soukrome uloziste nelze vytvorit.');
    }
    @chmod($directory, 0700);
    return $directory;
}

function privateStorageStore(string $source, string $category, bool $uploaded = true): string
{
    $detected = privateStorageDetectAllowedFile($source);
    $name = bin2hex(random_bytes(16)) . '.' . $detected['extension'];
    $target = privateStorageEnsureDirectory($category) . DIRECTORY_SEPARATOR . $name;
    $moved = $uploaded ? move_uploaded_file($source, $target) : rename($source, $target);
    if (!$moved) {
        throw new RuntimeException('Soubor se nepodarilo ulozit do soukromeho uloziste.');
    }
    @chmod($target, 0600);
    return 'private://' . $category . '/' . $name;
}

function privateStorageResolve(string $key): ?string
{
    if (preg_match('~^private://(receipts|stress-tests)/([a-f0-9]{32}\.(?:jpg|png|pdf))$~D', $key, $match) !== 1) {
        return null;
    }
    $path = privateStorageRoot() . DIRECTORY_SEPARATOR . $match[1] . DIRECTORY_SEPARATOR . $match[2];
    return is_file($path) ? $path : null;
}

function privateStorageSoftDelete(string $key): void
{
    $path = privateStorageResolve($key);
    if ($path === null) {
        return;
    }
    $deleted = dirname($path) . DIRECTORY_SEPARATOR . '.deleted-' . bin2hex(random_bytes(12));
    if (!rename($path, $deleted)) {
        throw new RuntimeException('Soukromy soubor se nepodarilo bezpecne odstranit.');
    }
    @chmod($deleted, 0600);
}

function privateStorageMime(string $path): string
{
    return privateStorageDetectAllowedFile($path)['mime'];
}
