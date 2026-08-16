<?php
declare(strict_types=1);

final class ShopCatalogOrigin
{
    public const IMPORT = 'import';
    public const MANUAL = 'manual';

    /** @var list<string> */
    public const VALUES = [self::IMPORT, self::MANUAL];
}

if (!defined('SHOP_MANUAL_SKU_PREFIX')) {
    define('SHOP_MANUAL_SKU_PREFIX', 'KP-');
}

function shopCatalogManualSkuPrefix(): string
{
    $prefix = (string)SHOP_MANUAL_SKU_PREFIX;
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{0,14}-$/D', $prefix) !== 1) {
        throw new RuntimeException('Konstanta SHOP_MANUAL_SKU_PREFIX nemá platný formát.');
    }
    return $prefix;
}

function shopCatalogManualExternalProductKey(): string
{
    return 'manual:' . bin2hex(random_bytes(16));
}

function shopCatalogAssertManualExternalProductKey(string $externalProductKey): void
{
    if (preg_match('/^manual:[a-f0-9]{32}$/D', $externalProductKey) !== 1) {
        throw new InvalidArgumentException('Ruční produkt musí mít vyhrazený náhodný klíč manual:.');
    }
}

function shopCatalogAssertManualSku(string $sku): void
{
    if (!str_starts_with($sku, shopCatalogManualSkuPrefix())
        || preg_match('/^[A-Z0-9_\/\-. ]{1,64}$/D', $sku) !== 1
    ) {
        throw new InvalidArgumentException(
            'Ruční SKU musí začínat prefixem ' . shopCatalogManualSkuPrefix()
            . ' a smí obsahovat pouze A-Z, 0-9, mezeru a _ / - .'
        );
    }
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
