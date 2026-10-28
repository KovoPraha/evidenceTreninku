<?php

declare(strict_types=1);

require_once __DIR__ . '/shop_public_navigation.php';

/** Public program presentation only; account prices and checkout remain separate.
 * @return list<array<string,mixed>>
 */
function shopPublicCatalogCards(PDO $pdo): array
{
    $readContext = new ClubProgramReadContext($pdo);
    $products = [];
    foreach (shopPublicSaleableCatalog($pdo, $readContext) as $product) {
        // Classification uses the original publication, not a rewritten display title.
        $identity = $product;
        $identity['public_name'] = $product['variants'][0]['public_name'];
        $isProgram = shopProductRequiresAthleteFromData($identity, $product['categories']);
        if ($isProgram) {
            $product['images'] = array_values(array_filter(
                $product['images'],
                static fn (string $url): bool => shopStorefrontIsLocalImageUrl($url)
            ));
        }
        $product['program_restrictions'] = [];
        $product['program_offers'] = [];
        if ($isProgram) {
            foreach ($product['variants'] as $variant) {
                $offer = $readContext->offer((int) $variant['variant_id']);
                if ($offer) {
                    $label = clubProgramBirthYearLabel($offer);
                    if (!in_array($label, $product['program_restrictions'], true)) {
                        $product['program_restrictions'][] = $label;
                    }
                    $product['program_offers'][] = $offer;
                }
            }
        }
        $product['is_program'] = $isProgram;
        $products[] = $product;
    }
    return $products;
}
