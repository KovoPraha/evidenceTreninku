<?php
declare(strict_types=1);

const PRIVATE_STORAGE_RECEIPTS = 'receipts';
const PRIVATE_STORAGE_STRESS_TESTS = 'stress-tests';
const PRIVATE_STORAGE_ATHLETE_PHOTOS = 'athlete-photos';
const PRIVATE_STORAGE_SERVICE_DOCUMENTS = 'service-documents';
const PRIVATE_STORAGE_UCI_TEMP = 'uci-temp';

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
    if (!in_array($category, [
        PRIVATE_STORAGE_RECEIPTS,
        PRIVATE_STORAGE_STRESS_TESTS,
        PRIVATE_STORAGE_ATHLETE_PHOTOS,
        PRIVATE_STORAGE_SERVICE_DOCUMENTS,
        PRIVATE_STORAGE_UCI_TEMP,
    ], true)) {
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
    if (preg_match('~^private://(receipts|stress-tests|athlete-photos|service-documents)/([a-f0-9]{32}\.(?:jpg|png|pdf))$~D', $key, $match) !== 1
        && preg_match('~^private://(uci-temp)/([a-f0-9]{32}\.xlsx)$~D', $key, $match) !== 1
    ) {
        return null;
    }
    $path = privateStorageRoot() . DIRECTORY_SEPARATOR . $match[1] . DIRECTORY_SEPARATOR . $match[2];
    return is_file($path) ? $path : null;
}

/**
 * Store an uploaded UCI XLSX template outside the public webroot.
 *
 * The dedicated function deliberately does not widen the document types
 * accepted by receipts, athlete photos or service documents.
 */
function privateStorageStoreUciTemp(
    string $source,
    string $originalName,
    bool $uploaded = true
): string {
    if (!is_file($source) || ($uploaded && !is_uploaded_file($source))) {
        throw new RuntimeException('Nahraný UCI formulář nebyl nalezen.');
    }
    $size = filesize($source);
    if (!is_int($size) || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('UCI formulář musí mít nejvýše 5 MB.');
    }
    if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
        throw new RuntimeException('UCI formulář musí být soubor XLSX.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string)$finfo->file($source));
    if (!in_array($mime, [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream',
        'application/zip',
    ], true)) {
        throw new RuntimeException('UCI formulář nemá podporovaný typ XLSX.');
    }

    $archive = new ZipArchive();
    if ($archive->open($source) !== true) {
        throw new RuntimeException('UCI formulář není platný XLSX archiv.');
    }
    try {
        if ($archive->locateName('[Content_Types].xml') === false
            || $archive->locateName('xl/workbook.xml') === false
        ) {
            throw new RuntimeException('UCI formulář nemá platnou strukturu XLSX.');
        }
    } finally {
        $archive->close();
    }

    $name = bin2hex(random_bytes(16)) . '.xlsx';
    $target = privateStorageEnsureDirectory(PRIVATE_STORAGE_UCI_TEMP) . DIRECTORY_SEPARATOR . $name;
    $moved = $uploaded ? move_uploaded_file($source, $target) : rename($source, $target);
    if (!$moved) {
        throw new RuntimeException('UCI formulář se nepodařilo uložit do soukromého úložiště.');
    }
    @chmod($target, 0600);
    return 'private://' . PRIVATE_STORAGE_UCI_TEMP . '/' . $name;
}

function privateStorageDeleteUciTemp(string $key): void
{
    if (preg_match('~^private://uci-temp/[a-f0-9]{32}\.xlsx$~D', $key) !== 1) {
        return;
    }
    $path = privateStorageResolve($key);
    if ($path !== null && !unlink($path)) {
        throw new RuntimeException('Dočasný UCI formulář se nepodařilo odstranit.');
    }
}

function privateStorageCleanupUciTemp(int $maxAgeSeconds = 86400): void
{
    $directory = privateStorageEnsureDirectory(PRIVATE_STORAGE_UCI_TEMP);
    $cutoff = time() - max(0, $maxAgeSeconds);
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*.xlsx') ?: [] as $path) {
        $modifiedAt = filemtime($path);
        if (is_int($modifiedAt) && $modifiedAt < $cutoff) {
            @unlink($path);
        }
    }
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

/**
 * Decode and re-encode an internal athlete photo so metadata and EXIF never
 * cross the private-storage boundary.
 *
 * @return array{storage_key:string,sha256_hex:string,byte_size:int,mime_type:string,width_px:int,height_px:int}
 */
function privateStorageStoreAthletePhoto(string $source, bool $uploaded = true): array
{
    if (!is_file($source) || ($uploaded && !is_uploaded_file($source))) {
        throw new RuntimeException('Nahraná fotografie nebyla nalezena.');
    }
    $size = filesize($source);
    if (!is_int($size) || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Fotografie musí mít nejvýše 5 MB.');
    }
    $bytes = file_get_contents($source);
    if (!is_string($bytes)) {
        throw new RuntimeException('Fotografii nelze bezpečně načíst.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $sourceMime = strtolower((string)$finfo->buffer($bytes));
    if (!in_array($sourceMime, ['image/jpeg', 'image/png'], true)) {
        throw new RuntimeException('Fotografie musí být skutečný JPG nebo PNG obrázek.');
    }
    $dimensions = @getimagesizefromstring($bytes);
    $width = is_array($dimensions) ? (int)($dimensions[0] ?? 0) : 0;
    $height = is_array($dimensions) ? (int)($dimensions[1] ?? 0) : 0;
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000) {
        throw new RuntimeException('Fotografie má neplatné rozměry.');
    }
    $image = @imagecreatefromstring($bytes);
    if (!$image instanceof GdImage) {
        throw new RuntimeException('Fotografii nelze bezpečně dekódovat.');
    }

    $name = bin2hex(random_bytes(16)) . '.jpg';
    $target = privateStorageEnsureDirectory(PRIVATE_STORAGE_ATHLETE_PHOTOS) . DIRECTORY_SEPARATOR . $name;
    try {
        if (!imagejpeg($image, $target, 90)) {
            throw new RuntimeException('Fotografii nelze uložit do soukromého úložiště.');
        }
    } catch (Throwable $exception) {
        if (is_file($target)) @unlink($target);
        throw $exception;
    } finally {
        imagedestroy($image);
    }
    @chmod($target, 0600);
    if (!$uploaded && is_file($source)) {
        @unlink($source);
    }
    $storedSize = filesize($target);
    $hash = hash_file('sha256', $target);
    if (!is_int($storedSize) || !is_string($hash)) {
        @unlink($target);
        throw new RuntimeException('Metadata fotografie nelze ověřit.');
    }
    return [
        'storage_key' => 'private://' . PRIVATE_STORAGE_ATHLETE_PHOTOS . '/' . $name,
        'sha256_hex' => $hash,
        'byte_size' => $storedSize,
        'mime_type' => 'image/jpeg',
        'width_px' => $width,
        'height_px' => $height,
    ];
}
