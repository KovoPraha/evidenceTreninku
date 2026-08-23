<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_attribute.php';

final class ShopAttributeFormTest extends TestCase
{
    public function testPlainRowsBecomeCanonicalStoredAttributes(): void
    {
        $definitions = [
            'Úroveň' => ['display_name' => 'Úroveň jezdce', 'active' => 1, 'value_type' => 'choice', 'choices' => ['Začátečník', 'Pokročilý']],
            'Věk' => ['display_name' => 'Doporučený věk', 'active' => 1, 'value_type' => 'number', 'choices' => []],
        ];
        $stored = shopAttributeFormJson([
            'attribute_keys' => ['Úroveň', 'Věk', ''],
            'attribute_values' => ['Začátečník', '8', ''],
        ], $definitions);

        self::assertSame(['Úroveň' => 'Začátečník', 'Věk' => 8], json_decode($stored, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testEmptyFormNeedsNoParameters(): void
    {
        self::assertSame('{}', shopAttributeFormJson(['attribute_keys' => [], 'attribute_values' => []]));
        self::assertSame('{}', shopAttributeFormJson([]));
    }

    public function testLegacyCorruptionProducesHumanReadableRecoveryMessage(): void
    {
        try {
            shopAttributeFormJson(['attributes_json' => '{broken']);
            self::fail('Poškozená starší data musí být odmítnuta.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Odeberte problematický parametr', $exception->getMessage());
            self::assertStringNotContainsString('JSON', $exception->getMessage());
        }
    }
}
