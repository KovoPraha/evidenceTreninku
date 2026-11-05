<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/category_management.php';
require_once dirname(__DIR__, 2) . '/includes/bar/photo_management.php';

final class BarCatalogManagementTest extends TestCase
{
    private PDO $pdo;
    private string $runtime;
    private string|false $oldStorage;

    protected function setUp(): void
    {
        $this->pdo = \barTestDatabase();
        \barTestLogin(3, 'bar_admin');
        $this->runtime = sys_get_temp_dir() . '/bar-catalog-' . bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        $this->oldStorage = getenv('APP_PRIVATE_STORAGE_ROOT');
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->runtime . '/private');
        $this->pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
        $this->pdo->exec("INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,stock_mode,package_name) "
            . "VALUES(1,1,'LOCALHOST Tea','',3500,'sledovat',''),(2,1,'LOCALHOST Coffee','',4000,'sledovat','')");
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->runtime, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->runtime);
        putenv($this->oldStorage === false ? 'APP_PRIVATE_STORAGE_ROOT' : 'APP_PRIVATE_STORAGE_ROOT=' . $this->oldStorage);
        $_SESSION = [];
    }

    private function image(string $name, int $red): string
    {
        $image = imagecreatetruecolor(8, 4);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, 25, 50));
        $path = $this->runtime . '/' . $name;
        imagepng($image, $path);
        imagedestroy($image);
        return $path;
    }

    public function testCategoryRenamePreservesProductsAndSourceAndRejectsStaleUpdate(): void
    {
        $this->pdo->exec('UPDATE kis_bar_categories SET source_id=71,color_index=8 WHERE id=1');
        \barSaveCategory($this->pdo, 1, 1, ['name' => 'LOCALHOST Nápoje', 'sort_order' => -10, 'color_index' => 8], 'LOCALHOST rename category');
        $row = \barCategories($this->pdo)[0];
        self::assertSame(71, (int)$row['source_id']);
        self::assertSame(8, (int)$row['color_index']);
        self::assertSame(2, (int)$row['product_count']);
        \barTestLogin(2, 'bar_operator');
        self::assertSame(['LOCALHOST Nápoje'], array_values(array_unique(array_column(\barCatalog($this->pdo), 'category'))));
        \barTestLogin(3, 'bar_admin');
        $this->expectException(\BarConflictException::class);
        \barSaveCategory($this->pdo, 1, 1, ['name' => 'STALE'], 'LOCALHOST stale category');
    }

    public function testDuplicateCategoryIsRejectedWithoutChangingVersionOrAudit(): void
    {
        $id = \barSaveCategory($this->pdo, 0, 0, ['name' => 'LOCALHOST Food'], 'LOCALHOST new category');
        self::assertGreaterThan(1, $id);
        try {
            \barSaveCategory($this->pdo, $id, 1, ['name' => ' LOCALHOST Drinks '], 'LOCALHOST duplicate category');
            self::fail('Duplicate accepted.');
        } catch (\DomainException $expected) {
            self::assertSame(1, (int)$this->pdo->query('SELECT lock_version FROM kis_bar_categories WHERE id=' . $id)->fetchColumn());
            self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        }
    }

    public function testCannotDeleteCategoryContainingArchivedProductsAndEmptyDeletionIsAudited(): void
    {
        $this->pdo->exec('UPDATE kis_bar_products SET archived=1');
        try {
            \barDeleteEmptyCategory($this->pdo, 1, 1, 'LOCALHOST nonempty deletion');
            self::fail('Populated category deleted.');
        } catch (\DomainException $expected) {
            self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_products')->fetchColumn());
        }
        $id = \barSaveCategory($this->pdo, 0, 0, ['name' => 'LOCALHOST Empty'], 'LOCALHOST empty category');
        \barDeleteEmptyCategory($this->pdo, $id, 1, 'LOCALHOST empty deletion');
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_categories')->fetchColumn());
        self::assertSame('deleted_empty', $this->pdo->query('SELECT action FROM kis_bar_events ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    public function testCategoryAndDeletionRollBackWhenAuditCannotBeWritten(): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_bar_audit BEFORE INSERT ON kis_bar_events BEGIN SELECT RAISE(FAIL,'LOCALHOST audit failure'); END");
        foreach (['save', 'delete'] as $action) {
            try {
                if ($action === 'save') {
                    \barSaveCategory($this->pdo, 1, 1, ['name' => 'CHANGED'], 'LOCALHOST failed update');
                } else {
                    $this->pdo->exec('DELETE FROM kis_bar_products');
                    \barDeleteEmptyCategory($this->pdo, 1, 1, 'LOCALHOST failed deletion');
                }
                self::fail('Missing audit did not fail.');
            } catch (\PDOException $expected) {
                self::assertSame('LOCALHOST Drinks', $this->pdo->query('SELECT name FROM kis_bar_categories WHERE id=1')->fetchColumn());
                self::assertSame(1, (int)$this->pdo->query('SELECT lock_version FROM kis_bar_categories WHERE id=1')->fetchColumn());
                self::assertFalse($this->pdo->inTransaction());
            }
        }
    }

    public function testPhotoReplacementAffectsOnlySelectedProductAndRemovalHidesUnusedAsset(): void
    {
        $first = $this->image('first.png', 50);
        \barSetProductImage($this->pdo, 1, 1, $first, 'LOCALHOST first photo');
        \barSetProductImage($this->pdo, 2, 1, $first, 'LOCALHOST shared photo');
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_assets')->fetchColumn());
        $second = $this->image('second.png', 150);
        \barSetProductImage($this->pdo, 1, 2, $second, 'LOCALHOST changed photo');
        $assets = $this->pdo->query('SELECT * FROM kis_bar_assets ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame(hash_file('sha256', $first), $assets[0]['sha256_hex']);
        self::assertSame(hash_file('sha256', $second), hash_file('sha256', \barTransferAssetPath($assets[1])));
        $names = $this->pdo->query('SELECT image_name FROM kis_bar_products ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotSame($names[0], $names[1]);
        \barSetProductImage($this->pdo, 1, 3, null, 'LOCALHOST remove photo');
        \barTestLogin(2, 'bar_operator');
        self::assertNull(\barCatalogAsset($this->pdo, (int)$assets[1]['id']));
        self::assertNotNull(\barCatalogAsset($this->pdo, (int)$assets[0]['id']));
        self::assertFileExists(\barTransferAssetPath($assets[1]));
    }

    public function testSamePhotoIsNoOpAndStaleVersionCannotAttachAnotherFile(): void
    {
        $path = $this->image('first.png', 50);
        \barSetProductImage($this->pdo, 1, 1, $path, 'LOCALHOST first photo');
        \barSetProductImage($this->pdo, 1, 2, $path, 'LOCALHOST same photo');
        self::assertSame(2, (int)$this->pdo->query('SELECT lock_version FROM kis_bar_products WHERE id=1')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        try {
            \barSetProductImage($this->pdo, 1, 1, $this->image('other.png', 180), 'LOCALHOST stale photo');
            self::fail('Stale photo accepted.');
        } catch (\BarConflictException $expected) {
            self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_assets')->fetchColumn());
            self::assertCount(1, glob($this->runtime . '/private/bar-images/*'));
        }
    }

    public function testInvalidPhotoIsRejectedAndAuditFailureLeavesNoReferencedFile(): void
    {
        file_put_contents($this->runtime . '/fake.png', '<svg onload="alert(1)"></svg>');
        try {
            \barSetProductImage($this->pdo, 1, 1, $this->runtime . '/fake.png', 'LOCALHOST rejected SVG');
            self::fail('SVG accepted as photograph.');
        } catch (\InvalidArgumentException $expected) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_assets')->fetchColumn());
        }
        $path = $this->image('first.png', 50);
        $this->pdo->exec("CREATE TRIGGER fail_bar_audit BEFORE INSERT ON kis_bar_events BEGIN SELECT RAISE(FAIL,'LOCALHOST audit failure'); END");
        try {
            \barSetProductImage($this->pdo, 1, 1, $path, 'LOCALHOST failed photo audit');
            self::fail('Photo committed without audit.');
        } catch (\PDOException $expected) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_assets')->fetchColumn());
            self::assertNull($this->pdo->query('SELECT image_name FROM kis_bar_products WHERE id=1')->fetchColumn());
        }
        $this->pdo->exec('DROP TRIGGER fail_bar_audit');
        \barSetProductImage($this->pdo, 1, 1, $path, 'LOCALHOST retried photo');
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_assets')->fetchColumn());
        self::assertCount(1, glob($this->runtime . '/private/bar-images/*'));
    }

    public function testOperatorAndRevokedAdminCannotMutateCatalogOrReadCategories(): void
    {
        $path = $this->image('first.png', 50);
        foreach (['operator', 'revoked'] as $scenario) {
            \barTestLogin($scenario === 'operator' ? 2 : 3, $scenario === 'operator' ? 'bar_operator' : 'bar_admin');
            if ($scenario === 'revoked') {
                $this->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=3');
            }
            foreach (['category', 'photo', 'read'] as $operation) {
                try {
                    match ($operation) {
                        'category' => \barSaveCategory($this->pdo, 1, 1, ['name' => 'UNAUTHORIZED'], 'LOCALHOST forbidden category'),
                        'photo' => \barSetProductImage($this->pdo, 1, 1, $path, 'LOCALHOST forbidden photo'),
                        'read' => \barCategories($this->pdo),
                    };
                    self::fail('Unauthorized catalog operation accepted.');
                } catch (\DomainException $expected) {
                    self::assertFalse($this->pdo->inTransaction());
                }
            }
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        self::assertDirectoryDoesNotExist($this->runtime . '/private');
    }
}
