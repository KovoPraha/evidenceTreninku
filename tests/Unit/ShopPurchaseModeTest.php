<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_purchase_mode.php';

final class ShopPurchaseModeTest extends TestCase
{
    public function testReviewedNonParticipationProductsRemainGoodsInMixedClubCategory(): void
    {
        $pdo = $this->database();
        $this->product($pdo, 1, 'Trička', 'shoptet:pair:250');
        $this->product($pdo, 2, 'Uskladnění kol na školní pololetí', 'shoptet:pair:852');
        $pdo->exec("INSERT INTO shop_product_categories VALUES(1,'Volnočasové oblečení > Cyklo kroužek - trička')");

        self::assertFalse(\shopProductRequiresAthlete($pdo, 1));
        self::assertFalse(\shopProductRequiresAthlete($pdo, 2));
    }

    public function testMixedCategoryExceptionsDoNotReopenImportedClubEnrollment(): void
    {
        $pdo = $this->database();
        $this->product($pdo, 1, 'Aligátoři 1. pololetí', 'shoptet:pair:675');
        $this->product($pdo, 2, 'Cvičení rodičů s dětmi', 'shoptet:pair:999');
        $this->product($pdo, 3, 'Trička', 'shoptet:pair:250', 'program');
        $this->product($pdo, 4, 'Uskladnění kol na školní pololetí', 'shoptet:pair:852', 'program');
        $this->product($pdo, 5, 'Kroužek s tričkem', 'shoptet:pair:250');
        $this->product($pdo, 6, 'Trička', 'shoptet:pair:999');
        $this->product($pdo, 7, 'Trička', 'shoptet:pair:250', 'goods', 'manual');

        foreach (range(1, 7) as $id) {
            self::assertTrue(\shopProductRequiresAthlete($pdo, $id), 'Product ' . $id);
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,origin TEXT,external_product_key TEXT)');
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,public_name TEXT)');
        $pdo->exec('CREATE TABLE shop_product_categories(product_id INTEGER,category_path TEXT)');
        return $pdo;
    }

    private function product(PDO $pdo, int $id, string $name, string $key, string $type = 'goods', string $origin = 'import'): void
    {
        $pdo->prepare('INSERT INTO shop_products VALUES(?,?,?,?)')->execute([$id, $type, $origin, $key]);
        $pdo->prepare('INSERT INTO shop_product_publications VALUES(?,?)')->execute([$id, $name]);
        $pdo->prepare('INSERT INTO shop_product_categories VALUES(?,?)')->execute([$id, 'Kroužky']);
    }
}
