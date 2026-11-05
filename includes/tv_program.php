<?php

declare(strict_types=1);

require_once __DIR__ . '/private_storage.php';
require_once __DIR__ . '/secure_upload.php';

const TV_CANVAS_WIDTH = 1920;
const TV_CANVAS_HEIGHT = 1080;
const TV_DOCUMENT_MAX_BYTES = 2 * 1024 * 1024;

final class TvConflictException extends RuntimeException
{
}

function tvJson(array $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function tvText(mixed $value, int $maximum, bool $required = false): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $maximum
        || ($required && trim($value) === '') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
        throw new InvalidArgumentException('Text je prázdný, příliš dlouhý nebo obsahuje nepovolené znaky.');
    }
    return $value;
}

function tvNumber(mixed $value, int $minimum, int $maximum): int
{
    if (!is_int($value) || $value < $minimum || $value > $maximum) {
        throw new InvalidArgumentException("Číselná hodnota musí být celé číslo od $minimum do $maximum.");
    }
    return $value;
}

function tvColor(mixed $value): string
{
    if (!is_string($value) || !preg_match('/^#[0-9a-f]{6}$/iD', $value)) {
        throw new InvalidArgumentException('Vyberte platnou barvu.');
    }
    return strtolower($value);
}

/** Only plain text and constrained layout values cross the publication boundary. */
function tvNormalizeDocument(array $input): array
{
    $name = trim(tvText($input['name'] ?? '', 120, true));
    $slides = $input['slides'] ?? null;
    if (!is_array($slides) || !array_is_list($slides) || count($slides) > 100) {
        throw new InvalidArgumentException('Program smí obsahovat nejvýše 100 snímků.');
    }
    $normalized = [];
    $ids = [];
    foreach ($slides as $slide) {
        if (!is_array($slide)) {
            throw new InvalidArgumentException('Neplatný snímek.');
        }
        $id = $slide['id'] ?? '';
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $id) || isset($ids[$id])) {
            throw new InvalidArgumentException('Snímky musí mít jedinečné identifikátory.');
        }
        $ids[$id] = true;
        if (!is_bool($slide['enabled'] ?? null) || !in_array($slide['fit'] ?? '', ['contain', 'cover'], true)) {
            throw new InvalidArgumentException('Neplatné nastavení snímku.');
        }
        $assetId = $slide['asset_id'] ?? null;
        if ($assetId !== null) {
            $assetId = tvNumber($assetId, 1, PHP_INT_MAX);
        }
        $blocks = $slide['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks) || count($blocks) > 20) {
            throw new InvalidArgumentException('Snímek smí obsahovat nejvýše 20 textových bloků.');
        }
        $texts = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !is_bool($block['bold'] ?? null)
                || !in_array($block['align'] ?? '', ['left', 'center', 'right'], true)) {
                throw new InvalidArgumentException('Neplatný textový blok.');
            }
            $x = tvNumber($block['x'] ?? null, 0, TV_CANVAS_WIDTH - 20);
            $width = tvNumber($block['width'] ?? null, 20, TV_CANVAS_WIDTH);
            if ($x + $width > TV_CANVAS_WIDTH) {
                throw new InvalidArgumentException('Textový blok přesahuje pravý okraj plátna.');
            }
            $opacity = $block['background_opacity'] ?? null;
            if ((!is_int($opacity) && !is_float($opacity)) || !is_finite((float)$opacity) || $opacity < 0 || $opacity > 1) {
                throw new InvalidArgumentException('Průhlednost podkladu musí být od 0 do 1.');
            }
            $texts[] = [
                'text' => tvText($block['text'] ?? '', 4000),
                'x' => $x, 'y' => tvNumber($block['y'] ?? null, 0, TV_CANVAS_HEIGHT - 1), 'width' => $width,
                'font_size' => tvNumber($block['font_size'] ?? null, 8, 400),
                'color' => tvColor($block['color'] ?? ''), 'bold' => $block['bold'], 'align' => $block['align'],
                'background' => tvColor($block['background'] ?? ''), 'background_opacity' => (float)$opacity,
            ];
        }
        $normalized[] = [
            'id' => $id, 'title' => trim(tvText($slide['title'] ?? '', 120, true)),
            'enabled' => $slide['enabled'], 'duration' => tvNumber($slide['duration'] ?? null, 1, 3600),
            'background' => tvColor($slide['background'] ?? ''), 'fit' => $slide['fit'],
            'asset_id' => $assetId, 'blocks' => $texts,
        ];
    }
    $document = ['name' => $name, 'slides' => $normalized];
    if (strlen(tvJson($document)) > TV_DOCUMENT_MAX_BYTES) {
        throw new InvalidArgumentException('Program je příliš velký. Zkraťte texty nebo uberte snímky.');
    }
    return $document;
}

function tvDecodeDocument(string $json): array
{
    if (strlen($json) > TV_DOCUMENT_MAX_BYTES) {
        throw new InvalidArgumentException('Program je příliš velký.');
    }
    $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($value)) {
        throw new InvalidArgumentException('Neplatný program.');
    }
    return tvNormalizeDocument($value);
}

function tvDocumentAssetIds(array $document, bool $enabledOnly = false): array
{
    $ids = [];
    foreach ($document['slides'] as $slide) {
        if ($slide['asset_id'] !== null && (!$enabledOnly || $slide['enabled'])) {
            $ids[] = (int)$slide['asset_id'];
        }
    }
    return array_values(array_unique($ids));
}

function tvAssertAssets(PDO $pdo, array $document): void
{
    $query = $pdo->prepare('SELECT storage_key FROM tv_assets WHERE id=?');
    foreach (tvDocumentAssetIds($document) as $id) {
        $query->execute([$id]);
        $key = $query->fetchColumn();
        if (!is_string($key) || privateStorageResolve($key) === null) {
            throw new InvalidArgumentException('Některý obrázek chybí. Nahrajte jej znovu.');
        }
    }
}

function tvProgramEvent(PDO $pdo, int $programId, int $actorId, string $action, array $detail = []): void
{
    $pdo->prepare('INSERT INTO tv_program_events(program_id,actor_id,action,detail_json) VALUES(?,?,?,?)')
        ->execute([$programId, $actorId, $action, tvJson($detail)]);
}

function tvCreateProgram(PDO $pdo, string $name, int $actorId): int
{
    $document = tvNormalizeDocument(['name' => $name, 'slides' => []]);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO tv_programs(name,draft_json) VALUES(?,?)')->execute([$document['name'], tvJson($document)]);
        $id = (int)$pdo->lastInsertId();
        tvProgramEvent($pdo, $id, $actorId, 'created');
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function tvProgram(PDO $pdo, int $id): ?array
{
    $query = $pdo->prepare('SELECT * FROM tv_programs WHERE id=?');
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Publication, restoration and archival share an optimistic lock and transaction. */
function tvMutateProgram(PDO $pdo, int $id, int $version, int $actorId, string $action, ?array $document = null, int $revisionId = 0): array
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $query = $pdo->prepare('SELECT * FROM tv_programs WHERE id=?' . $lock);
        $query->execute([$id]);
        $program = $query->fetch(PDO::FETCH_ASSOC);
        if (!$program) {
            throw new InvalidArgumentException('Program nebyl nalezen.');
        }
        if ((int)$program['lock_version'] !== $version) {
            throw new TvConflictException('Program mezitím změnil jiný uživatel. Obnovte stránku; vaše změny se nepřepsaly.');
        }
        $archived = (int)$program['archived'];
        $published = $program['published_revision_id'];
        $next = tvDecodeDocument((string)$program['draft_json']);
        if (in_array($action, ['save', 'publish'], true)) {
            if ($archived) {
                throw new InvalidArgumentException('Nejprve obnovte archivovaný program.');
            }
            $next = tvNormalizeDocument($document ?? []);
            tvAssertAssets($pdo, $next);
            if ($action === 'publish') {
                // Disabled slides belong only to the draft, including their texts and assets.
                $public = $next;
                $public['slides'] = array_values(array_filter($next['slides'], static fn (array $slide): bool => $slide['enabled']));
                $pdo->prepare('INSERT INTO tv_program_revisions(program_id,document_json,created_by) VALUES(?,?,?)')
                    ->execute([$id, tvJson($public), $actorId]);
                $published = (int)$pdo->lastInsertId();
                $insertAsset = $pdo->prepare('INSERT INTO tv_revision_assets(revision_id,asset_id) VALUES(?,?)');
                foreach (tvDocumentAssetIds($public) as $assetId) {
                    $insertAsset->execute([$published, $assetId]);
                }
            }
        } elseif ($action === 'restore_revision') {
            $revision = $pdo->prepare('SELECT document_json FROM tv_program_revisions WHERE id=? AND program_id=?');
            $revision->execute([$revisionId, $id]);
            $json = $revision->fetchColumn();
            if (!is_string($json)) {
                throw new InvalidArgumentException('Verze nebyla nalezena v tomto programu.');
            }
            $next = tvDecodeDocument($json);
            tvAssertAssets($pdo, $next);
        } elseif ($action === 'archive') {
            $archived = 1;
        } elseif ($action === 'unarchive') {
            $archived = 0;
            // Restoring an archived programme never silently resumes public playback.
            $published = null;
        } else {
            throw new InvalidArgumentException('Neznámá operace.');
        }
        $update = $pdo->prepare('UPDATE tv_programs SET name=?,draft_json=?,lock_version=lock_version+1,'
            . 'published_revision_id=?,archived=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND lock_version=?');
        $update->execute([$next['name'], tvJson($next), $published, $archived, $id, $version]);
        if ($update->rowCount() !== 1) {
            throw new TvConflictException('Program mezitím změnil jiný uživatel. Obnovte stránku.');
        }
        tvProgramEvent($pdo, $id, $actorId, $action, ['version' => $version + 1, 'revision_id' => $published, 'source_revision_id' => $revisionId]);
        $pdo->commit();
        return ['version' => $version + 1, 'published_revision_id' => $published, 'archived' => $archived, 'document' => $next];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function tvPublicProgram(PDO $pdo, int $id): ?array
{
    $query = $pdo->prepare('SELECT r.id,r.document_json FROM tv_programs p JOIN tv_program_revisions r '
        . 'ON r.id=p.published_revision_id AND r.program_id=p.id WHERE p.id=? AND p.archived=0');
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return ['revision' => (int)$row['id'], 'document' => tvDecodeDocument((string)$row['document_json'])];
}

function tvAsset(PDO $pdo, int $id, bool $preview = false): ?array
{
    $sql = 'SELECT a.* FROM tv_assets a WHERE a.id=?';
    if (!$preview) {
        $sql .= ' AND EXISTS (SELECT 1 FROM tv_revision_assets ra JOIN tv_programs p '
            . 'ON p.published_revision_id=ra.revision_id WHERE ra.asset_id=a.id AND p.archived=0)';
    }
    $query = $pdo->prepare($sql);
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function tvUploadAsset(PDO $pdo, string $source, int $actorId, bool $uploaded = true): array
{
    $name = bin2hex(random_bytes(16)) . '.jpg';
    $path = privateStorageEnsureDirectory(PRIVATE_STORAGE_TV_IMAGES) . DIRECTORY_SEPARATOR . $name;
    try {
        $meta = secureUploadReencodeImageToJpeg($source, $path, $uploaded);
        $key = 'private://' . PRIVATE_STORAGE_TV_IMAGES . '/' . $name;
        $pdo->prepare('INSERT INTO tv_assets(storage_key,width_px,height_px,byte_size,sha256_hex,created_by) VALUES(?,?,?,?,?,?)')
            ->execute([$key, $meta['width_px'], $meta['height_px'], $meta['byte_size'], $meta['sha256_hex'], $actorId]);
        return ['id' => (int)$pdo->lastInsertId(), 'width' => $meta['width_px'], 'height' => $meta['height_px']];
    } catch (Throwable $e) {
        if (is_file($path)) {
            unlink($path);
        }
        throw $e;
    }
}
