<?php
declare(strict_types=1);

final class ShopOfferClassifier
{
    public const GOODS = 'goods';
    public const CLUB_EVENT = 'club_event';
    public const CAMP = 'camp';
    public const BOOKABLE_SERVICE = 'bookable_service';
    public const BOOKABLE_RENTAL = 'bookable_rental';
    public const RENTAL = 'rental';
    public const CUSTOM_QUOTE = 'custom_quote';
    public const UNCLASSIFIED = 'unclassified';

    /** @var list<string> */
    public const TYPES = [
        self::GOODS,
        self::CLUB_EVENT,
        self::CAMP,
        self::BOOKABLE_SERVICE,
        self::BOOKABLE_RENTAL,
        self::RENTAL,
        self::CUSTOM_QUOTE,
        self::UNCLASSIFIED,
    ];

    /**
     * Classification is only a staging proposal. It never authorizes an import
     * or creates a registration, booking, order or inventory movement.
     *
     * @param array<string,mixed> $product
     * @return array{type:string,confidence:string,needs_manual_review:bool,signals:list<string>}
     */
    public static function classify(array $product): array
    {
        $name = self::normalize((string)($product['name'] ?? ''));
        $itemType = self::normalize((string)($product['item_type'] ?? ''));
        $categories = [];

        $defaultCategory = trim((string)($product['default_category_path'] ?? ''));
        if ($defaultCategory !== '') {
            $categories[] = $defaultCategory;
        }
        foreach (($product['additional_category_paths'] ?? []) as $category) {
            if (is_string($category) && trim($category) !== '') {
                $categories[] = trim($category);
            }
        }

        /** @var array<string,list<string>> $strong */
        $strong = [];
        foreach ($categories as $category) {
            $normalized = self::normalize($category);
            $segments = self::categorySegments($normalized);
            if (self::containsAny($segments, ['příměstské tábory'])) {
                $strong[self::CAMP][] = 'category:camp';
            }
            if (self::containsAny($segments, ['kroužky'])) {
                $strong[self::CLUB_EVENT][] = 'category:club_event';
            }
            if (self::containsAny($segments, ['tréninky a zážitky', 'zátěžové testování'])) {
                $strong[self::BOOKABLE_SERVICE][] = 'category:bookable_service';
            }
            if (self::containsAny($segments, ['půjčovna'])
                || self::containsPrefix($segments, 'půjčovna kol')
            ) {
                $strong[self::RENTAL][] = 'category:rental';
            }
            if (self::containsAny($segments, ['přednášky a školení'])) {
                $strong[self::CUSTOM_QUOTE][] = 'category:custom_quote';
            }
            if (self::containsAny($segments, [
                'cyklistické oblečení', 'volnočasové oblečení', 'doplňky',
                'knihy', 'výprodej',
            ]) || self::containsCollapsed($segments, 'výprodej')) {
                $strong[self::GOODS][] = 'category:goods';
            }
        }

        if (str_contains($name, 'příměstský tábor')) {
            $strong[self::CAMP][] = 'name:camp';
        }
        if (str_contains($name, 'půjčovna') || str_contains($name, 'pronájem')) {
            $strong[self::RENTAL][] = 'name:rental';
        }
        if (str_contains($name, 'individuální domluva')) {
            $strong[self::CUSTOM_QUOTE][] = 'name:custom_quote';
        }

        foreach ($strong as $type => $signals) {
            $strong[$type] = array_values(array_unique($signals, SORT_STRING));
        }
        if (count($strong) === 2
            && isset($strong[self::BOOKABLE_SERVICE], $strong[self::RENTAL])
        ) {
            return self::result(
                self::BOOKABLE_RENTAL,
                'high',
                false,
                array_merge(
                    $strong[self::BOOKABLE_SERVICE],
                    $strong[self::RENTAL],
                    ['combination:bookable_service+rental']
                )
            );
        }
        if (count($strong) > 1) {
            $signals = [];
            foreach ($strong as $type => $typeSignals) {
                foreach ($typeSignals as $signal) {
                    $signals[] = $signal;
                }
                $signals[] = 'candidate:' . $type;
            }
            sort($signals, SORT_STRING);
            return self::result(self::UNCLASSIFIED, 'low', true, $signals);
        }
        if (count($strong) === 1) {
            $type = (string)array_key_first($strong);
            return self::result($type, 'high', false, $strong[$type]);
        }

        if ($itemType === 'service') {
            return self::result(
                self::BOOKABLE_SERVICE,
                'low',
                true,
                ['item_type:service', 'fallback:service_requires_schedule_review']
            );
        }
        if ($itemType === 'product') {
            return self::result(
                self::GOODS,
                'medium',
                true,
                ['item_type:product', 'fallback:product_requires_scope_review']
            );
        }

        return self::result(
            self::UNCLASSIFIED,
            'low',
            true,
            ['fallback:missing_classification_signal']
        );
    }

    /** @return array{type:string,confidence:string,needs_manual_review:bool,signals:list<string>} */
    private static function result(
        string $type,
        string $confidence,
        bool $needsManualReview,
        array $signals
    ): array {
        $signals = array_values(array_unique($signals, SORT_STRING));
        sort($signals, SORT_STRING);
        return [
            'type' => $type,
            'confidence' => $confidence,
            'needs_manual_review' => $needsManualReview,
            'signals' => $signals,
        ];
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /** @return list<string> */
    private static function categorySegments(string $category): array
    {
        $segments = preg_split('/\s*(?:>|\/|\||»|→)\s*/u', $category) ?: [];
        return array_values(array_filter(
            array_map(static fn (string $segment): string => trim($segment), $segments),
            static fn (string $segment): bool => $segment !== ''
        ));
    }

    /** @param list<string> $haystack @param list<string> $needles */
    private static function containsAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $haystack, true)) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $haystack */
    private static function containsPrefix(array $haystack, string $prefix): bool
    {
        foreach ($haystack as $value) {
            if ($value === $prefix || str_starts_with($value, $prefix . ' - ')) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $haystack */
    private static function containsCollapsed(array $haystack, string $needle): bool
    {
        foreach ($haystack as $value) {
            if (str_replace(' ', '', $value) === $needle) {
                return true;
            }
        }
        return false;
    }
}
