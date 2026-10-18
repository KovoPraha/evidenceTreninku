<?php
declare(strict_types=1);

/** @return array{0:int,1:int}|null */
function clubLandingAgeRange(string $label): ?array
{
    if (preg_match('/(?<!\d)(\d{1,2})\s*[–-]\s*(\d{1,2})(?!\d)/u', $label, $match) === 1) {
        $from=(int)$match[1];$to=(int)$match[2];
        return $from <= $to && $to <= 99 ? [$from,$to] : null;
    }
    return null;
}

/** @param array<string,mixed> $program */
function clubLandingFirstSaleableProductId(array $program): int
{
    foreach (($program['offers'] ?? []) as $offer) {
        if (!empty($offer['saleable']) && (int)($offer['product_id'] ?? 0) > 0) {
            return (int)$offer['product_id'];
        }
    }
    return 0;
}

/** @param list<array<string,mixed>> $offers */
function clubLandingLowestOffer(array $offers): ?array
{
    if ($offers === []) return null;
    $lowest = null;
    foreach ($offers as $offer) {
        if ($lowest === null || (int)$offer['amount_minor'] < (int)$lowest['amount_minor']) $lowest = $offer;
    }
    return $lowest;
}

/** @param list<array<string,mixed>> $offers */
function clubLandingYearSaving(array $offers): int
{
    $byPeriod = [];
    foreach ($offers as $offer) {
        $period = (string)($offer['purchase_option'] ?? '');
        if (in_array($period, ['full_year', 'first_half', 'second_half'], true)) $byPeriod[$period] = $offer;
    }
    if (count($byPeriod) !== 3) return 0;
    if (count(array_unique(array_map(static fn(array $offer): string => (string)$offer['currency'], $byPeriod))) !== 1) return 0;
    return max(0, (int)$byPeriod['first_half']['amount_minor'] + (int)$byPeriod['second_half']['amount_minor'] - (int)$byPeriod['full_year']['amount_minor']);
}
