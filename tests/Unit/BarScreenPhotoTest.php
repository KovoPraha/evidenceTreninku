<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/bar/screen_photo.php';

final class BarScreenPhotoTest extends TestCase
{
    public function testLargePhotoBecomesSmallerJpegWithoutChangingOriginal(): void
    {
        $source = imagecreatetruecolor(2048, 2048);
        self::assertInstanceOf(\GdImage::class, $source);
        imagefill($source, 0, 0, imagecolorallocate($source, 210, 80, 30));
        $path = tempnam(sys_get_temp_dir(), 'bar-photo-');
        self::assertIsString($path);
        try {
            self::assertTrue(imagejpeg($source, $path, 90));
            $original = file_get_contents($path);
            self::assertIsString($original);
            $preview = \barScreenPhotoBytes($path);
            self::assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->buffer($preview));
            self::assertSame([960, 960], array_slice(getimagesizefromstring($preview), 0, 2));
            self::assertLessThan(strlen($original), strlen($preview));
            self::assertSame($original, file_get_contents($path));
        } finally {
            imagedestroy($source);
            unlink($path);
        }
    }

    public function testTransparentLandscapePhotoKeepsAspectRatioOnDarkBackground(): void
    {
        $source = imagecreatetruecolor(1080, 710);
        self::assertInstanceOf(\GdImage::class, $source);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
        $path = tempnam(sys_get_temp_dir(), 'bar-photo-');
        self::assertIsString($path);
        try {
            self::assertTrue(imagepng($source, $path));
            $preview = \barScreenPhotoBytes($path);
            self::assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->buffer($preview));
            self::assertSame([960, 631], array_slice(getimagesizefromstring($preview), 0, 2));
            $decoded = imagecreatefromstring($preview);
            self::assertInstanceOf(\GdImage::class, $decoded);
            self::assertLessThan(35, imagecolorat($decoded, 0, 0) & 0xff);
            imagedestroy($decoded);
        } finally {
            imagedestroy($source);
            unlink($path);
        }
    }
}
