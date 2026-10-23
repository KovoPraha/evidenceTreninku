<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/bin/finance-b1-router.php';

final class FinanceB1RouterTest extends TestCase
{
    public function testLocalPublicLoginRemainsReachable(): void
    {
        self::assertSame(realpath(dirname(__DIR__, 2) . '/login.php'), financeB1PublicPath('/login.php?next=%2F', dirname(__DIR__, 2)));
    }

    public function testPrivatePathsAndWindowsAliasesNeverReachBuiltInServer(): void
    {
        foreach (['/.git','/.git/config','/%2egit/config','/database/local-demo-schema.sql','/docs/CURRENT_STATE.md','/vendor/autoload.php','/includes/auth.php','/config.php','/db.php','/csrf_helper.php','/config.php/x','/bin/prepare-club-local-review.php','/tests/Support/FinanceB1HttpAcceptance.php','/uploads/uctenky/a.pdf','/nahrane_zavody/results/a.pdf','/stories/a.php','/uploads/a.svg','/login.php::$DATA','/login.php.','/login.php%20','/a/../login.php','/%252e%252e/config.php','/..%5cconfig.php','/login.php%00','/not-present.php','//config.php'] as $path) {
            self::assertNull(financeB1PublicPath($path, dirname(__DIR__, 2)), $path);
        }
    }

    public function testOldReportDirectoryIsAlwaysPrivate(): void
    {
        self::assertNull(financeB1PublicPath('/reports/weekly/2026/report.pdf', dirname(__DIR__, 2)));
    }
}
