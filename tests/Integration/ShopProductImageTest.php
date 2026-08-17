<?php
declare(strict_types=1);

namespace Tests\Integration;

use FilesystemIterator;
use PDO;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

require_once dirname(__DIR__,2) . '/includes/shop_product_image.php';

final class ShopProductImageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shop-product-image-' . bin2hex(random_bytes(6));
        mkdir($this->root,0750,true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) return;
        $iterator=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach($iterator as$item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
        rmdir($this->root);
    }

    public function testSpoofedMimeIsRejectedWithoutDatabaseOrPublicFile(): void
    {
        $source=$this->root . DIRECTORY_SEPARATOR . 'fake.jpg';
        file_put_contents($source,"<?php echo 'not an image';");
        $pdo=$this->database();

        try {
            \shopProductImageAdd($pdo,7,501,$source,0,'Test podvrženého MIME.',true,false,$this->root);
            self::fail('Podvržený obrázek měl být odmítnut.');
        } catch (\ShopProductImageException $exception) {
            self::assertStringContainsString('skutečný JPG nebo PNG',$exception->getMessage());
        }
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_images')->fetchColumn());
        self::assertDirectoryDoesNotExist($this->root . DIRECTORY_SEPARATOR . 'uploads');
    }

    public function testImageLifecycleStripsExifAuditsOrderingAndQuarantinesRemoval(): void
    {
        $source=$this->jpegWithExifMarker();
        $pdo=$this->database();

        $added=\shopProductImageAdd($pdo,7,501,$source,20,'Přidání fotografie programu.',true,false,$this->root);
        self::assertMatchesRegularExpression('~^uploads/shop-products/[a-f0-9]{32}\.jpg$~D',$added['image_url']);
        $stored=\shopProductImagePath($added['image_url'],$this->root);
        self::assertNotNull($stored);
        self::assertFileExists($stored);
        self::assertStringNotContainsString('R5-SECRET-EXIF',(string)file_get_contents($stored));
        self::assertSame('image/jpeg',(new \finfo(FILEINFO_MIME_TYPE))->file($stored));

        $changed=\shopProductImageUpdateOrder($pdo,7,$added['id'],-5,'Hlavní fotografie má být první.',true);
        self::assertTrue($changed['changed']);
        self::assertSame(-5,(int)$pdo->query('SELECT sort_order FROM shop_product_images')->fetchColumn());

        $removed=\shopProductImageRemove($pdo,7,$added['id'],'Fotografie už není aktuální.',true,$this->root);
        self::assertTrue($removed['quarantined']);
        self::assertFileDoesNotExist($stored);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_images')->fetchColumn());
        $quarantine=glob($this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . '_to_delete' . DIRECTORY_SEPARATOR . 'shop-product-images' . DIRECTORY_SEPARATOR . '*');
        self::assertIsArray($quarantine);self::assertCount(1,$quarantine);self::assertFileExists($quarantine[0]);
        self::assertSame(['add_image','reorder_image','remove_image'],$pdo->query('SELECT action FROM shop_catalog_admin_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    private function jpegWithExifMarker(): string
    {
        $image=imagecreatetruecolor(12,8);
        self::assertInstanceOf(\GdImage::class,$image);
        $color=imagecolorallocate($image,25,100,180);imagefill($image,0,0,$color);
        ob_start();imagejpeg($image,null,90);$jpeg=ob_get_clean();imagedestroy($image);
        self::assertIsString($jpeg);
        $payload="Exif\0\0R5-SECRET-EXIF";
        $jpeg=substr($jpeg,0,2) . "\xFF\xE1" . pack('n',strlen($payload)+2) . $payload . substr($jpeg,2);
        $path=$this->root . DIRECTORY_SEPARATOR . 'source-with-exif.jpg';file_put_contents($path,$jpeg);
        self::assertStringContainsString('R5-SECRET-EXIF',(string)file_get_contents($path));
        return $path;
    }

    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(501,'Rajčátka')");
        $pdo->exec('CREATE TABLE shop_product_images(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,image_url TEXT,sort_order INTEGER)');
        $pdo->exec('CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,variant_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,reason TEXT)');
        return $pdo;
    }
}
