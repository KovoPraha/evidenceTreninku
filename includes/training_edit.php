<?php
declare(strict_types=1);

/** Read the current image set under the same row lock held by all training writers. */
function trainingEditStoredImages(PDO $pdo, int $trainingId): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Úprava obrázků vyžaduje transakci.');
    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $query = $pdo->prepare('SELECT obrazky FROM treninky WHERE id=?'.$lock);
    $query->execute([$trainingId]);
    $json = $query->fetchColumn();
    if ($json === false) throw new RuntimeException('Trénink nebyl nalezen.');
    $images = $json ? json_decode((string)$json, true) : [];
    return is_array($images) ? $images : [];
}

/** Only the sports lead may replace colleagues; disabled fields are not submitted. */
function trainingEditCoachIds(PDO $pdo, int $trainingId, int $actorId, bool $canManageOthers, array $submitted): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Úprava trenérů vyžaduje transakci.');
    if (!$canManageOthers) {
        $query = $pdo->prepare('SELECT trener_id FROM trenink_trener WHERE trenink_id=?');
        $query->execute([$trainingId]);
        $submitted = $query->fetchAll(PDO::FETCH_COLUMN);
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $submitted), static fn(int $id): bool => $id > 0)));
    if (!in_array($actorId, $ids, true)) array_unshift($ids, $actorId);
    return $ids;
}

/** An image removal must be both a stored reference of this training and a safe path. */
function trainingEditOwnedImageRemovals(array $stored, array $requested): array
{
    return array_values(array_unique(array_filter($requested, static function ($path) use ($stored): bool {
        return is_string($path) && in_array($path, $stored, true)
            && str_starts_with($path, 'nahrane_obrazky/')
            && !str_contains($path, '..') && !str_contains($path, '\\') && !str_contains($path, "\0");
    })));
}
