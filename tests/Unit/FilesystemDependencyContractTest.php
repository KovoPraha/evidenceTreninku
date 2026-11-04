<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

final class FilesystemDependencyContractTest extends TestCase
{
    public function testSchemeLikeSegmentsCannotEscapeTheBaseDirectory(): void
    {
        self::assertFalse(Path::isBasePath('/base', '/base/../x://../secret.txt'));
        self::assertTrue(Path::isBasePath('/base', '/base/assets/image.png'));
        self::assertSame('phar://archive.phar/file.txt', Path::canonicalize('phar://archive.phar/dir/../file.txt'));
    }
}
