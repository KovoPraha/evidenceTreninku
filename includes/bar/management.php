<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/catalog_transfer.php';

final class BarConflictException extends DomainException
{
}

function barLockState(PDO $pdo): string
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Změna baru vyžaduje transakci.');
    }
    $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    if (!$pdo->query('SELECT id FROM kis_bar_state WHERE id=1' . $suffix)->fetchColumn()) {
        throw new RuntimeException('Chybí stav modulu baru.');
    }
    return $suffix;
}

function barReason(string $reason): string
{
    $reason = trim(barSourceText($reason, 1000, true));
    if (mb_strlen($reason, 'UTF-8') < 5) {
        throw new InvalidArgumentException('Důvod musí mít alespoň pět znaků.');
    }
    return $reason;
}

function barLockedProduct(PDO $pdo, int $id, int $version, string $suffix): array
{
    $query = $pdo->prepare('SELECT * FROM kis_bar_products WHERE id=?' . $suffix);
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new DomainException('Produkt neexistuje.');
    }
    if ((int)$row['lock_version'] !== $version) {
        throw new BarConflictException('Produkt nebo zásobu mezitím změnil jiný uživatel. Obnovte přehled a změnu zkontrolujte.');
    }
    return $row;
}

/** Pending sales already remove reserved units from available stock; resolve them before a physical inventory or mode change. */
function barHasPendingStock(PDO $pdo, int $id, ?string $location = null): bool
{
    $query = $pdo->prepare("SELECT c.id FROM kis_bar_sale_components c JOIN kis_bar_sale_payments p ON p.sale_id=c.sale_id "
        . "WHERE c.product_id=? AND p.status='pending'" . ($location === null ? '' : ' AND c.location_code=?') . ' LIMIT 1');
    $query->execute($location === null ? [$id] : [$id, $location]);
    return (bool)$query->fetchColumn();
}

function barSaveProduct(PDO $pdo, int $id, int $version, array $input, string $reason): int
{
    $reason = barReason($reason);
    $values = ['name' => barSourceText($input['name'] ?? null, 255, true), 'description' => barSourceText($input['description'] ?? '', 65535),
        'category_id' => barSourceInteger($input['category_id'] ?? null, 1, 2147483647),
        'price_minor' => barSourceMoney(barPriceInput($input['price'] ?? null)),
        'purchase_minor' => ($input['purchase_price'] ?? '') === '' ? null : barSourceMoney(barPriceInput($input['purchase_price'])),
        'stock_mode' => $input['stock_mode'] ?? null, 'minimum_stock' => barSourceInteger($input['minimum_stock'] ?? 0)];
    if (!in_array($values['stock_mode'], ['sledovat', 'nesledovat'], true)) {
        throw new InvalidArgumentException('Neplatný skladový režim.');
    }
    foreach (['on_terminal', 'sold_out', 'favorite', 'archived'] as $key) {
        $values[$key] = barSourceInteger($input[$key] ?? 0, 0, 1);
    }
    $pdo->beginTransaction();
    try {
        $suffix = barLockState($pdo);
        $actor = barActor($pdo, 'catalog_manage', true);
        $before = $id > 0 ? barLockedProduct($pdo, $id, $version, $suffix) : null;
        $category = $pdo->prepare('SELECT id FROM kis_bar_categories WHERE id=?' . $suffix);
        $category->execute([$values['category_id']]);
        if (!$category->fetchColumn()) {
            throw new DomainException('Kategorie neexistuje.');
        }
        if ($before !== null && ((int)$before['stock_stall'] !== 0 || (int)$before['stock_office'] !== 0)
            && ($values['archived'] === 1 || $values['stock_mode'] !== $before['stock_mode'])) {
            throw new DomainException('Nejprve vypořádejte zásobu obou skladových míst.');
        }
        if ($before !== null && ($values['archived'] === 1 || $values['stock_mode'] === 'nesledovat') && barHasPendingStock($pdo, $id)) {
            throw new DomainException('Produkt je rezervovaný čekající platbou. Před archivací nebo vypnutím skladu platbu vyřešte.');
        }
        $used = $pdo->prepare('SELECT product_id FROM kis_bar_recipes WHERE ingredient_id=? LIMIT 1');
        $used->execute([$id]);
        if ($used->fetchColumn() && ($values['stock_mode'] === 'nesledovat' || $values['archived'] === 1)) {
            throw new DomainException('Nejprve odeberte surovinu z receptur.');
        }
        if ($id > 0) {
            $assignments = implode(',', array_map(static fn (string $key): string => $key . '=?', array_keys($values)));
            $pdo->prepare('UPDATE kis_bar_products SET ' . $assignments . ',lock_version=lock_version+1 WHERE id=?')
                ->execute([...array_values($values), $id]);
        } else {
            $pdo->prepare('INSERT INTO kis_bar_products(' . implode(',', array_keys($values)) . ',package_name) VALUES('
                . implode(',', array_fill(0, count($values), '?')) . ", '')")->execute(array_values($values));
            $id = (int)$pdo->lastInsertId();
        }
        $old = $before === null ? [] : array_intersect_key($before, $values);
        barEvent($pdo, $actor['id'], 'product', $id, $before === null ? 'created' : 'updated', ['before' => $old, 'after' => $values, 'reason' => $reason]);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function barPriceInput(mixed $value): mixed
{
    return is_string($value) ? str_replace(',', '.', trim($value)) : $value;
}

function barSetRecipe(PDO $pdo, int $id, int $version, array $ingredients, string $reason): void
{
    $reason = barReason($reason);
    if (count($ingredients) > 20) {
        throw new InvalidArgumentException('Receptura smí mít nejvýše dvacet surovin.');
    }
    $normalized = [];
    foreach ($ingredients as $row) {
        $ingredient = barSourceInteger($row['id'] ?? null, 1, 2147483647);
        if ($ingredient === $id || isset($normalized[$ingredient])) {
            throw new InvalidArgumentException('Receptura obsahuje vlastní nebo opakovanou surovinu.');
        }
        $normalized[$ingredient] = barSourceInteger($row['quantity'] ?? null, 1);
    }
    ksort($normalized, SORT_NUMERIC);
    $pdo->beginTransaction();
    try {
        $suffix = barLockState($pdo);
        $actor = barActor($pdo, 'catalog_manage', true);
        $product = barLockedProduct($pdo, $id, $version, $suffix);
        if ($normalized !== [] && barHasPendingStock($pdo, $id)) {
            throw new DomainException('Před změnou fyzické skladové položky na recepturu nejprve vyřešte její čekající platby.');
        }
        if ((int)$product['stock_stall'] !== 0 || (int)$product['stock_office'] !== 0 || (int)$product['archived'] === 1) {
            throw new DomainException('Recepturu lze měnit pouze u aktivního produktu s nulovou vlastní zásobou.');
        }
        $used = $pdo->prepare('SELECT product_id FROM kis_bar_recipes WHERE ingredient_id=? LIMIT 1');
        $used->execute([$id]);
        if ($normalized !== [] && $used->fetchColumn()) {
            throw new DomainException('Produkt je už surovinou jiné receptury.');
        }
        foreach ($normalized as $ingredient => $quantity) {
            $query = $pdo->prepare('SELECT p.stock_mode,p.archived,EXISTS(SELECT 1 FROM kis_bar_recipes r WHERE r.product_id=p.id) AS composite '
                . 'FROM kis_bar_products p WHERE p.id=?' . $suffix);
            $query->execute([$ingredient]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['stock_mode'] !== 'sledovat' || (int)$row['archived'] === 1 || (int)$row['composite'] === 1) {
                throw new DomainException('Surovina musí být aktivní jednoduchý sledovaný produkt.');
            }
        }
        $pdo->prepare('DELETE FROM kis_bar_recipes WHERE product_id=?')->execute([$id]);
        $insert = $pdo->prepare('INSERT INTO kis_bar_recipes(product_id,ingredient_id,quantity) VALUES(?,?,?)');
        foreach ($normalized as $ingredient => $quantity) {
            $insert->execute([$id, $ingredient, $quantity]);
        }
        $pdo->prepare('UPDATE kis_bar_products SET lock_version=lock_version+1 WHERE id=?')->execute([$id]);
        barEvent($pdo, $actor['id'], 'product', $id, 'recipe_changed', ['ingredients' => $normalized, 'reason' => $reason]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
