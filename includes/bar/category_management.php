<?php

declare(strict_types=1);

require_once __DIR__ . '/management.php';

function barCategories(PDO $pdo): array
{
    barActor($pdo, 'catalog_manage');
    return $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM kis_bar_products p WHERE p.category_id=c.id) AS product_count '
        . 'FROM kis_bar_categories c ORDER BY c.sort_order,c.id')->fetchAll(PDO::FETCH_ASSOC);
}

function barLockedCategory(PDO $pdo, int $id, int $version, string $suffix): array
{
    $query = $pdo->prepare('SELECT * FROM kis_bar_categories WHERE id=?' . $suffix);
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new DomainException('Kategorie neexistuje.');
    }
    if ((int)$row['lock_version'] !== $version) {
        throw new BarConflictException('Kategorii mezitím změnil jiný uživatel. Obnovte stránku a změnu zkontrolujte.');
    }
    return $row;
}

function barSaveCategory(PDO $pdo, int $id, int $version, array $input, string $reason): int
{
    $reason = barReason($reason);
    $name = trim(barSourceText($input['name'] ?? null, 255, true));
    $order = barSourceInteger($input['sort_order'] ?? 0, -2147483648, 2147483647);
    $color = barSourceInteger($input['color_index'] ?? 0, 0, 2147483647);
    if ($id < 0 || $version < 0 || ($id === 0 && $version !== 0)) {
        throw new InvalidArgumentException('Neplatná kategorie nebo verze.');
    }
    $pdo->beginTransaction();
    try {
        $suffix = barLockState($pdo);
        $actor = barActor($pdo, 'catalog_manage', true);
        $before = $id === 0 ? null : barLockedCategory($pdo, $id, $version, $suffix);
        $duplicate = $pdo->prepare('SELECT id FROM kis_bar_categories WHERE LOWER(TRIM(name))=LOWER(?) AND id<>?' . $suffix);
        $duplicate->execute([$name, $id]);
        if ($duplicate->fetchColumn()) {
            throw new DomainException('Kategorie s tímto názvem již existuje.');
        }
        if ($id === 0) {
            $pdo->prepare('INSERT INTO kis_bar_categories(name,sort_order,color_index) VALUES(?,?,?)')->execute([$name, $order, $color]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE kis_bar_categories SET name=?,sort_order=?,color_index=?,lock_version=lock_version+1 WHERE id=?')
                ->execute([$name, $order, $color, $id]);
        }
        barEvent(
            $pdo,
            $actor['id'],
            'category',
            $id,
            $before === null ? 'created' : 'updated',
            ['before' => $before, 'after' => ['name' => $name, 'sort_order' => $order, 'color_index' => $color], 'reason' => $reason]
        );
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function barDeleteEmptyCategory(PDO $pdo, int $id, int $version, string $reason): void
{
    $reason = barReason($reason);
    $pdo->beginTransaction();
    try {
        $suffix = barLockState($pdo);
        $actor = barActor($pdo, 'catalog_manage', true);
        $before = barLockedCategory($pdo, $id, $version, $suffix);
        $query = $pdo->prepare('SELECT id FROM kis_bar_products WHERE category_id=? LIMIT 1');
        $query->execute([$id]);
        if ($query->fetchColumn()) {
            throw new DomainException('Kategorie obsahuje produkty včetně případných archivovaných. Nejprve je přesuňte do jiné kategorie.');
        }
        $pdo->prepare('DELETE FROM kis_bar_categories WHERE id=?')->execute([$id]);
        barEvent($pdo, $actor['id'], 'category', $id, 'deleted_empty', ['before' => $before, 'reason' => $reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
