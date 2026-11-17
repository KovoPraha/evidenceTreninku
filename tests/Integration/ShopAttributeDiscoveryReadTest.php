<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/AttributeDiscoveryBaseline.php';

final class ShopAttributeDiscoveryReadTest extends TestCase
{
    public function testLegacyShapesAndExactUnicodeKeysMatchFrozenAlgorithm(): void
    {
        $pdo = $this->database();
        $rows = [null, '', 'null', 'false', 'true', '0', '1', '"scalar"', '{}', '[]', '["A","B"]',
            '{invalid', "{\"bad\":\"\xFF\"}", '{" A ":1,"A":2,"a":3,"Á":4,"á":5," ":6}',
            '{"0":1,"01":2,"-1":3,"9223372036854775808":4}', '{"nested":{"x":1},"list":[1,2]}',
            '{"escaped\\u0041":1,"escapedA":2}', '{"dup":1,"dup":2}',
            str_repeat('[', 65) . '0' . str_repeat(']', 65)];
        $this->insert($pdo, [...$rows, ...$rows, ...array_reverse($rows)]);
        $before = $pdo->query('SELECT * FROM shop_variants ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame(\attributeDiscoveryBaseline($pdo), \shopAttributeDiscoveredKeys($pdo));
        self::assertSame($before, $pdo->query('SELECT * FROM shop_variants ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testEvictionAndLargeDocumentsNeverLoseLateKeys(): void
    {
        $pdo = $this->database();
        $rows = [];
        for ($i = 0; $i < 300; $i++) {
            $rows[] = json_encode(['LOCALHOST_' . $i => $i], JSON_THROW_ON_ERROR);
        }
        $rows[] = $rows[0];
        $rows[] = json_encode(['padding' => str_repeat('x', 9000), 'lateA' => 1], JSON_THROW_ON_ERROR);
        $rows[] = json_encode(['padding' => str_repeat('x', 9000), 'lateB' => 1], JSON_THROW_ON_ERROR);
        $this->insert($pdo, [...$rows, ...array_reverse($rows)]);
        $actual = \shopAttributeDiscoveredKeys($pdo);
        self::assertSame(\attributeDiscoveryBaseline($pdo), $actual);
        self::assertContains('lateA', $actual);
        self::assertContains('lateB', $actual);
        self::assertCount(303, $actual);
    }

    public function testEveryCallSeesCurrentConnectionAndTransactionData(): void
    {
        $pdo = $this->database();
        $this->insert($pdo, array_fill(0, 150, '{"old":1}'));
        self::assertSame(['old'], \shopAttributeDiscoveredKeys($pdo));
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM shop_variants');
        $this->insert($pdo, ['{"new":2}']);
        self::assertSame(['new'], \shopAttributeDiscoveredKeys($pdo));
        $pdo->rollBack();
        self::assertSame(['old'], \shopAttributeDiscoveredKeys($pdo));
        self::assertSame(\attributeDiscoveryBaseline($pdo), \shopAttributeDiscoveredKeys($pdo));
        $other = $this->database();
        $this->insert($other, ['{"other_connection":1}']);
        self::assertSame(['other_connection'], \shopAttributeDiscoveredKeys($other));
    }

    public function testEmptyMissingAndOnlyInvalidValuesStayEmpty(): void
    {
        $pdo = $this->database();
        self::assertSame([], \shopAttributeDiscoveredKeys($pdo));
        $this->insert($pdo, array_fill(0, 300, '{invalid'));
        self::assertSame([], \shopAttributeDiscoveredKeys($pdo));
        $pdo->exec('DROP TABLE shop_variants');
        self::assertSame(\attributeDiscoveryBaseline($pdo), \shopAttributeDiscoveredKeys($pdo));
        self::assertSame([], \shopAttributeDiscoveredKeys($pdo));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY, attributes_json TEXT NULL)');
        return $pdo;
    }

    private function insert(PDO $pdo, array $rows): void
    {
        $statement = $pdo->prepare('INSERT INTO shop_variants(attributes_json) VALUES(?)');
        foreach ($rows as $row) {
            $statement->execute([$row]);
        }
    }
}
