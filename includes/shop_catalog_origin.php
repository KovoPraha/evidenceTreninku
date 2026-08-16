<?php
declare(strict_types=1);

final class ShopCatalogOrigin
{
    public const IMPORT = 'import';
    public const MANUAL = 'manual';

    /** @var list<string> */
    public const VALUES = [self::IMPORT, self::MANUAL];
}

function shopCatalogAssertProductOrigin(
    string $origin,
    ?int $sourceCandidateId,
    ?int $sourceRunId,
    ?int $createdByTrainerId
): void {
    if (!in_array($origin, ShopCatalogOrigin::VALUES, true)) {
        throw new InvalidArgumentException('Neznámý původ produktu v katalogu.');
    }
    if ($origin === ShopCatalogOrigin::IMPORT) {
        if (($sourceCandidateId ?? 0) < 1 || ($sourceRunId ?? 0) < 1) {
            throw new InvalidArgumentException(
                'Importovaný produkt musí odkazovat na zdrojový produkt i importní běh.'
            );
        }
        if ($createdByTrainerId !== null && $createdByTrainerId < 1) {
            throw new InvalidArgumentException('Neplatný autor produktu v katalogu.');
        }
        return;
    }

    if ($sourceCandidateId !== null || $sourceRunId !== null) {
        throw new InvalidArgumentException('Ručně založený produkt nesmí odkazovat na importní zdroj.');
    }
    if (($createdByTrainerId ?? 0) < 1) {
        throw new InvalidArgumentException('Ručně založený produkt musí mít uvedeného administrátora.');
    }
}

function shopCatalogAssertVariantOrigin(
    string $origin,
    ?int $sourceCandidateId,
    ?int $createdByTrainerId,
    string $productOrigin
): void {
    if (!in_array($origin, ShopCatalogOrigin::VALUES, true)
        || !in_array($productOrigin, ShopCatalogOrigin::VALUES, true)
    ) {
        throw new InvalidArgumentException('Neznámý původ varianty v katalogu.');
    }
    if ($origin !== $productOrigin) {
        throw new InvalidArgumentException('Varianta musí mít stejný původ jako její produkt.');
    }
    if ($origin === ShopCatalogOrigin::IMPORT) {
        if (($sourceCandidateId ?? 0) < 1) {
            throw new InvalidArgumentException('Importovaná varianta musí odkazovat na zdrojovou variantu.');
        }
        if ($createdByTrainerId !== null && $createdByTrainerId < 1) {
            throw new InvalidArgumentException('Neplatný autor varianty v katalogu.');
        }
        return;
    }

    if ($sourceCandidateId !== null) {
        throw new InvalidArgumentException('Ručně založená varianta nesmí odkazovat na importní zdroj.');
    }
    if (($createdByTrainerId ?? 0) < 1) {
        throw new InvalidArgumentException('Ručně založená varianta musí mít uvedeného administrátora.');
    }
}
