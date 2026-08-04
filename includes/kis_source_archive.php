<?php
declare(strict_types=1);

final class KisSourceArchiveException extends RuntimeException
{
}

const KIS_SOURCE_ARCHIVE_MAX_BYTES = 50 * 1024 * 1024;
const KIS_SOURCE_MANIFEST_CONTRACT = 'kis-source-manifest-v1';

/** @return list<string> */
function kisSourceKinds(): array
{
    return ['users', 'payments', 'rosters'];
}

function kisSourceConfiguredArchiveDirectory(): string
{
    $configured = getenv('KIS_SOURCE_ARCHIVE_DIR');
    if (is_string($configured) && trim($configured) !== '') {
        return trim($configured);
    }
    if (defined('JE_LOKALNE') && JE_LOKALNE === true) {
        $local = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'evidencePavel-kis-source-archive';
        if (!is_dir($local) && !mkdir($local, 0700, true) && !is_dir($local)) {
            throw new KisSourceArchiveException('Privatni localhost archiv KIS zdroju nelze vytvorit.');
        }
        return $local;
    }
    throw new KisSourceArchiveException('Pro KIS import nastavte privatni KIS_SOURCE_ARCHIVE_DIR mimo web aplikace.');
}

/** @return array{source_kind:string,contract_version:string,sha256:string,byte_size:int,original_filename:string} */
function kisSourceInspect(string $inputPath, string $sourceKind, string $contractVersion): array
{
    $sourceKind = strtolower(trim($sourceKind));
    $contractVersion = strtolower(trim($contractVersion));
    if (!in_array($sourceKind, kisSourceKinds(), true)) {
        throw new InvalidArgumentException('Nepodporovaný typ KIS zdroje.');
    }
    if (preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D', $contractVersion) !== 1) {
        throw new InvalidArgumentException('Verze KIS kontraktu nemá platný formát.');
    }
    if ($inputPath === '' || str_contains($inputPath, "\0") || str_contains($inputPath, '://')) {
        throw new InvalidArgumentException('KIS zdroj musí být lokální soubor.');
    }
    if (is_link($inputPath)) {
        throw new KisSourceArchiveException('Symbolické odkazy nejsou povolené.');
    }
    $resolved = realpath($inputPath);
    if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
        throw new KisSourceArchiveException('KIS zdroj není čitelný běžný soubor.');
    }
    $size = filesize($resolved);
    if ($size === false || $size < 1 || $size > KIS_SOURCE_ARCHIVE_MAX_BYTES) {
        throw new KisSourceArchiveException('KIS zdroj je prázdný nebo překračuje limit 50 MiB.');
    }
    $hash = hash_file('sha256', $resolved);
    if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
        throw new KisSourceArchiveException('Hash KIS zdroje se nepodařilo vypočítat.');
    }
    return [
        'source_kind' => $sourceKind,
        'contract_version' => $contractVersion,
        'sha256' => $hash,
        'byte_size' => (int)$size,
        'original_filename' => mb_substr(basename($resolved), 0, 255, 'UTF-8'),
    ];
}

/**
 * @return array{id:int,created_file:bool,created_record:bool,source_kind:string,contract_version:string,sha256:string,byte_size:int,original_filename:string,storage_key:string}
 */
function kisSourceArchive(
    PDO $pdo,
    string $inputPath,
    string $sourceKind,
    string $contractVersion,
    string $archiveDirectory,
    ?int $actorId = null
): array {
    $source = kisSourceInspect($inputPath, $sourceKind, $contractVersion);
    $archiveRoot = kisSourceArchiveRoot($archiveDirectory);
    $contractKey = substr(hash('sha256', $source['contract_version']), 0, 8);
    $storageKey = $source['source_kind'] . '-' . $contractKey . '-' . $source['sha256'] . '.raw';
    $target = $archiveRoot . DIRECTORY_SEPARATOR . $storageKey;
    $resolvedInput = realpath($inputPath);
    if ($resolvedInput === false) {
        throw new KisSourceArchiveException('KIS zdroj před archivací zmizel.');
    }
    $createdFile = kisSourceArchiveFile($resolvedInput, $target, $source['sha256'], $source['byte_size']);

    $createdRecord = false;
    try {
        $statement = $pdo->prepare(
            'INSERT INTO kis_import_source_artifacts '
            . '(source_kind,contract_version,sha256,byte_size,original_filename,storage_key,archived_by) '
            . 'VALUES (?,?,?,?,?,?,?)'
        );
        $statement->execute([
            $source['source_kind'], $source['contract_version'], $source['sha256'],
            $source['byte_size'], $source['original_filename'], $storageKey, $actorId,
        ]);
        $createdRecord = true;
    } catch (PDOException $exception) {
        $existing = kisSourceArtifactByIdentity(
            $pdo,
            $source['source_kind'],
            $source['contract_version'],
            $source['sha256']
        );
        if ($existing === null) {
            throw $exception;
        }
    }
    $row = kisSourceArtifactByIdentity(
        $pdo,
        $source['source_kind'],
        $source['contract_version'],
        $source['sha256']
    );
    if ($row === null || !hash_equals((string)$row['storage_key'], $storageKey)
        || (int)$row['byte_size'] !== $source['byte_size']) {
        throw new KisSourceArchiveException('Metadata KIS archivu neodpovídají uloženému souboru.');
    }
    return array_merge($source, [
        'id' => (int)$row['id'],
        'storage_key' => $storageKey,
        'created_file' => $createdFile,
        'created_record' => $createdRecord,
    ]);
}

/** @param array<string,int> $artifactIds */
function kisSourceManifest(PDO $pdo, array $artifactIds): array
{
    $sources = [];
    foreach (kisSourceKinds() as $kind) {
        if (!isset($artifactIds[$kind])) {
            continue;
        }
        $id = (int)$artifactIds[$kind];
        $statement = $pdo->prepare('SELECT * FROM kis_import_source_artifacts WHERE id=?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals($kind, (string)$row['source_kind'])) {
            throw new KisSourceArchiveException('Artefakt neodpovídá typu KIS zdroje.');
        }
        $sources[$kind] = [
            'artifact_id' => $id,
            'contract_version' => (string)$row['contract_version'],
            'sha256' => (string)$row['sha256'],
            'byte_size' => (int)$row['byte_size'],
            'storage_key' => (string)$row['storage_key'],
        ];
    }
    if ($sources === []) {
        throw new InvalidArgumentException('Manifest musí obsahovat alespoň jeden KIS zdroj.');
    }
    $manifest = ['contract' => KIS_SOURCE_MANIFEST_CONTRACT, 'sources' => $sources];
    $fingerprintSources = [];
    foreach ($sources as $kind => $source) {
        $fingerprintSources[$kind] = array_diff_key($source, ['artifact_id' => true]);
    }
    $manifest['fingerprint'] = hash(
        'sha256',
        json_encode(
            ['contract' => KIS_SOURCE_MANIFEST_CONTRACT, 'sources' => $fingerprintSources],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )
    );
    return $manifest;
}

function kisSourceArchiveRoot(string $archiveDirectory): string
{
    if ($archiveDirectory === '' || str_contains($archiveDirectory, "\0")
        || str_contains($archiveDirectory, '://') || is_link($archiveDirectory)) {
        throw new KisSourceArchiveException('Archivní adresář musí být lokální a nesmí být odkaz.');
    }
    $root = realpath($archiveDirectory);
    if ($root === false || !is_dir($root) || !is_writable($root)) {
        throw new KisSourceArchiveException('Archivní adresář musí předem existovat a být zapisovatelný.');
    }
    $webRoot = realpath(dirname(__DIR__));
    if ($webRoot !== false && kisSourcePathWithin($root, $webRoot)) {
        throw new KisSourceArchiveException('KIS archiv nesmí být ve webrootu aplikace.');
    }
    return rtrim($root, DIRECTORY_SEPARATOR);
}

function kisSourcePathWithin(string $path, string $parent): bool
{
    $normalize = static fn(string $value): string => strtolower(
        rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value), DIRECTORY_SEPARATOR)
    );
    $path = $normalize($path);
    $parent = $normalize($parent);
    return $path === $parent || str_starts_with($path, $parent . DIRECTORY_SEPARATOR);
}

function kisSourceArchiveFile(string $inputPath, string $target, string $expectedHash, int $expectedSize): bool
{
    if (file_exists($target) || is_link($target)) {
        kisSourceAssertArchivedFile($target, $expectedHash, $expectedSize);
        return false;
    }
    $input = fopen($inputPath, 'rb');
    $output = fopen($target, 'x+b');
    if ($input === false || $output === false) {
        if (is_resource($input)) {
            fclose($input);
        }
        if (is_resource($output)) {
            fclose($output);
        }
        if (file_exists($target)) {
            kisSourceAssertArchivedFile($target, $expectedHash, $expectedSize);
            return false;
        }
        throw new KisSourceArchiveException('KIS zdroj se nepodařilo bezpečně archivovat.');
    }
    try {
        $copied = stream_copy_to_stream($input, $output);
        if ($copied !== $expectedSize || !fflush($output)) {
            throw new KisSourceArchiveException('Archivní kopie KIS zdroje není úplná.');
        }
    } catch (Throwable $exception) {
        fclose($input);
        fclose($output);
        @unlink($target);
        throw $exception;
    }
    fclose($input);
    fclose($output);
    @chmod($target, 0600);
    kisSourceAssertArchivedFile($target, $expectedHash, $expectedSize);
    return true;
}

function kisSourceAssertArchivedFile(string $path, string $expectedHash, int $expectedSize): void
{
    if (is_link($path) || !is_file($path) || filesize($path) !== $expectedSize) {
        throw new KisSourceArchiveException('Existující KIS archivní soubor má jinou velikost nebo typ.');
    }
    $actualHash = hash_file('sha256', $path);
    if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
        throw new KisSourceArchiveException('Existující KIS archivní soubor má jiný obsah.');
    }
}

function kisSourceArtifactByIdentity(PDO $pdo, string $kind, string $contract, string $hash): ?array
{
    $statement = $pdo->prepare(
        'SELECT * FROM kis_import_source_artifacts '
        . 'WHERE source_kind=? AND contract_version=? AND sha256=?'
    );
    $statement->execute([$kind, $contract, $hash]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
