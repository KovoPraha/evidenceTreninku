<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_catalog_migration_assets.php';

final class ShopCatalogMigrationAssetsTest extends TestCase
{
    private string $directory;

    protected function setUp():void{$this->directory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'shop-assets-'.bin2hex(random_bytes(8));}
    protected function tearDown():void{$this->remove($this->directory);}

    public function testPreparesDeterministicMetadataFreeJpegManifest():void
    {
        $url='https://cdn.myshoptet.com/usr/test/product.png';$png=$this->png();
        $catalog=['source'=>['sha256'=>str_repeat('a',64)],'summary'=>['contract_ready'=>true],'products'=>[['images'=>[$url]]]];
        $manifest=\shopCatalogMigrationPrepareAssets($catalog,$this->directory,static fn(string$requested):string=>$requested===$url?$png:throw new \RuntimeException('Unexpected URL'));
        self::assertSame(\SHOP_CATALOG_ASSET_MANIFEST_VERSION,$manifest['version']);self::assertSame(1,$manifest['source_images']);self::assertSame(1,$manifest['unique_files']);
        $row=$manifest['images'][$url];self::assertMatchesRegularExpression('~^images/[a-f0-9]{32}\.jpg$~',$row['file']);self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D',$row['sha256']);
        $path=$this->directory.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$row['file']);self::assertFileExists($path);self::assertSame('image/jpeg',(new \finfo(FILEINFO_MIME_TYPE))->file($path));
        $loaded=\shopCatalogOneTimeManifest($this->directory.'/manifest.json',str_repeat('a',64));self::assertSame('uploads/shop-products/'.basename($row['file']),\shopCatalogOneTimeManifestImage($loaded,$url)['image_url']);
    }

    public function testRejectsImageOutsideAllowlistedCdn():void
    {
        $catalog=['source'=>['sha256'=>str_repeat('a',64)],'summary'=>['contract_ready'=>true],'products'=>[['images'=>['https://example.invalid/product.png']]]];
        $this->expectException(\ShopCatalogMigrationAssetException::class);\shopCatalogMigrationPrepareAssets($catalog,$this->directory,fn():string=>$this->png());
    }

    public function testUsesBoundedShoptetRenditionWithoutChangingSourceIdentity():void
    {
        $source='https://cdn.myshoptet.com/usr/shop.kovopraha.cz/user/shop/orig/172_product.png?abc123';
        self::assertSame('https://cdn.myshoptet.com/usr/shop.kovopraha.cz/user/shop/big/172_product.png?abc123',\shopCatalogMigrationDownloadUrl($source));
    }

    private function png():string{$image=imagecreatetruecolor(2,2);self::assertInstanceOf(\GdImage::class,$image);$red=imagecolorallocate($image,200,20,20);imagefill($image,0,0,$red);ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);self::assertIsString($bytes);return$bytes;}
    private function remove(string$directory):void{if(!is_dir($directory))return;foreach(new \FilesystemIterator($directory,\FilesystemIterator::SKIP_DOTS)as$item){$item->isDir()?$this->remove($item->getPathname()):unlink($item->getPathname());}rmdir($directory);}
}
