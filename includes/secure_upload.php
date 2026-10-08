<?php
declare(strict_types=1);

final class SecureUploadException extends RuntimeException
{
}

const SECURE_UPLOAD_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const SECURE_UPLOAD_IMAGE_MAX_WIDTH = 6000;
const SECURE_UPLOAD_IMAGE_MAX_HEIGHT = 6000;
const SECURE_UPLOAD_IMAGE_MAX_PIXELS = 24000000;
const SECURE_UPLOAD_IMAGE_MAX_FILES = 10;

function secureUploadAssertFileCount(array $names, int $maximum = SECURE_UPLOAD_IMAGE_MAX_FILES): void
{
    $count = count(array_filter($names, static fn(mixed $name): bool => trim((string)$name) !== ''));
    if ($count > $maximum) {
        throw new SecureUploadException('Najednou lze nahrát nejvýše ' . $maximum . ' souborů.');
    }
}

/** @return string verified MIME type */
function secureUploadValidateDocument(
    string $source,
    array $allowedMimeTypes,
    bool $uploaded = true,
    int $maximumBytes = 10 * 1024 * 1024
): string {
    if (!is_file($source) || ($uploaded && !is_uploaded_file($source))) {
        throw new SecureUploadException('Nahraný soubor nebyl nalezen.');
    }
    $size = filesize($source);
    if (!is_int($size) || $size < 1 || $size > $maximumBytes) {
        throw new SecureUploadException('Soubor musí mít nejvýše ' . (int)ceil($maximumBytes / 1048576) . ' MB.');
    }
    $mime = strtolower((string)(new finfo(FILEINFO_MIME_TYPE))->file($source));
    if (!in_array($mime, $allowedMimeTypes, true)) {
        throw new SecureUploadException('Soubor nemá povolený datový formát.');
    }
    return $mime;
}

/**
 * Decode an uploaded JPG/PNG/WEBP/GIF and write a metadata-free JPEG.
 * The caller controls the destination, but the user never controls its suffix.
 *
 * @return array{mime_type:string,width_px:int,height_px:int,byte_size:int,sha256_hex:string}
 */
function secureUploadReencodeImageToJpeg(
    string $source,
    string $destination,
    bool $uploaded = true,
    int $maximumBytes = SECURE_UPLOAD_IMAGE_MAX_BYTES
): array {
    if (!is_file($source) || ($uploaded && !is_uploaded_file($source))) {
        throw new SecureUploadException('Nahraný obrázek nebyl nalezen.');
    }
    $size = filesize($source);
    if (!is_int($size) || $size < 1 || $size > $maximumBytes) {
        throw new SecureUploadException('Obrázek musí mít nejvýše ' . (int)ceil($maximumBytes / 1048576) . ' MB.');
    }
    $bytes = file_get_contents($source);
    if (!is_string($bytes)) {
        throw new SecureUploadException('Obrázek nelze bezpečně načíst.');
    }
    $mime = strtolower((string)(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes));
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new SecureUploadException('Soubor musí být skutečný JPG, PNG, WEBP nebo GIF obrázek.');
    }
    $dimensions = @getimagesizefromstring($bytes);
    $width = is_array($dimensions) ? (int)($dimensions[0] ?? 0) : 0;
    $height = is_array($dimensions) ? (int)($dimensions[1] ?? 0) : 0;
    if (
        $width < 1 || $height < 1
        || $width > SECURE_UPLOAD_IMAGE_MAX_WIDTH
        || $height > SECURE_UPLOAD_IMAGE_MAX_HEIGHT
        || $width * $height > SECURE_UPLOAD_IMAGE_MAX_PIXELS
    ) {
        throw new SecureUploadException('Obrázek má neplatné nebo příliš velké rozměry.');
    }
    $decoded = @imagecreatefromstring($bytes);
    if (!$decoded instanceof GdImage) {
        throw new SecureUploadException('Obrázek nelze bezpečně dekódovat.');
    }
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        imagedestroy($decoded);
        throw new SecureUploadException('Cílový adresář obrázku nelze vytvořit.');
    }
    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas instanceof GdImage) {
        imagedestroy($decoded);
        throw new SecureUploadException('Pro obrázek se nepodařilo připravit bezpečné plátno.');
    }
    try {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        if (!imagecopy($canvas, $decoded, 0, 0, 0, 0, $width, $height) || !imagejpeg($canvas, $destination, 90)) {
            throw new SecureUploadException('Obrázek se nepodařilo bezpečně uložit.');
        }
    } catch (Throwable $exception) {
        if (is_file($destination)) @unlink($destination);
        throw $exception;
    } finally {
        imagedestroy($canvas);
        imagedestroy($decoded);
    }
    @chmod($destination, 0640);
    $storedSize = filesize($destination);
    $hash = hash_file('sha256', $destination);
    if (!is_int($storedSize) || !is_string($hash)) {
        @unlink($destination);
        throw new SecureUploadException('Uložený obrázek nelze ověřit.');
    }
    return [
        'mime_type' => 'image/jpeg',
        'width_px' => $width,
        'height_px' => $height,
        'byte_size' => $storedSize,
        'sha256_hex' => $hash,
    ];
}

/** @return array{path:string,metadata:array{mime_type:string,width_px:int,height_px:int,byte_size:int,sha256_hex:string}} */
function secureUploadPrepareImage(string $source, bool $uploaded = true): array
{
    $temporary = tempnam(sys_get_temp_dir(), 'kis-image-');
    if (!is_string($temporary)) {
        throw new SecureUploadException('Dočasný obrázek nelze vytvořit.');
    }
    $jpeg = $temporary . '.jpg';
    @unlink($temporary);
    try {
        $metadata = secureUploadReencodeImageToJpeg($source, $jpeg, $uploaded);
        return ['path' => $jpeg, 'metadata' => $metadata];
    } catch (Throwable $exception) {
        if (is_file($jpeg)) @unlink($jpeg);
        throw $exception;
    }
}

/** @return array{relative_path:string,absolute_path:string,metadata:array{mime_type:string,width_px:int,height_px:int,byte_size:int,sha256_hex:string}} */
function secureUploadStorePublicImage(
    string $source,
    string $relativeDirectory,
    string $prefix,
    bool $uploaded = true,
    ?string $applicationRoot = null
): array {
    if (preg_match('~\A[a-z0-9/_-]+\z~D', $relativeDirectory) !== 1 || str_contains($relativeDirectory, '..')) {
        throw new InvalidArgumentException('Neplatný cílový adresář obrázku.');
    }
    if (preg_match('/\A[a-z0-9_-]{1,32}\z/D', $prefix) !== 1) {
        throw new InvalidArgumentException('Neplatný prefix obrázku.');
    }
    $applicationRoot ??= dirname(__DIR__);
    $directory = rtrim($applicationRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    $name = $prefix . '_' . bin2hex(random_bytes(16)) . '.jpg';
    $absolute = $directory . DIRECTORY_SEPARATOR . $name;
    $metadata = secureUploadReencodeImageToJpeg($source, $absolute, $uploaded);
    return [
        'relative_path' => trim($relativeDirectory, '/') . '/' . $name,
        'absolute_path' => $absolute,
        'metadata' => $metadata,
    ];
}
