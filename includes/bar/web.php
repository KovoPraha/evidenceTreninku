<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/editor_view.php';

function barH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function barRequireWebActor(PDO $pdo, string $capability): array
{
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (!isset($_SESSION['trener_id'])) {
        // All bar entry points live one directory below the shared staff login.
        // An anonymous guard must work even before URL configuration is loaded.
        header('Location: ../login.php', true, 302);
        exit;
    }
    try {
        return barActor($pdo, $capability);
    } catch (DomainException $error) {
        http_response_code(403);
        exit(barH($error->getMessage()));
    }
}

function barRenderCatalog(PDO $pdo, array $rows, bool $administration, ?array $editor = null): void
{
    ?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $administration ? 'Katalog baru' : 'Nabídka stánku' ?> | KIS</title>
    <?php appUiAssets(); ?>
</head>
<body>
<?php require dirname(__DIR__, 2) . '/hlavicka.php'; ?>
<main class="container py-4">
    <h1><?= $administration ? 'Katalog baru' : 'Nabídka stánku' ?></h1>
    <?php if ($administration): ?>
        <p><a href="<?= barH(appUiUrl('bar/index.php')) ?>">Zobrazit nabídku stánku</a> ·
            <a href="<?= barH(appUiUrl('bar/catalog.php?new=1')) ?>">Nový produkt</a> ·
            <a href="<?= barH(appUiUrl('bar/categories.php')) ?>">Kategorie</a> ·
            <a href="<?= barH(appUiUrl('bar/stock.php')) ?>">Sklad</a></p>
    <?php endif; ?>
    <?php if ($editor !== null): barRenderProductEditor($editor); endif; ?>
    <?php if ($rows === []): ?>
        <p>V katalogu nejsou žádné produkty.</p>
    <?php else: ?>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Produkt</th><th>Kategorie</th><th>Cena</th><th>Stánek</th>
            <?php if ($administration): ?><th>Předsklad</th><th>Nákupní cena</th><th>Stav</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td>
                    <?php if ($row['image_id'] !== null): ?>
                        <img src="<?= barH(appUiUrl('bar/image.php?id=' . (int)$row['image_id'])) ?>" alt="" width="80" loading="lazy">
                    <?php endif; ?>
                    <strong><?= barH($row['name']) ?></strong><div><?= nl2br(barH($row['description'])) ?></div>
                    <?php if ($administration): ?><a href="<?= barH(appUiUrl('bar/catalog.php?id=' . (int)$row['id'])) ?>">Upravit</a><?php endif; ?>
                </td>
                <td><?= barH($row['category']) ?></td>
                <td><?= barH(barFormatMoney((int)$row['price_minor'])) ?></td>
                <td><?= (int)$row['is_composite'] === 1 ? 'Receptura' : ($row['stock_mode'] === 'sledovat' ? (int)$row['stock_stall'] : 'Nesledováno') ?>
                    <?= (int)$row['sold_out'] === 1 ? ' · Vyprodáno' : '' ?></td>
                <?php if ($administration): ?>
                    <td><?= (int)$row['is_composite'] === 1 ? 'Receptura' : ($row['stock_mode'] === 'sledovat' ? (int)$row['stock_office'] : 'Nesledováno') ?></td>
                    <td><?= $row['purchase_minor'] === null ? 'Neuvedena' : barH(barFormatMoney((int)$row['purchase_minor'])) ?></td>
                    <td><?= (int)$row['archived'] === 1 ? 'Archivováno' : ((int)$row['on_terminal'] === 1 ? 'V nabídce' : 'Mimo nabídku') ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</main>
</body>
</html>
    <?php
}
