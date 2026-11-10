<?php

declare(strict_types=1);

require_once __DIR__ . '/requests.php';

/** Immutable publication selects products; current catalogue prices remain the POS/menu source of truth. */

function barActiveOffer(PDO $pdo): ?array
{
    $row = $pdo->query('SELECT p.id,p.config_json FROM kis_bar_state s JOIN kis_bar_offer_publications p ON p.id=s.active_offer_publication_id WHERE s.id=1')->fetch(PDO::FETCH_ASSOC);
    return $row ? ['publication_id' => (int)$row['id']] + json_decode($row['config_json'], true, 16, JSON_THROW_ON_ERROR) : null;
}

function barFilterPublishedProducts(PDO $pdo, array $rows): array
{
    $offer = barActiveOffer($pdo);
    return $offer === null ? $rows : array_values(array_filter(
        $rows,
        static fn (array $row): bool => in_array((int)$row['id'], $offer['products'], true)
    ));
}

function barSaveOffer(PDO $pdo, int $id, int $version, array $input, string $reason, string $token): array
{
    $title = barSourceText($input['title'] ?? '', 150, true);
    $subtitle = barSourceText($input['subtitle'] ?? '', 500);
    $layout = $input['layout'] ?? '';
    if (!in_array($layout, ['text', 'photos'], true) || !is_array($input['products'] ?? null) || count($input['products']) > 500) {
        throw new InvalidArgumentException('Vyberte zobrazení a nejvýše 500 produktů.');
    }
    $ids = [];
    foreach ($input['products'] as $productId) {
        $ids[] = barSourceInteger($productId, 1, 2147483647);
    }
    $ids = array_values(array_unique($ids));
    $reason = barReason($reason);
    return barRunRequest($pdo, 'offer_save', $token, [$id, $version, $title, $subtitle, $layout, $ids, $reason], function (
        array $actor,
        string $suffix
    ) use ($pdo, $id, $version, $title, $subtitle, $layout, $ids, $reason): array {
        if ($id > 0) {
            $query = $pdo->prepare('SELECT lock_version FROM kis_bar_offers WHERE id=?' . $suffix);
            $query->execute([$id]);
            if ((int)$query->fetchColumn() !== $version) {
                throw new BarConflictException('Nabídka se změnila. Načtěte ji znovu.');
            }
        }
        if ($ids !== []) {
            $query = $pdo->prepare('SELECT COUNT(*) FROM kis_bar_products WHERE archived=0 AND on_terminal=1 AND id IN (' . implode(
                ',',
                array_fill(0, count($ids), '?')
            ) . ')');
            $query->execute($ids);
            if ((int)$query->fetchColumn() !== count($ids)) {
                throw new DomainException('Nabídka může obsahovat pouze aktivní produkty povolené pro prodej.');
            }
        }
        $json = json_encode($ids, JSON_THROW_ON_ERROR);
        if ($id > 0) {
            $pdo->prepare('UPDATE kis_bar_offers SET title=?,subtitle=?,layout=?,selection_json=?,updated_by=?,lock_version=lock_version+1 WHERE id=?')
                ->execute([$title, $subtitle, $layout, $json, $actor['id'], $id]);
        } else {
            $pdo->prepare('INSERT INTO kis_bar_offers(title,subtitle,layout,selection_json,updated_by) VALUES(?,?,?,?,?)')->execute([$title,
     $subtitle, $layout, $json, $actor['id']]);
            $id = (int)$pdo->lastInsertId();
        }
        barEvent($pdo, $actor['id'], 'offer', $id, 'saved', ['products' => $ids, 'reason' => $reason]);
        return ['offer_id' => $id];
    }, 'offers_manage');
}

function barPublishOffer(PDO $pdo, int $id, int $version, string $reason, string $token): array
{
    $reason = barReason($reason);
    return barRunRequest($pdo, 'offer_publish', $token, [$id, $version, $reason], function (array $actor, string $suffix) use (
        $pdo,
        $id,
        $version,
        $reason
    ): array {
        $query = $pdo->prepare('SELECT * FROM kis_bar_offers WHERE id=?' . $suffix);
        $query->execute([$id]);
        $offer = $query->fetch(PDO::FETCH_ASSOC);
        if (!$offer || (int)$offer['lock_version'] !== $version) {
            throw new BarConflictException('Nabídka se změnila. Načtěte ji znovu.');
        }
        $config = ['title' => $offer['title'], 'subtitle' => $offer['subtitle'], 'layout' => $offer['layout'], 'products' => json_decode(
            $offer['selection_json'],
            true,
            8,
            JSON_THROW_ON_ERROR
        )];
        $pdo->prepare('INSERT INTO kis_bar_offer_publications(offer_id,config_json,published_by) VALUES(?,?,?)')
            ->execute([$id, json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $actor['id']]);
        $publication = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE kis_bar_state SET active_offer_publication_id=? WHERE id=1')->execute([$publication]);
        barEvent($pdo, $actor['id'], 'offer', $id, 'published', ['publication_id' => $publication, 'reason' => $reason]);
        return ['offer_id' => $id, 'publication_id' => $publication];
    }, 'offers_manage');
}

/** This exact projection is safe for anonymous display; it contains no costs, quantities or supplier information. */

function barPublicMenu(PDO $pdo): array
{
    $offer = barActiveOffer($pdo);
    if ($offer === null || $offer['products'] === []) {
        return ['offer' => $offer, 'products' => []];
    }
    $query = $pdo->prepare('SELECT p.id,p.name,p.description,p.price_minor,p.sold_out,c.id AS category_id,c.name AS category,c.color_index,a.id AS image_id '
        . 'FROM kis_bar_products p JOIN kis_bar_categories c ON c.id=p.category_id LEFT JOIN kis_bar_assets a ON a.source_name=p.image_name '
        . 'WHERE p.archived=0 AND p.on_terminal=1 AND p.id IN (' . implode(',', array_fill(0, count($offer['products']), '?')) . ') ORDER BY c.sort_order,c.id,p.sort_order,p.id');
    $query->execute($offer['products']);
    return ['offer' => $offer, 'products' => $query->fetchAll(PDO::FETCH_ASSOC)];
}
