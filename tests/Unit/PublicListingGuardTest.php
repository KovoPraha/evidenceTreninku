<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public_listing_guard.php';

final class PublicListingGuardTest extends TestCase
{
    public function testTechnicalTrainerNeedsVisibleTestPrefix(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,jmeno TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'kis-superadmin-test@velocota.com','KIS testovací administrátor'),(2,'coach@example.test','Skutečný trenér')");

        \publicListingValidateTechnicalOwner($pdo, 1, 'TEST - Individuální lekce');
        \publicListingValidateTechnicalOwner($pdo, 2, 'Běžná individuální lekce');

        $this->expectException(InvalidArgumentException::class);
        \publicListingValidateTechnicalOwner($pdo, 1, 'Běžná individuální lekce');
    }
}
