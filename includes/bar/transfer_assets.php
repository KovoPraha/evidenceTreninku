<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/private_storage.php';

/** Resolve a single basename, never a user-supplied path or a symbolic link. */
function barSourceImagePath(string $directory, string $name): string
{
    $root = realpath($directory);
    if ($root === false || !is_dir($root) || is_link($directory)
        || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,249}\.(?:jpg|jpeg|png|webp)$/iD', $name)) {
        throw new InvalidArgumentException('Neplatný adresář nebo název fotografie.');
    }
    $path = $root . DIRECTORY_SEPARATOR . $name;
    $resolved = realpath($path);
    if ($resolved === false || dirname($resolved) !== $root || !is_file($path) || is_link($path)) {
        throw new InvalidArgumentException('Fotografie chybí nebo není běžný soubor uvnitř zdrojového adresáře.');
    }
    return $resolved;
}

/** Read once: the manifest and stored file describe these exact bytes. */
function barReadImage(string $path): array
{
    $size = filesize($path);
    if (!is_int($size) || $size < 1 || $size > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('Fotografie musí mít nejvýše 8 MB.');
    }
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || strlen($bytes) !== $size) {
        throw new RuntimeException('Fotografii nelze úplně načíst.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    $dimensions = @getimagesizefromstring($bytes);
    $width = is_array($dimensions) ? (int)$dimensions[0] : 0;
    $height = is_array($dimensions) ? (int)$dimensions[1] : 0;
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $width < 1 || $height < 1
        || $width > 8000 || $height > 8000 || $width * $height > 40000000) {
        throw new InvalidArgumentException('Fotografie má nepovolený formát nebo rozměry.');
    }
    $decoded = @imagecreatefromstring($bytes);
    if (!$decoded instanceof GdImage) {
        throw new InvalidArgumentException('Fotografii nelze dekódovat.');
    }
    imagedestroy($decoded);
    return ['bytes' => $bytes, 'meta' => ['sha256' => hash('sha256', $bytes), 'mime_type' => $mime,
        'byte_size' => $size, 'width_px' => $width, 'height_px' => $height]];
}

function barPrivateImageDirectory(): string
{
    $directory = privateStorageEnsureDirectory(PRIVATE_STORAGE_BAR_IMAGES);
    $resolved = realpath($directory);
    if ($resolved === false || is_link($directory) || is_link(privateStorageRoot())) {
        throw new RuntimeException('Soukromé úložiště fotografií není běžný adresář.');
    }
    foreach ([dirname(__DIR__, 2), (string)($_SERVER['DOCUMENT_ROOT'] ?? '')] as $webRoot) {
        $web = $webRoot === '' ? false : realpath($webRoot);
        if ($web !== false && ($resolved === $web || str_starts_with($resolved . DIRECTORY_SEPARATOR, $web . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Fotografie baru musí být mimo veřejný web.');
        }
    }
    return $resolved;
}

/** Files are immutable and addressed by content; rollback leaves no publicly reachable orphan. */
function barStoreTransferImage(array $image): string
{
    $extension = match ($image['meta']['mime_type']) {
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        default => throw new InvalidArgumentException('Nepodporovaná fotografie.'),
    };
    $name = $image['meta']['sha256'] . '.' . $extension;
    if (!preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D', $name)
        || !hash_equals($image['meta']['sha256'], hash('sha256', $image['bytes']))) {
        throw new InvalidArgumentException('Otisk fotografie nesouhlasí.');
    }
    $path = barPrivateImageDirectory() . DIRECTORY_SEPARATOR . $name;
    $handle = @fopen($path, 'x+b');
    if ($handle === false) {
        if (is_link($path) || !is_file($path) || hash_file('sha256', $path) !== $image['meta']['sha256']) {
            throw new RuntimeException('Cílová fotografie existuje s jiným obsahem nebo ji nelze bezpečně vytvořit.');
        }
    } else {
        try {
            if (fwrite($handle, $image['bytes']) !== strlen($image['bytes']) || !fflush($handle)) {
                throw new RuntimeException('Fotografii se nepodařilo úplně uložit.');
            }
        } finally {
            fclose($handle);
        }
        @chmod($path, 0600);
    }
    return 'private://bar-images/' . $name;
}

function barTransferAssetPath(array $asset): string
{
    if (!is_string($asset['storage_key'] ?? null)
        || !preg_match('~^private://bar-images/([a-f0-9]{64}\.(?:jpg|png|webp))$~D', $asset['storage_key'], $match)) {
        throw new RuntimeException('Neplatná uložená fotografie.');
    }
    $path = barPrivateImageDirectory() . DIRECTORY_SEPARATOR . $match[1];
    if (is_link($path) || !is_file($path) || hash_file('sha256', $path) !== $asset['sha256_hex']) {
        throw new RuntimeException('Fotografie chybí nebo má jiný otisk.');
    }
    return $path;
}
