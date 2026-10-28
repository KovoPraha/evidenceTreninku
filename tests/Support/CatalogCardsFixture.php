<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

require_once __DIR__ . '/ProgramStorefrontFixture.php';

/** Original booking/eshop.php program presentation, retained as comparison oracle.
 * @return list<array<string,mixed>>
 */
function catalogCardsReference(PDO $pdo): array
{
    $products = [];
    foreach (shopPublicSaleableCatalog($pdo) as $product) {
        $isProgram = shopProductRequiresAthlete($pdo, (int) $product['product_id']);
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
                $offer = clubProgramOfferForVariant($pdo, (int) $variant['variant_id']);
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
