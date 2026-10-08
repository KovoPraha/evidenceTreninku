<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SecureUploadException;

require_once dirname(__DIR__, 2) . '/includes/secure_upload.php';

final class SecureUploadTest extends TestCase
{
    public function testImageIsDecodedAndAlwaysStoredAsJpeg(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'kis-upload-source-');
        $target = tempnam(sys_get_temp_dir(), 'kis-upload-target-') . '.jpg';
        self::assertIsString($source);
        $image = imagecreatetruecolor(4, 3);
        self::assertInstanceOf(\GdImage::class, $image);
        imagepng($image, $source);
        imagedestroy($image);
        try {
            $metadata = \secureUploadReencodeImageToJpeg($source, $target, false);
            self::assertSame('image/jpeg', $metadata['mime_type']);
            self::assertSame(4, $metadata['width_px']);
            self::assertSame(3, $metadata['height_px']);
            self::assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->file($target));
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    public function testGifHtmlPolyglotIsReducedToPassiveJpegPixels(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'kis-upload-polyglot-');
        $target = tempnam(sys_get_temp_dir(), 'kis-upload-target-') . '.jpg';
        self::assertIsString($source);
        $image = imagecreatetruecolor(2, 2);
        self::assertInstanceOf(\GdImage::class, $image);
        imagegif($image, $source);
        imagedestroy($image);
        file_put_contents($source, '<script>alert(1)</script>', FILE_APPEND);
        try {
            \secureUploadReencodeImageToJpeg($source, $target, false);
            self::assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->file($target));
            self::assertStringNotContainsString('<script>', (string)file_get_contents($target));
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    public function testFileCountIsBounded(): void
    {
        $this->expectException(SecureUploadException::class);
        \secureUploadAssertFileCount(array_fill(0, SECURE_UPLOAD_IMAGE_MAX_FILES + 1, 'x.jpg'));
    }
}
