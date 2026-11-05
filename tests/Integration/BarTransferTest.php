<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/includes/bar/transfer_verification.php';
require_once dirname(__DIR__, 2) . '/includes/unified_account.php';
require_once dirname(__DIR__, 2) . '/includes/trainer_password_reset.php';
require_once dirname(__DIR__, 2) . '/includes/password_reset.php';

final class BarTransferTest extends TestCase
{
    private string $runtime;
    private array $source;
    private string $hash;

    protected function setUp(): void
    {
        $this->runtime = sys_get_temp_dir() . '/kis-bar-transfer-' . bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        mkdir($this->runtime . '/source-images', 0700);
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->runtime . '/private');
        $image = imagecreatetruecolor(8, 4);
        imagepng($image, $this->runtime . '/source-images/tea.png');
        imagedestroy($image);
        $this->hash = password_hash('LOCALHOST-Lucka-Only-123', PASSWORD_DEFAULT);
        $this->source = ['categories' => [['id' => 41, 'nazev' => 'LOCALHOST Drinks']],
            'products' => [['id' => 91, 'kategorie_id' => 41, 'nazev' => 'LOCALHOST Tea', 'cena' => '35.00', 'nakupni_cena' => '11.01',
                'obrazek' => 'tea.png', 'skladovy_rezim' => 'sledovat', 'sklad' => 1000, 'predsklad' => 2000]],
            'recipes' => [], 'lucka' => ['id' => 77, 'jmeno' => 'lucka', 'heslo_hash' => $this->hash, 'role' => 'prodejce', 'schvalen' => 1]];
    }

    protected function tearDown(): void
    {
        putenv('APP_PRIVATE_STORAGE_ROOT');
        $_SESSION = [];
        $this->removeOwned($this->runtime);
    }

    private function removeOwned(string $path): void
    {
        if (!str_starts_with($path, $this->runtime)) {
            throw new \RuntimeException('Unsafe test cleanup.');
        }
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        foreach (new \FilesystemIterator($path) as $item) {
            $this->removeOwned($item->getPathname());
        }
        rmdir($path);
    }

    private function target(): PDO
    {
        $pdo = \barTestDatabase();
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,heslo_hash TEXT,'
            . 'email_overeno INT,aktivni INT,session_version INT,trener_id INT NULL)');
        \barTestLogin(1, 'system_admin');
        return $pdo;
    }

    private function bundle(): array
    {
        return \barBuildTransferBundle($this->source, $this->runtime . '/source-images');
    }

    public static function transferDrift(): array
    {
        return [
            'price' => ['UPDATE kis_bar_products SET price_minor=price_minor+1'],
            'stock' => ['UPDATE kis_bar_products SET stock_stall=1'],
            'name' => ["UPDATE kis_bar_products SET name='unexpected'"],
            'email marker' => ["UPDATE treneri SET technical_email=0 WHERE jmeno='lucka'"],
            'password' => ["UPDATE treneri SET heslo='unsupported' WHERE jmeno='lucka'"],
            'position' => ["UPDATE staff_user_positions SET position_code='bar_admin' WHERE trainer_id=(SELECT id FROM treneri WHERE jmeno='lucka')"],
            'image metadata' => ['UPDATE kis_bar_assets SET width_px=width_px+1'],
            'image content' => [null],
            'time contract' => ["UPDATE kis_bar_state SET clock_contract='legacy-local'"],
            'provenance link' => ['UPDATE kis_bar_state SET import_run_id=NULL'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('transferDrift')]
    public function testInitialVerificationRejectsDrift(?string $mutation): void
    {
        $bundle = $this->bundle();
        $pdo = $this->target();
        \barImportTransfer($pdo, $bundle, $this->runtime . '/source-images');
        self::assertTrue(\barVerifyInitialTransfer($pdo, $bundle, $this->runtime . '/source-images')['ok']);
        if ($mutation !== null) {
            $pdo->exec($mutation);
        } else {
            $asset = $pdo->query('SELECT * FROM kis_bar_assets')->fetch(PDO::FETCH_ASSOC);
            file_put_contents(\barTransferAssetPath($asset), 'invalid');
        }
        $this->expectException(\RuntimeException::class);
        \barVerifyInitialTransfer($pdo, $bundle, $this->runtime . '/source-images');
    }

    public function testPackageAndImportPreserveProductsPhotosAndOnlyLuckaWithZeroStock(): void
    {
        $bundle = $this->bundle();
        \barWriteTransferPackage($bundle, $this->runtime . '/source-images', $this->runtime . '/package');
        $pdo = $this->target();
        $result = \barImportTransfer($pdo, $bundle, $this->runtime . '/package/images');
        self::assertFalse($result['repeated']);
        $verification = \barVerifyInitialTransfer($pdo, $bundle, $this->runtime . '/package/images');
        self::assertTrue($verification['catalog_exact']);
        self::assertTrue($verification['images_exact']);
        self::assertTrue($verification['credential_hash_matches_package']);
        self::assertTrue($verification['operator_only']);
        self::assertTrue($verification['zero_stock']);
        self::assertTrue($verification['zero_operational_history']);
        $product = $pdo->query('SELECT * FROM kis_bar_products')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(91, (int)$product['source_id']);
        self::assertSame(3500, (int)$product['price_minor']);
        self::assertSame(1101, (int)$product['purchase_minor']);
        self::assertSame(0, (int)$product['stock_stall']);
        self::assertSame(0, (int)$product['stock_office']);
        $user = $pdo->query("SELECT * FROM treneri WHERE jmeno='lucka'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('info1@kovopraha.cz', $user['email']);
        self::assertSame($this->hash, $user['heslo']);
        self::assertTrue(password_verify('LOCALHOST-Lucka-Only-123', $user['heslo']));
        self::assertSame(1, (int)$user['technical_email']);
        self::assertSame(['bar_operator'], $pdo->query('SELECT position_code FROM staff_user_positions WHERE trainer_id=' . (int)$user['id'])->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_permissions')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
        $asset = $pdo->query('SELECT * FROM kis_bar_assets')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(hash_file('sha256', $this->runtime . '/source-images/tea.png'), hash_file('sha256', \barTransferAssetPath($asset)));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_import_runs')->fetchColumn());
        $pdo->exec('UPDATE kis_bar_products SET stock_stall=8,price_minor=9999');
        $repeat = \barImportTransfer($pdo, $bundle, $this->runtime . '/package/images');
        self::assertTrue($repeat['repeated']);
        self::assertSame(8, (int)$pdo->query('SELECT stock_stall FROM kis_bar_products')->fetchColumn());
        self::assertSame(9999, (int)$pdo->query('SELECT price_minor FROM kis_bar_products')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
    }

    public function testTechnicalAddressCannotCreateCustomerOrRequestEitherPasswordReset(): void
    {
        $pdo = $this->target();
        $result = \barImportTransfer($pdo, $this->bundle(), $this->runtime . '/source-images');
        self::assertNull(\unifiedAccountAuthenticate($pdo, 'info1@kovopraha.cz', 'LOCALHOST-Lucka-Only-123'));
        self::assertNull(\trainerPasswordResetTarget($pdo, 'info1@kovopraha.cz'));
        $user = $pdo->query('SELECT * FROM treneri WHERE id=' . $result['trainer_id'])->fetch(PDO::FETCH_ASSOC);
        try {
            \unifiedAccountEnsureTrainerCustomer($pdo, $user);
            self::fail('Technical e-mail became a verified customer.');
        } catch (\UnifiedAccountException $expected) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
        }
        $pdo->exec("INSERT INTO verejni_uzivatele(id,email,email_overeno,aktivni,trener_id) VALUES(1,'info1@kovopraha.cz',1,1," . $result['trainer_id'] . ')');
        self::assertNull(\passwordResetResolveTarget($pdo, 'info1@kovopraha.cz'));
        self::assertFalse(\passwordResetTargetStillAuthorized($pdo, ['target_type' => 'public', 'target_id' => 1, 'delivery_account_id' => 1]));
    }

    public function testCollisionAndNonemptyCatalogueNeverOverwriteExistingData(): void
    {
        $pdo = $this->target();
        $pdo->exec("UPDATE treneri SET email='INFO1@kovopraha.cz' WHERE id=4");
        try {
            \barImportTransfer($pdo, $this->bundle(), $this->runtime . '/source-images');
            self::fail('An existing account was overwritten.');
        } catch (\DomainException $expected) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products')->fetchColumn());
            self::assertSame(5, (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn());
        }
        $pdo->exec("UPDATE treneri SET email=NULL WHERE id=4; INSERT INTO kis_bar_categories(id,name) VALUES(1,'Existing')");
        $this->expectException(\DomainException::class);
        \barImportTransfer($pdo, $this->bundle(), $this->runtime . '/source-images');
    }

    public function testAuditFailureRollsBackCatalogueAccountAndProvenanceAndRetryReusesPrivateFiles(): void
    {
        $pdo = $this->target();
        $bundle = $this->bundle();
        $pdo->exec("CREATE TRIGGER deny_import_audit BEFORE INSERT ON kis_bar_events BEGIN SELECT RAISE(ABORT,'LOCALHOST audit failure'); END");
        try {
            \barImportTransfer($pdo, $bundle, $this->runtime . '/source-images');
            self::fail('An unaudited transfer was committed.');
        } catch (\PDOException $expected) {
            self::assertFalse($pdo->inTransaction());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_assets')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_import_runs')->fetchColumn());
            self::assertSame(5, (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn());
        }
        $pdo->exec('DROP TRIGGER deny_import_audit');
        self::assertFalse(\barImportTransfer($pdo, $bundle, $this->runtime . '/source-images')['repeated']);
    }

    public function testChangedImageAndMalformedBundleStopBeforeAnyWrite(): void
    {
        $pdo = $this->target();
        $bundle = $this->bundle();
        $bundle['catalog']['products'][0]['stock_stall'] = 123;
        $bundle['sha256'] = \barTransferHash($bundle);
        try {
            \barImportTransfer($pdo, $bundle, $this->runtime . '/source-images');
            self::fail('A hand-edited stock quantity was imported.');
        } catch (\InvalidArgumentException $expected) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products')->fetchColumn());
        }
        $bundle = $this->bundle();
        file_put_contents($this->runtime . '/source-images/tea.png', 'LOCALHOST not a photograph');
        $this->expectException(\InvalidArgumentException::class);
        \barImportTransfer($pdo, $bundle, $this->runtime . '/source-images');
    }

    public function testSourceReadNeedsNoWriteAndNeverExportsOtherUsers(): void
    {
        $source = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $source->exec('CREATE TABLE jidlo_kategorie(id INT,nazev TEXT); CREATE TABLE jidlo_produkty(id INT); '
            . 'CREATE TABLE jidlo_produkty_slozeni(produkt_id INT,slozka_id INT,mnozstvi INT); '
            . 'CREATE TABLE bar_uzivatele(id INT,jmeno TEXT,heslo_hash TEXT,role TEXT,schvalen INT)');
        $query = $source->prepare('INSERT INTO bar_uzivatele VALUES(?,?,?,?,?)');
        $query->execute([77, 'lucka', $this->hash, 'prodejce', 1]);
        $query->execute([88, 'trebe', 'LOCALHOST not exported', 'admin', 1]);
        $source->exec('PRAGMA query_only=ON');
        $data = \barReadLegacyTransferSource($source);
        self::assertSame('lucka', $data['lucka']['jmeno']);
        self::assertStringNotContainsString('trebe', json_encode($data, JSON_THROW_ON_ERROR));
        self::assertFalse($source->inTransaction());
    }

    public function testSourceAdminAndUnsupportedOrUnapprovedLuckaAreRejected(): void
    {
        $this->source['lucka']['role'] = 'admin';
        $this->expectException(\InvalidArgumentException::class);
        $this->bundle();
    }

    public function testBarAdministratorCannotProvisionAStaffAccount(): void
    {
        $pdo = $this->target();
        \barTestLogin(3, 'bar_admin');
        $this->expectException(\DomainException::class);
        \barImportTransfer($pdo, $this->bundle(), $this->runtime . '/source-images');
    }
}
